<?php

declare(strict_types=1);

function get_option( string $key, mixed $default = false ): mixed {
    return [
        "hws_brand_primary_color" => "#123456",
        "hws_brand_secondary_color" => "#654321",
    ][ $key ] ?? $default;
}
function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, "UTF-8" );
}
function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, "UTF-8" );
}
function checked( mixed $checked, mixed $current = true, bool $display = true ): string {
    $result = $checked == $current ? ' checked="checked"' : '';
    if ( $display ) {
        echo $result;
    }
    return $result;
}

$root = dirname( __DIR__ );
require $root . "/src/BrandColors/BrandColorProvider.php";
require $root . "/src/BrandColors/TemplateColorResolver.php";
require $root . "/src/WpAdminComponents/CoreUi.php";
require $root . "/src/WpAdminComponents/ColorControl.php";
require $root . "/src/WpAdminComponents/TemplateColorControl.php";

use Hexa\PluginCore\BrandColors\TemplateColorResolver;
use Hexa\PluginCore\WpAdminComponents\TemplateColorControl;

function template_color_control_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: " . $message . PHP_EOL );
        exit( 1 );
    }
}

$markup = TemplateColorControl::render(
    [
        "source_key" => "summary_color_source",
        "custom_key" => "summary_color",
        "template_key" => "summary_template",
        "template" => "panel",
        "source" => TemplateColorResolver::CUSTOM,
        "custom" => "",
        "palettes" => [
            "panel" => [ "background" => "#eff4ff", "accent" => "#2563eb" ],
        ],
        "variables" => [ "--summary-accent" => "color", "--summary-soft" => "rgba:0.1" ],
        "input_class" => "host-setting",
    ]
);

preg_match_all( '/<input[^>]+data-hpc-template-color-source-input/s', $markup, $source_inputs );
template_color_control_assert( 4 === count( $source_inputs[0] ), "The control must render all four source modes." );
template_color_control_assert( str_contains( $markup, '>Original Template Color</strong>' ) && str_contains( $markup, '>Site Primary Color</strong>' ) && str_contains( $markup, '>Site Secondary Color</strong>' ) && str_contains( $markup, '>Custom Design Color</strong>' ), "The source labels must clearly distinguish template, site, and custom design colors." );
template_color_control_assert( preg_match( '/data-hpc-template-color-option-swatch="template_default" style="background:#2563eb"/', $markup ) === 1, "The native swatch must prefer the explicit accent role." );
template_color_control_assert( str_contains( $markup, 'data-hpc-inherited-value="#2563eb"' ) && str_contains( $markup, 'data-hpc-color-inherited="true"' ), "Blank Custom must display the template accent without persisting it." );
template_color_control_assert( str_contains( $markup, 'class="hpc-color-value-input hpc-template-color-custom-value host-setting"' ), "The nested picker must use the host save hook." );
template_color_control_assert( str_contains( $markup, 'value=palette.accent||' ), "Live previews must use the explicit accent role instead of palette order." );
template_color_control_assert( str_contains( $markup, 'var DEFAULT="template_default",PRIMARY="site_primary",SECONDARY="site_secondary",CUSTOM="custom"' ), "The UI must share the resolver's four modes." );
template_color_control_assert( str_contains( $markup, 'var value=mode===DEFAULT?"":transform' ), "Template Default previews must remove mapped overrides." );
template_color_control_assert( str_contains( $markup, 'var explicit=hex(color);if(!explicit)syncCustomDisplay(control);color=explicit||base(control,mode)' ), "Explicit picker events must resolve before fallback synchronization so an older stored value cannot replace the selected color." );
template_color_control_assert( ! str_contains( $markup, 'syncCustomDisplay(control);color=hex(color)||base(control,mode)' ), "The template control must not overwrite an explicit picker event from stale nested storage." );
template_color_control_assert( ! str_contains( (string) file_get_contents( $root . "/src/WpAdminComponents/TemplateColorControl.php" ), "smpi-" ), "The generic control must remain host-neutral." );

echo "PASS: TemplateColorControl renders the shared four-mode UI and preserves explicit picker events.\n";
