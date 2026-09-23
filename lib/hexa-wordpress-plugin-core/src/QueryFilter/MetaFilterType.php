<?php

namespace Hexa\PluginCore\QueryFilter;

use Hexa\PluginCore\PublicComponents\ProfileValues;

/**
 * Filters by one custom field (post or user meta), including ACF fields.
 *
 * `compare` is `=` for single stored values or `serialized` for ACF arrays
 * (multi-selects, checkboxes, relationship and taxonomy fields). A toggle
 * matches the definition's `value` (default '1', an ACF true/false field).
 * Keyword options: 'acf' reads the ACF field's choices; 'distinct' lists the
 * values stored on the component's published posts.
 */
final class MetaFilterType extends QueryFilterType {
    public const COMPARES = [ '=', 'serialized' ];
    public const MAX_DISTINCT = 100;

    public function controls(): array {
        return [ QueryFilterSet::CONTROL_SELECT, QueryFilterSet::CONTROL_TOGGLE ];
    }

    public function keywords(): array {
        return [ 'acf', 'distinct' ];
    }

    public function normalize( array $definition, string $source ): ?array {
        $meta_key = ProfileValues::meta_key( $definition['meta_key'] ?? '' );
        if ( '' === $meta_key ) {
            return null;
        }

        return [
            'meta_key' => $meta_key,
            'compare'  => ProfileValues::choice( $definition['compare'] ?? '=', self::COMPARES, '=' ),
            'value'    => is_scalar( $definition['value'] ?? null ) && '' !== (string) $definition['value'] ? (string) $definition['value'] : '1',
        ];
    }

    public function keyword_options( array $filter, string $keyword, array $scope ): array {
        if ( 'acf' === $keyword ) {
            $field = function_exists( 'acf_get_field' ) ? acf_get_field( $filter['meta_key'] ) : null;

            return is_array( $field ) && is_array( $field['choices'] ?? null ) ? array_map( 'strval', $field['choices'] ) : [];
        }

        // Distinct values are listed only from published posts of the component's types; user meta never enumerates.
        if ( 'distinct' !== $keyword || 'posts' !== ( $scope['source'] ?? '' ) || [] === ( $scope['post_types'] ?? [] ) ) {
            return [];
        }

        global $wpdb;
        $types  = implode( ', ', array_map( static fn( string $type ): string => $wpdb->prepare( '%s', $type ), $scope['post_types'] ) );
        $values = (array) $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT m.meta_value FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id"
            . " WHERE m.meta_key = %s AND m.meta_value <> '' AND m.meta_value NOT LIKE %s"
            . " AND p.post_type IN ({$types}) AND p.post_status = 'publish' AND p.post_password = ''"
            . ' ORDER BY m.meta_value ASC LIMIT %d',
            $filter['meta_key'],
            $wpdb->esc_like( 'a:' ) . '%',
            self::MAX_DISTINCT
        ) );

        $options = [];
        foreach ( $values as $value ) {
            $options[ (string) $value ] = (string) $value;
        }

        return $options;
    }

    public function where( $database, array $filter, $value, array $context ): string {
        if ( ! is_string( $value ) || '' === $value ) {
            return '';
        }
        if ( QueryFilterSet::CONTROL_TOGGLE === $filter['control'] ) {
            $value = (string) $filter['value'];
        }

        [ $table, $owner ] = $this->meta_table( $database, (string) $context['source'] );
        $alias = (string) $context['alias'];
        $match = 'serialized' === $filter['compare']
            ? $database->prepare( "({$alias}.meta_value = %s OR {$alias}.meta_value LIKE %s)", $value, '%' . $database->esc_like( '"' . $value . '"' ) . '%' )
            : $database->prepare( "{$alias}.meta_value = %s", $value );

        return 'EXISTS (SELECT 1 FROM ' . $table . ' ' . $alias
            . ' WHERE ' . $alias . '.' . $owner . ' = ' . $context['table'] . '.ID'
            . ' AND ' . $database->prepare( "{$alias}.meta_key = %s", $filter['meta_key'] )
            . ' AND ' . $match . ')';
    }
}
