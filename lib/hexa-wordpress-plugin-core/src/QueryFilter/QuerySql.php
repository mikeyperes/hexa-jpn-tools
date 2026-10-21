<?php

namespace Hexa\PluginCore\QueryFilter;

/**
 * Runs filters and search through WordPress's own query classes.
 *
 * attach() adds trusted SQL conditions to one exact WP_Query or WP_User_Query
 * object, so the query keeps every WordPress argument, hook and other plugin
 * that touches it. post_ids_sql() and user_ids_sql() return the SQL WordPress
 * would run for a set of arguments, without running it, so result counts and
 * totals are worked out against exactly the same results the visitor sees.
 */
final class QuerySql {
    /** Bound on rows a type may read to tally values in PHP (serialized fields, nested terms). */
    public const MAX_TALLY_ROWS = 50000;

    /** @var \WeakMap<object,string[]>|null */
    private static ?\WeakMap $conditions = null;

    private static bool $hooked = false;

    /**
     * Adds conditions (SQL without a leading AND) to one exact query object.
     * They apply the next time that object runs, then stay for its later runs.
     *
     * @param object $query \WP_Query or \WP_User_Query.
     */
    public static function attach( $query, string $sql ): void {
        $sql = trim( $sql );
        if ( ! is_object( $query ) || '' === $sql ) {
            return;
        }

        self::hook();
        self::$conditions ??= new \WeakMap();
        $current = self::$conditions[ $query ] ?? [];
        $current[] = $sql;
        self::$conditions[ $query ] = $current;
    }

    /** Conditions attached to one query object. @param object $query @return string[] */
    public static function attached( $query ): array {
        return is_object( $query ) && self::$conditions instanceof \WeakMap ? ( self::$conditions[ $query ] ?? [] ) : [];
    }

    /** @param mixed $where @param mixed $query */
    public static function filter_posts_where( $where, $query ): string {
        $conditions = self::attached( $query );

        return (string) $where . ( [] !== $conditions ? ' AND (' . implode( ') AND (', $conditions ) . ')' : '' );
    }

    /** @param mixed $query \WP_User_Query, passed by reference by WordPress. */
    public static function filter_user_query( $query ): void {
        $conditions = self::attached( $query );
        if ( [] !== $conditions && is_object( $query ) && property_exists( $query, 'query_where' ) ) {
            $query->query_where .= ' AND (' . implode( ') AND (', $conditions ) . ')';
        }
    }

    /**
     * The SQL WordPress builds for these post query arguments, selecting every
     * matching ID (no paging) in a column named `ID`; '' outside WordPress.
     *
     * @param array<string,mixed> $args WP_Query arguments.
     */
    public static function post_ids_sql( array $args, string $conditions = '' ): string {
        if ( ! class_exists( '\WP_Query' ) || ! function_exists( 'add_filter' ) ) {
            return '';
        }

        $query = new \WP_Query();
        self::attach( $query, $conditions );
        $capture = static function ( $posts, $candidate ) use ( $query ) {
            return $candidate === $query ? [] : $posts;
        };
        add_filter( 'posts_pre_query', $capture, PHP_INT_MAX, 2 );
        $query->query( array_merge( $args, [
            'fields'                 => 'ids',
            'posts_per_page'         => -1,
            'nopaging'               => true,
            'paged'                  => 1,
            'offset'                 => 0,
            'no_found_rows'          => true,
            'ignore_sticky_posts'    => true,
            'suppress_filters'       => false,
            'cache_results'          => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ] ) );
        remove_filter( 'posts_pre_query', $capture, PHP_INT_MAX );

        return trim( (string) $query->request );
    }

    /**
     * The SQL WordPress builds for these user query arguments, selecting every
     * matching user ID in a column named `ID`; '' outside WordPress.
     *
     * @param array<string,mixed> $args WP_User_Query arguments.
     */
    public static function user_ids_sql( array $args, string $conditions = '' ): string {
        if ( ! class_exists( '\WP_User_Query' ) ) {
            return '';
        }

        $query = new \WP_User_Query();
        self::attach( $query, $conditions );
        $query->prepare_query( array_merge( $args, [ 'fields' => 'ID', 'number' => -1, 'paged' => 1, 'offset' => 0, 'count_total' => false ] ) );

        return trim( 'SELECT ' . $query->query_fields . ' ' . $query->query_from . ' ' . $query->query_where );
    }

    /** `$column IN (the IDs selected by $ids_sql)`. */
    public static function in_ids( string $column, string $ids_sql ): string {
        return $column . ' IN (SELECT hexa_ids.ID FROM (' . $ids_sql . ') hexa_ids)';
    }

    /** How many rows `$ids_sql` selects. @param object $database */
    public static function count( $database, string $ids_sql ): int {
        return '' === $ids_sql ? 0 : (int) $database->get_var( 'SELECT COUNT(*) FROM (' . $ids_sql . ') hexa_total' );
    }

    private static function hook(): void {
        if ( self::$hooked || ! function_exists( 'add_filter' ) ) {
            return;
        }
        self::$hooked = true;
        add_filter( 'posts_where', [ self::class, 'filter_posts_where' ], 20, 2 );
        add_action( 'pre_user_query', [ self::class, 'filter_user_query' ], 20 );
    }
}
