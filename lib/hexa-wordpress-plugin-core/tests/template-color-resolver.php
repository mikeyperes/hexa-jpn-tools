<?php

declare(strict_types=1);

function get_option( string $key, mixed $default = false ): mixed {
    return [
        "hws_brand_primary_color" => "#123456",
        "hws_brand_secondary_color" => "#654321",
    ][ $key ] ?? $default;
}

$root = dirname( __DIR__ );
require $root . "/src/BrandColors/BrandColorProvider.php";
require $root . "/src/BrandColors/TemplateColorResolver.php";

use Hexa\PluginCore\BrandColors\TemplateColorResolver;

function template_color_resolver_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: " . $message . PHP_EOL );
        exit( 1 );
    }
}

$palettes = [
    "panel" => [
        "background" => "#eff4ff",
        "accent" => "#2563eb",
        "invalid" => "not-a-color",
    ],
];
$variables = [
    "--host-accent" => "color",
    "--host-soft" => "rgba:0.1",
    "--host-ink" => "contrast",
    "color" => "color",
];

template_color_resolver_assert(
    "#2563eb" === TemplateColorResolver::effective_base( TemplateColorResolver::TEMPLATE_DEFAULT, "panel", $palettes ),
    "The explicit accent role must win over palette insertion order."
);
template_color_resolver_assert(
    "#2563eb" === TemplateColorResolver::effective_base( TemplateColorResolver::CUSTOM, "panel", $palettes, "" ),
    "Blank Custom must fall back to the selected template accent."
);
template_color_resolver_assert(
    "#abcdef" === TemplateColorResolver::effective_base( TemplateColorResolver::CUSTOM, "panel", $palettes, "#abcdef" ),
    "Custom must use an explicit valid color."
);
template_color_resolver_assert(
    "#123456" === TemplateColorResolver::effective_base( TemplateColorResolver::SITE_PRIMARY, "panel", $palettes ),
    "Site Primary must resolve through the shared brand provider."
);
template_color_resolver_assert(
    "#654321" === TemplateColorResolver::effective_base( TemplateColorResolver::SITE_SECONDARY, "panel", $palettes ),
    "Site Secondary must resolve through the shared brand provider."
);
template_color_resolver_assert(
    [] === TemplateColorResolver::css_variables( TemplateColorResolver::TEMPLATE_DEFAULT, "panel", $palettes, "", $variables ),
    "Template Default must not emit overrides."
);

$resolved = TemplateColorResolver::css_variables( TemplateColorResolver::CUSTOM, "panel", $palettes, "#ffffff", $variables );
template_color_resolver_assert(
    [
        "--host-accent" => "#ffffff",
        "--host-soft" => "rgba(255,255,255,0.1)",
        "--host-ink" => "#111111",
    ] === $resolved,
    "Custom must transform only valid mapped variables."
);

echo "PASS: TemplateColorResolver resolves four sources, explicit accents, and mapped transforms.\n";
