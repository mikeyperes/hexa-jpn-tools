<?php

declare(strict_types=1);

$GLOBALS['core_test_plugins'] = [
    'sample/sample.php' => [ 'Name' => 'Sample', 'Version' => '1.0.0' ],
];
$GLOBALS['core_test_site_active'] = [];
$GLOBALS['core_test_network_active'] = [];
$GLOBALS['core_test_caps'] = [
    'deactivate_plugins'    => true,
    'manage_network_plugins'=> false,
];
$GLOBALS['core_test_deactivation_calls'] = [];

function sanitize_key( mixed $value ): string {
    return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ) ?: '';
}

function get_plugins(): array {
    return $GLOBALS['core_test_plugins'];
}

function is_plugin_active( string $plugin ): bool {
    return in_array( $plugin, $GLOBALS['core_test_site_active'], true );
}

function is_plugin_active_for_network( string $plugin ): bool {
    return in_array( $plugin, $GLOBALS['core_test_network_active'], true );
}

function get_site_option( string $name, mixed $default = false ): mixed {
    unset( $name );
    return $default;
}

function get_plugin_updates(): array {
    return [];
}

function current_user_can( string $capability ): bool {
    return ! empty( $GLOBALS['core_test_caps'][ $capability ] );
}

function deactivate_plugins( string $plugin, bool $silent = false, bool $network_wide = false ): void {
    unset( $silent );
    $GLOBALS['core_test_deactivation_calls'][] = [
        'plugin'       => $plugin,
        'network_wide' => $network_wide,
    ];
    $key = $network_wide ? 'core_test_network_active' : 'core_test_site_active';
    $GLOBALS[ $key ] = array_values( array_diff( $GLOBALS[ $key ], [ $plugin ] ) );
}

function is_wp_error( mixed $value ): bool {
    return $value instanceof WP_Error;
}

final class WP_Error {
    public function __construct( private string $code, private string $message ) {}
    public function get_error_code(): string { return $this->code; }
    public function get_error_message(): string { return $this->message; }
}

$root = dirname( __DIR__ );
require_once $root . '/src/PluginChecks/PluginCheckDefinition.php';
require_once $root . '/src/PluginChecks/PluginCheckService.php';

use Hexa\PluginCore\PluginChecks\PluginCheckDefinition;
use Hexa\PluginCore\PluginChecks\PluginCheckService;

function deactivation_expect( bool $condition, string $message ): void {
    if ( $condition ) {
        return;
    }
    fwrite( STDERR, 'FAIL: ' . $message . "\n" );
    exit( 1 );
}

$definition = new PluginCheckDefinition(
    [
        'id'                 => 'sample',
        'name'               => 'Sample',
        'plugin_file'        => 'sample/sample.php',
        'source'             => 'manual',
        'should_not_contain' => true,
    ]
);

$GLOBALS['core_test_site_active'] = [ 'sample/sample.php' ];
$site_result = PluginCheckService::deactivate( $definition );
deactivation_expect( ! is_wp_error( $site_result ), 'A site-active plugin must deactivate.' );
deactivation_expect( false === $GLOBALS['core_test_deactivation_calls'][0]['network_wide'], 'Site deactivation must preserve site scope.' );

$GLOBALS['core_test_network_active'] = [ 'sample/sample.php' ];
$denied = PluginCheckService::deactivate( $definition );
deactivation_expect( is_wp_error( $denied ) && 'hexa_plugin_check_network_deactivate_forbidden' === $denied->get_error_code(), 'Network deactivation requires the network capability.' );

$GLOBALS['core_test_caps']['manage_network_plugins'] = true;
$network_result = PluginCheckService::deactivate( $definition );
deactivation_expect( ! is_wp_error( $network_result ), 'An authorized network-active plugin must deactivate.' );
deactivation_expect( true === $GLOBALS['core_test_deactivation_calls'][1]['network_wide'], 'Network deactivation must use network scope.' );
deactivation_expect( [] === $GLOBALS['core_test_network_active'], 'The network-active state must be cleared.' );

echo "PASS: Generic plugin deactivation preserves activation scope and enforces network authority.\n";
