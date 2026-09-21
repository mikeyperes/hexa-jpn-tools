<?php

declare( strict_types=1 );

$GLOBALS['gallery_hooks'] = [];
$GLOBALS['gallery_values'] = [ 'user_144' => [ 101, 202 ] ];

function sanitize_key( mixed $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ) ?: ''; }
function sanitize_html_class( mixed $value ): string { return preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $value ) ?: ''; }
function esc_attr( mixed $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html( mixed $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_url( mixed $value ): string { return filter_var( (string) $value, FILTER_SANITIZE_URL ) ?: ''; }
function add_action( string $hook, mixed $callback ): void { $GLOBALS['gallery_hooks'][ $hook ] = $callback; }
function acf_get_form_data( string $key ): string { return 'post_id' === $key ? 'user_144' : ''; }
function current_user_can( string $capability, mixed ...$args ): bool { return 'edit_user' === $capability && 144 === ( $args[0] ?? 0 ); }
function admin_url( string $path = '' ): string { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }
function wp_create_nonce( string $action ): string { return 'nonce-' . md5( $action ); }
function wp_attachment_is_image( int $attachment_id ): bool { return in_array( $attachment_id, [ 101, 202, 303 ], true ); }
function wp_get_attachment_metadata( int $attachment_id ): array { return [ 'width' => 1200, 'height' => 800, 'sizes' => [ 'thumbnail' => [ 'width' => 150, 'height' => 150 ], 'medium' => [ 'width' => 300, 'height' => 200 ] ] ]; }
function wp_get_attachment_url( int $attachment_id ): string { return 'https://example.test/uploads/image-' . $attachment_id . '.jpg'; }
function wp_get_attachment_image_src( int $attachment_id, string $size ): array { return [ 'https://example.test/uploads/image-' . $attachment_id . '-' . str_replace( '_', '-', $size ) . '.jpg', 300, 200 ]; }
function get_the_title( int $attachment_id ): string { return 'Image ' . $attachment_id; }
function wp_parse_url( string $url, int $component = -1 ): string|int|array|null|false { return parse_url( $url, $component ); }
function wp_basename( string $path ): string { return basename( $path ); }
function get_field( string $field_key, string $context, bool $format = true ): array { return $GLOBALS['gallery_values'][ $context ] ?? []; }
function update_field( string $field_key, array $value, string $context ): bool { $GLOBALS['gallery_values'][ $context ] = $value; return true; }
function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }

class WP_Error {
    public function __construct( private string $code, private string $message ) {}
    public function get_error_message(): string { return $this->message; }
}

$root = dirname( __DIR__ );
require $root . '/src/CoreContracts/ModuleInterface.php';
require $root . '/src/WpAdminComponents/CoreUi.php';
require $root . '/src/WpAdminComponents/DynamicButton.php';
require $root . '/src/WpAdminComponents/MediaGalleryDetailsRenderer.php';
require $root . '/src/FieldStructures/AcfGalleryDetailsModule.php';

use Hexa\PluginCore\FieldStructures\AcfGalleryDetailsModule;

$module = new AcfGalleryDetailsModule(
    [
        'field_key'          => 'field_host_photos',
        'persist_key'        => 'host-photo-details',
        'preview_pixels'     => 128,
        'preview_image_size' => 'medium',
    ]
);
$module->register();

$render_hook = 'acf/render_field/key=field_host_photos';
$ajax_hook   = 'wp_ajax_' . $module->ajax_action();
if ( ! isset( $GLOBALS['gallery_hooks'][ $render_hook ], $GLOBALS['gallery_hooks'][ $ajax_hook ] ) ) {
    fwrite( STDERR, "FAIL: Generic ACF render and AJAX hooks were not registered.\n" );
    exit( 1 );
}

ob_start();
$module->render( [ 'value' => [ 101, 202 ] ] );
$html = (string) ob_get_clean();

$checks = [
    'Module renders the configured field through Core.' => str_contains( $html, 'data-hpc-gallery-field-key="field_host_photos"' )
        && str_contains( $html, '--hpc-media-gallery-preview-size:128px' ),
    'Module enables live ACF synchronization.' => str_contains( $html, 'data-hpc-gallery-live-refresh="1"' )
        && str_contains( $html, 'data-hpc-gallery-context="user_144"' )
        && strpos( $html, 'data-hpc-media-gallery-details' ) < strpos( $html, 'window.hexaCoreMediaGalleryDetailsReady' ),
    'Module exposes nonce-protected generic AJAX configuration.' => str_contains( $html, 'data-hpc-gallery-ajax-action="' . $module->ajax_action() . '"' )
        && str_contains( $html, 'data-hpc-gallery-nonce="nonce-' ),
    'Module enables dynamic removal without deleting attachments.' => str_contains( $html, 'data-hpc-gallery-remove="101"' )
        && str_contains( $html, 'Media Library attachment will not be deleted' ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, 'FAIL: ' . $message . "\n" );
        exit( 1 );
    }
}

$removed = $module->remove_attachment( 101, 'user_144' );
if ( is_wp_error( $removed ) || [ 202 ] !== $GLOBALS['gallery_values']['user_144'] || true !== ( $removed['changed'] ?? false ) ) {
    fwrite( STDERR, "FAIL: Generic removal did not persist the remaining ACF gallery IDs.\n" );
    exit( 1 );
}

$unsaved = $module->remove_attachment( 303, 'user_144' );
if ( is_wp_error( $unsaved ) || false !== ( $unsaved['changed'] ?? true ) || [ 202 ] !== $GLOBALS['gallery_values']['user_144'] ) {
    fwrite( STDERR, "FAIL: Unsaved gallery removal was not idempotent.\n" );
    exit( 1 );
}

$source = strtolower( (string) file_get_contents( $root . '/src/FieldStructures/AcfGalleryDetailsModule.php' ) );
foreach ( [ 'hws_', 'smpi-', 'blockeditorial', 'herforward', 'michaelperes' ] as $host_term ) {
    if ( str_contains( $source, $host_term ) ) {
        fwrite( STDERR, 'FAIL: Generic ACF gallery module contains host-specific term ' . $host_term . ".\n" );
        exit( 1 );
    }
}

echo "PASS: Generic ACF gallery details module renders, refreshes, and persists removals.\n";
