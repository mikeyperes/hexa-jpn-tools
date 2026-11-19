<?php
/** The shared host-plugin test support uses Core and WordPress storage stubs. */

function get_field( $selector, $post_id = false, $format = true ) {
	throw new RuntimeException( 'Test support must not call ACF.' );
}

function get_option( $name, $default = false ) {
	return 'options_website' === $name ? 'stored.example' : $default;
}

require __DIR__ . '/support/fields.php';

use Hexa\PluginCore\Fields\Acf;
use Hexa\PluginCore\Fields\Field;
use Hexa\PluginCore\Fields\FieldGroups;

$fail = static function ( string $message ): void {
	fwrite( STDERR, $message . "\n" );
	exit( 1 );
};

! Acf::active() || $fail( 'Test support must run the Core engine.' );
'stored.example' === Field::get( 'website', 'option' ) || $fail( 'Field::get reads the stored option.' );
Field::available() || $fail( 'Field::available must be true.' );
FieldGroups::add( [ 'key' => 'group_support', 'title' => 'Support', 'fields' => [] ] );
null !== FieldGroups::get_group( 'group_support' ) || $fail( 'FieldGroups::add registers the group in Core.' );

echo "PASS: host-plugin test support runs Fields against WordPress storage stubs.\n";
