<?php

declare(strict_types=1);

// Staff plugins lose their hooks for clients; WordPress and client-facing plugins keep theirs.

$plugins = sys_get_temp_dir() . '/hpc-client-facing-' . getmypid();
define( 'WP_PLUGIN_DIR', $plugins );
@mkdir( $plugins . '/seo-by-rank-math', 0777, true );
@mkdir( $plugins . '/hexa-pr-wire-core', 0777, true );
file_put_contents( $plugins . '/seo-by-rank-math/notice.php', '<?php function hpc_test_rank_math_notice() {} return static function () {};' );
file_put_contents( $plugins . '/hexa-pr-wire-core/notice.php', '<?php function hpc_test_hexa_notice() {}' );
file_put_contents( $plugins . '/single-file.php', '<?php function hpc_test_single_file() {}' );

function wp_normalize_path( string $path ): string { return str_replace( '\\', '/', $path ); }
function apply_filters( string $hook, mixed $value ): mixed { return $value; }
final class WP_Hook { public array $callbacks = []; }
function remove_action( string $hook, mixed $callback, int $priority ): void {
    global $wp_filter;
    foreach ( $wp_filter[ $hook ]->callbacks[ $priority ] as $id => $entry ) {
        if ( $entry['function'] === $callback ) {
            unset( $wp_filter[ $hook ]->callbacks[ $priority ][ $id ] );
        }
    }
}
function hpc_test_core_notice() {}

$rank_closure = require $plugins . '/seo-by-rank-math/notice.php';
require $plugins . '/hexa-pr-wire-core/notice.php';
require $plugins . '/single-file.php';
require dirname( __DIR__ ) . '/src/WpAdminUiCleanup/ClientFacingPlugins.php';

use Hexa\PluginCore\WpAdminUiCleanup\ClientFacingPlugins;

$expect = static function ( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$expect( 'seo-by-rank-math' === ClientFacingPlugins::plugin_folder( 'hpc_test_rank_math_notice' ), 'A plugin function resolves to its folder.' );
$expect( 'seo-by-rank-math' === ClientFacingPlugins::plugin_folder( $rank_closure ), 'A plugin closure resolves to its folder.' );
$expect( 'single-file' === ClientFacingPlugins::plugin_folder( 'hpc_test_single_file' ), 'A single-file plugin resolves to its file name.' );
$expect( null === ClientFacingPlugins::plugin_folder( 'hpc_test_core_notice' ), 'Code outside the plugin folder is left alone.' );
$expect( ClientFacingPlugins::is_allowed( 'hexa-pr-wire-core' ) && ClientFacingPlugins::is_allowed( 'wp-user-avatars' ), 'Prefix and exact entries are client-facing.' );
$expect( ! ClientFacingPlugins::is_allowed( 'seo-by-rank-math' ) && ! ClientFacingPlugins::is_allowed( 'elementor' ), 'Staff plugins are not client-facing.' );

global $wp_filter;
$wp_filter['admin_notices'] = new WP_Hook();
$wp_filter['admin_notices']->callbacks = [ 10 => [
    'a' => [ 'function' => 'hpc_test_rank_math_notice' ],
    'b' => [ 'function' => $rank_closure ],
    'c' => [ 'function' => 'hpc_test_hexa_notice' ],
    'd' => [ 'function' => 'hpc_test_core_notice' ],
] ];
$expect( 2 === ClientFacingPlugins::strip( 'admin_notices' ), 'Both staff-plugin callbacks are removed.' );
$left = array_keys( $wp_filter['admin_notices']->callbacks[10] );
$expect( [ 'c', 'd' ] === $left, 'Our plugin and WordPress callbacks stay.' );
$expect( 0 === ClientFacingPlugins::strip( 'missing_hook' ), 'An unused hook is a no-op.' );

array_map( 'unlink', glob( $plugins . '/*/*.php' ) ?: [] );
@unlink( $plugins . '/single-file.php' );
@rmdir( $plugins . '/seo-by-rank-math' );
@rmdir( $plugins . '/hexa-pr-wire-core' );
@rmdir( $plugins );

echo "PASS: client-facing plugin hooks (9 assertions).\n";
