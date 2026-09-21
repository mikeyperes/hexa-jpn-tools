<?php

declare( strict_types=1 );

namespace Hexa\PluginCore\DataNormalization;

use DateTimeImmutable;

/** Generic scalar, row, URL, and identifier normalization for host plugins. */
final class ValueNormalizer {
    public static function present( mixed $value ): bool {
        if ( null === $value || false === $value || '' === $value ) {
            return false;
        }
        return ! is_array( $value ) || [] !== array_filter( $value, static fn( mixed $item ): bool => self::present( $item ) );
    }

    public static function text( mixed $value ): string {
        if ( ! is_scalar( $value ) ) {
            return '';
        }
        $value = trim( (string) $value );
        if ( '' === $value ) {
            return '';
        }
        return function_exists( 'wp_strip_all_tags' ) ? trim( wp_strip_all_tags( $value, true ) ) : trim( strip_tags( $value ) );
    }

    public static function url( mixed $value ): string {
        if ( ! is_scalar( $value ) ) {
            return '';
        }
        $url = trim( (string) $value );
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return '';
        }
        return function_exists( 'esc_url_raw' ) ? (string) esc_url_raw( $url ) : $url;
    }

    /**
     * Recursively normalize complete URL values from scalar lines and nested
     * field arrays.
     *
     * Unlike urls(), this method does not extract a URL embedded in prose. It
     * is intended for ACF URL fields, repeaters, and one-URL-per-line values.
     *
     * @return list<string>
     */
    public static function url_values( mixed $value, bool $strip_tags = false, bool $sanitize = true ): array {
        $urls = [];
        self::collect_url_values( $value, $strip_tags, $sanitize, $urls );
        return array_values( array_unique( $urls ) );
    }

    public static function email( mixed $value ): string {
        if ( ! is_scalar( $value ) ) {
            return '';
        }
        $email = trim( (string) $value );
        if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
            return '';
        }
        return function_exists( 'sanitize_email' ) ? (string) sanitize_email( $email ) : $email;
    }

    public static function date( mixed $value ): string {
        if ( ! is_scalar( $value ) ) {
            return '';
        }
        $date = trim( (string) $value );
        if ( preg_match( '/^\d{4}$/', $date ) ) {
            return $date;
        }
        if ( preg_match( '/^\d{4}-\d{2}$/', $date ) ) {
            $parsed = DateTimeImmutable::createFromFormat( '!Y-m', $date );
            return $parsed && $parsed->format( 'Y-m' ) === $date ? $date : '';
        }
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            $parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
            return $parsed && $parsed->format( 'Y-m-d' ) === $date ? $date : '';
        }
        if ( preg_match( '/^\d{8}$/', $date ) ) {
            $parsed = DateTimeImmutable::createFromFormat( '!Ymd', $date );
            return $parsed && $parsed->format( 'Ymd' ) === $date ? $parsed->format( 'Y-m-d' ) : '';
        }
        $timestamp = strtotime( $date );
        return false === $timestamp ? '' : gmdate( 'Y-m-d', $timestamp );
    }

    public static function number( mixed $value ): int|float|null {
        if ( ! is_numeric( $value ) ) {
            return null;
        }
        $number = (float) $value;
        return floor( $number ) === $number ? (int) $number : $number;
    }

    /** @return list<array<string,mixed>> */
    public static function rows( mixed $value ): array {
        if ( function_exists( 'maybe_unserialize' ) ) {
            $value = maybe_unserialize( $value );
        }
        if ( ! is_array( $value ) || [] === $value ) {
            return [];
        }
        if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
            return [ $value ];
        }
        return array_values( array_filter( $value, static fn( mixed $row ): bool => is_array( $row ) ) );
    }

    /** @param string|list<string> $row_keys
     *  @return list<string>
     */
    public static function strings( mixed $value, string|array $row_keys = 'name' ): array {
        $strings = [];
        $row_keys = is_array( $row_keys ) ? $row_keys : [ $row_keys ];
        if ( is_array( $value ) ) {
            foreach ( $value as $item ) {
                if ( is_array( $item ) ) {
                    $candidate = '';
                    foreach ( $row_keys as $row_key ) {
                        if ( isset( $item[ $row_key ] ) && self::present( $item[ $row_key ] ) ) {
                            $candidate = $item[ $row_key ];
                            break;
                        }
                    }
                    $item = $candidate;
                }
                $text = self::text( $item );
                if ( '' !== $text ) {
                    $strings[] = $text;
                }
            }
        } elseif ( is_scalar( $value ) ) {
            foreach ( preg_split( '/[\r\n,]+/', (string) $value ) ?: [] as $item ) {
                $text = self::text( $item );
                if ( '' !== $text ) {
                    $strings[] = $text;
                }
            }
        }
        return array_values( array_unique( $strings ) );
    }

    /**
     * Pull one raw scalar value from each repeater row without sanitizing or
     * deduplicating it. PHP-falsey values retain legacy omission semantics.
     *
     * @return list<string>
     */
    public static function row_values( mixed $value, string $row_key = 'name' ): array {
        if ( ! is_array( $value ) || [] === $value ) {
            return [];
        }
        $values = [];
        foreach ( $value as $row ) {
            if ( ! is_array( $row ) || ! is_scalar( $row[ $row_key ] ?? null ) ) {
                continue;
            }
            $candidate = trim( (string) $row[ $row_key ] );
            if ( $candidate ) {
                $values[] = $candidate;
            }
        }
        return $values;
    }

    /** @return list<string> */
    public static function urls( mixed ...$values ): array {
        $urls = [];
        foreach ( $values as $value ) {
            if ( is_array( $value ) ) {
                foreach ( $value as $item ) {
                    if ( is_array( $item ) ) {
                        $item = $item['url'] ?? '';
                    }
                    $url = self::url( $item );
                    if ( '' !== $url ) {
                        $urls[] = $url;
                    }
                }
                continue;
            }
            if ( is_scalar( $value ) ) {
                preg_match_all( '#https?://[^\s,<>]+#i', (string) $value, $matches );
                foreach ( $matches[0] ?? [] as $item ) {
                    $url = self::url( rtrim( $item, '.,;:)\]' ) );
                    if ( '' !== $url ) {
                        $urls[] = $url;
                    }
                }
            }
        }
        return array_values( array_unique( $urls ) );
    }

    /** @return list<int> */
    public static function ids( mixed $value ): array {
        $value = is_array( $value ) ? $value : [ $value ];
        $ids = [];
        foreach ( $value as $item ) {
            if ( is_object( $item ) && isset( $item->ID ) ) {
                $item = $item->ID;
            } elseif ( is_array( $item ) ) {
                $item = $item['ID'] ?? $item['id'] ?? 0;
            }
            $id = self::absint( $item );
            if ( $id > 0 ) {
                $ids[] = $id;
            }
        }
        return array_values( array_unique( $ids ) );
    }

    /**
     * Collapse a list to the schema convention of null, one scalar, or a list.
     *
     * @param array<mixed> $values
     */
    public static function single_or_array( array $values ): mixed {
        $values = array_values( array_filter( $values ) );
        if ( [] === $values ) {
            return null;
        }
        return 1 === count( $values ) ? $values[0] : $values;
    }

    /** @param list<string> $urls */
    private static function collect_url_values( mixed $value, bool $strip_tags, bool $sanitize, array &$urls ): void {
        if ( is_array( $value ) ) {
            foreach ( $value as $item ) {
                self::collect_url_values( $item, $strip_tags, $sanitize, $urls );
            }
            return;
        }
        if ( ! is_scalar( $value ) ) {
            return;
        }

        $value = trim( (string) $value );
        if ( $strip_tags ) {
            $value = function_exists( 'wp_strip_all_tags' )
                ? trim( wp_strip_all_tags( $value, true ) )
                : trim( strip_tags( $value ) );
        }
        foreach ( preg_split( '/\R/', $value ) ?: [] as $candidate ) {
            $candidate = trim( $candidate );
            if ( '' === $candidate || ! filter_var( $candidate, FILTER_VALIDATE_URL ) ) {
                continue;
            }
            $url = $sanitize && function_exists( 'esc_url_raw' ) ? (string) esc_url_raw( $candidate ) : $candidate;
            if ( '' !== $url ) {
                $urls[] = $url;
            }
        }
    }

    private static function absint( mixed $value ): int {
        return function_exists( 'absint' ) ? absint( $value ) : abs( (int) $value );
    }
}
