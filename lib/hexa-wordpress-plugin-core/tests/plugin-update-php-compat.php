<?php

declare(strict_types=1);

// The updater offers old-PHP sites the release's PHP 7.4 build, or states the
// source's real "Requires PHP" so WordPress refuses the update.

require __DIR__ . '/support/fields.php';

$GLOBALS['t_transients'] = [];
$GLOBALS['t_http']       = [];
function get_site_transient( $k ) { return $GLOBALS['t_transients'][ $k ] ?? false; }
function set_site_transient( $k, $v, $e = 0 ) { $GLOBALS['t_transients'][ $k ] = $v; return true; }
function get_bloginfo( $s = '' ) { return '7.1'; }
function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function add_query_arg( ...$a ) { return (string) end( $a ); }
function is_wp_error( $v ) { return false; }
function wp_remote_get( $url, $args = [] ) { foreach ( $GLOBALS['t_http'] as $prefix => $resp ) { if ( str_starts_with( $url, $prefix ) ) { return $resp; } } return [ 'code' => 404, 'body' => '' ]; }
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) { define( 'MINUTE_IN_SECONDS', 60 ); }

spl_autoload_register( static function ( string $c ): void {
    $p = 'Hexa\\PluginCore\\';
    if ( str_starts_with( $c, $p ) ) { $f = dirname( __DIR__ ) . '/src/' . str_replace( '\\', '/', substr( $c, strlen( $p ) ) ) . '.php'; if ( is_file( $f ) ) { require_once $f; } }
} );

use Hexa\PluginCore\PluginUpdates\GitHubVersionClient;

$failures = 0;
$check = static function ( bool $ok, string $label ) use ( &$failures ): void { echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . PHP_EOL; $failures += $ok ? 0 : 1; };

$check( '8.2' === GitHubVersionClient::extract_requires_php( "<?php\n/**\n * Plugin Name: X\n * Requires PHP: 8.2\n */" ), 'Requires PHP header is read' );
$check( '' === GitHubVersionClient::extract_requires_php( "<?php\n/** Plugin Name: X */" ), 'missing header reads as none' );

$repo = 'mikeyperes/hexa-pr-wire-distributor';
$release = [ 'assets' => [
    [ 'name' => 'hexa-pr-wire-distributor-3.5.4.zip', 'browser_download_url' => "https://github.com/$repo/releases/download/v3.5.4/hexa-pr-wire-distributor-3.5.4.zip" ],
    [ 'name' => 'hexa-pr-wire-distributor-3.5.4-php74.zip', 'browser_download_url' => "https://github.com/$repo/releases/download/v3.5.4/hexa-pr-wire-distributor-3.5.4-php74.zip" ],
] ];
$check( str_ends_with( GitHubVersionClient::compat_asset_url( $repo, $release ), '-3.5.4-php74.zip' ), 'the PHP 7.4 build asset is chosen' );
$check( '' === GitHubVersionClient::compat_asset_url( $repo, [ 'assets' => [ $release['assets'][0] ] ] ), 'no PHP 7.4 asset means none' );
$check( '' === GitHubVersionClient::compat_asset_url( $repo, [ 'assets' => [ [ 'name' => 'x-php74.zip', 'browser_download_url' => 'https://evil.example/x-php74.zip' ] ] ] ), 'foreign asset host is refused' );

// package_for() through the real updater, with a stubbed GitHub.
$config = new Hexa\PluginCore\PluginUpdates\UpdaterConfig( [
    'plugin_basename'     => 'hexa-pr-wire-distributor/hexa-pr-wire-distributor.php',
    'proper_folder_name'  => 'hexa-pr-wire-distributor',
    'plugin_starter_file' => 'hexa-pr-wire-distributor.php',
    'github_repo'         => 'mikeyperes/hexa-pr-wire-distributor',
    'version'             => '3.5.3',
    'plugin_name'         => 'Hexa PR Wire Distributor',
] );
$package_for = static function ( string $requires, ?array $release_body = null ) use ( $config ): array {
    $GLOBALS['t_transients'] = [];
    $GLOBALS['t_http'] = [ 'https://raw.githubusercontent.com/' => [ 'code' => 200, 'body' => "/**\n * Version: 3.5.4\n * Requires PHP: $requires\n */" ] ];
    if ( null !== $release_body ) { $GLOBALS['t_http']['https://api.github.com/repos/mikeyperes/hexa-pr-wire-distributor/releases/tags/v3.5.4'] = [ 'code' => 200, 'body' => json_encode( $release_body ) ]; }
    $client  = ( new ReflectionClass( GitHubVersionClient::class ) )->newInstanceWithoutConstructor();
    ( new ReflectionProperty( GitHubVersionClient::class, 'config' ) )->setValue( $client, $config );
    $updater = ( new ReflectionClass( Hexa\PluginCore\PluginUpdates\GitHubPluginUpdater::class ) )->newInstanceWithoutConstructor();
    ( new ReflectionProperty( $updater, 'config' ) )->setValue( $updater, $config );
    ( new ReflectionProperty( $updater, 'client' ) )->setValue( $updater, $client );
    $m = new ReflectionMethod( $updater, 'package_for' ); $m->setAccessible( true );
    return $m->invoke( $updater, '3.5.4' );
};
$modern = $package_for( '7.0' );
$check( str_ends_with( $modern['package'], 'refs/heads/main.zip' ) && '7.0' === $modern['requires_php'], 'a site on newer PHP keeps the branch source' );
$old = $package_for( '99.0', $release );
$check( str_ends_with( $old['package'], '-php74.zip' ) && '7.4' === $old['requires_php'], 'an older-PHP site gets the PHP 7.4 build' );
$none = $package_for( '99.0', [ 'assets' => [ $release['assets'][0] ] ] );
$check( str_ends_with( $none['package'], 'main.zip' ) && '99.0' === $none['requires_php'], 'without a PHP 7.4 build the real requirement is declared' );

exit( $failures > 0 ? 1 : 0 );
