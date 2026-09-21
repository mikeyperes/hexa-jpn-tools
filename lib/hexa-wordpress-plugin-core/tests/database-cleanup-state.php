<?php

declare(strict_types=1);

$runtime_root = __DIR__ . '/.database-cleanup-runtime';
@mkdir( $runtime_root . '/wp-optimize', 0777, true );
file_put_contents( $runtime_root . '/wp-optimize/wp-optimize.php', '<?php' );

define( 'WP_PLUGIN_DIR', $runtime_root );
define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['hexa_test_options'] = [];
$GLOBALS['hexa_test_plugin_active'] = true;
$GLOBALS['hexa_test_deactivations'] = 0;

class WP_Error {
}

function is_wp_error( mixed $value ): bool {
    return $value instanceof WP_Error;
}

function trailingslashit( string $value ): string {
    return rtrim( $value, '/\\' ) . '/';
}

function get_option( string $key, mixed $default = false ): mixed {
    return $GLOBALS['hexa_test_options'][ $key ] ?? $default;
}

function delete_option( string $key ): void {
    unset( $GLOBALS['hexa_test_options'][ $key ] );
}

function is_plugin_active( string $plugin_file ): bool {
    return $GLOBALS['hexa_test_plugin_active'];
}

function deactivate_plugins( string $plugin_file, bool $silent = false, bool $network_wide = false ): void {
    $GLOBALS['hexa_test_plugin_active'] = false;
    $GLOBALS['hexa_test_deactivations']++;
}

function current_time( string $format ): string {
    return '12:00:00';
}

require dirname( __DIR__ ) . '/src/DatabaseCleanup/DatabaseCleanupService.php';

use Hexa\PluginCore\DatabaseCleanup\DatabaseCleanupService;

$service = new DatabaseCleanupService();

$GLOBALS['hexa_test_options']['hpc_database_cleanup_active-before'] = [
    'session_id'       => 'active-before',
    'initially_active' => true,
];
$service->finish_session( 'active-before' );

if ( 0 !== $GLOBALS['hexa_test_deactivations'] || ! $GLOBALS['hexa_test_plugin_active'] ) {
    fwrite( STDERR, "FAIL: A provider active before cleanup must remain active.\n" );
    exit( 1 );
}

$GLOBALS['hexa_test_options']['hpc_database_cleanup_inactive-before'] = [
    'session_id'       => 'inactive-before',
    'initially_active' => false,
];
$service->finish_session( 'inactive-before' );

if ( 1 !== $GLOBALS['hexa_test_deactivations'] || $GLOBALS['hexa_test_plugin_active'] ) {
    fwrite( STDERR, "FAIL: A provider activated for cleanup must return to inactive.\n" );
    exit( 1 );
}

unlink( $runtime_root . '/wp-optimize/wp-optimize.php' );
rmdir( $runtime_root . '/wp-optimize' );
rmdir( $runtime_root );

echo "PASS: Database cleanup restores the provider's pre-run activation state.\n";
