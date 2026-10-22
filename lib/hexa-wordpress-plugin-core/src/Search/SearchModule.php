<?php

namespace Hexa\PluginCore\Search;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

/**
 * Applies a visitor's search to the list it searches, and adds the generic
 * `[hexa_search id="profile"]` shortcode (attributes as SearchBox::render()).
 *
 * The page's main query (a category, tag, author, search, post type or blog
 * page) and every query marked with the `hexa_search_list` argument are
 * narrowed. An Elementor Loop Grid, Posts or Archive Posts widget becomes the
 * marked listing when it carries the CSS class `hexa-listing-results` or shows
 * the page's own list (Elementor's "Current Query"), or
 * when a plugin says so through the `hexa_plugin_core_search_target`
 * filter (for widgets it builds itself). The widget's wrapper always carries
 * that class, which is what the search box refreshes in place.
 *
 * Several host plugins may add this module; hooks register once per request.
 */
final class SearchModule implements ModuleInterface {
    public const SHORTCODE = 'hexa_search';
    public const TARGET_CLASS = 'hexa-listing-results';
    public const TARGET_FILTER = 'hexa_plugin_core_search_target';

    private static bool $registered = false;

    public function register(): void {
        if ( self::$registered || ! function_exists( 'add_action' ) ) {
            return;
        }
        self::$registered = true;

        add_shortcode( self::SHORTCODE, [ self::class, 'shortcode' ] );
        add_action( 'pre_get_posts', [ self::class, 'narrow_query' ], 40 );
        add_filter( 'elementor/query/query_args', [ self::class, 'mark_widget_query' ], 30, 2 );
        // Loops showing the page's own list ("Current Query") copy the main query and skip the hook above.
        add_filter( 'elementor/query/get_query_args/current_query', [ self::class, 'mark_current_query' ], 30 );
        add_action( 'elementor/frontend/widget/before_render', [ self::class, 'mark_widget_wrapper' ] );
    }

    /** @param array<string,mixed>|string $attributes */
    public static function shortcode( $attributes = [] ): string {
        $attributes = is_array( $attributes ) ? $attributes : [];

        return SearchBox::render( (string) ( $attributes['id'] ?? '' ), $attributes );
    }

    /** @param \WP_Query $query */
    public static function narrow_query( $query ): void {
        if ( ! is_object( $query ) || ! method_exists( $query, 'get' ) || ( function_exists( 'is_admin' ) && is_admin() && ! wp_doing_ajax() ) ) {
            return;
        }

        $marked = (string) $query->get( Search::QUERY_VAR );
        $main   = method_exists( $query, 'is_main_query' ) && $query->is_main_query() && ! $query->is_feed() && ! $query->is_singular();
        if ( '' === $marked && ! $main ) {
            return;
        }

        $state = Search::state( $marked );
        if ( null === $state || ! $state['active'] || 'list' !== $state['profile']['results'] || ( '' !== $marked && $state['profile']['id'] !== $marked ) ) {
            return;
        }
        if ( $main && 'page' === $state['context']['type'] ) {
            return; // A listing on an ordinary page narrows only its marked loop, never the page itself.
        }

        Search::apply( $query, $state );
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

        $state = Search::state();
        if ( null !== $state && $state['active'] && 'list' === $state['profile']['results'] ) {
            $args[ Search::QUERY_VAR ] = $state['profile']['id'];
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

        $state = Search::state();
        if ( null !== $state && $state['active'] && 'list' === $state['profile']['results'] && 'page' !== $state['context']['type'] ) {
            $args[ Search::QUERY_VAR ] = $state['profile']['id'];
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

    /**
     * Whether an Elementor widget shows the page's own list: an Archive Posts
     * widget, or a Loop Grid or Posts widget whose query source is
     * "Current Query".
     *
     * @param object $widget
     */
    private static function shows_page_list( $widget ): bool {
        if ( ! method_exists( $widget, 'get_name' ) || ! method_exists( $widget, 'get_settings' ) ) {
            return false;
        }
        if ( 'archive-posts' === $widget->get_name() ) {
            return true;
        }
        foreach ( [ 'post_query_post_type', 'posts_post_type', 'query_post_type' ] as $key ) {
            if ( 'current_query' === $widget->get_settings( $key ) ) {
                return true;
            }
        }

        return false;
    }

    /** Whether an Elementor widget is the page's listing. @param object|null $widget */
    public static function is_target( $widget ): bool {
        if ( ! is_object( $widget ) ) {
            return false;
        }
        $classes = method_exists( $widget, 'get_settings' ) ? (string) $widget->get_settings( '_css_classes' ) : '';
        $target  = in_array( self::TARGET_CLASS, preg_split( '/\s+/', $classes ) ?: [], true ) || self::shows_page_list( $widget );

        return function_exists( 'apply_filters' ) ? (bool) apply_filters( self::TARGET_FILTER, $target, $widget ) : $target;
    }
}
