<?php

namespace Hexa\PluginCore\BrandColors;

final class BrandColorProvider {
    public const HWS_PRIMARY_OPTION = "hws_brand_primary_color";
    public const HWS_SECONDARY_OPTION = "hws_brand_secondary_color";

    public static function primary_color( string $fallback = "#2d5277" ): string {
        return self::hws_color( self::HWS_PRIMARY_OPTION, $fallback );
    }

    public static function secondary_color( string $fallback = "#111827" ): string {
        return self::hws_color( self::HWS_SECONDARY_OPTION, $fallback );
    }

    public static function payload( string $fallback = "#2d5277" ): array {
        $primary = self::primary_color( $fallback );
        $secondary = self::secondary_color( "#111827" );

        return [
            "source" => "hws-base-tools",
            "source_label" => "HWS Base Tools Brand Assets",
            "primary_color" => $primary,
            "primary_rgb" => self::rgb_string( $primary ),
            "secondary_color" => $secondary,
            "secondary_rgb" => self::rgb_string( $secondary ),
            "admin_url" => self::brand_assets_admin_url(),
        ];
    }

    public static function elementor_payload( string $fallback_primary = "#2d5277", string $fallback_secondary = "#111827" ): array {
        $colors = self::elementor_colors( $fallback_primary, $fallback_secondary );
        $fonts = self::elementor_fonts();

        return [
            "source" => "elementor",
            "source_label" => "Elementor Site Settings",
            "primary_color" => $colors["primary_color"] ?? "",
            "primary_rgb" => ! empty( $colors["primary_color"] ) ? self::rgb_string( $colors["primary_color"] ) : "",
            "secondary_color" => $colors["secondary_color"] ?? "",
            "secondary_rgb" => ! empty( $colors["secondary_color"] ) ? self::rgb_string( $colors["secondary_color"] ) : "",
            "text_color" => $colors["text_color"] ?? "",
            "accent_color" => $colors["accent_color"] ?? "",
            "primary_font_family" => $fonts["primary_font_family"] ?? "",
            "secondary_font_family" => $fonts["secondary_font_family"] ?? "",
            "text_font_family" => $fonts["text_font_family"] ?? "",
            "accent_font_family" => $fonts["accent_font_family"] ?? "",
        ];
    }

    public static function normalize_hex( string $value, string $fallback = "#000000" ): string {
        $value = trim( strtolower( $value ) );
        if ( "" !== $value && "#" !== $value[0] ) {
            $value = "#" . $value;
        }

        if ( preg_match( "/^#[0-9a-f]{3}$/", $value ) ) {
            $value = "#" . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3];
        }

        if ( function_exists( "sanitize_hex_color" ) ) {
            $sanitized = sanitize_hex_color( $value );
            if ( is_string( $sanitized ) && preg_match( "/^#[0-9a-f]{6}$/", strtolower( $sanitized ) ) ) {
                return strtolower( $sanitized );
            }
        }

        if ( preg_match( "/^#[0-9a-f]{6}$/", $value ) ) {
            return $value;
        }

        if ( $fallback !== $value ) {
            return self::normalize_hex( $fallback, "#000000" );
        }

        return "#000000";
    }

    public static function rgb_array( string $hex ): array {
        $hex = self::normalize_hex( $hex );

        return [
            "r" => hexdec( substr( $hex, 1, 2 ) ),
            "g" => hexdec( substr( $hex, 3, 2 ) ),
            "b" => hexdec( substr( $hex, 5, 2 ) ),
        ];
    }

    public static function rgb_string( string $hex ): string {
        $rgb = self::rgb_array( $hex );
        return "rgb(" . $rgb["r"] . ", " . $rgb["g"] . ", " . $rgb["b"] . ")";
    }

    public static function elementor_colors( string $fallback_primary = "#2d5277", string $fallback_secondary = "#111827" ): array {
        $settings = self::elementor_settings();
        if ( empty( $settings ) ) {
            return [];
        }

        $flat = [];
        foreach ( [ "system_colors", "custom_colors" ] as $group ) {
            foreach ( (array) ( $settings[ $group ] ?? [] ) as $color ) {
                if ( ! is_array( $color ) || empty( $color["_id"] ) || empty( $color["color"] ) ) {
                    continue;
                }
                $hex = self::normalize_hex( (string) $color["color"], "" );
                if ( "#000000" === $hex && ! preg_match( "/^#?0{6}$/", (string) $color["color"] ) ) {
                    continue;
                }
                $flat[ sanitize_key( (string) $color["_id"] ) ] = $hex;
            }
        }

        return array_filter(
            [
                "primary_color" => $flat["primary"] ?? $flat["accent"] ?? self::normalize_hex( $fallback_primary ),
                "secondary_color" => $flat["secondary"] ?? $flat["text"] ?? self::normalize_hex( $fallback_secondary ),
                "text_color" => $flat["text"] ?? "",
                "accent_color" => $flat["accent"] ?? "",
            ]
        );
    }

    /**
     * Return every Elementor site color as a normalized, display-ready palette.
     *
     * @return array<int,array{id:string,label:string,hex:string,source:string}>
     */
    public static function elementor_palette(): array {
        $settings = self::elementor_settings();
        if ( empty( $settings ) ) {
            return [];
        }

        $palette = [];
        foreach ( [ "system_colors" => "System", "custom_colors" => "Custom" ] as $group => $source ) {
            foreach ( (array) ( $settings[ $group ] ?? [] ) as $color ) {
                if ( ! is_array( $color ) || empty( $color["_id"] ) || empty( $color["color"] ) ) {
                    continue;
                }

                $hex = self::normalize_hex( (string) $color["color"], "" );
                if ( "#000000" === $hex && ! preg_match( "/^#?0{6}$/", (string) $color["color"] ) ) {
                    continue;
                }

                $label = isset( $color["title"] ) && is_scalar( $color["title"] ) && "" !== trim( (string) $color["title"] )
                    ? sanitize_text_field( (string) $color["title"] )
                    : ucwords( str_replace( [ "_", "-" ], " ", (string) $color["_id"] ) );

                $palette[] = [
                    "id"     => sanitize_key( (string) $color["_id"] ),
                    "label"  => $label,
                    "hex"    => $hex,
                    "source" => $source,
                ];
            }
        }

        return $palette;
    }

    /**
     * Return every Elementor global typography entry with its CSS variable.
     *
     * @return array<int,array{id:string,label:string,family:string,source:string,css_variable:string}>
     */
    public static function elementor_font_palette(): array {
        $settings = self::elementor_settings();
        if ( empty( $settings ) ) {
            return [];
        }

        $palette = [];
        foreach ( [ "system_typography" => "System", "custom_typography" => "Custom" ] as $group => $source ) {
            foreach ( (array) ( $settings[ $group ] ?? [] ) as $font ) {
                if ( ! is_array( $font ) || empty( $font["_id"] ) ) {
                    continue;
                }
                $id = sanitize_key( (string) $font["_id"] );
                $family = self::elementor_font_family( $font );
                if ( "" === $id || "" === $family ) {
                    continue;
                }

                $label = isset( $font["title"] ) && is_scalar( $font["title"] ) && "" !== trim( (string) $font["title"] )
                    ? sanitize_text_field( (string) $font["title"] )
                    : ucwords( str_replace( [ "_", "-" ], " ", $id ) );

                $palette[] = [
                    "id"           => $id,
                    "label"        => $label,
                    "family"       => $family,
                    "source"       => $source,
                    "css_variable" => "--e-global-typography-" . $id . "-font-family",
                ];
            }
        }

        return $palette;
    }

    public static function elementor_fonts(): array {
        $flat = [];
        foreach ( self::elementor_font_palette() as $font ) {
            $flat[ $font["id"] ] = $font["family"];
        }

        return array_filter(
            [
                "primary_font_family" => $flat["primary"] ?? "",
                "secondary_font_family" => $flat["secondary"] ?? "",
                "text_font_family" => $flat["text"] ?? "",
                "accent_font_family" => $flat["accent"] ?? "",
            ]
        );
    }

    private static function hws_color( string $option, string $fallback ): string {
        if ( function_exists( "hws_get_brand_colors_payload" ) ) {
            $payload = hws_get_brand_colors_payload();
            $key = self::HWS_PRIMARY_OPTION === $option ? "primary_color" : "secondary_color";
            if ( is_array( $payload ) && ! empty( $payload[ $key ] ) && is_scalar( $payload[ $key ] ) ) {
                return self::normalize_hex( (string) $payload[ $key ], $fallback );
            }
        }

        if ( function_exists( "get_option" ) ) {
            $stored = get_option( $option, "" );
            if ( is_scalar( $stored ) && "" !== trim( (string) $stored ) ) {
                return self::normalize_hex( (string) $stored, $fallback );
            }
        }

        return self::normalize_hex( $fallback );
    }

    private static function elementor_settings(): array {
        if ( ! function_exists( "get_option" ) || ! function_exists( "get_post_meta" ) ) {
            return [];
        }

        $kit = absint( get_option( "elementor_active_kit" ) );
        if ( ! $kit ) {
            return [];
        }

        $settings = get_post_meta( $kit, "_elementor_page_settings", true );
        return is_array( $settings ) ? $settings : [];
    }

    private static function elementor_font_family( array $font ): string {
        foreach ( [ "typography_font_family", "font_family", "family" ] as $key ) {
            if ( isset( $font[ $key ] ) && is_scalar( $font[ $key ] ) && "" !== trim( (string) $font[ $key ] ) ) {
                return self::safe_font_family( (string) $font[ $key ] );
            }
        }

        foreach ( $font as $value ) {
            if ( ! is_array( $value ) ) {
                continue;
            }
            foreach ( [ "typography_font_family", "font_family", "family" ] as $key ) {
                if ( isset( $value[ $key ] ) && is_scalar( $value[ $key ] ) && "" !== trim( (string) $value[ $key ] ) ) {
                    return self::safe_font_family( (string) $value[ $key ] );
                }
            }
        }

        return "";
    }

    private static function safe_font_family( string $family ): string {
        $family = trim( sanitize_text_field( $family ) );
        if ( "" === $family || preg_match( "/[;{}<>]/", $family ) ) {
            return "";
        }

        return $family;
    }

    private static function brand_assets_admin_url(): string {
        if ( ! function_exists( "admin_url" ) ) {
            return "";
        }

        return admin_url( "options-general.php?page=hws-core-tools&tab=brand-assets" );
    }
}
