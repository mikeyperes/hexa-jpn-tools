<?php

namespace Hexa\PluginCore\Typography;

final class TemplateTypography {
    public const TEMPLATE_DEFAULT = "template_default";
    public const SITE_INHERIT = "site_inherit";
    public const CUSTOM = "custom";

    public static function options(): array {
        return [
            self::TEMPLATE_DEFAULT => [
                "label" => "Original Template",
                "description" => "Match the selected template preview, including its font, size, color, and weight.",
            ],
            self::SITE_INHERIT => [
                "label" => "Use Site Typography",
                "description" => "Use the current site's font, size, text color, and weight.",
            ],
            self::CUSTOM => [
                "label" => "Custom Typography",
                "description" => "Change selected values below. Inherited values continue using the site.",
            ],
        ];
    }

    public static function normalize_mode( string $mode ): string {
        $mode = self::clean_key( $mode );
        return array_key_exists( $mode, self::options() ) ? $mode : self::TEMPLATE_DEFAULT;
    }

    public static function setting_key( string $prefix ): string {
        $prefix = self::clean_key( $prefix );
        return "" !== $prefix ? $prefix . "_typography_mode" : "";
    }

    public static function mode_state_class( string $prefix, string $mode ): string {
        $prefix = str_replace( "_", "-", self::clean_key( $prefix ) );
        $mode = str_replace( "_", "-", self::normalize_mode( $mode ) );
        return "" !== $prefix ? "hpc-typography-" . $prefix . "-mode-" . $mode : "";
    }

    public static function custom_property_state_class( string $prefix, string $property ): string {
        $prefix = str_replace( "_", "-", self::clean_key( $prefix ) );
        $property = str_replace( "_", "-", self::clean_key( $property ) );
        return "" !== $prefix && "" !== $property ? "hpc-typography-" . $prefix . "-custom-" . $property : "";
    }

    public static function preservation_values( array $settings, string $prefix, $defaults = true, array $properties = [] ): array {
        $mode_key = self::setting_key( $prefix );
        $mode = self::normalize_mode( (string) ( $settings[ $mode_key ] ?? self::TEMPLATE_DEFAULT ) );
        $properties = [] === $properties ? TypographyPreservation::PROPERTIES : $properties;
        if ( self::TEMPLATE_DEFAULT === $mode ) {
            return array_fill_keys( $properties, false );
        }
        if ( self::SITE_INHERIT === $mode ) {
            return array_fill_keys( $properties, true );
        }
        return TypographyPreservation::values( $settings, $prefix, $defaults, $properties );
    }

    private static function clean_key( string $value ): string {
        if ( function_exists( "sanitize_key" ) ) {
            return sanitize_key( $value );
        }
        return preg_replace( "/[^a-z0-9_\-]/", "", strtolower( $value ) ) ?: "";
    }
}
