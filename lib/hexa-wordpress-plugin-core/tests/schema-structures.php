<?php

declare(strict_types=1);

$GLOBALS['options'] = [];
function add_action( string $hook, mixed $callback, int $priority = 10, int $args = 1 ): void {}
function add_filter( string $hook, mixed $callback, int $priority = 10, int $args = 1 ): void {}
function do_action( string $hook, mixed ...$args ): void { $GLOBALS['actions'][] = [ $hook, $args ]; }
function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed { return $value; }
function sanitize_key( string $key ): string { return strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', $key ) ); }
function get_option( string $name, mixed $default = false ): mixed { return array_key_exists( $name, $GLOBALS['options'] ) ? $GLOBALS['options'][ $name ] : $default; }
function update_option( string $name, mixed $value, mixed $autoload = null ): bool { $GLOBALS['options'][ $name ] = $value; return true; }
function untrailingslashit( string $value ): string { return rtrim( $value, '/' ); }
function is_admin(): bool { return false; }
function wp_strip_all_tags( string $text, bool $breaks = false ): string { return strip_tags( $text ); }
function esc_url_raw( string $url ): string { return $url; }
function sanitize_email( string $email ): string { return $email; }

$root = dirname( __DIR__ );
foreach ( [ 'DataNormalization/ValueNormalizer', 'SchemaTools/SchemaGraph', 'SchemaTools/SchemaDocumentRenderer', 'SchemaTools/SchemaNodes', 'SchemaTools/SchemaPage', 'SchemaTools/SchemaContext', 'SchemaTools/SchemaStructures' ] as $class ) {
    require $root . '/src/' . $class . '.php';
}

use Hexa\PluginCore\SchemaTools\SchemaContext;
use Hexa\PluginCore\SchemaTools\SchemaNodes;
use Hexa\PluginCore\SchemaTools\SchemaStructures;

function structures_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

$front = new SchemaContext( SchemaContext::FRONT_PAGE, null, 'https://example.test/' );
$other = new SchemaContext( SchemaContext::OTHER, null, 'https://example.test/about/' );

SchemaStructures::register(
    'example-plugin',
    [
        'plugin'     => 'Example Plugin',
        'structures' => [
            [
                'id'      => 'homepage',
                'label'   => 'Homepage',
                'modes'   => [
                    'profile_page' => [ 'label' => 'ProfilePage', 'types' => 'ProfilePage, Person' ],
                    'person'       => [ 'label' => 'Person only', 'types' => 'Person' ],
                ],
                'default' => 'profile_page',
                'option'  => 'example_homepage_schema',
                'applies' => static fn( SchemaContext $context ): bool => $context->is_front_page(),
                'build'   => static fn( SchemaContext $context, string $mode ): array => array_filter(
                    [
                        SchemaNodes::person( [ '@id' => 'https://example.test/#person', 'name' => 'Ada' ] ),
                        'profile_page' === $mode ? SchemaNodes::web_page( [ '@id' => 'https://example.test/#webpage', 'url' => $context->url ], 'ProfilePage' ) : [],
                    ]
                ),
                'sample'  => static fn(): SchemaContext => $front,
            ],
            [
                'id'      => 'books',
                'label'   => 'Books',
                'default' => 'off',
                'applies' => static fn(): bool => true,
                'build'   => static fn(): array => [ SchemaNodes::book( [ 'name' => 'A Book' ] ) ],
            ],
            [ 'id' => 'broken', 'label' => 'No builder' ],
        ],
    ]
);

structures_assert( null === SchemaStructures::get( 'example-plugin', 'broken' ), 'A structure without a builder is not registered.' );
structures_assert( 'profile_page' === SchemaStructures::mode( 'example-plugin', 'homepage' ), 'A structure ships in its default mode.' );
structures_assert( ! SchemaStructures::enabled( 'example-plugin', 'books' ), 'A structure whose default is off stays off.' );

$nodes = SchemaStructures::nodes( 'example-plugin', $front );
structures_assert( [ 'Person', 'ProfilePage' ] === array_column( $nodes, '@type' ), 'Enabled structures that cover the page add their nodes.' );
structures_assert( [] === SchemaStructures::nodes( 'example-plugin', $other ), 'A structure only prints on the pages it covers.' );

structures_assert( ! SchemaStructures::set_mode( 'example-plugin', 'homepage', 'bogus' ), 'An unknown mode is refused.' );
structures_assert( SchemaStructures::set_mode( 'example-plugin', 'homepage', 'person' ), 'A declared mode is saved.' );
structures_assert( 'person' === get_option( 'example_homepage_schema' ), 'A structure with its own option stores its mode there.' );
structures_assert( [ 'Person' ] === array_column( SchemaStructures::nodes( 'example-plugin', $front ), '@type' ), 'The chosen mode is passed to the builder.' );

update_option( 'example_homepage_schema', 'no-longer-valid' );
structures_assert( 'profile_page' === SchemaStructures::mode( 'example-plugin', 'homepage' ), 'A stored mode that is no longer declared falls back to the default.' );

structures_assert( SchemaStructures::set_mode( 'example-plugin', 'books', 'on' ), 'A single-mode structure turns on with "on".' );
structures_assert( 'on' === ( get_option( SchemaStructures::OPTION )['example-plugin']['books'] ?? '' ), 'Structures without their own option share one Core option.' );
structures_assert( SchemaStructures::set_mode( 'example-plugin', 'homepage', 'off' ) && [ 'Book' ] === array_column( SchemaStructures::nodes( 'example-plugin', $front ), '@type' ), 'Off removes a structure from the page.' );

SchemaStructures::set_mode( 'example-plugin', 'homepage', 'profile_page' );
$preview = SchemaStructures::preview( 'example-plugin', 'homepage' );
structures_assert( $preview['ok'] && 'https://example.test/' === $preview['url'] && 2 === count( $preview['document']['@graph'] ), 'A preview builds the structure on its sample page.' );
structures_assert( ! SchemaStructures::preview( 'example-plugin', 'books' )['ok'], 'A structure without a sample page cannot be previewed.' );
structures_assert( 'Unknown schema structure.' === SchemaStructures::preview( 'example-plugin', 'missing' )['message'], 'Unknown structures report it.' );

echo "PASS: schema structures ship in their default mode, cover only their pages, save modes and preview.\n";
