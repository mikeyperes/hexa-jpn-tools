<?php

declare( strict_types=1 );

const LSCWP_V = 'test';

function sanitize_key( mixed $value ): string {
    return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ) ?: '';
}

$passed = 0;
$failed = 0;
function litespeed_expect( bool $condition, string $message ): void {
    global $passed, $failed;
    if ( $condition ) {
        ++$passed;
        echo "PASS {$message}\n";
        return;
    }

    ++$failed;
    echo "FAIL {$message}\n";
}

final class FakeLiteSpeedConf {
    /** @var array<string,mixed> */
    public array $stored;
    /** @var array<string,mixed> */
    public array $effective;
    /** @var array<string,string> */
    public array $overrides;
    /** @var array<string,mixed> */
    public array $network;
    /** @var list<array<string,mixed>> */
    public array $saves = [];

    public function __construct( array $stored, array $effective = [], array $overrides = [], array $network = [] ) {
        $this->stored    = $stored;
        $this->effective = array_merge( $stored, $effective );
        $this->overrides = $overrides;
        $this->network   = $network;
    }

    public function has_conf( string $id ): bool {
        return array_key_exists( $id, $this->stored );
    }

    public function has_network_conf( string $id ): bool {
        return array_key_exists( $id, $this->network );
    }

    public function conf( string $id, bool $original = false ): mixed {
        if ( $original ) {
            return $this->stored[ $id ] ?? $this->network[ $id ] ?? null;
        }
        return $this->effective[ $id ] ?? $this->network[ $id ] ?? null;
    }

    public function const_overwritten( string $id ): mixed {
        return 'constant' === ( $this->overrides[ $id ] ?? '' ) ? $this->conf( $id ) : null;
    }

    public function primary_overwritten( string $id ): mixed {
        return 'network_primary' === ( $this->overrides[ $id ] ?? '' ) ? $this->conf( $id ) : null;
    }

    public function filter_overwritten( string $id ): mixed {
        return 'filter' === ( $this->overrides[ $id ] ?? '' ) ? $this->conf( $id ) : null;
    }

    public function network_conf( string $id ): mixed {
        return $this->network[ $id ] ?? null;
    }

    /** @param array<string,mixed> $changes */
    public function update_confs( array $changes ): void {
        $this->saves[] = $changes;
        foreach ( $changes as $id => $value ) {
            $this->stored[ $id ]    = $value;
            $this->effective[ $id ] = $value;
        }
    }
}

$root = dirname( __DIR__ );
require $root . '/src/LiteSpeedCache/SettingDefinition.php';
require $root . '/src/LiteSpeedCache/Profile.php';
require $root . '/src/LiteSpeedCache/MissingValue.php';
require $root . '/src/LiteSpeedCache/ConfigurationAdapterInterface.php';
require $root . '/src/LiteSpeedCache/LiteSpeedConfAdapter.php';
require $root . '/src/LiteSpeedCache/CallbackConfigurationAdapter.php';
require $root . '/src/LiteSpeedCache/LiteSpeedCacheService.php';

use Hexa\PluginCore\LiteSpeedCache\LiteSpeedCacheService;
use Hexa\PluginCore\LiteSpeedCache\LiteSpeedConfAdapter;
use Hexa\PluginCore\LiteSpeedCache\Profile;

$clean_profile = new Profile( [
    'id'       => 'official-conf',
    'settings' => [
        'cache'      => [ 'option_name' => 'cache', 'expected' => true, 'cast' => 'bool' ],
        'public_ttl' => [ 'option_name' => 'cache-ttl_pub', 'expected' => 3600, 'cast' => 'int' ],
        'status_ttl' => [ 'option_name' => 'cache-ttl_status', 'expected' => [ '404 3600', '500 0' ], 'cast' => 'array' ],
    ],
] );
$clean_conf    = new FakeLiteSpeedConf( [
    'cache'            => '0',
    'cache-ttl_pub'    => '120',
    'cache-ttl_status' => [ '404 3600' ],
] );
$clean_service = new LiteSpeedCacheService( $clean_profile, new LiteSpeedConfAdapter( $clean_conf ) );
$clean_before  = $clean_service->audit();
$clean_apply   = $clean_service->apply();
$clean_after   = $clean_service->verify();

litespeed_expect( $clean_before['available'] && $clean_before['litespeed_active'] && 3 === $clean_before['mismatched'], 'official Conf adapter exposes availability and audits effective values' );
litespeed_expect( '0' === $clean_before['items'][0]['stored'] && '0' === $clean_before['items'][0]['effective'] && 'local' === $clean_before['items'][0]['provenance']['stored'] && $clean_before['items'][0]['writable'], 'audit results retain stored/effective values and writable provenance' );
litespeed_expect( $clean_apply['success'] && 3 === $clean_apply['updated'] && [ 'cache', 'cache-ttl_pub', 'cache-ttl_status' ] === $clean_apply['requested'], 'generic apply/result assembly reports every verified transition' );
litespeed_expect( 1 === count( $clean_conf->saves ) && [ 'cache' => true, 'cache-ttl_pub' => 3600, 'cache-ttl_status' => [ '404 3600', '500 0' ] ] === $clean_conf->saves[0], 'all writable differences use one official update_confs batch' );
litespeed_expect( $clean_after['success'] && 'verified' === $clean_after['status'] && 0 === $clean_after['mismatched'], 'verification re-inspects effective values after LiteSpeed synchronization' );

$blocked_profile = new Profile( [
    'id'       => 'provenance',
    'settings' => [
        'safe'     => [ 'option_name' => 'safe', 'expected' => 1, 'cast' => 'int' ],
        'missing'  => [ 'option_name' => 'missing', 'expected' => 1, 'cast' => 'int' ],
        'constant' => [ 'option_name' => 'constant', 'expected' => 1, 'cast' => 'int' ],
        'network'  => [ 'option_name' => 'network', 'expected' => 1, 'cast' => 'int' ],
        'filtered' => [ 'option_name' => 'filtered', 'expected' => 1, 'cast' => 'int' ],
        'external' => [ 'option_name' => 'external', 'expected' => 1, 'cast' => 'int' ],
    ],
] );
$blocked_conf = new FakeLiteSpeedConf(
    [ 'safe' => 0, 'constant' => 1, 'network' => 0, 'filtered' => 1, 'external' => 1 ],
    [ 'constant' => 0, 'network' => 2, 'filtered' => 2, 'external' => 2 ],
    [ 'constant' => 'constant', 'network' => 'network_primary', 'filtered' => 'filter' ],
    [ 'network' => 2 ]
);
$blocked_apply = ( new LiteSpeedCacheService( $blocked_profile, new LiteSpeedConfAdapter( $blocked_conf ) ) )->apply();
$blocked_items = array_column( $blocked_apply['after']['items'], null, 'id' );

litespeed_expect( ! $blocked_apply['success'] && 'option_missing' === $blocked_apply['blocked']['missing'], 'missing official option IDs are blocked instead of invented' );
litespeed_expect( 'effective_override:constant' === $blocked_apply['blocked']['constant'] && str_contains( $blocked_apply['blocked']['network'], 'network_primary' ) && 'effective_override:filter' === $blocked_apply['blocked']['filtered'], 'constant, network, and filter overrides remain explicit block reasons' );
litespeed_expect( 'effective_override:external' === $blocked_apply['blocked']['external'] && 'external' === $blocked_items['external']['provenance']['effective'], 'unattributed effective/stored drift has explicit external provenance' );
litespeed_expect( 1 === count( $blocked_conf->saves ) && [ 'safe' => 1 ] === $blocked_conf->saves[0], 'official adapter never writes behind missing or overridden effective values' );
litespeed_expect( 1 === $blocked_apply['missing'] && 4 === $blocked_apply['overridden'] && ! $blocked_items['network']['writable'] && 'local' === $blocked_items['network']['provenance']['stored'], 'result counts and per-item provenance expose review scope' );

$effective_values = [];
$callback_profile = new Profile( [ 'id' => 'callback', 'settings' => [
    'expected_false' => [ 'option_name' => 'virtual-false', 'expected' => false, 'cast' => 'bool' ],
    'expected_zero'  => [ 'option_name' => 'virtual-zero', 'expected' => 0, 'cast' => 'int' ],
    'expected_empty' => [ 'option_name' => 'virtual-empty', 'expected' => '', 'cast' => 'string' ],
    'expected_array' => [ 'option_name' => 'virtual-array', 'expected' => [], 'cast' => 'array' ],
] ] );
$callback_service = new LiteSpeedCacheService(
    $callback_profile,
    static function ( $setting ) use ( &$effective_values ): mixed {
        return array_key_exists( $setting->id(), $effective_values )
            ? $effective_values[ $setting->id() ]
            : LiteSpeedCacheService::missing_value();
    },
    static function ( $setting, mixed $value ) use ( &$effective_values ): bool {
        $effective_values[ $setting->id() ] = $value;
        return true;
    }
);
$callback_before = $callback_service->audit();
$callback_apply  = $callback_service->apply();
litespeed_expect( 4 === $callback_before['missing'] && $callback_apply['success'] && 4 === $callback_apply['updated'], 'callback compatibility keeps missing sentinels distinct from false, zero, and empty values' );
litespeed_expect( false === $effective_values['expected_false'] && 0 === $effective_values['expected_zero'] && '' === $effective_values['expected_empty'] && [] === $effective_values['expected_array'], 'Core casting and verification preserve host callback values' );

$unavailable = ( new LiteSpeedCacheService( $clean_profile, new LiteSpeedConfAdapter( new stdClass() ) ) )->apply();
litespeed_expect( ! $unavailable['success'] && ! $unavailable['available'] && 'LiteSpeed configuration API is unavailable.' === $unavailable['error'], 'invalid or unavailable Conf objects fail closed with a stable result' );

echo "\n{$passed} passed, {$failed} failed.\n";
exit( 0 === $failed ? 0 : 1 );
