<?php

declare(strict_types=1);

class WP_Error {
    public function __construct( public string $code = '', public string $message = '' ) {
    }
}

function sanitize_html_class( string $value ): string {
    return preg_replace( '/[^A-Za-z0-9_-]/', '', $value ) ?: '';
}

function sanitize_key( string $value ): string {
    return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', $value ) ?: '' );
}

function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function esc_textarea( mixed $value ): string {
    return esc_html( $value );
}

function esc_js( mixed $value ): string {
    return addslashes( (string) $value );
}

function selected( mixed $selected, mixed $current, bool $display = true ): string {
    return $selected == $current ? 'selected="selected"' : '';
}

function disabled( bool $disabled, bool $current = true, bool $display = true ): string {
    return $disabled === $current ? 'disabled="disabled"' : '';
}

function wp_editor( string $content, string $editor_id, array $settings = [] ): void {
    echo '<textarea id="' . esc_attr( $editor_id ) . '">' . esc_textarea( $content ) . '</textarea>';
}

function wp_json_encode( mixed $value ): string {
    return (string) json_encode( $value );
}

function admin_url( string $path = '' ): string {
    return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}

require dirname( __DIR__ ) . '/src/SiteStructure/PageStructureMenuService.php';
require dirname( __DIR__ ) . '/src/SiteStructure/PageStructureTemplateService.php';
require dirname( __DIR__ ) . '/src/SiteStructure/PageStructureManager.php';
require dirname( __DIR__ ) . '/src/SiteStructure/SiteStructureScriptRenderer.php';
require dirname( __DIR__ ) . '/src/SiteStructure/SiteStructureRenderer.php';
use Hexa\PluginCore\SiteStructure\PageStructureManager;
use Hexa\PluginCore\SiteStructure\SiteStructureRenderer;

$manager = new PageStructureManager(
    [
        'pages' => [
            'about' => [
                'title'    => 'About',
                'slug'     => 'about',
                'template' => true,
            ],
        ],
        'default_templates' => [
            'about' => '<h2>About this publication</h2>',
        ],
        'assignment_getter' => static fn( string $page_key ): int => 0,
    ]
);

$payload = $manager->page_workspace_payload( 'about' );
if ( $payload instanceof WP_Error ) {
    fwrite( STDERR, "FAIL: Known page workspace returned WP_Error.\n" );
    exit( 1 );
}

if ( 'about' !== $payload['page_key'] || $payload['assigned'] || '<h2>About this publication</h2>' !== $payload['template'] ) {
    fwrite( STDERR, "FAIL: Lazy workspace payload did not preserve page metadata and template content.\n" );
    exit( 1 );
}

if ( ! $manager->page_workspace_payload( 'missing' ) instanceof WP_Error ) {
    fwrite( STDERR, "FAIL: Unknown page key must return WP_Error.\n" );
    exit( 1 );
}

$renderer = new SiteStructureRenderer(
    $manager,
    [
        'instance_id'            => 'lazy-pages-test',
        'show_menus'             => false,
        'enable_templates'       => true,
        'enable_template_editors'=> true,
        'show_page_details'      => true,
        'lazy_page_workspace'    => true,
        'actions'                => [ 'page_workspace' => 'test_page_workspace' ],
    ]
);
$html = $renderer->render();

if ( 1 !== substr_count( $html, '<textarea' ) || str_contains( $html, 'hpc-site-template-row' ) ) {
    fwrite( STDERR, "FAIL: Lazy renderer must output one shared editor and no per-page editor rows.\n" );
    exit( 1 );
}

echo "PASS: Lazy page workspace returns one selected page payload.\n";
