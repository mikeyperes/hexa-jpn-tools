<?php

declare(strict_types=1);

function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

$root = dirname( __DIR__ );

require_once $root . '/src/WpAdminComponents/DynamicButton.php';
require_once $root . '/src/PluginChecks/PluginCheckDefinition.php';
require_once $root . '/src/PluginChecks/PluginCheckService.php';
require_once $root . '/src/PluginChecks/PluginInventoryRenderer.php';
require_once $root . '/src/PluginChecks/PluginRecommendationRegistry.php';

use Hexa\PluginCore\PluginChecks\PluginCheckDefinition;
use Hexa\PluginCore\PluginChecks\PluginCheckService;
use Hexa\PluginCore\PluginChecks\PluginInventoryRenderer;
use Hexa\PluginCore\PluginChecks\PluginRecommendationRegistry;
use Hexa\PluginCore\WpAdminComponents\DynamicButton;

function assert_true( bool $condition, string $message ): void {
    if ( $condition ) {
        return;
    }

    fwrite( STDERR, 'FAIL: ' . $message . "\n" );
    exit( 1 );
}

function invoke_private( object $object, string $method, mixed ...$args ): mixed {
    $reflection = new ReflectionMethod( $object, $method );
    return $reflection->invoke( $object, ...$args );
}

$required = new PluginCheckDefinition(
    [
        'id'          => 'required-plugin',
        'name'        => 'Required Plugin',
        'plugin_file' => 'required/required.php',
        'required'    => true,
    ]
);
$optional = new PluginCheckDefinition(
    [
        'id'          => 'unlisted-plugin',
        'name'        => 'Unlisted Plugin',
        'plugin_file' => 'unlisted/unlisted.php',
        'required'    => false,
        'recommended' => false,
    ]
);
$forbidden = new PluginCheckDefinition(
    [
        'id'                 => 'forbidden-plugin',
        'name'               => 'Forbidden Plugin',
        'plugin_file'        => 'forbidden/forbidden.php',
        'should_not_contain' => true,
    ]
);

assert_true( true === $required->checks['installed'], 'Required definitions must check installation.' );
assert_true( true === $required->checks['active'], 'Required definitions must check activation.' );
assert_true( false === $optional->checks['installed'], 'Optional definitions must not become required implicitly.' );
assert_true( false === $optional->checks['active'], 'Optional definitions must not require activation implicitly.' );
assert_true( true === $forbidden->checks['not_installed'], 'Forbidden definitions must check absence.' );

$required_ok = [
    'required'  => true,
    'installed' => true,
    'active'    => true,
    'ok'        => true,
];
$required_missing = [
    'required'  => true,
    'installed' => false,
    'active'    => false,
    'ok'        => false,
];
$forbidden_installed = [
    'required'  => false,
    'installed' => true,
    'active'    => true,
    'ok'        => false,
];
$forbidden_absent = [
    'required'  => false,
    'installed' => false,
    'active'    => false,
    'ok'        => true,
];
$unlisted_installed = [
    'required'  => false,
    'installed' => true,
    'active'    => true,
    'ok'        => true,
];

$renderer = new PluginInventoryRenderer();

$required_ok_policy = invoke_private( $renderer, 'policy_html', $required, $required_ok );
assert_true( str_contains( $required_ok_policy, 'Required: satisfied' ), 'Satisfied required policy label is missing.' );
assert_true( str_contains( $required_ok_policy, 'is-success' ), 'Satisfied required policy must be green.' );
assert_true( ! str_contains( $required_ok_policy, 'is-danger' ), 'Satisfied required policy must not be red.' );

$required_missing_policy = invoke_private( $renderer, 'policy_html', $required, $required_missing );
assert_true( str_contains( $required_missing_policy, 'Required: missing' ), 'Missing required policy label is incorrect.' );
assert_true( str_contains( $required_missing_policy, 'is-danger' ), 'Missing required policy must be red.' );

$forbidden_installed_policy = invoke_private( $renderer, 'policy_html', $forbidden, $forbidden_installed );
assert_true( str_contains( $forbidden_installed_policy, 'Forbidden: installed' ), 'Installed forbidden policy label is incorrect.' );
assert_true( str_contains( $forbidden_installed_policy, 'is-danger' ), 'Installed forbidden policy must be red.' );

$forbidden_absent_policy = invoke_private( $renderer, 'policy_html', $forbidden, $forbidden_absent );
assert_true( str_contains( $forbidden_absent_policy, 'Forbidden: absent' ), 'Absent forbidden policy label is incorrect.' );
assert_true( str_contains( $forbidden_absent_policy, 'is-success' ), 'Absent forbidden policy must be green.' );

$unlisted_policy = invoke_private( $renderer, 'policy_html', $optional, $unlisted_installed );
assert_true( str_contains( $unlisted_policy, 'Not listed' ), 'Unlisted plugin policy label is incorrect.' );
assert_true( str_contains( $unlisted_policy, 'is-neutral' ), 'Unlisted plugin policy must be neutral.' );
assert_true( ! str_contains( $unlisted_policy, 'Forbidden' ), 'Unlisted plugin must never be marked forbidden.' );

$required_installation = invoke_private( $renderer, 'installation_html', $required, $required_ok );
assert_true( str_contains( $required_installation, 'is-pass' ), 'Installed required plugin must pass installation.' );

$forbidden_absent_installation = invoke_private( $renderer, 'installation_html', $forbidden, $forbidden_absent );
assert_true( str_contains( $forbidden_absent_installation, 'is-pass' ), 'Absent forbidden plugin must pass installation policy.' );

$unlisted_installation = invoke_private( $renderer, 'installation_html', $optional, $unlisted_installed );
assert_true( str_contains( $unlisted_installation, 'hpc-plugin-inventory-muted' ), 'Unlisted installation state must remain neutral.' );

$summary = PluginCheckService::summary(
    [
        $required_ok + [ 'checks' => $required->checks, 'update_available' => false, 'should_not_contain' => false ],
        $required_missing + [ 'checks' => $required->checks, 'update_available' => false, 'should_not_contain' => false ],
        $forbidden_installed + [ 'checks' => $forbidden->checks, 'update_available' => false, 'should_not_contain' => true ],
        $forbidden_absent + [ 'checks' => $forbidden->checks, 'update_available' => false, 'should_not_contain' => true ],
        $unlisted_installed + [ 'checks' => $optional->checks, 'update_available' => false, 'should_not_contain' => false ],
    ]
);
assert_true( 1 === $summary['missing'], 'Summary must count only the missing required plugin.' );
assert_true( 0 === $summary['inactive'], 'Summary must not count absent forbidden or optional plugins as inactive.' );
assert_true( 1 === $summary['unwanted'], 'Summary must count only the installed forbidden plugin as unwanted.' );

$registry_method = new ReflectionMethod( PluginRecommendationRegistry::class, 'unlisted_definition_from_site_plugin' );
$unlisted_definition = $registry_method->invoke(
    null,
    [
        'plugin_file' => 'sample/sample.php',
        'source'      => 'plugin',
        'name'        => 'Sample Plugin',
    ]
);
assert_true( false === $unlisted_definition['should_not_contain'], 'Registry must not convert an unlisted plugin into forbidden policy.' );
assert_true( false === $unlisted_definition['checks']['not_installed'], 'Registry must not require an unlisted plugin to be absent.' );
assert_true( str_starts_with( $unlisted_definition['id'], 'unlisted-' ), 'Registry must use neutral unlisted identifiers.' );

ob_start();
$fragment_button = DynamicButton::render( [ 'label' => 'Fragment button', 'render_assets' => false ] );
$fragment_output = (string) ob_get_clean();
assert_true( '' === $fragment_output, 'Fragment button rendering must not emit shared asset tags.' );
assert_true( str_contains( $fragment_button, 'Fragment button' ), 'Fragment button HTML was not returned.' );

$renderer_source = (string) file_get_contents( $root . '/src/PluginChecks/PluginInventoryRenderer.php' );
assert_true( ! str_contains( $renderer_source, 'requirement_badge' ), 'Legacy contradictory requirement badge remains in the renderer.' );
assert_true( str_contains( $renderer_source, '<th>Policy</th>' ), 'Policy column is missing.' );
assert_true( str_contains( $renderer_source, '<th>Installation</th>' ), 'Installation column is missing.' );
assert_true( 1 === substr_count( $renderer_source, 'DynamicButton::render(' ), 'Inventory fragments still render DynamicButton assets inside rows.' );
assert_true( str_contains( $renderer_source, 'data-label="Plugin"' ), 'Responsive plugin cell label is missing.' );
assert_true( str_contains( $renderer_source, 'hpc-plugin-inventory-inline-source' ), 'Inline source output is missing.' );
assert_true( str_contains( $renderer_source, '.hpc-plugin-inventory-table-wrap.has-inline-source{overflow:hidden}' ), 'Compact inventory must not scroll horizontally.' );
assert_true( str_contains( $renderer_source, 'table-layout:fixed' ), 'Compact inventory must use a stable fixed table layout.' );
assert_true( str_contains( $renderer_source, 'content:attr(data-label)' ), 'Compact inventory rows must expose labels when stacked.' );

$checks_renderer_source = (string) file_get_contents( $root . '/src/PluginChecks/PluginChecksRenderer.php' );
assert_true( 1 === substr_count( $checks_renderer_source, 'DynamicButton::render(' ), 'Plugin-check fragments still render DynamicButton assets inside rows.' );

echo "PASS: Plugin inventory policy states are explicit, coherent, and neutral for unlisted plugins.\n";
