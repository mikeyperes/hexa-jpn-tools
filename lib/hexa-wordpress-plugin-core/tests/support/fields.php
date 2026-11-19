<?php
/**
 * Loads Core Fields for host tests using WordPress storage and hook stubs.
 */

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'Hexa\\PluginCore\\Fields\\';
		if ( str_starts_with( $class, $prefix ) ) {
			$file = dirname( __DIR__, 2 ) . '/src/Fields/' . substr( $class, strlen( $prefix ) ) . '.php';
			if ( is_file( $file ) ) {
				require_once $file;
			}
		}
	}
);

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['hexa_test_hooks'][ $hook ][] = [ $callback, $priority, $args ];
		return true;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		return add_action( $hook, $callback, $priority, $args );
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}
}
if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook, ...$args ) {}
}
if ( ! function_exists( 'did_action' ) ) {
	function did_action( $hook ) {
		return 1;
	}
}
if ( ! function_exists( 'doing_action' ) ) {
	function doing_action( $hook = null ) {
		return false;
	}
}
if ( ! function_exists( 'get_metadata' ) ) {
	function get_metadata( $type, $id, $key, $single = false ) {
		$reader = 'get_' . $type . '_meta';
		return function_exists( $reader ) ? $reader( $id, $key, $single ) : null;
	}
}
if ( ! function_exists( 'metadata_exists' ) ) {
	function metadata_exists( $type, $id, $key ) {
		$value = get_metadata( $type, $id, $key, true );
		return null !== $value && '' !== $value;
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) { return $default; }
}
if ( ! function_exists( 'get_the_ID' ) ) {
	function get_the_ID() { return false; }
}

require_once dirname( __DIR__, 2 ) . '/bootstrap.php';
