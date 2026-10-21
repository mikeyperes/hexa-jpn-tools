<?php

namespace Hexa\PluginCore\ListingSearch;

use Hexa\PluginCore\PublicComponents\PublicComponent;
use Hexa\PluginCore\QueryFilter\QueryFilterSet;
use Hexa\PluginCore\QueryFilter\QuerySql;
use Hexa\PluginCore\SearchQuery\SearchQueryConfiguration;
use Hexa\PluginCore\SearchQuery\SearchQueryEngine;

/**
 * The visitor's listing search for this request and how it narrows queries.
 *
 * state() reads the page address (`hls` profile, `hq` text, `hf[key]`
 * filters; on the search page the text is WordPress's own `s`) against the
 * page's ListingContext. conditions() turns it into SQL that QuerySql
 * attaches to an exact WP_Query, and ids_sql() gives the same results'
 * IDs for filter counts and the result total.
 */
final class ListingSearch {
    public const PARAM_PROFILE = 'hls';
    public const PARAM_TEXT = 'hq';
    public const PARAM_FILTER = 'hf';
    /** WP_Query argument that marks a query (for example an Elementor loop) as the listing. */
    public const QUERY_VAR = 'hexa_listing';
    public const MAX_TEXT_LENGTH = 200;

    /** @var array<string,array<string,mixed>|null> */
    private static array $memo = [];

    /**
     * @param array<string,mixed>|null $input Raw, unslashed parameters; defaults to $_GET.
     * @return array<string,mixed>|null profile, context, filters, scope, text, values, owned, active
     */
    public static function state( string $profile_id = '', ?array $input = null ): ?array {
        $memo_key = $profile_id . '|' . ( null === $input ? 'request' : md5( (string) serialize( $input ) ) );
        if ( array_key_exists( $memo_key, self::$memo ) ) {
            return self::$memo[ $memo_key ];
        }

        $input   = PublicComponent::input( $input );
        $owner   = self::key( PublicComponent::scalar( $input[ self::PARAM_PROFILE ] ?? '' ) );
        $profile = ListingSearchRegistry::get( '' !== $profile_id ? $profile_id : $owner );
        if ( null === $profile ) {
            return self::$memo[ $memo_key ] = null;
        }

        $context = ListingContext::current() ?? self::page_context( $profile );
        $filters = self::filters_for( $profile, $context );
        $scope   = QueryFilterSet::scope( 'listing', array_merge( $profile, [ 'id' => $profile['id'] . '-' . $context['token'], 'post_types' => $context['post_types'] ] ) );
        $owned   = $owner === $profile['id'];
        $search  = 'search' === $context['type'];
        $text    = $search ? ( function_exists( 'get_search_query' ) ? (string) get_search_query( false ) : '' ) : ( $owned ? self::text( $input[ self::PARAM_TEXT ] ?? '' ) : '' );
        $values  = $owned ? QueryFilterSet::parse( $filters, $input[ self::PARAM_FILTER ] ?? [], $scope ) : [];
        $length  = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );

        return self::$memo[ $memo_key ] = [
            'profile' => $profile,
            'context' => $context,
            'filters' => $filters,
            'scope'   => $scope,
            'text'    => $text,
            'searched' => ! $search && $length >= $profile['min_chars'],
            'values'  => $values,
            'owned'   => $owned,
            'active'  => $owned && ( [] !== $values || ( ! $search && $length >= $profile['min_chars'] ) ),
        ];
    }

    /** Test helper. */
    public static function reset(): void {
        self::$memo = [];
    }

    /**
     * The profile's filters as they apply on this page: on a category page
     * the category filter lists only its subcategories (or disappears), on an
     * author page the author filter disappears, and the content-type filter
     * shows only where several types are listed.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function filters_for( array $profile, array $context ): array {
        $filters = [];
        foreach ( $profile['filters'] as $key => $filter ) {
            if ( 'taxonomy' === $filter['type'] && 'term' === $context['type'] && $filter['taxonomy'] === $context['taxonomy'] ) {
                if ( ! function_exists( 'is_taxonomy_hierarchical' ) || ! is_taxonomy_hierarchical( $context['taxonomy'] ) ) {
                    continue;
                }
                $filter['child_of'] = $context['term_id'];
            }
            if ( 'author' === $filter['type'] && 'author' === $context['type'] ) {
                continue;
            }
            if ( 'post_type' === $filter['type'] && count( $context['post_types'] ) < 2 ) {
                continue;
            }
            $filters[ $key ] = $filter;
        }

        return $filters;
    }

    /**
     * SQL conditions (without a leading AND) for a set of filter values plus
     * the visitor's text, on the posts table WordPress queries.
     *
     * @param array<string,mixed> $state  From state().
     * @param array<string,mixed> $values Parsed filter values.
     */
    public static function conditions( array $state, array $values ): string {
        global $wpdb;
        if ( ! is_object( $wpdb ) ) {
            return '';
        }

        $parts = QueryFilterSet::where( $wpdb, $state['filters'], $values, $state['scope'], $wpdb->posts );
        if ( $state['searched'] ) {
            $sql = self::search_sql( $state['profile'], $state['text'], $wpdb );
            if ( '' !== $sql ) {
                $parts[] = $sql;
            }
        }

        return [] !== $parts ? '(' . implode( ') AND (', $parts ) . ')' : '';
    }

    /** SQL selecting every result ID of the page's listing for these filter values. */
    public static function ids_sql( array $state, array $values ): string {
        $args = $state['context']['args'];
        if ( 'search' === $state['context']['type'] ) {
            $args = array_merge( $args, $state['profile']['search_args'], [ 's' => $state['text'] ] );
        }

        return QuerySql::post_ids_sql( $args, self::conditions( $state, $values ) );
    }

    /**
     * Narrows one exact query to the visitor's listing search.
     *
     * @param object $query \WP_Query.
     */
    public static function apply( $query, array $state ): void {
        QuerySql::attach( $query, self::conditions( $state, $state['values'] ) );
    }

    /**
     * Context for a page that is not an archive: the profile's post types.
     *
     * @return array<string,mixed>
     */
    public static function page_context( array $profile ): array {
        return [
            'type'       => 'page',
            'token'      => 'page',
            'args'       => [ 'post_type' => $profile['post_types'], 'post_status' => 'publish', 'has_password' => false ],
            'post_types' => $profile['post_types'],
            'taxonomy'   => '',
            'term_id'    => 0,
            'author_id'  => 0,
            'title'      => '',
        ];
    }

    /** Visitor text with control characters and markup removed, bounded. @param mixed $value */
    public static function text( $value ): string {
        $text = trim( (string) preg_replace( '/\s+/u', ' ', (string) preg_replace( '/[\x00-\x1F\x7F<>]+/u', ' ', PublicComponent::scalar( $value ) ) ) );

        return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::MAX_TEXT_LENGTH, 'UTF-8' ) : substr( $text, 0, self::MAX_TEXT_LENGTH );
    }

    private static function key( string $value ): string {
        return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( trim( $value ) ) );
    }

    /** The profile's text search as SQL conditions on the posts table. @param object $database */
    private static function search_sql( array $profile, string $text, $database ): string {
        $settings = is_callable( $profile['search'] ) ? (array) call_user_func( $profile['search'] ) : (array) $profile['search'];
        $settings = SearchQueryConfiguration::normalize( $settings + [ 'enabled' => true ], (array) ( $settings['post_types'] ?? [ 'post', 'page' ] ), (array) ( $settings['taxonomies'] ?? [] ) );
        $engine   = new SearchQueryEngine( static fn(): array => $settings );

        return (string) preg_replace( '/^\s*AND\s+/', '', trim( $engine->build_search_sql( $text, $settings, $database ) ) );
    }
}
