<?php

namespace Hexa\PluginCore\Calendar;

use Hexa\PluginCore\PublicComponents\ProfileValues;
use Hexa\PluginCore\QueryFilter\QueryFilterSet;

/**
 * Normalizes untrusted visitor input for one calendar profile.
 *
 * The same public parameter names serve the no-JavaScript filter form, the
 * month links, shareable URLs, and the REST endpoint.
 */
final class CalendarRequest {
    public const PARAM_MONTH = 'cmonth';
    public const PARAM_FILTER = 'cfilter';

    /** Names the calendar that owns the URL state, so several calendars can share a page. */
    public const PARAM_CALENDAR = 'cal';

    /**
     * The month must lie in the profile's range, narrowed to an active
     * date-range filter. A missing month opens today's month when allowed. An
     * out-of-range month opens the date range's first month when the range has
     * a start day, else the nearest allowed month.
     *
     * @param array<string,mixed> $input   Unslashed parameters ($_GET or REST query params).
     * @param array<string,mixed> $profile Normalized profile.
     * @return array{month:string,filters:array<string,mixed>}
     */
    public static function from_input( array $input, array $profile, ?int $now = null ): array {
        $filters = QueryFilterSet::parse( self::filters( $profile, $now ), $input[ self::PARAM_FILTER ] ?? [], QueryFilterSet::scope( 'calendar', $profile ) );
        $bounds  = self::bounds( $profile, $filters, $now );
        $month   = CalendarGrid::month( $input[ self::PARAM_MONTH ] ?? '' );
        $month   = '' !== $month ? $month : $bounds['current'];

        if ( $month < $bounds['min'] || $month > $bounds['max'] ) {
            $month = $bounds['anchored'] ? $bounds['min'] : max( $bounds['min'], min( $bounds['max'], $month ) );
        }

        return [ 'month' => $month, 'filters' => $filters ];
    }

    /**
     * The profile's filters with this request's month window as date-control
     * limits wherever the host declared none.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function filters( array $profile, ?int $now = null ): array {
        $range   = CalendarGrid::range( ProfileValues::resolve_timezone( $profile['timezone'] ), $profile['months_back'], $profile['months_ahead'], $now );
        $filters = $profile['filters'];
        foreach ( $filters as &$filter ) {
            if ( QueryFilterSet::CONTROL_DATE_RANGE === $filter['control'] ) {
                $filter['min'] = '' !== $filter['min'] ? $filter['min'] : $range['min_day'];
                $filter['max'] = '' !== $filter['max'] ? $filter['max'] : $range['max_day'];
            }
        }
        unset( $filter );

        return $filters;
    }

    /**
     * Months a visitor may open for these filter values: the profile window,
     * narrowed to an active date range.
     *
     * @param array<string,mixed> $values Parsed filter values.
     * @return array{current:string,min:string,max:string,anchored:bool}
     */
    public static function bounds( array $profile, array $values, ?int $now = null ): array {
        $range = CalendarGrid::range( ProfileValues::resolve_timezone( $profile['timezone'] ), $profile['months_back'], $profile['months_ahead'], $now );
        $dates = QueryFilterSet::active_date_range( $profile['filters'], $values );
        $min   = $range['min'];
        $max   = $range['max'];
        if ( null !== $dates ) {
            $min = '' !== $dates['from'] ? max( $min, substr( $dates['from'], 0, 7 ) ) : $min;
            $max = '' !== $dates['to'] ? min( $max, substr( $dates['to'], 0, 7 ) ) : $max;
        }

        $max = max( $min, $max );
        // Today's month when it is allowed; otherwise the range's first month, or its last when only an end day is set.
        $current = $range['current'];
        if ( $current < $min || $current > $max ) {
            $current = null !== $dates && '' === $dates['from'] ? $max : $min;
        }

        return [ 'current' => $current, 'min' => $min, 'max' => $max, 'anchored' => null !== $dates && '' !== $dates['from'] ];
    }

    /**
     * Public query arguments for a month link, a shareable URL, or a REST call.
     *
     * @param array{month:string,filters:array<string,mixed>} $request
     * @return array<string,mixed>
     */
    public static function to_args( array $request, array $profile, ?string $month = null ): array {
        $args = [ self::PARAM_CALENDAR => $profile['id'], self::PARAM_MONTH => $month ?? $request['month'] ];
        if ( [] !== $request['filters'] ) {
            $args[ self::PARAM_FILTER ] = QueryFilterSet::to_args( $request['filters'] );
        }

        return $args;
    }
}
