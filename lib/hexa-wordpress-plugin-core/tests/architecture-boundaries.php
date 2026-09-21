<?php

declare(strict_types=1);

class WP_Error {
    public function __construct( public string $code = '', public string $message = '' ) {
    }
}

function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function esc_js( mixed $value ): string {
    return addslashes( (string) $value );
}

function wp_json_encode( mixed $value ): string {
    return (string) json_encode( $value );
}

$root = dirname( __DIR__ );

require $root . '/src/GettingStartedChecklist/GettingStartedChecklistAssets.php';
require $root . '/src/SiteStructure/PageStructureMenuService.php';
require $root . '/src/SiteStructure/PageStructureTemplateService.php';
require $root . '/src/SiteStructure/PageStructureManager.php';
require $root . '/src/SiteStructure/SiteStructureScriptRenderer.php';

$bounded_files = [
    'src/GettingStartedChecklist/GettingStartedChecklistRenderer.php',
    'src/GettingStartedChecklist/GettingStartedChecklistAssets.php',
    'src/SiteStructure/PageStructureManager.php',
    'src/SiteStructure/PageStructureMenuService.php',
    'src/SiteStructure/PageStructureTemplateService.php',
    'src/SiteStructure/SiteStructureRenderer.php',
    'src/SiteStructure/SiteStructureScriptRenderer.php',
];

foreach ( $bounded_files as $relative_file ) {
    $line_count = count( file( $root . '/' . $relative_file ) );
    if ( $line_count >= 700 ) {
        fwrite( STDERR, 'FAIL: ' . $relative_file . ' has ' . $line_count . " lines.\n" );
        exit( 1 );
    }
}

$expected_methods = [
    '__construct',
    'all_pages',
    'apply_template',
    'assign_page',
    'assigned_page',
    'assigned_page_id',
    'attach_menu_structure',
    'attach_page_to_menu_item',
    'create_custom_menu_item',
    'create_navigation_menu',
    'create_page',
    'default_template',
    'delete_navigation_menu',
    'delete_page',
    'find_page_menu_item',
    'flat_pages',
    'get_menu_item_from_menu',
    'guess_menu_id_for_structure',
    'is_assigned_page_set',
    'is_assigned_to_published_page',
    'is_managed_page',
    'mark_managed_page',
    'menu_inventory_payload',
    'menu_item_labels',
    'menu_structures',
    'option_key',
    'page_payload',
    'page_workspace_payload',
    'pages',
    'save_template',
    'stored_template',
    'template_content',
    'update_page_slug',
    'upsert_page_menu_item',
];

$reflection = new ReflectionClass( Hexa\PluginCore\SiteStructure\PageStructureManager::class );
$actual_methods = array_map(
    static fn( ReflectionMethod $method ): string => $method->getName(),
    array_filter(
        $reflection->getMethods( ReflectionMethod::IS_PUBLIC ),
        static fn( ReflectionMethod $method ): bool => $method->getDeclaringClass()->getName() === $reflection->getName()
    )
);
sort( $expected_methods );
sort( $actual_methods );

if ( $expected_methods !== $actual_methods ) {
    fwrite( STDERR, "FAIL: PageStructureManager public API changed during decomposition.\n" );
    exit( 1 );
}

$manager = new Hexa\PluginCore\SiteStructure\PageStructureManager(
    [
        'pages' => [
            'about' => [
                'title' => 'About',
                'slug'  => 'about',
            ],
        ],
        'default_templates' => [
            'about' => '<h2>About</h2>',
        ],
        'assignment_getter' => static fn( string $page_key ): int => 0,
        'menu_guess_terms' => [
            'header' => [ 'primary' ],
        ],
    ]
);

$menu_rows = $manager->menu_item_labels(
    [
        (object) [
            'ID'               => 10,
            'title'            => 'Parent',
            'menu_item_parent' => 0,
        ],
        (object) [
            'ID'               => 11,
            'title'            => 'Child',
            'menu_item_parent' => 10,
        ],
    ]
);

if ( 2 !== count( $menu_rows ) || '-- Child' !== $menu_rows[1]['label'] ) {
    fwrite( STDERR, "FAIL: Menu delegation changed hierarchy labels.\n" );
    exit( 1 );
}

$menu_id = $manager->guess_menu_id_for_structure(
    'header',
    [
        (object) [ 'term_id' => 44, 'name' => 'Primary Navigation' ],
    ]
);
if ( 44 !== $menu_id ) {
    fwrite( STDERR, "FAIL: Menu delegation changed structure matching.\n" );
    exit( 1 );
}

$workspace = $manager->page_workspace_payload( 'about' );
if ( $workspace instanceof WP_Error || '<h2>About</h2>' !== $workspace['template'] ) {
    fwrite( STDERR, "FAIL: Template delegation changed workspace payloads.\n" );
    exit( 1 );
}

$checklist_assets = Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistAssets::render( 'architecture-checklist' );
if ( ! str_contains( $checklist_assets, '#architecture-checklist' ) || ! str_contains( $checklist_assets, '[data-gsc-run-all]' ) ) {
    fwrite( STDERR, "FAIL: Extracted checklist assets did not render their scoped behavior.\n" );
    exit( 1 );
}

$structure_script = Hexa\PluginCore\SiteStructure\SiteStructureScriptRenderer::render(
    'architecture-structure',
    [
        'nonce'   => 'test-nonce',
        'actions' => [],
    ]
);
if ( ! str_contains( $structure_script, '#architecture-structure' ) || ! str_contains( $structure_script, 'test-nonce' ) ) {
    fwrite( STDERR, "FAIL: Extracted site-structure script did not render its payload.\n" );
    exit( 1 );
}

echo "PASS: Renderer assets and page-manager responsibilities are bounded without API loss.\n";
