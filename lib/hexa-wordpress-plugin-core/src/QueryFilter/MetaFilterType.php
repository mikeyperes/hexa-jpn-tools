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
 * values stored on the component's published posts. The `number_range`
 * control matches numbers between a visitor's min and max (either optional).
 */
final class MetaFilterType extends QueryFilterType {
    public const COMPARES = [ '=', 'serialized' ];
    public const MAX_DISTINCT = 100;

    public function controls(): array {
        return [ QueryFilterSet::CONTROL_SELECT, QueryFilterSet::CONTROL_TOGGLE, QueryFilterSet::CONTROL_CHECKBOXES, QueryFilterSet::CONTROL_NUMBER_RANGE ];
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
            $field = \Hexa\PluginCore\Fields\FieldGroups::get_field( (string) $filter['meta_key'] );

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
        [ $table, $owner ] = $this->meta_table( $database, (string) $context['source'] );
        $alias = (string) $context['alias'];
        $exists = 'EXISTS (SELECT 1 FROM ' . $table . ' ' . $alias
            . ' WHERE ' . $alias . '.' . $owner . ' = ' . $context['table'] . '.ID'
            . ' AND ' . $database->prepare( "{$alias}.meta_key = %s", $filter['meta_key'] );

        if ( QueryFilterSet::CONTROL_NUMBER_RANGE === $filter['control'] ) {
            if ( ! is_array( $value ) ) {
                return '';
            }
            $bounds = [];
            foreach ( [ 'min' => '>=', 'max' => '<=' ] as $side => $operator ) {
                $number = QueryFilterSet::number( $value[ $side ] ?? '' );
                if ( '' !== $number ) {
                    // Validated as a plain decimal above, so it is a safe SQL literal.
                    $bounds[] = "{$alias}.meta_value <> '' AND CAST({$alias}.meta_value AS DECIMAL(30,6)) {$operator} " . $number;
                }
            }

            return [] === $bounds ? '' : $exists . ' AND ' . implode( ' AND ', $bounds ) . ')';
        }

        if ( ! is_string( $value ) || '' === $value ) {
            return '';
        }
        if ( QueryFilterSet::CONTROL_TOGGLE === $filter['control'] ) {
            $value = (string) $filter['value'];
        }

        $match = 'serialized' === $filter['compare']
            ? $database->prepare( "({$alias}.meta_value = %s OR {$alias}.meta_value LIKE %s)", $value, '%' . $database->esc_like( '"' . $value . '"' ) . '%' )
            : $database->prepare( "{$alias}.meta_value = %s", $value );

        return $exists . ' AND ' . $match . ')';
    }

    public function counts( $database, array $filter, string $ids_sql, array $context ): ?array {
        [ $table, $owner ] = $this->meta_table( $database, (string) ( $context['source'] ?? 'posts' ) );
        $alias = (string) $context['alias'];
        $from  = ' FROM ' . $table . ' ' . $alias
            . ' WHERE ' . $database->prepare( "{$alias}.meta_key = %s", $filter['meta_key'] )
            . ' AND ' . QuerySql::in_ids( $alias . '.' . $owner, $ids_sql );

        $counts = [];
        if ( 'serialized' !== $filter['compare'] ) {
            foreach ( (array) $database->get_results( 'SELECT ' . $alias . '.meta_value AS option_value, COUNT(DISTINCT ' . $alias . '.' . $owner . ') AS total' . $from . ' GROUP BY ' . $alias . '.meta_value', ARRAY_A ) as $row ) {
                $counts[ (string) $row['option_value'] ] = (int) $row['total'];
            }

            return $counts;
        }

        // Serialized lists (ACF checkboxes, relationships) are tallied value by value, each owner once.
        $members = [];
        foreach ( (array) $database->get_results( 'SELECT ' . $alias . '.' . $owner . ' AS owner_id, ' . $alias . '.meta_value AS meta_value' . $from . ' LIMIT ' . QuerySql::MAX_TALLY_ROWS, ARRAY_A ) as $row ) {
            $stored = function_exists( 'maybe_unserialize' ) ? maybe_unserialize( $row['meta_value'] ) : $row['meta_value'];
            foreach ( is_array( $stored ) ? $stored : [ $stored ] as $one ) {
                if ( is_scalar( $one ) && '' !== (string) $one ) {
                    $members[ (string) $one ][ (int) $row['owner_id'] ] = true;
                }
            }
        }
        foreach ( $members as $one => $owners ) {
            $counts[ (string) $one ] = count( $owners );
        }

        return $counts;
    }
}
