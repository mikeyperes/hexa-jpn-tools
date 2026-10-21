<?php

namespace Hexa\PluginCore\QueryFilter;

use Hexa\PluginCore\PublicComponents\ProfileValues;

/**
 * Filters posts by author, matched by the author's address slug (default,
 * `user_field` `nicename`) or user id. Keyword option 'authors' lists the
 * people with published posts of the component's post types, by name.
 *
 * A plugin that credits extra authors (co-authors) widens the match with the
 * `hexa_plugin_core_query_filter_author_condition` filter, which receives the
 * condition, the user id, the posts table reference and a unique alias.
 */
final class AuthorFilterType extends QueryFilterType {
    public const USER_FIELDS = [ 'nicename', 'id' ];
    public const CONDITION_FILTER = 'hexa_plugin_core_query_filter_author_condition';
    public const MAX_AUTHORS = 200;

    public function controls(): array {
        return [ QueryFilterSet::CONTROL_CHECKBOXES, QueryFilterSet::CONTROL_SELECT ];
    }

    public function sources(): array {
        return [ 'posts' ];
    }

    public function normalize( array $definition, string $source ): ?array {
        return [ 'user_field' => ProfileValues::choice( $definition['user_field'] ?? 'nicename', self::USER_FIELDS, 'nicename' ) ];
    }

    public function keywords(): array {
        return [ 'authors' ];
    }

    public function keyword_options( array $filter, string $keyword, array $scope ): array {
        if ( 'authors' !== $keyword || [] === ( $scope['post_types'] ?? [] ) ) {
            return [];
        }

        global $wpdb;
        $types = implode( ', ', array_map( static fn( string $type ): string => $wpdb->prepare( '%s', $type ), (array) $scope['post_types'] ) );
        $rows  = (array) $wpdb->get_results( $wpdb->prepare(
            "SELECT u.ID, u.user_nicename, u.display_name FROM {$wpdb->users} u"
            . " WHERE u.ID IN (SELECT DISTINCT p.post_author FROM {$wpdb->posts} p WHERE p.post_type IN ({$types}) AND p.post_status = 'publish' AND p.post_password = '')"
            . ' ORDER BY u.display_name ASC LIMIT %d',
            self::MAX_AUTHORS
        ), ARRAY_A );

        $options = [];
        foreach ( $rows as $row ) {
            $options[ 'id' === $filter['user_field'] ? (string) $row['ID'] : (string) $row['user_nicename'] ] = (string) $row['display_name'];
        }

        return $options;
    }

    public function where( $database, array $filter, $value, array $context ): string {
        $user_id = $this->user_id( $filter, $value );
        if ( $user_id <= 0 ) {
            return '1=0';
        }

        $sql = $database->prepare( $context['table'] . '.post_author = %d', $user_id );
        if ( function_exists( 'apply_filters' ) ) {
            $sql = (string) apply_filters( self::CONDITION_FILTER, $sql, $user_id, (string) $context['table'], (string) $context['alias'] );
        }

        return '(' . $sql . ')';
    }

    public function counts( $database, array $filter, string $ids_sql, array $context ): ?array {
        $alias  = (string) $context['alias'];
        $column = 'id' === $filter['user_field'] ? $alias . '_u.ID' : $alias . '_u.user_nicename';
        $counts = [];
        foreach ( (array) $database->get_results(
            'SELECT ' . $column . ' AS option_value, COUNT(*) AS total FROM ' . $database->posts . ' ' . $alias
            . ' INNER JOIN ' . $database->users . ' ' . $alias . '_u ON ' . $alias . '_u.ID = ' . $alias . '.post_author'
            . ' WHERE ' . QuerySql::in_ids( $alias . '.ID', $ids_sql )
            . ' GROUP BY ' . $alias . '.post_author',
            ARRAY_A
        ) as $row ) {
            $counts[ (string) $row['option_value'] ] = (int) $row['total'];
        }

        return $counts;
    }

    /** @param mixed $value */
    private function user_id( array $filter, $value ): int {
        if ( ! is_string( $value ) || '' === $value ) {
            return 0;
        }
        if ( 'id' === $filter['user_field'] ) {
            return max( 0, (int) $value );
        }
        $user = function_exists( 'get_user_by' ) ? get_user_by( 'slug', $value ) : false;

        return is_object( $user ) ? (int) $user->ID : 0;
    }
}
