<?php

namespace Hexa\PluginCore\Search;

use Hexa\PluginCore\PublicComponents\PublicComponent;
use Hexa\PluginCore\QueryFilter\QueryFilterSet;
use Hexa\PluginCore\QueryFilter\QuerySql;
use Hexa\PluginCore\SearchQuery\SearchMatchSql;
use Hexa\PluginCore\SearchQuery\SearchQueryConfiguration;
use Hexa\PluginCore\SearchQuery\SearchQueryEngine;
use Hexa\PluginCore\SearchQuery\SearchTermParser;

/**
 * The one search: a visitor's search for this request and how it narrows
 * WordPress's own queries.
 *
 * state() reads the page address against a profile and the page's context:
 * `hs` names the box that owns the address, `hq` is the text (on the search
 * page WordPress's own `s`), `hf[key]` the filters, `hp` the page of cards and
 * `ho` the sort. Every state is a plain URL, so it is shareable and works
 * without JavaScript. conditions() turns it into SQL that QuerySql attaches
 * to an exact WP_Query or WP_User_Query; ids_sql() gives the same result IDs
 * for filter counts and totals; results() runs one page of cards.
 */
final class Search {
    public const PARAM_PROFILE = 'hs';
    public const PARAM_TEXT = 'hq';
    public const PARAM_FILTER = 'hf';
    public const PARAM_PAGE = 'hp';
    public const PARAM_SORT = 'ho';
    /** Every parameter the search owns, dropped from a box's base address. */
    public const PARAMS = [ self::PARAM_PROFILE, self::PARAM_TEXT, self::PARAM_FILTER, self::PARAM_PAGE, self::PARAM_SORT ];
    /** WP_Query argument that marks a query (for example an Elementor loop) as a search's list. */
    public const QUERY_VAR = 'hexa_search_list';
    public const MAX_TEXT_LENGTH = 200;
    public const MAX_PAGE = 1000;

    /** @var array<string,array<string,mixed>|null> */
    private static array $memo = [];

    /**
     * @param array<string,mixed>|null $input Raw, unslashed parameters; defaults to $_GET.
     * @param array<string,string>     $scope What a box on an ordinary page searches (SearchContext::for_scope()).
     * @return array<string,mixed>|null profile, context, filters, scope, text, values, page, sort, owned, searched, active
     */
    public static function state( string $profile_id = '', ?array $input = null, array $scope = [] ): ?array {
        $memo_key = $profile_id . '|' . md5( (string) serialize( [ $input, $scope ] ) );
        if ( array_key_exists( $memo_key, self::$memo ) ) {
            return self::$memo[ $memo_key ];
        }

        $input   = PublicComponent::input( $input );
        $owner   = self::key( PublicComponent::scalar( $input[ self::PARAM_PROFILE ] ?? '' ) );
        $profile = SearchRegistry::get( '' !== $profile_id ? $profile_id : $owner );
        if ( null === $profile ) {
            return self::$memo[ $memo_key ] = null;
        }

        $context = null;
        if ( 'posts' === $profile['source'] ) {
            $context = ( $profile['context'] ? SearchContext::current() : null ) ?? SearchContext::for_scope( $scope, $profile['post_types'] );
        }
        $filters = self::filters_for( $profile, $context );
        $qscope  = QueryFilterSet::scope( 'search', array_merge( $profile, [ 'id' => $profile['id'] . '-' . ( $context['token'] ?? 'users' ), 'post_types' => $context['post_types'] ?? [] ] ) );
        $owned   = $owner === $profile['id'];
        $site    = null !== $context && 'search' === $context['type'] && 'list' === $profile['results'];
        $text    = $site ? ( function_exists( 'get_search_query' ) ? (string) get_search_query( false ) : '' ) : ( $owned ? self::text( $input[ self::PARAM_TEXT ] ?? '' ) : '' );
        $values  = $owned ? QueryFilterSet::parse( $filters, $input[ self::PARAM_FILTER ] ?? [], $qscope ) : [];
        $length  = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
        $sort    = $owned ? self::key( PublicComponent::scalar( $input[ self::PARAM_SORT ] ?? '' ) ) : '';
        $searched = ! $site && $length >= $profile['min_chars'];

        return self::$memo[ $memo_key ] = [
            'profile'  => $profile,
            'context'  => $context,
            'filters'  => $filters,
            'scope'    => $qscope,
            'text'     => $text,
            'searched' => $searched,
            'values'   => $values,
            'page'     => $owned ? max( 1, min( self::MAX_PAGE, (int) PublicComponent::scalar( $input[ self::PARAM_PAGE ] ?? '1', '1' ) ) ) : 1,
            'sort'     => isset( $profile['sorts'][ $sort ] ) ? $sort : (string) $profile['default_sort'],
            'owned'    => $owned,
            'active'   => $owned && ( [] !== $values || $searched ),
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
     * @param array<string,mixed>|null $context
     * @return array<string,array<string,mixed>>
     */
    public static function filters_for( array $profile, ?array $context ): array {
        if ( null === $context ) {
            return $profile['filters'];
        }
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
     * the visitor's text, on the posts or users table WordPress queries.
     *
     * @param array<string,mixed> $state  From state().
     * @param array<string,mixed> $values Parsed filter values.
     */
    public static function conditions( array $state, array $values ): string {
        global $wpdb;
        if ( ! is_object( $wpdb ) ) {
            return '';
        }

        $profile = $state['profile'];
        $table   = 'users' === $profile['source'] ? (string) $wpdb->users : (string) $wpdb->posts;
        $parts   = QueryFilterSet::where( $wpdb, $state['filters'], $values, $state['scope'], $table );
        if ( $state['searched'] ) {
            $sql = self::text_sql( $wpdb, $profile, $state['text'], $table );
            if ( '' !== $sql ) {
                $parts[] = $sql;
            }
        }

        return [] !== $parts ? '(' . implode( ') AND (', $parts ) . ')' : '';
    }

    /** SQL selecting every result ID for these filter values (filter counts, totals). */
    public static function ids_sql( array $state, array $values ): string {
        return 'users' === $state['profile']['source']
            ? QuerySql::user_ids_sql( self::query_args( $state ), self::conditions( $state, $values ) )
            : QuerySql::post_ids_sql( self::query_args( $state ), self::conditions( $state, $values ) );
    }

    /**
     * Narrows one exact query (the page's list or a marked loop) to the search.
     *
     * @param object $query \WP_Query.
     */
    public static function apply( $query, array $state ): void {
        QuerySql::attach( $query, self::conditions( $state, $state['values'] ) );
    }

    /**
     * The WordPress query arguments for the state's scope and, when given, a field sort.
     *
     * @param array<string,mixed>|null $sort
     * @return array<string,mixed>
     */
    public static function query_args( array $state, ?array $sort = null ): array {
        $profile = $state['profile'];
        if ( 'users' === $profile['source'] ) {
            $columns = [ 'name' => 'display_name', 'registered' => 'registered' ];

            return [
                'role__in' => $profile['roles'],
                'orderby'  => [ ( null !== $sort ? ( $columns[ $sort['field'] ] ?? 'display_name' ) : 'display_name' ) => null !== $sort ? $sort['order'] : 'ASC', 'ID' => 'ASC' ],
            ];
        }

        $args = (array) $state['context']['args'];
        if ( 'search' === $state['context']['type'] && 'list' === $profile['results'] ) {
            $args = array_merge( $args, $profile['search_args'], [ 's' => $state['text'] ] );
        }
        if ( null !== $sort ) {
            $columns         = [ 'title' => 'title', 'date' => 'date', 'modified' => 'modified' ];
            $args['orderby'] = [ $columns[ $sort['field'] ] ?? 'date' => $sort['order'], 'ID' => 'ASC' ];
            $args['ignore_sticky_posts'] = true;
        }

        return $args;
    }

    /**
     * One page of result IDs for a box that renders its own cards.
     *
     * @return array{ids:int[],total:int,page:int,pages:int,per_page:int,truncated:bool}
     */
    public static function results( array $state ): array {
        $profile    = $state['profile'];
        $conditions = self::conditions( $state, $state['values'] );
        $per_page   = (int) $profile['per_page'];
        $sort       = $profile['sorts'][ $state['sort'] ] ?? reset( $profile['sorts'] );
        $truncated  = false;

        if ( 'callback' === $sort['type'] ) {
            $first      = self::fetch( $state, $conditions, null, SearchProfile::MAX_CALLBACK_CANDIDATES + 1, 1 );
            $candidates = $first['ids'];
            if ( count( $candidates ) > SearchProfile::MAX_CALLBACK_CANDIDATES ) {
                $truncated  = true;
                $candidates = array_slice( $candidates, 0, SearchProfile::MAX_CALLBACK_CANDIDATES );
            }
            $ordered = array_map( 'intval', (array) call_user_func( $sort['callback'], $candidates, $state ) );
            $allowed = array_flip( $candidates );
            $ordered = array_values( array_unique( array_filter( $ordered, static fn( int $id ): bool => isset( $allowed[ $id ] ) ) ) );
            $ordered = array_merge( $ordered, array_values( array_diff( $candidates, $ordered ) ) );
            $total   = $truncated ? self::fetch( $state, $conditions, null, 1, 1, true )['total'] : count( $ordered );
            $pages   = max( 1, (int) ceil( $total / $per_page ) );
            $page    = min( $state['page'], $pages );
            $offset  = ( $page - 1 ) * $per_page;
            $ids     = array_slice( $ordered, $offset, $per_page );
            if ( $truncated && count( $ids ) < $per_page && $offset + count( $ids ) < $total ) {
                $ids = array_merge( $ids, self::fetch( $state, $conditions, null, $per_page - count( $ids ), 1, false, max( $offset, count( $ordered ) ) )['ids'] );
            }
        } else {
            $result = self::fetch( $state, $conditions, $sort, $per_page, $state['page'], true );
            $total  = $result['total'];
            $pages  = max( 1, (int) ceil( $total / $per_page ) );
            $page   = min( $state['page'], $pages );
            $ids    = $page === $state['page'] ? $result['ids'] : self::fetch( $state, $conditions, $sort, $per_page, $page )['ids'];
        }

        return [ 'ids' => $ids, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $per_page, 'truncated' => $truncated ];
    }

    /** Visitor text with control characters and markup removed, bounded. @param mixed $value */
    public static function text( $value ): string {
        $text = trim( (string) preg_replace( '/\s+/u', ' ', (string) preg_replace( '/[\x00-\x1F\x7F<>]+/u', ' ', PublicComponent::scalar( $value ) ) ) );

        return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::MAX_TEXT_LENGTH, 'UTF-8' ) : substr( $text, 0, self::MAX_TEXT_LENGTH );
    }

    /** The search's own address arguments for a state, optionally on another page of cards. @return array<string,mixed> */
    public static function args( array $state, ?int $page = null, ?array $values = null ): array {
        $args   = [ self::PARAM_PROFILE => $state['profile']['id'] ];
        $values = $values ?? $state['values'];
        if ( '' !== $state['text'] && ! ( null !== $state['context'] && 'search' === $state['context']['type'] && 'list' === $state['profile']['results'] ) ) {
            $args[ self::PARAM_TEXT ] = $state['text'];
        }
        if ( [] !== $values ) {
            $args[ self::PARAM_FILTER ] = QueryFilterSet::to_args( $values );
        }
        if ( $state['sort'] !== $state['profile']['default_sort'] ) {
            $args[ self::PARAM_SORT ] = $state['sort'];
        }
        $page = $page ?? $state['page'];
        if ( $page > 1 ) {
            $args[ self::PARAM_PAGE ] = $page;
        }

        return $args;
    }

    public static function key( string $value ): string {
        return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( trim( $value ) ) );
    }

    /**
     * Runs one WordPress query for IDs.
     *
     * @param array<string,mixed>|null $sort
     * @return array{ids:int[],total:int}
     */
    private static function fetch( array $state, string $conditions, ?array $sort, int $number, int $page, bool $count = false, int $offset = -1 ): array {
        $args = self::query_args( $state, $sort );
        $at   = $offset >= 0 ? [ 'offset' => $offset ] : [ 'paged' => max( 1, $page ) ];
        if ( 'users' === $state['profile']['source'] ) {
            $query = new \WP_User_Query();
            QuerySql::attach( $query, $conditions );
            $query->prepare_query( array_merge( $args, [ 'fields' => 'ID', 'number' => $number, 'count_total' => $count ] + $at ) );
            $query->query();

            return [ 'ids' => array_map( 'intval', (array) $query->get_results() ), 'total' => (int) $query->get_total() ];
        }

        $query = new \WP_Query();
        QuerySql::attach( $query, $conditions );
        $ids = $query->query( array_merge( $args, [
            'fields'                 => 'ids',
            'posts_per_page'         => $number,
            'no_found_rows'          => ! $count,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ] + $at ) );

        return [ 'ids' => array_map( 'intval', (array) $ids ), 'total' => (int) $query->found_posts ];
    }

    /**
     * The visitor's text as SQL on the posts or users table: the site's search
     * settings for posts profiles that use them, otherwise the profile's
     * explicit fields, meta keys and taxonomy term names.
     *
     * @param object $database
     */
    private static function text_sql( $database, array $profile, string $text, string $table ): string {
        if ( 'posts' === $profile['source'] && null !== $profile['search'] ) {
            $settings = is_callable( $profile['search'] ) ? (array) call_user_func( $profile['search'] ) : (array) $profile['search'];
            $settings = SearchQueryConfiguration::normalize( $settings + [ 'enabled' => true ], (array) ( $settings['post_types'] ?? [ 'post', 'page' ] ), (array) ( $settings['taxonomies'] ?? [] ) );
            $engine   = new SearchQueryEngine( static fn(): array => $settings );

            return (string) preg_replace( '/^\s*AND\s+/', '', trim( $engine->build_search_sql( $text, $settings, $database ) ) );
        }

        $terms = SearchTermParser::parse( $text, $profile['term_logic'], (bool) $profile['wildcards'] );
        if ( [] === $terms ) {
            return '';
        }
        $matching = 'exact' === $profile['term_logic'] ? 'contains' : (string) $profile['word_matching'];
        $clauses  = [];
        foreach ( $terms as $term ) {
            $conditions = self::term_conditions( $database, $profile, $term, $matching, $table );
            if ( [] !== $conditions ) {
                $clauses[] = '(' . implode( ' OR ', $conditions ) . ')';
            }
        }

        return [] !== $clauses ? '(' . implode( 'any' === $profile['term_logic'] ? ' OR ' : ' AND ', $clauses ) . ')' : '';
    }

    /** @param object $database @return string[] */
    private static function term_conditions( $database, array $profile, string $term, string $matching, string $table ): array {
        $wildcards  = (bool) $profile['wildcards'];
        $users      = 'users' === $profile['source'];
        $columns    = $users ? SearchProfile::USER_FIELDS : SearchProfile::POST_FIELDS;
        $conditions = [];
        foreach ( $profile['fields'] as $field ) {
            if ( isset( $columns[ $field ] ) ) {
                $conditions[] = SearchMatchSql::condition( $database, $table . '.' . $columns[ $field ], $term, $matching, $wildcards );
            }
        }
        if ( [] !== $profile['meta_keys'] ) {
            $meta   = $users ? $database->usermeta : $database->postmeta;
            $column = $users ? 'user_id' : 'post_id';
            $conditions[] = 'EXISTS (SELECT 1 FROM ' . $meta . ' hsm WHERE hsm.' . $column . ' = ' . $table . '.ID'
                . ' AND hsm.meta_key IN (' . self::placeholders( $database, $profile['meta_keys'] ) . ')'
                . ' AND ' . SearchMatchSql::condition( $database, 'hsm.meta_value', $term, $matching, $wildcards ) . ')';
        }
        if ( ! $users && [] !== $profile['taxonomies'] ) {
            $conditions[] = 'EXISTS (SELECT 1 FROM ' . $database->term_relationships . ' hstr'
                . ' INNER JOIN ' . $database->term_taxonomy . ' hstt ON hstt.term_taxonomy_id = hstr.term_taxonomy_id'
                . ' INNER JOIN ' . $database->terms . ' hst ON hst.term_id = hstt.term_id'
                . ' WHERE hstr.object_id = ' . $table . '.ID'
                . ' AND hstt.taxonomy IN (' . self::placeholders( $database, $profile['taxonomies'] ) . ')'
                . ' AND ' . SearchMatchSql::condition( $database, 'hst.name', $term, $matching, $wildcards ) . ')';
        }

        return $conditions;
    }

    /** @param object $database @param string[] $values */
    private static function placeholders( $database, array $values ): string {
        return implode( ', ', array_map( static fn( string $value ): string => $database->prepare( '%s', $value ), $values ) );
    }
}
