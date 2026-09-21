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
require $root . '/src/SearchQuery/SearchQueryConfiguration.php';
require $root . '/src/SearchQuery/SearchTermParser.php';
require $root . '/src/SearchQuery/SearchQueryEngine.php';
require $root . '/src/SearchQuery/JetEngineSearchAdapter.php';

use Hexa\PluginCore\SearchQuery\JetEngineSearchAdapter;
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

$engine->register();
$engine->register();
$query_vars_filter = $test_filters['query_vars'][10][0] ?? null;
$expect( is_callable( $query_vars_filter ) && in_array( 'hexa_search', $query_vars_filter( [ 's' ] ), true ), 'The request marker is registered as a public query variable.' );
$expect( 1 === count( $test_filters['query_vars'][10] ?? [] ), 'Repeated registration must not duplicate the query-variable filter.' );
$expect( 1 === count( $test_filters['pre_get_posts'][20] ?? [] ), 'Repeated registration must not duplicate the preparation hook.' );
$expect( 1 === count( $test_filters['posts_search'][999] ?? [] ), 'One permanent exact-query SQL dispatcher must be registered.' );

$target = new FakeQuery( [ 's' => 'red shoes', 'hexa_search' => '1' ] );
$engine->prepare_query( $target );
$expect( [ 'post', 'book' ] === $target->get( 'post_type' ), 'The target query receives only the configured post types.' );
$expect( 12 === $target->get( 'posts_per_page' ) && 'date' === $target->get( 'orderby' ), 'Result count and ordering are applied to the target query.' );

$search_filter = array_values( $test_filters['posts_search'][999] ?? [] )[0] ?? null;
$other = new FakeQuery( [ 's' => 'other', 'hexa_search' => '1' ] );
$expect( is_callable( $search_filter ) && 'ORIGINAL' === $search_filter( 'ORIGINAL', $other ), 'The SQL dispatcher ignores every unprepared query instance.' );
$target_sql = is_callable( $search_filter ) ? $search_filter( 'ORIGINAL', $target ) : '';
$expect( str_contains( $target_sql, "post_title LIKE '%red%'" ), 'The SQL dispatcher replaces only the prepared target search clause.' );
$expect( 'ORIGINAL' === ( is_callable( $search_filter ) ? $search_filter( 'ORIGINAL', $target ) : '' ), 'Prepared state must be consumed after the exact target reaches the dispatcher.' );
$expect( 1 === count( $test_filters['posts_search'][999] ?? [] ), 'The permanent dispatcher must remain singular after consuming a target.' );

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
