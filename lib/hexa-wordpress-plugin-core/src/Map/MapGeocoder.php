<?php

namespace Hexa\PluginCore\Map;

/**
 * Turns a free-form street address into coordinates with free, keyless
 * services, in the order a profile lists them:
 *
 * - `census`: the US Census Bureau geocoder (US street addresses, no rate limit).
 * - `nominatim`: OpenStreetMap Nominatim (worldwide; at most one request per
 *   second under its usage policy, which the caller's batch pacing respects).
 *
 * Each service is tried with the address as written and again without a
 * suite or unit number. Callers store results, so a service sees each
 * address once.
 */
final class MapGeocoder {
    public const PROVIDER_PAUSE_MICROSECONDS = 1100000;

    /** @var callable(string):?string Fetches a URL and returns the body, or null on failure. */
    private $fetch;

    private bool $nominatim_used = false;

    public function __construct( ?callable $fetch = null ) {
        $this->fetch = $fetch ?? [ self::class, 'http_get' ];
    }

    /** Collapses markup, entities, repeated country/state suffixes, and whitespace. */
    public static function normalize( string $address ): string {
        $address = html_entity_decode( strip_tags( $address ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $address = str_replace( "\u{00A0}", ' ', $address );
        $address = (string) preg_replace( '/,?\s*United States(?:\s+of\s+America)?(?:,\s*[A-Za-z ]+)?\s*$/i', '', $address );
        $address = (string) preg_replace( '/,?\s*USA\s*$/i', '', $address );

        return trim( (string) preg_replace( '/\s+/u', ' ', $address ), " \t\n\r\0\x0B," );
    }

    /** The address without a suite, unit, apartment, or # number. */
    public static function without_unit( string $address ): string {
        return trim( (string) preg_replace( '/\s*,?\s*(?:suite|suit|ste|unit|apt|#)\s*\.?\s*#?[\w-]+/i', '', $address ) );
    }

    /**
     * @param string[]|callable $providers Provider keys, or fn( string $address ): ?array{lat:float,lng:float}.
     * @return array{lat:float,lng:float,src:string}|null
     */
    public function geocode( string $address, $providers, string $country = '' ): ?array {
        $address = self::normalize( $address );
        if ( '' === $address ) {
            return null;
        }
        if ( is_callable( $providers ) ) {
            $point = call_user_func( $providers, $address );

            return self::valid( $point ) ? [ 'lat' => (float) $point['lat'], 'lng' => (float) $point['lng'], 'src' => 'custom' ] : null;
        }

        $queries = array_values( array_unique( [ $address, self::without_unit( $address ) ] ) );
        foreach ( (array) $providers as $provider ) {
            foreach ( $queries as $query ) {
                $point = 'census' === $provider ? $this->census( $query ) : ( 'nominatim' === $provider ? $this->nominatim( $query, $country ) : null );
                if ( null !== $point ) {
                    return $point + [ 'src' => (string) $provider ];
                }
            }
        }

        return null;
    }

    /** @return array{lat:float,lng:float}|null */
    private function census( string $address ): ?array {
        $data  = $this->json( 'https://geocoding.geo.census.gov/geocoder/locations/onelineaddress?' . http_build_query( [ 'address' => $address, 'benchmark' => 'Public_AR_Current', 'format' => 'json' ] ) );
        $match = $data['result']['addressMatches'][0]['coordinates'] ?? null;

        return is_array( $match ) && self::valid( [ 'lat' => $match['y'] ?? null, 'lng' => $match['x'] ?? null ] ) ? [ 'lat' => (float) $match['y'], 'lng' => (float) $match['x'] ] : null;
    }

    /** @return array{lat:float,lng:float}|null */
    private function nominatim( string $address, string $country ): ?array {
        if ( $this->nominatim_used ) {
            usleep( self::PROVIDER_PAUSE_MICROSECONDS );
        }
        $this->nominatim_used = true;
        $args = [ 'q' => $address, 'format' => 'jsonv2', 'limit' => 1 ] + ( '' !== $country ? [ 'countrycodes' => $country ] : [] );
        $data = $this->json( 'https://nominatim.openstreetmap.org/search?' . http_build_query( $args ) );
        $hit  = $data[0] ?? null;

        return is_array( $hit ) && self::valid( [ 'lat' => $hit['lat'] ?? null, 'lng' => $hit['lon'] ?? null ] ) ? [ 'lat' => (float) $hit['lat'], 'lng' => (float) $hit['lon'] ] : null;
    }

    /** @return array<mixed> */
    private function json( string $url ): array {
        $body = call_user_func( $this->fetch, $url );
        $data = is_string( $body ) ? json_decode( $body, true ) : null;

        return is_array( $data ) ? $data : [];
    }

    /** @param mixed $point */
    private static function valid( $point ): bool {
        return is_array( $point ) && is_numeric( $point['lat'] ?? null ) && is_numeric( $point['lng'] ?? null )
            && abs( (float) $point['lat'] ) <= 90 && abs( (float) $point['lng'] ) <= 180
            && ( 0.0 !== (float) $point['lat'] || 0.0 !== (float) $point['lng'] );
    }

    /** WordPress HTTP GET with an identifying User-Agent, as the services' usage policies require. */
    public static function http_get( string $url ): ?string {
        if ( ! function_exists( 'wp_remote_get' ) ) {
            return null;
        }
        $site     = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
        $response = wp_remote_get( $url, [ 'timeout' => 12, 'user-agent' => 'HexaPluginCore-Map/1.0 (+' . $site . ')' ] );
        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return null;
        }

        return (string) wp_remote_retrieve_body( $response );
    }
}
