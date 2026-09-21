<?php

namespace Hexa\PluginCore\SearchQuery;

/** Parses bounded search terms while preserving explicitly quoted phrases. */
final class SearchTermParser {
    public const MAX_TERMS = 8;
    public const MAX_TERM_LENGTH = 80;

    /** @return string[] */
    public static function parse( string $query, string $term_logic = 'all' ): array {
        $query = self::clean( $query );
        if ( '' === $query ) {
            return [];
        }

        if ( 'exact' === $term_logic ) {
            $query = self::truncate( self::trim_quotes( $query ) );

            return '' === $query ? [] : [ $query ];
        }

        preg_match_all( '/"([^"]+)"|\'([^\']+)\'|([^\s]+)/u', $query, $matches, PREG_SET_ORDER );
        $terms = [];
        $seen = [];

        foreach ( $matches as $match ) {
            $term = $match[1] ?? '';
            if ( '' === $term ) {
                $term = $match[2] ?? '';
            }
            if ( '' === $term ) {
                $term = $match[3] ?? '';
            }

            $term = trim( self::clean( $term ), " \t\n\r\0\x0B*\"'" );
            if ( '' === $term ) {
                continue;
            }

            $term = self::truncate( $term );
            $comparison = function_exists( 'mb_strtolower' ) ? mb_strtolower( $term, 'UTF-8' ) : strtolower( $term );
            if ( isset( $seen[ $comparison ] ) ) {
                continue;
            }

            $seen[ $comparison ] = true;
            $terms[] = $term;
            if ( count( $terms ) >= self::MAX_TERMS ) {
                break;
            }
        }

        return $terms;
    }

    private static function clean( string $value ): string {
        $value = (string) preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $value );

        return trim( (string) preg_replace( '/\s+/u', ' ', $value ) );
    }

    private static function trim_quotes( string $value ): string {
        $length = strlen( $value );
        if ( $length >= 2 ) {
            $first = $value[0];
            $last = $value[ $length - 1 ];
            if ( ( '"' === $first && '"' === $last ) || ( "'" === $first && "'" === $last ) ) {
                return trim( substr( $value, 1, -1 ) );
            }
        }

        return $value;
    }

    private static function truncate( string $value ): string {
        if ( function_exists( 'mb_substr' ) ) {
            return mb_substr( $value, 0, self::MAX_TERM_LENGTH, 'UTF-8' );
        }

        return substr( $value, 0, self::MAX_TERM_LENGTH );
    }
}
