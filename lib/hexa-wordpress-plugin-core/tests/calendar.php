<?php

declare(strict_types=1);

$root = dirname( __DIR__ );

// Any notice or warning is a failure: visitor input must never produce PHP diagnostics.
set_error_handler( static function ( int $severity, string $message, string $file, int $line ): bool {
    throw new ErrorException( $message, 0, $severity, $file, $line );
} );
define( 'ARRAY_A', 'ARRAY_A' );

final class FakeCalendarWpdb {
    /** @var list<array<string,string>> */
    public array $rows = [];
    public string $posts = 'wp_posts';
    public string $postmeta = 'wp_postmeta';
    public string $users = 'wp_users';
    public string $usermeta = 'wp_usermeta';
    public string $terms = 'wp_terms';
    public string $term_taxonomy = 'wp_term_taxonomy';
    public string $term_relationships = 'wp_term_relationships';

    public function esc_like( string $value ): string {
        return addcslashes( $value, '_%\\' );
    }

    public function get_results( string $sql, string $output ): array {
        return $this->rows;
    }

    public function prepare( string $sql, mixed ...$values ): string {
        $index = 0;
        return (string) preg_replace_callback(
            '/%[sd]/',
            static function ( array $match ) use ( &$index, $values ): string {
                $value = $values[ $index++ ] ?? '';
                if ( '%d' === $match[0] ) {
                    return (string) (int) $value;
                }
                return "'" . str_replace( "'", "''", (string) $value ) . "'";
            },
            $sql
        );
    }
}

function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function esc_url( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function wp_strip_all_tags( string $value ): string {
    return strip_tags( $value );
}

function _prime_post_caches( array $ids, bool $terms, bool $meta ): void {
}

function get_the_title( int $id ): string {
    return 'Event ' . $id;
}

function get_permalink( int $id ): string {
    return 'https://example.test/?p=' . $id;
}

foreach ( [
    'PublicComponents/ProfileValues', 'PublicComponents/ProfileStore', 'PublicComponents/PublicComponent',
    'QueryFilter/QueryFilterType', 'QueryFilter/MetaFilterType', 'QueryFilter/TaxonomyFilterType', 'QueryFilter/DateRangeFilterType',
    'QueryFilter/CallbackFilterType', 'QueryFilter/QueryFilterTypes', 'QueryFilter/QueryFilterSet',
    'Calendar/CalendarGrid', 'Calendar/CalendarProfile', 'Calendar/CalendarRegistry', 'Calendar/CalendarRequest', 'Calendar/CalendarQuery', 'Calendar/CalendarRenderer',
] as $class ) {
    require $root . '/src/' . $class . '.php';
}

use Hexa\PluginCore\Calendar\CalendarGrid;
use Hexa\PluginCore\Calendar\CalendarProfile;
use Hexa\PluginCore\Calendar\CalendarQuery;
use Hexa\PluginCore\Calendar\CalendarRegistry;
use Hexa\PluginCore\Calendar\CalendarRenderer;
use Hexa\PluginCore\Calendar\CalendarRequest;

$assertions = 0;
$expect = static function ( bool $condition, string $message ) use ( &$assertions ): void {
    $assertions++;
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$ny  = new DateTimeZone( 'America/New_York' );
$now = 1790179200; // 2026-09-23 12:00 EDT.
$at  = static fn( string $local ): int => ( new DateTimeImmutable( $local, $ny ) )->getTimestamp();

// Month range and grid arithmetic.
$range = CalendarGrid::range( $ny, 12, 12, $now );
$expect( [ 'current' => '2026-09', 'min' => '2025-09', 'max' => '2027-09', 'min_day' => '2025-09-01', 'max_day' => '2027-09-30' ] === $range, 'The month range is bounded around the current month.' );
$expect( '2026-01' === CalendarGrid::shift( '2025-12', 1 ) && '2025-12' === CalendarGrid::shift( '2026-01', -1 ), 'Month shifting crosses years.' );
$expect( '' === CalendarGrid::month( '2026-13' ) && '' === CalendarGrid::month( [ '2026-09' ] ) && '2026-09' === CalendarGrid::month( '2026-09' ), 'Only real Y-m months are accepted.' );

$september = CalendarGrid::build( '2026-09', $ny, 0, $now );
$expect( 35 === count( $september['days'] ) && '2026-08-30' === $september['first'] && '2026-10-03' === $september['last'], 'A Sunday-start grid shows whole weeks around the month.' );
$expect( false === $september['days'][0]['in_month'] && true === $september['days'][2]['in_month'], 'Leading days are marked outside the month.' );
$today = array_values( array_filter( $september['days'], static fn( array $day ): bool => $day['today'] ) );
$expect( 1 === count( $today ) && '2026-09-23' === $today[0]['date'], 'Exactly today is marked in the profile timezone.' );
$expect( $september['days'][0]['past'] && ! $today[0]['past'], 'Days before today are marked past.' );
$expect( $at( '2026-08-30 00:00' ) === $september['start'] && $at( '2026-10-04 00:00' ) === $september['end'], 'The grid window runs from the first shown midnight to the midnight after the last.' );

$monday = CalendarGrid::build( '2026-09', $ny, 1, $now );
$expect( '2026-08-31' === $monday['first'] && '2026-10-04' === $monday['last'], 'A Monday-start grid shifts the whole weeks.' );

$november = CalendarGrid::build( '2026-11', $ny, 0, $now );
$midnights = array_filter( $november['days'], static fn( array $day ): bool => '00:00' === ( new DateTimeImmutable( '@' . $day['start'] ) )->setTimezone( $ny )->format( 'H:i' ) );
$expect( count( $midnights ) === count( $november['days'] ) && 35 === count( $november['days'] ), 'Every day starts at local midnight across the DST change.' );

// Placement.
$items = [
    [ 'id' => 1, 'title' => 'Evening talk', 'start' => $at( '2026-09-10 19:00' ), 'end' => null ],
    [ 'id' => 2, 'title' => 'Retreat', 'start' => $at( '2026-09-09 10:00' ), 'end' => $at( '2026-09-11 16:00' ) ],
    [ 'id' => 3, 'title' => 'Overnight', 'start' => $at( '2026-09-12 20:00' ), 'end' => $at( '2026-09-13 00:00' ) ],
    [ 'id' => 4, 'title' => 'Exhibit', 'start' => $at( '2026-08-20 10:00' ), 'end' => $at( '2026-10-20 18:00' ) ],
    [ 'id' => 5, 'title' => 'Morning minyan', 'start' => $at( '2026-09-10 07:00' ), 'end' => null ],
    [ 'id' => 6, 'title' => 'Long ago', 'start' => $at( '2026-07-01 10:00' ), 'end' => $at( '2026-07-02 10:00' ) ],
];
$placed = CalendarGrid::place( $items, $september, $ny, 7 );
$expect( [ 2, 5, 1 ] === array_column( $placed['2026-09-10'], 'id' ), 'Continuing items list first, then by start time.' );
$expect( isset( $placed['2026-09-09'], $placed['2026-09-11'] ) && $placed['2026-09-11'][0]['continued'] && ! $placed['2026-09-09'][0]['continued'], 'A multi-day item appears on each day and marks later days as continued.' );
$expect( [ 3 ] === array_column( $placed['2026-09-12'], 'id' ) && ! isset( $placed['2026-09-13'] ), 'An end at exactly midnight is exclusive.' );
$expect( [ 4 ] === array_column( $placed['2026-09-01'], 'id' ) && $placed['2026-09-01'][0]['long'] && '2026-10-20' === $placed['2026-09-01'][0]['until'], 'An item longer than the span limit appears once, on its first day inside the month.' );
$expect( ! isset( $placed['2026-08-30'] ), 'A long item that runs into the month is not hidden in a leading day.' );
$expect( 1 === count( array_filter( $placed, static fn( array $day ): bool => in_array( 4, array_column( $day, 'id' ), true ) ) ), 'A long item is not repeated on every day.' );
$expect( ! in_array( 6, array_merge( ...array_map( static fn( array $day ): array => array_column( $day, 'id' ), array_values( $placed ) ) ), true ), 'Items outside the grid are not placed.' );

$all_day = [
    [ 'id' => 7, 'title' => 'Book fair', 'start' => $at( '2026-09-14 00:00' ), 'end' => $at( '2026-09-16 00:00' ), 'all_day' => true ],
    [ 'id' => 8, 'title' => 'Late August', 'start' => $at( '2026-08-01 10:00' ), 'end' => $at( '2026-08-30 18:00' ) ],
];
$auto = CalendarGrid::place( $all_day, $september, $ny, 7 );
$expect( isset( $auto['2026-09-14'], $auto['2026-09-15'], $auto['2026-09-16'] ) && ! isset( $auto['2026-09-17'] ), 'An all-day item ending at midnight keeps its stored last day.' );
$expect( ! isset( CalendarGrid::place( $all_day, $september, $ny, 7, 'exclusive' )['2026-09-16'] ), 'end_midnight exclusive drops the midnight day.' );
$expect( isset( CalendarGrid::place( [ $items[2] ], $september, $ny, 7, 'inclusive' )['2026-09-13'] ), 'end_midnight inclusive keeps the midnight day for timed items.' );
$expect( [ 8 ] === array_column( $auto['2026-08-30'] ?? [], 'id' ), 'A long item that ends before the month stays on its last visible leading day.' );

// Profiles.
$threw = false;
try {
    CalendarProfile::normalize( 'broken', [ 'post_types' => [ 'event' ] ] );
} catch ( InvalidArgumentException $exception ) {
    $threw = true;
}
$expect( $threw, 'A posts calendar without a start date field is rejected.' );
$threw = false;
try {
    CalendarProfile::normalize( 'broken', [ 'source' => 'callback' ] );
} catch ( InvalidArgumentException $exception ) {
    $threw = true;
}
$expect( $threw, 'A callback calendar without a provider is rejected.' );

$events = CalendarProfile::normalize( 'Events', [
    'post_types'  => [ 'event' ],
    'start'       => 'start_date_timestamp',
    'end'         => [ 'meta' => 'end_date_timestamp' ],
    'timezone'    => 'America/New_York',
    'link'        => [ 'meta' => 'ticket_url', 'fallback' => 'permalink' ],
    'max_per_day' => 99,
    'filters'     => [
        'area'  => [ 'type' => 'taxonomy', 'taxonomy' => 'area', 'options' => [ 'miami' => 'Miami' ] ],
        'dates' => [ 'type' => 'date_range', 'meta_key' => 'start_date_timestamp', 'timezone' => 'America/New_York' ],
        'kids'  => [ 'type' => 'meta', 'meta_key' => 'kids_event', 'control' => 'toggle' ],
    ],
] );
$expect( 'events' === $events['id'] && [ 'meta_key' => 'start_date_timestamp', 'column' => '', 'format' => 'timestamp' ] === $events['start'], 'A plain start string means a timestamp custom field.' );
$expect( [ 'meta_key' => 'ticket_url', 'fallback' => true ] === $events['link'], 'A link can come from a custom field with a permalink fallback.' );
$expect( 20 === $events['max_per_day'] && 12 === $events['months_ahead'], 'Numeric options are bounded.' );
$expect( '' === $events['filters']['dates']['min'], 'Profiles store no time-dependent limits.' );
$request_filters = CalendarRequest::filters( $events, $now );
$expect( '2025-09-01' === $request_filters['dates']['min'] && '2027-09-30' === $request_filters['dates']['max'], 'Each request applies its own month window as date-control limits.' );
$zoned = CalendarProfile::normalize( 'zoned', [ 'post_types' => [ 'event' ], 'start' => 'start', 'timezone' => 'America/Chicago', 'filters' => [ 'd' => [ 'type' => 'date_range', 'meta_key' => 'start' ], 'e' => [ 'type' => 'date_range', 'meta_key' => 'start', 'timezone' => 'Europe/Paris' ] ] ] );
$expect( 'America/Chicago' === $zoned['filters']['d']['timezone'] && 'Europe/Paris' === $zoned['filters']['e']['timezone'], 'Date filters read days in the calendar timezone unless they declare one.' );
$threw = false;
try {
    CalendarProfile::normalize( 'mixed', [ 'post_types' => [ 'event' ], 'start' => [ 'meta' => 's', 'format' => 'datetime' ], 'end' => [ 'meta' => 'e', 'format' => 'date' ] ] );
} catch ( InvalidArgumentException $exception ) {
    $threw = true;
}
$expect( $threw, 'An end stored in a different format than the start is rejected.' );
$expect( 'datetime' === CalendarProfile::normalize( 'same', [ 'post_types' => [ 'event' ], 'start' => [ 'meta' => 's', 'format' => 'datetime' ], 'end' => 'e' ] )['end']['format'], 'An end without a format inherits the start format.' );

// Requests.
$request = CalendarRequest::from_input( [ 'cmonth' => '2031-01', 'cfilter' => [ 'area' => 'miami', 'kids' => '1' ] ], $events, $now );
$expect( '2027-09' === $request['month'] && [ 'area' => 'miami', 'kids' => '1' ] === $request['filters'], 'Months are clamped to the range and filters parsed.' );
$expect( '2026-09' === CalendarRequest::from_input( [ 'cmonth' => 'bogus' ], $events, $now )['month'], 'An invalid month opens the current month.' );
$expect( '2026-11' === CalendarRequest::from_input( [ 'cmonth' => '2026-09', 'cfilter' => [ 'dates' => [ 'from' => '2026-11-05', 'to' => '2026-11-20' ] ] ], $events, $now )['month'], 'A date range outside the month moves to the range.' );
$expect( '2026-11' === CalendarRequest::from_input( [ 'cmonth' => '2026-11', 'cfilter' => [ 'dates' => [ 'from' => '2026-10-25', 'to' => '2026-11-20' ] ] ], $events, $now )['month'], 'A month inside the date range stays put.' );
$expect( '2026-07' === CalendarRequest::from_input( [ 'cmonth' => '2026-09', 'cfilter' => [ 'dates' => [ 'from' => '2026-07-01', 'to' => '2026-08-05' ] ] ], $events, $now )['month'], 'A past date range opens at its first month.' );
$expect( '2026-11' === CalendarRequest::from_input( [ 'cmonth' => '2027-02', 'cfilter' => [ 'dates' => [ 'to' => '2026-11-20' ] ] ], $events, $now )['month'], 'An end-only range clamps to its last month.' );
$expect( '2026-09' === CalendarRequest::from_input( [ 'cfilter' => [ 'dates' => [ 'to' => '2026-11-20' ] ] ], $events, $now )['month'], 'Without a month, an end-only range keeps today when allowed.' );
$expect( [ 'current' => '2026-09', 'min' => '2026-09', 'max' => '2026-09', 'anchored' => true ] === CalendarRequest::bounds( $events, [ 'dates' => [ 'from' => '2026-09-10', 'to' => '2026-09-12' ] ], $now ), 'An active date range narrows the months visitors may open.' );
$expect( [ 'cal' => 'events', 'cmonth' => '2026-10', 'cfilter' => [ 'kids' => '1' ] ] === CalendarRequest::to_args( [ 'month' => '2026-09', 'filters' => [ 'kids' => '1' ] ], $events, '2026-10' ), 'Month links keep filters and name their calendar.' );

// Query.
$db    = new FakeCalendarWpdb();
$query = new CalendarQuery( $db );
$sql   = $query->sql( $db, $events, [ 'month' => '2026-09', 'filters' => [ 'area' => 'miami', 'kids' => '1' ] ], $september['start'], $september['end'] );
$expect( str_contains( $sql, "INNER JOIN wp_postmeta hcal_s ON hcal_s.post_id = hcal_p.ID AND hcal_s.meta_key = 'start_date_timestamp'" ) && str_contains( $sql, "LEFT JOIN wp_postmeta hcal_e ON hcal_e.post_id = hcal_p.ID AND hcal_e.meta_key = 'end_date_timestamp'" ), 'Start and end come from indexed meta joins.' );
$expect( str_contains( $sql, 'CAST(hcal_s.meta_value AS SIGNED) < ' . $september['end'] ) && str_contains( $sql, "CAST(COALESCE(NULLIF(hcal_e.meta_value, ''), hcal_s.meta_value) AS SIGNED)) AS SIGNED) >= " . $september['start'] ), 'Items overlapping the grid window are selected; a missing end means the start.' );
$expect( str_contains( $sql, "hcal_p.post_type IN ('event') AND hcal_p.post_status = 'publish' AND hcal_p.post_password = ''" ), 'Only published, unprotected posts of the declared types appear.' );
$expect( str_contains( $sql, "hcal_p_f1_t.slug = 'miami'" ) && str_contains( $sql, "hcal_p_f2.meta_key = 'kids_event'" ), 'Visitor filters narrow the calendar through the shared filter set.' );
$expect( str_ends_with( $sql, 'ORDER BY CAST(hcal_s.meta_value AS SIGNED) ASC, hcal_p.ID ASC LIMIT 500' ), 'Items load in start order within the item limit.' );
$expect( str_contains( $sql, "GREATEST(CAST(hcal_s.meta_value AS SIGNED), CAST(COALESCE(NULLIF(hcal_e.meta_value, ''), hcal_s.meta_value) AS SIGNED))" ), 'An end stored before its start never hides the item from its start month.' );

$db->rows = [ [ 'id' => '11', 'start_raw' => (string) $at( '2026-09-10 19:00' ), 'end_raw' => '' ], [ 'id' => '11', 'start_raw' => '1', 'end_raw' => '' ], [ 'id' => '12', 'start_raw' => (string) $at( '2026-09-11 00:00' ), 'end_raw' => (string) $at( '2026-09-10 00:00' ) ] ];
$closure = CalendarProfile::normalize( 'closure', [ 'post_types' => [ 'event' ], 'start' => 'start', 'end' => 'end', 'timezone' => 'America/New_York', 'link' => static fn( int $id, array $item ): string => 'https://tickets.test/' . $id ] );
$loaded  = ( new CalendarQuery( $db ) )->items( $closure, [ 'month' => '2026-09', 'filters' => [] ], $september['start'], $september['end'] );
$expect( 2 === count( $loaded ) && 'https://tickets.test/11' === $loaded[0]['url'] && 'Event 11' === $loaded[0]['title'], 'A callable link builds each item URL without breaking the render.' );
$expect( ! $loaded[0]['all_day'] && $loaded[1]['all_day'] && null === $loaded[1]['end'], 'Items at local midnight are all-day, and an end before the start is dropped.' );
$permalink = ( new CalendarQuery( $db ) )->items( $events, [ 'month' => '2026-09', 'filters' => [] ], $september['start'], $september['end'] );
$expect( 'https://example.test/?p=11' === $permalink[0]['url'], 'Permalinks are the default link.' );

$acf = CalendarProfile::normalize( 'acf', [ 'post_types' => [ 'event' ], 'start' => [ 'meta' => 'event_start', 'format' => 'datetime' ], 'timezone' => 'America/New_York' ] );
$acf_sql = $query->sql( $db, $acf, [ 'month' => '2026-09', 'filters' => [] ], $september['start'], $september['end'] );
$expect( str_contains( $acf_sql, "hcal_s.meta_value < '2026-10-04 00:00:00'" ) && str_contains( $acf_sql, "hcal_s.meta_value >= '2026-08-30 00:00:00'" ), 'ACF date-time fields compare local strings.' );
$posted = CalendarProfile::normalize( 'posted', [ 'post_types' => [ 'post' ], 'start' => [ 'column' => 'date' ], 'timezone' => 'America/New_York' ] );
$expect( str_contains( $query->sql( $db, $posted, [ 'month' => '2026-09', 'filters' => [] ], $september['start'], $september['end'] ), "hcal_p.post_date_gmt >= '2026-08-30 04:00:00'" ), 'A post-date calendar compares UTC post dates.' );
$expect( $at( '2026-09-10 00:00' ) === CalendarQuery::timestamp( '20260910', 'date', $ny ) && $at( '2026-09-10 19:30' ) === CalendarQuery::timestamp( '2026-09-10 19:30:00', 'datetime', $ny ) && null === CalendarQuery::timestamp( 'x', 'timestamp', $ny ), 'Stored dates convert to timestamps per format.' );

// Rendering (callback source; no database).
CalendarRegistry::register( 'demo', [
    'source'      => 'callback',
    'timezone'    => 'America/New_York',
    'week_start'  => 0,
    'max_per_day' => 2,
    'time_format' => 'g:ia',
    'months_back' => 0,
    'provider'    => static fn( int $from, int $to, array $request ): array => '2026-09' !== $request['month'] ? [] : [
        [ 'id' => 5, 'title' => 'Festival', 'url' => 'https://example.test/festival/', 'start' => $at( '2026-08-25 10:00' ), 'end' => $at( '2026-09-20 18:00' ) ],
        [ 'id' => 1, 'title' => 'Talk [x]', 'url' => 'https://example.test/talk/', 'start' => $at( '2026-09-10 19:00' ) ],
        [ 'id' => 2, 'title' => 'Dinner', 'url' => 'https://example.test/dinner/', 'start' => $at( '2026-09-10 20:00' ) ],
        [ 'id' => 3, 'title' => 'Late <b>show</b>', 'url' => 'https://example.test/late/', 'start' => $at( '2026-09-10 22:00' ) ],
        [ 'id' => 4, 'title' => 'Holiday', 'url' => '', 'start' => $at( '2026-09-21 00:00' ), 'all_day' => true ],
    ],
    'item_class'  => static fn( array $item ): string => 1 === $item['id'] ? 'is-featured bad"class' : '',
    'filters'     => [ 'dates' => [ 'type' => 'callback', 'control' => 'date_range', 'apply' => static fn(): ?array => null ] ],
] );
$renderer = new CalendarRenderer();
$profile  = CalendarRegistry::get( 'demo' );
$month    = $renderer->month( $profile, [ 'month' => '2026-09', 'filters' => [] ], '/?lang=he', $now );
$html     = $month['html'];
$expect( '2026-09' === $month['month'] && 5 === $month['total'] && 'September 2026' === $month['label'] && 'September 2026 · 5 events' === $month['status'], 'The month fragment reports its month, label, count, and status text.' );
$expect( ! str_contains( $html, 'hcal-status' ), 'The live status is not part of the swapped fragment.' );
$expect( str_contains( $html, '<span class="hcal-time">Until Sep 20</span><span class="hcal-name">Festival</span>' ), 'A long item that began last month states its end inside this month.' );
$expect( str_contains( $html, '<h2 class="hcal-title" tabindex="-1">September 2026</h2>' ), 'The month title can take focus after navigation.' );
$expect( str_contains( $html, '<a class="hcal-item is-featured badclass" href="https://example.test/talk/"' ), 'Each item links to its own URL with sanitized host classes.' );
$expect( str_contains( $html, '<span class="hcal-time">7:00pm</span><span class="hcal-name">Talk &#091;x&#093;</span>' ), 'Items show time and title, inert to shortcode parsing.' );
$expect( str_contains( $html, 'Late &lt;b&gt;show&lt;/b&gt;' ), 'Titles are escaped.' );
$expect( str_contains( $html, '<details class="hcal-more"><summary>+1 more</summary>' ), 'Items beyond the per-day limit collapse behind a count.' );
$expect( str_contains( $html, '<span class="hcal-item"><span class="hcal-name">Holiday</span></span>' ), 'An all-day item without a URL renders without a link or time.' );
$expect( ! preg_match( '/<li class="hcal-day[^"]*"[^>]*(onclick|href|tabindex|role="button")/', $html ), 'Days themselves are not interactive.' );
$expect( str_contains( $html, '<li class="hcal-day is-today" aria-current="date">' ), 'Today is marked for styling and assistive technology.' );
$expect( str_contains( $html, '<span class="hcal-btn hcal-prev" aria-disabled="true"' ), 'Navigation stops at the first allowed month.' );
$expect( str_contains( $html, 'href="/?lang=he&amp;cal=demo&amp;cmonth=2026-10" rel="nofollow" data-hcal-month="2026-10"' ), 'Month links keep page arguments, carry rel=nofollow, and name the target month.' );
$expect( str_contains( $html, '<ol class="hcal-weekdays" aria-hidden="true"><li>Sun</li>' ), 'Weekday headers follow the week start.' );

$empty = $renderer->month( $profile, [ 'month' => '2026-10', 'filters' => [] ], '/', $now );
$expect( 0 === $empty['total'] && str_contains( $empty['html'], '<p class="hcal-empty">Nothing is scheduled in October 2026.</p>' ), 'An empty month says so.' );
$ranged = $renderer->month( $profile, [ 'month' => '2026-09', 'filters' => [ 'dates' => [ 'from' => '2026-09-10', 'to' => '2026-09-12' ] ] ], '/', $now );
$expect( str_contains( $ranged['html'], '<span class="hcal-btn hcal-next" aria-disabled="true"' ) && ! str_contains( $ranged['html'], 'data-hcal-month="2026-10"' ), 'Months outside an active date range are not offered.' );
$expect( str_contains( $ranged['html'], 'cfilter%5Bdates%5D%5Bfrom%5D=2026-09-10' ), 'Month links preserve active date filters.' );
$expect( 32 === substr_count( $ranged['html'], 'is-out-of-range' ), 'The 32 shown days outside a Sept 10–12 range are dimmed.' );

$shortcode = $renderer->render( 'demo', [ 'cmonth' => '2026-09', 'cfilter' => [ 'dates' => [ 'from' => '2026-09-10' ] ] ], $now );
$expect( str_contains( $shortcode, '<style id="hexa-calendar-css">' ) && str_contains( $shortcode, '<script id="hexa-calendar-js">' ), 'The first calendar prints its small inline assets.' );
$expect( str_contains( $shortcode, '<p class="hcal-status" role="status" aria-live="polite" aria-atomic="true">September 2026 · 5 events</p><div class="hcal-body">' ), 'The live status sits outside the swapped month fragment.' );
$expect( str_contains( $shortcode, 'min="2026-09-01" max="2027-09-30"' ), 'Date controls use this request\'s month window.' );
$expect( str_contains( $shortcode, '<input type="hidden" name="cal" value="demo"><input type="hidden" name="cmonth" value="2026-09">' ), 'The filter form keeps the calendar and month without JavaScript.' );
// Brackets are entity-encoded by the shortcode-inert pass; browsers decode attribute values back to cfilter[dates][from].
$expect( str_contains( $shortcode, 'name="cfilter&#091;dates&#093;&#091;from&#093;" value="2026-09-10"' ) && ! str_contains( $shortcode, 'rel="nofollow" hidden>' ), 'Active filters are shown and the clear link appears.' );
$expect( 'cfilter[dates][from]' === html_entity_decode( 'cfilter&#091;dates&#093;&#091;from&#093;', ENT_QUOTES | ENT_HTML5 ), 'Encoded field names decode to the real parameter names.' );
$expect( ! str_contains( $renderer->render( 'demo', [], $now ), 'hexa-calendar-css' ), 'Assets print once per page.' );
$expect( str_contains( $renderer->render( 'demo', [ 'cal' => 'other', 'cmonth' => '2026-10' ], $now ), 'data-hcal-month="2026-09"' ), 'URL state that belongs to another calendar is ignored.' );
$expect( '/' === CalendarRenderer::sanitize_base( '/?cmonth=2026-09&cal=demo&cfilter[a]=b' ), 'Calendar parameters are removed from the base path.' );
$expect( '/events/?lang=he' === CalendarRenderer::sanitize_base( '/events/?utm_source=news&UTM_Medium=x&gclid=ABC&fbclid=1&lang=he' ), 'Tracking parameters never reach cached component links.' );
$expect( str_contains( $renderer->render( 'demo', [ 'cal' => [ 'x' ], 'cmonth' => [ '2026-09' ], 'cfilter' => 'x' ], $now ), 'data-hcal-month="2026-09"' ), 'Array-shaped visitor parameters are ignored without warnings.' );

// Profiles resolve lazily, so a filter type may register after a profile that uses it; broken profiles are skipped.
final class WeekendFilterType extends \Hexa\PluginCore\QueryFilter\QueryFilterType {
    public function controls(): array {
        return [ 'toggle' ];
    }

    public function where( $database, array $filter, $value, array $context ): string {
        return '1' === $value ? 'DAYOFWEEK(1) IN (1,7)' : '';
    }
}
CalendarRegistry::register( 'lazy', [ 'post_types' => [ 'event' ], 'start' => 'start', 'filters' => [ 'weekend' => [ 'type' => 'weekend' ] ] ] );
\Hexa\PluginCore\QueryFilter\QueryFilterTypes::register( 'weekend', new WeekendFilterType() );
CalendarRegistry::register( 'broken', [ 'post_types' => [ 'event' ] ] );
$expect( isset( CalendarRegistry::get( 'lazy' )['filters']['weekend'] ), 'A custom type registered after its profile still applies.' );
$expect( null === CalendarRegistry::get( 'broken' ), 'A profile that cannot be served is skipped instead of breaking the page.' );
$expect( str_contains( CalendarRenderer::css(), '.hcal-day>.hcal-items,.hcal-day>.hcal-more{grid-column:2}' ) && str_contains( CalendarRenderer::css(), '.hcal-range-inputs .hcal-input{flex:1 1 0;min-width:0;width:100%}' ), 'Mobile keeps extra items in the content column and date inputs inside the viewport.' );
$expect( str_contains( CalendarRenderer::js(), "eu.href.indexOf('hexa-plugin-core/v1/calendar/')" ) && str_contains( CalendarRenderer::js(), 'status.textContent=d.status' ), 'The script only calls its own same-site endpoint and updates the persistent status.' );
$expect( strlen( CalendarRenderer::css() ) < 6500 && strlen( CalendarRenderer::js() ) < 5000 && strlen( (string) gzencode( CalendarRenderer::css() . CalendarRenderer::js(), 9 ) ) < 3600, 'Calendar assets stay lightweight (under about 3.5 KB gzipped together).' );
$expect( str_contains( $shortcode, 'data-hcal-today="2026-09-23" data-hcal-tz="America/New_York"' ), 'The rendered day and timezone let a cached page detect that it is stale.' );

echo "PASS: calendar contract ({$assertions} assertions).\n";
