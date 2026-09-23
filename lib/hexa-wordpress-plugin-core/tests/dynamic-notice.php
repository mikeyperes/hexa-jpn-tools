<?php

declare(strict_types=1);

function sanitize_key( mixed $value ): string {
    return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ) ?: '';
}

function sanitize_html_class( mixed $value ): string {
    return preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) ?: '';
}

function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

$root = dirname( __DIR__ );
require_once $root . '/src/WpAdminComponents/CoreUi.php';
require_once $root . '/src/WpAdminComponents/DynamicNotice.php';

use Hexa\PluginCore\WpAdminComponents\DynamicNotice;

ob_start();
$html = DynamicNotice::render(
    [
        'id'      => 'host-save-notice',
        'tone'    => 'warning',
        'title'   => 'Recommended author changed',
        'message' => 'The setting was saved with a non-default author.',
        'hidden'  => false,
    ]
);
$assets = (string) ob_get_clean();

$checks = [
    'Renders one reusable live region with the selected tone.' => str_contains( $html, 'id="host-save-notice"' )
        && str_contains( $html, 'class="hpc-dynamic-notice is-warning"' )
        && str_contains( $html, 'aria-live="polite"' ),
    'Renders concise title and message fields.' => str_contains( $html, 'Recommended author changed' )
        && str_contains( $html, 'non-default author' ),
    'Ships one shared browser API for save success, warning and failure states.' => str_contains( $assets, 'window.HexaWpCoreDynamicNotice' )
        && str_contains( $assets, 'success:function' )
        && str_contains( $assets, 'warning:function' )
        && str_contains( $assets, 'error:function' ),
    'Supports dismissing a notice without reloading the page.' => str_contains( $html, 'data-hpc-dynamic-notice-dismiss' )
        && str_contains( $assets, "document.addEventListener('click'" ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, 'FAIL: ' . $message . "\n" );
        exit( 1 );
    }
}

echo "PASS: Dynamic notices provide one shared Hexa WP Core save-feedback pattern.\n";
