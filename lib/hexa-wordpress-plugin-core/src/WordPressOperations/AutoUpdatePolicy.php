<?php

namespace Hexa\PluginCore\WordPressOperations;

/** Persists future WordPress auto-update policy using the native site options. */
final class AutoUpdatePolicy {
    /** @param array<string,callable> $callbacks */
    public function __construct( private array $callbacks = [] ) {}

    /** @return array<string,mixed> */
    public function status(): array {
        if ( isset( $this->callbacks['status'] ) ) {
            return (array) call_user_func( $this->callbacks['status'] );
        }
        $get = static fn( string $key, mixed $default = null ): mixed => function_exists( 'get_site_option' ) ? get_site_option( $key, $default ) : $default;
        $major = (string) $get( 'auto_update_core_major', 'disabled' );
        $minor = (string) $get( 'auto_update_core_minor', 'enabled' );
        $core = 'enabled' === $major ? 'all' : ( 'enabled' === $minor ? 'minor' : 'disabled' );
        $plugins = $this->normalize_items( (array) $get( 'auto_update_plugins', [] ) );
        $themes = $this->normalize_items( (array) $get( 'auto_update_themes', [] ) );
        return [
            'success'   => true,
            'action'    => 'status',
            'available' => function_exists( 'get_site_option' ),
            'before'    => [],
            'after'     => [],
            'counts'    => [ 'plugins' => count( $plugins ), 'themes' => count( $themes ) ],
            'items'     => [ 'core' => $core, 'plugins' => $plugins, 'themes' => $themes ],
            'messages'  => [],
        ];
    }

    /** @return array<string,mixed> */
    public function apply( array $policy ): array {
        if ( isset( $this->callbacks['apply'] ) ) {
            return (array) call_user_func( $this->callbacks['apply'], $policy );
        }
        $before = $this->status();
        if ( ! function_exists( 'update_site_option' ) ) {
            return [ 'success' => false, 'action' => 'apply', 'before' => $before, 'after' => $before, 'counts' => [ 'updated' => 0, 'failed' => 1 ], 'items' => [], 'messages' => [ 'WordPress site-option API is unavailable.' ] ];
        }
        $core = strtolower( (string) ( $policy['core'] ?? $before['items']['core'] ?? 'minor' ) );
        if ( ! in_array( $core, [ 'disabled', 'minor', 'all' ], true ) ) {
            $core = 'minor';
        }
        update_site_option( 'auto_update_core_major', 'all' === $core ? 'enabled' : 'disabled' );
        update_site_option( 'auto_update_core_minor', 'disabled' === $core ? 'disabled' : 'enabled' );
        update_site_option( 'auto_update_core_dev', 'all' === $core ? 'enabled' : 'disabled' );
        $plugins = $this->resolve_items( $policy['plugins'] ?? $before['items']['plugins'] ?? [], 'plugins' );
        $themes  = $this->resolve_items( $policy['themes'] ?? $before['items']['themes'] ?? [], 'themes' );
        update_site_option( 'auto_update_plugins', $plugins );
        update_site_option( 'auto_update_themes', $themes );
        $after = $this->status();
        $success = $core === ( $after['items']['core'] ?? '' ) && $plugins === ( $after['items']['plugins'] ?? [] ) && $themes === ( $after['items']['themes'] ?? [] );
        return [ 'success' => $success, 'action' => 'apply', 'before' => $before, 'after' => $after, 'counts' => [ 'requested' => 5, 'updated' => $success ? 5 : 0, 'failed' => $success ? 0 : 1 ], 'items' => $after['items'], 'messages' => [ $success ? 'Future auto-update policy applied and verified.' : 'Future auto-update policy did not verify.' ] ];
    }

    /** @return list<string> */
    private function resolve_items( mixed $value, string $type ): array {
        if ( false === $value ) {
            return [];
        }
        if ( true === $value ) {
            if ( 'plugins' === $type && ! function_exists( 'get_plugins' ) && defined( 'ABSPATH' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            if ( 'plugins' === $type && function_exists( 'get_plugins' ) ) {
                return $this->normalize_items( array_keys( get_plugins() ) );
            }
            if ( 'themes' === $type && function_exists( 'wp_get_themes' ) ) {
                return $this->normalize_items( array_keys( wp_get_themes() ) );
            }
            return [];
        }
        $items = $this->normalize_items( is_array( $value ) ? $value : [] );
        if ( 'plugins' === $type && ! function_exists( 'get_plugins' ) && defined( 'ABSPATH' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if ( 'plugins' === $type && function_exists( 'get_plugins' ) ) {
            $items = array_values( array_intersect( $items, array_keys( get_plugins() ) ) );
        } elseif ( 'themes' === $type && function_exists( 'wp_get_themes' ) ) {
            $items = array_values( array_intersect( $items, array_keys( wp_get_themes() ) ) );
        }
        return $this->normalize_items( $items );
    }

    /** @return list<string> */
    private function normalize_items( array $items ): array {
        $items = array_values(
            array_unique(
                array_filter(
                    array_map( 'strval', $items ),
                    static fn( string $item ): bool => '' !== $item
                )
            )
        );
        sort( $items, SORT_STRING );
        return $items;
    }
}
