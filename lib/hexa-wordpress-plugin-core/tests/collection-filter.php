<?php

declare(strict_types=1);

function sanitize_html_class( mixed $value ): string {
    return preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $value ) ?: '';
}

function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

$root = dirname( __DIR__ );
require $root . '/src/WpAdminComponents/CoreUi.php';

use Hexa\PluginCore\WpAdminComponents\CoreUi;

$html = CoreUi::collection_filter(
    [
        'id'                  => 'feature-search',
        'target_id'           => 'feature-collection',
        'item_selector'       => '.feature-card',
        "text_selector"       => ".feature-title, .feature-settings",
        'group_selector'      => '.feature-group',
        'label'               => 'Search features',
        'placeholder'         => 'Search features...',
        'item_label_singular' => 'feature',
        'item_label_plural'   => 'features',
        'empty_message'       => 'No matching features.',
    ]
);
$collapsible = CoreUi::collapsible(
    [
        'title'     => 'Feature',
        'body_html' => '<p>Body</p>',
        'class'     => 'feature-card invalid@class',
    ]
);
$source = (string) file_get_contents( $root . '/src/WpAdminComponents/CoreUi.php' );

$checks = [
    'Renders an accessible search field linked to its collection.' => str_contains( $html, 'type="search"' )
        && str_contains( $html, 'aria-controls="feature-collection"' )
        && str_contains( $html, '>Search features</label>'),
    'Carries reusable item and group selectors as data.' => str_contains( $html, 'data-item-selector=".feature-card"')
        && str_contains( $html, "data-text-selector=\".feature-title, .feature-settings\"" )
        && str_contains( $html, 'data-group-selector=".feature-group"'),
    'Renders clear, live-count, and no-result controls.' => str_contains( $html, 'data-hpc-filter-clear hidden')
        && str_contains( $html, 'role="status" aria-live="polite"')
        && str_contains( $html, 'No matching features.'),
    'Reserves icon space with enough specificity to beat WordPress search input padding.' => str_contains( $source, '.hpc-collection-filter-field .hpc-collection-filter-input{' )
        && str_contains( $source, 'padding:0 44px 0 46px' )
        && str_contains( $source, '.hpc-collection-filter-icon{' ),
    'Allows host cards to opt into filtering through a sanitized class.' => str_contains( $collapsible, 'class="hpc-section feature-card invalidclass"'),
    'Filters case-insensitively and force-hides nonmatching items.' => str_contains( $source, "toLocaleLowerCase()" )
        && str_contains( $source, 'item.hidden = !matches;' )
        && str_contains( $source, "item.setAttribute('data-hpc-filter-hidden', '1')" )
        && str_contains( $source, '[data-hpc-filter-hidden="1"]{display:none!important}' ),
    'Hides empty groups and reports visible versus total items.' => str_contains( $source, 'group.hidden = groupHidden;' )
        && str_contains( $source, "group.setAttribute('data-hpc-filter-hidden', '1')" )
        && str_contains( $source, "visible + ' of ' + items.length"),
    'Supports clear, Escape, initial load, and AJAX tab reloads.' => str_contains( $source, "event.key !== 'Escape'")
        && str_contains( $source, 'window.hexaPluginCoreInitCollectionFilters')
        && str_contains( $source, "DOMContentLoaded" )
        && str_contains( $source, "document.addEventListener('hexa-core-host-tab-loaded'"),
    "Uses host-selected text regions before falling back to full card text." => str_contains( $source, "selected(textSelector, item)" )
        && str_contains( $source, "data-text-selector" ),
    'Rejects a filter without a target collection.' => '' === CoreUi::collection_filter( [ 'label' => 'Invalid' ] ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, 'FAIL: ' . $message . "\n" );
        exit( 1 );
    }
}

echo "PASS: Core collection filter is reusable, accessible, group-aware, and AJAX-safe.\n";
