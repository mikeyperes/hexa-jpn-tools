<?php

function add_action( string $hook, callable $callback, int $priority = 10 ): void { $GLOBALS['taxonomy_test_hooks'][] = [ $hook, $callback, $priority ]; }
function sanitize_key( string $value ): string { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', $value ) ?: '' ); }
function sanitize_text_field( string $value ): string { return trim( strip_tags( $value ) ); }
function taxonomy_exists( string $taxonomy ): bool { return false; }
function register_taxonomy( string $taxonomy, array $object_types, array $args ): void { $GLOBALS['taxonomy_test_registered'][] = compact( 'taxonomy', 'object_types', 'args' ); }

require_once dirname( __DIR__ ) . '/src/CoreContracts/ModuleInterface.php';
require_once dirname( __DIR__ ) . '/src/Taxonomies/TaxonomyDefinition.php';
require_once dirname( __DIR__ ) . '/src/Taxonomies/TaxonomyRegistry.php';

use Hexa\PluginCore\Taxonomies\TaxonomyRegistry;

$enabled = true;
$registry = new TaxonomyRegistry( [ 'hook_priority' => 7 ] );
$registry->add(
    [
        'id' => 'article-type', 'taxonomy' => 'article_type', 'label' => 'Article Types',
        'object_types' => static fn(): array => [ 'post', 'resources', 'post' ],
        'enabled' => static fn(): bool => $enabled,
        'args' => static fn(): array => [ 'public' => false, 'show_ui' => true ],
    ]
);
$registry->register();
if ( 7 !== $GLOBALS['taxonomy_test_hooks'][0][2] ) throw new RuntimeException( 'Hook priority was not preserved.' );
$registry->register_taxonomies();
$registered = $GLOBALS['taxonomy_test_registered'][0] ?? [];
if ( 'article_type' !== ( $registered['taxonomy'] ?? '' ) ) throw new RuntimeException( 'Taxonomy key was not registered.' );
if ( [ 'post', 'resources' ] !== ( $registered['object_types'] ?? [] ) ) throw new RuntimeException( 'Dynamic object types were not normalized.' );
if ( true !== ( $registered['args']['show_ui'] ?? false ) ) throw new RuntimeException( 'Dynamic arguments were not preserved.' );

echo "PASS taxonomies\n";
