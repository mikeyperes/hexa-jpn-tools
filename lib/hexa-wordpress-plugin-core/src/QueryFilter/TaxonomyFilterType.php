<?php

namespace Hexa\PluginCore\QueryFilter;

use Hexa\PluginCore\PublicComponents\ProfileValues;

/**
 * Filters posts by one taxonomy term, matched by slug (default) or term id.
 * In a hierarchical taxonomy a term also matches its descendants, like
 * WordPress's own `include_children` default; set `include_children` false to
 * match only the exact term. Keyword option 'terms' lists the taxonomy's
 * non-empty terms by name.
 */
final class TaxonomyFilterType extends QueryFilterType {
    public const TERM_FIELDS = [ 'slug', 'term_id' ];
    public const MAX_TERMS = 200;

    public function sources(): array {
        return [ 'posts' ];
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
        ];
    }

    public function keywords(): array {
        return [ 'terms' ];
    }

    public function keyword_options( array $filter, string $keyword, array $scope ): array {
        if ( 'terms' !== $keyword || ! function_exists( 'get_terms' ) ) {
            return [];
        }

        $terms = get_terms( [
            'taxonomy'   => $filter['taxonomy'],
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC',
            'number'     => self::MAX_TERMS,
        ] );
        if ( ! is_array( $terms ) ) {
            return [];
        }

        $options = [];
        foreach ( $terms as $term ) {
            $options[ 'term_id' === $filter['term_field'] ? (string) $term->term_id : (string) $term->slug ] = (string) $term->name;
        }

        return $options;
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
}
