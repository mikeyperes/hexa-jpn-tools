<?php

namespace Hexa\PluginCore\Calendar;

use Hexa\PluginCore\PublicComponents\ProfileValues;
use Hexa\PluginCore\QueryFilter\DateRangeFilterType;
use Hexa\PluginCore\QueryFilter\QueryFilterSet;

/**
 * Normalizes one host-declared calendar profile.
 *
 * A profile says which items appear (published posts of selected types with a
 * start date and optional end date in custom fields or the post date, or a
 * host `provider` callback), where each item links, which filters visitors
 * may use, and how an item reads. Core owns the month grid, the bounded
 * query, the endpoint, the markup, and the interaction.
 */
final class CalendarProfile {
    public const SOURCES = [ 'posts', 'callback' ];
    public const LINK_PERMALINK = 'permalink';

    /**
     * How an end at exactly local midnight is read: `auto` (inclusive for
     * all-day items, which store a last day at midnight, exclusive for timed
     * items, which end as the day begins), `inclusive`, or `exclusive`.
     */
    public const END_MIDNIGHT = [ 'auto', 'inclusive', 'exclusive' ];

    public const MAX_ITEMS = 2000;
    public const MAX_MONTHS = 60;
    public const MAX_CACHE_TTL = 3600;

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     * @throws \InvalidArgumentException When the profile cannot be served safely.
     */
    public static function normalize( string $id, array $config ): array {
        $id = ProfileValues::key( $id );
        if ( '' === $id ) {
            throw new \InvalidArgumentException( 'A calendar profile needs a non-empty id.' );
        }

        $source = ProfileValues::choice( $config['source'] ?? 'posts', self::SOURCES, '' );
        if ( '' === $source ) {
            throw new \InvalidArgumentException( "Calendar profile '{$id}' has an unsupported source." );
        }

        $post_types = 'posts' === $source ? ProfileValues::keys( (array) ( $config['post_types'] ?? [ 'post' ] ) ) : [];
        $start      = 'posts' === $source ? self::date_field( $config['start'] ?? null, 'timestamp', true ) : null;
        $end        = 'posts' === $source && null !== $start && '' !== $start['meta_key'] ? self::date_field( $config['end'] ?? null, $start['format'], false ) : null;
        if ( null !== $end && $end['format'] !== $start['format'] ) {
            throw new \InvalidArgumentException( "Calendar profile '{$id}' must store its end in the start field's format." );
        }
        $provider   = 'callback' === $source ? ProfileValues::callback( $config['provider'] ?? null ) : null;
        if ( 'posts' === $source && ( [] === $post_types || null === $start ) ) {
            throw new \InvalidArgumentException( "Calendar profile '{$id}' needs post types and a start date field." );
        }
        if ( 'callback' === $source && null === $provider ) {
            throw new \InvalidArgumentException( "Calendar profile '{$id}' needs a callable provider." );
        }

        $timezone     = ProfileValues::timezone( $config['timezone'] ?? '' );
        $months_back  = ProfileValues::bounded_int( $config['months_back'] ?? 12, 12, 0, self::MAX_MONTHS );
        $months_ahead = ProfileValues::bounded_int( $config['months_ahead'] ?? 12, 12, 0, self::MAX_MONTHS );
        $week_start   = ProfileValues::bounded_int( $config['week_start'] ?? ( function_exists( 'get_option' ) ? get_option( 'start_of_week', 0 ) : 0 ), 0, 0, 6 );
        $time_format  = is_string( $config['time_format'] ?? null ) && '' !== trim( $config['time_format'] ) ? $config['time_format'] : ( function_exists( 'get_option' ) ? (string) get_option( 'time_format', 'g:i a' ) : 'g:i a' );

        // Date filters read days in the calendar's timezone unless they declare their own; their month limits are applied per request.
        $filters = QueryFilterSet::normalize( (array) ( $config['filters'] ?? [] ), 'posts' );
        foreach ( $filters as &$filter ) {
            if ( '' === ( $filter['timezone'] ?? null ) ) {
                $filter['timezone'] = $timezone;
            }
        }
        unset( $filter );

        return [
            'id'            => $id,
            'source'        => $source,
            'post_types'    => $post_types,
            'start'         => $start,
            'end'           => $end,
            'provider'      => $provider,
            'timezone'      => $timezone,
            'week_start'    => $week_start,
            'months_back'   => $months_back,
            'months_ahead'  => $months_ahead,
            'max_per_day'   => ProfileValues::bounded_int( $config['max_per_day'] ?? 3, 3, 1, 20 ),
            'max_items'     => ProfileValues::bounded_int( $config['max_items'] ?? 500, 500, 1, self::MAX_ITEMS ),
            'max_span_days' => ProfileValues::bounded_int( $config['max_span_days'] ?? 7, 7, 1, 62 ),
            'end_midnight'  => ProfileValues::choice( $config['end_midnight'] ?? 'auto', self::END_MIDNIGHT, 'auto' ),
            'link'          => self::link( $config['link'] ?? self::LINK_PERMALINK ),
            'link_target'   => '_blank' === ( $config['link_target'] ?? '' ) ? '_blank' : '',
            'title'         => ProfileValues::callback( $config['title'] ?? null ),
            'time_format'   => $time_format,
            'filters'       => $filters,
            'prepare'       => ProfileValues::callback( $config['prepare'] ?? null ),
            'render_item'   => ProfileValues::callback( $config['render_item'] ?? null ),
            'item_class'    => ProfileValues::callback( $config['item_class'] ?? null ),
            'heading_level' => ProfileValues::bounded_int( $config['heading_level'] ?? 2, 2, 2, 6 ),
            'labels'        => self::labels( (array) ( $config['labels'] ?? [] ) ),
            'public'        => (bool) ( $config['public'] ?? true ),
            'cache_ttl'     => ProfileValues::bounded_int( $config['cache_ttl'] ?? 300, 300, 0, self::MAX_CACHE_TTL ),
            'cache_version' => substr( ProfileValues::key( (string) ( $config['cache_version'] ?? '1' ) ), 0, 32 ),
            'class'         => ProfileValues::classes( (string) ( $config['class'] ?? '' ) ),
        ];
    }

    /**
     * A stored date: 'meta_key', ['meta' => key, 'format' => …], or (start only) ['column' => 'date'].
     *
     * @param mixed $value
     * @return array{meta_key:string,column:string,format:string}|null
     */
    private static function date_field( $value, string $default_format, bool $allow_column ): ?array {
        if ( is_string( $value ) ) {
            $value = [ 'meta' => $value ];
        }
        if ( ! is_array( $value ) ) {
            return null;
        }

        $meta_key = ProfileValues::meta_key( $value['meta'] ?? ( $value['meta_key'] ?? '' ) );
        $column   = $allow_column && '' === $meta_key && 'date' === ( $value['column'] ?? '' ) ? 'date' : '';
        if ( '' === $meta_key && '' === $column ) {
            return null;
        }

        return [
            'meta_key' => $meta_key,
            'column'   => $column,
            'format'   => '' !== $column ? 'column' : ProfileValues::choice( $value['format'] ?? $default_format, DateRangeFilterType::FORMATS, 'timestamp' ),
        ];
    }

    /** @param mixed $value @return string|array{meta_key:string}|callable */
    private static function link( $value ) {
        if ( is_callable( $value ) && ! is_string( $value ) ) {
            return $value;
        }
        if ( is_array( $value ) ) {
            $meta_key = ProfileValues::meta_key( $value['meta'] ?? ( $value['meta_key'] ?? '' ) );
            if ( '' !== $meta_key ) {
                return [ 'meta_key' => $meta_key, 'fallback' => self::LINK_PERMALINK === ( $value['fallback'] ?? '' ) ];
            }
        }

        return self::LINK_PERMALINK;
    }

    /** @return array<string,string> */
    private static function labels( array $labels ): array {
        return ProfileValues::labels( [
            'previous'    => 'Previous month',
            'next'        => 'Next month',
            'today'       => 'Today',
            'months'      => 'Change month',
            'apply'       => 'Apply',
            'reset'       => 'Clear filters',
            'more'        => '+%d more',
            'count_one'   => '%d event',
            'count_many'  => '%d events',
            'count_none'  => 'No events',
            'empty'       => 'Nothing is scheduled in %s.',
            'continues'   => 'Continues',
            'until'       => 'Until %s',
            'all_day'     => '',
            'error'       => 'The calendar could not load. Please try again.',
        ], $labels );
    }
}
