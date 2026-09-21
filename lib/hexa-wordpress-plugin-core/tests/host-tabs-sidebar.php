<?php

declare(strict_types=1);

function sanitize_key( mixed $value ): string {
    return trim( strtolower( preg_replace( '/[^a-z0-9_-]+/i', '-', (string) $value ) ?? '' ), '-' );
}

function sanitize_html_class( mixed $value ): string {
    return preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $value ) ?: '';
}

function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function esc_url( mixed $value ): string {
    return (string) $value;
}

function esc_js( mixed $value ): string {
    return addslashes( (string) $value );
}

$root = dirname( __DIR__ );
require $root . '/src/WpAdminComponents/CoreUi.php';
require $root . '/src/WpAdminTabs/HostTabsRenderer.php';

use Hexa\PluginCore\WpAdminTabs\HostTabsRenderer;

$args = [
    'tabs'                => [
        'overview' => [ 'label' => 'Overview' ],
        'features' => [ 'label' => 'Features' ],
        'logs'     => [ 'label' => 'Logs' ],
    ],
    'active'              => 'features',
    'page_url'            => '/wp-admin/options-general.php?page=example',
    'ajax_url'            => '/wp-admin/admin-ajax.php',
    'ajax_action'         => 'example_load_tab',
    'nonce'               => 'test-nonce',
    'root_id'             => 'example-tabs',
    'panel_id'            => 'example-panel',
    'layout'              => 'sidebar',
    'groups'              => [
        [
            'label' => 'Settings',
            'tabs'  => [ 'overview', 'features' ],
        ],
    ],
    'sidebar_identity'    => [
        'plugin_name'     => 'Example Plugin',
        'current_version' => '1.2.3',
        'github_version'  => '1.3.0',
        'github_url'      => 'https://github.com/example/example-plugin',
        'core_name'       => 'Hexa WP Core',
        'core_version'    => '0.19.51',
        'core_github_url' => 'https://github.com/example/hexa-core',
    ],
    'sidebar_collapsible' => true,
    'sidebar_persist'     => true,
    'render_callback'     => static function ( string $tab ): void {
        echo '<p>Rendered ' . esc_html( $tab ) . '</p>';
    },
];

ob_start();
( new HostTabsRenderer() )->render( $args );
$html = (string) ob_get_clean();

$collapsed_args                      = $args;
$collapsed_args['root_id']           = 'example-collapsed-tabs';
$collapsed_args['panel_id']          = 'example-collapsed-panel';
$collapsed_args['sidebar_collapsed'] = true;

ob_start();
( new HostTabsRenderer() )->render( $collapsed_args );
$collapsed_html = (string) ob_get_clean();

$core_ui_source = (string) file_get_contents( $root . '/src/WpAdminComponents/CoreUi.php' );
$rail_rule      = '';
if ( preg_match( '/\.hpc-host-rail\{([^}]*)\}/', $core_ui_source, $matches ) ) {
    $rail_rule = (string) $matches[1];
}

$checks = [
    'Renders the reusable grouped sidebar shell.' => str_contains( $html, 'hpc-host-tabs-shell is-sidebar has-collapsible-sidebar' )
        && str_contains( $html, '<aside id="example-tabs-rail" class="hpc-host-rail"' )
        && 3 === substr_count( $html, 'data-hpc-host-tab=' ),
    'Keeps ungrouped tabs visible in a More group.' => str_contains( $html, '<p class="hpc-host-rail-title">More</p>' )
        && str_contains( $html, '>Logs</a>' ),
    'Does not nest tablist roles.' => ! str_contains( $html, 'class="hpc-host-rail" role="tablist"' )
        && 2 === substr_count( $html, 'role="tablist"' ),
    'Renders an accessible expanded toggle by default.' => str_contains( $html, 'data-hpc-sidebar-toggle' )
        && str_contains( $html, 'aria-expanded="true"' )
        && str_contains( $html, 'aria-label="Collapse navigation"' )
        && str_contains( $html, 'dashicons-arrow-left-alt2' ),
    'Places identity and toggle together in the rail header.' => str_contains( $html, '<div class="hpc-host-rail-header">' )
        && strpos( $html, 'hpc-host-rail-header' ) < strpos( $html, 'hpc-host-rail-identity' )
        && strpos( $html, 'hpc-host-rail-identity' ) < strpos( $html, 'hpc-host-rail-tools' )
        && strpos( $html, 'hpc-host-rail-tools' ) < strpos( $html, 'hpc-host-rail-navigation' ),
    'Positions the expanded toggle at the header top-right.' => str_contains( $core_ui_source, '.hpc-host-rail-tools{display:flex;justify-content:flex-end;padding:0;position:absolute;right:5px;top:7px}' ),
    'Centers the toggle and removes the identity row when collapsed.' => str_contains( $core_ui_source, '.is-sidebar-collapsed .hpc-host-rail-header{border-bottom:0;display:flex;justify-content:center;margin:0;padding:0}' )
        && str_contains( $core_ui_source, '.is-sidebar-collapsed .hpc-host-rail-tools{justify-content:center;padding:0;position:static}' ),
    'Renders a valid collapsed initial state.' => str_contains( $collapsed_html, 'is-sidebar-collapsed' )
        && str_contains( $collapsed_html, 'aria-expanded="false"' )
        && str_contains( $collapsed_html, 'aria-label="Expand navigation"' )
        && str_contains( $collapsed_html, 'data-hpc-sidebar-navigation hidden' ),
    'Scopes persistence to the renderer root ID.' => str_contains( $html, 'data-sidebar-storage-key="hpc-host-sidebar-example-tabs"' )
        && str_contains( $html, 'data-sidebar-persist="1"' )
        && str_contains( $html, 'window.localStorage.setItem(root.dataset.sidebarStorageKey' ),
    'Connects tabs and the active panel accessibly.' => str_contains( $html, 'id="example-tabs-tab-features"' )
        && str_contains( $html, 'aria-controls="example-panel"' )
        && str_contains( $html, 'role="tabpanel" aria-labelledby="example-tabs-tab-features"' ),
    'Uses the requested narrower expanded width and compact collapsed width.' => str_contains( $core_ui_source, '--hpc-host-sidebar-width:214px' )
        && str_contains( $core_ui_source, 'grid-template-columns:44px minmax(0,1fr)' ),
    'Removes the sidebar internal scroll container.' => str_contains( $rail_rule, 'max-height:none' )
        && str_contains( $rail_rule, 'overflow:visible' )
        && ! str_contains( $rail_rule, 'overflow:auto' )
        && ! str_contains( $rail_rule, 'max-height:calc' ),
    'Keeps the complete sidebar in normal document flow.' => str_contains( $rail_rule, 'position:static' )
        && ! str_contains( $rail_rule, 'position:sticky' )
        && ! str_contains( $rail_rule, 'top:' ),
    'Renders identity before the first navigation group.' => str_contains( $html, '<strong class="hpc-host-rail-plugin-name">Example Plugin</strong>' )
        && strpos( $html, 'hpc-host-rail-identity' ) < strpos( $html, 'hpc-host-rail-group' ),
    'Renders linked plugin and Core versions safely.' => str_contains( $html, 'Current 1.2.3' )
        && str_contains( $html, '<a href="https://github.com/example/example-plugin" target="_blank" rel="noopener noreferrer">GitHub 1.3.0</a>' )
        && str_contains( $html, '<a class="hpc-host-rail-core" href="https://github.com/example/hexa-core" target="_blank" rel="noopener noreferrer">Hexa WP Core 0.19.51</a>' ),
    'Hides identity when collapsed and wraps version metadata.' => str_contains( $core_ui_source, '.is-sidebar-collapsed .hpc-host-rail-identity{display:none}' )
        && str_contains( $core_ui_source, '.hpc-host-rail-versions{align-items:baseline;color:var(--hpc-muted);display:flex;flex-wrap:wrap' ),
    'Wraps the mobile navigation without horizontal scrolling.' => str_contains( $core_ui_source, '@media(max-width:960px)' )
        && str_contains( $core_ui_source, 'display:flex;flex-wrap:wrap;gap:6px;max-width:100%' )
        && ! str_contains( $rail_rule, 'overflow-x' ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, 'FAIL: ' . $message . "\n" );
        exit( 1 );
    }
}

echo "PASS: Host tabs sidebar is reusable, collapsible, persistent, accessible, and free of internal scrolling.\n";
