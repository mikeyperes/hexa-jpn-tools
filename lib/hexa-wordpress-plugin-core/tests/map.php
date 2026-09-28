<?php

declare(strict_types=1);

$root = dirname( __DIR__ );

// Any notice or warning is a failure: stored data and visitor pages must never produce PHP diagnostics.
set_error_handler( static function ( int $severity, string $message, string $file, int $line ): bool {
    throw new ErrorException( $message, 0, $severity, $file, $line );
} );

final class MapTestStore {
    /** @var array<int,array<string,mixed>> */
    public static array $meta = [];
    /** @var array<string,mixed> */
    public static array $options = [];
    /** @var string[] */
    public static array $actions = [];
}

final class MapTestUser {
    public function __construct( public string $display_name ) {}
}

final class MapTestTerm {
    public function __construct( public string $name ) {}
}

function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function esc_url( mixed $value ): string {
    return str_starts_with( (string) $value, 'javascript:' ) ? '' : htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function wp_json_encode( mixed $value ): string {
    return (string) json_encode( $value );
}
function wp_strip_all_tags( string $value ): string {
    return strip_tags( $value );
}
function maybe_unserialize( mixed $value ): mixed {
    return is_string( $value ) && str_starts_with( $value, 'a:' ) ? unserialize( $value ) : $value;
}
function is_wp_error( mixed $value ): bool {
    return false;
}
function current_user_can( string $capability ): bool {
    return false;
}
function do_action( string $hook, mixed ...$args ): void {
    MapTestStore::$actions[] = $hook;
}
function get_option( string $name, mixed $default = false ): mixed {
    return MapTestStore::$options[ $name ] ?? $default;
}
function update_option( string $name, mixed $value, bool $autoload = true ): bool {
    MapTestStore::$options[ $name ] = $value;
    return true;
}
function get_users( array $args ): array {
    return array_keys( MapTestStore::$meta );
}
function get_user_meta( int $id, string $key, bool $single ): mixed {
    return MapTestStore::$meta[ $id ][ $key ] ?? '';
}
function update_user_meta( int $id, string $key, mixed $value ): bool {
    MapTestStore::$meta[ $id ][ $key ] = $value;
    return true;
}
function get_userdata( int $id ): ?MapTestUser {
    return isset( MapTestStore::$meta[ $id ] ) ? new MapTestUser( (string) MapTestStore::$meta[ $id ]['name'] ) : null;
}
function get_author_posts_url( int $id ): string {
    return 'https://example.com/author/' . $id . '/';
}
function get_term( int $id, string $taxonomy ): ?MapTestTerm {
    return [ 7 => new MapTestTerm( 'Boca Raton' ), 8 => new MapTestTerm( 'Miami' ) ][ $id ] ?? null;
}

require $root . '/src/PublicComponents/ProfileValues.php';
require $root . '/src/PublicComponents/ProfileStore.php';
require $root . '/src/PublicComponents/PublicComponent.php';
require $root . '/src/Map/MapProfile.php';
require $root . '/src/Map/MapRegistry.php';
require $root . '/src/Map/MapGeocoder.php';
require $root . '/src/Map/MapLocations.php';
require $root . '/src/Map/MapRenderer.php';

use Hexa\PluginCore\Map\MapGeocoder;
use Hexa\PluginCore\Map\MapLocations;
use Hexa\PluginCore\Map\MapProfile;
use Hexa\PluginCore\Map\MapRegistry;
use Hexa\PluginCore\Map\MapRenderer;

$assertions = 0;
$expect = static function ( bool $condition, string $message ) use ( &$assertions ): void {
    $assertions++;
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};
$throws = static function ( callable $callback ): bool {
    try {
        $callback();
    } catch ( InvalidArgumentException $exception ) {
        return true;
    }
    return false;
};

// Address normalization.
$expect( '844 Prosperity Farms Road North Palm Beach, Florida 33408' === MapGeocoder::normalize( '<span style="color:#000">844 Prosperity Farms Road North Palm Beach, Florida 33408</span>' ), 'Markup is stripped from stored addresses.' );
$expect( '35 SE 9th St, Miami, FL' === MapGeocoder::normalize( "&nbsp; 35 SE 9th St, Miami, FL, United States, Florida &nbsp;" ), 'Entities, non-breaking spaces, and a repeated country/state suffix are removed.' );
$expect( '9540 Collins AveSurfside, FL 33154' === MapGeocoder::normalize( '9540 Collins AveSurfside, FL 33154, USA' ), 'A trailing USA is removed.' );
$expect( '361 South County Road Palm Beach, FL 33480' === MapGeocoder::without_unit( '361 South County Road Suite #D Palm Beach, FL 33480' ), 'Suite numbers are dropped for the retry query.' );
$expect( '501 Silks Run, Hallandale Beach, FL 33009' === MapGeocoder::without_unit( '501 Silks Run Suit 1130, Hallandale Beach, FL 33009' ), 'A misspelled suite is dropped too.' );

// Geocoder provider order and response parsing (no network).
$calls = [];
$fetch = static function ( string $url ) use ( &$calls ): ?string {
    $calls[] = (string) parse_url( $url, PHP_URL_HOST );
    if ( str_contains( $url, 'census.gov' ) ) {
        return str_contains( $url, 'Unknown' ) ? '{"result":{"addressMatches":[]}}' : '{"result":{"addressMatches":[{"coordinates":{"x":-80.1234567,"y":26.1234567}}]}}';
    }
    return str_contains( $url, 'Unknown' ) ? '[]' : '[{"lat":"25.5","lon":"-80.5"}]';
};
$geocoder = new MapGeocoder( $fetch );
$point    = $geocoder->geocode( '1 Main St, Miami, FL', [ 'census', 'nominatim' ], 'us' );
$expect( null !== $point && 'census' === $point['src'] && abs( $point['lat'] - 26.1234567 ) < 1e-9 && [ 'geocoding.geo.census.gov' ] === $calls, 'The first provider that places the address wins; later providers are not called.' );
$calls = [];
$expect( null === $geocoder->geocode( 'Unknown Place', [ 'census' ] ) && 1 === count( $calls ), 'An unplaced address returns null after one query per distinct form.' );
$expect( null === $geocoder->geocode( '   ', [ 'census' ] ), 'An empty address is never sent.' );
$expect( [ 'lat' => 1.0, 'lng' => 2.0, 'src' => 'custom' ] === $geocoder->geocode( 'x', static fn( string $a ): array => [ 'lat' => 1, 'lng' => 2 ] ), 'A host geocoder callback is supported.' );

// Profile validation.
$expect( $throws( static fn() => MapProfile::normalize( 'm', [ 'source' => 'users', 'address' => 'address' ] ) ), 'A users profile needs roles.' );
$expect( $throws( static fn() => MapProfile::normalize( 'm', [ 'source' => 'users', 'roles' => [ 'host' ] ] ) ), 'A profile needs an address source.' );
$expect( $throws( static fn() => MapProfile::normalize( 'm', [ 'source' => 'rows', 'roles' => [ 'host' ], 'address' => 'a' ] ) ), 'Unknown sources are rejected.' );
$profile = MapProfile::normalize( 'Hosts Map!', [ 'source' => 'users', 'roles' => [ 'host' ], 'address' => 'address', 'geocoders' => [ 'census', 'bogus' ], 'view' => [ 'center' => [ 26.2, -80.19 ], 'zoom' => 99 ], 'style' => 'javascript:alert(1)' ] );
$expect( 'hostsmap' === $profile['id'] && [ 'census' ] === $profile['geocoders'], 'Ids and geocoder lists are sanitized.' );
$expect( [ -80.19, 26.2 ] === $profile['view']['center'] && 22.0 === $profile['view']['zoom'], 'The view center becomes [lng, lat] and zoom is bounded.' );
$expect( MapProfile::DEFAULT_STYLE === $profile['style'] && str_starts_with( $profile['library']['js'], 'https://unpkg.com/maplibre-gl@' ), 'Only https style and library URLs are accepted.' );

// Geocoding pending items, change detection, and misses.
MapTestStore::$meta = [
    10 => [ 'name' => 'Temple &amp; Center', 'address' => '1 Main St, Boca Raton, FL', 'area' => 'a:1:{i:0;s:1:"7";}' ],
    11 => [ 'name' => 'Nowhere Shul', 'address' => 'Unknown Place', 'area' => 'a:1:{i:0;s:1:"8";}' ],
    12 => [ 'name' => 'No Address', 'address' => '' ],
];
MapRegistry::register( 'hosts', [
    'source'    => 'users',
    'roles'     => [ 'host' ],
    'address'   => 'address',
    'geocoders' => [ 'census' ],
    'group'     => [ 'meta' => 'area', 'taxonomy' => 'area' ],
    'prepare'   => static fn( array $ids ): array => array_fill_keys( $ids, [ 'upcoming' => 2 ] ),
    'highlight' => static fn( int $id, array $data ): bool => $data['upcoming'] > 0,
    'card'      => static fn( int $id, array $data ): array => [ 'list_label' => 'Upcoming', 'list' => [ [ 'label' => 'Oct 4', 'text' => '<b>Shabbat</b> [x]', 'url' => 'https://example.com/e/' ] ], 'cta' => 'View host' ],
    'cache_ttl' => 0,
    'labels'    => [ 'count_one' => '%d host', 'count_many' => '%d hosts' ],
    'class'     => 'jpn-map',
] );
$hosts = MapRegistry::get( 'hosts' );
$expect( [ 10 => '1 Main St, Boca Raton, FL', 11 => 'Unknown Place' ] === MapLocations::pending( $hosts ), 'Items with an address and no stored coordinates are pending.' );
$result = MapLocations::geocode_pending( $hosts, null, new MapGeocoder( $fetch ) );
$expect( 1 === $result['placed'] && 1 === $result['missed'] && 0 === $result['remaining'], 'A batch reports placed, missed, and remaining items.' );
$expect( [] === MapLocations::pending( $hosts ), 'Placed items and remembered misses are not retried while their address is unchanged.' );
$expect( in_array( 'litespeed_purge', MapTestStore::$actions, true ) && '0' !== MapLocations::generation(), 'Storing results starts a new cache generation and purges map pages.' );
MapTestStore::$meta[11]['address'] = '2 Ocean Dr, Miami, FL';
$expect( [ 11 => '2 Ocean Dr, Miami, FL' ] === MapLocations::pending( $hosts ), 'An edited address is detected by its hash and geocoded again.' );
MapLocations::geocode_pending( $hosts, null, new MapGeocoder( $fetch ) );

// Rendering.
$html = ( new MapRenderer() )->render( 'hosts' );
$expect( str_contains( $html, '<style id="hexa-map-css">' ) && str_contains( $html, '<script id="hexa-map-js">' ), 'Assets print inline with the first map.' );
$expect( str_contains( $html, 'class="hmap jpn-map" id="hmap-hosts"' ) && str_contains( $html, '<p class="hmap-status" role="status" aria-live="polite">2 hosts</p>' ), 'The component carries the host class and a live count.' );
$expect( str_contains( $html, 'data-hmap-group="Boca Raton"' ) === false && str_contains( $html, '<option value="Boca Raton">Boca Raton (1)</option>' ), 'Single-item groups are offered in the select, not as chips.' );
$expect( str_contains( $html, '<a href="https://example.com/author/10/">Temple &amp;amp; Center</a>' ) === false && str_contains( $html, 'Temple &amp; Center</a>' ), 'Stored entities are decoded once and escaped once.' );
$expect( ! str_contains( $html, '<b>Shabbat</b>' ) && ! str_contains( $html, '[x]' ), 'Card text is escaped and inert to shortcodes.' );
$points = json_decode( html_entity_decode( (string) preg_replace( '/^.*data-hmap-points="([^"]*)".*$/s', '$1', $html ), ENT_QUOTES ), true );
$expect( is_array( $points ) && 2 === count( $points ) && true === $points[0]['live'] && 'Miami' === $points[0]['g'] && str_contains( $points[0]['h'], 'hmap-card__cta' ), 'Points carry coordinates, group, highlight, and the rendered card (sorted by title).' );
$expect( str_contains( $html, '<details class="hmap-list">' ) && substr_count( $html, '<li><a href="https://example.com/author/' ) === 2, 'Every placed item is also a plain link in the list.' );
$expect( ! str_contains( $html, 'No Address' ), 'Items without an address stay off the map.' );
$expect( in_array( 'litespeed_tag_add', MapTestStore::$actions, true ), 'Pages with a public map are tagged for purging.' );
$expect( '' === ( new MapRenderer() )->render( 'missing' ), 'An unknown profile renders nothing.' );
$expect( strlen( (string) gzencode( MapRenderer::css() . MapRenderer::js(), 9 ) ) < 6000, 'Map assets stay small (the map library itself loads lazily from its CDN).' );

echo "PASS: map contract ({$assertions} assertions).\n";
