<?php

declare(strict_types=1);

require dirname( __DIR__ ) . '/src/SchemaTools/SchemaGraph.php';
require dirname( __DIR__ ) . '/src/SchemaDetection/SchemaPageScanner.php';

use Hexa\PluginCore\SchemaDetection\SchemaPageScanner;
use Hexa\PluginCore\SchemaTools\SchemaGraph;

$fail = static function ( string $message ): void {
    fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
    exit( 1 );
};

$foreign_group = [
    'footer_text' => '<p>Powered by an unrelated settings group.</p>',
];

if ( 'https://example.com/' !== SchemaGraph::web_url( $foreign_group, 'https://example.com/' ) ) {
    $fail( 'Typed URL normalization did not reject an associative settings group.' );
}

$schema = [
    '@context' => 'https://schema.org',
    '@graph'   => [
        [
            '@type' => 'NewsMediaOrganization',
            '@id'   => 'https://example.com/#organization',
            'name'  => 'Example Publication',
            'url'   => $foreign_group,
            'sameAs' => [ 'https://example.com/profile', $foreign_group ],
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'url'   => [
                    '@type' => 'Role',
                    'url'   => 'https://example.com/contact/',
                ],
            ],
            'ethicsPolicy' => [
                '@type' => 'CreativeWork',
                '@id'   => 'https://example.com/ethics/#policy',
                'url'   => 'https://example.com/ethics/',
            ],
        ],
        [
            '@type' => 'NewsArticle',
            '@id'   => 'https://example.com/story/#article',
            'url'   => 'https://example.com/story/',
        ],
    ],
];

$issues = SchemaGraph::validation_issues( $schema );
$paths = array_column( $issues, 'path' );
if ( ! in_array( '@graph[0].url', $paths, true ) || ! in_array( '@graph[0].sameAs', $paths, true ) ) {
    $fail( 'Semantic validation did not identify malformed URL properties.' );
}

$sanitized = SchemaGraph::sanitize_urls( $schema );
$organization = $sanitized['@graph'][0] ?? [];
if ( isset( $organization['url'] ) ) {
    $fail( 'Fail-closed sanitization retained an invalid organization URL.' );
}
if ( [ 'https://example.com/profile' ] !== ( $organization['sameAs'] ?? [] ) ) {
    $fail( 'Fail-closed sanitization did not preserve only valid sameAs URLs.' );
}
if ( 'CreativeWork' !== ( $organization['ethicsPolicy']['@type'] ?? '' ) ) {
    $fail( 'Fail-closed sanitization removed a valid structured policy value.' );
}
if ( 'Role' !== ( $organization['contactPoint']['url']['@type'] ?? '' ) ) {
    $fail( 'Fail-closed sanitization removed a valid Role value for url.' );
}
if ( [] !== SchemaGraph::validation_issues( $sanitized ) ) {
    $fail( 'Sanitized schema still reports semantic URL issues.' );
}

$html = '<script id="smpi-schema-jsonld" type="application/ld+json">' . json_encode( $schema ) . '</script>';
$scan = ( new SchemaPageScanner() )->scanBody( $html, [ 'url' => 'https://example.com/story/', 'status' => 200 ] );
if ( 2 !== count( $scan['semantic_issues'] ?? [] ) ) {
    $fail( 'Schema scanner did not expose malformed property values.' );
}
if ( 1 !== (int) ( $scan['semantic_issues'][0]['block'] ?? 0 ) ) {
    $fail( 'Schema scanner did not retain the source block number.' );
}

echo "PASS: typed schema URLs reject foreign option groups and scanner reports semantic issues.\n";
