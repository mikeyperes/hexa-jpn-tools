<?php

namespace Hexa\PluginCore\ListingSearch;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

/**
 * Applies a visitor's listing search to the page's own listing.
 *
 * The page's main query (a category, tag, author, search, post type or blog
 * page) and every query marked with the `hexa_listing` argument are
 * narrowed. An Elementor Loop Grid, Posts or Archive Posts widget becomes the
 * marked listing when it carries the CSS class `hexa-listing-results` or shows
 * the page's own list (Elementor's "Current Query"), or
 * when a plugin says so through the `hexa_plugin_core_listing_search_target`
 * filter (for widgets it builds itself). The widget's wrapper always carries
 * that class, which is what the search box refreshes in place.
 *
 * Several host plugins may add this module; hooks register once per request.
 */
final class ListingSearchModule implements ModuleInterface {
    public const TARGET_CLASS = 'hexa-listing-results';
    public const TARGET_FILTER = 'hexa_plugin_core_listing_search_target';

    private static bool $registered = false;

    public function register(): void {
        if ( self::$registered || ! function_exists( 'add_action' ) ) {
            return;
        }
        self::$registered = true;

        add_action( 'pre_get_posts', [ self::class, 'narrow_query' ], 40 );
        add_filter( 'elementor/query/query_args', [ self::class, 'mark_widget_query' ], 30, 2 );
        // Loops showing the page's own list ("Current Query") copy the main query and skip the hook above.
        add_filter( 'elementor/query/get_query_args/current_query', [ self::class, 'mark_current_query' ], 30 );
        add_action( 'elementor/frontend/widget/before_render', [ self::class, 'mark_widget_wrapper' ] );
    }

    /** @param \WP_Query $query */
    public static function narrow_query( $query ): void {
        if ( ! is_object( $query ) || ! method_exists( $query, 'get' ) || ( function_exists( 'is_admin' ) && is_admin() && ! wp_doing_ajax() ) ) {
            return;
        }

        $marked = (string) $query->get( ListingSearch::QUERY_VAR );
        $main   = method_exists( $query, 'is_main_query' ) && $query->is_main_query() && ! $query->is_feed() && ! $query->is_singular();
        if ( '' === $marked && ! $main ) {
            return;
        }

        $state = ListingSearch::state( $marked );
        if ( null === $state || ! $state['active'] || ( '' !== $marked && $state['profile']['id'] !== $marked ) ) {
            return;
        }
        if ( $main && 'page' === $state['context']['type'] ) {
            return; // A listing on an ordinary page narrows only its marked loop, never the page itself.
        }

        ListingSearch::apply( $query, $state );
    }

    /**
     * @param array<string,mixed> $args
     * @param object|null         $widget
     * @return array<string,mixed>
     */
    public static function mark_widget_query( $args, $widget = null ) {
        if ( ! is_array( $args ) || ! self::is_target( $widget ) ) {
            return $args;
        }

        $state = ListingSearch::state();
        if ( null !== $state && $state['active'] ) {
            $args[ ListingSearch::QUERY_VAR ] = $state['profile']['id'];
        }

        return $args;
    }

    /**
     * @param array<string,mixed> $args The main query's variables.
     * @return array<string,mixed>
     */
    public static function mark_current_query( $args ) {
        if ( ! is_array( $args ) ) {
            return $args;
        }

        $state = ListingSearch::state();
        if ( null !== $state && $state['active'] && 'page' !== $state['context']['type'] ) {
            $args[ ListingSearch::QUERY_VAR ] = $state['profile']['id'];
        }

        return $args;
    }

    /** @param object $widget */
    public static function mark_widget_wrapper( $widget ): void {
        if ( ! self::is_target( $widget ) || ! method_exists( $widget, 'add_render_attribute' ) ) {
            return;
        }
        $classes = method_exists( $widget, 'get_settings' ) ? (string) $widget->get_settings( '_css_classes' ) : '';
        if ( ! in_array( self::TARGET_CLASS, preg_split( '/\s+/', $classes ) ?: [], true ) ) {
            $widget->add_render_attribute( '_wrapper', 'class', self::TARGET_CLASS );
        }
    }

    /** Whether an Elementor widget is the page's listing. @param object|null $widget */
    public static function is_target( $widget ): bool {
        if ( ! is_object( $widget ) ) {
            return false;
        }
        $classes = method_exists( $widget, 'get_settings' ) ? (string) $widget->get_settings( '_css_classes' ) : '';
        $target  = in_array( self::TARGET_CLASS, preg_split( '/\s+/', $classes ) ?: [], true );

        return function_exists( 'apply_filters' ) ? (bool) apply_filters( self::TARGET_FILTER, $target, $widget ) : $target;
    }
}
