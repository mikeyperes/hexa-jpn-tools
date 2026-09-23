<?php

namespace Hexa\PluginCore\SearchQuery;

/**
 * Builds one bounded SQL match condition for a parsed search term.
 *
 * Shared by the native results engine and DirectorySearch so every search
 * surface matches words the same way. Column names must be trusted
 * identifiers chosen by Core; the term is always bound through prepare().
 */
final class SearchMatchSql {
    public const WILDCARD = '*';

    /** @param object $database wpdb-compatible object exposing prepare() and esc_like(). */
    public static function condition( $database, string $column, string $term, string $matching, bool $wildcards = false ): string {
        if ( $wildcards && str_contains( $term, self::WILDCARD ) ) {
            return self::wildcard_condition( $database, $column, $term );
        }

        if ( 'contains' === $matching ) {
            return $database->prepare( $column . ' LIKE %s', '%' . $database->esc_like( $term ) . '%' );
        }

        $pattern = '(^|[^[:alnum:]_])' . preg_quote( $term, '/' );
        if ( 'whole' === $matching ) {
            $pattern .= '([^[:alnum:]_]|$)';
        }

        return $database->prepare( $column . ' REGEXP %s', $pattern );
    }

    /**
     * Translates explicit `*` wildcards into a word-anchored pattern.
     *
     * `syn*` matches words starting with "syn", `*gogue` words ending with
     * "gogue", and `s*gogue` one word starting with "s" and ending with "gogue".
     *
     * @param object $database
     */
    private static function wildcard_condition( $database, string $column, string $term ): string {
        $parts   = explode( self::WILDCARD, $term );
        $leading = '' === $parts[0];
        $closing = '' === $parts[ count( $parts ) - 1 ];
        $pieces  = array_values( array_filter( $parts, static fn( string $part ): bool => '' !== $part ) );

        if ( [] === $pieces ) {
            return '1=1';
        }

        $pattern = implode( '[^[:space:]]*', array_map( static fn( string $piece ): string => preg_quote( $piece, '/' ), $pieces ) );
        if ( ! $leading ) {
            $pattern = '(^|[^[:alnum:]_])' . $pattern;
        }
        if ( ! $closing ) {
            $pattern .= '([^[:alnum:]_]|$)';
        }

        return $database->prepare( $column . ' REGEXP %s', $pattern );
    }
}
