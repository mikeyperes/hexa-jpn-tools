<?php

declare(strict_types=1);

// Unit checks for PluginBridge's repository allowlist and release-asset choice.

final class WP_Error {
    public function __construct( public string $code = '', public string $message = '', public array $data = [] ) {}
    public function get_error_code(): string { return $this->code; }
}
function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
function apply_filters( string $hook, mixed $value ): mixed { return $GLOBALS['bridge_owner_filter'][ $hook ] ?? $value; }

require dirname( __DIR__ ) . '/src/PluginProvisioning/PluginProvisioner.php';
require dirname( __DIR__ ) . '/src/PluginProvisioning/PluginBridge.php';

use Hexa\PluginCore\PluginProvisioning\PluginBridge;

$failures = 0;
$check = static function ( bool $ok, string $label ) use ( &$failures ): void {
    echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . PHP_EOL;
    $failures += $ok ? 0 : 1;
};

$check( 'mikeyperes/hexa-pr-wire-distributor' === PluginBridge::allowed_repo( 'https://github.com/mikeyperes/hexa-pr-wire-distributor.git' ), 'URL normalizes to an allowed owner/name' );
$check( 'MikeyPeres/hws-base-tools' === PluginBridge::allowed_repo( 'MikeyPeres/hws-base-tools' ), 'owner match is case-insensitive' );
$other = PluginBridge::allowed_repo( 'someone/evil-plugin' );
$check( is_wp_error( $other ) && 'hexa_plugin_bridge_owner_not_allowed' === $other->get_error_code(), 'other owners are refused' );
$bad = PluginBridge::allowed_repo( 'not a repo' );
$check( is_wp_error( $bad ) && 'hexa_plugin_bridge_invalid_repo' === $bad->get_error_code(), 'malformed repo is refused' );
$GLOBALS['bridge_owner_filter']['hexa_plugin_core/plugin_bridge_owners'] = [ 'mikeyperes', 'someone' ];
$check( 'someone/evil-plugin' === PluginBridge::allowed_repo( 'someone/evil-plugin' ), 'owner allowlist is filterable' );
unset( $GLOBALS['bridge_owner_filter'] );

$repo = 'mikeyperes/hexa-pr-wire-distributor';
$asset = PluginBridge::release_asset( $repo, [
    'tag_name' => 'v3.5.1',
    'assets'   => [
        [ 'name' => 'checksums.txt', 'browser_download_url' => 'https://github.com/' . $repo . '/releases/download/v3.5.1/checksums.txt' ],
        [ 'name' => 'hexa-pr-wire-distributor-3.5.1.zip', 'browser_download_url' => 'https://github.com/' . $repo . '/releases/download/v3.5.1/hexa-pr-wire-distributor-3.5.1.zip' ],
    ],
] );
$check( is_array( $asset ) && 'hexa-pr-wire-distributor' === $asset['folder'] && 'v3.5.1' === $asset['tag'] && str_ends_with( $asset['zip_url'], '-3.5.1.zip' ), 'release zip asset and folder are chosen' );
$vtag = PluginBridge::release_asset( 'mikeyperes/smp-verified-profiles', [ 'tag_name' => 'v8.1.0', 'assets' => [ [ 'name' => 'smp-verified-profiles-v8.1.0.zip', 'browser_download_url' => 'https://github.com/mikeyperes/smp-verified-profiles/releases/download/v8.1.0/smp-verified-profiles-v8.1.0.zip' ] ] ] );
$check( is_array( $vtag ) && 'smp-verified-profiles' === $vtag['folder'], 'v-prefixed version is stripped from the folder' );
$both = [ 'tag_name' => 'v3.5.4', 'assets' => [
    [ 'name' => 'hexa-pr-wire-distributor-3.5.4-php74.zip', 'browser_download_url' => 'https://github.com/' . $repo . '/releases/download/v3.5.4/hexa-pr-wire-distributor-3.5.4-php74.zip' ],
    [ 'name' => 'hexa-pr-wire-distributor-3.5.4.zip', 'browser_download_url' => 'https://github.com/' . $repo . '/releases/download/v3.5.4/hexa-pr-wire-distributor-3.5.4.zip' ],
] ];
$modern = PluginBridge::release_asset( $repo, $both, '8.4.0' );
$check( is_array( $modern ) && str_ends_with( $modern['zip_url'], '-3.5.4.zip' ) && 'hexa-pr-wire-distributor' === $modern['folder'], 'current PHP gets the normal zip even when the PHP 7.4 build is listed first' );
$legacy = PluginBridge::release_asset( $repo, $both, '7.4.33' );
$check( is_array( $legacy ) && str_ends_with( $legacy['zip_url'], '-php74.zip' ) && 'hexa-pr-wire-distributor' === $legacy['folder'], 'PHP 7.4 gets the PHP 7.4 build into the same folder' );
$foreign = PluginBridge::release_asset( $repo, [ 'tag_name' => 'v1', 'assets' => [ [ 'name' => 'x.zip', 'browser_download_url' => 'https://evil.example/x.zip' ] ] ] );
$check( is_wp_error( $foreign ), 'assets hosted outside the repository release are refused' );
$none = PluginBridge::release_asset( $repo, [ 'tag_name' => 'v1', 'assets' => [] ] );
$check( is_wp_error( $none ) && 'hexa_plugin_bridge_no_zip_asset' === $none->get_error_code(), 'a release without a zip is refused' );

exit( $failures > 0 ? 1 : 0 );
