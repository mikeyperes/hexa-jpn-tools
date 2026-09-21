<?php

declare(strict_types=1);

$root = dirname( __DIR__ );
require $root . "/src/Typography/TypographyPreservation.php";
require $root . "/src/Typography/TemplateTypography.php";

use Hexa\PluginCore\Typography\TemplateTypography;

function template_typography_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: " . $message . PHP_EOL );
        exit( 1 );
    }
}

$options = TemplateTypography::options();
template_typography_assert(
    [ TemplateTypography::TEMPLATE_DEFAULT, TemplateTypography::SITE_INHERIT, TemplateTypography::CUSTOM ] === array_keys( $options ),
    "Core must expose exactly Original Template, Use Site Typography, and Custom Typography."
);
template_typography_assert(
    "Original Template" === $options[ TemplateTypography::TEMPLATE_DEFAULT ]["label"]
        && "Use Site Typography" === $options[ TemplateTypography::SITE_INHERIT ]["label"]
        && "Custom Typography" === $options[ TemplateTypography::CUSTOM ]["label"],
    "Typography choices must use the shared plain-language labels."
);
template_typography_assert(
    TemplateTypography::TEMPLATE_DEFAULT === TemplateTypography::normalize_mode( "unknown" ),
    "Unknown modes must be non-destructive Template Default."
);
template_typography_assert(
    "article_heading_typography_mode" === TemplateTypography::setting_key( "article_heading" ),
    "Mode keys must be stable and prefix-scoped."
);

$settings = [
    "article_heading_typography_mode" => TemplateTypography::TEMPLATE_DEFAULT,
    "article_heading_preserve_font_family" => true,
];
template_typography_assert(
    ! in_array( true, TemplateTypography::preservation_values( $settings, "article_heading", true ), true ),
    "Template Default must retain template-owned typography."
);
$settings["article_heading_typography_mode"] = TemplateTypography::SITE_INHERIT;
template_typography_assert(
    ! in_array( false, TemplateTypography::preservation_values( $settings, "article_heading", false ), true ),
    "Site Typography must inherit every typography property."
);
$settings["article_heading_typography_mode"] = TemplateTypography::CUSTOM;
$custom = TemplateTypography::preservation_values( $settings, "article_heading", false );
template_typography_assert(
    true === $custom["font_family"] && false === $custom["font_size"],
    "Custom Typography must honor per-property preservation values."
);
template_typography_assert(
    "hpc-typography-article-heading-mode-site-inherit" === TemplateTypography::mode_state_class( "article_heading", TemplateTypography::SITE_INHERIT )
        && "hpc-typography-article-heading-custom-font-color" === TemplateTypography::custom_property_state_class( "article_heading", "font_color" ),
    "Preview state classes must be stable and host-neutral."
);

echo "PASS: TemplateTypography enforces the three shared modes and preservation semantics.\n";
