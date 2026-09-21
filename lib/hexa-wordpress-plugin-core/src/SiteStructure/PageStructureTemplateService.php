<?php

namespace Hexa\PluginCore\SiteStructure;

final class PageStructureTemplateService {
    private PageStructureManager $manager;

    /**
     * @var array<string,mixed>
     */
    private array $config;

    /**
     * @param array<string,mixed> $config
     */
    public function __construct( PageStructureManager $manager, array $config ) {
        $this->manager = $manager;
        $this->config  = $config;
    }

    public function default_template( string $page_key ): string {
        if ( isset( $this->config['default_template_getter'] ) && is_callable( $this->config['default_template_getter'] ) ) {
            return (string) call_user_func( $this->config['default_template_getter'], $page_key );
        }

        $templates = is_array( $this->config['default_templates'] ) ? $this->config['default_templates'] : [];
        if ( isset( $templates[ $page_key ] ) && is_scalar( $templates[ $page_key ] ) ) {
            return (string) $templates[ $page_key ];
        }

        $flat_pages = $this->manager->flat_pages();
        if ( isset( $flat_pages[ $page_key ]['template_content'] ) && is_scalar( $flat_pages[ $page_key ]['template_content'] ) ) {
            return (string) $flat_pages[ $page_key ]['template_content'];
        }

        return '';
    }

    public function stored_template( string $page_key ): string {
        if ( isset( $this->config['template_getter'] ) && is_callable( $this->config['template_getter'] ) ) {
            return (string) call_user_func( $this->config['template_getter'], $page_key );
        }

        $prefix = (string) $this->config['template_option_prefix'];
        if ( '' !== $prefix && function_exists( 'get_option' ) ) {
            return (string) get_option( $prefix . $page_key, '' );
        }

        return '';
    }

    public function template_content( string $page_key ): string {
        $stored = trim( $this->stored_template( $page_key ) );

        return '' !== $stored ? $stored : $this->default_template( $page_key );
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function page_workspace_payload( string $page_key, int $page_id = 0 ): array|\WP_Error {
        $pages = $this->manager->flat_pages();
        if ( '' === $page_key || ! isset( $pages[ $page_key ] ) ) {
            return new \WP_Error( 'unknown_page_key', 'Unknown page key.' );
        }

        $page_id = $page_id > 0 ? $page_id : $this->manager->assigned_page_id( $page_key );
        $page     = $pages[ $page_key ];
        $payload  = $page_id > 0 ? $this->page_payload( $page_id ) : [];

        return array_merge(
            $payload,
            [
                'page_key'    => $page_key,
                'page_id'     => $page_id,
                'title'       => (string) ( $payload['title'] ?? $page['title'] ?? $page_key ),
                'slug'        => (string) ( $payload['slug'] ?? $page['slug'] ?? $page_key ),
                'assigned'    => $page_id > 0,
                'detail_html' => (string) ( $payload['detail_html'] ?? '' ),
                'template'    => $this->template_content( $page_key ),
            ]
        );
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function save_template( string $page_key, string $template ): array|\WP_Error {
        $flat_pages = $this->manager->flat_pages();
        if ( '' === $page_key || ! isset( $flat_pages[ $page_key ] ) ) {
            return new \WP_Error( 'unknown_page_key', 'Unknown page key.' );
        }

        $template = ! empty( $this->config['template_kses'] ) && function_exists( 'wp_kses_post' ) ? wp_kses_post( $template ) : $template;

        if ( isset( $this->config['template_saver'] ) && is_callable( $this->config['template_saver'] ) ) {
            call_user_func( $this->config['template_saver'], $page_key, $template );
        } else {
            $prefix = (string) $this->config['template_option_prefix'];
            if ( '' === $prefix || ! function_exists( 'update_option' ) ) {
                return new \WP_Error( 'template_storage_missing', 'No template storage was configured.' );
            }

            update_option( $prefix . $page_key, $template, false );
        }

        $this->log( 'Page template saved: ' . $page_key );

        return [
            'page_key' => $page_key,
            'template' => $template,
            'message'  => 'Template saved.',
        ];
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function apply_template( string $page_key, int $page_id = 0, bool $force = false ): array|\WP_Error {
        $flat_pages = $this->manager->flat_pages();
        if ( '' === $page_key || ! isset( $flat_pages[ $page_key ] ) ) {
            return new \WP_Error( 'unknown_page_key', 'Unknown page key.' );
        }

        $page_id = $page_id > 0 ? $page_id : $this->manager->assigned_page_id( $page_key );
        $post    = $page_id > 0 && function_exists( 'get_post' ) ? get_post( $page_id ) : null;
        if ( ! $post instanceof \WP_Post || 'page' !== $post->post_type ) {
            return new \WP_Error( 'page_not_found', 'Selected page was not found.' );
        }

        $plain_content = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( (string) $post->post_content ) : strip_tags( (string) $post->post_content );
        if ( ! $force && '' !== trim( $plain_content ) ) {
            return new \WP_Error( 'has_content', 'Page already has content. Confirm before overwriting it with the stored template.' );
        }

        $content = $this->template_content( $page_key );
        if ( '' === trim( $content ) ) {
            return new \WP_Error( 'empty_template', 'Template content is empty.' );
        }

        $updated = function_exists( 'wp_update_post' ) ? wp_update_post(
            [
                'ID'           => $page_id,
                'post_content' => $content,
            ],
            true
        ) : new \WP_Error( 'wordpress_unavailable', 'WordPress page updates are unavailable.' );

        if ( is_wp_error( $updated ) ) {
            return $updated;
        }

        $this->log( 'Page template applied: ' . $page_key . ' -> ' . $page_id );

        return array_merge(
            $this->page_payload( $page_id ),
            [
                'page_key' => $page_key,
                'message'  => 'Template applied.',
            ]
        );
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function update_page_slug( string $page_key, int $page_id, string $slug ): array|\WP_Error {
        $flat_pages = $this->manager->flat_pages();
        if ( '' === $page_key || ! isset( $flat_pages[ $page_key ] ) ) {
            return new \WP_Error( 'unknown_page_key', 'Unknown page key.' );
        }

        $post = $page_id > 0 && function_exists( 'get_post' ) ? get_post( $page_id ) : null;
        if ( ! $post instanceof \WP_Post || 'page' !== $post->post_type ) {
            return new \WP_Error( 'page_not_found', 'Selected page was not found.' );
        }

        $requested = function_exists( 'sanitize_title' ) ? sanitize_title( $slug ) : strtolower( preg_replace( '/[^a-z0-9-]+/', '-', $slug ) );
        if ( '' === $requested ) {
            return new \WP_Error( 'missing_slug', 'Slug cannot be empty.' );
        }

        $unique = function_exists( 'wp_unique_post_slug' ) ? wp_unique_post_slug( $requested, $page_id, $post->post_status, 'page', (int) $post->post_parent ) : $requested;
        $updated = function_exists( 'wp_update_post' ) ? wp_update_post(
            [
                'ID'        => $page_id,
                'post_name' => $unique,
            ],
            true
        ) : new \WP_Error( 'wordpress_unavailable', 'WordPress page updates are unavailable.' );

        if ( is_wp_error( $updated ) ) {
            return $updated;
        }

        if ( function_exists( 'clean_post_cache' ) ) {
            clean_post_cache( $page_id );
        }

        $this->log( 'Page slug updated: ' . $page_key . ' -> ' . $unique );

        return array_merge(
            $this->page_payload( $page_id ),
            [
                'page_key'       => $page_key,
                'requested_slug' => $requested,
                'slug_adjusted'  => $unique !== $requested,
                'message'        => $unique === $requested ? 'Slug updated.' : 'Slug updated with a unique suffix because the requested slug was unavailable.',
            ]
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function page_payload( int $page_id, bool $existing = false, string $message = '' ): array {
        $post = function_exists( 'get_post' ) ? get_post( $page_id ) : null;
        $status = $post instanceof \WP_Post ? (string) $post->post_status : '';
        $status_obj = '' !== $status && function_exists( 'get_post_status_object' ) ? get_post_status_object( $status ) : null;
        $detail_html = '';
        if ( isset( $this->config['page_detail_renderer'] ) && is_callable( $this->config['page_detail_renderer'] ) ) {
            $detail_html = (string) call_user_func( $this->config['page_detail_renderer'], $page_id );
        }

        return [
            'page_id'   => $page_id,
            'id'        => $page_id,
            'existing'  => $existing,
            'permalink' => function_exists( 'get_permalink' ) ? get_permalink( $page_id ) : '',
            'view_url'  => function_exists( 'get_permalink' ) ? get_permalink( $page_id ) : '',
            'edit_url'  => function_exists( 'get_edit_post_link' ) ? ( get_edit_post_link( $page_id, 'raw' ) ?: '' ) : '',
            'title'     => function_exists( 'get_the_title' ) ? get_the_title( $page_id ) : '',
            'status'    => $status,
            'status_label' => $status_obj ? (string) $status_obj->label : ( '' !== $status ? ucfirst( $status ) : '' ),
            'slug'      => $post instanceof \WP_Post ? (string) $post->post_name : '',
            'post_type' => $post instanceof \WP_Post ? (string) $post->post_type : '',
            'date'      => function_exists( 'get_the_date' ) ? get_the_date( 'M j, Y g:i a', $page_id ) : '',
            'modified'  => function_exists( 'get_the_modified_date' ) ? get_the_modified_date( 'M j, Y g:i a', $page_id ) : '',
            'author'    => $post instanceof \WP_Post && function_exists( 'get_the_author_meta' ) ? get_the_author_meta( 'display_name', (int) $post->post_author ) : '',
            'detail_html' => $detail_html,
            'message'   => $message,
        ];
    }

    private function log( string $message ): void {
        if ( isset( $this->config['logger'] ) && is_callable( $this->config['logger'] ) ) {
            call_user_func( $this->config['logger'], $message );
        }
    }
}
