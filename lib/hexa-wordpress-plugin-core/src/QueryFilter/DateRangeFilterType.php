<?php

namespace Hexa\PluginCore\QueryFilter;

use Hexa\PluginCore\PublicComponents\ProfileValues;

/**
 * Filters by a date between two visitor-selected days (either side optional).
 *
 * The date lives in a custom field (`meta_key` plus `format`: `timestamp` for
 * Unix seconds, `datetime` for 'Y-m-d H:i:s' such as ACF date-time pickers, or
 * `date` for 'Ymd' such as ACF date pickers) or in a core column (`column`:
 * posts `date`/`modified`, users `registered`). Days are interpreted in the
 * filter's `timezone`, else the site timezone.
 *
 * With `end_meta_key` (same format) the filter matches items that overlap the
 * range: they start before the range ends and end (or start, when no end is
 * stored) on or after the range starts, so ongoing multi-day items stay.
 */
final class DateRangeFilterType extends QueryFilterType {
    public const FORMATS = [ 'timestamp', 'datetime', 'date' ];
    public const POST_COLUMNS = [ 'date' => 'post_date_gmt', 'modified' => 'post_modified_gmt' ];
    public const USER_COLUMNS = [ 'registered' => 'user_registered' ];

    public function controls(): array {
        return [ QueryFilterSet::CONTROL_DATE_RANGE ];
    }

    public function normalize( array $definition, string $source ): ?array {
        $meta_key = ProfileValues::meta_key( $definition['meta_key'] ?? '' );
        $columns  = 'users' === $source ? self::USER_COLUMNS : self::POST_COLUMNS;
        $column   = ProfileValues::key( (string) ( $definition['column'] ?? '' ) );
        if ( '' === $meta_key && ! isset( $columns[ $column ] ) ) {
            return null;
        }

        return [
            'meta_key'     => $meta_key,
            'end_meta_key' => '' !== $meta_key ? ProfileValues::meta_key( $definition['end_meta_key'] ?? '' ) : '',
            'column'       => '' === $meta_key ? $column : '',
            'format'       => ProfileValues::choice( $definition['format'] ?? 'timestamp', self::FORMATS, 'timestamp' ),
            'timezone'     => ProfileValues::timezone( $definition['timezone'] ?? '' ),
        ];
    }

    public function where( $database, array $filter, $value, array $context ): string {
        [ $from, $to ] = self::bounds( $value, ProfileValues::resolve_timezone( (string) $filter['timezone'] ) );
        if ( null === $from && null === $to ) {
            return '';
        }

        if ( '' !== $filter['column'] ) {
            $columns = 'users' === $context['source'] ? self::USER_COLUMNS : self::POST_COLUMNS;
            $utc     = new \DateTimeZone( 'UTC' );
            $clauses = [];
            if ( null !== $from ) {
                $clauses[] = $database->prepare( $context['table'] . '.' . $columns[ $filter['column'] ] . ' >= %s', $from->setTimezone( $utc )->format( 'Y-m-d H:i:s' ) );
            }
            if ( null !== $to ) {
                $clauses[] = $database->prepare( $context['table'] . '.' . $columns[ $filter['column'] ] . ' < %s', $to->setTimezone( $utc )->format( 'Y-m-d H:i:s' ) );
            }

            return '(' . implode( ' AND ', $clauses ) . ')';
        }

        [ $table, $owner ] = $this->meta_table( $database, (string) $context['source'] );
        $alias   = (string) $context['alias'];
        $format  = (string) $filter['format'];
        $start   = $alias . '.meta_value';
        $end     = '' !== $filter['end_meta_key'] ? self::end_expression( $start, $alias . '_e.meta_value', $format ) : $start;
        $clauses = [];
        if ( null !== $from ) {
            $clauses[] = self::compare( $database, $end, $format, '>=', $from );
        }
        if ( null !== $to ) {
            $clauses[] = self::compare( $database, $start, $format, '<', $to );
        }

        return 'EXISTS (SELECT 1 FROM ' . $table . ' ' . $alias
            . ( '' !== $filter['end_meta_key']
                ? ' LEFT JOIN ' . $table . ' ' . $alias . '_e ON ' . $alias . '_e.' . $owner . ' = ' . $alias . '.' . $owner . ' AND ' . $database->prepare( "{$alias}_e.meta_key = %s", $filter['end_meta_key'] )
                : '' )
            . ' WHERE ' . $alias . '.' . $owner . ' = ' . $context['table'] . '.ID'
            . ' AND ' . $database->prepare( "{$alias}.meta_key = %s", $filter['meta_key'] )
            . " AND {$alias}.meta_value <> ''"
            . ' AND ' . implode( ' AND ', $clauses ) . ')';
    }

    /**
     * The later of a stored start and its optional end, in the stored format.
     * An empty end means the start; an end before the start never ends earlier.
     */
    public static function end_expression( string $start, string $end, string $format ): string {
        $fallback = "COALESCE(NULLIF({$end}, ''), {$start})";

        return 'timestamp' === $format
            ? "GREATEST(CAST({$start} AS SIGNED), CAST({$fallback} AS SIGNED))"
            : "GREATEST({$start}, {$fallback})";
    }

    /**
     * Start of the first selected day and start of the day after the last one.
     *
     * @param mixed $value Parsed value: ['from' => 'Y-m-d'|'', 'to' => 'Y-m-d'|''].
     * @return array{0:\DateTimeImmutable|null,1:\DateTimeImmutable|null}
     */
    public static function bounds( $value, \DateTimeZone $timezone ): array {
        if ( ! is_array( $value ) ) {
            return [ null, null ];
        }

        $day = static function ( $date ) use ( $timezone ): ?\DateTimeImmutable {
            $parsed = is_string( $date ) && '' !== $date ? \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $timezone ) : false;

            return $parsed instanceof \DateTimeImmutable ? $parsed : null;
        };
        $to = $day( $value['to'] ?? '' );

        return [ $day( $value['from'] ?? '' ), null !== $to ? $to->modify( '+1 day' ) : null ];
    }

    /**
     * One comparison of a stored date expression against a bound, in the
     * stored format. Shared by date-range filters and the Calendar query.
     *
     * @param object $database
     */
    public static function compare( $database, string $expression, string $format, string $operator, \DateTimeImmutable $bound ): string {
        $operator = in_array( $operator, [ '<', '<=', '>', '>=' ], true ) ? $operator : '>=';
        if ( 'timestamp' === $format ) {
            return $database->prepare( "CAST({$expression} AS SIGNED) {$operator} %d", $bound->getTimestamp() );
        }

        return $database->prepare( "{$expression} {$operator} %s", $bound->format( 'date' === $format ? 'Ymd' : 'Y-m-d H:i:s' ) );
    }
}
