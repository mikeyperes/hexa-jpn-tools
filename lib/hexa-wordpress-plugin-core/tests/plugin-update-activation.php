<?php

declare( strict_types=1 );

define( 'ABSPATH', sys_get_temp_dir() . '/hexa-core-updater-wordpress/' );
define( 'WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins' );

final class WP_Error {
    public function __construct( private string $code, private string $message ) {}
    public function get_error_code(): string { return $this->code; }
    public function get_error_message(): string { return $this->message; }
}

function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
function trailingslashit( string $value ): string { return rtrim( $value, '/\\' ) . '/'; }
function untrailingslashit( string $value ): string { return rtrim( $value, '/\\' ); }

$GLOBALS['updater_site_active'] = [];
$GLOBALS['updater_network_active'] = [];
$GLOBALS['updater_cache_cleared'] = false;
$GLOBALS['updater_activation_calls'] = [];
$GLOBALS['updater_activation_failure'] = false;

function get_option( string $key, mixed $default = false ): mixed {
    return 'active_plugins' === $key ? $GLOBALS['updater_site_active'] : $default;
}

function is_plugin_active_for_network( string $plugin ): bool {
    return in_array( $plugin, $GLOBALS['updater_network_active'], true );
}

function wp_clean_plugins_cache( bool $clear_update_cache = true ): void {
    $GLOBALS['updater_cache_cleared'] = true;
}

function activate_plugin( string $plugin, string $redirect = '', bool $network_wide = false, bool $silent = false ): null|WP_Error {
    $GLOBALS['updater_activation_calls'][] = compact( 'plugin', 'network_wide', 'silent' );
    if ( ! $GLOBALS['updater_cache_cleared'] ) {
        return new WP_Error( 'invalid_plugin', 'Plugin discovery is stale.' );
    }
    if ( $GLOBALS['updater_activation_failure'] ) {
        return new WP_Error( 'activation_failed', 'Synthetic activation failure.' );
    }
    if ( $network_wide ) {
        $GLOBALS['updater_network_active'][] = $plugin;
        $GLOBALS['updater_network_active'] = array_values( array_unique( $GLOBALS['updater_network_active'] ) );
    } else {
        $GLOBALS['updater_site_active'][] = $plugin;
        $GLOBALS['updater_site_active'] = array_values( array_unique( $GLOBALS['updater_site_active'] ) );
    }
    return null;
}

final class UpdaterFilesystemDouble {
    public function exists( string $path ): bool { return false; }
    public function delete( string $path, bool $recursive = false ): bool { return true; }
    public function move( string $source, string $destination, bool $overwrite = false ): bool { return true; }
    public function is_dir( string $path ): bool { return false; }
}

$passed = 0;
$failed = 0;
function updater_activation_expect( bool $condition, string $message ): void {
    global $passed, $failed;
    if ( $condition ) {
        ++$passed;
        echo "PASS {$message}\n";
        return;
    }
    ++$failed;
    echo "FAIL {$message}\n";
}

$root = dirname( __DIR__ );
require $root . '/src/CoreContracts/ModuleInterface.php';
require $root . '/src/PluginUpdates/UpdaterConfig.php';
require $root . '/src/PluginUpdates/GitHubVersionClient.php';
require $root . '/src/PluginUpdates/UpdaterFilesystem.php';
require $root . '/src/PluginUpdates/GitHubPluginUpdater.php';

use Hexa\PluginCore\PluginUpdates\GitHubPluginUpdater;
use Hexa\PluginCore\PluginUpdates\UpdaterConfig;

global $wp_filesystem;
$wp_filesystem = new UpdaterFilesystemDouble();

$runtime = 'hws-base-tools-main/hws-base-tools.php';
$canonical = 'hws-base-tools/hws-base-tools.php';
$config = new UpdaterConfig( [
    'plugin_basename' => $runtime,
    'canonical_plugin_basename' => $canonical,
    'plugin_slug' => 'hws-base-tools',
    'proper_folder_name' => 'hws-base-tools',
    'runtime_folder_name' => 'hws-base-tools-main',
    'plugin_starter_file' => 'hws-base-tools.php',
    'github_repo' => 'mikeyperes/hws-base-tools',
    'version' => '13.2.7',
    'plugin_name' => 'HWS Base Tools',
] );
$hook = [ 'plugin' => $runtime, 'type' => 'plugin', 'action' => 'update' ];
$result = [ 'destination' => WP_PLUGIN_DIR . '/hws-base-tools/' ];

$GLOBALS['updater_site_active'] = [ $runtime ];
$updater = new GitHubPluginUpdater( $config );
$updater->pre_install( true, $hook );
$GLOBALS['updater_site_active'] = [];
$restored = $updater->post_install( true, $hook, $result );
updater_activation_expect( true === $restored, 'post-install returns the WordPress success response' );
updater_activation_expect( [ $canonical ] === $GLOBALS['updater_site_active'], 'a site-active noncanonical install is restored under the canonical basename' );
updater_activation_expect( $GLOBALS['updater_cache_cleared'] && true === $GLOBALS['updater_activation_calls'][0]['silent'], 'reactivation clears plugin discovery and runs silently' );

$GLOBALS['updater_site_active'] = [];
$GLOBALS['updater_network_active'] = [];
$GLOBALS['updater_cache_cleared'] = false;
$GLOBALS['updater_activation_calls'] = [];
$inactive = new GitHubPluginUpdater( $config );
$inactive->pre_install( true, $hook );
$inactive->post_install( true, $hook, $result );
updater_activation_expect( [] === $GLOBALS['updater_activation_calls'] && [] === $GLOBALS['updater_site_active'], 'an intentionally inactive plugin remains inactive after update' );

$GLOBALS['updater_network_active'] = [ $runtime ];
$network = new GitHubPluginUpdater( $config );
$network->pre_install( true, $hook );
$GLOBALS['updater_network_active'] = [];
$network->post_install( true, $hook, $result );
updater_activation_expect( [ $canonical ] === $GLOBALS['updater_network_active'] && true === $GLOBALS['updater_activation_calls'][0]['network_wide'], 'network activation scope is preserved under the canonical basename' );

$GLOBALS['updater_network_active'] = [];
$GLOBALS['updater_site_active'] = [ $runtime ];
$GLOBALS['updater_activation_failure'] = true;
$failure = new GitHubPluginUpdater( $config );
$failure->pre_install( true, $hook );
$GLOBALS['updater_site_active'] = [];
$failed_result = $failure->post_install( true, $hook, $result );
updater_activation_expect( is_wp_error( $failed_result ) && 'hexa_plugin_core_reactivation_failed' === $failed_result->get_error_code(), 'a restoration failure fails the update instead of silently leaving the plugin inactive' );

echo "\n{$passed} passed, {$failed} failed.\n";
exit( 0 === $failed ? 0 : 1 );
