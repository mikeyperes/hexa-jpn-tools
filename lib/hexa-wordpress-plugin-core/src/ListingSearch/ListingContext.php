<?php

namespace Hexa\PluginCore\ListingSearch;

/**
 * What a listing is a list of: the category, tag, other taxonomy term,
 * author, post type archive, search page or blog the page shows, as plain
 * WP_Query arguments. Search and filters then narrow inside it, so a search
 * box on a category page searches that category.
 *
 * current() reads WordPress's main query; a token (`term:category:12`,
 * `author:5`, `post_type:event`, `search`, `home`) carries the same context
 * to a later AJAX or REST request. Plugins that widen a context (for example
 * co-authored posts on an author page) adjust the arguments with the
 * `hexa_plugin_core_listing_context_args` filter.
 */
final class ListingContext {
    public const ARGS_FILTER = 'hexa_plugin_core_listing_context_args';
    public const SEARCH_TYPES_FILTER = 'hexa_plugin_core_listing_search_post_types';

    /**
     * @return array{type:string,token:string,args:array<string,mixed>,post_types:string[],taxonomy:string,term_id:int,author_id:int,title:string}|null
     */
    public static function current(): ?array {
        if ( ! function_exists( 'is_category' ) ) {
            return null;
        }

        if ( is_category() || is_tag() || is_tax() ) {
            $term = get_queried_object();

            return is_object( $term ) && isset( $term->taxonomy, $term->term_id ) ? self::from_token( 'term:' . $term->taxonomy . ':' . (int) $term->term_id ) : null;
        }
        if ( is_author() ) {
            $author = get_queried_object();

            return is_object( $author ) && isset( $author->ID ) ? self::from_token( 'author:' . (int) $author->ID ) : null;
        }
        if ( is_search() ) {
            return self::from_token( 'search' );
        }
        if ( is_post_type_archive() ) {
            $type = get_query_var( 'post_type' );

            return self::from_token( 'post_type:' . ( is_array( $type ) ? (string) reset( $type ) : (string) $type ) );
        }
        if ( is_home() ) {
            return self::from_token( 'home' );
        }

        return null;
    }

    /**
     * Rebuilds a context from its token; null when the token or what it names is invalid.
     *
     * @return array{type:string,token:string,args:array<string,mixed>,post_types:string[],taxonomy:string,term_id:int,author_id:int,title:string}|null
     */
    public static function from_token( string $token ): ?array {
        $parts = explode( ':', strtolower( trim( $token ) ) );
        $type  = (string) ( $parts[0] ?? '' );
        $context = [ 'type' => $type, 'token' => '', 'args' => [], 'post_types' => [ 'post' ], 'taxonomy' => '', 'term_id' => 0, 'author_id' => 0, 'title' => '' ];

        if ( 'term' === $type ) {
            $taxonomy = (string) preg_replace( '/[^a-z0-9_\-]/', '', (string) ( $parts[1] ?? '' ) );
            $term     = function_exists( 'get_term' ) ? get_term( (int) ( $parts[2] ?? 0 ), $taxonomy ) : null;
            $object   = function_exists( 'get_taxonomy' ) ? get_taxonomy( $taxonomy ) : null;
            if ( ! is_object( $term ) || ! isset( $term->term_id ) || ! is_object( $object ) || empty( $object->public ) ) {
                return null;
            }
            $context['taxonomy']   = $taxonomy;
            $context['term_id']    = (int) $term->term_id;
            $context['title']      = (string) $term->name;
            $context['post_types'] = in_array( $taxonomy, [ 'category', 'post_tag' ], true ) ? [ 'post' ] : array_values( array_map( 'strval', (array) $object->object_type ) );
            $context['args']       = 'category' === $taxonomy
                ? [ 'cat' => (int) $term->term_id ]
                : ( 'post_tag' === $taxonomy ? [ 'tag_id' => (int) $term->term_id ] : [ 'tax_query' => [ [ 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => [ (int) $term->term_id ], 'include_children' => true ] ] ] );
            $context['token']      = 'term:' . $taxonomy . ':' . (int) $term->term_id;
        } elseif ( 'author' === $type ) {
            $user = function_exists( 'get_userdata' ) ? get_userdata( (int) ( $parts[1] ?? 0 ) ) : null;
            if ( ! is_object( $user ) || ! isset( $user->ID ) ) {
                return null;
            }
            $context['author_id'] = (int) $user->ID;
            $context['title']     = (string) $user->display_name;
            $context['args']      = [ 'author' => (int) $user->ID ];
            $context['token']     = 'author:' . (int) $user->ID;
        } elseif ( 'post_type' === $type ) {
            $post_type = (string) preg_replace( '/[^a-z0-9_\-]/', '', (string) ( $parts[1] ?? '' ) );
            $object    = function_exists( 'get_post_type_object' ) ? get_post_type_object( $post_type ) : null;
            if ( ! is_object( $object ) || empty( $object->public ) ) {
                return null;
            }
            $context['post_types'] = [ $post_type ];
            $context['title']      = (string) ( $object->labels->name ?? $post_type );
            $context['token']      = 'post_type:' . $post_type;
        } elseif ( 'search' === $type ) {
            $context['post_types'] = self::search_post_types();
            $context['token']      = 'search';
        } elseif ( 'home' === $type ) {
            $context['token'] = 'home';
        } else {
            return null;
        }

        $context['args'] = array_merge( $context['args'], [ 'post_type' => $context['post_types'], 'post_status' => 'publish', 'has_password' => false ] );
        if ( function_exists( 'apply_filters' ) ) {
            $context['args'] = (array) apply_filters( self::ARGS_FILTER, $context['args'], $context );
        }

        return $context;
    }

    /**
     * What site search lists by default: pages, posts and every public custom
     * post type that is not excluded from search.
     *
     * @return string[]
     */
    public static function search_post_types(): array {
        $types = function_exists( 'get_post_types' ) ? array_values( array_diff( array_map( 'strval', (array) get_post_types( [ 'public' => true, 'exclude_from_search' => false ] ) ), [ 'attachment' ] ) ) : [ 'post', 'page' ];
        if ( function_exists( 'apply_filters' ) ) {
            $types = array_values( array_filter( array_map( 'strval', (array) apply_filters( self::SEARCH_TYPES_FILTER, $types ) ) ) );
        }

        return [] !== $types ? $types : [ 'post', 'page' ];
    }
}
