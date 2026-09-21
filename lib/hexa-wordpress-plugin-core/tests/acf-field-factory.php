<?php

declare( strict_types=1 );

function sanitize_key( mixed $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ) ?: ''; }

$root = dirname( __DIR__ );
require $root . '/src/AcfFieldFactory/AcfFieldFactory.php';

use Hexa\PluginCore\AcfFieldFactory\AcfFieldFactory;

$methods = [ 'text', 'textarea', 'wysiwyg', 'url', 'email', 'number', 'date', 'select', 'toggle', 'image', 'gallery', 'group', 'repeater', 'relationship', 'user', 'tab' ];
foreach ( $methods as $method ) {
    $key = 'field_host_' . $method;
    $field = AcfFieldFactory::$method( [ 'key' => $key, 'label' => ucfirst( $method ), 'name' => $method, 'required' => 1, 'custom_flag' => 'kept' ] );
    if ( $field['key'] !== $key || 1 !== $field['required'] || 'kept' !== $field['custom_flag'] || '' === $field['type'] ) {
        fwrite( STDERR, 'FAIL: ACF method did not preserve caller keys/arguments: ' . $method . "\n" );
        exit( 1 );
    }
}

$generic = AcfFieldFactory::field( 'custom_type', [ 'key' => 'field_stable', 'name' => 'stable' ] );
$legacy = AcfFieldFactory::multiPostObject( [ 'key' => 'field_posts', 'label' => 'Posts', 'name' => 'posts', 'post_types' => [ 'post', 'page' ] ] );
if ( 'field_stable' !== $generic['key'] || 'custom_type' !== $generic['type'] || 'post_object' !== $legacy['type'] || 1 !== $legacy['multiple'] || 'id' !== $legacy['return_format'] ) {
    fwrite( STDERR, "FAIL: Generic or legacy ACF factory contract changed.\n" );
    exit( 1 );
}

echo "PASS: ACF field factory exposes generic typed builders with caller-owned stable keys.\n";
