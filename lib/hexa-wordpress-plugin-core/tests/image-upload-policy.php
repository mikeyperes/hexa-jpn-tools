<?php

declare(strict_types=1);

$root = dirname( __DIR__ );
require $root . '/src/MediaUploads/ImageUploadPolicy.php';

use Hexa\PluginCore\MediaUploads\ImageUploadPolicy;

function image_policy_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

$policy = new ImageUploadPolicy( 1024 );
$valid = $policy->validate(
    [
        'name' => 'portrait.jpg',
        'type' => 'image/jpeg',
        'tmp_name' => '/not-readable',
        'error' => UPLOAD_ERR_OK,
        'size' => 512,
    ]
);
$wrong_extension = $policy->validate(
    [
        'name' => 'portrait.png',
        'type' => 'image/jpeg',
        'tmp_name' => '/not-readable',
        'error' => UPLOAD_ERR_OK,
        'size' => 512,
    ]
);
$oversized = $policy->validate(
    [
        'name' => 'portrait.webp',
        'type' => 'image/webp',
        'tmp_name' => '/not-readable',
        'error' => UPLOAD_ERR_OK,
        'size' => 2048,
    ]
);

image_policy_assert( $valid['valid'], 'A bounded JPEG upload must pass.' );
image_policy_assert( ! $wrong_extension['valid'], 'A mismatched image extension must fail.' );
image_policy_assert( ! $oversized['valid'], 'An oversized image must fail.' );
image_policy_assert( 'image/jpeg,image/png,image/webp' === $policy->accept_attribute(), 'The reusable accept list must stay explicit.' );

echo "PASS: ImageUploadPolicy enforces reusable MIME, extension, and size boundaries.\n";
