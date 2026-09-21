<?php

declare(strict_types=1);

function sanitize_html_class( mixed $value ): string {
    return preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $value ) ?: '';
}

function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

$root = dirname( __DIR__ );
require $root . '/src/WpAdminComponents/CoreUi.php';

use Hexa\PluginCore\WpAdminComponents\CoreUi;

$automatic = CoreUi::collapsible(
    [
        'title'     => 'Article first-letter drop cap',
        'body_html' => '<p>Settings</p>',
    ]
);
$explicit = CoreUi::collapsible(
    [
        'title'       => 'Repeated title',
        'body_html'   => '<p>Settings</p>',
        'query_key'   => 'Plugin / Feature Card',
        'persist_key' => 'plugin-feature-local-state',
    ]
);
$local_fallback = CoreUi::collapsible(
    [
        'title'       => 'Readable card title',
        'body_html'   => '<p>Settings</p>',
        'persist_key' => 'internal-storage-key',
    ]
);
$disabled = CoreUi::collapsible(
    [
        'title'       => 'Ephemeral card',
        'body_html'   => '<p>Temporary</p>',
        'query_state' => false,
    ]
);

ob_start();
CoreUi::render_assets();
$assets = (string) ob_get_clean();

$checks = [
    'Creates a readable query key from the title by default.' => str_contains( $automatic, 'data-hpc-query-key="article-first-letter-drop-cap"' ),
    'Allows a stable explicit key while preserving local-storage fallback.' => str_contains( $explicit, 'data-hpc-query-key="plugin-feature-card"' )
        && str_contains( $explicit, 'data-hpc-persist-key="plugin-feature-local-state"' ),
    'Keeps the default query key readable when a local-storage key exists.' => str_contains( $local_fallback, 'data-hpc-query-key="readable-card-title"' )
        && str_contains( $local_fallback, 'data-hpc-persist-key="internal-storage-key"' ),
    'Allows intentionally ephemeral sections to opt out.' => ! str_contains( $disabled, 'data-hpc-query-key=' ),
    'Uses one WordPress-safe query parameter and an explicit all-closed state.' => str_contains( $assets, "detailsQueryParam = 'hpc_open'" )
        && str_contains( $assets, "url.searchParams.set(detailsQueryParam, keys.join(','))" )
        && str_contains( $assets, "url.searchParams.set(detailsQueryParam, 'none')" )
        && str_contains( $assets, 'params.getAll(detailsQueryParam)' ),
    'Updates the current URL without adding browser-history noise.' => str_contains( $assets, 'history.replaceState(history.state || null' ),
    'Restores query state on initial and AJAX-loaded admin content.' => str_contains( $assets, 'DOMContentLoaded' )
        && str_contains( $assets, "document.addEventListener('hexa-core-host-tab-loaded'" )
        && str_contains( $assets, 'window.hexaPluginCoreInitPersistentDetails' ),
    'Pre-initialization native toggles cannot overwrite restored query or local state.' => str_contains( $assets, "details.dataset.hpcPersistentReady !== '1'" )
        && str_contains( $assets, "details.dataset.hpcQueryReady !== '1'" ),
    'Keeps query and local-storage state in the same generic toggle handler.' => str_contains( $assets, 'details[data-hpc-persist-key],details[data-hpc-query-key]' )
        && str_contains( $assets, 'if (details.dataset.hpcQueryKey) updateDetailsQuery();' ),
    'Visually distinguishes every open Core section without host CSS.' => str_contains( $assets, '.hpc-section[open]{border-color:#c6d3e4' )
        && str_contains( $assets, '.hpc-section[open]>summary{background:#f1f5fb}' ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, 'FAIL: ' . $message . "\n" );
        exit( 1 );
    }
}

echo "PASS: Core collapsibles synchronize stable open state through the query string.\n";
