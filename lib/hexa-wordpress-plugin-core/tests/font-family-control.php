<?php

declare(strict_types=1);

$GLOBALS["hpc_test_elementor_settings"] = [
    "system_typography" => [
        [ "_id" => "primary", "title" => "Primary", "typography_font_family" => "Roboto" ],
        [ "_id" => "secondary", "title" => "Secondary", "typography_font_family" => "Playfair Display" ],
        [ "_id" => "text", "title" => "Text", "typography_font_family" => "Roboto" ],
    ],
    "custom_typography" => [
        [ "_id" => "brand_body", "title" => "Brand Body", "typography_font_family" => "Inter" ],
        [ "_id" => "brand_body_copy", "title" => "Brand Body Copy", "typography_font_family" => "Inter" ],
        [ "_id" => "unsafe", "title" => "Unsafe", "typography_font_family" => "Bad; color: red" ],
    ],
];

function get_option( string $key, mixed $default = false ): mixed {
    return "elementor_active_kit" === $key ? 42 : $default;
}

function get_post_meta( int $post_id, string $key, bool $single = false ): mixed {
    return 42 === $post_id && "_elementor_page_settings" === $key
        ? $GLOBALS["hpc_test_elementor_settings"]
        : ( $single ? "" : [] );
}

function absint( mixed $value ): int {
    return abs( (int) $value );
}

function sanitize_key( string $value ): string {
    return preg_replace( "/[^a-z0-9_\-]/", "", strtolower( $value ) ) ?: "";
}

function sanitize_text_field( string $value ): string {
    return trim( strip_tags( $value ) );
}

function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

$root = dirname( __DIR__ );
require $root . "/src/BrandColors/BrandColorProvider.php";
require $root . "/src/BrandColors/FontFamilyProvider.php";
require $root . "/src/BrandColors/FontWeightProvider.php";
require $root . "/src/WpAdminComponents/FontFamilyControl.php";

use Hexa\PluginCore\BrandColors\FontFamilyProvider;
use Hexa\PluginCore\BrandColors\FontWeightProvider;
use Hexa\PluginCore\WpAdminComponents\FontFamilyControl;

$options = FontFamilyProvider::options();
$markup = FontFamilyControl::render(
    [
        "key"          => "heading_font",
        "label"        => "Heading font",
        "value"        => "elementor_brand_body",
        "select_class" => "host-font-setting",
        "weight_key" => "heading_font_weight",
        "weight_value" => "700",
        "weight_select_class" => "host-weight-setting",
    ]
);
$family_only_markup = FontFamilyControl::render(
    [
        "key" => "body_font",
        "value" => "native_primary",
    ]
);

$checks = [
    "Template remains the non-destructive default." => isset( $options["template"] ) && "" === $options["template"]["css"],
    "Native primary resolves through Elementor's global CSS token." => isset( $options["native_primary"] )
        && "primary" === $options["native_primary"]["source_id"]
        && 'var(--e-global-typography-primary-font-family, "Roboto")' === $options["native_primary"]["css"],
    "Native secondary exposes its current family." => isset( $options["native_secondary"] )
        && "Playfair Display" === $options["native_secondary"]["family"],
    "Elementor choices persist source identifiers instead of arbitrary CSS." => isset( $options["elementor_brand_body"] )
        && "brand_body" === $options["elementor_brand_body"]["source_id"]
        && str_contains( $options["elementor_brand_body"]["css"], "--e-global-typography-brand_body-font-family" ),
    "Duplicate Elementor families are listed once." => ! isset( $options["elementor_brand_body_copy"] ),
    "Unsafe Elementor font values are excluded." => ! isset( $options["elementor_unsafe"] ),
    "Unknown selections fall back to template behavior." => "template" === FontFamilyProvider::normalize_selection( "url-javascript" )
        && "" === FontFamilyProvider::css_value( "url-javascript" ),
    "Font weights normalize to a bounded reusable option set." => "400" === FontWeightProvider::normalize_selection( "normal" )
        && "700" === FontWeightProvider::normalize_selection( "bold" )
        && "" === FontWeightProvider::css_value( "inherit" )
        && "inherit" === FontWeightProvider::normalize_selection( "950" ),
    "The shared control renders grouped, host-saveable options." => str_contains( $markup, 'data-hpc-font-family-control' )
        && str_contains( $markup, 'class="hpc-font-family-select host-font-setting"' )
        && str_contains( $markup, 'data-source-id="brand_body"' )
        && preg_match( '/value="elementor_brand_body"[^>]* selected/s', $markup ),
    "The shared control renders the requested host-saveable weight selector." => str_contains( $markup, 'data-hpc-font-weight-select' )
        && str_contains( $markup, 'class="hpc-font-weight-select host-weight-setting"' )
        && str_contains( $markup, 'data-key="heading_font_weight"' )
        && preg_match( '/value="700"[^>]* selected/s', $markup ),
    "Existing hosts remain family-only until they supply a weight key." => ! str_contains( $family_only_markup, 'data-hpc-font-weight-select' ),
    "The default control avoids duplicate selected-value reporting." => ! str_contains( $markup, '<span class="hpc-font-family-current"' ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: " . $message . "\n" );
        exit( 1 );
    }
}

echo "PASS: FontFamilyControl safely renders validated Elementor font sources and optional weights.\n";
