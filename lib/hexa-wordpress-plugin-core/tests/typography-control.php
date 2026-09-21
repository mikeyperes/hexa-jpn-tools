<?php

declare(strict_types=1);

$GLOBALS["hpc_typography_elementor_settings"] = [
    "system_typography" => [
        [ "_id" => "primary", "title" => "Primary", "typography_font_family" => "Roboto" ],
        [ "_id" => "secondary", "title" => "Secondary", "typography_font_family" => "Playfair Display" ],
    ],
];

function get_option( string $key, mixed $default = false ): mixed {
    return "elementor_active_kit" === $key ? 42 : $default;
}
function get_post_meta( int $post_id, string $key, bool $single = false ): mixed {
    return 42 === $post_id && "_elementor_page_settings" === $key
        ? $GLOBALS["hpc_typography_elementor_settings"]
        : ( $single ? "" : [] );
}
function absint( mixed $value ): int {
    return abs( (int) $value );
}
function sanitize_key( mixed $value ): string {
    return preg_replace( "/[^a-z0-9_\-]/", "", strtolower( (string) $value ) ) ?: "";
}
function sanitize_html_class( mixed $value ): string {
    return preg_replace( "/[^a-zA-Z0-9_\-]/", "", (string) $value ) ?: "";
}
function sanitize_text_field( string $value ): string {
    return trim( strip_tags( $value ) );
}
function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, "UTF-8" );
}
function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, "UTF-8" );
}
function selected( mixed $selected, mixed $current = true, bool $display = true ): string {
    $result = $selected == $current ? ' selected="selected"' : '';
    if ( $display ) {
        echo $result;
    }
    return $result;
}

$root = dirname( __DIR__ );
require $root . "/src/BrandColors/BrandColorProvider.php";
require $root . "/src/BrandColors/FontFamilyProvider.php";
require $root . "/src/BrandColors/FontWeightProvider.php";
require $root . "/src/Typography/TypographyPreservation.php";
require $root . "/src/Typography/TemplateTypography.php";
require $root . "/src/WpAdminComponents/CoreUi.php";
require $root . "/src/WpAdminComponents/ColorControl.php";
require $root . "/src/WpAdminComponents/FontFamilyControl.php";
require $root . "/src/WpAdminComponents/TypographyPreservationControl.php";
require $root . "/src/WpAdminComponents/TypographyControl.php";

use Hexa\PluginCore\WpAdminComponents\TypographyControl;

function typography_control_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: " . $message . PHP_EOL );
        exit( 1 );
    }
}

$markup = TypographyControl::render(
    [
        "prefix" => "article_heading",
        "settings" => [
            "article_heading_font_family" => "native_secondary",
            "article_heading_font_weight" => "700",
            "article_heading_color" => "#942929",
            "article_heading_h2_size" => 28,
            "article_heading_h3_size" => 22,
            "article_heading_font_style" => "italic",
            "article_heading_preserve_font_family" => true,
        ],
        "defaults" => false,
        "input_class" => "host-save-setting",
        "font_family" => [
            "key" => "article_heading_font_family",
            "label" => "Heading font",
        ],
        "font_weight" => [
            "key" => "article_heading_font_weight",
            "label" => "Heading weight",
        ],
        "font_color" => [
            "key" => "article_heading_color",
            "label" => "Heading color",
            "default" => "#942929",
            "disable_when_preserved" => false,
        ],
        "font_size" => [
            [ "key" => "article_heading_h2_size", "label" => "H2 size", "min" => 8, "max" => 64, "suffix" => "px" ],
            [ "key" => "article_heading_h3_size", "label" => "H3 size", "min" => 8, "max" => 64, "suffix" => "px" ],
        ],
        "font_style" => [
            "key" => "article_heading_font_style",
            "label" => "Heading style",
        ],
    ]
);

typography_control_assert( str_contains( $markup, 'data-hpc-typography-control' ), "Core must own the combined typography control." );
typography_control_assert( str_contains( $markup, 'data-hpc-font-family-select' ) && str_contains( $markup, 'data-hpc-font-weight-select' ), "The combined control must use Core font and weight pickers." );
typography_control_assert( preg_match( '/data-hpc-font-family-select.*data-hpc-typography-property="font_family"/s', $markup ) === 1, "The family preservation toggle must sit after the family picker." );
typography_control_assert( preg_match( '/data-hpc-font-weight-select.*data-hpc-typography-property="font_weight"/s', $markup ) === 1, "The weight preservation toggle must sit after the weight picker." );
typography_control_assert( preg_match( '/data-hpc-color-control.*data-hpc-typography-property="font_color"/s', $markup ) === 1, "The color preservation toggle must sit beside the Core color picker." );
typography_control_assert( preg_match( '/hpc-color-head.*hpc-color-head-action.*data-hpc-typography-property="font_color"/s', $markup ) === 1, "The color preservation toggle must remain attached to the color heading at narrow widths." );
typography_control_assert( str_contains( $markup, 'data-hpc-typography-targets=""' ), "Decorative accent controls may stay active while text color is preserved." );
typography_control_assert( str_contains( $markup, 'data-hpc-typography-targets="article_heading_h2_size,article_heading_h3_size"' ), "One size toggle must own every configured size field." );
typography_control_assert( str_contains( $markup, 'data-hpc-typography-targets="article_heading_font_family,article_heading_font_style"' ), "Using the site font must also disable every font-style field governed by that setting." );
typography_control_assert( str_contains( $markup, '.hpc-typography-control-action{align-items:center;display:flex;grid-row:1;justify-content:flex-start' ) && str_contains( $markup, '.hpc-font-family-field-action{grid-row:1;justify-content:flex-start' ), "Every inheritance toggle must align at the left before its value editor." );
typography_control_assert( 2 === substr_count( $markup, 'class="hpc-typography-number-input host-save-setting"' ), "Core must render both host-saveable size fields." );
typography_control_assert( ! str_contains( (string) file_get_contents( $root . "/src/WpAdminComponents/TypographyControl.php" ), "smpi-" ), "The combined typography component must remain host-neutral." );

echo "PASS: TypographyControl pairs each Core field with its own preservation toggle." . PHP_EOL;
