<?php

namespace Hexa\PluginCore\PublicComponents;

/**
 * Shared sanitizers for declarative public-component profiles
 * (DirectorySearch, Calendar, and QueryFilter definitions).
 */
final class ProfileValues {
    /** Lowercase identifier: profile ids, filter keys, post types, roles, taxonomies. */
    public static function key( string $value ): string {
        return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( trim( $value ) ) );
    }

    /** @return string[] Unique non-empty keys. */
    public static function keys( array $values ): array {
        $keys = [];
        foreach ( $values as $value ) {
            $key = is_scalar( $value ) ? self::key( (string) $value ) : '';
            if ( '' !== $key && ! in_array( $key, $keys, true ) ) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /** A meta key keeps its case and the characters WordPress stores; '' when invalid. @param mixed $value */
    public static function meta_key( $value ): string {
        $key = is_scalar( $value ) ? (string) preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $value ) : '';

        return strlen( $key ) <= 191 ? $key : '';
    }

    /** @return string[] Unique valid meta keys. */
    public static function meta_keys( array $values ): array {
        $keys = [];
        foreach ( $values as $value ) {
            $key = self::meta_key( $value );
            if ( '' !== $key && ! in_array( $key, $keys, true ) ) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /** @param mixed $value @param string[] $allowed */
    public static function choice( $value, array $allowed, string $fallback ): string {
        $value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';

        return in_array( $value, $allowed, true ) ? $value : $fallback;
    }

    /** @param mixed $value */
    public static function bounded_int( $value, int $default, int $min, int $max ): int {
        $value = is_numeric( $value ) ? (int) $value : $default;

        return max( $min, min( $max, $value ) );
    }

    /** Space-separated CSS classes with unsafe characters removed. */
    public static function classes( string $value ): string {
        $classes = array_filter( array_map( static fn( string $class ): string => (string) preg_replace( '/[^A-Za-z0-9_\-]/', '', $class ), preg_split( '/\s+/', $value ) ?: [] ) );

        return implode( ' ', $classes );
    }

    /** @param mixed $value */
    public static function callback( $value ): ?callable {
        return is_callable( $value ) ? $value : null;
    }

    public static function label( string $key ): string {
        return ucfirst( str_replace( [ '_', '-' ], ' ', $key ) );
    }

    /** A valid IANA timezone identifier, or '' to use the site timezone. @param mixed $value */
    public static function timezone( $value ): string {
        $value = is_string( $value ) ? trim( $value ) : '';

        return '' !== $value && in_array( $value, \DateTimeZone::listIdentifiers(), true ) ? $value : '';
    }

    /** The declared timezone, else the site timezone, else UTC. */
    public static function resolve_timezone( string $timezone ): \DateTimeZone {
        if ( '' !== $timezone ) {
            return new \DateTimeZone( $timezone );
        }

        return function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
    }

    /**
     * Host labels override defaults only with non-empty strings.
     *
     * @param array<string,string> $defaults
     * @return array<string,string>
     */
    public static function labels( array $defaults, array $labels ): array {
        foreach ( $defaults as $key => $default ) {
            if ( isset( $labels[ $key ] ) && is_string( $labels[ $key ] ) && '' !== trim( $labels[ $key ] ) ) {
                $defaults[ $key ] = $labels[ $key ];
            }
        }

        return $defaults;
    }
}
