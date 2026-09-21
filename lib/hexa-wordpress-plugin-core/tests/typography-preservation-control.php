<?php

declare(strict_types=1);

function sanitize_key( mixed $value ): string {
    return preg_replace( "/[^a-z0-9_\-]/", "", strtolower( (string) $value ) ) ?: "";
}
function sanitize_html_class( mixed $value ): string {
    return preg_replace( "/[^a-zA-Z0-9_\-]/", "", (string) $value ) ?: "";
}
function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, "UTF-8" );
}
function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, "UTF-8" );
}

$root = dirname( __DIR__ );
require $root . "/src/Typography/TypographyPreservation.php";
require $root . "/src/WpAdminComponents/CoreUi.php";
require $root . "/src/WpAdminComponents/TypographyPreservationControl.php";

use Hexa\PluginCore\Typography\TypographyPreservation;
use Hexa\PluginCore\WpAdminComponents\TypographyPreservationControl;

function typography_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: " . $message . PHP_EOL );
        exit( 1 );
    }
}

$defaults = TypographyPreservation::defaults( "article_heading", true );
typography_assert( 4 === count( $defaults ), "Core must generate all four preservation defaults." );
typography_assert( true === $defaults["article_heading_preserve_font_family"], "Generated keys must be prefix-scoped." );
typography_assert( "hpc-typography-article-heading-preserve-font-weight" === TypographyPreservation::state_class( "article_heading", "font_weight" ), "Core must generate stable preview-state classes." );

$markup = TypographyPreservationControl::render(
    [
        "prefix" => "article_heading",
        "settings" => $defaults,
        "targets" => [
            "font_family" => [ "article_heading_font_family" ],
            "font_size" => [ "article_heading_h2_font_size", "article_heading_h3_font_size" ],
            "font_weight" => [ "article_heading_font_weight" ],
        ],
        "input_class" => "host-save-setting",
    ]
);

foreach ( [ "font_family", "font_size", "font_color", "font_weight" ] as $property ) {
    typography_assert( str_contains( $markup, 'data-hpc-typography-property="' . $property . '"' ), "Core markup must include " . $property . "." );
}
typography_assert( str_contains( $markup, 'data-key="article_heading_preserve_font_family"' ), "Host save keys must be exposed without host-specific rendering." );
typography_assert( str_contains( $markup, 'data-hpc-typography-targets="article_heading_h2_font_size,article_heading_h3_font_size"' ), "Target controls must be declared generically." );
typography_assert( str_contains( $markup, "Use site font" ) && str_contains( $markup, "Use site text size" ) && str_contains( $markup, "Use site text color" ) && str_contains( $markup, "Use site font weight" ), "Inheritance toggles must use clear site-value wording." );
typography_assert( str_contains( $markup, "hexaTypographyPreservationReady" ), "Core must own preservation synchronization JavaScript." );
$source = (string) file_get_contents( $root . "/src/WpAdminComponents/TypographyPreservationControl.php" );
typography_assert( str_contains( $source, '[data-hpc-color-control][data-key="' ) && str_contains( $source, '[data-hpc-color-picker],[data-hpc-color-hex-input],[data-hpc-color-value-input],[data-hpc-brand-color-import],[data-hpc-color-inherit]' ), "Preserved colors must disable the complete Core color editor." );
typography_assert( str_contains( $source, '.hpc-color-row,.hpc-font-family-field,.hpc-typography-number-control,.hpc-typography-style-field' ) && str_contains( $source, 'hpc-typography-value-inherited' ), "Inherited editors must share one visible disabled-state treatment." );
typography_assert( ! str_contains( $markup, "smpi-" ), "Core preservation markup must remain host-neutral." );

echo "PASS: Core typography preservation settings are reusable, prefix-scoped, and host-neutral." . PHP_EOL;
