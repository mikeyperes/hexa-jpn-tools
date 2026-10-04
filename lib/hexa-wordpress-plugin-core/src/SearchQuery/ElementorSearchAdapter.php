<?php

namespace Hexa\PluginCore\SearchQuery;

/**
 * Routes one exact Elementor Pro Search widget query through SearchQueryEngine.
 *
 * Elementor owns the live REST request, Loop Item rendering, pagination, and
 * interaction. Core only accepts a registered query id from a real Search
 * widget, then applies its bounded matching to that exact WP_Query object.
 */
final class ElementorSearchAdapter {
    /** @var callable */
    private $query_configurator;

    private string $query_id;

    private SearchQueryEngine $engine;

    private int $max_results_per_page;

    /**
     * @param callable $settings_provider Returns SearchQueryConfiguration input.
     * @param callable|null $query_configurator Receives ($query, $settings, $widget).
     */
    public function __construct(
        callable $settings_provider,
        string $query_id,
        ?callable $query_configurator = null,
        int $max_results_per_page = 50
    ) {
        $this->query_id = self::key( $query_id );
        if ( '' === $this->query_id ) {
            throw new \InvalidArgumentException( 'An Elementor Search adapter needs a query id.' );
        }

        $this->engine = new SearchQueryEngine( $settings_provider, 'hexa_elementor_search' );
        $this->query_configurator = $query_configurator;
        $this->max_results_per_page = max( 1, min( 50, $max_results_per_page ) );
    }

    public function register(): void {
        if ( ! function_exists( 'add_action' ) ) {
            return;
        }

        $this->engine->register_search_dispatcher();
        add_action( 'elementor/query/' . $this->query_id, [ $this, 'prepare_elementor_query' ], 20, 2 );
        add_action( 'pre_get_posts', [ $this, 'prepare_native_submission' ], 30 );
        add_action( 'elementor/frontend/widget/before_render', [ $this, 'mark_widget' ] );
        add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_frontend_assets' ], 20 );
    }

    /** @param mixed $widget */
    public function mark_widget( $widget ): void {
        if ( $this->trusted_widget( $widget ) && method_exists( $widget, 'add_render_attribute' ) ) {
            $widget->add_render_attribute( '_wrapper', 'data-hexa-search-query-id', $this->query_id );
        }
    }

    /**
     * Adds request cancellation, stale-response protection, and an accessible
     * request status to only the native Search widgets marked above.
     */
    public static function enqueue_frontend_assets(): void {
        if ( ! function_exists( 'plugin_dir_url' ) || ! function_exists( 'wp_enqueue_script' ) ) {
            return;
        }

        $directory = __DIR__ . '/assets/';
        $url = plugin_dir_url( __FILE__ ) . 'assets/';
        $script = $directory . 'elementor-search.js';
        $style = $directory . 'elementor-search.css';

        if ( is_file( $script ) ) {
            wp_enqueue_script(
                'hexa-plugin-core-elementor-search',
                $url . 'elementor-search.js',
                [],
                (string) filemtime( $script ),
                true
            );
        }
        if ( is_file( $style ) && function_exists( 'wp_enqueue_style' ) ) {
            wp_enqueue_style(
                'hexa-plugin-core-elementor-search',
                $url . 'elementor-search.css',
                [],
                (string) filemtime( $style )
            );
        }
    }

    /** @param mixed $query @param mixed $widget */
    public function prepare_elementor_query( $query, $widget = null ): void {
        if ( ! $this->trusted_widget( $widget ) || ! $this->request_allows_widget_query() ) {
            return;
        }

        $this->prepare( $query, $widget );
    }

    /**
     * Preserves an ordinary GET fallback when JavaScript is unavailable.
     * Elementor encodes the exact stored widget and page ids in the form; the
     * same widget/query-id checks used by the live REST path run here.
     *
     * @param mixed $query
     */
    public function prepare_native_submission( $query ): void {
        if ( ! $this->native_candidate( $query ) ) {
            return;
        }

        $binding = isset( $_GET['e_search_props'] ) && is_scalar( $_GET['e_search_props'] )
            ? trim( (string) wp_unslash( $_GET['e_search_props'] ) )
            : '';
        if ( ! preg_match( '/^([a-zA-Z0-9]{7})-([1-9][0-9]*)$/D', $binding, $parts )
            || ! class_exists( '\\ElementorPro\\Core\\Utils' )
            || ! method_exists( '\\ElementorPro\\Core\\Utils', 'create_widget_instance_from_db' )
        ) {
            return;
        }

        $widget = \ElementorPro\Core\Utils::create_widget_instance_from_db( (int) $parts[2], $parts[1] );
        if ( ! $this->trusted_widget( $widget ) ) {
            return;
        }

        $this->prepare( $query, $widget );
    }

    /** @param mixed $query @param mixed $widget */
    private function prepare( $query, $widget ): void {
        $settings = $this->engine->prepare_explicit_query( $query );
        if ( null === $settings ) {
            return;
        }

        if ( null !== $this->query_configurator ) {
            call_user_func( $this->query_configurator, $query, $settings, $widget );
        }

        $allowed = array_values( array_intersect(
            (array) $settings['post_types'],
            array_map( 'sanitize_key', (array) $query->get( 'post_type' ) )
        ) );
        $query->set( 'post_type', [] !== $allowed ? $allowed : $settings['post_types'] );
        $query->set( 'post_status', 'publish' );
        $query->set( 'has_password', false );
        $query->set( 'ignore_sticky_posts', true );
        $query->set( 'suppress_filters', false );

        $per_page = (int) $query->get( 'posts_per_page' );
        if ( $per_page < 1 || $per_page > $this->max_results_per_page ) {
            $query->set( 'posts_per_page', $this->max_results_per_page );
        }
    }

    /** @param mixed $widget */
    private function trusted_widget( $widget ): bool {
        if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' )
            || 'search' !== (string) $widget->get_name() || ! method_exists( $widget, 'get_settings_for_display' )
        ) {
            return false;
        }

        $settings = $widget->get_settings_for_display();

        return is_array( $settings )
            && $this->query_id === self::key( (string) ( $settings['search_query_query_id'] ?? '' ) );
    }

    private function request_allows_widget_query(): bool {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            return false;
        }
        if ( function_exists( 'is_admin' ) && is_admin()
            && ! ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() )
        ) {
            return false;
        }
        if ( ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
            return false;
        }
        if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
            return false;
        }

        return true;
    }

    /** @param mixed $query */
    private function native_candidate( $query ): bool {
        if ( ! $this->request_allows_widget_query() || ! is_object( $query )
            || ! method_exists( $query, 'get' ) || ! method_exists( $query, 'set' )
            || ! method_exists( $query, 'is_main_query' ) || ! $query->is_main_query()
        ) {
            return false;
        }

        return '' !== trim( (string) $query->get( 's' ) )
            && ! $query->get( 'suppress_filters' )
            && ! $query->get( 'hexa_search_query_disabled' );
    }

    private static function key( string $value ): string {
        return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( trim( $value ) ) );
    }
}
