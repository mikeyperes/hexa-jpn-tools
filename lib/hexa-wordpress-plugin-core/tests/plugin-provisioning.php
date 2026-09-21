<?php

declare( strict_types=1 );

define( 'WP_PLUGIN_DIR', '/tmp/hexa-plugin-provisioning-tests' );

$GLOBALS['pp_plugins'] = [
    'network-only/network.php' => [ 'Name' => 'Network Only', 'Version' => '1.2.3' ],
    'site-only/site.php'       => [ 'Name' => 'Site Only', 'Version' => '2.0.0' ],
];

function get_plugins(): array { return $GLOBALS['pp_plugins']; }
function is_plugin_active( string $plugin_file ): bool { return 'site-only/site.php' === $plugin_file; }
function is_plugin_active_for_network( string $plugin_file ): bool { return 'network-only/network.php' === $plugin_file; }
function get_option( string $key, mixed $default = null ): mixed { return 'active_plugins' === $key ? [ 'site-only/site.php' ] : $default; }
function get_site_option( string $key, mixed $default = null ): mixed { return 'auto_update_plugins' === $key ? [ 'network-only/network.php' ] : $default; }
function wp_cache_delete( string $key, string $group = '' ): bool { return true; }
function wp_normalize_path( string $path ): string { return str_replace( '\\', '/', $path ); }
function sanitize_key( mixed $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ) ?: ''; }

$root = dirname( __DIR__ );
require $root . '/src/PluginProvisioning/PluginProvisioner.php';

use Hexa\PluginCore\PluginProvisioning\PluginProvisioner;

$folder_status = PluginProvisioner::plugin_status_by_folder( 'network-only' );
$file_status   = PluginProvisioner::plugin_status_by_file( 'network-only/network.php' );
$site_status   = PluginProvisioner::plugin_status_by_file( 'site-only/site.php' );

if ( ! $folder_status['installed'] || ! $folder_status['active'] || ! $folder_status['network_active'] || ! $file_status['active'] || ! $file_status['network_active'] || ! $file_status['auto_update'] || ! $site_status['active'] || $site_status['network_active'] ) {
    fwrite( STDERR, "FAIL: PluginProvisioner did not recognize site-active and network-active plugins.\n" );
    exit( 1 );
}

echo "PASS: PluginProvisioner recognizes network-only activation as active.\n";
