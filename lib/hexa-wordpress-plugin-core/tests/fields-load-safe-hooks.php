<?php
/** hexa_fields_on() registers field hooks from code that runs before Core's classes can load. */

$GLOBALS['hooks'] = [];
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $callback; return true; }
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { return add_action( $hook, $callback, $priority, $args ); }
function did_action( $hook ) { return 0; }
function doing_action( $hook = null ) { return false; }

require dirname( __DIR__ ) . '/bootstrap.php';

$fail = static function ( string $message ): void {
	fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
	exit( 1 );
};

$callback = static fn( $field ) => $field;
hexa_fields_on( 'prepare_field', $callback );
isset( $GLOBALS['hooks']['acf/prepare_field'] ) && $fail( 'Nothing registers before Core is loaded.' );
1 === count( $GLOBALS['hooks']['plugins_loaded'] ?? [] ) || $fail( 'The registration waits for plugins_loaded.' );

spl_autoload_register( static function ( string $class ): void {
	$prefix = 'Hexa\\PluginCore\\';
	if ( str_starts_with( $class, $prefix ) ) {
		require_once dirname( __DIR__ ) . '/src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
	}
} );
( $GLOBALS['hooks']['plugins_loaded'][0] )();
in_array( $callback, $GLOBALS['hooks']['acf/prepare_field'] ?? [], true ) && in_array( $callback, $GLOBALS['hooks']['hexa_fields/prepare_field'] ?? [], true ) || $fail( 'On plugins_loaded the callback listens on acf/ and hexa_fields/.' );

hexa_fields_on( 'save_post', $callback );
in_array( $callback, $GLOBALS['hooks']['hexa_fields/save_post'] ?? [], true ) || $fail( 'Once Core is loaded, hexa_fields_on() registers at once.' );

echo "PASS: hexa_fields_on() defers field hooks until Core is loaded.\n";
