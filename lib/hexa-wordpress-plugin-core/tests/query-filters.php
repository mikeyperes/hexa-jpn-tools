<?php

declare(strict_types=1);

$root = dirname( __DIR__ );

set_error_handler( static function ( int $severity, string $message, string $file, int $line ): bool {
    throw new ErrorException( $message, 0, $severity, $file, $line );
} );

final class FakeFilterWpdb {
    public string $prefix = 'wp_';
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

final class WP_Term_Stub {
    public function __construct( public int $term_id, public string $slug, public string $name ) {
    }
}

function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function get_terms( array $args ): array {
    return 'area' === $args['taxonomy'] && true === $args['hide_empty']
        ? [ new WP_Term_Stub( 18, 'miami', 'Miami' ), new WP_Term_Stub( 43, 'boca-raton', 'Boca Raton' ) ]
        : [];
}

function is_taxonomy_hierarchical( string $taxonomy ): bool {
    return 'region' === $taxonomy;
}

function get_term_by( string $field, $value, string $taxonomy ) {
    return 'region' === $taxonomy && ( 'south-florida' === $value || 30 === (int) $value ) ? new WP_Term_Stub( 30, 'south-florida', 'South Florida' ) : false;
}

function get_term_children( int $term_id, string $taxonomy ): array {
    return 30 === $term_id ? [ 31, '32' ] : [];
}

function jpn_test_area_options(): array {
    return [ 'miami' => 'Miami' ];
}

final class AreaOptionsStub {
    public static function all(): array {
        return [ 'boca' => 'Boca Raton' ];
    }
}

function acf_get_field( string $name ): ?array {
    return 'cost' === $name ? [ 'name' => 'cost', 'choices' => [ 'free' => 'Free', 'paid' => 'Paid' ] ] : null;
}

foreach ( [ 'PublicComponents/ProfileValues', 'QueryFilter/QueryFilterType', 'QueryFilter/MetaFilterType', 'QueryFilter/TaxonomyFilterType', 'QueryFilter/DateRangeFilterType', 'QueryFilter/CallbackFilterType', 'QueryFilter/QueryFilterTypes', 'QueryFilter/QueryFilterSet' ] as $class ) {
    require $root . '/src/' . $class . '.php';
}

use Hexa\PluginCore\QueryFilter\QueryFilterSet;
use Hexa\PluginCore\QueryFilter\QueryFilterType;
use Hexa\PluginCore\QueryFilter\QueryFilterTypes;

$assertions = 0;
$expect = static function ( bool $condition, string $message ) use ( &$assertions ): void {
    $assertions++;
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$db = new FakeFilterWpdb();

// A custom type is one small class; built-in names cannot be replaced.
final class PriceBandFilterType extends QueryFilterType {
    public function normalize( array $definition, string $source ): ?array {
        return [ 'meta_key' => 'price' ];
    }

    public function where( $database, array $filter, $value, array $context ): string {
        return 'cheap' === $value ? $database->prepare( 'EXISTS (SELECT 1 FROM wp_postmeta ' . $context['alias'] . ' WHERE ' . $context['alias'] . '.post_id = ' . $context['table'] . '.ID AND ' . $context['alias'] . '.meta_key = %s AND CAST(' . $context['alias'] . '.meta_value AS DECIMAL) < %d)', 'price', 25 ) : '';
    }
}
QueryFilterTypes::register( 'price_band', new PriceBandFilterType() );
QueryFilterTypes::register( 'meta', new PriceBandFilterType() );
$expect( in_array( 'price_band', QueryFilterTypes::names(), true ), 'Hosts can register a new filter type.' );
$expect( QueryFilterTypes::get( 'meta' ) instanceof \Hexa\PluginCore\QueryFilter\MetaFilterType, 'Built-in filter types cannot be replaced by another plugin.' );

$scope   = [ 'component' => 'test', 'source' => 'posts', 'post_types' => [ 'event' ], 'profile' => [ 'id' => 'events' ] ];
$filters = QueryFilterSet::normalize( [
    'area'     => [ 'type' => 'taxonomy', 'taxonomy' => 'area', 'options' => 'terms', 'all_label' => 'All areas' ],
    'area_id'  => [ 'type' => 'taxonomy', 'taxonomy' => 'area', 'term_field' => 'term_id', 'options' => 'terms' ],
    'kids'     => [ 'type' => 'meta', 'meta_key' => 'kids_event', 'control' => 'toggle', 'label' => 'Kids <events>' ],
    'status'   => [ 'type' => 'meta', 'meta_key' => 'status', 'value' => 'open', 'control' => 'toggle' ],
    'cost'     => [ 'type' => 'meta', 'meta_key' => 'cost', 'options' => 'acf' ],
    'tags'     => [ 'type' => 'meta', 'meta_key' => 'tags', 'compare' => 'serialized', 'options' => [ '5' => 'Five' ] ],
    'dates'    => [ 'type' => 'date_range', 'meta_key' => 'start_date_timestamp', 'timezone' => 'America/New_York', 'control' => 'select' ],
    'picked'   => [ 'type' => 'date_range', 'meta_key' => 'picked', 'format' => 'date', 'timezone' => 'America/New_York' ],
    'stamped'  => [ 'type' => 'date_range', 'meta_key' => 'stamped', 'format' => 'datetime', 'timezone' => 'America/New_York' ],
    'posted'   => [ 'type' => 'date_range', 'column' => 'date', 'timezone' => 'America/New_York', 'min' => '2026-01-01', 'max' => '2026-12-31' ],
    'hosted'   => [ 'type' => 'callback', 'control' => 'toggle', 'apply' => static fn( $value, array $profile ): array => [ 7, 9, 7, -1 ] ],
    'price'    => [ 'type' => 'price_band', 'options' => [ 'cheap' => 'Under $25' ] ],
    'unknown'  => [ 'type' => 'nope', 'meta_key' => 'x' ],
    'no_meta'  => [ 'type' => 'meta' ],
    'no_apply' => [ 'type' => 'callback' ],
    'no_field' => [ 'type' => 'date_range' ],
], 'posts', [ 'min' => '2025-09-01', 'max' => '2027-09-30' ] );

$expect( ! isset( $filters['unknown'], $filters['no_meta'], $filters['no_apply'], $filters['no_field'] ), 'Unknown types and incomplete definitions are rejected.' );
$expect( 12 === count( $filters ), 'Every valid definition is kept within the filter limit.' );
$expect( 'date_range' === $filters['dates']['control'], 'A date-range filter always uses the date-range control.' );
$expect( 'toggle' === $filters['kids']['control'] && '1' === $filters['kids']['value'], 'Meta toggles default to matching 1 (ACF true/false).' );
$expect( '2025-09-01' === $filters['dates']['min'] && '2027-09-30' === $filters['dates']['max'], 'Component date bounds become date-control limits.' );
$expect( '2026-01-01' === $filters['posted']['min'], 'A filter can declare its own date limits.' );
$expect( ! isset( QueryFilterSet::normalize( [ 'area' => [ 'type' => 'taxonomy', 'taxonomy' => 'area' ] ], 'users' )['area'] ), 'Taxonomy filters are rejected for users.' );
$expect( 'user_registered' === ( \Hexa\PluginCore\QueryFilter\DateRangeFilterType::USER_COLUMNS[ QueryFilterSet::normalize( [ 'joined' => [ 'type' => 'date_range', 'column' => 'registered' ] ], 'users' )['joined']['column'] ] ?? '' ), 'Users can filter by registration date.' );

// Options: keyword sources, arrays, and memoization.
$expect( [ 'miami' => 'Miami', 'boca-raton' => 'Boca Raton' ] === QueryFilterSet::options( $filters['area'], $scope ), "Keyword 'terms' lists non-empty taxonomy terms by slug." );
$expect( [ '18' => 'Miami', '43' => 'Boca Raton' ] === QueryFilterSet::options( $filters['area_id'], $scope ), "Keyword 'terms' keys by term id when declared." );
$expect( [ 'free' => 'Free', 'paid' => 'Paid' ] === QueryFilterSet::options( $filters['cost'], $scope ), "Keyword 'acf' reads the ACF field's choices." );

// Parsing untrusted input.
$values = QueryFilterSet::parse( $filters, [
    'area'    => 'miami',
    'area_id' => '999',
    'kids'    => 'on',
    'status'  => 'no',
    'cost'    => [ 'free' ],
    'tags'    => '5',
    'dates'   => [ 'from' => '2026-10-20', 'to' => '2026-10-05' ],
    'picked'  => [ 'from' => '2026-02-30', 'to' => '2026-03-02' ],
    'stamped' => [ 'from' => '2026-10-01' ],
    'posted'  => [ 'from' => '2020-01-01', 'to' => '2030-01-01' ],
    'hosted'  => '1',
    'price'   => 'cheap',
], $scope );
$expect( 'miami' === $values['area'] && ! isset( $values['area_id'] ), 'Select values must be declared options.' );
$expect( '1' === $values['kids'] && ! isset( $values['status'] ), 'Toggles accept only affirmative values.' );
$expect( ! isset( $values['cost'] ), 'Array input for a select is ignored.' );
$expect( [ 'from' => '2026-10-05', 'to' => '2026-10-20' ] === $values['dates'], 'Reversed date ranges are swapped.' );
$expect( [ 'from' => '', 'to' => '2026-03-02' ] === $values['picked'], 'Impossible dates are dropped.' );
$expect( [ 'from' => '2026-01-01', 'to' => '2026-12-31' ] === $values['posted'], 'Dates are clamped to the declared limits.' );
$expect( [] === QueryFilterSet::parse( $filters, 'not-an-array', $scope ), 'Non-array filter input yields no filters.' );
$expect( [ 'from' => '2026-10-05', 'to' => '2026-10-20' ] === QueryFilterSet::active_date_range( $filters, $values ), 'The first active date range is exposed to components.' );

// SQL.
$where = implode( ' AND ', QueryFilterSet::where( $db, $filters, $values, $scope, 'p' ) );
$expect( str_contains( $where, "p_f1_t.slug = 'miami'" ) && str_contains( $where, "p_f1_tt.taxonomy = 'area'" ), 'Taxonomy filters join terms through unique aliases.' );
$expect( str_contains( $where, "p_f2.meta_key = 'kids_event' AND p_f2.meta_value = '1'" ), 'Toggle meta filters match the declared value.' );
$expect( str_contains( $where, "(p_f3.meta_value = '5' OR p_f3.meta_value LIKE '%\"5\"%')" ), 'Serialized meta filters match ACF arrays.' );
$expect( str_contains( $where, 'CAST(p_f4.meta_value AS SIGNED) >= 1791172800 AND CAST(p_f4.meta_value AS SIGNED) < 1792555200' ), 'Timestamp ranges run from New York midnight to the midnight after the last day.' );
$expect( str_contains( $where, "p_f5.meta_value < '20260303'" ) && ! str_contains( $where, "p_f5.meta_value >= " ), 'Ymd date ranges compare stored strings and skip an empty side.' );
$expect( str_contains( $where, "p_f6.meta_value >= '2026-10-01 00:00:00'" ), 'Datetime ranges compare local date-time strings.' );
$expect( str_contains( $where, "p.post_date_gmt >= '2026-01-01 05:00:00' AND p.post_date_gmt < '2027-01-01 05:00:00'" ), 'Post-date ranges compare UTC columns.' );
$expect( str_contains( $where, 'p.ID IN (7,9)' ), 'Callback filters restrict to unique positive IDs.' );
$expect( str_contains( $where, "CAST(p_f9.meta_value AS DECIMAL) < 25" ), 'Custom filter types contribute their own SQL.' );
$expect( [] === QueryFilterSet::where( $db, $filters, [ 'nope' => 'x' ], $scope, 'p' ), 'Values for undeclared filters are ignored.' );
$none = QueryFilterSet::normalize( [ 'none' => [ 'type' => 'callback', 'control' => 'toggle', 'apply' => static fn(): array => [] ] ], 'posts' );
$expect( [ '1=0' ] === QueryFilterSet::where( $db, $none, [ 'none' => '1' ], $scope, 'p' ), 'A callback returning no IDs excludes everything.' );

$users = QueryFilterSet::normalize( [ 'city' => [ 'type' => 'meta', 'meta_key' => 'city', 'options' => [ 'x' => 'X' ] ] ], 'users' );
$expect( str_contains( implode( '', QueryFilterSet::where( $db, $users, [ 'city' => 'x' ], [ 'source' => 'users' ] + $scope, 'u' ) ), 'FROM wp_usermeta u_f1 WHERE u_f1.user_id = u.ID' ), 'User meta filters use usermeta.' );

// Controls and URL arguments.
$controls = QueryFilterSet::controls( $filters, $values, 'cfilter', 'hcal', $scope );
$expect( str_contains( $controls, '<option value="miami" selected>Miami</option>' ) && str_contains( $controls, '<option value="">All areas</option>' ), 'Select controls render options with the current value selected.' );
$expect( str_contains( $controls, 'name="cfilter[kids]" value="1" checked' ) && str_contains( $controls, 'Kids &lt;events&gt;' ), 'Toggle controls are checked and labels escaped.' );
$expect( str_contains( $controls, 'type="date" name="cfilter[dates][from]" value="2026-10-05" min="2025-09-01" max="2027-09-30"' ), 'Date controls carry value and limits.' );
$expect( str_contains( $controls, 'class="hcal-field hcal-field--dates hcal-range" role="group"' ), 'Controls use the component class prefix.' );
$expect( [ 'picked' => [ 'to' => '2026-03-02' ], 'kids' => '1' ] === QueryFilterSet::to_args( [ 'picked' => [ 'from' => '', 'to' => '2026-03-02' ], 'kids' => '1', 'empty' => [ 'from' => '', 'to' => '' ] ] ), 'URL arguments drop empty values and date sides.' );

// Regressions found in review.
$expect( [ 'yes_no' => '0' ] === QueryFilterSet::to_args( [ 'yes_no' => '0' ] ), "A select value of '0' survives into URLs." );
$callables = QueryFilterSet::normalize( [
    'fn'     => [ 'type' => 'meta', 'meta_key' => 'area', 'options' => 'jpn_test_area_options' ],
    'static' => [ 'type' => 'meta', 'meta_key' => 'area', 'options' => 'AreaOptionsStub::all' ],
    'many'   => [ 'type' => 'meta', 'meta_key' => 'zip', 'options' => array_combine( array_map( 'strval', range( 1, 250 ) ), array_map( 'strval', range( 1, 250 ) ) ) ],
    'nulls'  => [ 'type' => 'meta', 'meta_key' => 'x', 'options' => [ 'a' => null ] ],
], 'posts' );
$expect( [ 'miami' => 'Miami' ] === QueryFilterSet::options( $callables['fn'], $scope ) && [ 'boca' => 'Boca Raton' ] === QueryFilterSet::options( $callables['static'], $scope ), "Function-name and 'Class::method' strings are callables, as in 3.1.0." );
$expect( 250 === count( QueryFilterSet::options( $callables['many'], $scope ) ) && [ 'a' => '' ] === QueryFilterSet::options( $callables['nulls'], $scope ), 'Host-supplied option lists are kept whole, including null labels.' );

final class ChecklistFilterType extends QueryFilterType {
    public function controls(): array {
        return [ 'checkboxes' ];
    }

    public function where( $database, array $filter, $value, array $context ): string {
        return '';
    }
}
final class BandsFilterType extends QueryFilterType {
    public function normalize( array $definition, string $source ): ?array {
        return [ 'options' => [ 'cheap' => 'Under $25' ] ];
    }

    public function where( $database, array $filter, $value, array $context ): string {
        return 'cheap' === $value ? '1=1' : '';
    }
}
QueryFilterTypes::register( 'checklist', new ChecklistFilterType() );
QueryFilterTypes::register( 'bands', new BandsFilterType() );
$typed = QueryFilterSet::normalize( [ 'list' => [ 'type' => 'checklist' ], 'band' => [ 'type' => 'bands' ] ], 'posts' );
$expect( ! isset( $typed['list'] ), 'A type offering only controls Core cannot render is rejected instead of silently becoming a select.' );
$expect( [ 'band' => 'cheap' ] === QueryFilterSet::parse( $typed, [ 'band' => 'cheap' ], $scope ), 'A type can supply its own options.' );

$overlap = QueryFilterSet::normalize( [ 'when' => [ 'type' => 'date_range', 'meta_key' => 'start_date_timestamp', 'end_meta_key' => 'end_date_timestamp', 'timezone' => 'America/New_York' ] ], 'posts' );
$overlap_sql = implode( '', QueryFilterSet::where( $db, $overlap, [ 'when' => [ 'from' => '2026-10-05', 'to' => '2026-10-20' ] ], $scope, 'p' ) );
$expect( str_contains( $overlap_sql, "LEFT JOIN wp_postmeta p_f1_e ON p_f1_e.post_id = p_f1.post_id AND p_f1_e.meta_key = 'end_date_timestamp'" ), 'An overlap range joins the end field of the same post.' );
$expect( str_contains( $overlap_sql, "CAST(GREATEST(CAST(p_f1.meta_value AS SIGNED), CAST(COALESCE(NULLIF(p_f1_e.meta_value, ''), p_f1.meta_value) AS SIGNED)) AS SIGNED) >= 1791172800" ) && str_contains( $overlap_sql, 'CAST(p_f1.meta_value AS SIGNED) < 1792555200' ), 'Ongoing items that started before the range still match.' );

$region = QueryFilterSet::normalize( [ 'region' => [ 'type' => 'taxonomy', 'taxonomy' => 'region', 'options' => [ 'south-florida' => 'South Florida' ] ], 'exact' => [ 'type' => 'taxonomy', 'taxonomy' => 'region', 'include_children' => false, 'options' => [ 'south-florida' => 'x' ] ] ], 'posts' );
$region_sql = implode( ' AND ', QueryFilterSet::where( $db, $region, [ 'region' => 'south-florida', 'exact' => 'south-florida' ], $scope, 'p' ) );
$expect( str_contains( $region_sql, 'p_f1_tt.term_id IN (30,31,32)' ) && str_contains( $region_sql, "p_f2_t.slug = 'south-florida'" ), 'Hierarchical terms include their descendants unless include_children is false.' );

echo "PASS: query filter contract ({$assertions} assertions).\n";
