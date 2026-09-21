<?php

declare(strict_types=1);

function sanitize_key( mixed $value ): string {
    return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ) ?: '';
}
function sanitize_html_class( mixed $value ): string {
    return preg_replace( '/[^a-zA-Z0-9_\-]/', '-', (string) $value ) ?: '';
}
function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function checked( mixed $checked, mixed $current = true, bool $display = true ): string {
    $result = $checked == $current ? ' checked="checked"' : '';
    if ( $display ) {
        echo $result;
    }
    return $result;
}

$root = dirname( __DIR__ );
require $root . '/src/WpAdminComponents/CoreUi.php';
require $root . '/src/WpAdminComponents/TemplateSelectionControl.php';

use Hexa\PluginCore\WpAdminComponents\TemplateSelectionControl;

function template_selection_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

ob_start();
$markup = TemplateSelectionControl::render(
    [
        'id'             => 'article-card-design',
        'name'           => 'article_card_template',
        'value'          => 'card-b',
        'title'          => 'Article cards',
        'description'    => 'Choose one design.',
        'columns'        => 3,
        'preview_height' => 220,
        'preview_width'  => 960,
        'input_class'    => 'host-setting',
        'input_data'     => [ 'target' => 'homepage' ],
        'templates'      => [
            'card-a' => [
                'label'        => 'Card A',
                'description'  => 'First design.',
                'preview_html' => '<div class="preview-a">Preview A</div>',
            ],
            'card-b' => [
                'label'        => 'Card B',
                'description'  => 'Second design.',
                'preview_html' => '<div class="preview-b">Preview B</div>',
                'actions_html' => '<a class="button" href="#preview-b">View live</a>',
            ],
        ],
    ]
);
ob_end_clean();

preg_match_all( '/<input[^>]+data-hpc-template-selection-input/s', $markup, $radio_inputs );
template_selection_assert( 3 === count( $radio_inputs[0] ), 'The two host templates and built-in custom option must render as radios.' );
template_selection_assert( str_contains( $markup, 'grid-template-columns:repeat(var(--hpc-template-columns,3),minmax(0,1fr))' ), 'Desktop layout must be an explicit three-column grid.' );
template_selection_assert( str_contains( $markup, '--hpc-template-preview-height:220px' ) && str_contains( $markup, 'data-preview-width="960"' ), 'Preview dimensions must be stable and host-configurable.' );
template_selection_assert( str_contains( $markup, 'value="card-b"' ) && preg_match( '/value="card-b"[^>]+checked="checked"/s', $markup ) === 1, 'The saved template must be selected.' );
template_selection_assert( str_contains( html_entity_decode( $markup, ENT_QUOTES, 'UTF-8' ), "I'm going to design it myself" ) && str_contains( $markup, 'No plugin design output' ), 'Core must include the plain-language custom-design option.' );
template_selection_assert( str_contains( $markup, 'class="hpc-template-selection-input host-setting"' ) && str_contains( $markup, 'data-target="homepage"' ), 'Host save hooks must reach every radio input.' );
template_selection_assert( str_contains( $markup, 'hexa-template-selection-change' ) && str_contains( $markup, 'hexa-core-host-tab-loaded' ), 'Core must emit selection changes and reinitialize AJAX-loaded tabs.' );
template_selection_assert( str_contains( $markup, 'pointer-events:none' ) && str_contains( $markup, 'ResizeObserver' ), 'Live renderer previews must be isolated and scaled inside fixed viewports.' );
template_selection_assert( str_contains( $markup, '<a class="button" href="#preview-b">View live</a>' ), 'Hosts must be able to provide a separate live-preview action.' );
template_selection_assert( ! str_contains( (string) file_get_contents( $root . '/src/WpAdminComponents/TemplateSelectionControl.php' ), 'smp-' ), 'The reusable control must remain host-neutral.' );

ob_start();
$toggle_markup = TemplateSelectionControl::render(
    [
        'id'             => 'full-width-design',
        'name'           => 'full_width_template',
        'value'          => 'custom',
        'columns'        => 1,
        'custom_control' => 'toggle',
        'custom'         => [
            'label'              => 'No plugin design',
            'toggle_label'       => 'No plugin design',
            'toggle_description' => 'Disable every template choice.',
        ],
        'templates'      => [
            'card-a' => [ 'label' => 'Card A', 'preview_html' => '<div>A</div>' ],
            'card-b' => [ 'label' => 'Card B', 'preview_html' => '<div>B</div>' ],
        ],
    ]
);
ob_end_clean();

preg_match_all( '/data-hpc-template-selection-option/', $toggle_markup, $toggle_cards );
template_selection_assert( 2 === count( $toggle_cards[0] ), 'Toggle mode must keep the no-design state above the template grid instead of rendering it as a card.' );
template_selection_assert( str_contains( $toggle_markup, 'data-hpc-template-custom-toggle="1"' ) && str_contains( $toggle_markup, 'data-hpc-template-custom-input' ), 'Toggle mode must bind the visible Core toggle to the host template value.' );
template_selection_assert( str_contains( $toggle_markup, 'Disable every template choice.' ) && str_contains( $toggle_markup, 'is-custom-mode' ) && str_contains( $toggle_markup, 'aria-disabled="true"' ), 'A saved no-design state must clearly disable the visual choices.' );
template_selection_assert( str_contains( $toggle_markup, '--hpc-template-columns:1;--hpc-template-tablet-columns:1;' ) && str_contains( $markup, 'repeat(var(--hpc-template-tablet-columns,2)' ), 'A one-column host selector must remain one column at the tablet breakpoint.' );
template_selection_assert( str_contains( $markup, 'option.disabled=custom' ) && str_contains( $markup, 'data-hpc-template-selection-last' ), 'Core must disable template radios and restore the previous design when no-design mode is turned off.' );

echo "PASS: TemplateSelectionControl owns reusable visual grids and a top-level no-design toggle mode.\n";
