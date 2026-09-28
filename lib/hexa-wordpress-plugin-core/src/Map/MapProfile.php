<?php

namespace Hexa\PluginCore\Map;

use Hexa\PluginCore\PublicComponents\ProfileValues;

/**
 * Normalizes one host-declared map profile.
 *
 * A profile says which items appear (published posts of selected types, or
 * users with selected roles), where each item's street address is stored,
 * how items are grouped for the visitor filter, and what an item's card says.
 * Core owns geocoding and storing coordinates, the map, clustering, the
 * filter, the card markup, caching, and the interaction.
 */
final class MapProfile {
    public const SOURCES = [ 'posts', 'users' ];
    public const GEOCODERS = [ 'census', 'nominatim' ];
    public const GEO_META = 'hexa_map_geo';
    public const MAX_ITEMS = 2000;
    public const MAX_CACHE_TTL = 86400;

    public const DEFAULT_STYLE = 'https://tiles.openfreemap.org/styles/dark';
    public const LIBRARY_VERSION = '5.24.0';

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     * @throws \InvalidArgumentException When the profile cannot be served safely.
     */
    public static function normalize( string $id, array $config ): array {
        $id = ProfileValues::key( $id );
        if ( '' === $id ) {
            throw new \InvalidArgumentException( 'A map profile needs a non-empty id.' );
        }

        $source = ProfileValues::choice( $config['source'] ?? 'posts', self::SOURCES, '' );
        if ( '' === $source ) {
            throw new \InvalidArgumentException( "Map profile '{$id}' has an unsupported source." );
        }
        $post_types = 'posts' === $source ? ProfileValues::keys( (array) ( $config['post_types'] ?? [ 'post' ] ) ) : [];
        $roles      = 'users' === $source ? ProfileValues::keys( (array) ( $config['roles'] ?? [] ) ) : [];
        if ( [] === $post_types && [] === $roles ) {
            throw new \InvalidArgumentException( "Map profile '{$id}' needs post types or user roles." );
        }

        $address = self::field( $config['address'] ?? null );
        if ( null === $address ) {
            throw new \InvalidArgumentException( "Map profile '{$id}' needs an address meta key or callback." );
        }

        $geocoders = $config['geocoders'] ?? [ 'nominatim' ];
        $geocoders = is_callable( $geocoders ) && ! is_string( $geocoders ) ? $geocoders : array_values( array_intersect( ProfileValues::keys( (array) $geocoders ), self::GEOCODERS ) );
        if ( [] === $geocoders ) {
            $geocoders = [ 'nominatim' ];
        }

        $view   = (array) ( $config['view'] ?? [] );
        $center = self::coordinates( $view['center'] ?? null );
        $library = (array) ( $config['library'] ?? [] );
        $cdn     = 'https://unpkg.com/maplibre-gl@' . self::LIBRARY_VERSION . '/dist/maplibre-gl.';

        return [
            'id'            => $id,
            'source'        => $source,
            'post_types'    => $post_types,
            'roles'         => $roles,
            'address'       => $address,
            'geo_meta'      => ProfileValues::meta_key( $config['geo_meta'] ?? self::GEO_META ) ?: self::GEO_META,
            'geocoders'     => $geocoders,
            'country'       => substr( ProfileValues::key( (string) ( $config['country'] ?? '' ) ), 0, 2 ),
            'batch'         => ProfileValues::bounded_int( $config['batch'] ?? 25, 25, 1, 200 ),
            'group'         => self::group( $config['group'] ?? null ),
            'chips'         => ProfileValues::bounded_int( $config['chips'] ?? 6, 6, 0, 20 ),
            'prepare'       => ProfileValues::callback( $config['prepare'] ?? null ),
            'title'         => ProfileValues::callback( $config['title'] ?? null ),
            'link'          => ProfileValues::callback( $config['link'] ?? null ),
            'card'          => ProfileValues::callback( $config['card'] ?? null ),
            'render_item'   => ProfileValues::callback( $config['render_item'] ?? null ),
            'highlight'     => ProfileValues::callback( $config['highlight'] ?? null ),
            'view'          => [
                'center'  => $center,
                'zoom'    => self::number( $view['zoom'] ?? 9, 0, 22, 9 ),
                'fitZoom' => self::number( $view['fit_zoom'] ?? 13, 1, 22, 13 ),
                'maxZoom' => self::number( $view['max_zoom'] ?? 17, 1, 22, 17 ),
            ],
            'style'         => self::https( $config['style'] ?? '' ) ?: self::DEFAULT_STYLE,
            'library'       => [
                'js'  => self::https( $library['js'] ?? '' ) ?: $cdn . 'js',
                'css' => self::https( $library['css'] ?? '' ) ?: $cdn . 'css',
            ],
            'cluster'       => (bool) ( $config['cluster'] ?? true ),
            'max_items'     => ProfileValues::bounded_int( $config['max_items'] ?? 500, 500, 1, self::MAX_ITEMS ),
            'heading_level' => ProfileValues::bounded_int( $config['heading_level'] ?? 3, 3, 2, 6 ),
            'labels'        => self::labels( (array) ( $config['labels'] ?? [] ) ),
            'public'        => (bool) ( $config['public'] ?? true ),
            'cache_ttl'     => ProfileValues::bounded_int( $config['cache_ttl'] ?? 3600, 3600, 0, self::MAX_CACHE_TTL ),
            'cache_version' => substr( ProfileValues::key( (string) ( $config['cache_version'] ?? '1' ) ), 0, 32 ),
            'class'         => ProfileValues::classes( (string) ( $config['class'] ?? '' ) ),
        ];
    }

    /**
     * A per-item value: a meta key string, ['meta' => key], or fn( int $id ): string.
     *
     * @param mixed $value
     * @return string|callable|null
     */
    private static function field( $value ) {
        if ( is_callable( $value ) && ! is_string( $value ) ) {
            return $value;
        }
        $key = ProfileValues::meta_key( is_array( $value ) ? ( $value['meta'] ?? '' ) : $value );

        return '' !== $key ? $key : null;
    }

    /**
     * Visitor filter group: fn( int $id, array $data ): string, ['taxonomy' => t] (post terms),
     * or ['meta' => key, 'taxonomy' => t] (term IDs stored in a field, as ACF taxonomy fields do).
     *
     * @param mixed $value
     * @return callable|array{meta_key:string,taxonomy:string}|null
     */
    private static function group( $value ) {
        if ( is_callable( $value ) && ! is_string( $value ) ) {
            return $value;
        }
        if ( ! is_array( $value ) ) {
            return null;
        }
        $group = [ 'meta_key' => ProfileValues::meta_key( $value['meta'] ?? '' ), 'taxonomy' => ProfileValues::key( (string) ( $value['taxonomy'] ?? '' ) ) ];

        return '' !== $group['meta_key'] || '' !== $group['taxonomy'] ? $group : null;
    }

    /** @param mixed $value @return float[]|null [lng, lat] as MapLibre expects, from [lat, lng]. */
    private static function coordinates( $value ): ?array {
        if ( ! is_array( $value ) || 2 !== count( $value ) ) {
            return null;
        }
        [ $lat, $lng ] = array_values( $value );
        if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) || abs( (float) $lat ) > 90 || abs( (float) $lng ) > 180 ) {
            return null;
        }

        return [ (float) $lng, (float) $lat ];
    }

    /** @param mixed $value */
    private static function number( $value, float $min, float $max, float $default ): float {
        return is_numeric( $value ) ? max( $min, min( $max, (float) $value ) ) : $default;
    }

    /** @param mixed $value */
    private static function https( $value ): string {
        $value = is_string( $value ) ? trim( $value ) : '';

        return (bool) preg_match( '#^https://[^\s"\'<>]+$#', $value ) ? $value : '';
    }

    /** @return array<string,string> */
    private static function labels( array $labels ): array {
        return ProfileValues::labels( [
            'region'     => 'Map',
            'loading'    => 'Loading map…',
            'all'        => 'All',
            'filter'     => 'Filter the map',
            'more'       => 'More…',
            'count_one'  => '%d location',
            'count_many' => '%d locations',
            'list'       => 'List of every location on the map',
            'cta'        => 'View details',
        ], $labels );
    }
}
