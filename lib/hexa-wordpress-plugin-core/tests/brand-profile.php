<?php

declare(strict_types=1);

$root = dirname( __DIR__ );
require $root . '/src/BrandColors/BrandColorProvider.php';
require $root . '/src/BrandProfiles/BrandProfile.php';

use Hexa\PluginCore\BrandProfiles\BrandProfile;

function brand_profile_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

$profile = BrandProfile::from_array(
    [
        'host' => 'Orders.Example.com:443',
        'name' => '<b>Example Services</b>',
        'logo_url' => 'https://cdn.example.com/logo.svg',
        'primary_color' => '#123',
        'accent_color' => 'ef8354',
        'support_email' => 'Help@Example.com',
    ]
);
$values = $profile->to_array();

brand_profile_assert( 'orders.example.com' === $values['host'], 'Hosts must be canonical and port-free.' );
brand_profile_assert( 'Example Services' === $values['name'], 'Brand names must be plain text.' );
brand_profile_assert( '#112233' === $values['primary_color'] && '#ef8354' === $values['accent_color'], 'Brand colors must be normalized.' );
brand_profile_assert( 'help@example.com' === $values['support_email'], 'Support email must be normalized.' );
brand_profile_assert( '#112233' === $profile->css_variables()['--hexa-brand-primary'], 'CSS variables must use the normalized brand.' );

echo "PASS: BrandProfile normalizes reusable front-end identities.\n";
