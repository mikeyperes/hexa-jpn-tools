<?php

declare( strict_types=1 );

$GLOBALS['dn_meta'] = [
    7 => [ 'fallback' => 'Post fallback', '_wp_attachment_image_alt' => 'Alt text' ],
    8 => [ '_wp_attachment_image_alt' => 'Fallback alt' ],
];
$GLOBALS['dn_fields'] = [
    'primary'     => 'ACF value',
    'zero'        => 0,
    'empty_array' => [],
];
function absint( mixed $value ): int { return abs( (int) $value ); }
function wp_strip_all_tags( string $value, bool $remove_breaks = false ): string { return strip_tags( $value ); }
function esc_url_raw( string $value ): string { return $value; }
function sanitize_email( string $value ): string { return strtolower( $value ); }
function get_field( string $name, mixed $context ): mixed { return $GLOBALS['dn_fields'][ $name ] ?? ''; }
function get_post_meta( int $id, string $key, bool $single = true ): mixed { return $GLOBALS['dn_meta'][ $id ][ $key ] ?? ''; }
function wp_get_attachment_image_src( int $id, string $size ): array|false {
    if ( 8 === $id && 'full' !== $size ) {
        return false;
    }
    return [ 'https://example.test/' . $id . '-' . $size . '.jpg', 'full' === $size ? 1200 : 300, 'full' === $size ? 800 : 200 ];
}
function get_the_title( int $id ): string { return 'Attachment title'; }
function wp_get_attachment_caption( int $id ): string { return 'Attachment caption'; }

$root = dirname( __DIR__ );
require $root . '/src/DataNormalization/ValueNormalizer.php';
require $root . '/src/DataNormalization/FieldReader.php';
require $root . '/src/DataNormalization/MediaNormalizer.php';

use Hexa\PluginCore\DataNormalization\FieldReader;
use Hexa\PluginCore\DataNormalization\MediaNormalizer;
use Hexa\PluginCore\DataNormalization\ValueNormalizer;

$reader = new FieldReader( 7 );
$image = MediaNormalizer::image( [ 'ID' => 7, 'url' => 'https://example.test/fallback.jpg' ], 'thumbnail' );
$fallback_image = MediaNormalizer::image( 8, 'thumbnail' );
$loose_gallery = MediaNormalizer::gallery(
    json_encode( [
        [
            'url'      => 'https://cdn.example.test/custom.jpg',
            'full_url' => 'https://cdn.example.test/custom-full.jpg',
            'alt'      => 'Custom <b>alt</b>',
        ],
        'https://cdn.example.test/plain-file.jpg',
        'https://cdn.example.test/plain-file.jpg',
    ] ),
    'large',
    true
);
$gallery_records = MediaNormalizer::gallery_records(
    json_encode( [
        [ 'url' => 'https://cdn.example.test/duplicate.jpg', 'title' => 'First' ],
        [ 'url' => 'https://cdn.example.test/duplicate.jpg', 'title' => 'Second' ],
        'https://cdn.example.test/plain-record.jpg',
    ] ),
    'large'
);
$checks = [
    ValueNormalizer::present( [ '', 'value' ] ),
    'Hello' === ValueNormalizer::text( '<b>Hello</b>' ),
    '2025-01-02' === ValueNormalizer::date( '20250102' ),
    [ 3, 4 ] === ValueNormalizer::ids( [ [ 'ID' => 3 ], (object) [ 'ID' => 4 ], 3 ] ),
    [ 'One', 'Two' ] === ValueNormalizer::strings( [ [ 'name' => 'One' ], [ 'label' => 'Two' ] ], [ 'name', 'label' ] ),
    [ '<b>One</b>', '<b>One</b>' ] === ValueNormalizer::row_values( [ [ 'name' => '<b>One</b>' ], [ 'name' => '0' ], [ 'name' => '<b>One</b>' ] ] ),
    [ 'https://one.example.test/', 'https://two.example.test/path' ] === ValueNormalizer::url_values( [ [ 'https://one.example.test/' ], "https://two.example.test/path\ninvalid" ] ),
    'Only' === ValueNormalizer::single_or_array( [ '', 'Only' ] ),
    'ACF value' === $reader->first( 'missing', 'primary' ),
    'Post fallback' === $reader->read( 'fallback' ),
    0 === FieldReader::acf_value( 'zero', false, 'fallback' ),
    'fallback' === FieldReader::acf_value( 'zero', false, 'fallback', true ),
    [] === FieldReader::acf_value( 'empty_array', false, 'fallback' ),
    'https://example.test/7-thumbnail.jpg' === $image['url'] && 'Alt text' === $image['alt'],
    'https://example.test/8-full.jpg' === $fallback_image['url'],
    2 === count( $loose_gallery )
        && 'Custom alt' === $loose_gallery[0]['alt']
        && 'plain-file.jpg' === $loose_gallery[1]['title'],
    2 === count( $gallery_records )
        && [ 'id', 'url', 'full_url', 'alt', 'title', 'caption' ] === array_keys( $gallery_records[0] )
        && 'First' === $gallery_records[0]['title']
        && 'plain-record.jpg' === $gallery_records[1]['title'],
    [] === MediaNormalizer::gallery_records( 7 ),
    'ImageObject' === MediaNormalizer::schema_image( $image, 'https://example.test/#image' )['@type'],
];
if ( in_array( false, $checks, true ) ) {
    fwrite( STDERR, "FAIL: DataNormalization compatibility contract failed.\n" );
    exit( 1 );
}
echo "PASS: DataNormalization provides reusable nested URL, ACF value, row, and loose media normalization.\n";
