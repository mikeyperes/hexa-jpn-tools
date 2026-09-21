<?php

declare( strict_types=1 );

$test_root = sys_get_temp_dir() . '/hexa-core-object-cache-' . bin2hex( random_bytes( 6 ) );
mkdir( $test_root . '/plugins/litespeed-cache', 0777, true );
mkdir( $test_root . '/content', 0777, true );
file_put_contents( $test_root . '/plugins/litespeed-cache/litespeed-cache.php', "<?php\n" );

define( 'WP_PLUGIN_DIR', $test_root . '/plugins' );
define( 'WP_CONTENT_DIR', $test_root . '/content' );

function trailingslashit( string $value ): string {
    return rtrim( $value, '/\\' ) . '/';
}

function is_plugin_active( string $plugin ): bool {
    return 'litespeed-cache/litespeed-cache.php' === $plugin;
}

function wp_cache_flush(): bool {
    return true;
}

$passed = 0;
$failed = 0;
function object_cache_expect( bool $condition, string $message ): void {
    global $passed, $failed;
    if ( $condition ) {
        ++$passed;
        echo "PASS {$message}\n";
        return;
    }

    ++$failed;
    echo "FAIL {$message}\n";
}

require dirname( __DIR__ ) . '/src/ObjectCache/LiteSpeedRedisService.php';

use Hexa\PluginCore\ObjectCache\LiteSpeedRedisService;

$configuration = [
    'object'            => 0,
    'object-kind'       => 0,
    'object-host'       => 'localhost',
    'object-port'       => 11211,
    'object-db_id'      => 2,
    'object-persistent' => 0,
    'object-admin'      => 0,
];
$saves = [];
$external_cache = false;

$service = new LiteSpeedRedisService( [
    'conf_value' => static function ( string $id, mixed $default ) use ( &$configuration ): mixed {
        return $configuration[ $id ] ?? $default;
    },
    'save_configuration' => static function ( array $changes ) use ( &$configuration, &$saves ): array {
        $saves[] = $changes;
        $configuration = array_merge( $configuration, $changes );
        file_put_contents( WP_CONTENT_DIR . '/object-cache.php', "<?php define( 'LSCWP_OBJECT_CACHE', true );\n" );
        return [ 'success' => true, 'message' => 'saved' ];
    },
    'redis_connection_status' => static fn(): array => [ 'extension' => true, 'connected' => true ],
    'wp_object_cache_round_trip' => static fn(): array => [ 'success' => true, 'message' => 'round trip passed' ],
    'wp_using_ext_object_cache' => static fn(): bool => $external_cache,
] );

$result = $service->enable();
$expected = [
    'object'            => 1,
    'object-kind'       => 1,
    'object-host'       => 'localhost',
    'object-port'       => 6379,
    'object-db_id'      => 2,
    'object-persistent' => 1,
    'object-admin'      => 1,
];

object_cache_expect( $result['success'] && $result['configured'] && ! $result['active'] && $result['requires_new_request'], 'enable reports configured state until a new request loads the managed drop-in' );
object_cache_expect( [ $expected ] === $saves, 'enable sends one exact supported Redis configuration batch through LiteSpeed' );
object_cache_expect( $result['after']['dropin_litespeed'] && ! $result['after']['wp_using_ext'], 'configured state requires LiteSpeed ownership of the object-cache drop-in' );

$external_cache = true;
$active_service = new LiteSpeedRedisService( [
    'conf_value' => static function ( string $id, mixed $default ) use ( &$configuration ): mixed {
        return $configuration[ $id ] ?? $default;
    },
    'redis_connection_status' => static fn(): array => [ 'extension' => true, 'connected' => true ],
    'wp_object_cache_round_trip' => static fn(): array => [ 'success' => true, 'message' => 'round trip passed' ],
    'wp_using_ext_object_cache' => static fn(): bool => $external_cache,
] );
$active_status = $active_service->status();
object_cache_expect( $active_status['enabled'] && $active_status['active'], 'a later request verifies the managed drop-in, Redis connection, and WordPress round trip' );

file_put_contents( WP_CONTENT_DIR . '/object-cache.php', "<?php // foreign provider\n" );
$foreign_status = $active_service->status();
object_cache_expect( $foreign_status['dropin_present'] && ! $foreign_status['dropin_litespeed'] && ! $foreign_status['enabled'] && ! $foreign_status['active'], 'a foreign object-cache drop-in fails closed' );

@unlink( WP_CONTENT_DIR . '/object-cache.php' );
@unlink( WP_PLUGIN_DIR . '/litespeed-cache/litespeed-cache.php' );
@rmdir( WP_PLUGIN_DIR . '/litespeed-cache' );
@rmdir( WP_PLUGIN_DIR );
@rmdir( WP_CONTENT_DIR );
@rmdir( $test_root );

echo "\n{$passed} passed, {$failed} failed.\n";
exit( 0 === $failed ? 0 : 1 );
