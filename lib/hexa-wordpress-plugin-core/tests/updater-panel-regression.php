<?php

declare(strict_types=1);

$root = dirname( __DIR__ );

$core_ui = (string) file_get_contents( $root . '/src/WpAdminComponents/CoreUi.php' );
if ( ! str_contains( $core_ui, '.hpc-stack{display:grid;gap:14px}' ) ) {
    fwrite( STDERR, "FAIL: Core UI stack spacing contract is missing.\n" );
    exit( 1 );
}

$renderers = [
    'src/CorePackageUpdates/CorePackagePanelRenderer.php',
    'src/PluginUpdates/UpdaterPanelRenderer.php',
];

foreach ( $renderers as $relative_file ) {
    $source = (string) file_get_contents( $root . '/' . $relative_file );

    if ( ! str_contains( $source, 'class="hpc-section-body hpc-stack"' ) ) {
        fwrite( STDERR, 'FAIL: ' . $relative_file . " does not use the shared stack layout.\n" );
        exit( 1 );
    }
    if ( str_contains( $source, 'for(var i=renderedSteps;i<data.steps.length;i++)' ) ) {
        fwrite( STDERR, 'FAIL: ' . $relative_file . " still uses append-only progress rendering.\n" );
        exit( 1 );
    }
    if (
        ! str_contains( $source, 'for(var i=0;i<data.steps.length;i++)' )
        || ! str_contains( $source, "row.attr('class'" )
        || ! str_contains( $source, 'body.children().slice(data.steps.length).remove()' )
    ) {
        fwrite( STDERR, 'FAIL: ' . $relative_file . " does not reconcile existing progress rows.\n" );
        exit( 1 );
    }
}

echo "PASS: Updater panels reconcile progress state and use shared stack spacing.\n";
