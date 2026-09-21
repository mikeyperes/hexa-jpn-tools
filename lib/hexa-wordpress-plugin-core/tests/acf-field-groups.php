<?php

declare( strict_types=1 );

$GLOBALS['acf_group_test_options'] = [ 'legacy_fields' => 1 ];
$GLOBALS['acf_group_test_registered'] = [];
$GLOBALS['acf_group_test_filters'] = [];
function sanitize_key( string $value ): string { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $value ) ?: '' ); }
function sanitize_text_field( string $value ): string { return trim( strip_tags( $value ) ); }
function get_option( string $key, mixed $default = false ): mixed { return $GLOBALS['acf_group_test_options'][ $key ] ?? $default; }
function update_option( string $key, mixed $value, bool $autoload = false ): void { $GLOBALS['acf_group_test_options'][ $key ] = $value; }
function add_action( string $hook, callable $callback, int $priority = 10 ): void {}
function add_filter( string $hook, callable $callback, int $priority = 10 ): void { $GLOBALS['acf_group_test_filters'][ $hook ][ $priority ] = $callback; }
function acf_add_local_field_group( array $group ): void { $GLOBALS['acf_group_test_registered'][] = $group; }

require_once dirname( __DIR__ ) . '/src/CoreContracts/ModuleInterface.php';
require_once dirname( __DIR__ ) . '/src/FieldStructures/AcfFieldGroupSettingsStore.php';
require_once dirname( __DIR__ ) . '/src/FieldStructures/AcfFieldGroupAjaxController.php';
require_once dirname( __DIR__ ) . '/src/FieldStructures/AcfFieldGroupRegistry.php';

use Hexa\PluginCore\FieldStructures\AcfFieldGroupRegistry;

$registry = new AcfFieldGroupRegistry( [ 'option_name' => 'host_acf_groups' ] );
$registry->add( [
    'id' => 'person-fields', 'label' => 'Person Fields', 'group_key' => 'group_person',
    'legacy_option' => 'legacy_fields', 'enabled_default' => false,
    'definition' => static fn(): array => [ 'key' => 'group_person', 'title' => 'Person', 'fields' => [] ],
] );
$resolved = $registry->resolved_definitions()[0];
if ( empty( $resolved['enabled'] ) ) { fwrite( STDERR, "Legacy state was not preserved.\n" ); exit( 1 ); }
$registry->register_groups();
if ( 'group_person' !== ( $GLOBALS['acf_group_test_registered'][0]['key'] ?? '' ) ) { fwrite( STDERR, "Field group was not registered.\n" ); exit( 1 ); }
$registry->store()->save( $registry->definition( 'person-fields' ), false );
if ( 0 !== $GLOBALS['acf_group_test_options']['legacy_fields'] || 0 !== $GLOBALS['acf_group_test_options']['host_acf_groups']['person-fields'] ) { fwrite( STDERR, "Saved state was not synchronized.\n" ); exit( 1 ); }
$registry->register();
if ( ! isset( $GLOBALS['acf_group_test_filters']['acf/load_field_group'][999] ) ) { fwrite( STDERR, "Managed database groups are not filtered.\n" ); exit( 1 ); }
$database_group = $registry->apply_managed_state( [ 'key' => 'group_person', 'active' => true ] );
if ( false !== ( $database_group['active'] ?? null ) ) { fwrite( STDERR, "A disabled database copy remained active.\n" ); exit( 1 ); }
$unmanaged_group = $registry->apply_managed_state( [ 'key' => 'group_external', 'active' => true ] );
if ( true !== ( $unmanaged_group['active'] ?? null ) ) { fwrite( STDERR, "An unmanaged database group was changed.\n" ); exit( 1 ); }
echo "ACF field-group registry tests passed.\n";

$after_save = static function (): void {};
$callback_registry = new AcfFieldGroupRegistry( [ 'after_save' => $after_save ] );
if ( $callback_registry->config( 'after_save' ) !== $after_save ) {
    fwrite( STDERR, "ACF after-save callback was not preserved.\n" );
    exit( 1 );
}
