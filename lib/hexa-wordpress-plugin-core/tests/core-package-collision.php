<?php

declare(strict_types=1);

$GLOBALS['hexa_test_actions'] = [];

function add_action( string $hook, callable $callback, int $priority = 10 ): void {
    $GLOBALS['hexa_test_actions'][ $hook ][ $priority ][] = $callback;
}

function did_action( string $hook ): int {
    return 0;
}

function do_action( string $hook, ...$arguments ): void {
}

function hexa_collision_remove_tree( string $root ): void {
    if ( ! is_dir( $root ) ) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ( $iterator as $item ) {
        $item instanceof SplFileInfo && $item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
    }
    rmdir( $root );
}

function hexa_collision_package( string $root, string $hash ): void {
    mkdir( $root . '/src', 0777, true );
    file_put_contents( $root . '/VERSION', '2.0.0' );
    file_put_contents( $root . '/PACKAGE_HASH', $hash );
}

$temporary_root = __DIR__ . '/.runtime-' . bin2hex( random_bytes( 6 ) );
register_shutdown_function( static function() use ( $temporary_root ): void {
    hexa_collision_remove_tree( $temporary_root );
} );

hexa_collision_package( $temporary_root . '/one', str_repeat( '1', 64 ) );
hexa_collision_package( $temporary_root . '/two', str_repeat( '2', 64 ) );

require dirname( __DIR__ ) . '/bootstrap.php';

hexa_plugin_core_register_package( 'host-one', $temporary_root . '/one' );
hexa_plugin_core_register_package( 'host-two', $temporary_root . '/two' );
HexaPluginCorePackageRegistry::resolve();

$report = HexaPluginCorePackageRegistry::report();
$types  = array_column( $report['issues'], 'type' );

if ( $report['healthy'] || ! in_array( 'same_version_hash_mismatch', $types, true ) ) {
    fwrite( STDERR, "FAIL: A same-version source mismatch must be reported as unhealthy.\n" );
    exit( 1 );
}

echo "PASS: Same-version package source mismatch is reported.\n";
