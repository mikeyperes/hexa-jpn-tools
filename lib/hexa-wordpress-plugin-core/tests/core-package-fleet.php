<?php

declare( strict_types=1 );

function sanitize_file_name( mixed $value ): string { return preg_replace( '/[^a-zA-Z0-9._-]/', '', (string) $value ) ?: ''; }

$root = dirname( __DIR__ );
require $root . '/bootstrap.php';
require $root . '/src/CoreRuntime/CoreVersion.php';
require $root . '/src/PluginUpdates/UpdaterConfig.php';
require $root . '/src/CorePackageUpdates/CorePackageConfig.php';
require $root . '/src/CorePackageUpdates/CorePackageInstaller.php';

$temporary = sys_get_temp_dir() . '/hpc-fleet-' . uniqid( '', true );
$roots = [ $temporary . '/one', $temporary . '/two' ];
foreach ( $roots as $index => $candidate ) {
    mkdir( $candidate . '/src', 0777, true );
    file_put_contents( $candidate . '/VERSION', '2.0.' . $index );
    file_put_contents( $candidate . '/src/Example.php', '<?php // fleet test' );
    HexaPluginCorePackageRegistry::register_candidate( 'host-' . $index, $candidate, [ 'minimum_version' => '1.0.0' ] );
}

use Hexa\PluginCore\CorePackageUpdates\CorePackageConfig;
use Hexa\PluginCore\CorePackageUpdates\CorePackageInstaller;

$installer = new CorePackageInstaller( CorePackageConfig::from_core_root( $roots[0] ) );
$hosts = $installer->registered_hosts();
$source = (string) file_get_contents( $root . '/src/CorePackageUpdates/CorePackageInstaller.php' );
$controller_source = (string) file_get_contents( $root . '/src/CorePackageUpdates/CorePackageAjaxController.php' );
if ( 2 !== count( $hosts ) || ! method_exists( $installer, 'run_registered_hosts' ) || 1 !== substr_count( $source, 'download_url(' ) || ! str_contains( $source, 'foreach ( $targets as $target )' ) || ! str_contains( $controller_source, '->run_registered_hosts()' ) ) {
    fwrite( STDERR, "FAIL: Registered-host Core fleet synchronization contract failed.\n" );
    exit( 1 );
}
foreach ( $roots as $candidate ) {
    unlink( $candidate . '/src/Example.php' );
    rmdir( $candidate . '/src' );
    unlink( $candidate . '/VERSION' );
    rmdir( $candidate );
}
rmdir( $temporary );
echo "PASS: CorePackageInstaller discovers distinct registered hosts and uses one fleet download path.\n";
