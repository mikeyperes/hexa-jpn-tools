<?php

namespace Hexa\PluginCore\DirectorySearch;

use Hexa\PluginCore\QueryFilter\QueryFilterSet;
use Hexa\PluginCore\SearchQuery\SearchMatchSql;
use Hexa\PluginCore\SearchQuery\SearchTermParser;

/**
 * Resolves one page of result IDs for a directory search profile.
 *
 * Every identifier comes from Core whitelists and every visitor value is
 * bound through prepare(). Optional sources (meta, taxonomy) use correlated
 * EXISTS subqueries so rows never duplicate and unused sources cost nothing.
 */
final class DirectorySearchQuery {
    private const POST_ALIAS = 'hds_p';
    private const USER_ALIAS = 'hds_u';

    /** @var object|null */
    private $database;

    /** @param object|null $database wpdb-compatible object; defaults to the global $wpdb. */
    public function __construct( $database = null ) {
        $this->database = $database;
    }

    /**
     * @param array<string,mixed> $profile Normalized profile.
     * @param array{q:string,page:int,sort:string,filters:array<string,mixed>} $request Normalized request.
     * @return array{ids:int[],total:int,page:int,pages:int,per_page:int,truncated:bool,searched:bool}
     */
    public function run( array $profile, array $request ): array {
        $database = $this->db();
        $alias    = $this->alias( $profile );
        $from     = $this->from_sql( $database, $profile );
        $where    = $this->where_sql( $database, $profile, $request );
        $per_page = (int) $profile['per_page'];
        $sort     = $profile['sorts'][ $request['sort'] ] ?? reset( $profile['sorts'] );
        $truncated = false;

        if ( 'callback' === $sort['type'] ) {
            $limit = DirectorySearchProfile::MAX_CALLBACK_CANDIDATES + 1;
            $candidates = array_map( 'intval', (array) $database->get_col(
                "SELECT {$alias}.ID {$from} WHERE {$where} ORDER BY " . $this->default_order_sql( $profile ) . " LIMIT {$limit}"
            ) );
            if ( count( $candidates ) > DirectorySearchProfile::MAX_CALLBACK_CANDIDATES ) {
                $truncated = true;
                $candidates = array_slice( $candidates, 0, DirectorySearchProfile::MAX_CALLBACK_CANDIDATES );
            }

            $ordered = array_map( 'intval', (array) call_user_func( $sort['callback'], $candidates, $request ) );
            $allowed = array_flip( $candidates );
            $ordered = array_values( array_unique( array_filter( $ordered, static fn( int $id ): bool => isset( $allowed[ $id ] ) ) ) );
            $ordered = array_merge( $ordered, array_values( array_diff( $candidates, $ordered ) ) );

            // Beyond the 1,000-row reorder window, rows continue in the default SQL order they were drawn from.
            $total  = $truncated ? (int) $database->get_var( "SELECT COUNT(*) {$from} WHERE {$where}" ) : count( $ordered );
            $pages  = max( 1, (int) ceil( $total / $per_page ) );
            $page   = min( $request['page'], $pages );
            $offset = ( $page - 1 ) * $per_page;
            $ids    = array_slice( $ordered, $offset, $per_page );
            if ( $truncated && count( $ids ) < $per_page && $offset + count( $ids ) < $total ) {
                $skip  = max( $offset, count( $ordered ) );
                $limit = $per_page - count( $ids );
                $ids   = array_merge( $ids, array_map( 'intval', (array) $database->get_col(
                    "SELECT {$alias}.ID {$from} WHERE {$where} ORDER BY " . $this->default_order_sql( $profile ) . " LIMIT {$limit} OFFSET {$skip}"
                ) ) );
            }
        } else {
            $total = (int) $database->get_var( "SELECT COUNT(*) {$from} WHERE {$where}" );
            $pages = max( 1, (int) ceil( $total / $per_page ) );
            $page  = min( $request['page'], $pages );
            $offset = ( $page - 1 ) * $per_page;
            $ids = array_map( 'intval', (array) $database->get_col(
                "SELECT {$alias}.ID {$from} WHERE {$where} ORDER BY " . $this->field_order_sql( $profile, $sort )
                . " LIMIT {$per_page} OFFSET {$offset}"
            ) );
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

    /** @param object $database */
    public function from_sql( $database, array $profile ): string {
        return 'users' === $profile['source']
            ? 'FROM ' . $database->users . ' ' . self::USER_ALIAS
            : 'FROM ' . $database->posts . ' ' . self::POST_ALIAS;
    }

    /**
     * Complete WHERE clause (without the keyword). Public for deterministic tests.
     *
     * @param object $database
     */
    public function where_sql( $database, array $profile, array $request ): string {
        $clauses = [ $this->scope_sql( $database, $profile ) ];

        $search = $this->search_sql( $database, $profile, (string) $request['q'] );
        if ( '' !== $search ) {
            $clauses[] = $search;
        }

        $filters = QueryFilterSet::where( $database, $profile['filters'], $request['filters'], QueryFilterSet::scope( 'directory', $profile ), $this->alias( $profile ) );

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

    /** @param object $database @return string[] */
    private function term_conditions( $database, array $profile, string $term, string $matching ): array {
        $wildcards = (bool) $profile['wildcards'];
        $conditions = [];

        if ( 'users' === $profile['source'] ) {
            foreach ( $profile['fields'] as $field ) {
                $column = DirectorySearchProfile::USER_FIELDS[ $field ] ?? '';
                if ( '' !== $column ) {
                    $conditions[] = SearchMatchSql::condition( $database, self::USER_ALIAS . '.' . $column, $term, $matching, $wildcards );
                }
            }
            if ( [] !== $profile['meta_keys'] ) {
                $conditions[] = 'EXISTS (SELECT 1 FROM ' . $database->usermeta . ' hds_um'
                    . ' WHERE hds_um.user_id = ' . self::USER_ALIAS . '.ID'
                    . ' AND hds_um.meta_key IN (' . $this->placeholders( $database, $profile['meta_keys'] ) . ')'
                    . ' AND ' . SearchMatchSql::condition( $database, 'hds_um.meta_value', $term, $matching, $wildcards ) . ')';
            }

            return $conditions;
        }

        foreach ( $profile['fields'] as $field ) {
            $column = DirectorySearchProfile::POST_FIELDS[ $field ] ?? '';
            if ( '' !== $column ) {
                $conditions[] = SearchMatchSql::condition( $database, self::POST_ALIAS . '.' . $column, $term, $matching, $wildcards );
            }
        }
        if ( [] !== $profile['meta_keys'] ) {
            $conditions[] = 'EXISTS (SELECT 1 FROM ' . $database->postmeta . ' hds_pm'
                . ' WHERE hds_pm.post_id = ' . self::POST_ALIAS . '.ID'
                . ' AND hds_pm.meta_key IN (' . $this->placeholders( $database, $profile['meta_keys'] ) . ')'
                . ' AND ' . SearchMatchSql::condition( $database, 'hds_pm.meta_value', $term, $matching, $wildcards ) . ')';
        }
        if ( [] !== $profile['taxonomies'] ) {
            $conditions[] = 'EXISTS (SELECT 1 FROM ' . $database->term_relationships . ' hds_tr'
                . ' INNER JOIN ' . $database->term_taxonomy . ' hds_tt ON hds_tt.term_taxonomy_id = hds_tr.term_taxonomy_id'
                . ' INNER JOIN ' . $database->terms . ' hds_t ON hds_t.term_id = hds_tt.term_id'
                . ' WHERE hds_tr.object_id = ' . self::POST_ALIAS . '.ID'
                . ' AND hds_tt.taxonomy IN (' . $this->placeholders( $database, $profile['taxonomies'] ) . ')'
                . ' AND ' . SearchMatchSql::condition( $database, 'hds_t.name', $term, $matching, $wildcards ) . ')';
        }

        return $conditions;
    }

    /** @param object $database */
    private function scope_sql( $database, array $profile ): string {
        if ( 'users' === $profile['source'] ) {
            $prefix = method_exists( $database, 'get_blog_prefix' ) ? (string) $database->get_blog_prefix() : (string) ( $database->prefix ?? 'wp_' );
            $roles = [];
            foreach ( $profile['roles'] as $role ) {
                $roles[] = $database->prepare( 'hds_cap.meta_value LIKE %s', '%' . $database->esc_like( '"' . $role . '"' ) . '%' );
            }

            return 'EXISTS (SELECT 1 FROM ' . $database->usermeta . ' hds_cap'
                . ' WHERE hds_cap.user_id = ' . self::USER_ALIAS . '.ID'
                . ' AND ' . $database->prepare( 'hds_cap.meta_key = %s', $prefix . 'capabilities' )
                . ' AND (' . implode( ' OR ', $roles ) . '))';
        }

        return self::POST_ALIAS . '.post_type IN (' . $this->placeholders( $database, $profile['post_types'] ) . ')'
            . ' AND ' . self::POST_ALIAS . ".post_status = 'publish'"
            . ' AND ' . self::POST_ALIAS . ".post_password = ''";
    }

    private function field_order_sql( array $profile, array $sort ): string {
        $columns = 'users' === $profile['source'] ? DirectorySearchProfile::USER_SORT_FIELDS : DirectorySearchProfile::POST_SORT_FIELDS;
        $column = $columns[ $sort['field'] ] ?? '';
        if ( '' === $column ) {
            return $this->default_order_sql( $profile );
        }
        $alias = $this->alias( $profile );

        return $alias . '.' . $column . ' ' . ( 'DESC' === $sort['order'] ? 'DESC' : 'ASC' ) . ', ' . $alias . '.ID ASC';
    }

    private function default_order_sql( array $profile ): string {
        return 'users' === $profile['source']
            ? self::USER_ALIAS . '.display_name ASC, ' . self::USER_ALIAS . '.ID ASC'
            : self::POST_ALIAS . '.post_date DESC, ' . self::POST_ALIAS . '.ID DESC';
    }

    private function alias( array $profile ): string {
        return 'users' === $profile['source'] ? self::USER_ALIAS : self::POST_ALIAS;
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
