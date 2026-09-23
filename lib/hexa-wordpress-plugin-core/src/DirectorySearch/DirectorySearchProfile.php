<?php

namespace Hexa\PluginCore\DirectorySearch;

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

    public const FILTER_TYPES = [ 'meta', 'taxonomy', 'callback' ];
    public const FILTER_CONTROLS = [ 'select', 'toggle' ];
    public const META_COMPARES = [ '=', 'serialized' ];
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
    public const MAX_FILTERS = 8;
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
        $id = self::key( $id );
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

        $post_types = 'posts' === $source ? self::keys( (array) ( $config['post_types'] ?? [ 'post' ] ) ) : [];
        $roles      = 'users' === $source ? self::keys( (array) ( $config['roles'] ?? [] ) ) : [];
        if ( 'posts' === $source && [] === $post_types ) {
            throw new \InvalidArgumentException( "Directory search profile '{$id}' needs at least one post type." );
        }
        if ( 'users' === $source && [] === $roles ) {
            throw new \InvalidArgumentException( "Directory search profile '{$id}' must restrict users to at least one role." );
        }

        $field_map      = 'users' === $source ? self::USER_FIELDS : self::POST_FIELDS;
        $default_fields = 'users' === $source ? [ 'display_name' ] : [ 'title', 'excerpt', 'content' ];
        $fields         = array_values( array_intersect( self::keys( (array) ( $config['fields'] ?? $default_fields ) ), array_keys( $field_map ) ) );
        $meta_keys      = array_slice( self::meta_keys( (array) ( $config['meta_keys'] ?? [] ) ), 0, self::MAX_META_KEYS );
        $taxonomies     = 'posts' === $source ? self::keys( (array) ( $config['taxonomies'] ?? [] ) ) : [];
        if ( [] === $fields && [] === $meta_keys && [] === $taxonomies ) {
            $fields = $default_fields;
        }

        $sorts = self::sorts( (array) ( $config['sorts'] ?? [] ), $source );
        $default_sort = self::key( (string) ( $config['default_sort'] ?? '' ) );
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
            'term_logic'    => self::choice( $config['term_logic'] ?? 'all', SearchQueryConfiguration::TERM_LOGICS, 'all' ),
            'word_matching' => self::choice( $config['word_matching'] ?? 'prefix', SearchQueryConfiguration::WORD_MATCHING, 'prefix' ),
            'wildcards'     => (bool) ( $config['wildcards'] ?? true ),
            'min_chars'     => max( 1, min( 10, (int) ( $config['min_chars'] ?? 2 ) ) ),
            'per_page'      => max( 1, min( self::MAX_PER_PAGE, (int) ( $config['per_page'] ?? 12 ) ) ),
            'filters'       => self::filters( (array) ( $config['filters'] ?? [] ), $source ),
            'sorts'         => $sorts,
            'default_sort'  => $default_sort,
            'render_item'   => $config['render_item'],
            'prepare'       => isset( $config['prepare'] ) && is_callable( $config['prepare'] ) ? $config['prepare'] : null,
            'labels'        => self::labels( (array) ( $config['labels'] ?? [] ) ),
            'public'        => (bool) ( $config['public'] ?? true ),
            'cache_ttl'     => max( 0, min( self::MAX_CACHE_TTL, (int) ( $config['cache_ttl'] ?? 0 ) ) ),
            'cache_version' => substr( self::key( (string) ( $config['cache_version'] ?? '1' ) ), 0, 32 ),
            'class'         => self::classes( (string) ( $config['class'] ?? '' ) ),
        ];
    }

    /**
     * @param array<int|string,mixed> $filters
     * @return array<string,array<string,mixed>>
     */
    private static function filters( array $filters, string $source ): array {
        $normalized = [];
        foreach ( $filters as $key => $filter ) {
            if ( ! is_array( $filter ) || count( $normalized ) >= self::MAX_FILTERS ) {
                continue;
            }
            $key = self::key( (string) ( $filter['key'] ?? ( is_string( $key ) ? $key : '' ) ) );
            $type = self::choice( $filter['type'] ?? 'meta', self::FILTER_TYPES, '' );
            if ( '' === $key || '' === $type ) {
                continue;
            }
            if ( 'taxonomy' === $type && 'posts' !== $source ) {
                continue;
            }
            if ( 'callback' === $type && ( ! isset( $filter['apply'] ) || ! is_callable( $filter['apply'] ) ) ) {
                continue;
            }

            $meta_key = self::meta_keys( [ $filter['meta_key'] ?? '' ] )[0] ?? '';
            $taxonomy = self::key( (string) ( $filter['taxonomy'] ?? '' ) );
            if ( ( 'meta' === $type && '' === $meta_key ) || ( 'taxonomy' === $type && '' === $taxonomy ) ) {
                continue;
            }

            $normalized[ $key ] = [
                'key'       => $key,
                'type'      => $type,
                'control'   => self::choice( $filter['control'] ?? 'select', self::FILTER_CONTROLS, 'select' ),
                'label'     => (string) ( $filter['label'] ?? ucfirst( str_replace( '_', ' ', $key ) ) ),
                'all_label' => (string) ( $filter['all_label'] ?? 'All' ),
                'meta_key'  => $meta_key,
                'compare'   => self::choice( $filter['compare'] ?? '=', self::META_COMPARES, '=' ),
                'taxonomy'  => $taxonomy,
                'term_field' => self::choice( $filter['term_field'] ?? 'slug', [ 'slug', 'term_id' ], 'slug' ),
                'options'   => $filter['options'] ?? [],
                'apply'     => $filter['apply'] ?? null,
            ];
        }

        return $normalized;
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
            $key = self::key( (string) ( $sort['key'] ?? ( is_string( $key ) ? $key : '' ) ) );
            $type = self::choice( $sort['type'] ?? 'field', self::SORT_TYPES, '' );
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
                'label'    => (string) ( $sort['label'] ?? ucfirst( str_replace( '_', ' ', $key ) ) ),
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
        $defaults = [
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
        ];

        foreach ( $defaults as $key => $default ) {
            if ( isset( $labels[ $key ] ) && is_string( $labels[ $key ] ) && '' !== trim( $labels[ $key ] ) ) {
                $defaults[ $key ] = $labels[ $key ];
            }
        }

        return $defaults;
    }

    /** @param mixed $value @param string[] $allowed */
    private static function choice( $value, array $allowed, string $fallback ): string {
        $value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';

        return in_array( $value, $allowed, true ) ? $value : $fallback;
    }

    /** @return string[] */
    private static function keys( array $values ): array {
        $keys = [];
        foreach ( $values as $value ) {
            $key = is_scalar( $value ) ? self::key( (string) $value ) : '';
            if ( '' !== $key && ! in_array( $key, $keys, true ) ) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /** Meta keys keep their case and allow the characters WordPress stores. @return string[] */
    private static function meta_keys( array $values ): array {
        $keys = [];
        foreach ( $values as $value ) {
            $key = is_scalar( $value ) ? (string) preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $value ) : '';
            if ( '' !== $key && strlen( $key ) <= 191 && ! in_array( $key, $keys, true ) ) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public static function key( string $value ): string {
        return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( trim( $value ) ) );
    }

    private static function classes( string $value ): string {
        $classes = array_filter( array_map( static fn( string $class ): string => (string) preg_replace( '/[^A-Za-z0-9_\-]/', '', $class ), preg_split( '/\s+/', $value ) ?: [] ) );

        return implode( ' ', $classes );
    }
}
