<?php

namespace Hexa\PluginCore\BrandColors;

final class TemplateColorResolver {
    public const TEMPLATE_DEFAULT = "template_default";
    public const SITE_PRIMARY = "site_primary";
    public const SITE_SECONDARY = "site_secondary";
    public const CUSTOM = "custom";

    public static function source_options(): array {
        return [
            self::TEMPLATE_DEFAULT => [
                "label" => "Original Template Color",
                "description" => "Keep the selected design's original mapped color.",
            ],
            self::SITE_PRIMARY => [
                "label" => "Site Primary Color",
                "description" => "Use the primary color from HWS Brand Assets.",
            ],
            self::SITE_SECONDARY => [
                "label" => "Site Secondary Color",
                "description" => "Use the secondary color from HWS Brand Assets.",
            ],
            self::CUSTOM => [
                "label" => "Custom Design Color",
                "description" => "Use a custom hex color for the design's mapped accents.",
            ],
        ];
    }

    public static function normalize_source( string $source ): string {
        $source = self::clean_key( $source );
        return array_key_exists( $source, self::source_options() ) ? $source : self::TEMPLATE_DEFAULT;
    }

    public static function template_palette( string $template, array $palettes, string $fallback = "#2d5277" ): array {
        $template = self::clean_key( $template );
        $raw = isset( $palettes[ $template ] ) && is_array( $palettes[ $template ] )
            ? $palettes[ $template ]
            : ( isset( $palettes["*"] ) && is_array( $palettes["*"] ) ? $palettes["*"] : [] );
        $palette = [];
        foreach ( $raw as $role => $color ) {
            $role = self::clean_key( (string) $role );
            $color = self::optional_hex( (string) $color );
            if ( "" !== $role && "" !== $color ) {
                $palette[ $role ] = $color;
            }
        }
        if ( [] === $palette ) {
            $palette["accent"] = BrandColorProvider::normalize_hex( $fallback, "#2d5277" );
        }

        return $palette;
    }

    public static function effective_base(
        string $source,
        string $template,
        array $palettes,
        string $custom = "",
        string $fallback = "#2d5277",
        string $primary = "",
        string $secondary = ""
    ): string {
        $source = self::normalize_source( $source );
        $fallback = BrandColorProvider::normalize_hex( $fallback, "#2d5277" );
        if ( self::SITE_PRIMARY === $source ) {
            return "" !== self::optional_hex( $primary )
                ? self::optional_hex( $primary )
                : BrandColorProvider::primary_color( $fallback );
        }
        if ( self::SITE_SECONDARY === $source ) {
            return "" !== self::optional_hex( $secondary )
                ? self::optional_hex( $secondary )
                : BrandColorProvider::secondary_color( "#111827" );
        }
        if ( self::CUSTOM === $source ) {
            $custom = self::optional_hex( $custom );
            if ( "" !== $custom ) {
                return $custom;
            }

            $palette = self::template_palette( $template, $palettes, $fallback );
            return self::base_color( $palette );
        }

        $palette = self::template_palette( $template, $palettes, $fallback );
        return self::base_color( $palette );
    }

    public static function css_variables(
        string $source,
        string $template,
        array $palettes,
        string $custom,
        array $variables,
        string $fallback = "#2d5277",
        string $primary = "",
        string $secondary = ""
    ): array {
        $source = self::normalize_source( $source );
        if ( self::TEMPLATE_DEFAULT === $source ) {
            return [];
        }

        $base = self::effective_base( $source, $template, $palettes, $custom, $fallback, $primary, $secondary );
        $resolved = [];
        foreach ( $variables as $variable => $transform ) {
            $variable = trim( (string) $variable );
            if ( ! preg_match( "/^--[a-z0-9_-]+$/", $variable ) ) {
                continue;
            }
            $value = self::transform( $base, (string) $transform );
            if ( "" !== $value ) {
                $resolved[ $variable ] = $value;
            }
        }

        return $resolved;
    }

    public static function transform( string $color, string $transform = "color" ): string {
        $color = BrandColorProvider::normalize_hex( $color, "#2d5277" );
        $transform = strtolower( trim( $transform ) );
        if ( "" === $transform || "color" === $transform ) {
            return $color;
        }
        if ( "contrast" === $transform ) {
            return self::contrast_ink( $color );
        }
        if ( 0 === strpos( $transform, "rgba:" ) ) {
            return self::rgba( $color, (float) substr( $transform, 5 ) );
        }

        return "";
    }

    public static function rgba( string $color, float $alpha ): string {
        $rgb = BrandColorProvider::rgb_array( $color );
        $alpha = max( 0, min( 1, $alpha ) );
        $alpha_text = rtrim( rtrim( number_format( $alpha, 3, ".", "" ), "0" ), "." );
        if ( "" === $alpha_text ) {
            $alpha_text = "0";
        }
        return "rgba(" . $rgb["r"] . "," . $rgb["g"] . "," . $rgb["b"] . "," . $alpha_text . ")";
    }

    public static function contrast_ink( string $color ): string {
        $rgb = BrandColorProvider::rgb_array( $color );
        $luma = ( $rgb["r"] * 299 + $rgb["g"] * 587 + $rgb["b"] * 114 ) / 1000;
        return $luma >= 150 ? "#111111" : "#ffffff";
    }

    private static function optional_hex( string $value ): string {
        $value = strtolower( trim( $value ) );
        if ( "" === $value ) {
            return "";
        }
        if ( "#" !== substr( $value, 0, 1 ) ) {
            $value = "#" . $value;
        }
        if ( preg_match( "/^#[0-9a-f]{3}$/", $value ) ) {
            $value = "#" . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3];
        }
        return preg_match( "/^#[0-9a-f]{6}$/", $value ) ? $value : "";
    }

    private static function base_color( array $palette ): string {
        return isset( $palette["accent"] ) ? (string) $palette["accent"] : (string) reset( $palette );
    }

    private static function clean_key( string $value ): string {
        if ( function_exists( "sanitize_key" ) ) {
            return sanitize_key( $value );
        }
        return preg_replace( "/[^a-z0-9_\-]/", "", strtolower( $value ) ) ?: "";
    }
}
