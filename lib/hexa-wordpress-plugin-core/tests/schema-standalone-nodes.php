<?php

declare(strict_types=1);

require dirname( __DIR__ ) . '/src/SchemaTools/SchemaGraph.php';

use Hexa\PluginCore\SchemaTools\SchemaGraph;

$fail = static function ( string $message ): never {
    fwrite( STDERR, "FAIL: {$message}\n" );
    exit( 1 );
};

$org_id = 'https://example.com/#organization';
$website_id = 'https://example.com/#website';
$page_id = 'https://example.com/story/#webpage';
$article_id = 'https://example.com/story/#article';
$person_id = 'https://example.com/author/editor/#person';
$image_id = 'https://example.com/story/#primaryimage';
$breadcrumb_id = 'https://example.com/story/#breadcrumb';
$schema = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [ '@type' => 'NewsMediaOrganization', '@id' => $org_id, 'name' => 'Example News', 'url' => 'https://example.com/', 'logo' => [ '@type' => 'ImageObject', 'url' => 'https://example.com/logo.png', 'width' => 512, 'height' => 512 ] ],
        [ '@type' => 'WebSite', '@id' => $website_id, 'url' => 'https://example.com/', 'publisher' => [ '@id' => $org_id ] ],
        [ '@type' => 'WebPage', '@id' => $page_id, 'url' => 'https://example.com/story/', 'isPartOf' => [ '@id' => $website_id ], 'publisher' => [ '@id' => $org_id ], 'primaryImageOfPage' => [ '@id' => $image_id ], 'breadcrumb' => [ '@id' => $breadcrumb_id ] ],
        [ '@type' => 'NewsArticle', '@id' => $article_id, 'url' => 'https://example.com/story/', 'mainEntityOfPage' => 'https://example.com/story/', 'isPartOf' => [ '@id' => $website_id ], 'author' => [ '@id' => $person_id ], 'publisher' => [ '@id' => $org_id ], 'image' => [ '@id' => $image_id ], 'copyrightHolder' => [ '@id' => $org_id ] ],
        [ '@type' => 'Person', '@id' => $person_id, 'name' => 'Editor Example', 'url' => 'https://example.com/author/editor/' ],
        [ '@type' => 'ImageObject', '@id' => $image_id, 'url' => 'https://example.com/image.jpg', 'width' => 1200, 'height' => 675 ],
        [ '@type' => 'BreadcrumbList', '@id' => $breadcrumb_id, 'itemListElement' => [] ],
    ],
];

$standalone = SchemaGraph::standalone_nodes( $schema );
$nodes = [];
foreach ( $standalone['@graph'] as $node ) {
    $nodes[ $node['@type'] ] = $node;
}

if ( 7 !== count( $standalone['@graph'] ) || array_column( $standalone['@graph'], '@id' ) !== array_column( $schema['@graph'], '@id' ) ) {
    $fail( 'Top-level graph nodes or identifiers changed.' );
}
if ( $website_id !== ( $nodes['NewsArticle']['isPartOf'] ?? '' ) || $breadcrumb_id !== ( $nodes['WebPage']['breadcrumb'] ?? '' ) ) {
    $fail( 'Reference-only relationships did not become identifier URLs.' );
}
if ( 'Person' !== ( $nodes['NewsArticle']['author']['@type'] ?? '' ) || isset( $nodes['NewsArticle']['author']['@id'] ) ) {
    $fail( 'Article author did not retain a detached typed Person summary.' );
}
if ( 'NewsMediaOrganization' !== ( $nodes['NewsArticle']['publisher']['@type'] ?? '' ) || isset( $nodes['NewsArticle']['publisher']['@id'] ) ) {
    $fail( 'Article publisher did not retain a detached typed organization summary.' );
}
if ( 'ImageObject' !== ( $nodes['WebPage']['primaryImageOfPage']['@type'] ?? '' ) || isset( $nodes['WebPage']['primaryImageOfPage']['@id'] ) ) {
    $fail( 'Primary image did not retain a detached typed ImageObject summary.' );
}
if ( [ '@id' => $website_id ] !== $schema['@graph'][2]['isPartOf'] ) {
    $fail( 'Standalone transformation mutated its source graph.' );
}
if ( [] !== SchemaGraph::validation_issues( $standalone ) ) {
    $fail( 'Standalone graph introduced malformed URL values.' );
}

$linked = SchemaGraph::standalone_nodes(
    $schema,
    [
        'author'             => [ '@id', '@type' ],
        'publisher'          => [ '@id', '@type' ],
        'copyrightHolder'    => [ '@id', '@type' ],
        'image'              => [ '@id', '@type' ],
        'primaryImageOfPage' => [ '@id', '@type' ],
    ]
);
$linked_nodes = [];
foreach ( $linked['@graph'] as $node ) {
    $linked_nodes[ $node['@type'] ] = $node;
}

if ( [ '@id' => $org_id, '@type' => 'NewsMediaOrganization' ] !== $linked_nodes['NewsArticle']['publisher'] ) {
    $fail( 'Article publisher did not retain its independent organization identifier.' );
}
if ( [ '@id' => $person_id, '@type' => 'Person' ] !== $linked_nodes['NewsArticle']['author'] ) {
    $fail( 'Article author did not retain its independent person identifier.' );
}
if ( [ '@id' => $image_id, '@type' => 'ImageObject' ] !== $linked_nodes['WebPage']['primaryImageOfPage'] ) {
    $fail( 'Primary image did not retain its independent image identifier.' );
}
if ( 7 !== count( $linked['@graph'] ) || [] !== SchemaGraph::validation_issues( $linked ) ) {
    $fail( 'Identifier-linked relationships changed or invalidated top-level graph nodes.' );
}

echo "PASS: Graph nodes remain independently detectable with typed or identifier-linked relationships.\n";
