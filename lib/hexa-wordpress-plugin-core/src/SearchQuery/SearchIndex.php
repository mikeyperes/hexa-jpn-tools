<?php

namespace Hexa\PluginCore\SearchQuery;

/**
 * Optional full-text index for SearchQueryEngine.
 *
 * Stores one row per published post holding the same sources a host search
 * configuration matches (fields, taxonomy terms, author, custom fields, user
 * reference names), behind one InnoDB FULLTEXT key. The engine queries it with
 * MATCH ... AGAINST instead of scanning posts and postmeta with REGEXP.
 * Words shorter than the server's token size are pattern-matched on the one
 * indexed text column; the scan is used only until a full build finishes.
 */
final class SearchIndex {
    public const TABLE = 'hexa_search_index';
    public const SCHEMA_VERSION = '1';
    public const SCHEMA_OPTION = 'hexa_search_index_schema';
    public const READY_OPTION = 'hexa_search_index_ready';
    public const MAX_CHARACTERS = 200000;

    /** @var callable */
    private $settings_provider;

    private bool $registered = false;

    private bool $shutdown_registered = false;

    /** @var array<int,true> */
    private array $queued_post_ids = [];

    /** @var array<string,mixed>|null */
    private ?array $settings = null;

    /** @param callable $settings_provider Returns the same settings array given to SearchQueryEngine. */
    public function __construct( callable $settings_provider ) {
        $this->settings_provider = $settings_provider;
    }

    public static function table(): string {
        global $wpdb;

        return $wpdb->prefix . self::TABLE;
    }

    /** True once a full rebuild finished on the current schema. */
    public static function ready(): bool {
        return function_exists( 'get_option' ) && self::SCHEMA_VERSION === (string) get_option( self::READY_OPTION, '' );
    }

    /** Minimum indexed word length; shorter words use the scan fallback. */
    public static function min_token_length(): int {
        static $length = null;
        if ( null === $length ) {
            global $wpdb;
            $value = is_object( $wpdb ) ? $wpdb->get_var( "SELECT @@innodb_ft_min_token_size" ) : null;
            $length = max( 1, (int) ( $value ?: 3 ) );
        }

        return $length;
    }

    public function register(): void {
        if ( $this->registered || ! function_exists( 'add_action' ) ) {
            return;
        }

        add_action( 'save_post', [ $this, 'queue_post' ], 200, 1 );
        add_action( 'deleted_post', [ $this, 'queue_post' ], 20, 1 );
        add_action( 'set_object_terms', [ $this, 'queue_post' ], 20, 1 );
        add_action( 'added_post_meta', [ $this, 'queue_meta_change' ], 20, 3 );
        add_action( 'updated_post_meta', [ $this, 'queue_meta_change' ], 20, 3 );
        add_action( 'deleted_post_meta', [ $this, 'queue_meta_change' ], 20, 3 );
        $this->registered = true;
    }

    /** @param mixed $post_id */
    public function queue_post( $post_id ): void {
        $post_id = (int) $post_id;
        if ( $post_id < 1 || ! self::ready() ) {
            return;
        }
        $this->queued_post_ids[ $post_id ] = true;
        if ( ! $this->shutdown_registered ) {
            add_action( 'shutdown', [ $this, 'flush_queue' ], 30 );
            $this->shutdown_registered = true;
        }
    }

    /** @param mixed $meta_id @param mixed $post_id @param mixed $meta_key */
    public function queue_meta_change( $meta_id, $post_id, $meta_key ): void {
        $settings = $this->settings();
        $keys = array_merge( (array) $settings['custom_fields'], (array) $settings['user_reference_fields'] );
        if ( in_array( (string) $meta_key, $keys, true ) ) {
            $this->queue_post( $post_id );
        }
    }

    public function flush_queue(): void {
        $post_ids = array_keys( $this->queued_post_ids );
        $this->queued_post_ids = [];
        $this->shutdown_registered = false;
        foreach ( $post_ids as $post_id ) {
            $this->sync_post( (int) $post_id );
        }
    }

    /** Writes or removes one post's row. Returns indexed, removed or failed. */
    public function sync_post( int $post_id ): string {
        global $wpdb;
        $this->install();

        $text = $this->document_text( $post_id );
        if ( null === $text ) {
            $wpdb->delete( self::table(), [ 'post_id' => $post_id ], [ '%d' ] );
            return 'removed';
        }

        $written = $wpdb->replace(
            self::table(),
            [ 'post_id' => $post_id, 'post_type' => (string) get_post_type( $post_id ), 'body' => $text ],
            [ '%d', '%s', '%s' ]
        );

        return false === $written ? 'failed' : 'indexed';
    }

    /**
     * Rebuilds one page of eligible posts by ascending ID. Run pages until
     * `next_after_id` is null; the final page marks the index ready.
     *
     * @return array{indexed:int,failed:int,next_after_id:int|null,ready:bool}
     */
    public function rebuild( int $after_id = 0, int $per_page = 200 ): array {
        global $wpdb;
        $this->install();
        $settings = $this->settings();
        $per_page = max( 1, min( 1000, $per_page ) );
        $types = (array) $settings['post_types'];
        if ( 0 === $after_id ) {
            $wpdb->query( 'TRUNCATE TABLE ' . self::table() );
            delete_option( self::READY_OPTION );
        }

        $placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
        $ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_status = 'publish' AND post_password = '' AND post_type IN ($placeholders) ORDER BY ID ASC LIMIT %d",
            array_merge( [ $after_id ], $types, [ $per_page ] )
        ) ) );

        if ( [] !== $ids ) {
            update_meta_cache( 'post', $ids );
            update_object_term_cache( $ids, $types );
        }

        $counts = [ 'indexed' => 0, 'failed' => 0 ];
        foreach ( $ids as $post_id ) {
            $status = $this->sync_post( $post_id );
            if ( isset( $counts[ $status ] ) ) {
                ++$counts[ $status ];
            }
            wp_cache_delete( $post_id, 'posts' );
            wp_cache_delete( $post_id, 'post_meta' );
        }

        $done = count( $ids ) < $per_page;
        if ( $done ) {
            update_option( self::READY_OPTION, self::SCHEMA_VERSION, false );
        }

        return [
            'indexed'       => $counts['indexed'],
            'failed'        => $counts['failed'],
            'next_after_id' => $done ? null : (int) end( $ids ),
            'ready'         => $done,
        ];
    }

    /**
     * One post's searchable text, or null when the post is not searchable.
     */
    public function document_text( int $post_id ): ?string {
        $post = get_post( $post_id );
        $settings = $this->settings();
        if ( ! is_object( $post )
            || 'publish' !== $post->post_status
            || '' !== (string) $post->post_password
            || ! in_array( (string) $post->post_type, (array) $settings['post_types'], true )
        ) {
            return null;
        }

        $parts = [];
        $columns = [ 'title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt', 'slug' => 'post_name' ];
        foreach ( (array) $settings['fields'] as $field ) {
            if ( isset( $columns[ $field ] ) ) {
                $value = (string) $post->{$columns[ $field ]};
                $parts[] = 'slug' === $field ? str_replace( '-', ' ', $value ) : $value;
            }
        }
        foreach ( (array) $settings['taxonomies'] as $taxonomy ) {
            $terms = get_the_terms( $post, $taxonomy );
            foreach ( is_array( $terms ) ? $terms : [] as $term ) {
                $parts[] = (string) $term->name;
            }
        }
        if ( ! empty( $settings['authors'] ) ) {
            $parts[] = $this->user_name( (int) $post->post_author );
        }
        foreach ( (array) $settings['custom_fields'] as $meta_key ) {
            foreach ( (array) get_post_meta( $post_id, $meta_key, false ) as $value ) {
                if ( is_scalar( $value ) ) {
                    $parts[] = (string) $value;
                }
            }
        }
        foreach ( (array) $settings['user_reference_fields'] as $meta_key ) {
            foreach ( (array) get_post_meta( $post_id, $meta_key, false ) as $value ) {
                $parts[] = $this->user_name( (int) $value );
            }
        }

        return ElementorPublicTextIndex::normalize_text( implode( ' ', $parts ), self::MAX_CHARACTERS );
    }

    /**
     * Converts terms the index can answer into one BOOLEAN MODE expression.
     * Terms holding a word below the token size are returned for a pattern
     * match on the indexed text; with all-words logic their long words still
     * narrow the rows through the expression.
     *
     * @param string[] $terms
     * @return array{expression:string,remaining:string[]}
     */
    public static function boolean_expression( array $terms, string $term_logic, string $word_matching, int $min_length ): array {
        $clauses = [];
        $remaining = [];
        $operator = 'any' === $term_logic ? '' : '+';
        foreach ( $terms as $term ) {
            $words = preg_split( '/[^\p{L}\p{N}_]+/u', $term, -1, PREG_SPLIT_NO_EMPTY ) ?: [];
            $short = array_filter( $words, static fn( string $word ): bool => self::length( $word ) < $min_length );
            if ( [] === $words || [] !== $short ) {
                $remaining[] = $term;
                if ( '+' === $operator ) {
                    foreach ( array_diff( $words, $short ) as $word ) {
                        $clauses[] = '+' . $word . '*';
                    }
                }
                continue;
            }
            if ( count( $words ) > 1 ) {
                $clauses[] = $operator . '"' . implode( ' ', $words ) . '"';
                continue;
            }
            $clauses[] = $operator . $words[0] . ( 'whole' === $word_matching ? '' : '*' );
        }

        return [ 'expression' => implode( ' ', $clauses ), 'remaining' => $remaining ];
    }

    public function install(): void {
        if ( self::SCHEMA_VERSION === (string) get_option( self::SCHEMA_OPTION, '' ) ) {
            return;
        }
        global $wpdb;
        $wpdb->query(
            'CREATE TABLE IF NOT EXISTS ' . self::table() . ' ('
            . ' post_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,'
            . ' post_type VARCHAR(20) NOT NULL DEFAULT \'\','
            . ' body LONGTEXT NOT NULL,'
            . ' FULLTEXT KEY body (body)'
            . ') ENGINE=InnoDB ' . $wpdb->get_charset_collate()
        );
        update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, true );
    }

    /** @return array<string,mixed> */
    private function settings(): array {
        if ( null === $this->settings ) {
            $provided = call_user_func( $this->settings_provider );
            $provided = is_array( $provided ) ? $provided : [];
            $this->settings = SearchQueryConfiguration::normalize(
                $provided,
                (array) ( $provided['post_types'] ?? [] ),
                (array) ( $provided['taxonomies'] ?? [] )
            );
        }

        return $this->settings;
    }

    private function user_name( int $user_id ): string {
        $user = $user_id > 0 ? get_userdata( $user_id ) : false;

        return is_object( $user ) ? (string) $user->display_name : '';
    }

    private static function length( string $value ): int {
        return function_exists( 'mb_strlen' ) ? (int) mb_strlen( $value, 'UTF-8' ) : strlen( $value );
    }
}
