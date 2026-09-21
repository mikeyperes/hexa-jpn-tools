<?php

declare(strict_types=1);

function sanitize_key( string $value ): string { return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', $value ) ?: '' ); }
function sanitize_html_class( string $value ): string { return preg_replace( '/[^a-zA-Z0-9_-]/', '', $value ) ?: ''; }
function esc_attr( mixed $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html( mixed $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_url( mixed $value ): string { return (string) $value; }
function wp_kses_post( mixed $value ): string { return (string) $value; }
function wp_strip_all_tags( mixed $value ): string { return strip_tags( (string) $value ); }
function checked( bool $checked, bool $current = true, bool $display = true ): string { return $checked === $current ? 'checked="checked"' : ''; }
function disabled( bool $disabled, bool $current = true, bool $display = true ): string { return $disabled === $current ? 'disabled="disabled"' : ''; }
function admin_url( string $path = '' ): string { return 'https://example.test/wp-admin/' . $path; }
function get_option( string $key, mixed $default = false ): mixed { return $default; }

require dirname( __DIR__ ) . '/src/WpAdminComponents/CoreUi.php';
require dirname( __DIR__ ) . '/src/SnippetRegistry/SnippetDefinition.php';
require dirname( __DIR__ ) . '/src/SnippetRegistry/SnippetRegistry.php';
require dirname( __DIR__ ) . '/src/SnippetRegistry/SnippetsTableRenderer.php';

use Hexa\PluginCore\SnippetRegistry\SnippetRegistry;
use Hexa\PluginCore\SnippetRegistry\SnippetsTableRenderer;

$registry = ( new SnippetRegistry() )->add(
    [
        'id'          => 'example',
        'name'        => 'Example',
        'description' => 'Example implementation metadata.',
        'option_key'  => 'example_enabled',
    ]
);

$html = ( new SnippetsTableRenderer() )->render( $registry, [ 'show_toggle' => false ] );
if ( str_contains( $html, '<input type="checkbox" data-snippet-toggle' ) || str_contains( $html, '<th class="c-toggle"' ) ) {
    fwrite( STDERR, "FAIL: Metadata-only snippets view rendered a duplicate feature toggle.\n" );
    exit( 1 );
}

echo "PASS: Metadata-only snippets view omits feature toggles.\n";
