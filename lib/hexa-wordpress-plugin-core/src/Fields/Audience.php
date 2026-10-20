<?php

namespace Hexa\PluginCore\Fields;

/**
 * Client-friendly field view.
 *
 * Any field (sub-fields and flexible-content layouts included) may carry
 * `developer_instructions`: technical notes such as shortcodes, meta keys or
 * compatibility details. A group's `client_view` setting decides who gets the
 * client view, which shows only the friendly `instructions`:
 *
 *     'client_view' => [ 'enabled' => true, 'roles' => 'below_admin' ]          // default
 *     'client_view' => [ 'enabled' => true, 'roles' => [ 'author', 'editor' ] ]  // only these roles
 *     'client_view' => [ 'enabled' => false ]                                    // everyone sees developer notes
 *
 * Everyone else sees the developer view: friendly instructions followed by the
 * developer notes. Filter: hexa_core_fields_client_view( $client, $setting, $user ).
 */
final class Audience {
	public const BELOW_ADMIN = 'below_admin';

	/** @return array{enabled:bool,roles:string|list<string>} */
	public static function normalize( mixed $setting ): array {
		if ( is_bool( $setting ) ) {
			$setting = [ 'enabled' => $setting ];
		}
		$setting = is_array( $setting ) ? $setting : [];
		$roles = $setting['roles'] ?? self::BELOW_ADMIN;
		if ( self::BELOW_ADMIN !== $roles ) {
			$roles = array_values( array_unique( array_filter( array_map( static fn( mixed $role ): string => is_scalar( $role ) ? sanitize_key( (string) $role ) : '', (array) $roles ) ) ) );
			$roles = [] === $roles ? self::BELOW_ADMIN : $roles;
		}
		return [ 'enabled' => ! array_key_exists( 'enabled', $setting ) || (bool) $setting['enabled'], 'roles' => $roles ];
	}

	public static function client_view( mixed $setting, mixed $user = null ): bool {
		$setting = self::normalize( $setting );
		if ( null === $user && function_exists( 'wp_get_current_user' ) ) {
			$user = wp_get_current_user();
		}
		$client = false;
		if ( $setting['enabled'] ) {
			if ( ! $user instanceof \WP_User || ! $user->exists() ) {
				$client = true;
			} elseif ( self::BELOW_ADMIN === $setting['roles'] ) {
				$client = ! user_can( $user, 'manage_options' );
			} else {
				$client = [] !== array_intersect( (array) $user->roles, $setting['roles'] );
			}
		}
		return function_exists( 'apply_filters' ) ? (bool) apply_filters( 'hexa_core_fields_client_view', $client, $setting, $user ) : $client;
	}

	/** @param array<string,mixed> $group @return array<string,mixed> */
	public static function apply( array $group, mixed $user = null ): array {
		$client = self::client_view( $group['client_view'] ?? [], $user );
		$group['fields'] = self::fields( (array) ( $group['fields'] ?? [] ), $client );
		return $group;
	}

	/** @param array<int,mixed> $fields @return array<int,mixed> */
	private static function fields( array $fields, bool $client ): array {
		foreach ( $fields as $index => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$fields[ $index ] = self::field( $field, $client );
		}
		return $fields;
	}

	/** @param array<string,mixed> $field @return array<string,mixed> */
	private static function field( array $field, bool $client ): array {
		$friendly = trim( (string) ( $field['instructions'] ?? '' ) );
		$developer = trim( (string) ( $field['developer_instructions'] ?? '' ) );
		unset( $field['developer_instructions'] );
		$field['instructions'] = $client || '' === $developer
			? $friendly
			: ( '' === $friendly ? $developer : $friendly . '<br>' . $developer );
		if ( isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
			$field['sub_fields'] = self::fields( $field['sub_fields'], $client );
		}
		if ( isset( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
			foreach ( $field['layouts'] as $key => $layout ) {
				if ( is_array( $layout ) && isset( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ) {
					$field['layouts'][ $key ]['sub_fields'] = self::fields( $layout['sub_fields'], $client );
				}
			}
		}
		return $field;
	}
}
