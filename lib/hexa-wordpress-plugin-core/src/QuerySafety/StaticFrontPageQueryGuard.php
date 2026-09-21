<?php

namespace Hexa\PluginCore\QuerySafety;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

/**
 * Keeps the configured static front-page query eligible to return its page.
 */
final class StaticFrontPageQueryGuard implements ModuleInterface {
    private static bool $capture_hook_registered = false;
    private static bool $registration_scheduled = false;
    private static bool $query_hooks_registered = false;

    /** @var \SplObjectStorage<\WP_Query,array{page_id:mixed,p:mixed}>|null */
    private static ?\SplObjectStorage $captured_queries = null;

    public function register(): void {
        if ( ! function_exists( 'add_action' ) ) {
            return;
        }

        if ( ! self::$capture_hook_registered ) {
            add_action( 'parse_query', [ self::class, 'capture' ], PHP_INT_MIN );
            self::$capture_hook_registered = true;
        }
        if ( self::$registration_scheduled ) {
            return;
        }

        self::$registration_scheduled = true;
        if ( function_exists( 'did_action' ) && did_action( 'wp_loaded' ) ) {
            self::register_query_hooks();
            return;
        }

        add_action( 'wp_loaded', [ self::class, 'register_query_hooks' ], PHP_INT_MAX );
    }

    public static function register_query_hooks(): void {
        if ( self::$query_hooks_registered || ! function_exists( 'add_action' ) ) {
            return;
        }

        add_action( 'pre_get_posts', [ self::class, 'protect' ], PHP_INT_MAX );
        self::$query_hooks_registered = true;
    }

    public static function capture( \WP_Query $query ): void {
        self::forget( $query );
        if ( ! self::is_static_front_page_main_query( $query ) ) {
            return;
        }

        if ( ! self::$captured_queries instanceof \SplObjectStorage ) {
            self::$captured_queries = new \SplObjectStorage();
        }

        self::$captured_queries[ $query ] = [
            'page_id' => $query->get( 'page_id' ),
            'p'       => $query->get( 'p' ),
        ];
    }

    public static function protect( \WP_Query $query ): void {
        $baseline = self::$captured_queries instanceof \SplObjectStorage && self::$captured_queries->offsetExists( $query )
            ? self::$captured_queries[ $query ]
            : null;
        if ( null === $baseline && ! self::is_static_front_page_main_query( $query ) ) {
            return;
        }
        if ( function_exists( 'apply_filters' )
            && ! apply_filters( 'hexa_plugin_core_should_protect_static_front_page_query', true, $query, $baseline )
        ) {
            self::forget( $query );
            return;
        }

        $changes = [];
        foreach ( [ 'page_id', 'p' ] as $query_var ) {
            $expected = is_array( $baseline ) && array_key_exists( $query_var, $baseline )
                ? $baseline[ $query_var ]
                : $query->get( $query_var );
            if ( $query->get( $query_var ) !== $expected ) {
                $changes[ $query_var ] = [
                    'from' => $query->get( $query_var ),
                    'to'   => $expected,
                ];
                $query->set( $query_var, $expected );
            }
        }

        $post_type = $query->get( 'post_type' );
        if ( ! self::post_type_includes_page( $post_type ) ) {
            $changes['post_type'] = [
                'from' => $post_type,
                'to'   => 'page',
            ];
            $query->set( 'post_type', 'page' );
        }

        if ( [] !== $changes && function_exists( 'do_action' ) ) {
            do_action( 'hexa_plugin_core_static_front_page_query_repaired', $query, $changes );
        }

        self::forget( $query );
    }

    public static function is_static_front_page_main_query( \WP_Query $query ): bool {
        if ( ! $query->is_main_query() || ! $query->is_page() ) {
            return false;
        }
        if ( ( function_exists( 'is_admin' ) && is_admin() )
            || ( defined( 'WP_CLI' ) && WP_CLI )
            || ( ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) )
            || ( ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) )
            || ( ( function_exists( 'wp_is_serving_rest_request' ) && wp_is_serving_rest_request() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) )
            || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
        ) {
            return false;
        }

        $query_page_id = self::positive_int( $query->get( 'page_id' ) );
        if ( $query_page_id < 1 ) {
            $query_page_id = self::positive_int( $query->get( 'p' ) );
        }
        if ( $query_page_id < 1 || ! function_exists( 'get_option' ) ) {
            return false;
        }

        if ( 'page' !== get_option( 'show_on_front' ) ) {
            return false;
        }

        $front_page_id = self::positive_int( get_option( 'page_on_front' ) );

        return $front_page_id > 0 && $front_page_id === $query_page_id;
    }

    private static function post_type_includes_page( mixed $post_type ): bool {
        if ( empty( $post_type ) || 'page' === $post_type || 'any' === $post_type ) {
            return true;
        }

        return is_array( $post_type )
            && ( in_array( 'page', $post_type, true ) || in_array( 'any', $post_type, true ) );
    }

    private static function positive_int( mixed $value ): int {
        if ( ! is_scalar( $value ) ) {
            return 0;
        }

        return max( 0, (int) $value );
    }

    private static function forget( \WP_Query $query ): void {
        if ( self::$captured_queries instanceof \SplObjectStorage && self::$captured_queries->offsetExists( $query ) ) {
            self::$captured_queries->offsetUnset( $query );
        }
    }
}
