<?php

declare(strict_types=1);

$content_type_options = [];
$registered_post_types = [];
$registered_field_groups = [];
$post_type_registration_calls = 0;

function sanitize_key( mixed $value ): string {
    return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ) ?: '';
}
function sanitize_title( mixed $value ): string {
    return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ) ?: '', '-' );
}
function sanitize_text_field( mixed $value ): string {
    return trim( strip_tags( (string) $value ) );
}
function get_option( string $name, mixed $default = false ): mixed {
    global $content_type_options;
    return $content_type_options[ $name ] ?? $default;
}
function update_option( string $name, mixed $value, mixed $autoload = null ): bool {
    global $content_type_options;
    $content_type_options[ $name ] = $value;
    return true;
}
function register_post_type( string $key, array $args ): void {
    global $registered_post_types, $post_type_registration_calls;
    $registered_post_types[ $key ] = $args;
    ++$post_type_registration_calls;
}
function post_type_exists( string $key ): bool {
    global $registered_post_types;
    return isset( $registered_post_types[ $key ] );
}
function register_taxonomy( string $key, array $post_types, array $args ): void {}
function taxonomy_exists( string $key ): bool { return false; }
function acf_add_local_field_group( array $definition ): void {
    global $registered_field_groups;
    $registered_field_groups[] = $definition;
}

$root = dirname( __DIR__ );
require $root . '/src/CoreContracts/ModuleInterface.php';
require $root . '/src/ContentTypes/ContentTypeDefinition.php';
require $root . '/src/ContentTypes/ContentTypeSettingsStore.php';
require $root . '/src/ContentTypes/ContentTypeRegistry.php';
require $root . '/src/ContentTypes/ContentTypeRegistrar.php';

use Hexa\PluginCore\ContentTypes\ContentTypeRegistrar;
use Hexa\PluginCore\ContentTypes\ContentTypeRegistry;

function content_type_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

$registry = new ContentTypeRegistry( [ 'option_name' => 'test_content_types' ] );
$registry->add_many(
    [
        [
            'id' => 'book',
            'owner' => 'Test host',
            'legacy_enabled_option' => 'legacy_book_cpt',
            'post_type' => [
                'key' => 'book', 'singular' => 'Book', 'plural' => 'Books', 'rewrite_slug' => 'library',
                'args' => [ 'public' => true, 'has_archive' => true ],
            ],
            'field_groups' => [ [
                'id' => 'book-fields', 'legacy_option' => 'legacy_book_acf',
                'definition' => [ 'fields' => [] ],
            ] ],
        ],
        [
            'id' => 'organization-extension',
            'owner' => 'Extension plugin',
            'registration_mode' => 'external',
            'post_type' => [ 'key' => 'organization', 'singular' => 'Organization', 'plural' => 'Organizations' ],
            'field_groups' => [
                [
                    'id' => 'organization-fields', 'group_key' => 'group_test_organization',
                    'definition' => [
                        'fields' => [ [ 'key' => 'field_test_name', 'name' => 'legal_name', 'type' => 'text' ] ],
                        'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => '@post_type' ] ] ],
                    ],
                ],
            ],
        ],
    ]
);

$registrar = new ContentTypeRegistrar( $registry );
$registrar->register_post_types();
$registrar->register_acf_groups();

content_type_assert( isset( $registered_post_types['book'] ), 'Owned content types must register through Core.' );
content_type_assert( ! isset( $registered_post_types['organization'] ), 'External content types must never compete with their owning plugin.' );
content_type_assert( 'library' === $registered_post_types['book']['rewrite']['slug'], 'Resolved public rewrite slugs must reach registration.' );
content_type_assert( 2 === count( $registered_field_groups ), 'Owned and external plugins must both register ACF groups through Core.' );
content_type_assert( 'organization' === $registered_field_groups[1]['location'][0][0]['value'], 'ACF post-type placeholders must resolve to the immutable key.' );

$registrar->register_post_types();
content_type_assert( 1 === $post_type_registration_calls, 'Core registration must be idempotent when another hook already registered the post type.' );

$book = $registry->definition( 'book' );
$saved = $registry->store()->save(
    $book,
    [ 'enabled' => true, 'singular' => 'Volume', 'plural' => 'Volumes', 'rewrite_slug' => 'reading', 'enabled_field_groups' => [] ]
);
content_type_assert( 'book' === $saved['post_type']['key'], 'Saving labels must never mutate the post-type key.' );
content_type_assert( 'reading' === $saved['post_type']['rewrite_slug'] && 'Volumes' === $saved['post_type']['plural'], 'Editable labels and slug must persist independently.' );
content_type_assert( 1 === $content_type_options['legacy_book_cpt'], 'Core content-type saves must synchronize the legacy enabled option.' );
content_type_assert( 0 === $content_type_options['legacy_book_acf'], 'Core ACF toggles must synchronize the legacy field-group option.' );

echo "PASS: Content types share owned/external registration, immutable keys, editable labels, and Core-managed ACF groups.\n";
