<?php

declare( strict_types=1 );

$GLOBALS['gsc_options'] = [];
$GLOBALS['gsc_capabilities'] = [];
function sanitize_key( mixed $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ) ?: ''; }
function sanitize_html_class( mixed $value ): string { return preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) ?: ''; }
function sanitize_text_field( mixed $value ): string { return trim( strip_tags( (string) $value ) ); }
function get_option( string $key, mixed $default = null ): mixed { return $GLOBALS['gsc_options'][ $key ] ?? $default; }
function update_option( string $key, mixed $value, bool $autoload = false ): bool { $GLOBALS['gsc_options'][ $key ] = $value; return true; }
function delete_option( string $key ): bool { unset( $GLOBALS['gsc_options'][ $key ] ); return true; }
function current_time( string $format, bool $gmt = false ): string { return 'H:i:s' === $format ? '12:00:00' : '2026-01-01 12:00:00'; }
function current_user_can( string $capability ): bool { return ! empty( $GLOBALS['gsc_capabilities'][ $capability ] ); }

$root = dirname( __DIR__ );
foreach ( [ 'GettingStartedChecklistSubtask', 'GettingStartedChecklistStep', 'GettingStartedChecklistTemplate', 'GettingStartedChecklistConfig', 'GettingStartedChecklistStateStore', 'GettingStartedChecklistRunner' ] as $class ) {
    require $root . '/src/GettingStartedChecklist/' . $class . '.php';
}

use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistConfig;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistRunner;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistStateStore;

$config = new GettingStartedChecklistConfig( [
    'root_id' => 'persistent-checklist',
    'steps' => [ [
        'id' => 'review', 'label' => 'Review', 'destructive' => true, 'batch_enabled' => false,
        'subtasks' => [ [ 'id' => 'confirm', 'label' => 'Confirm', 'callback' => static fn(): array => [ 'success' => true, 'message' => 'Confirmed.' ] ] ],
    ] ],
] );
$step = $config->find_step( 'review' );
if ( ! $step || ! $step->destructive || $step->batch_enabled || true !== $step->to_public_array()['destructive'] ) {
    fwrite( STDERR, "FAIL: Checklist safety metadata was not normalized.\n" );
    exit( 1 );
}
$result = ( new GettingStartedChecklistRunner( $config ) )->run_item( 'review', 'confirm' );
$state = ( new GettingStartedChecklistStateStore( $config ) )->status();
if ( ! $result['success'] || empty( $state['summary']['complete'] ) || 2 !== $state['summary']['success'] ) {
    fwrite( STDERR, "FAIL: Checklist outcome was not persisted and summarized.\n" );
    exit( 1 );
}
( new GettingStartedChecklistStateStore( $config ) )->reset();
if ( 2 !== ( new GettingStartedChecklistStateStore( $config ) )->summary()['pending'] ) {
    fwrite( STDERR, "FAIL: Checklist reset did not clear persistent state.\n" );
    exit( 1 );
}

$dry_runs = 0;
$live_runs = 0;
$secure_config = new GettingStartedChecklistConfig( [
    'template_id' => 'live',
    'templates'   => [
        'live' => [
            'steps' => [
                [
                    'id'         => 'live-only',
                    'label'      => 'Live only',
                    'type'       => 'status_check',
                    'capability' => 'edit_plugins',
                    'callback'   => static function () use ( &$live_runs ): bool { $live_runs++; return true; },
                    'subtasks'   => [
                        [
                            'id'         => 'restricted',
                            'label'      => 'Restricted',
                            'type'       => 'config_mutation',
                            'capability' => 'delete_plugins',
                            'callback'   => static function () use ( &$live_runs ): bool { $live_runs++; return true; },
                        ],
                    ],
                ],
            ],
        ],
        'dry-run' => [
            'steps' => [
                [
                    'id'       => 'dry-only',
                    'label'    => 'Dry only',
                    'callback' => static function () use ( &$dry_runs ): bool { $dry_runs++; return true; },
                ],
            ],
        ],
    ],
] );
$secure_runner = new GettingStartedChecklistRunner( $secure_config );
$GLOBALS['gsc_capabilities']['edit_plugins'] = true;

$cross_template = $secure_runner->run_item( 'dry-only', '', [], 'live' );
$crafted_template = $secure_runner->run_item( 'dry-only', '', [], 'dry-run!' );
$missing_capability = $secure_runner->run_item( 'live-only', 'restricted', [], 'live' );
if ( $cross_template['success'] || $crafted_template['success'] || $missing_capability['success'] || 0 !== $dry_runs || 0 !== $live_runs ) {
    fwrite( STDERR, "FAIL: Exact-template or item-capability enforcement allowed an unauthorized callback.\n" );
    exit( 1 );
}

$GLOBALS['gsc_capabilities']['delete_plugins'] = true;
$authorized = $secure_runner->run_item( 'live-only', 'restricted', [], 'live' );
$live_step = $secure_config->find_step( 'live-only', 'live' );
$restricted = $live_step?->find_subtask( 'restricted' );
if ( ! $authorized['success'] || 1 !== $live_runs || ! $live_step || ! $restricted || [] !== $secure_config->template_steps( 'dry-run!' ) || false !== $live_step->mutating || true !== $restricted->mutating || 'edit_plugins' !== $live_step->to_public_array()['capability'] || 'delete_plugins' !== $restricted->to_public_array()['capability'] ) {
    fwrite( STDERR, "FAIL: Checklist capability or mutation metadata was not normalized and enforced.\n" );
    exit( 1 );
}

$before_invalid_reset = $GLOBALS['gsc_options'][ $secure_config->state_option() ] ?? [];
( new GettingStartedChecklistStateStore( $secure_config ) )->reset( 'dry-run!' );
if ( $before_invalid_reset !== ( $GLOBALS['gsc_options'][ $secure_config->state_option() ] ?? [] ) ) {
    fwrite( STDERR, "FAIL: Invalid template reset changed another template's saved state.\n" );
    exit( 1 );
}

$renderer = (string) file_get_contents( $root . '/src/GettingStartedChecklist/GettingStartedChecklistRenderer.php' );
$assets = (string) file_get_contents( $root . '/src/GettingStartedChecklist/GettingStartedChecklistAssets.php' );
$controller = (string) file_get_contents( $root . '/src/GettingStartedChecklist/GettingStartedChecklistAjaxController.php' );
if ( ! str_contains( $renderer, 'Individual only' ) || ! str_contains( $renderer, 'Destructive' ) || ! str_contains( $renderer, 'data-mutating=') || ! str_contains( $assets, "batchMode && stepRow.dataset.batchEnabled === '0'" ) || ! str_contains( $assets, 'stepOutcome.mutatingFailure' ) || ! str_contains( $assets, 'break;' ) || ! str_contains( $assets, 'normalizeRunOutcome' ) || ! str_contains( $assets, 'resetStatus()' ) || ! str_contains( $controller, "'capability'   => \$this->config->capability()" ) || ! str_contains( $controller, '$this->runner->authorize_item' ) ) {
    fwrite( STDERR, "FAIL: Checklist safety labels or real-time UI state protocol is missing.\n" );
    exit( 1 );
}
echo "PASS: Getting Started state persists, summarizes, resets, and skips individual-only batch actions.\n";
