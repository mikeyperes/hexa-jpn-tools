<?php

namespace Hexa\PluginCore\Search;

use Hexa\PluginCore\PublicComponents\ProfileValues;
use Hexa\PluginCore\QueryFilter\QueryFilterSet;
use Hexa\PluginCore\SearchQuery\SearchQueryConfiguration;

/**
 * Normalizes one search profile: what a search box searches, how words
 * match, which filters and sorts visitors get, and where results show.
 *
 * Results show in one of two places:
 * - `list` (default): the page's own list narrows in place. That is the
 *   category, tag, author, search or post type page's posts, or any
 *   Elementor loop marked with the CSS class `hexa-listing-results` (a
 *   homepage section, a landing page).
 * - `cards`: the box renders its own result cards with `render_item`
 *   (a people or host directory, a list with no loop of its own).
 *
 * Text matching uses the site's search settings (`search`: settings or a
 * callable returning them) or, when given, explicit `fields`, `meta_keys`
 * and `taxonomies`. Users (`source` = users, restricted to `roles`) always
 * match explicit fields; login and email are never searchable.
 */
final class SearchProfile {
    public const SOURCES = [ 'posts', 'users' ];
    public const RESULTS = [ 'list', 'cards' ];
    public const LOAD_MODES = [ 'infinite', 'load_more', 'numbered' ];
    public const DEFAULT_TARGET = '.hexa-listing-results';

    /** Public field key => trusted posts column. */
    public const POST_FIELDS = [ 'title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt', 'slug' => 'post_name' ];
    /** Public field key => trusted users column. Login and email are deliberately absent. */
    public const USER_FIELDS = [ 'display_name' => 'display_name', 'nicename' => 'user_nicename', 'url' => 'user_url' ];
    public const POST_SORT_FIELDS = [ 'title' => 'post_title', 'date' => 'post_date', 'modified' => 'post_modified' ];
    public const USER_SORT_FIELDS = [ 'name' => 'display_name', 'registered' => 'user_registered' ];

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
            throw new \InvalidArgumentException( 'A search profile needs a non-empty id.' );
        }

        $source = ProfileValues::choice( $config['source'] ?? 'posts', self::SOURCES, '' );
        if ( '' === $source ) {
            throw new \InvalidArgumentException( "Search profile '{$id}' has an unsupported source." );
        }
        $render  = isset( $config['render_item'] ) && is_callable( $config['render_item'] ) ? $config['render_item'] : null;
        $results = null !== $render ? 'cards' : 'list';
        if ( 'users' === $source && 'cards' !== $results ) {
            throw new \InvalidArgumentException( "Search profile '{$id}' searches users and needs a callable render_item." );
        }

        $post_types = 'posts' === $source ? ( ProfileValues::keys( (array) ( $config['post_types'] ?? [ 'post' ] ) ) ?: [ 'post' ] ) : [];
        $roles      = 'users' === $source ? ProfileValues::keys( (array) ( $config['roles'] ?? [] ) ) : [];
        if ( 'users' === $source && [] === $roles ) {
            throw new \InvalidArgumentException( "Search profile '{$id}' must restrict users to at least one role." );
        }

        $field_map  = 'users' === $source ? self::USER_FIELDS : self::POST_FIELDS;
        $fields     = array_values( array_intersect( ProfileValues::keys( (array) ( $config['fields'] ?? [] ) ), array_keys( $field_map ) ) );
        $meta_keys  = array_slice( ProfileValues::meta_keys( (array) ( $config['meta_keys'] ?? [] ) ), 0, self::MAX_META_KEYS );
        $taxonomies = 'posts' === $source ? ProfileValues::keys( (array) ( $config['taxonomies'] ?? [] ) ) : [];
        $settings   = $config['search'] ?? null;
        $settings   = is_callable( $settings ) || is_array( $settings ) ? $settings : null;
        if ( null === $settings && [] === $fields && [] === $meta_keys && [] === $taxonomies ) {
            $fields = 'users' === $source ? [ 'display_name' ] : [ 'title', 'excerpt', 'content' ];
        }

        $sorts        = self::sorts( (array) ( $config['sorts'] ?? [] ), $source );
        $default_sort = ProfileValues::key( (string) ( $config['default_sort'] ?? '' ) );
        if ( ! isset( $sorts[ $default_sort ] ) ) {
            $default_sort = (string) array_key_first( $sorts );
        }

        return [
            'id'            => $id,
            'source'        => $source,
            'results'       => $results,
            'post_types'    => $post_types,
            'roles'         => $roles,
            'search'        => $settings,
            'fields'        => $fields,
            'meta_keys'     => $meta_keys,
            'taxonomies'    => $taxonomies,
            'term_logic'    => ProfileValues::choice( $config['term_logic'] ?? 'all', SearchQueryConfiguration::TERM_LOGICS, 'all' ),
            'word_matching' => ProfileValues::choice( $config['word_matching'] ?? 'prefix', SearchQueryConfiguration::WORD_MATCHING, 'prefix' ),
            'wildcards'     => (bool) ( $config['wildcards'] ?? true ),
            'search_args'   => array_filter( (array) ( $config['search_args'] ?? [] ), 'is_scalar' ),
            'min_chars'     => ProfileValues::bounded_int( $config['min_chars'] ?? 2, 2, 1, 10 ),
            'per_page'      => ProfileValues::bounded_int( $config['per_page'] ?? 12, 12, 1, self::MAX_PER_PAGE ),
            'load_mode'     => ProfileValues::choice( $config['load_mode'] ?? 'infinite', self::LOAD_MODES, 'infinite' ),
            'filters'       => QueryFilterSet::normalize( (array) ( $config['filters'] ?? [] ), $source ),
            // On a category, tag, author or post type page, posts results stay inside that page's list.
            'context'       => 'posts' === $source && (bool) ( $config['context'] ?? true ),
            'sorts'         => $sorts,
            'default_sort'  => $default_sort,
            'render_item'   => $render,
            'prepare'       => isset( $config['prepare'] ) && is_callable( $config['prepare'] ) ? $config['prepare'] : null,
            'target'        => self::selector( (string) ( $config['target'] ?? self::DEFAULT_TARGET ) ),
            'class'         => ProfileValues::classes( (string) ( $config['class'] ?? '' ) ),
            'public'        => (bool) ( $config['public'] ?? true ),
            'cache_ttl'     => max( 0, min( self::MAX_CACHE_TTL, (int) ( $config['cache_ttl'] ?? 0 ) ) ),
            'cache_version' => substr( ProfileValues::key( (string) ( $config['cache_version'] ?? '1' ) ), 0, 32 ),
            'labels'        => ProfileValues::labels( [
                'search'              => 'Search',
                'placeholder'         => 'Search…',
                'placeholder_context' => 'Search %s',
                'clear'               => 'Clear',
                'sort'                => 'Sort',
                'results_one'         => '%d result',
                'results_many'        => '%d results',
                'empty'               => 'Nothing matches. Try fewer words or clear a filter.',
                'error'               => 'Search is unavailable right now. Please try again.',
                'load_more'           => 'Load more',
                'results_for'         => ' for “%s”',
                'min_chars'           => 'Type at least %d characters.',
                'pagination'          => 'Results pages',
                'previous'            => 'Previous',
                'next'                => 'Next',
            ], (array) ( $config['labels'] ?? [] ) ),
        ];
    }

    /** A CSS selector limited to the characters selectors of classes, ids and attributes need. */
    public static function selector( string $value ): string {
        $value = trim( (string) preg_replace( '/[^A-Za-z0-9_\-.#,\[\]=" :>]/', '', $value ) );

        return '' !== $value ? substr( $value, 0, 200 ) : self::DEFAULT_TARGET;
    }

    /**
     * Sorts visitors may choose; a `field` sort orders by a whitelisted column,
     * a `callback` sort reorders up to MAX_CALLBACK_CANDIDATES matching IDs.
     *
     * @param array<int|string,mixed> $sorts
     * @return array<string,array<string,mixed>>
     */
    private static function sorts( array $sorts, string $source ): array {
        $fields     = 'users' === $source ? self::USER_SORT_FIELDS : self::POST_SORT_FIELDS;
        $normalized = [];
        foreach ( $sorts as $key => $sort ) {
            if ( ! is_array( $sort ) || count( $normalized ) >= self::MAX_SORTS ) {
                continue;
            }
            $key  = ProfileValues::key( (string) ( $sort['key'] ?? ( is_string( $key ) ? $key : '' ) ) );
            $type = ProfileValues::choice( $sort['type'] ?? 'field', [ 'field', 'callback' ], '' );
            if ( '' === $key || '' === $type
                || ( 'field' === $type && ! isset( $fields[ (string) ( $sort['field'] ?? '' ) ] ) )
                || ( 'callback' === $type && ( ! isset( $sort['callback'] ) || ! is_callable( $sort['callback'] ) ) ) ) {
                continue;
            }
            $normalized[ $key ] = [
                'key'      => $key,
                'type'     => $type,
                'field'    => 'field' === $type ? (string) $sort['field'] : '',
                'order'    => 'DESC' === strtoupper( (string) ( $sort['order'] ?? 'ASC' ) ) ? 'DESC' : 'ASC',
                'callback' => 'callback' === $type ? $sort['callback'] : null,
                'label'    => trim( (string) ( $sort['label'] ?? '' ) ) ?: ProfileValues::label( $key ),
            ];
        }

        if ( [] === $normalized ) {
            $normalized = 'users' === $source
                ? [ 'name' => [ 'key' => 'name', 'type' => 'field', 'field' => 'name', 'order' => 'ASC', 'callback' => null, 'label' => 'Name' ] ]
                : [ 'newest' => [ 'key' => 'newest', 'type' => 'field', 'field' => 'date', 'order' => 'DESC', 'callback' => null, 'label' => 'Newest' ] ];
        }

        return $normalized;
    }
}
