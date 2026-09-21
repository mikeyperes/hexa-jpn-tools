<?php

namespace Hexa\PluginCore\ContentCleanup;

final class ArticleMediaCleanupConfig {
    private array $values;

    public function __construct( array $values = [] ) {
        $defaults = [
            'root_id'             => 'hpc-article-media-cleanup',
            'title'               => 'Article & Media Cleanup',
            'description'         => 'Filter articles, preview matches, and run AJAX batch deletion against all matching posts with optional associated media cleanup.',
            'capability'          => 'manage_options',
            'nonce_action'        => 'hpc_article_media_cleanup',
            'nonce_field'         => 'nonce',
            'scan_action'         => 'hpc_article_media_cleanup_scan',
            'delete_action'       => 'hpc_article_media_cleanup_delete',
            'batch_delete_action' => 'hpc_article_media_cleanup_batch_delete',
            'auto_scan'           => false,
            'post_types'          => [ 'post' => 'Posts' ],
            'statuses'            => [
                'publish' => 'Published',
                'draft'   => 'Draft',
                'private' => 'Private',
                'pending' => 'Pending',
                'any'     => 'Any active status',
            ],
            'default_post_type'   => 'post',
            'default_status'      => 'publish',
            'default_keep_recent' => 0,
            'default_limit'       => 50,
            'max_limit'           => 250,
            'default_batch_size'  => 50,
            'max_batch_size'      => 100,
            'empty_message'       => 'No matching articles were detected for the selected filters.',
        ];

        $values = array_merge( $defaults, $values );
        $values['root_id']             = $this->clean_html_id( (string) $values['root_id'] );
        $values['nonce_field']         = $this->clean_key( (string) $values['nonce_field'] );
        $values['scan_action']         = $this->clean_key( (string) $values['scan_action'] );
        $values['delete_action']       = $this->clean_key( (string) $values['delete_action'] );
        $values['batch_delete_action'] = $this->clean_key( (string) $values['batch_delete_action'] );
        $values['post_types']          = $this->normalize_options( (array) $values['post_types'], [ 'post' => 'Posts' ] );
        $values['statuses']            = $this->normalize_options( (array) $values['statuses'], [ 'publish' => 'Published' ] );
        $values['default_post_type']   = $this->clean_key( (string) $values['default_post_type'] );
        $values['default_status']      = $this->clean_key( (string) $values['default_status'] );
        $values['default_keep_recent'] = max( 0, (int) $values['default_keep_recent'] );
        $values['default_limit']       = max( 1, (int) $values['default_limit'] );
        $values['max_limit']           = max( 1, (int) $values['max_limit'] );
        $values['default_batch_size']  = max( 1, (int) $values['default_batch_size'] );
        $values['max_batch_size']      = max( 1, (int) $values['max_batch_size'] );

        if ( ! isset( $values['post_types'][ $values['default_post_type'] ] ) ) {
            $values['default_post_type'] = array_key_first( $values['post_types'] );
        }

        if ( ! isset( $values['statuses'][ $values['default_status'] ] ) ) {
            $values['default_status'] = array_key_first( $values['statuses'] );
        }

        $this->values = $values;
    }

    public function get( string $key, mixed $default = null ): mixed {
        return array_key_exists( $key, $this->values ) ? $this->values[ $key ] : $default;
    }

    public function root_id(): string {
        return (string) $this->get( 'root_id' );
    }

    public function capability(): string {
        return (string) $this->get( 'capability', 'manage_options' );
    }

    public function nonce_action(): string {
        return (string) $this->get( 'nonce_action' );
    }

    public function nonce_field(): string {
        return (string) $this->get( 'nonce_field', 'nonce' );
    }

    public function scan_action(): string {
        return (string) $this->get( 'scan_action' );
    }

    public function delete_action(): string {
        return (string) $this->get( 'delete_action' );
    }

    public function batch_delete_action(): string {
        return (string) $this->get( 'batch_delete_action' );
    }

    public function post_types(): array {
        return (array) $this->get( 'post_types', [] );
    }

    public function statuses(): array {
        return (array) $this->get( 'statuses', [] );
    }

    public function default_criteria(): array {
        return [
            'post_type'   => (string) $this->get( 'default_post_type', 'post' ),
            'status'      => (string) $this->get( 'default_status', 'publish' ),
            'keep_recent' => (int) $this->get( 'default_keep_recent', 0 ),
            'search'      => '',
            'limit'       => (int) $this->get( 'default_limit', 50 ),
        ];
    }

    public function max_limit(): int {
        return (int) $this->get( 'max_limit', 250 );
    }

    public function default_batch_size(): int {
        return (int) $this->get( 'default_batch_size', 50 );
    }

    public function max_batch_size(): int {
        return (int) $this->get( 'max_batch_size', 100 );
    }

    public function auto_scan(): bool {
        return (bool) $this->get( 'auto_scan', false );
    }

    private function normalize_options( array $options, array $fallback ): array {
        $normalized = [];
        foreach ( $options as $key => $label ) {
            $key = $this->clean_key( is_string( $key ) ? $key : (string) $label );
            if ( '' === $key ) {
                continue;
            }
            $normalized[ $key ] = is_scalar( $label ) ? (string) $label : $key;
        }

        return [] !== $normalized ? $normalized : $fallback;
    }

    private function clean_key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : ( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?: '' );
    }

    private function clean_html_id( string $value ): string {
        $value = trim( $value );
        if ( '' === $value ) {
            return 'hpc-article-media-cleanup';
        }

        return function_exists( 'sanitize_html_class' ) ? sanitize_html_class( $value ) : ( preg_replace( '/[^a-zA-Z0-9_\-]/', '-', $value ) ?: 'hpc-article-media-cleanup' );
    }
}
