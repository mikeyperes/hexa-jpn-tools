<?php

declare(strict_types=1);

// URL rules are refreshed once when a registry's post types or permalink structure change.

$options = [ 'permalink_structure' => '/%postname%/' ];
$flushes = 0;
function sanitize_key( mixed $v ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ) ?: ''; }
function sanitize_title( mixed $v ): string { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $v ) ) ?: '', '-' ); }
function sanitize_text_field( mixed $v ): string { return trim( strip_tags( (string) $v ) ); }
function get_option( string $n, mixed $d = false ): mixed { return $GLOBALS['options'][ $n ] ?? $d; }
function update_option( string $n, mixed $v, mixed $a = null ): bool { $GLOBALS['options'][ $n ] = $v; return true; }
function wp_json_encode( mixed $v ): string|false { return json_encode( $v ); }
function flush_rewrite_rules( bool $hard = true ): void { ++$GLOBALS['flushes']; }

$root = dirname( __DIR__ );
require __DIR__ . '/support/fields.php';
require $root . '/src/CoreContracts/ModuleInterface.php';
require $root . '/src/ContentTypes/ContentTypeDefinition.php';
require $root . '/src/ContentTypes/ContentTypeSettingsStore.php';
require $root . '/src/ContentTypes/ContentTypeRegistry.php';

use Hexa\PluginCore\ContentTypes\ContentTypeRegistry;

$failures = 0;
$check = static function ( bool $ok, string $label ) use ( &$failures ): void { echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . PHP_EOL; $failures += $ok ? 0 : 1; };

$make = static function ( string $slug ): ContentTypeRegistry {
    return ( new ContentTypeRegistry( [ 'option_name' => 'test_types' ] ) )->add( [ 'id' => 'press_release', 'enabled_default' => true, 'post_type' => [ 'key' => 'press-release', 'singular' => 'Press Release', 'plural' => 'Press Releases', 'rewrite_slug' => $slug, 'args' => [ 'public' => true ] ] ] );
};

$check( true === $make( 'press-release' )->sync_rewrite_rules() && 1 === $flushes, 'first run (fresh activation) refreshes URL rules' );
$check( false === $make( 'press-release' )->sync_rewrite_rules() && 1 === $flushes, 'unchanged types do not refresh again' );
$check( true === $make( 'news-release' )->sync_rewrite_rules() && 2 === $flushes, 'a changed URL base refreshes once' );
$options['permalink_structure'] = '/%year%/%postname%/';
$check( true === $make( 'news-release' )->sync_rewrite_rules() && 3 === $flushes, 'a changed permalink structure refreshes once' );
$check( false === $make( 'news-release' )->sync_rewrite_rules() && 3 === $flushes, 'then stays quiet' );

exit( $failures > 0 ? 1 : 0 );
