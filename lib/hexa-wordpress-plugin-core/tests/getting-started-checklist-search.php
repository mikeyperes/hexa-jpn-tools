<?php

declare(strict_types=1);

function sanitize_key( mixed $value ): string {
    return trim( strtolower( preg_replace( '/[^a-z0-9_-]+/i', '-', (string) $value ) ?? '' ), '-' );
}

function sanitize_html_class( mixed $value ): string {
    return preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $value ) ?: '';
}

function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function esc_url( mixed $value ): string {
    return (string) $value;
}

function esc_js( mixed $value ): string {
    return addslashes( (string) $value );
}

function wp_json_encode( mixed $value ): string|false {
    return json_encode( $value );
}

function wp_create_nonce( string $action ): string {
    return 'nonce-' . $action;
}

function admin_url( string $path = '' ): string {
    return 'https://example.test/wp-admin/' . $path;
}

function selected( mixed $selected, mixed $current = true, bool $display = true ): string {
    $attribute = $selected === $current ? ' selected="selected"' : '';
    if ( $display ) {
        echo $attribute;
    }
    return $attribute;
}

$root = dirname( __DIR__ );
require $root . '/src/WpAdminComponents/CoreUi.php';
require $root . '/src/WpAdminComponents/DynamicButton.php';
require $root . '/src/GettingStartedChecklist/GettingStartedChecklistSubtask.php';
require $root . '/src/GettingStartedChecklist/GettingStartedChecklistStep.php';
require $root . '/src/GettingStartedChecklist/GettingStartedChecklistTemplate.php';
require $root . '/src/GettingStartedChecklist/GettingStartedChecklistConfig.php';
require $root . '/src/GettingStartedChecklist/GettingStartedChecklistAssets.php';
require $root . '/src/GettingStartedChecklist/GettingStartedChecklistRenderer.php';

use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistConfig;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistRenderer;

$config = new GettingStartedChecklistConfig(
    [
        'root_id'              => 'searchable-checklist',
        'show_search'          => true,
        'search_label'         => 'Find setup work',
        'search_placeholder'   => 'Search setup work...',
        'search_empty_message' => 'Nothing matches this setup search.',
        'steps'                => [
            [
                'id'          => 'site_setup',
                'label'       => 'Site Setup',
                'type'        => 'setup_action',
                'description' => 'Configure the website.',
                'subtasks'    => [
                    [
                        'id'          => 'redis_cache',
                        'label'       => 'Enable Redis Cache',
                        'description' => 'Connect the object cache.',
                    ],
                ],
            ],
            [
                'id'          => 'check_version',
                'label'       => 'Check Plugin Version',
                'description' => 'Confirm the current release.',
            ],
        ],
    ]
);

ob_start();
( new GettingStartedChecklistRenderer( $config ) )->render();
$html = (string) ob_get_clean();
$ui_source = (string) file_get_contents( $root . '/src/WpAdminComponents/CoreUi.php' );
$checklist_source = (string) file_get_contents( $root . '/src/GettingStartedChecklist/GettingStartedChecklistAssets.php' );

$template_config = new GettingStartedChecklistConfig(
    [
        'root_id'              => 'template-checklist',
        'show_template_picker' => true,
        'templates'            => [
            'default'   => [ 'label' => 'Default', 'steps' => [] ],
            'alternate' => [ 'label' => 'Alternate', 'steps' => [] ],
        ],
    ]
);
ob_start();
( new GettingStartedChecklistRenderer( $template_config ) )->render();
$template_html = (string) ob_get_clean();

$checks = [
    'Enables search only when the host opts in.' => $config->show_search(),
    'Renders the host labels and accessible collection target.' => str_contains( $html, '>Find setup work</label>' )
        && str_contains( $html, 'placeholder="Search setup work..."' )
        && str_contains( $html, 'aria-controls="searchable-checklist-items"' )
        && str_contains( $html, 'id="searchable-checklist-items"' ),
    'Filters actionable nested and standalone checklist items.' => str_contains( $html, 'data-item-selector="[data-gsc-filter-item]"' )
        && str_contains( $html, 'data-group-selector="[data-gsc-step-card]"' )
        && str_contains( $html, 'hpc-gsc-subtask-row" data-gsc-item data-gsc-filter-item' )
        && str_contains( $html, 'hpc-gsc-step-single" data-gsc-filter-item' ),
    'Includes parent and child metadata in nested search text.' => str_contains( $html, 'data-hpc-filter-text="Site Setup Configure the website. site_setup setup_action Enable Redis Cache Connect the object cache. redis_cache callback"' ),
    'Includes standalone step metadata in search text.' => str_contains( $html, 'data-hpc-filter-text="Check Plugin Version Confirm the current release. check_version callback"' ),
    'Renders the configured empty state.' => str_contains( $html, 'Nothing matches this setup search.' ),
    'Reapplies filtering when templates replace checklist rows.' => str_contains( $ui_source, 'new MutationObserver(function() { applyFilter(); })' )
        && str_contains( $ui_source, 'observe(target, {childList:true, subtree:true})' ),
    'Uses an explicit filter state that cannot be overridden by row display rules.' => str_contains( $ui_source, "item.setAttribute('data-hpc-filter-hidden', '1')" )
        && str_contains( $ui_source, '[data-hpc-filter-hidden="1"]{display:none!important}' ),
    'Allows long collapsible titles to wrap instead of truncating.' => str_contains( $ui_source, '.hpc-section-title{min-width:0;overflow-wrap:anywhere;white-space:normal}' ),
    'Renders template loading as an explicit dynamic button state.' => str_contains( $template_html, 'data-gsc-load-template' )
        && str_contains( $template_html, 'data-working-label="Loading..."' )
        && str_contains( $template_html, 'data-success-label="Template Loaded"' )
        && str_contains( $template_html, 'data-error-label="Load Failed"' ),
    'Does not claim a newly selected template was loaded before the button succeeds.' => str_contains( $checklist_source, "option.value === currentTemplateId()" )
        && str_contains( $checklist_source, "' selected — click Load Template'" )
        && str_contains( $checklist_source, "dynamicStart(loadTemplateButton, 'Loading...')" )
        && str_contains( $checklist_source, "dynamicSuccess(loadTemplateButton, 'Template Loaded')" )
        && str_contains( $checklist_source, "dynamicError(loadTemplateButton, 'Load Failed')" ),
    'Keeps parent and full checklist runs available when only child tasks need input.' => str_contains( $checklist_source, 'setButtonBlocked(stepButton, rowInputMessages(stepRow, false));' )
        && str_contains( $checklist_source, 'setButtonBlocked(runAllButton, []);' )
        && str_contains( $checklist_source, "if (!validateRowInputs(stepRow, true))" )
        && ! str_contains( $checklist_source, 'rowAndChildrenInputMessages' )
        && ! str_contains( $checklist_source, 'rowAndChildrenInputsValid' ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, 'FAIL: ' . $message . "\n" );
        exit( 1 );
    }
}

echo "PASS: Getting Started checklists provide optional nested search with dynamic template refresh.\n";
