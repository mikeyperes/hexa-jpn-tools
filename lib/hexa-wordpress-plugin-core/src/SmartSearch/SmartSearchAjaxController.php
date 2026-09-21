<?php

namespace Hexa\PluginCore\SmartSearch;

final class SmartSearchAjaxController {
    private static bool $registered = false;

    public function register(): void {
        if ( self::$registered || ! function_exists( 'add_action' ) ) {
            return;
        }

        add_action( 'wp_ajax_hexa_plugin_core_smart_search', [ $this, 'search' ] );

        self::$registered = true;
    }

    public function search(): void {
        if ( function_exists( 'current_user_can' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
        }

        if ( function_exists( 'check_ajax_referer' ) ) {
            check_ajax_referer( 'hexa_plugin_core_smart_search' );
        }

        $source = isset( $_GET['source'] ) ? sanitize_key( (string) wp_unslash( $_GET['source'] ) ) : 'posts';
        $query  = isset( $_GET['q'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['q'] ) ) ) : '';
        $limit  = isset( $_GET['limit'] ) ? min( 50, max( 1, (int) $_GET['limit'] ) ) : 15;

        if ( strlen( $query ) < 2 ) {
            wp_send_json_success( [ 'results' => [] ] );
        }

        $results = match ( $source ) {
            'post_types' => $this->search_post_types( $query, $limit ),
            "users"      => $this->search_users( $query, $limit ),
            default      => $this->search_posts( $query, $limit ),
        };

        $results = apply_filters( 'hexa_plugin_core_smart_search_results', $results, $source, $query, $limit );

        wp_send_json_success( [ 'results' => array_values( $results ) ] );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function search_posts( string $query, int $limit ): array {
        $post_type = isset( $_GET['post_type'] ) ? sanitize_key( (string) wp_unslash( $_GET['post_type'] ) ) : 'any';

        $ids = get_posts(
            [
                's'                   => $query,
                'post_type'           => '' !== $post_type ? $post_type : 'any',
                'post_status'         => 'any',
                'posts_per_page'      => $limit,
                'fields'              => 'ids',
                'orderby'             => 'date',
                'order'               => 'DESC',
                'ignore_sticky_posts' => true,
                'suppress_filters'    => false,
            ]
        );

        $results = [];
        foreach ( $ids as $post_id ) {
            $post = get_post( $post_id );
            if ( ! $post ) {
                continue;
            }

            $type_object = get_post_type_object( $post->post_type );
            $type_label  = $type_object ? $type_object->labels->singular_name : $post->post_type;

            $results[] = [
                'id'       => (int) $post_id,
                'value'    => (int) $post_id,
                "label"    => get_the_title( $post_id ) ?: "(no title)",
                'name'     => get_the_title( $post_id ) ?: '(no title)',
                'subtitle' => $type_label . ' - ' . $post->post_status,
                'type'     => $post->post_type,
                'status'   => $post->post_status,
                'url'      => get_edit_post_link( $post_id, 'raw' ),
                "edit_url" => get_edit_post_link( $post_id, "raw" ),
                "view_url" => get_permalink( $post_id ),
                "thumbnail" => get_the_post_thumbnail_url( $post_id, "thumbnail" ) ?: "",
            ];
        }

        return $results;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function search_users( string $query, int $limit ): array {
        $users = get_users(
            [
                "number"         => $limit,
                "orderby"        => "display_name",
                "order"          => "ASC",
                "fields"         => "all",
                "search"         => "*" . $query . "*",
                "search_columns" => [ "user_login", "user_nicename", "user_email", "display_name" ],
            ]
        );

        $results = [];
        foreach ( $users as $user ) {
            if ( ! $user instanceof \WP_User ) {
                continue;
            }

            $results[] = [
                "id"        => (int) $user->ID,
                "value"     => (int) $user->ID,
                "label"     => $user->display_name,
                "name"      => $user->display_name,
                "subtitle"  => $user->user_email,
                "type"      => "user",
                "status"    => implode( ", ", (array) $user->roles ),
                "url"       => function_exists( "get_edit_user_link" ) ? get_edit_user_link( $user->ID ) : "",
                "edit_url"  => function_exists( "get_edit_user_link" ) ? get_edit_user_link( $user->ID ) : "",
                "view_url"  => function_exists( "get_author_posts_url" ) ? get_author_posts_url( $user->ID ) : "",
                "thumbnail" => function_exists( "get_avatar_url" ) ? get_avatar_url( $user->ID, [ "size" => 96 ] ) : "",
                "email"     => $user->user_email,
                "login"     => $user->user_login,
            ];
        }

        return $results;
    }

    private function search_post_types( string $query, int $limit ): array {
        $objects = get_post_types( [ 'show_ui' => true ], 'objects' );
        $results = [];
        $needle  = strtolower( $query );

        foreach ( $objects as $slug => $object ) {
            $label = (string) ( $object->labels->name ?? $slug );

            if ( ! str_contains( strtolower( $slug . ' ' . $label ), $needle ) ) {
                continue;
            }

            $results[] = [
                'id'       => $slug,
                'value'    => $slug,
                'name'     => $label,
                'subtitle' => 'Post type: ' . $slug,
                'type'     => 'post_type',
            ];

            if ( count( $results ) >= $limit ) {
                break;
            }
        }

        return $results;
    }
}
