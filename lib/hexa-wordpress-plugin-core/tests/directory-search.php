<?php

declare(strict_types=1);

$root = dirname( __DIR__ );

set_error_handler( static function ( int $severity, string $message, string $file, int $line ): bool {
    throw new ErrorException( $message, 0, $severity, $file, $line );
} );

final class FakeDirectoryWpdb {
    public string $prefix = 'wp_';
    public string $posts = 'wp_posts';
    public string $postmeta = 'wp_postmeta';
    public string $users = 'wp_users';
    public string $usermeta = 'wp_usermeta';
    public string $terms = 'wp_terms';
    public string $term_taxonomy = 'wp_term_taxonomy';
    public string $term_relationships = 'wp_term_relationships';

    public function get_blog_prefix(): string {
        return $this->prefix;
    }

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

require $root . '/src/SearchQuery/SearchQueryConfiguration.php';
require $root . '/src/SearchQuery/SearchTermParser.php';
require $root . '/src/SearchQuery/SearchMatchSql.php';
require $root . '/src/PublicComponents/ProfileValues.php';
require $root . '/src/PublicComponents/ProfileStore.php';
require $root . '/src/PublicComponents/PublicComponent.php';
require $root . '/src/QueryFilter/QueryFilterType.php';
require $root . '/src/QueryFilter/MetaFilterType.php';
require $root . '/src/QueryFilter/TaxonomyFilterType.php';
require $root . '/src/QueryFilter/DateRangeFilterType.php';
require $root . '/src/QueryFilter/CallbackFilterType.php';
require $root . '/src/QueryFilter/QueryFilterTypes.php';
require $root . '/src/QueryFilter/QueryFilterSet.php';
require $root . '/src/DirectorySearch/DirectorySearchProfile.php';
require $root . '/src/DirectorySearch/DirectorySearchRegistry.php';
require $root . '/src/DirectorySearch/DirectorySearchRequest.php';
require $root . '/src/DirectorySearch/DirectorySearchQuery.php';
require $root . '/src/DirectorySearch/DirectorySearchRenderer.php';

use Hexa\PluginCore\DirectorySearch\DirectorySearchProfile;
use Hexa\PluginCore\DirectorySearch\DirectorySearchQuery;
use Hexa\PluginCore\DirectorySearch\DirectorySearchRenderer;
use Hexa\PluginCore\DirectorySearch\DirectorySearchRequest;
use Hexa\PluginCore\SearchQuery\SearchMatchSql;
use Hexa\PluginCore\SearchQuery\SearchTermParser;

$assertions = 0;
$expect = static function ( bool $condition, string $message ) use ( &$assertions ): void {
    $assertions++;
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$render = static fn( int $id ): string => '<p>' . $id . '</p>';
$db = new FakeDirectoryWpdb();

// Profile contract.
$threw = false;
try {
    DirectorySearchProfile::normalize( 'people', [ 'source' => 'users', 'render_item' => $render ] );
} catch ( InvalidArgumentException $exception ) {
    $threw = true;
}
$expect( $threw, 'A users profile without roles is rejected so a public directory can never list every account.' );

$hosts = DirectorySearchProfile::normalize(
    'Hosts',
    [
        'source'        => 'users',
        'roles'         => [ 'host' ],
        'fields'        => [ 'display_name', 'email', 'login', 'nicename' ],
        'meta_keys'     => [ 'description', 'website' ],
        'word_matching' => 'prefix',
        'filters'       => [
            'area'     => [ 'type' => 'meta', 'meta_key' => 'area', 'compare' => 'serialized', 'options' => [ '18' => 'Miami', '10' => 'Boca' ] ],
            'upcoming' => [ 'type' => 'callback', 'control' => 'toggle', 'apply' => static fn( string $value ): array => [] ],
            'broken'   => [ 'type' => 'taxonomy', 'taxonomy' => 'area' ],
        ],
        'sorts'         => [
            'name'   => [ 'type' => 'field', 'field' => 'name', 'label' => 'A–Z' ],
            'events' => [ 'type' => 'callback', 'callback' => static fn( array $ids ): array => array_reverse( $ids ) ],
            'bogus'  => [ 'type' => 'field', 'field' => 'user_pass' ],
        ],
        'default_sort'  => 'events',
        'render_item'   => $render,
    ]
);
$expect( 'hosts' === $hosts['id'], 'Profile ids are normalized keys.' );
$expect( [ 'display_name', 'nicename' ] === $hosts['fields'], 'Login and email are never searchable in a directory profile.' );
$expect( ! isset( $hosts['filters']['broken'] ), 'Taxonomy filters are rejected for the users source.' );
$expect( ! isset( $hosts['sorts']['bogus'] ), 'Sorts accept only whitelisted columns.' );
$expect( 'events' === $hosts['default_sort'], 'A declared default sort is kept.' );

// Request contract.
$request = DirectorySearchRequest::from_input(
    [ 'dq' => "  syn*  <b>", 'dpage' => '999999', 'dsort' => 'nope', 'dfilter' => [ 'area' => '999', 'upcoming' => 'on' ] ],
    $hosts
);
$expect( 'syn* b' === $request['q'], 'Visitor text is stripped of control characters and angle brackets.' );
$expect( DirectorySearchRequest::MAX_PAGE === $request['page'], 'Pages are bounded.' );
$expect( 'events' === $request['sort'], 'Unknown sorts fall back to the profile default.' );
$expect( [ 'upcoming' => '1' ] === $request['filters'], 'Select filters accept only declared options; toggles normalize to 1.' );

// Shared matcher and wildcard parsing.
$expect( [ 'syn*', 'b' ] === SearchTermParser::parse( 'syn* **  b', 'all', true ), 'Wildcards survive parsing when requested and lone stars are dropped.' );
$expect( [ 'syn', 'b' ] === SearchTermParser::parse( 'syn* b' ), 'Default parsing still strips wildcards (native engine unchanged).' );
$expect( "wp_posts.post_title LIKE '%sy\\_n%'" === SearchMatchSql::condition( $db, 'wp_posts.post_title', 'sy_n', 'contains' ), 'Contains matching escapes LIKE wildcards.' );
$expect( "c REGEXP '(^|[^[:alnum:]_])syn'" === SearchMatchSql::condition( $db, 'c', 'syn*', 'whole', true ), 'A trailing star becomes a word-start prefix match.' );
$expect( "c REGEXP 'gogue([^[:alnum:]_]|$)'" === SearchMatchSql::condition( $db, 'c', '*gogue', 'whole', true ), 'A leading star becomes a word-end match.' );
$expect( "c REGEXP '(^|[^[:alnum:]_])s[^[:space:]]*gogue([^[:alnum:]_]|$)'" === SearchMatchSql::condition( $db, 'c', 's*gogue', 'prefix', true ), 'An inner star matches within one word.' );

// Users SQL.
$query = new DirectorySearchQuery( $db );
$where = $query->where_sql( $db, $hosts, [ 'q' => 'Chabad syn*', 'page' => 1, 'sort' => 'events', 'filters' => [] ] );
$expect( str_contains( $where, "hds_cap.meta_key = 'wp_capabilities'" ), 'Users are scoped through the blog capabilities key.' );
$expect( str_contains( $where, "LIKE '%\"host\"%'" ), 'Users are scoped to the declared role.' );
$expect( str_contains( $where, "hds_u.display_name REGEXP '(^|[^[:alnum:]_])Chabad'" ), 'Prefix matching applies to display names.' );
$expect( str_contains( $where, "hds_um.meta_key IN ('description', 'website')" ), 'Declared user meta keys are searched through EXISTS.' );
$expect( ! str_contains( $where, 'user_email' ) && ! str_contains( $where, 'user_login' ), 'Private user columns never appear in directory SQL.' );
$expect( substr_count( $where, ') AND (' ) >= 1, 'All-word logic joins term clauses with AND.' );

$short = $query->where_sql( $db, $hosts, [ 'q' => 'C', 'page' => 1, 'sort' => 'events', 'filters' => [] ] );
$expect( ! str_contains( $short, 'REGEXP' ), 'Text shorter than min_chars applies no search clause.' );

$filtered = $query->where_sql( $db, $hosts, [ 'q' => '', 'page' => 1, 'sort' => 'name', 'filters' => [ 'area' => '18', 'upcoming' => '1' ] ] );
$expect( str_contains( $filtered, "hds_u_f1.meta_key = 'area'" ) && str_contains( $filtered, "LIKE '%\"18\"%'" ), 'Serialized meta filters match ACF-style stored arrays.' );
$expect( str_contains( $filtered, 'hds_u_f1.user_id = hds_u.ID' ), 'User meta filters correlate on the users alias through the shared filter set.' );
$expect( str_contains( $filtered, '1=0' ), 'A callback filter returning no ids excludes everything.' );

// Posts SQL.
$events = DirectorySearchProfile::normalize(
    'events',
    [
        'post_types'   => [ 'event' ],
        'fields'       => [ 'title', 'excerpt' ],
        'taxonomies'   => [ 'area' ],
        'term_logic'   => 'any',
        'word_matching'=> 'contains',
        'filters'      => [ 'area' => [ 'type' => 'taxonomy', 'taxonomy' => 'area', 'options' => [ 'miami' => 'Miami' ] ] ],
        'render_item'  => $render,
    ]
);
$post_where = $query->where_sql( $db, $events, [ 'q' => 'shabbat dinner', 'page' => 1, 'sort' => 'title', 'filters' => [ 'area' => 'miami' ] ] );
$expect( str_contains( $post_where, "hds_p.post_type IN ('event')" ) && str_contains( $post_where, "post_status = 'publish'" ) && str_contains( $post_where, "post_password = ''" ), 'Posts are scoped to published, unprotected content of declared types.' );
$expect( str_contains( $post_where, ') OR (' ), 'Any-word logic joins term clauses with OR.' );
$expect( str_contains( $post_where, "hds_p_f1_t.slug = 'miami'" ), 'Taxonomy filters accept term slugs.' );
$expect( str_contains( $post_where, "hds_t.name LIKE '%shabbat%'" ), 'Declared taxonomies are searchable by term name.' );

// Base path normalization (REST `base` and REQUEST_URI).
$expect( '/x' === DirectorySearchRenderer::sanitize_base( '//evil.example/x' ), 'Protocol-relative bases keep only their same-site path.' );
$expect( '/' === DirectorySearchRenderer::sanitize_base( 'javascript:alert(1)' ), 'Non-path bases collapse to the site root.' );
$expect( '/' === DirectorySearchRenderer::sanitize_base( '/<>/evil.example' ) || ! str_starts_with( DirectorySearchRenderer::sanitize_base( '/<>/evil.example' ), '//' ), 'Stripping characters cannot produce a protocol-relative base.' );
$expect( '/hosts/?lang=he' === DirectorySearchRenderer::sanitize_base( '/hosts/?lang=he&dq=x&dpage=3&dfilter[area]=1&dir=jpn_hosts' ), 'Page query arguments survive while directory arguments are removed.' );
$expect( 255 >= strlen( DirectorySearchRenderer::sanitize_base( '/' . str_repeat( 'a', 5000 ) ) ), 'Base paths are length-bounded.' );
$expect( [] === SearchTermParser::parse( '"**"', 'exact', true ), 'An exact query made only of wildcards applies no search.' );

$slug_profile = DirectorySearchProfile::normalize( 'tax', [ 'post_types' => [ 'event' ], 'filters' => [
    'year' => [ 'type' => 'taxonomy', 'taxonomy' => 'year', 'options' => [ '2026' => '2026' ] ],
    'area' => [ 'type' => 'taxonomy', 'taxonomy' => 'area', 'term_field' => 'term_id', 'options' => [ '25' => 'Miami' ] ],
], 'render_item' => $render ] );
$tax_where = $query->where_sql( $db, $slug_profile, [ 'q' => '', 'page' => 1, 'sort' => 'title', 'filters' => [ 'year' => '2026', 'area' => '25' ] ] );
$expect( str_contains( $tax_where, "hds_p_f1_t.slug = '2026'" ) && str_contains( $tax_where, 'hds_p_f2_tt.term_id = 25' ), 'Taxonomy filters match slugs by default and term ids only when declared.' );

$dated = DirectorySearchProfile::normalize( 'dated', [ 'post_types' => [ 'event' ], 'filters' => [
    'when' => [ 'type' => 'date_range', 'meta_key' => 'start_date_timestamp', 'format' => 'timestamp', 'timezone' => 'America/New_York' ],
], 'render_item' => $render ] );
$dated_request = DirectorySearchRequest::from_input( [ 'dfilter' => [ 'when' => [ 'from' => '2026-10-01', 'to' => '' ] ] ], $dated );
$expect( [ 'when' => [ 'from' => '2026-10-01', 'to' => '' ] ] === $dated_request['filters'], 'Directory requests accept date-range filters from the shared filter set.' );
$expect( [ 'dfilter' => [ 'when' => [ 'from' => '2026-10-01' ] ] ] === DirectorySearchRequest::to_args( $dated_request, $dated ), 'Directory URL arguments drop empty date-range sides.' );
$expect( str_contains( $query->where_sql( $db, $dated, $dated_request ), 'CAST(hds_p_f1.meta_value AS SIGNED) >= 1790827200' ), 'Directory date-range filters bound New York midnight as a Unix timestamp.' );

$expect( [ 'miami' => 'Miami' ] === DirectorySearchRequest::filter_options( [ 'options' => static fn(): array => [ 'miami' => 'Miami' ] ] ), 'The 3.1.0 filter_options() helper still works (deprecated).' );
$expect( [ '=', 'serialized' ] === DirectorySearchProfile::META_COMPARES && in_array( 'taxonomy', DirectorySearchProfile::FILTER_TYPES, true ), 'The 3.1.0 filter constants remain as deprecated aliases.' );
$expect( [ 'area' => '0' ] === DirectorySearchRequest::to_args( [ 'q' => '', 'page' => 1, 'sort' => 'events', 'filters' => [ 'area' => '0' ] ], $hosts )['dfilter'], "A '0' filter value stays in pagination links." );

echo "PASS: directory search contract ({$assertions} assertions).\n";
