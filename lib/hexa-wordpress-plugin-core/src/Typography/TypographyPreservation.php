<?php

namespace Hexa\PluginCore\Typography;

final class TypographyPreservation {
    public const PROPERTIES = [ "font_family", "font_size", "font_color", "font_weight" ];

    public static function setting_key( string $prefix, string $property ): string {
        $prefix = self::clean_key( $prefix );
        $property = self::normalize_property( $property );
        return "" !== $prefix && "" !== $property ? $prefix . "_preserve_" . $property : "";
    }

    public static function setting_keys( string $prefix, array $properties = [] ): array {
        $keys = [];
        foreach ( self::normalize_properties( $properties ) as $property ) {
            $key = self::setting_key( $prefix, $property );
            if ( "" !== $key ) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    public static function defaults( string $prefix, $defaults = true, array $properties = [] ): array {
        $values = [];
        foreach ( self::normalize_properties( $properties ) as $property ) {
            $key = self::setting_key( $prefix, $property );
            $values[ $key ] = is_array( $defaults )
                ? (bool) ( $defaults[ $property ] ?? true )
                : (bool) $defaults;
        }
        return $values;
    }

    public static function values( array $settings, string $prefix, $defaults = true, array $properties = [] ): array {
        $values = [];
        foreach ( self::normalize_properties( $properties ) as $property ) {
            $key = self::setting_key( $prefix, $property );
            $fallback = is_array( $defaults ) ? (bool) ( $defaults[ $property ] ?? true ) : (bool) $defaults;
            $values[ $property ] = array_key_exists( $key, $settings ) ? (bool) $settings[ $key ] : $fallback;
        }
        return $values;
    }

    public static function preserves( array $settings, string $prefix, string $property, bool $default = true ): bool {
        $key = self::setting_key( $prefix, $property );
        return "" !== $key && array_key_exists( $key, $settings ) ? (bool) $settings[ $key ] : $default;
    }

    public static function state_class( string $prefix, string $property ): string {
        $prefix = str_replace( "_", "-", self::clean_key( $prefix ) );
        $property = str_replace( "_", "-", self::normalize_property( $property ) );
        return "" !== $prefix && "" !== $property ? "hpc-typography-" . $prefix . "-preserve-" . $property : "";
    }

    private static function normalize_properties( array $properties ): array {
        if ( [] === $properties ) {
            return self::PROPERTIES;
        }
        $normalized = [];
        foreach ( $properties as $property ) {
            $property = self::normalize_property( (string) $property );
            if ( "" !== $property && ! in_array( $property, $normalized, true ) ) {
                $normalized[] = $property;
            }
        }
        return $normalized;
    }

    private static function normalize_property( string $property ): string {
        $property = self::clean_key( $property );
        return in_array( $property, self::PROPERTIES, true ) ? $property : "";
    }

    private static function clean_key( string $value ): string {
        if ( function_exists( "sanitize_key" ) ) {
            return sanitize_key( $value );
        }
        return preg_replace( "/[^a-z0-9_\-]/", "", strtolower( $value ) ) ?: "";
    }
}
