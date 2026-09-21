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

function hexa_test_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

function hexa_test_package( string $root, string $version, string $hash, string $source ): void {
    mkdir( $root . '/src/TestFixture', 0777, true );
    file_put_contents( $root . '/VERSION', $version );
    file_put_contents( $root . '/PACKAGE_HASH', $hash );
    file_put_contents( $root . '/src/TestFixture/SelectedPackage.php', $source );
}

function hexa_test_remove_tree( string $root ): void {
    if ( ! is_dir( $root ) ) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ( $iterator as $item ) {
        if ( $item instanceof SplFileInfo && $item->isDir() ) {
            rmdir( $item->getPathname() );
        } else {
            unlink( $item->getPathname() );
        }
    }
    rmdir( $root );
}

$temporary_root = __DIR__ . '/.runtime-' . bin2hex( random_bytes( 6 ) );
$older_root     = $temporary_root . '/older';
$newer_root     = $temporary_root . '/newer';

register_shutdown_function( static function() use ( $temporary_root ): void {
    hexa_test_remove_tree( $temporary_root );
} );

hexa_test_package(
    $older_root,
    '1.0.0',
    str_repeat( 'a', 64 ),
    '<?php namespace Hexa\\PluginCore\\TestFixture; final class SelectedPackage { public const SOURCE = "older"; }'
);
hexa_test_package(
    $newer_root,
    '1.1.0',
    str_repeat( 'b', 64 ),
    '<?php namespace Hexa\\PluginCore\\TestFixture; final class SelectedPackage { public const SOURCE = "newer"; }'
);

require dirname( __DIR__ ) . '/bootstrap.php';

hexa_plugin_core_register_package( 'older-host', $older_root, [ 'minimum_version' => '1.0.0' ] );
hexa_plugin_core_register_package( 'newer-host', $newer_root, [ 'minimum_version' => '1.1.0' ] );

$selected = HexaPluginCorePackageRegistry::resolve();
hexa_test_assert( '1.1.0' === ( $selected['version'] ?? '' ), 'The newest compatible package should be selected.' );
hexa_test_assert( $newer_root === ( $selected['root'] ?? '' ), 'The selected package root should belong to the newer package.' );
hexa_test_assert( class_exists( Hexa\PluginCore\TestFixture\SelectedPackage::class ), 'The selected package class should autoload.' );
hexa_test_assert( 'newer' === Hexa\PluginCore\TestFixture\SelectedPackage::SOURCE, 'Only the selected package should provide Core classes.' );

$report = HexaPluginCorePackageRegistry::report();
hexa_test_assert( true === $report['healthy'], 'Different package versions should be healthy when one compatible root owns the namespace.' );
hexa_test_assert( 2 === count( $report['candidates'] ), 'Both package candidates should remain visible in diagnostics.' );

echo "PASS: Core package registry selects one compatible package root.\n";
