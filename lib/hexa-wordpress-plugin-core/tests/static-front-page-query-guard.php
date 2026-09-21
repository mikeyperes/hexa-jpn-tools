<?php

declare(strict_types=1);

$GLOBALS['hexa_query_guard_actions'] = [];
$GLOBALS['hexa_query_guard_events'] = [];
$GLOBALS['hexa_query_guard_options'] = [
    'show_on_front' => 'page',
    'page_on_front' => 42,
];
$GLOBALS['hexa_query_guard_option_reads'] = 0;
$GLOBALS['hexa_query_guard_did_wp_loaded'] = 0;
$GLOBALS['hexa_query_guard_context'] = [
    'admin' => false,
    'ajax'  => false,
    'cron'  => false,
    'rest'  => false,
];

function add_action( string $hook, callable $callback, int $priority = 10 ): void {
    $GLOBALS['hexa_query_guard_actions'][ $hook ][ $priority ][] = $callback;
}

function do_action( string $hook, mixed ...$arguments ): void {
    $GLOBALS['hexa_query_guard_events'][] = [ $hook, $arguments ];
}

function apply_filters( string $hook, mixed $value, mixed ...$arguments ): mixed {
    return $value;
}

function did_action( string $hook ): int {
    return 'wp_loaded' === $hook ? $GLOBALS['hexa_query_guard_did_wp_loaded'] : 0;
}

function is_admin(): bool {
    return $GLOBALS['hexa_query_guard_context']['admin'];
}

function wp_doing_ajax(): bool {
    return $GLOBALS['hexa_query_guard_context']['ajax'];
}

function wp_doing_cron(): bool {
    return $GLOBALS['hexa_query_guard_context']['cron'];
}

function wp_is_serving_rest_request(): bool {
    return $GLOBALS['hexa_query_guard_context']['rest'];
}

function get_option( string $key, mixed $default = false ): mixed {
    ++$GLOBALS['hexa_query_guard_option_reads'];
    return $GLOBALS['hexa_query_guard_options'][ $key ] ?? $default;
}

final class WP_Query {
    /** @param array<string,mixed> $vars */
    public function __construct(
        public array $vars = [],
        private bool $main = true,
        private bool $page = true
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

    public function is_page(): bool {
        return $this->page;
    }
}

require dirname( __DIR__ ) . '/src/CoreContracts/ModuleInterface.php';
require dirname( __DIR__ ) . '/src/CoreContracts/PluginContextInterface.php';
require dirname( __DIR__ ) . '/src/QuerySafety/QueryEligibility.php';
require dirname( __DIR__ ) . '/src/QuerySafety/StaticFrontPageQueryGuard.php';

final class QueryGuardIntegrationTestRuntimeStub {
    public static int $host_count = 0;

    public static function register_host( mixed $context ): void {
        ++self::$host_count;
    }
}
class_alias( QueryGuardIntegrationTestRuntimeStub::class, 'Hexa\\PluginCore\\IntegrationTests\\IntegrationTestRuntime' );
require dirname( __DIR__ ) . '/src/CoreBootstrap/CoreBootstrap.php';

use Hexa\PluginCore\CoreBootstrap\CoreBootstrap;
use Hexa\PluginCore\CoreContracts\PluginContextInterface;
use Hexa\PluginCore\QuerySafety\QueryEligibility;
use Hexa\PluginCore\QuerySafety\StaticFrontPageQueryGuard;

final class QueryGuardPluginContext implements PluginContextInterface {
    public function __construct( private string $slug ) {
    }

    public function get( string $key, mixed $default = null ): mixed {
        return 'slug' === $key ? $this->slug : $default;
    }

    public function all(): array {
        return [ 'slug' => $this->slug ];
    }
}

$failures = [];
$expect = static function ( bool $passed, string $message ) use ( &$failures ): void {
    if ( ! $passed ) {
        $failures[] = $message;
    }
};

$early_mutation_count = 0;
add_action(
    'pre_get_posts',
    static function ( WP_Query $query ) use ( &$early_mutation_count ): void {
        ++$early_mutation_count;
        $query->set( 'page_id', 99 );
        $query->set( 'post_type', 'post' );
    },
    PHP_INT_MIN
);

( new CoreBootstrap( new QueryGuardPluginContext( 'first-host' ) ) )->boot();
( new CoreBootstrap( new QueryGuardPluginContext( 'second-host' ) ) )->boot();
$parse_query_callbacks = $GLOBALS['hexa_query_guard_actions']['parse_query'][PHP_INT_MIN] ?? [];
$wp_loaded_callbacks = $GLOBALS['hexa_query_guard_actions']['wp_loaded'][PHP_INT_MAX] ?? [];
$expect( 2 === QueryGuardIntegrationTestRuntimeStub::$host_count, 'Independent CoreBootstrap hosts must both register their runtime context.' );
$expect( 1 === count( $parse_query_callbacks ), 'Multiple CoreBootstrap hosts must register one early parse-query capture.' );
$expect( 1 === count( $wp_loaded_callbacks ), 'Multiple CoreBootstrap hosts must schedule one site-wide query protector.' );
if ( isset( $wp_loaded_callbacks[0] ) ) {
    $wp_loaded_callbacks[0]();
}
$early_callbacks = $GLOBALS['hexa_query_guard_actions']['pre_get_posts'][PHP_INT_MIN] ?? [];
$repair_callbacks = $GLOBALS['hexa_query_guard_actions']['pre_get_posts'][PHP_INT_MAX] ?? [];
$expect( 1 === count( $early_callbacks ), 'The earlier host mutation must remain ahead of Core on pre_get_posts.' );
$expect( 1 === count( $repair_callbacks ), 'The final-priority protector must register exactly once.' );

$ordered_main_query = new WP_Query( [ 'page_id' => 42, 'post_type' => 'page' ] );
$parse_query_callbacks[0]( $ordered_main_query );
$early_callbacks[0]( $ordered_main_query );
$repair_callbacks[0]( $ordered_main_query );
$expect( 42 === $ordered_main_query->get( 'page_id' ), 'A mutation registered first at minimum pre_get_posts priority must be repaired from the parse baseline.' );
$expect( 'page' === $ordered_main_query->get( 'post_type' ), 'The parse baseline must preserve page eligibility after an earlier minimum-priority mutation.' );

$ordered_secondary_query = new WP_Query( [ 'page_id' => 42, 'post_type' => 'page' ], false );
$parse_query_callbacks[0]( $ordered_secondary_query );
$early_callbacks[0]( $ordered_secondary_query );
$repair_callbacks[0]( $ordered_secondary_query );
$expect( 99 === $ordered_secondary_query->get( 'page_id' ), 'A secondary query must not receive a captured main-query baseline.' );
$expect( 'post' === $ordered_secondary_query->get( 'post_type' ), 'A secondary query must remain outside automatic front-page repair.' );
$expect( 2 === $early_mutation_count, 'The ordering regression must execute the earlier host callback for both query identities.' );
$GLOBALS['hexa_query_guard_events'] = [];

$reparsed_query = new WP_Query( [ 'page_id' => 42, 'post_type' => 'page' ] );
$parse_query_callbacks[0]( $reparsed_query );
$GLOBALS['hexa_query_guard_options']['page_on_front'] = 43;
$reparsed_query->set( 'page_id', 43 );
$parse_query_callbacks[0]( $reparsed_query );
$reparsed_query->set( 'page_id', 99 );
$reparsed_query->set( 'post_type', 'post' );
$repair_callbacks[0]( $reparsed_query );
$expect( 43 === $reparsed_query->get( 'page_id' ), 'Reparsing the same object must replace its earlier baseline.' );
$expect( 'page' === $reparsed_query->get( 'post_type' ), 'The replacement baseline must retain page eligibility.' );
$GLOBALS['hexa_query_guard_options']['page_on_front'] = 42;
$GLOBALS['hexa_query_guard_events'] = [];

$GLOBALS['hexa_query_guard_option_reads'] = 0;
foreach ( [
    new WP_Query( [ 'page_id' => 42 ], false, true ),
    new WP_Query( [ 'page_id' => 42 ], true, false ),
    new WP_Query( [], true, true ),
] as $unrelated_query ) {
    $expect( ! StaticFrontPageQueryGuard::is_static_front_page_main_query( $unrelated_query ), 'Unrelated queries must be rejected.' );
}
$expect( 0 === $GLOBALS['hexa_query_guard_option_reads'], 'Unrelated queries must be rejected before reading WordPress options.' );

$suppressed = new WP_Query( [ 'suppress_filters' => true ] );
$expect( ! QueryEligibility::allows_main_filtered_frontend_query( $suppressed ), 'Queries that suppress SQL filters must reject paired mutations.' );
$eligible = new WP_Query();
$expect( QueryEligibility::allows_main_filtered_frontend_query( $eligible ), 'An ordinary filtered frontend main query must remain eligible.' );
$secondary = new WP_Query( [], false );
$expect( ! QueryEligibility::allows_main_filtered_frontend_query( $secondary ), 'An unmarked secondary query must not inherit main-query eligibility.' );
$expect(
    ! QueryEligibility::allows_main_or_explicit_filtered_frontend_query( $secondary, 'smpi_context', [ 'home', 'author' ] ),
    'A secondary query without an allowed explicit marker must be rejected.'
);
$secondary->set( 'smpi_context', 'author' );
$expect(
    QueryEligibility::allows_main_or_explicit_filtered_frontend_query( $secondary, 'smpi_context', [ 'home', 'author' ] ),
    'A secondary query with an exact host-owned marker must be eligible.'
);
$expect(
    ! QueryEligibility::allows_main_or_explicit_filtered_frontend_query( $secondary, 'smpi_context', [ 'home' ] ),
    'Explicit marker values must be checked strictly against the host allowlist.'
);
foreach ( [ 'admin', 'ajax', 'cron', 'rest' ] as $context_key ) {
    $GLOBALS['hexa_query_guard_context'][ $context_key ] = true;
    $expect( ! QueryEligibility::allows_main_filtered_frontend_query( $eligible ), strtoupper( $context_key ) . ' requests must be rejected.' );
    $expect(
        ! QueryEligibility::allows_main_or_explicit_filtered_frontend_query( $secondary, 'smpi_context', [ 'author' ] ),
        strtoupper( $context_key ) . ' requests must reject explicitly marked secondary queries.'
    );
    $GLOBALS['hexa_query_guard_context'][ $context_key ] = false;
}

$faulty = new WP_Query( [ 'page_id' => 42, 'post_type' => [ 'post', 'press-release' ] ] );
StaticFrontPageQueryGuard::capture( $faulty );
$faulty->set( 'page_id', 99 );
StaticFrontPageQueryGuard::protect( $faulty );
$expect( 'page' === $faulty->get( 'post_type' ), 'A post-type mutation that excludes pages must be repaired.' );
$expect( 42 === $faulty->get( 'page_id' ), 'The captured static front-page ID must be restored.' );
$expect(
    1 === count( $GLOBALS['hexa_query_guard_events'] )
        && 'hexa_plugin_core_static_front_page_query_repaired' === $GLOBALS['hexa_query_guard_events'][0][0],
    'A repair must emit one diagnostic action.'
);

foreach ( [ null, '', 'page', 'any', [ 'page', 'post' ], [ 'any' ] ] as $safe_post_type ) {
    $query = new WP_Query( [ 'page_id' => 42, 'post_type' => $safe_post_type ] );
    StaticFrontPageQueryGuard::capture( $query );
    $before = count( $GLOBALS['hexa_query_guard_events'] );
    StaticFrontPageQueryGuard::protect( $query );
    $expect( $safe_post_type === $query->get( 'post_type' ), 'An eligible post type must remain unchanged.' );
    $expect( $before === count( $GLOBALS['hexa_query_guard_events'] ), 'Safe queries must not emit repair events.' );
}

$posts_front = new WP_Query( [ 'page_id' => 42, 'post_type' => 'post' ] );
$GLOBALS['hexa_query_guard_options']['show_on_front'] = 'posts';
StaticFrontPageQueryGuard::protect( $posts_front );
$expect( 'post' === $posts_front->get( 'post_type' ), 'A posts-based front page must remain untouched.' );

$GLOBALS['hexa_query_guard_options']['show_on_front'] = 'page';
$GLOBALS['hexa_query_guard_options']['page_on_front'] = 43;
$other_page = new WP_Query( [ 'page_id' => 42, 'post_type' => 'post' ] );
StaticFrontPageQueryGuard::protect( $other_page );
$expect( 'post' === $other_page->get( 'post_type' ), 'A different singular page must remain untouched.' );

$GLOBALS['hexa_query_guard_options']['page_on_front'] = 42;
$p_query = new WP_Query( [ 'p' => 42, 'post_type' => 'post' ] );
StaticFrontPageQueryGuard::capture( $p_query );
StaticFrontPageQueryGuard::protect( $p_query );
$expect( 'page' === $p_query->get( 'post_type' ), 'The canonical p query variable must also be protected.' );

$bootstrap_source = (string) file_get_contents( dirname( __DIR__ ) . '/src/CoreBootstrap/CoreBootstrap.php' );
$expect(
    str_contains( $bootstrap_source, 'new StaticFrontPageQueryGuard()' )
        && str_contains( $bootstrap_source, '->register()' ),
    'CoreBootstrap must inject the guard automatically for every host.'
);

define( 'WP_CLI', true );
$cli_query = new WP_Query( [ 'page_id' => 42 ] );
$option_reads_before_cli_check = $GLOBALS['hexa_query_guard_option_reads'];
$expect( ! QueryEligibility::allows_main_filtered_frontend_query( $cli_query ), 'WP-CLI queries must reject paired mutations.' );
$expect( ! StaticFrontPageQueryGuard::is_static_front_page_main_query( $cli_query ), 'WP-CLI queries must reject automatic front-page repair.' );
$expect( $option_reads_before_cli_check === $GLOBALS['hexa_query_guard_option_reads'], 'WP-CLI queries must be rejected before front-page options are read.' );

if ( [] !== $failures ) {
    foreach ( $failures as $failure ) {
        fwrite( STDERR, 'FAIL: ' . $failure . PHP_EOL );
    }
    exit( 1 );
}

echo "PASS: Static front-page query protection is exact, automatic, and cheap for unrelated queries.\n";
