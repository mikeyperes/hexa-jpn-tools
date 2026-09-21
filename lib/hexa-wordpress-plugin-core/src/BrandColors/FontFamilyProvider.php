<?php

namespace Hexa\PluginCore\BrandColors;

final class FontFamilyProvider {
    public const TEMPLATE = "template";
    public const NATIVE_PRIMARY = "native_primary";
    public const NATIVE_SECONDARY = "native_secondary";
    private const ELEMENTOR_PREFIX = "elementor_";

    /**
     * @return array<string,array{value:string,label:string,group:string,family:string,source:string,source_id:string,css:string,available:bool}>
     */
    public static function options( bool $include_template = true ): array {
        $options = [];
        if ( $include_template ) {
            $options[ self::TEMPLATE ] = self::option(
                self::TEMPLATE,
                "Template font",
                "Current template",
                "",
                "Template",
                "template",
                "",
                true
            );
        }

        $palette = BrandColorProvider::elementor_font_palette();
        $by_id = [];
        foreach ( $palette as $font ) {
            $by_id[ (string) $font["id"] ] = $font;
        }

        foreach ( [ self::NATIVE_PRIMARY => "primary", self::NATIVE_SECONDARY => "secondary" ] as $value => $id ) {
            $font = $by_id[ $id ] ?? [];
            $family = (string) ( $font["family"] ?? "" );
            $label = self::NATIVE_PRIMARY === $value ? "Native primary" : "Native secondary";
            $css_variable = (string) ( $font["css_variable"] ?? "" );
            $css = self::variable_with_fallback( $css_variable, $family );
            $options[ $value ] = self::option( $value, $label, "Native fonts", $family, "Elementor global", $id, $css, "" !== $css );
        }

        $seen = [];
        foreach ( $palette as $font ) {
            $family = (string) $font["family"];
            $family_key = strtolower( trim( preg_replace( "/\s+/", " ", $family ) ?? $family ) );
            if ( isset( $seen[ $family_key ] ) ) {
                continue;
            }
            $seen[ $family_key ] = true;
            $source_id = self::clean_key( (string) $font["id"] );
            $value = self::ELEMENTOR_PREFIX . $source_id;
            if ( self::ELEMENTOR_PREFIX === $value ) {
                continue;
            }
            $css_variable = (string) ( $font["css_variable"] ?? "" );
            $css = self::variable_with_fallback( $css_variable, $family );
            if ( "" === $css || isset( $options[ $value ] ) ) {
                continue;
            }
            $options[ $value ] = self::option(
                $value,
                $family,
                "Elementor fonts",
                $family,
                "Elementor " . (string) $font["source"],
                $source_id,
                $css,
                true
            );
        }

        return $options;
    }

    public static function normalize_selection( string $value, string $fallback = self::TEMPLATE ): string {
        $value = self::clean_key( $value );
        $fallback = self::clean_key( $fallback );
        $options = self::options( true );

        if ( isset( $options[ $value ] ) && $options[ $value ]["available"] ) {
            return $value;
        }

        if ( isset( $options[ $fallback ] ) && $options[ $fallback ]["available"] ) {
            return $fallback;
        }

        return self::TEMPLATE;
    }

    /**
     * @return array{value:string,label:string,group:string,family:string,source:string,source_id:string,css:string,available:bool,requested:string}
     */
    public static function resolve( string $value ): array {
        $requested = self::clean_key( $value );
        $options = self::options( true );
        if ( isset( $options[ $requested ] ) && $options[ $requested ]["available"] ) {
            return $options[ $requested ] + [ "requested" => $requested ];
        }

        return $options[ self::TEMPLATE ] + [ "requested" => $requested ];
    }

    public static function css_value( string $value ): string {
        $resolved = self::resolve( $value );
        return (string) $resolved["css"];
    }

    private static function option( string $value, string $label, string $group, string $family, string $source, string $source_id, string $css, bool $available ): array {
        return [
            "value"     => $value,
            "label"     => $label,
            "group"     => $group,
            "family"    => $family,
            "source"    => $source,
            "source_id" => $source_id,
            "css"       => $css,
            "available" => $available,
        ];
    }

    private static function quote_family( string $family ): string {
        $family = trim( preg_replace( "/[\\x00-\\x1f\\x7f]/", "", $family ) ?? "" );
        if ( "" === $family || preg_match( "/[;{}<>]/", $family ) ) {
            return "";
        }

        return '"' . str_replace( [ "\\", '"' ], [ "", '\\"' ], $family ) . '"';
    }

    private static function variable_with_fallback( string $css_variable, string $family ): string {
        if ( ! preg_match( "/^--[a-zA-Z0-9_-]+$/", $css_variable ) ) {
            return "";
        }

        $fallback = self::quote_family( $family );
        return "" !== $fallback ? "var(" . $css_variable . ", " . $fallback . ")" : "";
    }

    private static function clean_key( string $value ): string {
        if ( function_exists( "sanitize_key" ) ) {
            return sanitize_key( $value );
        }

        return preg_replace( "/[^a-z0-9_\-]/", "", strtolower( $value ) ) ?: "";
    }
}
