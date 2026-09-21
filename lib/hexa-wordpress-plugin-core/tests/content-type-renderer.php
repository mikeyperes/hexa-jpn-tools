<?php

declare(strict_types=1);

$content_type_renderer_options = [];
$content_type_renderer_registered = [ 'press-release' => true ];
$content_type_renderer_acf_registered = [ 'group_press_release' => true ];

function sanitize_key( mixed $value ): string {
    return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ) ?: '';
}
function sanitize_title( mixed $value ): string {
    return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ) ?: '', '-' );
}
function sanitize_text_field( mixed $value ): string {
    return trim( strip_tags( (string) $value ) );
}
function sanitize_html_class( mixed $value ): string {
    return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $value ) ?: '';
}
function get_option( string $name, mixed $default = false ): mixed {
    global $content_type_renderer_options;
    return $content_type_renderer_options[ $name ] ?? $default;
}
function post_type_exists( string $key ): bool {
    global $content_type_renderer_registered;
    return ! empty( $content_type_renderer_registered[ $key ] );
}
function acf_get_local_field_group( string $key ): array|false {
    global $content_type_renderer_acf_registered;
    return ! empty( $content_type_renderer_acf_registered[ $key ] ) ? [ 'key' => $key ] : false;
}
function admin_url( string $path = '' ): string {
    return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}
function wp_create_nonce( string $action ): string {
    return 'test-nonce-' . $action;
}
function esc_url( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function wp_kses_post( mixed $value ): string {
    return (string) $value;
}

$root = dirname( __DIR__ );
require $root . '/src/CoreContracts/ModuleInterface.php';
require $root . '/src/WpAdminComponents/CoreUi.php';
require $root . '/src/ContentTypes/ContentTypeDefinition.php';
require $root . '/src/ContentTypes/ContentTypeSettingsStore.php';
require $root . '/src/ContentTypes/ContentTypeRegistry.php';
require $root . '/src/ContentTypes/ContentTypeRenderer.php';

use Hexa\PluginCore\ContentTypes\ContentTypeRegistry;
use Hexa\PluginCore\ContentTypes\ContentTypeRenderer;

function content_type_renderer_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

$registry = new ContentTypeRegistry(
    [
        'option_name'  => 'test_content_type_renderer',
        'ajax_action'  => 'test_save_content_type',
        'nonce_action' => 'test_content_types',
    ]
);
$registry->add(
    [
        'id'          => 'press-release',
        'owner'       => 'Test host',
        'description' => 'Published press releases.',
        'post_type'   => [
            'key'          => 'press-release',
            'singular'     => 'Press Release',
            'plural'       => 'Press Releases',
            'rewrite_slug' => 'press-release',
            'args'         => [
                'has_archive' => false,
                'supports'    => [ 'title', 'editor', 'thumbnail' ],
            ],
        ],
        'taxonomies'  => [ 'topic' ],
        'field_groups' => [
            [
                'id'          => 'press-release-fields',
                'label'       => 'Press Release Fields',
                'description' => 'Press release details.',
                'group_key'   => 'group_press_release',
                'fields'      => [ 'Source URL', 'Publication date' ],
                'definition'  => [
                    'fields' => [
                        [
                            'key'      => 'field_source_url',
                            'label'    => 'Source URL',
                            'name'     => 'source_url',
                            'type'     => 'url',
                            'required' => 1,
                        ],
                        [
                            'key'   => 'field_publication_date',
                            'label' => 'Publication date',
                            'name'  => 'publication_date',
                            'type'  => 'date_picker',
                        ],
                    ],
                ],
            ],
            [
                'id'           => 'distribution-fields',
                'label'        => 'Distribution Fields',
                'description'  => 'Distribution metadata.',
                'group_key'    => 'group_distribution',
                'fields'       => [ 'Distribution channel' ],
                'dependencies' => [ 'Advanced Custom Fields Pro' ],
            ],
        ],
    ]
);

$html = ( new ContentTypeRenderer() )->render( $registry );
$renderer_source = (string) file_get_contents( $root . '/src/ContentTypes/ContentTypeRenderer.php' );

content_type_renderer_assert( str_contains( $html, '<details class="hpc-section hpc-content-type-parent">' ), 'Each CPT should render as the parent accordion.' );
content_type_renderer_assert( ! preg_match( '/<details class="hpc-section hpc-content-type-parent"[^>]*\sopen(?:\s|>)/', $html ), 'CPT parent accordions must be collapsed by default.' );
content_type_renderer_assert( str_contains( $html, '<span class="hpc-section-title">Press Releases</span>' ), 'The CPT header should show the actual title without a prefix.' );
content_type_renderer_assert( str_contains( $html, 'id="hpc-content-type-enabled-press-release"' ), 'The CPT enable control should be in its card header.' );
content_type_renderer_assert( ! str_contains( $html, 'hpc-content-type-mini-status' ) && ! str_contains( $html, 'hpc-content-type-summary-key' ), 'CPT headers must not show redundant state badges or keys.' );
content_type_renderer_assert( str_contains( $html, 'Labels and URL' ), 'Editable labels and URL should remain visible.' );
content_type_renderer_assert( str_contains( $html, '<summary>CPT technical details</summary>' ), 'Secondary CPT metadata should remain inside the parent CPT section.' );
content_type_renderer_assert( str_contains( $html, '<section class="hpc-content-type-acf-siblings"' ), 'The ACF collection should render as a sibling outside the CPT accordion.' );
content_type_renderer_assert( str_contains( $html, 'ACF field groups for Press Releases' ), 'The ACF collection should identify its owning CPT.' );
content_type_renderer_assert( 2 === substr_count( $html, '<details class="hpc-detail-card hpc-content-type-acf-group">' ), 'Every ACF group should render as its own child accordion.' );
content_type_renderer_assert( ! preg_match( '/<details class="hpc-detail-card hpc-content-type-acf-group"[^>]*\sopen(?:\s|>)/', $html ), 'ACF child accordions must be collapsed by default.' );
content_type_renderer_assert( str_contains( $html, '<span class="hpc-detail-card-title">Press Release Fields</span>' ) && str_contains( $html, '<span class="hpc-detail-card-title">Distribution Fields</span>' ), 'ACF headers should show the actual field-group titles without prefixes.' );
content_type_renderer_assert( str_contains( $html, 'id="hpc-content-type-acf-enabled-press-release-press-release-fields"' ) && str_contains( $html, 'id="hpc-content-type-acf-enabled-press-release-distribution-fields"' ), 'Each ACF card should put its enable control in the header.' );
content_type_renderer_assert( str_contains( $renderer_source, "details.open=wasOpen" ), 'Header switches should not change the accordion open state.' );
content_type_renderer_assert( str_contains( $renderer_source, '.hpc-detail-card.hpc-content-type-acf-group{background:#f8fafc' ), 'ACF sibling cards should use the secondary surface treatment.' );
content_type_renderer_assert( str_contains( $renderer_source, '.hpc-detail-card.hpc-content-type-acf-group>summary .hpc-detail-card-title{font-size:12px' ), 'ACF sibling titles should remain visually subordinate to CPT titles.' );
content_type_renderer_assert( str_contains( $renderer_source, '.hpc-detail-card.hpc-content-type-acf-group .hpc-toggle-ui{height:18px;width:34px}' ), 'ACF sibling switches should use the compact secondary size.' );
content_type_renderer_assert( str_contains( $html, 'Group details' ) && str_contains( $html, 'Attached CPT' ), 'Each ACF child should show its group metadata and parent CPT.' );
content_type_renderer_assert( str_contains( $html, 'Imported fields' ) && str_contains( $html, 'Source URL' ), 'Each ACF child should show its field inventory.' );
content_type_renderer_assert( str_contains( $html, '<strong>Source URL</strong>' ) && str_contains( $html, '>source_url</span>' ) && str_contains( $html, '>url</span>' ), 'Structured ACF rows should show label, name, and type.' );
content_type_renderer_assert( str_contains( $html, '<summary>JSON breakdown</summary>' ) && str_contains( $html, '&quot;name&quot;: &quot;source_url&quot;' ), 'Each structured ACF field should expose its definition as collapsed JSON.' );
content_type_renderer_assert( ! str_contains( $html, '<summary>View fields</summary>' ), 'Field inventories should belong directly to their ACF child section.' );
content_type_renderer_assert( str_contains( $html, '>Save CPT and ACF settings</button>' ), 'The parent action should describe the settings it saves.' );
content_type_renderer_assert( str_contains( $renderer_source, '.hpc-content-types{margin-bottom:36px' ) && str_contains( $renderer_source, '.hpc-content-type-list{display:flex;flex-direction:column;gap:24px}' ), 'Content-type sections and following panels should have deliberate vertical spacing.' );
content_type_renderer_assert( ! str_contains( $renderer_source, 'hpc-content-type-grid' ), 'The content-type renderer must not restore the split dashboard grid.' );
content_type_renderer_assert( ! str_contains( $renderer_source, 'hpc-content-type-facts' ), 'Technical facts must not render as a fact-card grid.' );
content_type_renderer_assert( ! str_contains( $renderer_source, 'grid-template-columns' ), 'Content-type settings must remain single-column.' );

$parent_position = strpos( $html, '<details class="hpc-section hpc-content-type-parent">' );
$enable_position = strpos( $html, 'id="hpc-content-type-enabled-press-release"' );
$labels_position = strpos( $html, 'Labels and URL' );
$technical_position = strpos( $html, 'CPT technical details' );
$acf_collection_position = strpos( $html, '<section class="hpc-content-type-acf-siblings"' );
$first_acf_position = strpos( $html, '<span class="hpc-detail-card-title">Press Release Fields</span>' );
$second_acf_position = strpos( $html, '<span class="hpc-detail-card-title">Distribution Fields</span>' );
$save_position = strpos( $html, 'Save CPT and ACF settings' );
$parent_markup = false !== $parent_position && false !== $acf_collection_position
    ? substr( $html, $parent_position, $acf_collection_position - $parent_position )
    : '';

content_type_renderer_assert(
    '' !== $parent_markup && substr_count( $parent_markup, '<details' ) === substr_count( $parent_markup, '</details>' ),
    'The CPT accordion must close before its ACF sibling collection begins.'
);

content_type_renderer_assert(
    false !== $parent_position
    && false !== $enable_position
    && false !== $labels_position
    && false !== $technical_position
    && false !== $acf_collection_position
    && false !== $first_acf_position
    && false !== $second_acf_position
    && false !== $save_position
    && $parent_position < $enable_position
    && $enable_position < $labels_position
    && $labels_position < $technical_position
    && $technical_position < $acf_collection_position
    && $acf_collection_position < $first_acf_position
    && $first_acf_position < $second_acf_position
    && $second_acf_position < $save_position,
    'Each CPT should be followed immediately by its external ACF sibling cards.'
);

echo "PASS: CPT headers own their enable controls and external ACF siblings follow each CPT.\n";
