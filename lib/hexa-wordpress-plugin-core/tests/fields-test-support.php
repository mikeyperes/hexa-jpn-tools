<?php
/** The shared host-plugin test support runs Fields in ACF mode against the test's own stubs. */

function get_field( $selector, $post_id = false, $format = true ) {
	return 'stub:' . $selector . '@' . $post_id;
}

require __DIR__ . '/support/fields.php';

use Hexa\PluginCore\Fields\Acf;
use Hexa\PluginCore\Fields\Field;
use Hexa\PluginCore\Fields\FieldGroups;

$fail = static function ( string $message ): void {
	fwrite( STDERR, $message . "\n" );
	exit( 1 );
};

Acf::active() || $fail( 'Test support must run Fields in ACF mode.' );
'stub:website@option' === Field::get( 'website', 'option' ) || $fail( 'Field::get must delegate to the test get_field stub.' );
Field::available() || $fail( 'Field::available must be true.' );
FieldGroups::add( [ 'key' => 'group_support', 'title' => 'Support', 'fields' => [] ] );
isset( $GLOBALS['hexa_test_local_field_groups']['group_support'] ) || $fail( 'FieldGroups::add must register through acf_add_local_field_group.' );

echo "PASS: host-plugin test support runs Fields against the test's ACF stubs.\n";
