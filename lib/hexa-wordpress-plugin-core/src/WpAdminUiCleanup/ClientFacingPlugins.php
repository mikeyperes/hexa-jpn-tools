<?php

namespace Hexa\PluginCore\WpAdminUiCleanup;

/**
 * Which plugins may speak to clients (users below administrator) in wp-admin.
 *
 * WordPress itself and the plugins in the client-facing list (our own Hexa and HWS
 * plugins, and the plugins that draw client fields) keep their hooks. Everything
 * else (SEO, builders, security, caching, donation banners) is staff business:
 * strip() removes its callbacks from a hook before it runs, at the source, instead
 * of hiding its output.
 *
 * Filter: hexa_core_client_facing_plugins( string[] $folders_or_prefixes ).
 * A trailing "*" is a prefix ("hexa-*"); anything else is an exact plugin folder.
 */
final class ClientFacingPlugins {
	public const DEFAULT = [ 'hexa-*', 'hws-*', 'advanced-custom-fields-pro', 'advanced-custom-fields', 'wp-user-avatars' ];

	/** @return list<string> */
	public static function allowed(): array {
		$list = function_exists( 'apply_filters' ) ? apply_filters( 'hexa_core_client_facing_plugins', self::DEFAULT ) : self::DEFAULT;
		return array_values( array_filter( array_map( 'strval', (array) $list ) ) );
	}

	/** Removes every callback on $hook that comes from a plugin outside the list. Returns how many were removed. */
	public static function strip( string $hook ): int {
		global $wp_filter;
		if ( ! isset( $wp_filter[ $hook ] ) || ! $wp_filter[ $hook ] instanceof \WP_Hook ) {
			return 0;
		}
		$removed = 0;
		foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$folder = self::plugin_folder( $callback['function'] ?? null );
				if ( null !== $folder && ! self::is_allowed( $folder ) ) {
					remove_action( $hook, $callback['function'], $priority );
					$removed++;
				}
			}
		}
		return $removed;
	}

	public static function is_allowed( string $folder ): bool {
		foreach ( self::allowed() as $entry ) {
			if ( str_ends_with( $entry, '*' ) ? str_starts_with( $folder, substr( $entry, 0, -1 ) ) : $folder === $entry ) {
				return true;
			}
		}
		return false;
	}

	/** The plugin folder a callback's code lives in; null for WordPress core, themes and unknown callbacks. */
	public static function plugin_folder( mixed $callback ): ?string {
		$file = self::source_file( $callback );
		if ( '' === $file ) {
			return null;
		}
		foreach ( array_filter( [ defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : '', defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : '' ] ) as $root ) {
			$root = rtrim( wp_normalize_path( (string) realpath( $root ) ?: $root ), '/' ) . '/';
			if ( str_starts_with( $file, $root ) ) {
				$relative = substr( $file, strlen( $root ) );
				return str_contains( $relative, '/' ) ? strstr( $relative, '/', true ) : basename( $relative, '.php' );
			}
		}
		return null;
	}

	private static function source_file( mixed $callback ): string {
		try {
			if ( is_string( $callback ) && str_contains( $callback, '::' ) ) {
				$callback = explode( '::', $callback, 2 );
			}
			if ( is_array( $callback ) && 2 === count( $callback ) ) {
				$reflection = new \ReflectionMethod( $callback[0], (string) $callback[1] );
			} elseif ( $callback instanceof \Closure || ( is_string( $callback ) && function_exists( $callback ) ) ) {
				$reflection = new \ReflectionFunction( $callback );
			} elseif ( is_object( $callback ) && method_exists( $callback, '__invoke' ) ) {
				$reflection = new \ReflectionMethod( $callback, '__invoke' );
			} else {
				return '';
			}
			$file = $reflection->getFileName();
			return false === $file ? '' : wp_normalize_path( (string) ( realpath( $file ) ?: $file ) );
		} catch ( \ReflectionException ) {
			return '';
		}
	}
}
