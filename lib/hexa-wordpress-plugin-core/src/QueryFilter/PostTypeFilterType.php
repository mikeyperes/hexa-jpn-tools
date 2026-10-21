<?php

namespace Hexa\PluginCore\QueryFilter;

/**
 * Filters posts by content type (posts, pages, any custom post type), for
 * listings that mix several, such as site search. Keyword option 'types'
 * lists the component's post types by their plural name.
 */
final class PostTypeFilterType extends QueryFilterType {
    public function controls(): array {
        return [ QueryFilterSet::CONTROL_CHECKBOXES, QueryFilterSet::CONTROL_SELECT ];
    }

    public function sources(): array {
        return [ 'posts' ];
    }

    public function keywords(): array {
        return [ 'types' ];
    }

    public function keyword_options( array $filter, string $keyword, array $scope ): array {
        if ( 'types' !== $keyword ) {
            return [];
        }

        $options = [];
        foreach ( (array) ( $scope['post_types'] ?? [] ) as $type ) {
            $object = function_exists( 'get_post_type_object' ) ? get_post_type_object( (string) $type ) : null;
            $options[ (string) $type ] = is_object( $object ) && isset( $object->labels->name ) ? (string) $object->labels->name : ucfirst( (string) $type );
        }

        return $options;
    }

    public function where( $database, array $filter, $value, array $context ): string {
        return is_string( $value ) && '' !== $value ? $database->prepare( $context['table'] . '.post_type = %s', $value ) : '';
    }

    public function counts( $database, array $filter, string $ids_sql, array $context ): ?array {
        $alias  = (string) $context['alias'];
        $counts = [];
        foreach ( (array) $database->get_results(
            'SELECT ' . $alias . '.post_type AS option_value, COUNT(*) AS total FROM ' . $database->posts . ' ' . $alias
            . ' WHERE ' . QuerySql::in_ids( $alias . '.ID', $ids_sql ) . ' GROUP BY ' . $alias . '.post_type',
            ARRAY_A
        ) as $row ) {
            $counts[ (string) $row['option_value'] ] = (int) $row['total'];
        }

        return $counts;
    }
}
