<?php

namespace Hexa\PluginCore\DirectorySearch;

use Hexa\PluginCore\ListingSearch\ListingContext;
use Hexa\PluginCore\QueryFilter\QueryFilterSet;
use Hexa\PluginCore\QueryFilter\QuerySql;
use Hexa\PluginCore\SearchQuery\SearchMatchSql;
use Hexa\PluginCore\SearchQuery\SearchTermParser;

/**
 * Resolves one page of result IDs for a directory search profile.
 *
 * Results come from WordPress's own WP_Query (posts) or WP_User_Query
 * (users): the profile's scope, paging and sort are ordinary query
 * arguments, and the visitor's search text and filters are attached to that
 * exact query object as extra conditions (QuerySql). Other plugins therefore
 * see and can adjust the query as usual. Every identifier comes from Core
 * whitelists and every visitor value is bound through prepare().
 */
final class DirectorySearchQuery {
    /** @var object|null */
    private $database;

    /** @param object|null $database wpdb-compatible object; defaults to the global $wpdb. */
    public function __construct( $database = null ) {
        $this->database = $database;
    }

    /**
     * @param array<string,mixed> $profile Normalized profile.
     * @param array{q:string,page:int,sort:string,filters:array<string,mixed>,context?:string} $request Normalized request.
     * @return array{ids:int[],total:int,page:int,pages:int,per_page:int,truncated:bool,searched:bool}
     */
    public function run( array $profile, array $request ): array {
        $database   = $this->db();
        $conditions = $this->where_sql( $database, $profile, $request );
        $per_page   = (int) $profile['per_page'];
        $sort       = $profile['sorts'][ $request['sort'] ] ?? reset( $profile['sorts'] );
        $truncated  = false;

        if ( 'callback' === $sort['type'] ) {
            $limit      = DirectorySearchProfile::MAX_CALLBACK_CANDIDATES + 1;
            $first      = $this->fetch( $profile, $request, $conditions, null, $limit, 1 );
            $candidates = $first['ids'];
            if ( count( $candidates ) > DirectorySearchProfile::MAX_CALLBACK_CANDIDATES ) {
                $truncated  = true;
                $candidates = array_slice( $candidates, 0, DirectorySearchProfile::MAX_CALLBACK_CANDIDATES );
            }

            $ordered = array_map( 'intval', (array) call_user_func( $sort['callback'], $candidates, $request ) );
            $allowed = array_flip( $candidates );
            $ordered = array_values( array_unique( array_filter( $ordered, static fn( int $id ): bool => isset( $allowed[ $id ] ) ) ) );
            $ordered = array_merge( $ordered, array_values( array_diff( $candidates, $ordered ) ) );

            // Beyond the 1,000-row reorder window, rows continue in the default order they were drawn from.
            $total  = $truncated ? $this->fetch( $profile, $request, $conditions, null, 1, 1, true )['total'] : count( $ordered );
            $pages  = max( 1, (int) ceil( $total / $per_page ) );
            $page   = min( $request['page'], $pages );
            $offset = ( $page - 1 ) * $per_page;
            $ids    = array_slice( $ordered, $offset, $per_page );
            if ( $truncated && count( $ids ) < $per_page && $offset + count( $ids ) < $total ) {
                $skip = max( $offset, count( $ordered ) );
                $ids  = array_merge( $ids, $this->fetch( $profile, $request, $conditions, null, $per_page - count( $ids ), 1, false, $skip )['ids'] );
            }
        } else {
            $result = $this->fetch( $profile, $request, $conditions, $sort, $per_page, max( 1, $request['page'] ), true );
            $total  = $result['total'];
            $pages  = max( 1, (int) ceil( $total / $per_page ) );
            $page   = min( $request['page'], $pages );
            $ids    = $page === $request['page'] ? $result['ids'] : $this->fetch( $profile, $request, $conditions, $sort, $per_page, $page )['ids'];
        }

        return [
            'ids'       => $ids,
            'total'     => $total,
            'page'      => $page,
            'pages'     => $pages,
            'per_page'  => $per_page,
            'truncated' => $truncated,
            'searched'  => '' !== $this->search_sql( $database, $profile, $request['q'] ),
        ];
    }

    /**
     * The WordPress query arguments for the profile's scope, the page's
     * context (a category, tag or author the directory sits on) and a sort.
     *
     * @param array<string,mixed>|null $sort Field sort, or null for the default order.
     * @return array<string,mixed>
     */
    public function query_args( array $profile, array $request, ?array $sort = null ): array {
        if ( 'users' === $profile['source'] ) {
            $columns = [ 'name' => 'display_name', 'registered' => 'registered' ];
            $field   = null !== $sort ? ( $columns[ $sort['field'] ] ?? 'display_name' ) : 'display_name';

            return [
                'role__in' => $profile['roles'],
                'orderby'  => [ $field => null !== $sort ? $sort['order'] : 'ASC', 'ID' => 'ASC' ],
            ];
        }

        $columns = [ 'title' => 'title', 'date' => 'date', 'modified' => 'modified' ];
        $args    = [
            'post_type'           => $profile['post_types'],
            'post_status'         => 'publish',
            'has_password'        => false,
            'ignore_sticky_posts' => true,
            'orderby'             => null !== $sort ? [ $columns[ $sort['field'] ] ?? 'date' => $sort['order'], 'ID' => 'ASC' ] : [ 'date' => 'DESC', 'ID' => 'DESC' ],
        ];

        $context = '' !== (string) ( $request['context'] ?? '' ) ? ListingContext::from_token( (string) $request['context'] ) : null;
        if ( null !== $context ) {
            $args = array_merge( $args, $context['args'], [ 'post_type' => $profile['post_types'] ] );
        }

        return $args;
    }

    /**
     * SQL selecting every matching ID for a set of filter values: what the
     * shared filter counts run against.
     *
     * @param array<string,mixed> $values Parsed filter values.
     */
    public function ids_sql( array $profile, array $request, array $values ): string {
        $database   = $this->db();
        $conditions = $this->where_sql( $database, $profile, array_merge( $request, [ 'filters' => $values ] ) );

        return 'users' === $profile['source']
            ? QuerySql::user_ids_sql( $this->query_args( $profile, $request ), $conditions )
            : QuerySql::post_ids_sql( $this->query_args( $profile, $request ), $conditions );
    }

    /**
     * The visitor's search and filter conditions on the posts or users table
     * (without a leading AND). Public for deterministic tests.
     *
     * @param object $database
     */
    public function where_sql( $database, array $profile, array $request ): string {
        $clauses = [];
        $search  = $this->search_sql( $database, $profile, (string) $request['q'] );
        if ( '' !== $search ) {
            $clauses[] = $search;
        }

        $filters = QueryFilterSet::where( $database, $profile['filters'], $request['filters'], QueryFilterSet::scope( 'directory', $profile ), $this->table( $database, $profile ) );

        return implode( ' AND ', array_filter( array_merge( $clauses, $filters ) ) );
    }

    /**
     * Search clause for the visitor text, or '' when no search applies.
     *
     * @param object $database
     */
    public function search_sql( $database, array $profile, string $query ): string {
        $length = function_exists( 'mb_strlen' ) ? mb_strlen( $query, 'UTF-8' ) : strlen( $query );
        if ( $length < (int) $profile['min_chars'] ) {
            return '';
        }

        $terms = SearchTermParser::parse( $query, $profile['term_logic'], (bool) $profile['wildcards'] );
        if ( [] === $terms ) {
            return '';
        }

        $matching = 'exact' === $profile['term_logic'] ? 'contains' : (string) $profile['word_matching'];
        $term_clauses = [];
        foreach ( $terms as $term ) {
            $conditions = $this->term_conditions( $database, $profile, $term, $matching );
            if ( [] !== $conditions ) {
                $term_clauses[] = '(' . implode( ' OR ', $conditions ) . ')';
            }
        }
        if ( [] === $term_clauses ) {
            return '';
        }

        $glue = 'any' === $profile['term_logic'] ? ' OR ' : ' AND ';

        return '(' . implode( $glue, $term_clauses ) . ')';
    }

    /**
     * Runs one WordPress query for IDs.
     *
     * @param array<string,mixed>|null $sort
     * @return array{ids:int[],total:int}
     */
    private function fetch( array $profile, array $request, string $conditions, ?array $sort, int $number, int $page, bool $count = false, int $offset = -1 ): array {
        $args = $this->query_args( $profile, $request, $sort );

        if ( 'users' === $profile['source'] ) {
            $query = new \WP_User_Query();
            QuerySql::attach( $query, $conditions );
            $query->prepare_query( array_merge( $args, [ 'fields' => 'ID', 'number' => $number, 'count_total' => $count ] + ( $offset >= 0 ? [ 'offset' => $offset ] : [ 'paged' => $page ] ) ) );
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
        ] + ( $offset >= 0 ? [ 'offset' => $offset ] : [ 'paged' => $page ] ) ) );

        return [ 'ids' => array_map( 'intval', (array) $ids ), 'total' => (int) $query->found_posts ];
    }

    /** @param object $database @return string[] */
    private function term_conditions( $database, array $profile, string $term, string $matching ): array {
        $wildcards  = (bool) $profile['wildcards'];
        $table      = $this->table( $database, $profile );
        $conditions = [];

        if ( 'users' === $profile['source'] ) {
            foreach ( $profile['fields'] as $field ) {
                $column = DirectorySearchProfile::USER_FIELDS[ $field ] ?? '';
                if ( '' !== $column ) {
                    $conditions[] = SearchMatchSql::condition( $database, $table . '.' . $column, $term, $matching, $wildcards );
                }
            }
            if ( [] !== $profile['meta_keys'] ) {
                $conditions[] = 'EXISTS (SELECT 1 FROM ' . $database->usermeta . ' hds_um'
                    . ' WHERE hds_um.user_id = ' . $table . '.ID'
                    . ' AND hds_um.meta_key IN (' . $this->placeholders( $database, $profile['meta_keys'] ) . ')'
                    . ' AND ' . SearchMatchSql::condition( $database, 'hds_um.meta_value', $term, $matching, $wildcards ) . ')';
            }

            return $conditions;
        }

        foreach ( $profile['fields'] as $field ) {
            $column = DirectorySearchProfile::POST_FIELDS[ $field ] ?? '';
            if ( '' !== $column ) {
                $conditions[] = SearchMatchSql::condition( $database, $table . '.' . $column, $term, $matching, $wildcards );
            }
        }
        if ( [] !== $profile['meta_keys'] ) {
            $conditions[] = 'EXISTS (SELECT 1 FROM ' . $database->postmeta . ' hds_pm'
                . ' WHERE hds_pm.post_id = ' . $table . '.ID'
                . ' AND hds_pm.meta_key IN (' . $this->placeholders( $database, $profile['meta_keys'] ) . ')'
                . ' AND ' . SearchMatchSql::condition( $database, 'hds_pm.meta_value', $term, $matching, $wildcards ) . ')';
        }
        if ( [] !== $profile['taxonomies'] ) {
            $conditions[] = 'EXISTS (SELECT 1 FROM ' . $database->term_relationships . ' hds_tr'
                . ' INNER JOIN ' . $database->term_taxonomy . ' hds_tt ON hds_tt.term_taxonomy_id = hds_tr.term_taxonomy_id'
                . ' INNER JOIN ' . $database->terms . ' hds_t ON hds_t.term_id = hds_tt.term_id'
                . ' WHERE hds_tr.object_id = ' . $table . '.ID'
                . ' AND hds_tt.taxonomy IN (' . $this->placeholders( $database, $profile['taxonomies'] ) . ')'
                . ' AND ' . SearchMatchSql::condition( $database, 'hds_t.name', $term, $matching, $wildcards ) . ')';
        }

        return $conditions;
    }

    /** The posts or users table WordPress queries, unaliased. @param object $database */
    private function table( $database, array $profile ): string {
        return 'users' === $profile['source'] ? (string) $database->users : (string) $database->posts;
    }

    /** @param object $database @param string[] $values */
    private function placeholders( $database, array $values ): string {
        return implode( ', ', array_map( static fn( string $value ): string => $database->prepare( '%s', $value ), $values ) );
    }

    /** @return object */
    private function db() {
        if ( null !== $this->database ) {
            return $this->database;
        }

        global $wpdb;

        return $wpdb;
    }
}
