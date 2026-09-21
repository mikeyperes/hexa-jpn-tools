<?php

declare(strict_types=1);

$test_actions = [];
function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool { global $test_actions; $test_actions[ $hook ][] = $callback; return true; }
function do_action( string $hook, mixed ...$args ): void { global $test_actions; foreach ( $test_actions[ $hook ] ?? [] as $callback ) { $callback( ...$args ); } }
function sanitize_key( mixed $value ): string { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ) ?: ''; }
function current_time( string $format ): string { return '2026-07-27T12:00:00-04:00'; }

$fixture_root = __DIR__ . '/.tmp-hexa-integration-tests-' . getmypid();
mkdir( $fixture_root, 0777, true );
file_put_contents( $fixture_root . '/PACKAGE_HASH', 'fixture-hash' );
file_put_contents( $fixture_root . '/sample-plugin.php', "<?php\n/*\nPlugin Name: Sample Plugin\nVersion: 3.4.5\n*/\n" );

final class HexaPluginCorePackageRegistry {
    public static string $root = '';
    public static function report(): array {
        return [
            'healthy' => true,
            'selected' => [ 'root' => self::$root, 'version' => '1.1.0', 'host' => 'sample-plugin' ],
            'issues' => [],
            'candidates' => [ [ 'host' => 'sample-plugin' ] ],
        ];
    }
    public static function source_hash( string $root ): string { return 'fixture-hash'; }
}
HexaPluginCorePackageRegistry::$root = $fixture_root;

$root = dirname( __DIR__ );
require $root . '/src/CoreContracts/PluginContextInterface.php';
require $root . '/src/CoreRuntime/PluginContext.php';
require $root . '/src/CoreContracts/ModuleInterface.php';
require $root . '/src/QuerySafety/QueryEligibility.php';
require $root . '/src/QuerySafety/StaticFrontPageQueryGuard.php';
require $root . '/src/CoreBootstrap/CoreBootstrap.php';
require $root . '/src/WpAdminComponents/CoreUi.php';
require $root . '/src/WpAdminAjax/AjaxActionRegistry.php';
require $root . '/src/EntitySources/CanonicalEntityResolver.php';
require $root . '/src/IntegrationTests/TestDefinition.php';
require $root . '/src/IntegrationTests/TestResult.php';
require $root . '/src/IntegrationTests/TestRegistry.php';
require $root . '/src/IntegrationTests/TestEndpointController.php';
require $root . '/src/IntegrationTests/IntegrationTestRuntime.php';
require $root . '/src/IntegrationTests/TestRunner.php';

use Hexa\PluginCore\CoreRuntime\PluginContext;
use Hexa\PluginCore\IntegrationTests\IntegrationTestRuntime;
use Hexa\PluginCore\IntegrationTests\TestRegistry;
use Hexa\PluginCore\IntegrationTests\TestRunner;

function integration_assert( bool $condition, string $message ): void {
    global $fixture_root;
    if ( ! $condition ) {
        @unlink( $fixture_root . '/PACKAGE_HASH' );
        @unlink( $fixture_root . '/sample-plugin.php' );
        @rmdir( $fixture_root );
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

$context = new PluginContext(
    [
        'slug' => 'sample-plugin', 'basename' => 'sample-plugin/sample-plugin.php', 'version' => '3.4.5',
        'path' => $fixture_root, 'url' => 'https://example.test/wp-content/plugins/sample-plugin/',
        'github_repo' => 'mikeyperes/sample-plugin', 'admin_page' => 'sample-plugin', 'capability' => 'manage_options',
    ]
);
IntegrationTestRuntime::register_host( $context );

add_action( 'hexa_plugin_core_register_integration_tests', static function( TestRegistry $registry ): void {
    $registry->register( 'sample-plugin.behavior', 'Sample behavior passes', static fn(): array => [
        'passed' => true, 'summary' => 'The host-defined callback ran.', 'expected' => 'pass', 'actual' => 'pass', 'details' => [ 'contract' => 'registered by host' ],
    ], [ 'group' => 'Sample Plugin', 'host' => 'sample-plugin' ] );
} );

$report = ( new TestRunner() )->run();
integration_assert( 'pass' === $report['status'], 'A healthy Core and host contract must pass.' );
integration_assert( 6 === $report['total'], 'The report must include three Core, two host, and one plugin-defined test.' );
integration_assert( 6 === $report['passed'] && 0 === $report['failed'], 'All fixture tests must be counted as passing.' );
integration_assert( isset( $test_actions['admin_menu'] ), 'Registering a host must expose the protected Tools endpoint.' );

$filtered = ( new TestRunner() )->run( [ 'host' => 'sample-plugin' ] );
integration_assert( 3 === $filtered['total'], 'Host filtering must return only host-context and host-defined tests.' );

@unlink( $fixture_root . '/PACKAGE_HASH' );
@unlink( $fixture_root . '/sample-plugin.php' );
@rmdir( $fixture_root );

echo "PASS: IntegrationTests provides Core checks, host contracts, plugin callbacks, filtering, and endpoint registration.\n";
