<?php

namespace Hexa\PluginCore\QueryFilter;

/**
 * Extracts an explicit relative time window from public search text.
 *
 * A duration without a cue is accepted only when it is the complete query.
 * Mixed text must put a `next`/`within` phrase at one edge, which keeps prose
 * such as "Open 24 hours" searchable as ordinary words. Quoted text is never
 * interpreted as a filter.
 */
final class NaturalTimeWindow {
    public const MAX_DURATION_SECONDS = 31622400; // 366 days.

    private const NUMBER_WORDS = [
        'a'      => 1,
        'an'     => 1,
        'one'    => 1,
        'two'    => 2,
        'three'  => 3,
        'four'   => 4,
        'five'   => 5,
        'six'    => 6,
        'seven'  => 7,
        'eight'  => 8,
        'nine'   => 9,
        'ten'    => 10,
        'eleven' => 11,
        'twelve' => 12,
    ];

    /**
     * @param array<string,mixed> $definition Host-owned event field mapping.
     * @param string[]            $available_post_types Search profile allowlist.
     * @return array<string,mixed>
     */
    public static function normalize( array $definition, array $available_post_types ): array {
        $start = self::key( $definition['start_meta_key'] ?? '' );
        $post_types = array_values( array_intersect(
            self::keys( (array) ( $definition['post_types'] ?? [] ) ),
            self::keys( $available_post_types )
        ) );
        if ( '' === $start || [] === $post_types ) {
            return [];
        }

        $timezone = trim( (string) ( $definition['timezone'] ?? '' ) );
        if ( '' !== $timezone ) {
            try {
                new \DateTimeZone( $timezone );
            } catch ( \Throwable $exception ) {
                $timezone = '';
            }
        }

        return [
            'start_meta_key'     => $start,
            'end_meta_key'       => self::key( $definition['end_meta_key'] ?? '' ),
            'precision_meta_key' => self::key( $definition['precision_meta_key'] ?? '' ),
            'date_only_value'    => self::scalar( $definition['date_only_value'] ?? 'date', 'date' ),
            'timezone'           => $timezone,
            'post_types'         => $post_types,
        ];
    }

    /**
     * @param array<string,mixed> $definition Normalized field mapping.
     * @return array{query:string,from:int,to:int,today:int,duration:int}|null
     */
    public static function parse( string $query, array $definition, ?\DateTimeImmutable $now = null ): ?array {
        if ( [] === $definition || '' === (string) ( $definition['start_meta_key'] ?? '' ) ) {
            return null;
        }

        $query = self::clean( $query );
        if ( '' === $query ) {
            return null;
        }

        $mask = self::unquoted_mask( $query );
        $number_words = implode( '|', array_map( 'preg_quote', array_keys( self::NUMBER_WORDS ) ) );
        $pattern = '/(?<![\p{L}\p{N}_])'
            . '(?:(?<cue>within(?:\s+the)?(?:\s+next)?|next)\s+)?'
            . '(?:(?<amount>[0-9]{1,3}|' . $number_words . ')\s*)?'
            . '(?<unit>hours?|hrs?|h|days?|d|weeks?|w)'
            . '(?![\p{L}\p{N}_])/iu';
        preg_match_all( $pattern, $mask, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL );

        $candidates = [];
        foreach ( $matches as $match ) {
            $text = (string) ( $match[0][0] ?? '' );
            $offset = (int) ( $match[0][1] ?? -1 );
            $cue = trim( (string) ( $match['cue'][0] ?? '' ) );
            $amount_text = strtolower( trim( (string) ( $match['amount'][0] ?? '' ) ) );
            if ( '' === $text || $offset < 0 || ( '' === $amount_text && ! str_contains( strtolower( $cue ), 'next' ) ) ) {
                continue;
            }

            $amount = ctype_digit( $amount_text ) ? (int) $amount_text : ( self::NUMBER_WORDS[ $amount_text ] ?? 1 );
            $seconds = $amount * self::unit_seconds( (string) ( $match['unit'][0] ?? '' ) );
            if ( $amount < 1 || $seconds < 1 || $seconds > self::MAX_DURATION_SECONDS ) {
                continue;
            }

            $before = substr( $query, 0, $offset );
            $after = substr( $query, $offset + strlen( $text ) );
            $before_has_text = self::has_meaningful_text( $before );
            $after_has_text = self::has_meaningful_text( $after );
            $pure = ! $before_has_text && ! $after_has_text;
            if ( ! $pure && ( '' === $cue || ( $before_has_text && $after_has_text ) ) ) {
                continue;
            }

            $candidates[] = [
                'offset'   => $offset,
                'length'   => strlen( $text ),
                'seconds'  => $seconds,
                'residual' => $pure ? '' : self::residual( $before . ' ' . $after ),
            ];
        }

        if ( 1 !== count( $candidates ) ) {
            return null;
        }

        $candidate = $candidates[0];
        $timezone = self::timezone( (string) ( $definition['timezone'] ?? '' ) );
        $now = $now instanceof \DateTimeImmutable ? $now : new \DateTimeImmutable( 'now', $timezone );
        $from = $now->getTimestamp();

        return [
            'query'    => (string) $candidate['residual'],
            'from'     => $from,
            'to'       => $from + (int) $candidate['seconds'],
            'today'    => $now->setTimezone( $timezone )->setTime( 0, 0, 0 )->getTimestamp(),
            'duration' => (int) $candidate['seconds'],
        ];
    }

    /**
     * Inclusive now-through-upper-bound event eligibility. Timed events match
     * when they start in the window or are still running. Date-only events use
     * local midnight so the current local day remains ongoing.
     *
     * @param array<string,mixed> $definition Normalized field mapping.
     * @param array{from:int,to:int,today:int} $window Parsed window.
     * @return array<string|int,mixed>
     */
    public static function constraints( array $definition, array $window ): array {
        $start = (string) ( $definition['start_meta_key'] ?? '' );
        if ( '' === $start ) {
            return [];
        }

        $from = (int) ( $window['from'] ?? 0 );
        $to = (int) ( $window['to'] ?? 0 );
        $today = (int) ( $window['today'] ?? 0 );
        if ( $from < 1 || $to < $from || $today < 1 ) {
            return [];
        }

        $end = (string) ( $definition['end_meta_key'] ?? '' );
        $precision = (string) ( $definition['precision_meta_key'] ?? '' );
        $date_only = (string) ( $definition['date_only_value'] ?? 'date' );
        $ongoing = [
            'relation' => 'OR',
            [ 'key' => $start, 'value' => $from, 'compare' => '>=', 'type' => 'NUMERIC' ],
        ];
        if ( '' !== $end ) {
            $ongoing[] = [ 'key' => $end, 'value' => $from, 'compare' => '>=', 'type' => 'NUMERIC' ];
        }
        if ( '' !== $precision ) {
            $ongoing[] = [
                'relation' => 'AND',
                [ 'key' => $precision, 'value' => $date_only, 'compare' => '=' ],
                [ 'key' => $start, 'value' => $today, 'compare' => '>=', 'type' => 'NUMERIC' ],
            ];
            if ( '' !== $end ) {
                $ongoing[] = [
                    'relation' => 'AND',
                    [ 'key' => $precision, 'value' => $date_only, 'compare' => '=' ],
                    [ 'key' => $end, 'value' => $today, 'compare' => '>=', 'type' => 'NUMERIC' ],
                ];
            }
        }

        return [
            'relation' => 'AND',
            [ 'key' => $start, 'value' => $to, 'compare' => '<=', 'type' => 'NUMERIC' ],
            $ongoing,
        ];
    }

    private static function unquoted_mask( string $query ): string {
        $mask = $query;
        $quote = '';
        $escaped = false;
        $length = strlen( $query );
        for ( $index = 0; $index < $length; $index++ ) {
            $character = $query[ $index ];
            if ( $escaped ) {
                if ( '' !== $quote ) {
                    $mask[ $index ] = ' ';
                }
                $escaped = false;
                continue;
            }
            if ( '\\' === $character ) {
                if ( '' !== $quote ) {
                    $mask[ $index ] = ' ';
                }
                $escaped = true;
                continue;
            }
            if ( '' === $quote && ( '"' === $character || "'" === $character ) ) {
                $quote = $character;
                $mask[ $index ] = ' ';
                continue;
            }
            if ( '' !== $quote ) {
                $mask[ $index ] = ' ';
                if ( $character === $quote ) {
                    $quote = '';
                }
            }
        }

        return $mask;
    }

    private static function has_meaningful_text( string $value ): bool {
        return '' !== (string) preg_replace( '/[\s\p{P}\p{S}]+/u', '', $value );
    }

    private static function residual( string $value ): string {
        $value = self::clean( $value );

        return trim( $value, " \t\n\r\0\x0B,;:.!?-" );
    }

    private static function clean( string $value ): string {
        $value = (string) preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $value );

        return trim( (string) preg_replace( '/\s+/u', ' ', $value ) );
    }

    private static function unit_seconds( string $unit ): int {
        $unit = strtolower( trim( $unit ) );
        if ( in_array( $unit, [ 'w', 'week', 'weeks' ], true ) ) {
            return 604800;
        }
        if ( in_array( $unit, [ 'd', 'day', 'days' ], true ) ) {
            return 86400;
        }

        return in_array( $unit, [ 'h', 'hr', 'hrs', 'hour', 'hours' ], true ) ? 3600 : 0;
    }

    private static function timezone( string $name ): \DateTimeZone {
        if ( '' !== $name ) {
            try {
                return new \DateTimeZone( $name );
            } catch ( \Throwable $exception ) {
                // Fall through to the site timezone.
            }
        }
        if ( function_exists( 'wp_timezone' ) ) {
            $timezone = wp_timezone();
            if ( $timezone instanceof \DateTimeZone ) {
                return $timezone;
            }
        }

        return new \DateTimeZone( date_default_timezone_get() ?: 'UTC' );
    }

    /** @param mixed $value */
    private static function key( $value ): string {
        return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( trim( is_scalar( $value ) ? (string) $value : '' ) ) );
    }

    /** @param mixed[] $values @return string[] */
    private static function keys( array $values ): array {
        return array_values( array_unique( array_filter( array_map( [ self::class, 'key' ], $values ) ) ) );
    }

    /** @param mixed $value */
    private static function scalar( $value, string $fallback ): string {
        $value = is_scalar( $value ) ? trim( (string) $value ) : '';

        return '' !== $value ? substr( $value, 0, 40 ) : $fallback;
    }
}
