<?php

namespace Hexa\PluginCore\Calendar;

/** Host-declared, stable multi-criterion ordering of the items within each day. */
final class CalendarSort {
    public const DEFAULT_CRITERIA = [
        [ 'field' => 'continued', 'direction' => 'desc' ],
        [ 'field' => 'start' ],
        [ 'field' => 'title' ],
    ];

    /**
     * Each criterion selects a dot-separated item field or a `value` callback.
     * Invalid declarations are rejected rather than silently changing priority.
     *
     * @return list<array<string,mixed>>
     */
    public static function normalize( array $criteria ): array {
        $normalized = [];
        foreach ( $criteria as $criterion ) {
            if ( is_string( $criterion ) ) {
                $criterion = [ 'field' => $criterion ];
            }
            if ( ! is_array( $criterion ) ) {
                throw new \InvalidArgumentException( 'A calendar sort criterion must be a field name or an array.' );
            }

            $field     = is_string( $criterion['field'] ?? null ) ? trim( $criterion['field'] ) : '';
            $value     = $criterion['value'] ?? null;
            $direction = strtolower( (string) ( $criterion['direction'] ?? 'asc' ) );
            $type      = $criterion['type'] ?? 'auto';
            $missing   = $criterion['missing'] ?? 'last';
            if ( ( '' === $field && ! is_callable( $value ) ) || ( '' !== $field && null !== $value )
                || ( '' !== $field && ! preg_match( '/^[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*$/', $field ) )
                || ! in_array( $direction, [ 'asc', 'desc' ], true )
                || ! in_array( $type, [ 'auto', 'text', 'number', 'boolean' ], true )
                || ! in_array( $missing, [ 'first', 'last' ], true ) ) {
                throw new \InvalidArgumentException( 'Invalid calendar sort field, callback, direction, type, or missing-value order.' );
            }

            $normalized[] = [ 'field' => $field, 'value' => $value, 'direction' => $direction, 'type' => $type, 'missing' => $missing ];
        }

        return $normalized;
    }

    /**
     * Fields include `start`, `title`, placement flags, and host `data.*` values.
     * Callbacks receive the complete placed item and are evaluated once per item.
     * Missing values keep their declared position regardless of sort direction.
     * Equal criteria retain input order; an empty list also retains input order.
     *
     * @param list<array<string,mixed>> $items
     * @return list<array<string,mixed>>
     */
    public static function items( array $items, array $criteria = self::DEFAULT_CRITERIA ): array {
        $criteria = self::normalize( $criteria );
        if ( [] === $criteria || count( $items ) < 2 ) {
            return array_values( $items );
        }

        $rows = [];
        foreach ( $items as $item ) {
            $values = [];
            foreach ( $criteria as $criterion ) {
                $value = $item;
                if ( null !== $criterion['value'] ) {
                    $value = call_user_func( $criterion['value'], $item );
                } else {
                    foreach ( explode( '.', $criterion['field'] ) as $key ) {
                        $value = is_array( $value ) ? ( $value[ $key ] ?? null ) : null;
                    }
                }
                $values[] = is_scalar( $value ) && '' !== $value ? $value : null;
            }
            $rows[] = [ 'item' => $item, 'values' => $values, 'index' => count( $rows ) ];
        }

        usort( $rows, static function ( array $left, array $right ) use ( $criteria ): int {
            foreach ( $criteria as $index => $criterion ) {
                $a = $left['values'][ $index ];
                $b = $right['values'][ $index ];
                if ( null === $a || null === $b ) {
                    $comparison = ( null === $a ? 1 : 0 ) <=> ( null === $b ? 1 : 0 );
                    if ( 0 !== $comparison ) {
                        return 'first' === $criterion['missing'] ? -$comparison : $comparison;
                    }
                    continue;
                }
                $comparison = match ( $criterion['type'] ) {
                    'text'    => strnatcasecmp( (string) $a, (string) $b ),
                    'number'  => (float) $a <=> (float) $b,
                    'boolean' => (bool) $a <=> (bool) $b,
                    default   => $a <=> $b,
                };
                if ( 0 !== $comparison ) {
                    return 'desc' === $criterion['direction'] ? -$comparison : $comparison;
                }
            }

            return $left['index'] <=> $right['index'];
        } );

        return array_column( $rows, 'item' );
    }
}
