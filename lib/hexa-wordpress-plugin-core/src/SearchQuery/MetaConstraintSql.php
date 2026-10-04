<?php

namespace Hexa\PluginCore\SearchQuery;

/**
 * Compiles a bounded post-meta predicate tree without WP_Meta_Query joins.
 *
 * Trusted component adapters can use this for exact queries whose host-owned
 * date or state constraints would otherwise multiply result rows before the
 * reusable search clause runs.
 */
final class MetaConstraintSql {
    private const MAX_DEPTH = 4;
    private const MAX_LEAVES = 20;
    private const COMPARES = [ '=', '>', '>=', '<', '<=', 'EXISTS', 'NOT EXISTS' ];
    private const TYPES = [ 'CHAR', 'NUMERIC', 'SIGNED', 'UNSIGNED', 'DECIMAL', 'DATE', 'DATETIME' ];

    /**
     * @param object $database wpdb-compatible object exposing prepare().
     * @param array<string|int,mixed> $constraints
     */
    public static function compile( $database, array $constraints, string $post_id_column = '' ): string {
        if ( [] === $constraints ) {
            return '';
        }
        if ( ! is_object( $database ) || ! method_exists( $database, 'prepare' )
            || ! isset( $database->posts, $database->postmeta )
        ) {
            return '1=0';
        }

        $post_id_column = '' !== $post_id_column ? trim( $post_id_column ) : $database->posts . '.ID';
        if ( ! preg_match( '/^[A-Za-z0-9_$.]+$/D', $post_id_column ) ) {
            return '1=0';
        }

        $leaf_index = 0;
        $sql = self::node(
            $database,
            $constraints,
            $post_id_column,
            0,
            $leaf_index
        );

        return null === $sql ? '1=0' : $sql;
    }

    /**
     * @param object $database
     * @param array<string|int,mixed> $node
     */
    private static function node( $database, array $node, string $post_id_column, int $depth, int &$leaf_index ): ?string {
        if ( $depth > self::MAX_DEPTH ) {
            return null;
        }
        if ( array_key_exists( 'key', $node ) ) {
            return self::leaf( $database, $node, $post_id_column, $leaf_index );
        }

        $relation = strtoupper( trim( (string) ( $node['relation'] ?? 'AND' ) ) );
        if ( ! in_array( $relation, [ 'AND', 'OR' ], true ) ) {
            return null;
        }

        $parts = [];
        foreach ( $node as $key => $child ) {
            if ( 'relation' === $key ) {
                continue;
            }
            if ( ! is_array( $child ) ) {
                return null;
            }
            $part = self::node( $database, $child, $post_id_column, $depth + 1, $leaf_index );
            if ( null === $part || '' === $part ) {
                return null;
            }
            $parts[] = $part;
        }

        return [] === $parts ? null : '(' . implode( ' ' . $relation . ' ', $parts ) . ')';
    }

    /**
     * @param object $database
     * @param array<string|int,mixed> $leaf
     */
    private static function leaf( $database, array $leaf, string $post_id_column, int &$leaf_index ): ?string {
        if ( $leaf_index >= self::MAX_LEAVES ) {
            return null;
        }

        $raw_meta_key = strtolower( trim( (string) ( $leaf['key'] ?? '' ) ) );
        $meta_key = self::key( $raw_meta_key );
        $compare = strtoupper( trim( (string) ( $leaf['compare'] ?? '=' ) ) );
        $type = strtoupper( trim( (string) ( $leaf['type'] ?? 'CHAR' ) ) );
        if ( '' === $meta_key || $meta_key !== $raw_meta_key
            || ! in_array( $compare, self::COMPARES, true ) || ! in_array( $type, self::TYPES, true )
        ) {
            return null;
        }

        $alias = 'hexa_sq_mc' . $leaf_index++;
        $key_sql = $database->prepare( $alias . '.meta_key = %s', $meta_key );
        if ( 'EXISTS' === $compare || 'NOT EXISTS' === $compare ) {
            return ( 'NOT EXISTS' === $compare ? 'NOT ' : '' )
                . 'EXISTS (SELECT 1 FROM ' . $database->postmeta . ' ' . $alias
                . ' WHERE ' . $alias . '.post_id = ' . $post_id_column
                . ' AND ' . $key_sql . ')';
        }
        if ( ! array_key_exists( 'value', $leaf ) || ! is_scalar( $leaf['value'] ) ) {
            return null;
        }
        if ( in_array( $type, [ 'NUMERIC', 'SIGNED', 'UNSIGNED', 'DECIMAL' ], true ) && ! is_numeric( $leaf['value'] ) ) {
            return null;
        }
        if ( 'DATE' === $type && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', (string) $leaf['value'] ) ) {
            return null;
        }
        if ( 'DATETIME' === $type && ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', (string) $leaf['value'] ) ) {
            return null;
        }

        [ $value_column, $placeholder, $value ] = self::typed_value( $alias . '.meta_value', $type, $leaf['value'] );
        $value_sql = $database->prepare( $value_column . ' ' . $compare . ' ' . $placeholder, $value );

        return 'EXISTS (SELECT 1 FROM ' . $database->postmeta . ' ' . $alias
            . ' WHERE ' . $alias . '.post_id = ' . $post_id_column
            . ' AND ' . $key_sql . ' AND ' . $value_sql . ')';
    }

    /** @param mixed $value @return array{string,string,mixed} */
    private static function typed_value( string $column, string $type, $value ): array {
        switch ( $type ) {
            case 'NUMERIC':
            case 'SIGNED':
                return [ 'CAST(' . $column . ' AS SIGNED)', '%s', (string) $value ];
            case 'UNSIGNED':
                return [ 'CAST(' . $column . ' AS UNSIGNED)', '%s', (string) $value ];
            case 'DECIMAL':
                return [ 'CAST(' . $column . ' AS DECIMAL(30,10))', '%s', (string) $value ];
            case 'DATE':
                return [ 'CAST(' . $column . ' AS DATE)', '%s', (string) $value ];
            case 'DATETIME':
                return [ 'CAST(' . $column . ' AS DATETIME)', '%s', (string) $value ];
            case 'CHAR':
            default:
                return [ $column, '%s', (string) $value ];
        }
    }

    private static function key( string $value ): string {
        $value = strtolower( trim( $value ) );

        return (string) preg_replace( '/[^a-z0-9_\-]/', '', $value );
    }
}
