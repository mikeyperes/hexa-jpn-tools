<?php

namespace Hexa\PluginCore\Calendar;

use Hexa\PluginCore\PublicComponents\ProfileValues;
use Hexa\PluginCore\QueryFilter\DateRangeFilterType;
use Hexa\PluginCore\QueryFilter\QueryFilterSet;

/**
 * Loads the items overlapping one grid window.
 *
 * For a posts profile this is one bounded SQL query: published, unprotected
 * posts of the declared types whose start is before the window end and whose
 * end (or start, when no end is stored) is on or after the window start,
 * narrowed by the active filters. Items then get their title, link, and the
 * host's batch data for exactly the loaded IDs.
 */
final class CalendarQuery {
    private const ALIAS = 'hcal_p';

    /** @var object|null */
    private $database;

    /** @param object|null $database wpdb-compatible object; defaults to the global $wpdb. */
    public function __construct( $database = null ) {
        $this->database = $database;
    }

    /**
     * @param array<string,mixed> $profile
     * @param array{month:string,filters:array<string,mixed>} $request
     * @return list<array{id:int|string,title:string,url:string,start:int,end:int|null,all_day:bool,data:array<string,mixed>}>
     */
    public function items( array $profile, array $request, int $from, int $to ): array {
        if ( 'callback' === $profile['source'] ) {
            return $this->provided_items( $profile, $request, $from, $to );
        }

        $database = $this->db();
        $rows     = (array) $database->get_results( $this->sql( $database, $profile, $request, $from, $to ), ARRAY_A );
        $timezone = ProfileValues::resolve_timezone( $profile['timezone'] );
        $dated    = [];
        foreach ( $rows as $row ) {
            $id    = (int) ( $row['id'] ?? 0 );
            $start = self::timestamp( (string) ( $row['start_raw'] ?? '' ), $profile['start']['format'], $timezone );
            if ( $id <= 0 || null === $start || isset( $dated[ $id ] ) ) {
                continue;
            }
            $end = self::timestamp( (string) ( $row['end_raw'] ?? '' ), $profile['start']['format'], $timezone );
            $dated[ $id ] = [ 'start' => $start, 'end' => null !== $end && $end > $start ? $end : null ];
        }
        if ( [] === $dated ) {
            return [];
        }

        $ids = array_keys( $dated );
        if ( function_exists( '_prime_post_caches' ) ) {
            _prime_post_caches( $ids, false, is_array( $profile['link'] ) && isset( $profile['link']['meta_key'] ) );
        }
        $data = null !== $profile['prepare'] ? (array) call_user_func( $profile['prepare'], $ids, $request ) : [];

        $items = [];
        foreach ( $dated as $id => $dates ) {
            $item = [
                'id'      => $id,
                'title'   => '',
                'url'     => '',
                'start'   => $dates['start'],
                'end'     => $dates['end'],
                'all_day' => 'date' === $profile['start']['format'] || self::is_midnight( $dates['start'], $timezone ),
                'data'    => (array) ( $data[ $id ] ?? [] ),
            ];
            $item['title'] = null !== $profile['title'] ? (string) call_user_func( $profile['title'], $id, $item ) : ( function_exists( 'get_the_title' ) ? (string) get_the_title( $id ) : '' );
            $item['url']   = $this->link( $profile, $id, $item );
            $items[]       = $item;
        }

        return $items;
    }

    /**
     * The complete posts query. Public for deterministic tests.
     *
     * @param object $database
     */
    public function sql( $database, array $profile, array $request, int $from, int $to ): string {
        $timezone = ProfileValues::resolve_timezone( $profile['timezone'] );
        $format   = $profile['start']['format'];
        $alias    = self::ALIAS;
        $window   = [ ( new \DateTimeImmutable( '@' . $from ) )->setTimezone( $timezone ), ( new \DateTimeImmutable( '@' . $to ) )->setTimezone( $timezone ) ];

        if ( 'column' === $format ) {
            $utc       = new \DateTimeZone( 'UTC' );
            $select    = "{$alias}.post_date_gmt AS start_raw, NULL AS end_raw";
            $joins     = '';
            $order     = "{$alias}.post_date_gmt";
            $overlap   = $database->prepare( "{$alias}.post_date_gmt >= %s AND {$alias}.post_date_gmt < %s", $window[0]->setTimezone( $utc )->format( 'Y-m-d H:i:s' ), $window[1]->setTimezone( $utc )->format( 'Y-m-d H:i:s' ) );
        } else {
            $start_raw = 'hcal_s.meta_value';
            // The later of start and end, so an end stored before its start still shows the item on its start day.
            $end_raw   = null !== $profile['end'] ? DateRangeFilterType::end_expression( $start_raw, 'hcal_e.meta_value', $format ) : $start_raw;
            $select    = "{$start_raw} AS start_raw, " . ( null !== $profile['end'] ? 'hcal_e.meta_value' : 'NULL' ) . ' AS end_raw';
            $joins     = ' INNER JOIN ' . $database->postmeta . ' hcal_s ON hcal_s.post_id = ' . $alias . '.ID AND ' . $database->prepare( 'hcal_s.meta_key = %s', $profile['start']['meta_key'] )
                . ( null !== $profile['end'] ? ' LEFT JOIN ' . $database->postmeta . ' hcal_e ON hcal_e.post_id = ' . $alias . '.ID AND ' . $database->prepare( 'hcal_e.meta_key = %s', $profile['end']['meta_key'] ) : '' );
            $order     = 'timestamp' === $format ? "CAST({$start_raw} AS SIGNED)" : $start_raw;
            $overlap   = "{$start_raw} <> '' AND " . DateRangeFilterType::compare( $database, $start_raw, $format, '<', $window[1] )
                . ' AND ' . DateRangeFilterType::compare( $database, $end_raw, $format, '>=', $window[0] );
        }

        $types   = implode( ', ', array_map( static fn( string $type ): string => $database->prepare( '%s', $type ), $profile['post_types'] ) );
        $clauses = array_merge(
            [
                "{$alias}.post_type IN ({$types})",
                "{$alias}.post_status = 'publish'",
                "{$alias}.post_password = ''",
                $overlap,
            ],
            QueryFilterSet::where( $database, $profile['filters'], $request['filters'], QueryFilterSet::scope( 'calendar', $profile ), $alias )
        );

        return "SELECT {$alias}.ID AS id, {$select} FROM {$database->posts} {$alias}{$joins}"
            . ' WHERE ' . implode( ' AND ', $clauses )
            . " ORDER BY {$order} ASC, {$alias}.ID ASC LIMIT " . (int) $profile['max_items'];
    }

    /** Stored value to Unix seconds in the profile timezone, or null. */
    public static function timestamp( string $raw, string $format, \DateTimeZone $timezone ): ?int {
        $raw = trim( $raw );
        if ( '' === $raw ) {
            return null;
        }
        if ( 'timestamp' === $format ) {
            return is_numeric( $raw ) ? (int) $raw : null;
        }

        $parsed = match ( $format ) {
            'date'   => \DateTimeImmutable::createFromFormat( '!Ymd', substr( $raw, 0, 8 ), $timezone ),
            'column' => \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $raw, new \DateTimeZone( 'UTC' ) ),
            default  => \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', substr( $raw, 0, 19 ), $timezone ),
        };

        return $parsed instanceof \DateTimeImmutable ? $parsed->getTimestamp() : null;
    }

    /** @return list<array<string,mixed>> */
    private function provided_items( array $profile, array $request, int $from, int $to ): array {
        $items = [];
        foreach ( (array) call_user_func( $profile['provider'], $from, $to, $request, $profile ) as $item ) {
            if ( ! is_array( $item ) || ! isset( $item['start'] ) || ! is_numeric( $item['start'] ) || count( $items ) >= $profile['max_items'] ) {
                continue;
            }
            $start   = (int) $item['start'];
            $end     = isset( $item['end'] ) && is_numeric( $item['end'] ) && (int) $item['end'] > $start ? (int) $item['end'] : null;
            $items[] = [
                'id'      => is_scalar( $item['id'] ?? null ) ? $item['id'] : count( $items ),
                'title'   => (string) ( $item['title'] ?? '' ),
                'url'     => (string) ( $item['url'] ?? '' ),
                'start'   => $start,
                'end'     => $end,
                'all_day' => (bool) ( $item['all_day'] ?? false ),
                'data'    => (array) ( $item['data'] ?? [] ),
            ];
        }

        return $items;
    }

    private function link( array $profile, int $id, array $item ): string {
        $link = $profile['link'];
        if ( is_array( $link ) && isset( $link['meta_key'] ) ) {
            $url = function_exists( 'get_post_meta' ) ? trim( (string) get_post_meta( $id, $link['meta_key'], true ) ) : '';
            if ( '' !== $url || ! $link['fallback'] ) {
                return $url;
            }
        } elseif ( is_callable( $link ) ) {
            return (string) call_user_func( $link, $id, $item );
        }

        return function_exists( 'get_permalink' ) ? (string) get_permalink( $id ) : '';
    }

    private static function is_midnight( int $timestamp, \DateTimeZone $timezone ): bool {
        return '00:00:00' === ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone )->format( 'H:i:s' );
    }

    /** @return object */
    private function db() {
        if ( null !== $this->database ) {
            return $this->database;
        }

        global $wpdb;

        return $wpdb;
    }
}
