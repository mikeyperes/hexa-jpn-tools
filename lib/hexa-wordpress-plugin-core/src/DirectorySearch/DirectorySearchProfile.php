<?php

namespace Hexa\PluginCore\DirectorySearch;

use Hexa\PluginCore\PublicComponents\ProfileValues;
use Hexa\PluginCore\QueryFilter\MetaFilterType;
use Hexa\PluginCore\QueryFilter\QueryFilterSet;
use Hexa\PluginCore\SearchQuery\SearchQueryConfiguration;

/**
 * Normalizes one host-declared directory search profile.
 *
 * A profile describes what is searched (published posts of selected post
 * types, or users holding selected roles), how words match, which filters and
 * sorts visitors may use, and how one result card renders. Hosts own the
 * profile arrays, card markup, and any business data; Core owns the accepted
 * shape, limits, SQL, endpoint, and interaction.
 */
final class DirectorySearchProfile {
    public const SOURCES = [ 'posts', 'users' ];

    /** Public field key => trusted posts column. */
    public const POST_FIELDS = [
        'title'   => 'post_title',
        'content' => 'post_content',
        'excerpt' => 'post_excerpt',
        'slug'    => 'post_name',
    ];

    /**
     * Public field key => trusted users column. Login and email are
     * deliberately absent: a public directory must never enable account
     * enumeration. Search visitor-facing profile meta through `meta_keys`.
     */
    public const USER_FIELDS = [
        'display_name' => 'display_name',
        'nicename'     => 'user_nicename',
        'url'          => 'user_url',
    ];

    /** @deprecated 3.2.0 Filters are QueryFilter definitions; see QueryFilterTypes::names(). */
    public const FILTER_TYPES = [ 'meta', 'taxonomy', 'date_range', 'callback' ];
    /** @deprecated 3.2.0 Use QueryFilterSet::CONTROLS. */
    public const FILTER_CONTROLS = QueryFilterSet::CONTROLS;
    /** @deprecated 3.2.0 Use MetaFilterType::COMPARES. */
    public const META_COMPARES = MetaFilterType::COMPARES;
    /** @deprecated 3.2.0 Use QueryFilterSet::MAX_FILTERS. */
    public const MAX_FILTERS = QueryFilterSet::MAX_FILTERS;
    public const SORT_TYPES = [ 'field', 'callback' ];

    /** Sortable field key => trusted column, per source. */
    public const POST_SORT_FIELDS = [
        'title'    => 'post_title',
        'date'     => 'post_date',
        'modified' => 'post_modified',
    ];
    public const USER_SORT_FIELDS = [
        'name'       => 'display_name',
        'registered' => 'user_registered',
    ];

    public const MAX_META_KEYS = 20;
    public const MAX_SORTS = 8;
    public const MAX_PER_PAGE = 50;
    public const MAX_CALLBACK_CANDIDATES = 1000;
    public const MAX_CACHE_TTL = 3600;

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     * @throws \InvalidArgumentException When the profile cannot be served safely.
     */
    public static function normalize( string $id, array $config ): array {
        $id = ProfileValues::key( $id );
        if ( '' === $id ) {
            throw new \InvalidArgumentException( 'A directory search profile needs a non-empty id.' );
        }

        $source = is_string( $config['source'] ?? null ) ? strtolower( trim( $config['source'] ) ) : 'posts';
        if ( ! in_array( $source, self::SOURCES, true ) ) {
            throw new \InvalidArgumentException( "Directory search profile '{$id}' has an unsupported source." );
        }
        if ( ! isset( $config['render_item'] ) || ! is_callable( $config['render_item'] ) ) {
            throw new \InvalidArgumentException( "Directory search profile '{$id}' needs a callable render_item." );
        }

        $post_types = 'posts' === $source ? ProfileValues::keys( (array) ( $config['post_types'] ?? [ 'post' ] ) ) : [];
        $roles      = 'users' === $source ? ProfileValues::keys( (array) ( $config['roles'] ?? [] ) ) : [];
        if ( 'posts' === $source && [] === $post_types ) {
            throw new \InvalidArgumentException( "Directory search profile '{$id}' needs at least one post type." );
        }
        if ( 'users' === $source && [] === $roles ) {
            throw new \InvalidArgumentException( "Directory search profile '{$id}' must restrict users to at least one role." );
        }

        $field_map      = 'users' === $source ? self::USER_FIELDS : self::POST_FIELDS;
        $default_fields = 'users' === $source ? [ 'display_name' ] : [ 'title', 'excerpt', 'content' ];
        $fields         = array_values( array_intersect( ProfileValues::keys( (array) ( $config['fields'] ?? $default_fields ) ), array_keys( $field_map ) ) );
        $meta_keys      = array_slice( ProfileValues::meta_keys( (array) ( $config['meta_keys'] ?? [] ) ), 0, self::MAX_META_KEYS );
        $taxonomies     = 'posts' === $source ? ProfileValues::keys( (array) ( $config['taxonomies'] ?? [] ) ) : [];
        if ( [] === $fields && [] === $meta_keys && [] === $taxonomies ) {
            $fields = $default_fields;
        }

        $sorts = self::sorts( (array) ( $config['sorts'] ?? [] ), $source );
        $default_sort = ProfileValues::key( (string) ( $config['default_sort'] ?? '' ) );
        if ( ! isset( $sorts[ $default_sort ] ) ) {
            $default_sort = (string) array_key_first( $sorts );
        }

        return [
            'id'            => $id,
            'source'        => $source,
            'post_types'    => $post_types,
            'roles'         => $roles,
            'fields'        => $fields,
            'meta_keys'     => $meta_keys,
            'taxonomies'    => $taxonomies,
            'term_logic'    => ProfileValues::choice( $config['term_logic'] ?? 'all', SearchQueryConfiguration::TERM_LOGICS, 'all' ),
            'word_matching' => ProfileValues::choice( $config['word_matching'] ?? 'prefix', SearchQueryConfiguration::WORD_MATCHING, 'prefix' ),
            'wildcards'     => (bool) ( $config['wildcards'] ?? true ),
            'min_chars'     => max( 1, min( 10, (int) ( $config['min_chars'] ?? 2 ) ) ),
            'per_page'      => max( 1, min( self::MAX_PER_PAGE, (int) ( $config['per_page'] ?? 12 ) ) ),
            'filters'       => QueryFilterSet::normalize( (array) ( $config['filters'] ?? [] ), $source ),
            'sorts'         => $sorts,
            'default_sort'  => $default_sort,
            'render_item'   => $config['render_item'],
            'prepare'       => isset( $config['prepare'] ) && is_callable( $config['prepare'] ) ? $config['prepare'] : null,
            'labels'        => self::labels( (array) ( $config['labels'] ?? [] ) ),
            'public'        => (bool) ( $config['public'] ?? true ),
            'cache_ttl'     => max( 0, min( self::MAX_CACHE_TTL, (int) ( $config['cache_ttl'] ?? 0 ) ) ),
            'cache_version' => substr( ProfileValues::key( (string) ( $config['cache_version'] ?? '1' ) ), 0, 32 ),
            'class'         => ProfileValues::classes( (string) ( $config['class'] ?? '' ) ),
        ];
    }

    /**
     * @param array<int|string,mixed> $sorts
     * @return array<string,array<string,mixed>>
     */
    private static function sorts( array $sorts, string $source ): array {
        $fields = 'users' === $source ? self::USER_SORT_FIELDS : self::POST_SORT_FIELDS;
        $normalized = [];

        foreach ( $sorts as $key => $sort ) {
            if ( ! is_array( $sort ) || count( $normalized ) >= self::MAX_SORTS ) {
                continue;
            }
            $key = ProfileValues::key( (string) ( $sort['key'] ?? ( is_string( $key ) ? $key : '' ) ) );
            $type = ProfileValues::choice( $sort['type'] ?? 'field', self::SORT_TYPES, '' );
            if ( '' === $key || '' === $type ) {
                continue;
            }
            if ( 'field' === $type && ! isset( $fields[ (string) ( $sort['field'] ?? '' ) ] ) ) {
                continue;
            }
            if ( 'callback' === $type && ( ! isset( $sort['callback'] ) || ! is_callable( $sort['callback'] ) ) ) {
                continue;
            }

            $normalized[ $key ] = [
                'key'      => $key,
                'type'     => $type,
                'label'    => (string) ( $sort['label'] ?? ProfileValues::label( $key ) ),
                'field'    => 'field' === $type ? (string) $sort['field'] : '',
                'order'    => 'DESC' === strtoupper( (string) ( $sort['order'] ?? 'ASC' ) ) ? 'DESC' : 'ASC',
                'callback' => 'callback' === $type ? $sort['callback'] : null,
            ];
        }

        if ( [] === $normalized ) {
            $default_field = 'users' === $source ? 'name' : 'title';
            $normalized[ $default_field ] = [
                'key'      => $default_field,
                'type'     => 'field',
                'label'    => 'users' === $source ? 'Name' : 'Title',
                'field'    => $default_field,
                'order'    => 'ASC',
                'callback' => null,
            ];
        }

        return $normalized;
    }

    /** @return array<string,string> */
    private static function labels( array $labels ): array {
        return ProfileValues::labels( [
            'search'       => 'Search',
            'placeholder'  => 'Search…',
            'submit'       => 'Search',
            'sort'         => 'Sort by',
            'empty'        => 'No results match your search.',
            'results_one'  => '%d result',
            'results_many' => '%d results',
            'results_for'  => ' for “%s”',
            'min_chars'    => 'Type at least %d characters.',
            'error'        => 'Search is unavailable right now. Please try again.',
            'previous'     => 'Previous',
            'next'         => 'Next',
            'pagination'   => 'Results pages',
        ], $labels );
    }

    public static function key( string $value ): string {
        return ProfileValues::key( $value );
    }
}
