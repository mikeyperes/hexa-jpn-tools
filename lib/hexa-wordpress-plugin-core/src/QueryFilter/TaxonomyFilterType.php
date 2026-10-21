<?php

namespace Hexa\PluginCore\QueryFilter;

use Hexa\PluginCore\PublicComponents\ProfileValues;

/**
 * Filters posts by one taxonomy term, matched by slug (default) or term id.
 * In a hierarchical taxonomy a term also matches its descendants, like
 * WordPress's own `include_children` default; set `include_children` false to
 * match only the exact term. Keyword option 'terms' lists the taxonomy's
 * non-empty terms by name; a hierarchical taxonomy lists them as a tree
 * (parents before their children, with depths()) and `child_of` (a term id)
 * limits the list to one term's descendants. Counts include child terms'
 * results in their parents, without counting one result twice.
 */
final class TaxonomyFilterType extends QueryFilterType {
    public const TERM_FIELDS = [ 'slug', 'term_id' ];
    public const MAX_TERMS = 200;

    /** @var array<string,array<string,int>> Option depths per taxonomy listing, filled by keyword_options(). */
    private static array $depths = [];

    public function sources(): array {
        return [ 'posts' ];
    }

    public function controls(): array {
        return [ QueryFilterSet::CONTROL_SELECT, QueryFilterSet::CONTROL_CHECKBOXES ];
    }

    public function normalize( array $definition, string $source ): ?array {
        $taxonomy = ProfileValues::key( (string) ( $definition['taxonomy'] ?? '' ) );
        if ( '' === $taxonomy ) {
            return null;
        }

        return [
            'taxonomy'         => $taxonomy,
            'term_field'       => ProfileValues::choice( $definition['term_field'] ?? 'slug', self::TERM_FIELDS, 'slug' ),
            'include_children' => (bool) ( $definition['include_children'] ?? true ),
            'child_of'         => max( 0, (int) ( $definition['child_of'] ?? 0 ) ),
        ];
    }

    public function keywords(): array {
        return [ 'terms' ];
    }

    public function keyword_options( array $filter, string $keyword, array $scope ): array {
        if ( 'terms' !== $keyword || ! function_exists( 'get_terms' ) ) {
            return [];
        }

        $hierarchical = function_exists( 'is_taxonomy_hierarchical' ) && is_taxonomy_hierarchical( $filter['taxonomy'] );
        $args = [
            'taxonomy'   => $filter['taxonomy'],
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC',
            'number'     => self::MAX_TERMS,
        ];
        if ( $hierarchical && (int) ( $filter['child_of'] ?? 0 ) > 0 ) {
            $args['child_of'] = (int) $filter['child_of'];
        }
        $terms = get_terms( $args );
        if ( ! is_array( $terms ) ) {
            return [];
        }

        $value = static fn( $term ): string => 'term_id' === $filter['term_field'] ? (string) $term->term_id : (string) $term->slug;
        if ( ! $hierarchical ) {
            $options = [];
            foreach ( $terms as $term ) {
                $options[ $value( $term ) ] = (string) $term->name;
            }

            return $options;
        }

        // Parents first, each followed by its children (already sorted by name), with their depth.
        $ids      = [];
        $children = [];
        foreach ( $terms as $term ) {
            $ids[ (int) $term->term_id ] = true;
        }
        foreach ( $terms as $term ) {
            $parent = (int) ( $term->parent ?? 0 );
            $children[ isset( $ids[ $parent ] ) ? $parent : 0 ][] = $term;
        }
        $options = [];
        $depths  = [];
        $walk = static function ( int $parent, int $depth ) use ( &$walk, &$options, &$depths, $children, $value ): void {
            foreach ( $children[ $parent ] ?? [] as $term ) {
                $key = $value( $term );
                $options[ $key ] = (string) $term->name;
                $depths[ $key ]  = $depth;
                $walk( (int) $term->term_id, $depth + 1 );
            }
        };
        $walk( 0, 0 );
        self::$depths[ self::depth_key( $filter ) ] = $depths;

        return $options;
    }

    public function depths( array $filter, array $scope ): array {
        return self::$depths[ self::depth_key( $filter ) ] ?? [];
    }

    public function counts( $database, array $filter, string $ids_sql, array $context ): ?array {
        $alias = (string) $context['alias'];
        $value = 'term_id' === $filter['term_field'] ? $alias . '_tt.term_id' : $alias . '_t.slug';
        $from  = ' FROM ' . $database->term_relationships . ' ' . $alias . '_tr'
            . ' INNER JOIN ' . $database->term_taxonomy . ' ' . $alias . '_tt ON ' . $alias . '_tt.term_taxonomy_id = ' . $alias . '_tr.term_taxonomy_id'
            . ' INNER JOIN ' . $database->terms . ' ' . $alias . '_t ON ' . $alias . '_t.term_id = ' . $alias . '_tt.term_id'
            . ' WHERE ' . $database->prepare( $alias . '_tt.taxonomy = %s', $filter['taxonomy'] )
            . ' AND ' . QuerySql::in_ids( $alias . '_tr.object_id', $ids_sql );

        $nested = $filter['include_children'] && function_exists( 'is_taxonomy_hierarchical' ) && is_taxonomy_hierarchical( $filter['taxonomy'] ) && function_exists( 'get_ancestors' );
        if ( ! $nested ) {
            $counts = [];
            foreach ( (array) $database->get_results( 'SELECT ' . $value . ' AS option_value, COUNT(DISTINCT ' . $alias . '_tr.object_id) AS total' . $from . ' GROUP BY ' . $alias . '_tt.term_id', ARRAY_A ) as $row ) {
                $counts[ (string) $row['option_value'] ] = (int) $row['total'];
            }

            return $counts;
        }

        // A parent counts every result filed under it or any child term, each result once.
        $rows = (array) $database->get_results( 'SELECT ' . $alias . '_tt.term_id AS term_id, ' . $value . ' AS option_value, ' . $alias . '_tr.object_id AS object_id' . $from . ' LIMIT ' . QuerySql::MAX_TALLY_ROWS, ARRAY_A );
        $keys    = [];
        $members = [];
        foreach ( $rows as $row ) {
            $keys[ (int) $row['term_id'] ] = (string) $row['option_value'];
        }
        foreach ( $rows as $row ) {
            $term_id = (int) $row['term_id'];
            foreach ( array_merge( [ $term_id ], array_map( 'intval', (array) get_ancestors( $term_id, $filter['taxonomy'], 'taxonomy' ) ) ) as $owner ) {
                $members[ $owner ][ (int) $row['object_id'] ] = true;
            }
        }

        $counts = [];
        foreach ( $members as $owner => $objects ) {
            $key = $keys[ $owner ] ?? self::term_value( $filter, $owner );
            if ( '' !== $key ) {
                $counts[ $key ] = count( $objects );
            }
        }

        return $counts;
    }

    public function where( $database, array $filter, $value, array $context ): string {
        if ( ! is_string( $value ) || '' === $value ) {
            return '';
        }

        $alias = (string) $context['alias'];
        $match = 'term_id' === $filter['term_field']
            ? $database->prepare( "{$alias}_tt.term_id = %d", (int) $value )
            : $database->prepare( "{$alias}_t.slug = %s", $value );

        $family = $this->family( $filter, $value );
        if ( null !== $family ) {
            $match = [] === $family ? '1=0' : "{$alias}_tt.term_id IN (" . implode( ',', $family ) . ')';
        }

        return 'EXISTS (SELECT 1 FROM ' . $database->term_relationships . ' ' . $alias . '_tr'
            . ' INNER JOIN ' . $database->term_taxonomy . ' ' . $alias . '_tt ON ' . $alias . '_tt.term_taxonomy_id = ' . $alias . '_tr.term_taxonomy_id'
            . ' INNER JOIN ' . $database->terms . ' ' . $alias . '_t ON ' . $alias . '_t.term_id = ' . $alias . '_tt.term_id'
            . ' WHERE ' . $alias . '_tr.object_id = ' . $context['table'] . '.ID'
            . ' AND ' . $database->prepare( "{$alias}_tt.taxonomy = %s", $filter['taxonomy'] )
            . ' AND ' . $match . ')';
    }

    /**
     * The selected term plus its descendants in a hierarchical taxonomy; null
     * when the exact-term match applies. Term lookups use WordPress's caches.
     *
     * @return int[]|null
     */
    private function family( array $filter, string $value ): ?array {
        if ( ! $filter['include_children'] || ! function_exists( 'is_taxonomy_hierarchical' ) || ! is_taxonomy_hierarchical( $filter['taxonomy'] ) ) {
            return null;
        }

        $term = get_term_by( 'term_id' === $filter['term_field'] ? 'id' : 'slug', $value, $filter['taxonomy'] );
        if ( ! is_object( $term ) ) {
            return [];
        }
        $children = get_term_children( (int) $term->term_id, $filter['taxonomy'] );

        return array_values( array_unique( array_merge( [ (int) $term->term_id ], is_array( $children ) ? array_map( 'intval', $children ) : [] ) ) );
    }

    private static function depth_key( array $filter ): string {
        return $filter['taxonomy'] . '|' . $filter['term_field'] . '|' . (int) ( $filter['child_of'] ?? 0 );
    }

    private static function term_value( array $filter, int $term_id ): string {
        if ( 'term_id' === $filter['term_field'] ) {
            return (string) $term_id;
        }
        $term = function_exists( 'get_term' ) ? get_term( $term_id, $filter['taxonomy'] ) : null;

        return is_object( $term ) && isset( $term->slug ) ? (string) $term->slug : '';
    }
}
