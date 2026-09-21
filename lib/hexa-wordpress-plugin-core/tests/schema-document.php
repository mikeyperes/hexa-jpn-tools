<?php

declare(strict_types=1);

function esc_attr( mixed $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function sanitize_html_class( mixed $value ): string { return preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $value ) ?: ''; }
function wp_json_encode( mixed $value, int $flags = 0 ): string|false { return json_encode( $value, $flags ); }

$root = dirname( __DIR__ );
require $root . '/src/SchemaTools/SchemaGraph.php';
require $root . '/src/SchemaTools/SchemaDocumentRenderer.php';

use Hexa\PluginCore\SchemaTools\SchemaDocumentRenderer;

function schema_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

$renderer = new SchemaDocumentRenderer();
$schema = [
    '@graph' => [
        [ '@type' => 'Person', '@id' => 'https://example.test/#person', 'name' => 'First' ],
        [ '@type' => 'Person', '@id' => 'https://example.test/#person', 'description' => '</script><script>alert("x")</script>' ],
    ],
];
$normalized = $renderer->normalize( $schema );
$script = $renderer->script( $schema, 'schema:test', 'schema-output' );

schema_assert( 'https://schema.org' === $normalized['@context'], 'Schema documents must receive the canonical context.' );
schema_assert( 1 === count( $normalized['@graph'] ), 'Duplicate graph nodes with the same @id must merge.' );
schema_assert( str_contains( $normalized['@graph'][0]['description'], '</script>' ), 'Normalization must preserve source values.' );
schema_assert( ! str_contains( $script, '</script><script>' ) && str_contains( $script, '\\u003C/script\\u003E' ), 'JSON-LD output must hex-escape script-breaking text.' );
schema_assert( str_contains( $script, 'id="schematest"' ) && str_contains( $script, 'class="schema-output"' ), 'Script attributes must be sanitized.' );

echo "PASS: Schema documents normalize, deduplicate, and safely encode shared JSON-LD output.\n";
