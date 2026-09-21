<?php

namespace Hexa\PluginCore\BrandColors;

final class FontWeightProvider {
    public const FONT_DEFAULT = "inherit";

    /**
     * @return array<string,array{value:string,label:string,css:string}>
     */
    public static function options(): array {
        $labels = [
            self::FONT_DEFAULT => "Font default",
            "100" => "Thin (100)",
            "200" => "Extra light (200)",
            "300" => "Light (300)",
            "400" => "Regular (400)",
            "500" => "Medium (500)",
            "600" => "Semi-bold (600)",
            "700" => "Bold (700)",
            "800" => "Extra-bold (800)",
            "900" => "Black (900)",
        ];
        $options = [];
        foreach ( $labels as $value => $label ) {
            $value = (string) $value;
            $options[ $value ] = [
                "value" => $value,
                "label" => $label,
                "css"   => self::FONT_DEFAULT === $value ? "" : $value,
            ];
        }

        return $options;
    }

    public static function normalize_selection( mixed $value, string $fallback = self::FONT_DEFAULT ): string {
        $value = strtolower( trim( (string) $value ) );
        $aliases = [ "normal" => "400", "bold" => "700" ];
        $value = $aliases[ $value ] ?? $value;
        $fallback = strtolower( trim( $fallback ) );
        $options = self::options();

        if ( isset( $options[ $value ] ) ) {
            return $value;
        }
        if ( isset( $options[ $fallback ] ) ) {
            return $fallback;
        }

        return self::FONT_DEFAULT;
    }

    public static function css_value( mixed $value ): string {
        $value = self::normalize_selection( $value );
        return (string) self::options()[ $value ]["css"];
    }

    public static function label( mixed $value ): string {
        $value = self::normalize_selection( $value );
        return (string) self::options()[ $value ]["label"];
    }
}
