<?php

declare(strict_types=1);

function wp_kses_post( string $html ): string {
    return str_replace( '<script>alert(1)</script>', '', $html );
}
function wp_strip_all_tags( string $html, bool $remove_breaks = false ): string {
    return strip_tags( $html );
}

$root = dirname( __DIR__ );
require $root . '/src/FrontendForms/FieldSchema.php';
require $root . '/src/FrontendForms/RichTextValue.php';

use Hexa\PluginCore\FrontendForms\FieldSchema;
use Hexa\PluginCore\FrontendForms\RichTextValue;

function frontend_form_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

$schema = FieldSchema::normalize(
    [
        [ 'key' => 'subject_name', 'label' => 'Person and/or Company', 'type' => 'text', 'required' => true ],
        [ 'key' => 'where_to_add', 'label' => 'Where to add', 'type' => 'wysiwyg' ],
        [ 'key' => 'supporting_photo', 'label' => 'Photo', 'type' => 'image' ],
        [ 'key' => 'subject_name', 'label' => 'Duplicate', 'type' => 'text' ],
        [ 'key' => '../invalid', 'label' => 'Invalid', 'type' => 'text' ],
    ]
);

frontend_form_assert( 3 === count( $schema ), 'Schemas must reject invalid and duplicate keys.' );
frontend_form_assert( FieldSchema::is_rich_text( $schema[1]['type'] ), 'WYSIWYG must be a canonical rich-text type.' );
frontend_form_assert( FieldSchema::is_upload( $schema[2]['type'] ), 'Image must be a canonical upload type.' );

$safe = RichTextValue::sanitize( '<p>Use <a href="https://example.com">this</a>.</p><script>alert(1)</script>' );
frontend_form_assert( ! str_contains( $safe, '<script>' ) && str_contains( $safe, '<a ' ), 'Rich text must be sanitized while preserving allowed formatting.' );
frontend_form_assert( 'Use this.' === RichTextValue::plain_text( $safe ), 'Rich text must have a reusable plain-text projection.' );

echo "PASS: Front-end form schemas share field, WYSIWYG, and upload contracts.\n";
