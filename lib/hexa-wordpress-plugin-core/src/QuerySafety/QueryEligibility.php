<?php

namespace Hexa\PluginCore\QuerySafety;

/**
 * Cheap scope checks for host callbacks that pair query mutation with SQL filters.
 */
final class QueryEligibility {
    public static function allows_main_filtered_frontend_query( \WP_Query $query ): bool {
        return self::is_filtered_frontend_request( $query ) && $query->is_main_query();
    }

    /**
     * @param array<int,mixed> $allowed_values
     */
    public static function allows_main_or_explicit_filtered_frontend_query(
        \WP_Query $query,
        string $marker,
        array $allowed_values = [ '1' ]
    ): bool {
        if ( ! self::is_filtered_frontend_request( $query ) ) {
            return false;
        }
        if ( $query->is_main_query() ) {
            return true;
        }

        $marker = self::key( $marker );

        return '' !== $marker
            && [] !== $allowed_values
            && in_array( $query->get( $marker ), $allowed_values, true );
    }

    private static function is_filtered_frontend_request( \WP_Query $query ): bool {
        if ( $query->get( 'suppress_filters' ) ) {
            return false;
        }

        return ! ( function_exists( 'is_admin' ) && is_admin() )
            && ! ( defined( 'WP_CLI' ) && WP_CLI )
            && ! ( ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) )
            && ! ( ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) )
            && ! ( ( function_exists( 'wp_is_serving_rest_request' ) && wp_is_serving_rest_request() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) )
            && ! ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST );
    }

    private static function key( string $value ): string {
        if ( function_exists( 'sanitize_key' ) ) {
            return sanitize_key( $value );
        }

        $value = strtolower( trim( $value ) );

        return (string) preg_replace( '/[^a-z0-9_\-]/', '', $value );
    }
}
