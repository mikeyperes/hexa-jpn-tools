<?php

namespace Hexa\PluginCore\Calendar;

/**
 * Pure month-grid arithmetic: bounded month range, the weeks shown for one
 * month, and the placement of dated items on those days. No WordPress calls,
 * so every rule is deterministic under test.
 */
final class CalendarGrid {
    /**
     * The months visitors may open, relative to the current month.
     *
     * @return array{current:string,min:string,max:string,min_day:string,max_day:string}
     */
    public static function range( \DateTimeZone $timezone, int $months_back, int $months_ahead, ?int $now = null ): array {
        $current = self::now( $timezone, $now )->modify( 'first day of this month' )->setTime( 0, 0 );
        $min     = $current->modify( '-' . $months_back . ' months' );
        $max     = $current->modify( '+' . $months_ahead . ' months' );

        return [
            'current' => $current->format( 'Y-m' ),
            'min'     => $min->format( 'Y-m' ),
            'max'     => $max->format( 'Y-m' ),
            'min_day' => $min->format( 'Y-m-d' ),
            'max_day' => $max->modify( 'last day of this month' )->format( 'Y-m-d' ),
        ];
    }

    /** A valid 'Y-m' month, or ''. @param mixed $value */
    public static function month( $value ): string {
        $value = is_scalar( $value ) ? trim( (string) $value ) : '';

        return preg_match( '/^(\d{4})-(0[1-9]|1[0-2])$/', $value ) ? $value : '';
    }

    public static function shift( string $month, int $months ): string {
        return self::first_day( $month, new \DateTimeZone( 'UTC' ) )->modify( ( $months >= 0 ? '+' : '' ) . $months . ' months' )->format( 'Y-m' );
    }

    /**
     * Every day shown for a month: whole weeks starting on $week_start (0 = Sunday).
     *
     * @return array{month:string,start:int,end:int,first:string,last:string,days:list<array{date:string,day:int,start:int,in_month:bool,today:bool,past:bool}>}
     */
    public static function build( string $month, \DateTimeZone $timezone, int $week_start, ?int $now = null ): array {
        $first   = self::first_day( $month, $timezone );
        $last    = $first->modify( 'last day of this month' );
        $lead    = ( (int) $first->format( 'w' ) - $week_start + 7 ) % 7;
        $trail   = ( $week_start + 6 - (int) $last->format( 'w' ) + 7 ) % 7;
        $cursor  = $first->modify( '-' . $lead . ' days' );
        $stop    = $last->modify( '+' . $trail . ' days' )->format( 'Y-m-d' );
        $today   = self::now( $timezone, $now )->format( 'Y-m-d' );
        $days    = [];

        while ( true ) {
            $date   = $cursor->format( 'Y-m-d' );
            $days[] = [
                'date'     => $date,
                'day'      => (int) $cursor->format( 'j' ),
                'start'    => $cursor->getTimestamp(),
                'in_month' => $cursor->format( 'Y-m' ) === $month,
                'today'    => $date === $today,
                'past'     => $date < $today,
            ];
            if ( $date === $stop ) {
                break;
            }
            $cursor = $cursor->modify( '+1 day' );
        }

        return [
            'month' => $month,
            'start' => $days[0]['start'],
            'end'   => $cursor->modify( '+1 day' )->getTimestamp(),
            'first' => $days[0]['date'],
            'last'  => $stop,
            'days'  => $days,
        ];
    }

    /**
     * Places items on the grid's days.
     *
     * An item spanning up to $max_span_days appears on each of its days
     * (later days marked `continued`); a longer item appears once, marked
     * `long`, on its first day inside the month (or its first visible day when
     * it lies wholly in the leading or trailing days). An end at exactly local
     * midnight follows $end_midnight: `auto` keeps that day for all-day items
     * (a stored last day) and drops it for timed items (the item ends as the
     * day begins). Each day lists continuing items first, then by start time.
     *
     * @param list<array<string,mixed>> $items Each with int `start`, optional int `end`, bool `all_day`, string `title`.
     * @return array<string,list<array<string,mixed>>> Y-m-d => items.
     */
    public static function place( array $items, array $grid, \DateTimeZone $timezone, int $max_span_days, string $end_midnight = 'auto' ): array {
        $in_month    = array_values( array_filter( $grid['days'], static fn( array $day ): bool => $day['in_month'] ) );
        $month_first = $in_month[0]['date'] ?? $grid['first'];
        $month_last  = $in_month[ count( $in_month ) - 1 ]['date'] ?? $grid['last'];
        $placed      = [];
        foreach ( $items as $item ) {
            $start      = ( new \DateTimeImmutable( '@' . (int) $item['start'] ) )->setTimezone( $timezone );
            $start_date = $start->format( 'Y-m-d' );
            $end_date   = $start_date;
            if ( isset( $item['end'] ) && (int) $item['end'] > (int) $item['start'] ) {
                $end       = ( new \DateTimeImmutable( '@' . (int) $item['end'] ) )->setTimezone( $timezone );
                $inclusive = 'inclusive' === $end_midnight || ( 'auto' === $end_midnight && ! empty( $item['all_day'] ) );
                if ( '00:00:00' === $end->format( 'H:i:s' ) && ! $inclusive ) {
                    $end = $end->modify( '-1 day' );
                }
                $end_date = max( $start_date, $end->format( 'Y-m-d' ) );
            }
            if ( $end_date < $grid['first'] || $start_date > $grid['last'] ) {
                continue;
            }

            $span = (int) \DateTimeImmutable::createFromFormat( '!Y-m-d', $start_date, $timezone )
                ->diff( \DateTimeImmutable::createFromFormat( '!Y-m-d', $end_date, $timezone ) )->days + 1;
            $from = max( $start_date, $grid['first'] );

            if ( $span > $max_span_days ) {
                $anchor = $end_date >= $month_first && $start_date <= $month_last ? max( $start_date, $month_first ) : $from;
                $placed[ $anchor ][] = $item + [ 'continued' => $anchor !== $start_date, 'long' => true, 'until' => $end_date ];
                continue;
            }

            $cursor = \DateTimeImmutable::createFromFormat( '!Y-m-d', $from, $timezone );
            $to     = min( $end_date, $grid['last'] );
            while ( ( $date = $cursor->format( 'Y-m-d' ) ) <= $to ) {
                $placed[ $date ][] = $item + [ 'continued' => $date !== $start_date, 'long' => false, 'until' => $end_date ];
                $cursor = $cursor->modify( '+1 day' );
            }
        }

        foreach ( $placed as &$day_items ) {
            usort( $day_items, static fn( array $a, array $b ): int => [ $b['continued'], $a['start'], (string) $a['title'] ] <=> [ $a['continued'], $b['start'], (string) $b['title'] ] );
        }
        unset( $day_items );

        return $placed;
    }

    private static function first_day( string $month, \DateTimeZone $timezone ): \DateTimeImmutable {
        $first = \DateTimeImmutable::createFromFormat( '!Y-m-d', ( '' !== self::month( $month ) ? $month : '1970-01' ) . '-01', $timezone );

        return $first instanceof \DateTimeImmutable ? $first : new \DateTimeImmutable( '1970-01-01', $timezone );
    }

    private static function now( \DateTimeZone $timezone, ?int $now ): \DateTimeImmutable {
        return ( new \DateTimeImmutable( '@' . (string) ( $now ?? time() ) ) )->setTimezone( $timezone );
    }
}
