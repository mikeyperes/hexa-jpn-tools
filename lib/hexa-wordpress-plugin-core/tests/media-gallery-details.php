<?php

declare(strict_types=1);

function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function esc_url( mixed $value ): string {
    return filter_var( (string) $value, FILTER_SANITIZE_URL ) ?: '';
}

function sanitize_html_class( mixed $value ): string {
    return preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $value ) ?: '';
}

function sanitize_key( mixed $value ): string {
    return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ) ?: '';
}

function wp_attachment_is_image( int $attachment_id ): bool {
    return in_array( $attachment_id, [ 101, 202 ], true );
}

function wp_get_attachment_metadata( int $attachment_id ): array {
    return [
        'width'  => 2400,
        'height' => 1600,
        'sizes'  => [
            'medium'        => [ 'width' => 300, 'height' => 200 ],
            'thumbnail'     => [ 'width' => 150, 'height' => 150 ],
            'medium_large'  => [ 'width' => 768, 'height' => 512 ],
            'publication-x' => [ 'width' => 1200, 'height' => 800 ],
        ],
    ];
}

function wp_get_attachment_url( int $attachment_id ): string {
    return 'https://example.test/uploads/photo-' . $attachment_id . '.jpg';
}

function wp_get_attachment_image_src( int $attachment_id, string $size ): array {
    $dimensions = [
        'thumbnail'     => [ 150, 150 ],
        'medium'        => [ 300, 200 ],
        'medium_large'  => [ 768, 512 ],
        'publication-x' => [ 1200, 800 ],
    ];
    $dimension = $dimensions[ $size ] ?? [ 2400, 1600 ];

    return [
        'https://example.test/uploads/photo-' . $attachment_id . '-' . str_replace( '_', '-', $size ) . '.jpg',
        $dimension[0],
        $dimension[1],
    ];
}

function get_the_title( int $attachment_id ): string {
    return 101 === $attachment_id ? 'Primary portrait' : 'Secondary portrait';
}

function wp_parse_url( string $url, int $component = -1 ): string|int|array|null|false {
    return parse_url( $url, $component );
}

function wp_basename( string $path ): string {
    return basename( $path );
}

$root = dirname( __DIR__ );
require $root . '/src/WpAdminComponents/CoreUi.php';
require $root . '/src/WpAdminComponents/DynamicButton.php';
require $root . '/src/WpAdminComponents/MediaGalleryDetailsRenderer.php';

use Hexa\PluginCore\WpAdminComponents\MediaGalleryDetailsRenderer;

$html = MediaGalleryDetailsRenderer::render(
    [
        101,
        [ 'ID' => 202 ],
        (object) [ 'ID' => 101 ],
        999,
    ],
    [
        'persist_key'   => 'test-gallery-details',
        'allow_remove'  => true,
        'live_refresh'  => true,
        'ajax_url'      => 'https://example.test/wp-admin/admin-ajax.php',
        'ajax_action'   => 'test_gallery_action',
        'nonce'         => 'test-nonce',
        'context'       => 'user_44',
        'field_key'     => 'field_test_gallery',
        'preview_pixels'=> 112,
    ]
);

$checks = [
    'Uses the shared collapsed details card.' => str_contains( $html, 'hpc-detail-card hpc-media-gallery-details-card' )
        && str_contains( $html, 'hpc-detail-card-title">Details</span>' )
        && ! preg_match( '/<details[^>]*\sopen(?:\s|>)/', $html ),
    'Normalizes ACF values and renders every valid attachment once.' => str_contains( $html, 'data-attachment-id="101"' )
        && str_contains( $html, 'data-attachment-id="202"' )
        && 1 === substr_count( $html, 'data-attachment-id="101"' )
        && ! str_contains( $html, 'data-attachment-id="999"' ),
    'Lists full and every generated image-size URL.' => str_contains( $html, 'https://example.test/uploads/photo-101.jpg' )
        && str_contains( $html, 'photo-101-thumbnail.jpg' )
        && str_contains( $html, 'photo-101-medium.jpg' )
        && str_contains( $html, 'photo-101-medium-large.jpg' )
        && str_contains( $html, 'photo-101-publication-x.jpg' ),
    'URLs are selectable external links with dimensions.' => str_contains( $html, 'class="hpc-media-gallery-url hpc-external"' )
        && str_contains( $html, 'target="_blank" rel="noopener noreferrer"' )
        && str_contains( $html, '2400 x 1600 px' )
        && str_contains( $html, 'user-select:text' ),
    'Every size has separate dynamic image and URL clipboard buttons.' => str_contains( $html, 'data-hpc-dynamic-button' )
        && str_contains( $html, 'data-working-label="Copying image..."' )
        && str_contains( $html, 'data-working-label="Copying URL..."' )
        && str_contains( $html, 'data-hpc-gallery-copy-image="https://example.test/uploads/photo-101.jpg"' )
        && str_contains( $html, 'data-hpc-gallery-copy="https://example.test/uploads/photo-101.jpg"' )
        && str_contains( $html, "HexaWpCoreDynamicButton" )
        && str_contains( $html, "new ClipboardItem({'image/png':pending})" )
        && str_contains( $html, 'catch(function(){return legacyCopy(value)})' ),
    'Images can be selected individually or all at once.' => str_contains( $html, 'value="101" data-hpc-gallery-select' )
        && str_contains( $html, 'value="202" data-hpc-gallery-select' )
        && str_contains( $html, 'data-hpc-gallery-select-all' )
        && str_contains( $html, "selected.length+' selected'" ),
    'Large previews and removal controls use stable generic UI.' => str_contains( $html, '--hpc-media-gallery-preview-size:112px' )
        && str_contains( $html, 'data-hpc-gallery-remove="101"' )
        && str_contains( $html, 'Remove from this gallery. The Media Library attachment will not be deleted.' ),
    'Native ACF gallery mutations request immediate Core refreshes.' => str_contains( $html, 'data-hpc-gallery-live-refresh="1"' )
        && str_contains( $html, 'new MutationObserver' )
        && str_contains( $html, "scheduleRefresh(root)" )
        && str_contains( $html, "request(root,'refresh'" )
        && str_contains( $html, "document.addEventListener('DOMContentLoaded'" )
        && str_contains( $html, "window.acf.addAction('append'" )
        && strpos( $html, 'data-hpc-media-gallery-details' ) < strpos( $html, 'window.hexaCoreMediaGalleryDetailsReady' ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, 'FAIL: ' . $message . "\n" );
        exit( 1 );
    }
}

$source = strtolower( (string) file_get_contents( $root . '/src/WpAdminComponents/MediaGalleryDetailsRenderer.php' ) );
foreach ( [ 'hws_', 'smpi-', 'blockeditorial', 'herforward' ] as $host_term ) {
    if ( str_contains( $source, $host_term ) ) {
        fwrite( STDERR, 'FAIL: Shared gallery renderer contains host-specific term ' . $host_term . ".\n" );
        exit( 1 );
    }
}

echo "PASS: Media gallery details are live, removable, image/URL-copyable, size-complete, and host-neutral.\n";
