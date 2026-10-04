<?php

declare(strict_types=1);

$test_filters = [];
$test_context = [
    'admin' => false,
    'ajax'  => false,
    'cron'  => false,
    'rest'  => false,
];

function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
    global $test_filters;
    $test_filters[ $hook ][ $priority ][] = $callback;
    return true;
}

function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
    return add_filter( $hook, $callback, $priority, $accepted_args );
}

function remove_filter( string $hook, callable $callback, int $priority = 10 ): bool {
    global $test_filters;
    foreach ( $test_filters[ $hook ][ $priority ] ?? [] as $index => $registered ) {
        if ( $registered === $callback ) {
            unset( $test_filters[ $hook ][ $priority ][ $index ] );
            return true;
        }
    }
    return false;
}

function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
    return $value;
}

function is_admin(): bool {
    global $test_context;
    return $test_context['admin'];
}

function wp_doing_ajax(): bool {
    global $test_context;
    return $test_context['ajax'];
}

function wp_doing_cron(): bool {
    global $test_context;
    return $test_context['cron'];
}

function wp_is_serving_rest_request(): bool {
    global $test_context;
    return $test_context['rest'];
}

function is_user_logged_in(): bool {
    return false;
}

final class FakeWpdb {
    public string $posts = 'wp_posts';
    public string $postmeta = 'wp_postmeta';
    public string $terms = 'wp_terms';
    public string $term_taxonomy = 'wp_term_taxonomy';
    public string $term_relationships = 'wp_term_relationships';
    public string $users = 'wp_users';

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

final class FakeQuery {
    /** @param array<string,mixed> $vars */
    public function __construct(
        public array $vars,
        private bool $main = true,
        private bool $search = true,
        private bool $feed = false
    ) {
    }

    public function get( string $key ): mixed {
        return $this->vars[ $key ] ?? null;
    }

    public function set( string $key, mixed $value ): void {
        $this->vars[ $key ] = $value;
    }

    public function is_main_query(): bool {
        return $this->main;
    }

    public function is_search(): bool {
        return $this->search;
    }

    public function is_feed(): bool {
        return $this->feed;
    }
}

$root = dirname( __DIR__ );
require $root . '/src/QueryFilter/NaturalTimeWindow.php';
require $root . '/src/SearchQuery/SearchQueryConfiguration.php';
require $root . '/src/SearchQuery/SearchTermParser.php';
require $root . '/src/SearchQuery/SearchMatchSql.php';
require $root . '/src/SearchQuery/MetaConstraintSql.php';
require $root . '/src/SearchQuery/SearchQueryEngine.php';
require $root . '/src/SearchQuery/JetEngineSearchAdapter.php';

use Hexa\PluginCore\SearchQuery\JetEngineSearchAdapter;
use Hexa\PluginCore\QueryFilter\NaturalTimeWindow;
use Hexa\PluginCore\SearchQuery\SearchQueryConfiguration;
use Hexa\PluginCore\SearchQuery\SearchQueryEngine;
use Hexa\PluginCore\SearchQuery\SearchTermParser;

$failures = [];
$expect = static function ( bool $passed, string $message ) use ( &$failures ): void {
    if ( ! $passed ) {
        $failures[] = $message;
    }
};

$normalized = SearchQueryConfiguration::normalize(
    [
        'enabled'          => '1',
        'scope'            => 'shortcode',
        'term_logic'       => 'any',
        'word_matching'    => 'prefix',
        'post_types'       => [ 'post', 'book', 'private_type' ],
        'fields'           => [ 'title', 'slug', 'invalid' ],
        'taxonomies'       => [ 'category', 'private_taxonomy' ],
        'authors'          => 'yes',
        'custom_fields'    => [ '_sku', 'location', 'bad key' ],
        'results_per_page' => 500,
        'orderby'          => 'oldest',
        'time_window'      => [
            'start_meta_key' => 'starts_at',
            'end_meta_key' => 'ends_at',
            'precision_meta_key' => 'start_precision',
            'post_types' => [ 'book', 'private_type' ],
            'timezone' => 'America/New_York',
        ],
    ],
    [ 'post' => 'Posts', 'page' => 'Pages', 'book' => 'Books' ],
    [ 'category' => 'Categories', 'post_tag' => 'Tags' ]
);

$expect( true === $normalized['enabled'], 'Enabled values normalize to a boolean.' );
$expect( [ 'post', 'book' ] === $normalized['post_types'], 'Only available post types survive normalization.' );
$expect( [ 'title', 'slug' ] === $normalized['fields'], 'Only supported post fields survive normalization.' );
$expect( [ 'category' ] === $normalized['taxonomies'], 'Only available taxonomies survive normalization.' );
$expect( [ '_sku', 'location', 'badkey' ] === $normalized['custom_fields'], 'Custom field keys are bounded and normalized.' );
$expect( 100 === $normalized['results_per_page'], 'Results per page is capped at 100.' );
$expect( [ 'book' ] === $normalized['time_window']['post_types'] && 'starts_at' === $normalized['time_window']['start_meta_key'], 'A time window keeps only valid host fields and searchable post types.' );

$advanced_only = SearchQueryConfiguration::normalize(
    [
        'post_types' => [ 'post' ],
        'fields' => [],
        'taxonomies' => [ 'category' ],
    ],
    [ 'post' => 'Posts' ],
    [ 'category' => 'Categories' ]
);
$expect( [] === $advanced_only['fields'] && [ 'category' ] === $advanced_only['taxonomies'], 'An explicit advanced-only source selection does not silently re-enable native post fields.' );

$terms = SearchTermParser::parse( 'alpha "beta gamma" alpha delta' );
$expect( [ 'alpha', 'beta gamma', 'delta' ] === $terms, 'Quoted phrases stay together and duplicate terms are removed.' );
$expect( [ 'alpha beta' ] === SearchTermParser::parse( '"alpha beta"', 'exact' ), 'Exact mode treats the full query as one phrase.' );
$expect( [] === SearchTermParser::parse( '""', 'exact' ), 'An empty quoted exact phrase cannot compile into a match-all condition.' );
$expect( 80 === strlen( SearchTermParser::parse( '"' . str_repeat( 'a', 100 ) . '"', 'exact' )[0] ?? '' ), 'Exact phrases are unquoted before their bounded length is applied.' );
$expect( 8 === count( SearchTermParser::parse( 'one two three four five six seven eight nine ten' ) ), 'Search terms are capped to control SQL growth.' );

$clock = new DateTimeImmutable( '2026-10-04 15:00:00', new DateTimeZone( 'America/New_York' ) );
$window_definition = $normalized['time_window'];
$day_window = NaturalTimeWindow::parse( '24 hours', $window_definition, $clock );
$mixed_window = NaturalTimeWindow::parse( 'Chabad next 48 hours', $window_definition, $clock );
$week_window = NaturalTimeWindow::parse( 'within one week', $window_definition, $clock );
$expect( is_array( $day_window ) && '' === $day_window['query'] && 86400 === $day_window['duration'], 'A duration-only query becomes a 24-hour filter with no residual keyword.' );
$expect( is_array( $mixed_window ) && 'Chabad' === $mixed_window['query'] && 172800 === $mixed_window['duration'], 'A cued mixed query retains its ordinary keyword and extracts 48 hours.' );
$expect( is_array( $week_window ) && '' === $week_window['query'] && 604800 === $week_window['duration'], 'Number words and within phrases produce a bounded one-week window.' );
$expect( is_array( $day_window ) && $day_window['from'] === $clock->getTimestamp() && $day_window['to'] === $clock->getTimestamp() + 86400, 'The time window includes the exact current instant through the exact duration boundary.' );
$expect( null === NaturalTimeWindow::parse( '"next 48 hours"', $window_definition, $clock ), 'Quoted literal phrases are never interpreted as time filters.' );
$expect( null === NaturalTimeWindow::parse( 'Open 24 hours', $window_definition, $clock ), 'An uncued duration inside ordinary prose remains ordinary search text.' );
$expect( null === NaturalTimeWindow::parse( 'Chabad next 48 hours Miami', $window_definition, $clock ), 'A time-like phrase in the middle of unrelated prose is not extracted accidentally.' );
$expect( null === NaturalTimeWindow::parse( '999 weeks', $window_definition, $clock ), 'Durations beyond the one-year bound remain ordinary search text.' );

$day_constraints = is_array( $day_window ) ? NaturalTimeWindow::constraints( $window_definition, $day_window ) : [];
$expect(
    ( $day_constraints[0]['compare'] ?? '' ) === '<='
    && ( $day_constraints[0]['value'] ?? 0 ) === ( $day_window['to'] ?? -1 )
    && ( $day_constraints[1][0]['compare'] ?? '' ) === '>='
    && ( $day_constraints[1][0]['value'] ?? 0 ) === ( $day_window['from'] ?? -1 ),
    'Window constraints include both exact upper and lower boundaries.'
);
$expect(
    ( $day_constraints[1][2][0]['key'] ?? '' ) === 'start_precision'
    && ( $day_constraints[1][2][1]['value'] ?? 0 ) === ( $day_window['today'] ?? -1 ),
    'Date-only events use the site-local current day for ongoing eligibility.'
);

$settings = SearchQueryConfiguration::normalize(
    [
        'enabled'          => true,
        'scope'            => 'shortcode',
        'term_logic'       => 'all',
        'word_matching'    => 'contains',
        'post_types'       => [ 'post', 'book' ],
        'fields'           => [ 'title', 'content' ],
        'taxonomies'       => [ 'category' ],
        'authors'          => true,
        'custom_fields'    => [ '_sku' ],
        'results_per_page' => 12,
        'orderby'          => 'newest',
        'time_window'      => [
            'start_meta_key' => 'starts_at',
            'end_meta_key' => 'ends_at',
            'precision_meta_key' => 'start_precision',
            'date_only_value' => 'date',
            'post_types' => [ 'book' ],
            'timezone' => 'America/New_York',
        ],
    ],
    [ 'post', 'book' ],
    [ 'category' ]
);

$wpdb = new FakeWpdb();
$GLOBALS['wpdb'] = $wpdb;
$engine = new SearchQueryEngine( static fn(): array => $settings, 'hexa_search' );
$sql = $engine->build_search_sql( 'red shoes', $settings, $wpdb );
$expect( str_contains( $sql, "post_title LIKE '%red%'" ) && str_contains( $sql, "post_content LIKE '%shoes%'" ), 'Selected post fields receive bounded LIKE conditions.' );
$expect( str_contains( $sql, ') AND (' ), 'All-terms mode joins term groups with AND.' );
$expect( str_contains( $sql, 'wp_term_relationships' ) && str_contains( $sql, 'wp_users' ) && str_contains( $sql, 'wp_postmeta' ), 'Opt-in taxonomy, author, and custom-field sources use EXISTS subqueries.' );
$expect( str_contains( $sql, "post_password = ''" ), 'Anonymous searches retain password protection.' );

$any = $settings;
$any['term_logic'] = 'any';
$any_sql = $engine->build_search_sql( 'red shoes', $any, $wpdb );
$expect( str_contains( $any_sql, ') OR (' ), 'Any-term mode joins term groups with OR.' );

$prefix = $settings;
$prefix['word_matching'] = 'prefix';
$prefix_sql = $engine->build_search_sql( 'publ', $prefix, $wpdb );
$expect( str_contains( $prefix_sql, "REGEXP '(^|[^[:alnum:]_])publ'" ), 'Prefix mode anchors each term at a word beginning.' );

$exact = $settings;
$exact['term_logic'] = 'exact';
$exact_sql = $engine->build_search_sql( 'red shoes', $exact, $wpdb );
$expect( str_contains( $exact_sql, "LIKE '%red shoes%'" ) && ! str_contains( $exact_sql, "LIKE '%red%'" ), 'Exact phrase mode searches the contiguous phrase once.' );

$invalid_constraint_sql = $engine->build_search_sql(
    'red shoes',
    $settings,
    $wpdb,
    [ [ 'key' => 'bad key', 'value' => 1, 'compare' => '>=', 'type' => 'NUMERIC' ] ]
);
$expect( str_contains( $invalid_constraint_sql, 'AND (1=0)' ) && ! str_contains( $invalid_constraint_sql, 'badkey' ), 'Invalid trusted constraint shapes fail closed instead of changing the requested key.' );

$numeric_boundary_sql = $engine->build_search_sql(
    'red shoes',
    $settings,
    $wpdb,
    [
        'relation' => 'AND',
        [ 'key' => 'signed_score', 'value' => '10.75', 'compare' => '>=', 'type' => 'SIGNED' ],
        [ 'key' => 'unsigned_score', 'value' => '-2.5', 'compare' => '>', 'type' => 'UNSIGNED' ],
    ]
);
$expect(
    str_contains( $numeric_boundary_sql, "CAST(hexa_sq_mc0.meta_value AS SIGNED) >= '10.75'" )
    && str_contains( $numeric_boundary_sql, "CAST(hexa_sq_mc1.meta_value AS UNSIGNED) > '-2.5'" ),
    'Validated numeric thresholds retain fractional and negative caller values instead of being truncated or clamped.'
);

$engine->register();
$engine->register();
$query_vars_filter = $test_filters['query_vars'][10][0] ?? null;
$expect( is_callable( $query_vars_filter ) && in_array( 'hexa_search', $query_vars_filter( [ 's' ] ), true ), 'The request marker is registered as a public query variable.' );
$expect( 1 === count( $test_filters['query_vars'][10] ?? [] ), 'Repeated registration must not duplicate the query-variable filter.' );
$expect( 1 === count( $test_filters['pre_get_posts'][20] ?? [] ), 'Repeated registration must not duplicate the preparation hook.' );
$expect( 1 === count( $test_filters['posts_search'][999] ?? [] ), 'One permanent exact-query SQL dispatcher must be registered.' );

$target = new FakeQuery( [ 's' => 'red shoes', 'hexa_search' => '1' ] );
$engine->prepare_query( $target );
$engine->set_meta_constraints( $target, [
    'relation' => 'OR',
    [ 'key' => 'starts_at', 'value' => 1700000000, 'compare' => '>=', 'type' => 'NUMERIC' ],
    [
        'relation' => 'AND',
        [ 'key' => 'precision', 'value' => 'date', 'compare' => '=' ],
        [ 'key' => 'ends_at', 'value' => 1700000000, 'compare' => '>=', 'type' => 'NUMERIC' ],
    ],
] );
$expect( [ 'post', 'book' ] === $target->get( 'post_type' ), 'The target query receives only the configured post types.' );
$expect( 12 === $target->get( 'posts_per_page' ) && 'date' === $target->get( 'orderby' ), 'Result count and ordering are applied to the target query.' );

$search_filter = array_values( $test_filters['posts_search'][999] ?? [] )[0] ?? null;
$other = new FakeQuery( [ 's' => 'other', 'hexa_search' => '1' ] );
$expect( is_callable( $search_filter ) && 'ORIGINAL' === $search_filter( 'ORIGINAL', $other ), 'The SQL dispatcher ignores every unprepared query instance.' );
$target_sql = is_callable( $search_filter ) ? $search_filter( 'ORIGINAL', $target ) : '';
$expect( str_contains( $target_sql, "post_title LIKE '%red%'" ), 'The SQL dispatcher replaces only the prepared target search clause.' );
$expect( str_contains( $target_sql, 'hexa_sq_mc0.meta_key = \'starts_at\'' ) && str_contains( $target_sql, "CAST(hexa_sq_mc0.meta_value AS SIGNED) >= '1700000000'" ), 'Trusted exact-query numeric meta constraints compile into bounded correlated predicates.' );
$expect( str_contains( $target_sql, ' OR (' ) && str_contains( $target_sql, ' AND ' ), 'Nested meta-constraint relations preserve their host-declared boolean structure.' );
$expect( 'ORIGINAL' === ( is_callable( $search_filter ) ? $search_filter( 'ORIGINAL', $target ) : '' ), 'Prepared state must be consumed after the exact target reaches the dispatcher.' );
$expect( 1 === count( $test_filters['posts_search'][999] ?? [] ), 'The permanent dispatcher must remain singular after consuming a target.' );

$window_only = new FakeQuery( [ 's' => '24 hours', 'hexa_search' => '1', 'paged' => 3 ] );
$engine->prepare_query( $window_only );
$active_window = $window_only->get( SearchQueryEngine::TIME_WINDOW_QUERY_VAR );
$expect( [ 'book' ] === $window_only->get( 'post_type' ), 'A recognized time window narrows the configured search to the host-declared dated post type.' );
$expect( 3 === $window_only->get( 'paged' ) && '24 hours' === $window_only->get( 's' ), 'Time filtering preserves native pagination and the original request needed by WordPress dispatch.' );
$engine->set_meta_constraints( $window_only, [ [ 'key' => 'published_state', 'value' => 'public', 'compare' => '=' ] ] );
$window_sql = is_callable( $search_filter ) ? $search_filter( 'ORIGINAL', $window_only ) : '';
$expect( is_array( $active_window ) && '' !== $window_sql, 'A duration-only query reaches constraint SQL even with no residual keyword.' );
$expect( ! str_contains( $window_sql, "LIKE '%24%'" ) && ! str_contains( $window_sql, "LIKE '%hours%'" ), 'Duration words never become text LIKE predicates after extraction.' );
$expect(
    str_contains( $window_sql, "meta_key = 'starts_at'" )
    && str_contains( $window_sql, "meta_key = 'ends_at'" )
    && str_contains( $window_sql, "meta_key = 'start_precision'" )
    && str_contains( $window_sql, "meta_key = 'published_state'" )
    && str_contains( $window_sql, "<= '" . (string) ( $active_window['to'] ?? 0 ) . "'" )
    && str_contains( $window_sql, ">= '" . (string) ( $active_window['from'] ?? 0 ) . "'" ),
    'The exact target query combines bounded upcoming, ongoing, and date-only predicates with its host eligibility tree.'
);

$keyword_window = new FakeQuery( [ 's' => 'Chabad within 48 hours', 'hexa_search' => '1' ] );
$engine->prepare_query( $keyword_window );
$keyword_window_sql = is_callable( $search_filter ) ? $search_filter( 'ORIGINAL', $keyword_window ) : '';
$expect( str_contains( $keyword_window_sql, "LIKE '%Chabad%'" ) && ! str_contains( $keyword_window_sql, "LIKE '%48%'" ) && ! str_contains( $keyword_window_sql, "LIKE '%hours%'" ), 'A mixed time query retains keyword matching without searching its duration words.' );

$ordinary_numeric = new FakeQuery( [ 's' => 'Open 24 hours', 'hexa_search' => '1' ] );
$engine->prepare_query( $ordinary_numeric );
$ordinary_numeric_sql = is_callable( $search_filter ) ? $search_filter( 'ORIGINAL', $ordinary_numeric ) : '';
$expect( null === $ordinary_numeric->get( SearchQueryEngine::TIME_WINDOW_QUERY_VAR ) && str_contains( $ordinary_numeric_sql, "LIKE '%24%'" ), 'Ordinary numeric text remains an ordinary search when it has no explicit time-filter intent.' );

$duplicate = new FakeQuery( [ 's' => 'duplicate prepare', 'hexa_search' => '1' ] );
$engine->prepare_query( $duplicate );
$engine->prepare_query( $duplicate );
$expect( 1 === count( $test_filters['posts_search'][999] ?? [] ), 'Duplicate prepare calls must not stack SQL callbacks.' );
$duplicate_sql = is_callable( $search_filter ) ? $search_filter( 'ORIGINAL', $duplicate ) : '';
$expect( str_contains( $duplicate_sql, "post_title LIKE '%duplicate%'" ), 'Duplicate preparation must leave one usable exact-query state.' );
$expect( 'ORIGINAL' === ( is_callable( $search_filter ) ? $search_filter( 'ORIGINAL', $duplicate ) : '' ), 'Duplicate preparation state must still be consumed once.' );

$abandoned = new FakeQuery( [ 's' => 'abandoned query', 'hexa_search' => '1' ] );
$engine->prepare_query( $abandoned );
$abandoned_reference = \WeakReference::create( $abandoned );
unset( $abandoned );
gc_collect_cycles();
$expect( null === $abandoned_reference->get(), 'An abandoned prepared query must not be retained when posts_search never runs.' );

$before = count( $test_filters['posts_search'][999] ?? [] );
$engine->prepare_query( new FakeQuery( [ 's' => 'unmarked' ] ) );
$engine->prepare_query( new FakeQuery( [ 's' => 'nested', 'hexa_search' => '1' ], false ) );
$engine->prepare_query( new FakeQuery( [ 's' => 'feed', 'hexa_search' => '1' ], true, true, true ) );
$test_context['ajax'] = true;
$engine->prepare_query( new FakeQuery( [ 's' => 'ajax', 'hexa_search' => '1' ] ) );
$test_context['ajax'] = false;
$test_context['rest'] = true;
$engine->prepare_query( new FakeQuery( [ 's' => 'rest', 'hexa_search' => '1' ] ) );
$test_context['rest'] = false;
$after = count( $test_filters['posts_search'][999] ?? [] );
$expect( $before === $after, 'Unmarked, nested, feed, AJAX, and REST requests never add SQL callbacks.' );

$explicit = new FakeQuery(
    [
        's'                                  => 'adapted nested search',
        'hexa_search'                        => '1',
        SearchQueryEngine::EXPLICIT_QUERY_VAR => '1',
    ],
    false
);
$engine->prepare_query( $explicit );
$expect( is_callable( $search_filter ), 'A trusted explicitly marked template query is eligible.' );
if ( is_callable( $search_filter ) ) {
    $explicit_sql = $search_filter( 'ORIGINAL', $explicit );
    $expect( str_contains( $explicit_sql, "post_title LIKE '%adapted%'" ), 'The permanent dispatcher consumes an explicitly marked template query.' );
}

$provider_calls = 0;
$guarded_engine = new SearchQueryEngine(
    static function () use ( &$provider_calls, $settings ): array {
        ++$provider_calls;
        return $settings;
    }
);
$guarded_engine->prepare_query( new FakeQuery( [ 's' => 'ordinary post loop' ], true, false ) );
$guarded_engine->prepare_query( new FakeQuery( [ 's' => 'nested search', 'hexa_search' => '1' ], false ) );
$test_context['admin'] = true;
$guarded_engine->prepare_query( new FakeQuery( [ 's' => 'admin search', 'hexa_search' => '1' ] ) );
$test_context['admin'] = false;
$expect( 0 === $provider_calls, 'Unrelated, nested, and admin queries are rejected before the settings provider performs option or object discovery.' );

$adapter_provider_calls = 0;
$GLOBALS['wp_query'] = new FakeQuery( [ 's' => 'adapted words', 'hexa_search' => '1' ] );
$adapter = new JetEngineSearchAdapter(
    static function () use ( &$adapter_provider_calls, $settings ): array {
        ++$adapter_provider_calls;
        return $settings;
    },
    'hexa_search'
);
$adapter->register();
$adapter_filter = $test_filters['jet-engine/listing/grid/posts-query-args'][20][0] ?? null;
$adapted_args = is_callable( $adapter_filter )
    ? $adapter_filter( [ 'post_type' => 'post', 'posts_per_page' => 6 ], null, [ 'is_archive_template' => '' ] )
    : [];
$expect(
    'adapted words' === ( $adapted_args['s'] ?? '' )
    && '1' === ( $adapted_args['hexa_search'] ?? '' )
    && '1' === ( $adapted_args[ SearchQueryEngine::EXPLICIT_QUERY_VAR ] ?? '' )
    && false === ( $adapted_args['suppress_filters'] ?? null ),
    'The JetEngine adapter copies the eligible main search and marks one secondary query explicitly.'
);
$expect( 1 === $adapter_provider_calls, 'The JetEngine adapter loads host settings only after cheap request and template guards pass.' );

$archive_args = is_callable( $adapter_filter )
    ? $adapter_filter( [ 'post_type' => 'post' ], null, [ 'is_archive_template' => 'true' ] )
    : [];
$expect( [ 'post_type' => 'post' ] === $archive_args && 1 === $adapter_provider_calls, 'JetEngine archive grids already using the main query are not adapted or rediscovered.' );

$GLOBALS['wp_query'] = new FakeQuery( [ 's' => 'unmarked words' ] );
$unmarked_args = is_callable( $adapter_filter )
    ? $adapter_filter( [ 'post_type' => 'post' ], null, [ 'is_archive_template' => '' ] )
    : [];
$expect( [ 'post_type' => 'post' ] === $unmarked_args, 'Shortcode-only scope leaves unmarked JetEngine search grids untouched.' );

define( 'WP_CLI', true );
$cli_adapter_calls_before = $adapter_provider_calls;
$cli_adapter_args = is_callable( $adapter_filter )
    ? $adapter_filter( [ 'post_type' => 'post' ], null, [ 'is_archive_template' => '' ] )
    : [];
$expect( [ 'post_type' => 'post' ] === $cli_adapter_args, 'WP-CLI requests must leave JetEngine query arguments untouched.' );
$expect( $cli_adapter_calls_before === $adapter_provider_calls, 'WP-CLI requests must reject JetEngine adaptation before the settings provider runs.' );

$cli_provider_calls = 0;
$cli_engine = new SearchQueryEngine(
    static function () use ( &$cli_provider_calls, $settings ): array {
        ++$cli_provider_calls;
        return $settings;
    }
);
$cli_engine->prepare_query( new FakeQuery( [ 's' => 'cli search', 'hexa_search' => '1' ] ) );
$expect( 0 === $cli_provider_calls, 'WP-CLI queries must be rejected before the settings provider runs.' );

if ( [] !== $failures ) {
    foreach ( $failures as $failure ) {
        fwrite( STDERR, 'FAIL: ' . $failure . "\n" );
    }
    exit( 1 );
}

echo "PASS: Search Query configuration, matching, sources, and exact-query scoping are verified.\n";
