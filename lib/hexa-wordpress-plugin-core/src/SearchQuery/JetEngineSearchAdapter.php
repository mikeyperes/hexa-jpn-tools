<?php

namespace Hexa\PluginCore\SearchQuery;

/**
 * Routes eligible JetEngine listing grids through SearchQueryEngine.
 *
 * JetEngine can render a search-results template with a secondary WP_Query
 * instead of the native main query. This adapter marks only that deliberate
 * query; SearchQueryEngine continues rejecting every unmarked nested query.
 */
final class JetEngineSearchAdapter {
    /** @var callable */
    private $settings_provider;

    private string $marker_key;

    public function __construct( callable $settings_provider, string $marker_key = 'hexa_search' ) {
        $this->settings_provider = $settings_provider;
        $this->marker_key = self::key( $marker_key );

        if ( '' === $this->marker_key ) {
            $this->marker_key = 'hexa_search';
        }
    }

    public function register(): void {
        if ( function_exists( 'add_filter' ) ) {
            add_filter( 'jet-engine/listing/grid/posts-query-args', [ $this, 'filter_query_args' ], 20, 3 );
        }
    }

    /**
     * @param array<string,mixed> $query_args
     * @param mixed $render
     * @param array<string,mixed> $widget_settings
     * @return array<string,mixed>
     */
    public function filter_query_args( array $query_args, $render = null, array $widget_settings = [] ): array {
        $main_query = $this->eligible_main_query( $widget_settings );
        if ( null === $main_query || ! empty( $query_args['hexa_search_query_disabled'] ) ) {
            return $query_args;
        }

        $provided = call_user_func( $this->settings_provider );
        if ( ! is_array( $provided ) ) {
            return $query_args;
        }

        $settings = SearchQueryConfiguration::normalize(
            $provided,
            (array) ( $provided['post_types'] ?? [] ),
            (array) ( $provided['taxonomies'] ?? [] )
        );
        $marker = (string) $main_query->get( $this->marker_key );

        if ( ! $settings['enabled'] || ( 'shortcode' === $settings['scope'] && '1' !== $marker ) ) {
            return $query_args;
        }

        if ( function_exists( 'apply_filters' ) && ! apply_filters(
            'hexa_plugin_core_search_query_jet_engine_should_handle',
            true,
            $query_args,
            $render,
            $widget_settings,
            $settings,
            $main_query
        ) ) {
            return $query_args;
        }

        $query_args['s'] = trim( (string) $main_query->get( 's' ) );
        $query_args[ SearchQueryEngine::EXPLICIT_QUERY_VAR ] = '1';
        $query_args['suppress_filters'] = false;
        if ( '' !== $marker ) {
            $query_args[ $this->marker_key ] = $marker;
        }

        return $query_args;
    }

    /**
     * @param array<string,mixed> $widget_settings
     * @return object|null
     */
    private function eligible_main_query( array $widget_settings ) {
        if ( function_exists( 'is_admin' ) && is_admin() ) {
            return null;
        }
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            return null;
        }
        if ( ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
            return null;
        }
        if ( ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
            return null;
        }
        if ( ( function_exists( 'wp_is_serving_rest_request' ) && wp_is_serving_rest_request() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return null;
        }
        if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
            return null;
        }
        if ( ! empty( $widget_settings['is_archive_template'] )
            && filter_var( $widget_settings['is_archive_template'], FILTER_VALIDATE_BOOLEAN ) ) {
            return null;
        }

        global $wp_query;
        if ( ! is_object( $wp_query ) || ! method_exists( $wp_query, 'get' ) || ! method_exists( $wp_query, 'is_main_query' )
            || ! $wp_query->is_main_query() || ! method_exists( $wp_query, 'is_search' ) || ! $wp_query->is_search() ) {
            return null;
        }
        if ( ( method_exists( $wp_query, 'is_feed' ) && $wp_query->is_feed() )
            || '' === trim( (string) $wp_query->get( 's' ) ) || $wp_query->get( 'suppress_filters' )
            || $wp_query->get( 'hexa_search_query_disabled' ) ) {
            return null;
        }

        return $wp_query;
    }

    private static function key( string $value ): string {
        $value = strtolower( trim( $value ) );

        return (string) preg_replace( '/[^a-z0-9_\-]/', '', $value );
    }
}
