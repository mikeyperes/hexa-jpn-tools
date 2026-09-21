<?php

declare( strict_types=1 );

$GLOBALS['acf_settings_panel_calls'] = [ 'head' => 0, 'enqueue' => 0 ];

function add_action( string $hook, callable $callback, int $priority = 10 ): void {}
function is_admin(): bool { return true; }
function sanitize_key( string $value ): string { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $value ) ?: '' ); }
function wp_unslash( string $value ): string { return stripslashes( $value ); }
function acf_form_head(): void { ++$GLOBALS['acf_settings_panel_calls']['head']; }
function acf_enqueue_scripts(): void { ++$GLOBALS['acf_settings_panel_calls']['enqueue']; }

require_once dirname( __DIR__ ) . '/src/CoreContracts/ModuleInterface.php';
require_once dirname( __DIR__ ) . '/src/FieldStructures/AcfSettingsPanel.php';

use Hexa\PluginCore\FieldStructures\AcfSettingsPanel;

$panel = new AcfSettingsPanel( [ 'page_slug' => 'host-settings', 'tab' => 'website-types' ] );

$_REQUEST = [ 'page' => 'host-settings', 'tab' => 'other-tab' ];
$panel->prepare_form();
$panel->enqueue();
if ( [ 'head' => 0, 'enqueue' => 0 ] !== $GLOBALS['acf_settings_panel_calls'] ) {
    fwrite( STDERR, "FAIL: ACF assets loaded on a different host tab.\n" );
    exit( 1 );
}

$_REQUEST = [ 'page' => 'host-settings', 'tab' => 'website-types' ];
$panel->prepare_form();
$panel->enqueue();
if ( [ 'head' => 1, 'enqueue' => 1 ] !== $GLOBALS['acf_settings_panel_calls'] ) {
    fwrite( STDERR, "FAIL: ACF assets did not load on the configured host tab.\n" );
    exit( 1 );
}

$page_panel = new AcfSettingsPanel( [ 'page_slug' => 'standalone-settings' ] );
$_REQUEST = [ 'page' => 'standalone-settings', 'tab' => 'anything' ];
$page_panel->prepare_form();
$page_panel->enqueue();
if ( [ 'head' => 2, 'enqueue' => 2 ] !== $GLOBALS['acf_settings_panel_calls'] ) {
    fwrite( STDERR, "FAIL: Page-scoped ACF settings panels lost backward compatibility.\n" );
    exit( 1 );
}

echo "PASS: ACF settings panels scope assets to their configured admin tab.\n";
