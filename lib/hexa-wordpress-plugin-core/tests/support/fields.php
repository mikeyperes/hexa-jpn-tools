<?php
/**
 * Unit-test support for host plugins that call Hexa\PluginCore\Fields.
 *
 * Require it after the test's own WordPress and ACF stubs:
 *
 *     require $plugin_root . '/lib/hexa-wordpress-plugin-core/tests/support/fields.php';
 *
 * It autoloads the Fields classes from this Core copy and runs them in ACF
 * mode, so Field::get(), FieldGroups::add() and friends delegate to the test's
 * get_field()/update_field() stubs exactly as they delegate to ACF on a live
 * site. Only functions the test did not define are stubbed. Core's
 * bootstrap.php is loaded too, for hexa_fields_on().
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
if ( ! function_exists( 'get_field' ) ) {
	function get_field( $selector, $post_id = false, $format = true ) {
		if ( is_string( $post_id ) && str_starts_with( $post_id, 'user_' ) ) {
			return function_exists( 'get_user_meta' ) ? get_user_meta( (int) substr( $post_id, 5 ), $selector, true ) : null;
		}
		if ( is_string( $post_id ) && in_array( $post_id, [ 'option', 'options' ], true ) ) {
			return function_exists( 'get_option' ) ? get_option( 'options_' . $selector, null ) : null;
		}
		return function_exists( 'get_post_meta' ) ? get_post_meta( (int) $post_id, $selector, true ) : null;
	}
}
if ( ! function_exists( 'acf_add_local_field_group' ) ) {
	function acf_add_local_field_group( $group ) {
		$GLOBALS['hexa_test_local_field_groups'][ $group['key'] ?? '' ] = $group;
		return true;
	}
}

require_once dirname( __DIR__, 2 ) . '/bootstrap.php';
