<?php

namespace Hexa\PluginCore\SiteStructure;

/**
 * Keeps a page's old address working after the page moves under a parent or
 * changes its slug: the old path is stored on the page and a 301 sends
 * visitors and search engines to the current address. One hook per site,
 * registered by the first page manager built or by a host plugin calling
 * PageRedirects::register() on every request (needed on the front end).
 */
final class PageRedirects {
    public const META_KEY = '_hexa_old_paths';

    private static bool $registered = false;

    public static function register(): void {
        if ( self::$registered || ! function_exists( 'add_action' ) ) {
            return;
        }
        self::$registered = true;
        // On `wp`, before redirect plugins that send every 404 to the home page (Rank Math runs at 10).
        add_action( 'wp', [ self::class, 'redirect' ], 1 );
    }

    /** The page's address path without slashes at either end ("about/team"). */
    public static function path( int $page_id ): string {
        $uri = function_exists( 'get_page_uri' ) ? (string) get_page_uri( $page_id ) : '';

        return trim( $uri, '/' );
    }

    public static function remember( int $page_id, string $old_path ): void {
        $old_path = trim( $old_path, '/' );
        if ( $page_id <= 0 || '' === $old_path || $old_path === self::path( $page_id ) ) {
            return;
        }
        $paths = array_filter( array_map( 'strval', (array) get_post_meta( $page_id, self::META_KEY, false ) ) );
        if ( ! in_array( $old_path, $paths, true ) ) {
            add_post_meta( $page_id, self::META_KEY, $old_path );
        }
    }

    public static function redirect(): void {
        if ( ! function_exists( 'is_404' ) || ! is_404() ) {
            return;
        }
        $request = trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $home    = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
        if ( '' !== $home && 0 === strpos( $request, $home . '/' ) ) {
            $request = substr( $request, strlen( $home ) + 1 );
        }
        if ( '' === $request ) {
            return;
        }
        $ids = get_posts(
            [
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_key'       => self::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery
                'meta_value'     => $request, // phpcs:ignore WordPress.DB.SlowDBQuery
            ]
        );
        if ( ! empty( $ids ) ) {
            wp_safe_redirect( (string) get_permalink( (int) $ids[0] ), 301 );
            exit;
        }
    }
}
