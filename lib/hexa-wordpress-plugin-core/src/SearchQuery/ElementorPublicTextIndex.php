<?php

namespace Hexa\PluginCore\SearchQuery;

/**
 * Maintains a bounded search-only index of public Elementor document text.
 *
 * Raw Elementor document data is never searched. The default extractor renders
 * the document through Elementor's public frontend API as an anonymous visitor,
 * then discards markup, attributes, scripts, styles, and private widget settings.
 */
final class ElementorPublicTextIndex {
    public const META_KEY = '_hexa_elementor_public_text';

    /** @var callable|null */
    private $extractor;

    /** @var string[] */
    private array $post_types;

    private int $max_characters;

    private bool $registered = false;

    private bool $shutdown_registered = false;

    /** @var array<int,true> */
    private array $queued_post_ids = [];

    /** @var array<int,true> */
    private array $syncing_post_ids = [];

    /** @var array<int,int[]> */
    private array $template_dependents = [];

    /**
     * @param string[] $post_types Public post types eligible for this index.
     * @param callable|null $extractor Optional deterministic HTML extractor for tests or another compatible builder.
     */
    public function __construct( array $post_types = [], ?callable $extractor = null, int $max_characters = 100000 ) {
        $this->post_types = array_values( array_unique( array_filter( array_map( [ self::class, 'key' ], $post_types ) ) ) );
        $this->extractor = $extractor;
        $this->max_characters = max( 1000, min( 250000, $max_characters ) );
    }

    public function register(): void {
        if ( $this->registered || ! function_exists( 'add_action' ) ) {
            return;
        }

        add_action( 'elementor/editor/after_save', [ $this, 'queue_elementor_save' ], 20, 1 );
        add_action( 'added_post_meta', [ $this, 'queue_elementor_meta_change' ], 20, 4 );
        add_action( 'updated_post_meta', [ $this, 'queue_elementor_meta_change' ], 20, 4 );
        add_action( 'deleted_post_meta', [ $this, 'queue_elementor_meta_change' ], 20, 4 );
        add_action( 'save_post', [ $this, 'queue_post_save' ], 100, 3 );
        $this->registered = true;
    }

    /** @param mixed $post_id */
    public function queue_elementor_save( $post_id ): void {
        $this->queue( (int) $post_id );
    }

    /** @param mixed $meta_id @param mixed $post_id @param mixed $meta_key @param mixed $meta_value */
    public function queue_elementor_meta_change( $meta_id, $post_id, $meta_key, $meta_value = null ): void {
        if ( '_elementor_data' === (string) $meta_key ) {
            $this->queue( (int) $post_id );
        }
    }

    /** @param mixed $post_id @param mixed $post @param mixed $update */
    public function queue_post_save( $post_id, $post = null, $update = false ): void {
        $post_id = (int) $post_id;
        if ( $post_id < 1
            || ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) )
            || ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $post_id ) )
        ) {
            return;
        }

        $this->queue( $post_id );
    }

    public function flush_queue(): void {
        $post_ids = array_keys( $this->queued_post_ids );
        $this->queued_post_ids = [];
        $this->shutdown_registered = false;
        sort( $post_ids, SORT_NUMERIC );

        foreach ( $post_ids as $post_id ) {
            $this->sync_post( (int) $post_id );
        }
    }

    /**
     * @return array{post_id:int,status:string,characters:int,before_hash:string|null,after_hash:string|null,changed:bool}
     */
    public function sync_post( int $post_id ): array {
        return $this->index_post( $post_id, false );
    }

    /**
     * Returns the exact bounded stored-data delta without writing it.
     *
     * @return array{post_id:int,status:string,characters:int,before_hash:string|null,after_hash:string|null,changed:bool}
     */
    public function preview_post( int $post_id ): array {
        return $this->index_post( $post_id, true );
    }

    /**
     * @return array{post_id:int,status:string,characters:int,before_hash:string|null,after_hash:string|null,changed:bool}
     */
    private function index_post( int $post_id, bool $dry_run ): array {
        if ( $post_id < 1 || isset( $this->syncing_post_ids[ $post_id ] ) ) {
            return $this->item_result( $post_id, 'skipped', '', '', false );
        }

        $this->syncing_post_ids[ $post_id ] = true;
        try {
            $before = function_exists( 'get_post_meta' ) ? (string) get_post_meta( $post_id, self::META_KEY, true ) : '';
            if ( ! $this->eligible_post( $post_id ) ) {
                if ( '' === $before ) {
                    return $this->item_result( $post_id, 'unchanged', $before, '', false );
                }
                if ( ! $dry_run ) {
                    $this->remove_index( $post_id );
                }
                return $this->item_result( $post_id, $dry_run ? 'would_remove' : 'removed', $before, '', true );
            }

            $html = $this->extract( $post_id );
            if ( null === $html ) {
                return $this->item_result( $post_id, 'unavailable', $before, $before, false );
            }

            $text = self::normalize_text( $html, $this->max_characters );
            if ( $text === $before ) {
                return $this->item_result( $post_id, 'unchanged', $before, $text, false );
            }

            if ( $dry_run ) {
                return $this->item_result(
                    $post_id,
                    '' === $text ? 'would_remove' : 'would_index',
                    $before,
                    $text,
                    true
                );
            }

            if ( '' === $text ) {
                $this->remove_index( $post_id );
                return $this->item_result( $post_id, 'removed', $before, '', true );
            }

            update_post_meta( $post_id, self::META_KEY, $text );

            return $this->item_result( $post_id, 'indexed', $before, $text, true );
        } catch ( \Throwable $exception ) {
            $before = isset( $before ) ? $before : '';
            return $this->item_result( $post_id, 'failed', $before, $before, false );
        } finally {
            unset( $this->syncing_post_ids[ $post_id ] );
        }
    }

    /**
     * Rebuilds one bounded page of published Elementor documents.
     *
     * @return array<string,mixed>
     */
    public function rebuild( int $page = 1, int $per_page = 100, bool $dry_run = false ): array {
        $page = max( 1, $page );
        $per_page = max( 1, min( 200, $per_page ) );
        $post_types = $this->allowed_post_types();
        if ( [] === $post_types || ! class_exists( '\\WP_Query' ) ) {
            return $this->rebuild_result( $page, $per_page, 0, 0, $dry_run, [], [] );
        }

        $query = new \WP_Query(
            [
                'post_type'              => $post_types,
                'post_status'            => 'publish',
                'has_password'           => false,
                'posts_per_page'         => $per_page,
                'paged'                  => $page,
                'fields'                 => 'ids',
                'orderby'                => 'ID',
                'order'                  => 'ASC',
                'ignore_sticky_posts'    => true,
                'no_found_rows'          => false,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query'             => [
                    [
                        'key'     => '_elementor_edit_mode',
                        'value'   => 'builder',
                        'compare' => '=',
                    ],
                ],
            ]
        );

        $statuses = [];
        $items = [];
        foreach ( array_map( 'intval', (array) $query->posts ) as $post_id ) {
            $result = $dry_run ? $this->preview_post( $post_id ) : $this->sync_post( $post_id );
            $status = (string) $result['status'];
            $statuses[ $status ] = ( $statuses[ $status ] ?? 0 ) + 1;
            $items[] = $result;
        }

        return $this->rebuild_result(
            $page,
            $per_page,
            (int) $query->found_posts,
            (int) $query->max_num_pages,
            $dry_run,
            $statuses,
            $items
        );
    }

    public static function normalize_text( string $html, int $max_characters = 100000 ): string {
        $max_characters = max( 1000, min( 250000, $max_characters ) );
        $text = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $html, true ) : strip_tags( $html );
        $charset = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'charset' ) : 'UTF-8';
        $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, '' !== $charset ? $charset : 'UTF-8' );
        $normalized = preg_replace( '/[\s\x{00A0}]+/u', ' ', $text );
        $text = trim( is_string( $normalized ) ? $normalized : $text );

        if ( self::length( $text ) > $max_characters ) {
            $text = function_exists( 'mb_substr' )
                ? (string) mb_substr( $text, 0, $max_characters, 'UTF-8' )
                : substr( $text, 0, $max_characters );
        }

        return $text;
    }

    private function queue( int $post_id ): void {
        if ( $post_id < 1 ) {
            return;
        }

        if ( 'elementor_library' === (string) get_post_type( $post_id ) ) {
            foreach ( $this->dependent_public_post_ids( $post_id ) as $dependent_post_id ) {
                $this->queued_post_ids[ $dependent_post_id ] = true;
            }
        } else {
            $this->queued_post_ids[ $post_id ] = true;
        }
        if ( [] === $this->queued_post_ids ) {
            return;
        }
        if ( ! $this->shutdown_registered ) {
            add_action( 'shutdown', [ $this, 'flush_queue' ], 20 );
            $this->shutdown_registered = true;
        }
    }

    private function eligible_post( int $post_id ): bool {
        $post = get_post( $post_id );
        if ( ! is_object( $post )
            || 'publish' !== (string) ( $post->post_status ?? '' )
            || '' !== (string) ( $post->post_password ?? '' )
            || ! in_array( self::key( (string) ( $post->post_type ?? '' ) ), $this->allowed_post_types(), true )
        ) {
            return false;
        }

        $post_type = get_post_type_object( (string) $post->post_type );

        return is_object( $post_type ) && ! empty( $post_type->public ) && empty( $post_type->exclude_from_search );
    }

    private function extract( int $post_id ): ?string {
        if ( null !== $this->extractor ) {
            $value = call_user_func( $this->extractor, $post_id );
            return is_string( $value ) ? $value : null;
        }

        if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
            return null;
        }

        $plugin = \Elementor\Plugin::$instance;
        if ( ! is_object( $plugin ) || ! isset( $plugin->documents, $plugin->frontend )
            || ! method_exists( $plugin->documents, 'get' )
            || ! method_exists( $plugin->frontend, 'get_builder_content_for_display' )
        ) {
            return null;
        }

        $document = $plugin->documents->get( $post_id );
        if ( ! is_object( $document ) || ! method_exists( $document, 'is_built_with_elementor' )
            || ! $document->is_built_with_elementor()
        ) {
            return '';
        }

        $had_post = array_key_exists( 'post', $GLOBALS );
        $previous_post = $GLOBALS['post'] ?? null;
        $previous_user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;

        try {
            unset( $GLOBALS['post'] );
            if ( function_exists( 'wp_set_current_user' ) ) {
                wp_set_current_user( 0 );
            }

            return (string) $plugin->frontend->get_builder_content_for_display( $post_id, false );
        } finally {
            if ( $had_post ) {
                $GLOBALS['post'] = $previous_post;
            } else {
                unset( $GLOBALS['post'] );
            }
            if ( function_exists( 'wp_set_current_user' ) ) {
                wp_set_current_user( $previous_user_id );
            }
        }
    }

    /** @return string[] */
    private function allowed_post_types(): array {
        if ( [] !== $this->post_types ) {
            return $this->post_types;
        }

        $objects = function_exists( 'get_post_types' ) ? get_post_types( [ 'public' => true ], 'objects' ) : [];
        $post_types = [];
        foreach ( is_array( $objects ) ? $objects : [] as $name => $object ) {
            $name = self::key( (string) $name );
            if ( '' !== $name && 'attachment' !== $name && is_object( $object ) && empty( $object->exclude_from_search ) ) {
                $post_types[] = $name;
            }
        }

        return array_values( array_unique( $post_types ) );
    }

    private function remove_index( int $post_id ): void {
        if ( function_exists( 'delete_post_meta' ) ) {
            delete_post_meta( $post_id, self::META_KEY );
        }
    }

    /**
     * Resolves public Elementor documents that directly or transitively embed
     * one saved template. Only exact template IDs are read from structured
     * document objects; private widget settings are never inspected or stored.
     *
     * @return int[]
     */
    private function dependent_public_post_ids( int $template_id ): array {
        if ( isset( $this->template_dependents[ $template_id ] ) ) {
            return $this->template_dependents[ $template_id ];
        }
        if ( ! class_exists( '\\WP_Query' ) || ! class_exists( '\\Elementor\\Plugin' ) ) {
            return [];
        }

        $post_types = array_values( array_unique( array_merge( $this->allowed_post_types(), [ 'elementor_library' ] ) ) );
        $query = new \WP_Query(
            [
                'post_type'              => $post_types,
                'post_status'            => 'publish',
                'posts_per_page'         => 2000,
                'fields'                 => 'ids',
                'orderby'                => 'ID',
                'order'                  => 'ASC',
                'ignore_sticky_posts'    => true,
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query'             => [
                    [
                        'key'     => '_elementor_edit_mode',
                        'value'   => 'builder',
                        'compare' => '=',
                    ],
                ],
            ]
        );

        $reverse = [];
        foreach ( array_map( 'intval', (array) $query->posts ) as $document_id ) {
            foreach ( $this->template_reference_ids( $document_id ) as $referenced_template_id ) {
                $reverse[ $referenced_template_id ][ $document_id ] = true;
            }
        }

        $public_types = $this->allowed_post_types();
        $pending = [ $template_id ];
        $visited = [];
        $dependents = [];
        while ( [] !== $pending && count( $visited ) < 100 ) {
            $current = (int) array_shift( $pending );
            if ( isset( $visited[ $current ] ) ) {
                continue;
            }
            $visited[ $current ] = true;

            foreach ( array_keys( $reverse[ $current ] ?? [] ) as $document_id ) {
                $document_id = (int) $document_id;
                $post_type = (string) get_post_type( $document_id );
                if ( 'elementor_library' === $post_type ) {
                    $pending[] = $document_id;
                } elseif ( in_array( $post_type, $public_types, true ) ) {
                    $dependents[ $document_id ] = true;
                }
            }
        }

        $ids = array_map( 'intval', array_keys( $dependents ) );
        sort( $ids, SORT_NUMERIC );
        $this->template_dependents[ $template_id ] = $ids;

        return $ids;
    }

    /** @return int[] */
    private function template_reference_ids( int $post_id ): array {
        $plugin = \Elementor\Plugin::$instance;
        if ( ! is_object( $plugin ) || ! isset( $plugin->documents ) || ! method_exists( $plugin->documents, 'get' ) ) {
            return [];
        }
        $document = $plugin->documents->get( $post_id );
        if ( ! is_object( $document ) || ! method_exists( $document, 'get_elements_data' ) ) {
            return [];
        }

        $references = [];
        $nodes_seen = 0;
        $this->collect_template_reference_ids( (array) $document->get_elements_data(), 0, $nodes_seen, $references );
        $ids = array_map( 'intval', array_keys( $references ) );
        sort( $ids, SORT_NUMERIC );

        return $ids;
    }

    /** @param array<int,mixed> $nodes @param array<int,true> $references */
    private function collect_template_reference_ids( array $nodes, int $depth, int &$nodes_seen, array &$references ): void {
        if ( $depth > 32 || $nodes_seen >= 10000 ) {
            return;
        }

        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) || ++$nodes_seen > 10000 ) {
                continue;
            }
            if ( 'template' === (string) ( $node['widgetType'] ?? '' ) ) {
                $template_id = (int) ( $node['settings']['template_id'] ?? 0 );
                if ( $template_id > 0 ) {
                    $references[ $template_id ] = true;
                }
            }
            if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
                $this->collect_template_reference_ids( $node['elements'], $depth + 1, $nodes_seen, $references );
            }
        }
    }

    /**
     * @param array<string,int> $statuses
     * @param array<int,array<string,mixed>> $items
     * @return array<string,mixed>
     */
    private function rebuild_result(
        int $page,
        int $per_page,
        int $total,
        int $total_pages,
        bool $dry_run,
        array $statuses,
        array $items
    ): array {
        return [
            'page'        => $page,
            'per_page'    => $per_page,
            'dry_run'     => $dry_run,
            'post_types'  => $this->allowed_post_types(),
            'total'       => $total,
            'total_pages' => $total_pages,
            'indexed'     => (int) ( $statuses['indexed'] ?? 0 ),
            'removed'     => (int) ( $statuses['removed'] ?? 0 ),
            'would_index' => (int) ( $statuses['would_index'] ?? 0 ),
            'would_remove'=> (int) ( $statuses['would_remove'] ?? 0 ),
            'unchanged'   => (int) ( $statuses['unchanged'] ?? 0 ),
            'unavailable' => (int) ( $statuses['unavailable'] ?? 0 ),
            'failed'      => (int) ( $statuses['failed'] ?? 0 ),
            'next_page'   => $page < $total_pages ? $page + 1 : null,
            'items'       => $items,
        ];
    }

    /**
     * @return array{post_id:int,status:string,characters:int,before_hash:string|null,after_hash:string|null,changed:bool}
     */
    private function item_result( int $post_id, string $status, string $before, string $after, bool $changed ): array {
        return [
            'post_id'    => $post_id,
            'status'     => $status,
            'characters' => self::length( $after ),
            'before_hash'=> '' !== $before ? hash( 'sha256', $before ) : null,
            'after_hash' => '' !== $after ? hash( 'sha256', $after ) : null,
            'changed'    => $changed,
        ];
    }

    private static function length( string $value ): int {
        return function_exists( 'mb_strlen' ) ? (int) mb_strlen( $value, 'UTF-8' ) : strlen( $value );
    }

    private static function key( string $value ): string {
        $value = strtolower( trim( $value ) );
        return (string) preg_replace( '/[^a-z0-9_\-]/', '', $value );
    }
}
