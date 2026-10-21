<?php

namespace Hexa\PluginCore\ListingSearch;

use Hexa\PluginCore\PublicComponents\ProfileValues;
use Hexa\PluginCore\QueryFilter\QueryFilterSet;

/**
 * Normalizes one listing search profile: the search box and filters that
 * narrow a page's existing list of posts (a category, tag, author, search or
 * post type page, or any Elementor loop marked as the listing).
 *
 * A profile declares only filters (QueryFilter definitions, posts source),
 * how text matches (`search`: SearchQueryConfiguration settings or a
 * callable returning them) and labels. Which posts are listed comes from the
 * page itself (ListingContext); `post_types` applies only on a page that is
 * not an archive. Visitors' choices travel in the page address
 * (`hls`, `hq`, `hf[key]`), so every state is shareable and works without
 * JavaScript.
 */
final class ListingSearchProfile {
    public const DEFAULT_TARGET = '.hexa-listing-results';

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     * @throws \InvalidArgumentException When the profile has no id.
     */
    public static function normalize( string $id, array $config ): array {
        $id = ProfileValues::key( $id );
        if ( '' === $id ) {
            throw new \InvalidArgumentException( 'A listing search profile needs a non-empty id.' );
        }

        $search = $config['search'] ?? [];

        return [
            'id'          => $id,
            'source'      => 'posts',
            'post_types'  => ProfileValues::keys( (array) ( $config['post_types'] ?? [ 'post' ] ) ) ?: [ 'post' ],
            'filters'     => QueryFilterSet::normalize( (array) ( $config['filters'] ?? [] ), 'posts' ),
            'search'      => is_callable( $search ) || is_array( $search ) ? $search : [],
            'search_args' => array_filter( (array) ( $config['search_args'] ?? [] ), 'is_scalar' ),
            'min_chars'   => ProfileValues::bounded_int( $config['min_chars'] ?? 2, 2, 1, 10 ),
            'target'      => self::selector( (string) ( $config['target'] ?? self::DEFAULT_TARGET ) ),
            'class'       => ProfileValues::classes( (string) ( $config['class'] ?? '' ) ),
            'labels'      => ProfileValues::labels( [
                'search'              => 'Search',
                'placeholder'         => 'Search…',
                'placeholder_context' => 'Search %s',
                'clear'               => 'Clear',
                'results_one'         => '%d result',
                'results_many'        => '%d results',
                'empty'               => 'Nothing matches. Try fewer words or clear a filter.',
                'error'               => 'Search is unavailable right now. Please try again.',
            ], (array) ( $config['labels'] ?? [] ) ),
        ];
    }

    /** A CSS selector limited to the characters selectors of classes, ids and attributes need. */
    public static function selector( string $value ): string {
        $value = trim( (string) preg_replace( '/[^A-Za-z0-9_\-.#,\[\]=" :>]/', '', $value ) );

        return '' !== $value ? substr( $value, 0, 200 ) : self::DEFAULT_TARGET;
    }
}
