<?php

namespace Hexa\PluginCore\WordPressOperations;

/** Native immediate update status/actions with structured before/after reports. */
final class UpdateOperations {
    /** @param array<string,callable> $callbacks */
    public function __construct( private array $callbacks = [] ) {}

    /** @return array<string,mixed> */
    public function status( bool $refresh = false ): array {
        if ( isset( $this->callbacks['status'] ) ) {
            return (array) call_user_func( $this->callbacks['status'] );
        }
        if ( $refresh ) {
            $this->refresh_update_data( 'core' );
            $this->refresh_update_data( 'plugins' );
            $this->refresh_update_data( 'themes' );
        }
        $core = function_exists( 'get_site_transient' ) ? get_site_transient( 'update_core' ) : null;
        $plugins = function_exists( 'get_site_transient' ) ? get_site_transient( 'update_plugins' ) : null;
        $themes = function_exists( 'get_site_transient' ) ? get_site_transient( 'update_themes' ) : null;
        $core_updates = is_object( $core ) && is_array( $core->updates ?? null ) ? $core->updates : [];
        return [
            'success'   => true,
            'action'    => 'status',
            'available' => function_exists( 'get_site_transient' ),
            'before'    => [],
            'after'     => [],
            'counts'    => [
                'core'    => count( array_filter( $core_updates, static fn( mixed $item ): bool => is_object( $item ) && 'upgrade' === (string) ( $item->response ?? '' ) ) ),
                'plugins' => is_object( $plugins ) && is_array( $plugins->response ?? null ) ? count( $plugins->response ) : 0,
                'themes'  => is_object( $themes ) && is_array( $themes->response ?? null ) ? count( $themes->response ) : 0,
            ],
            'items' => [
                'core_version' => function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'version' ) : (string) ( $GLOBALS['wp_version'] ?? '' ),
                'plugins'      => is_object( $plugins ) && is_array( $plugins->response ?? null ) ? array_keys( $plugins->response ) : [],
                'themes'       => is_object( $themes ) && is_array( $themes->response ?? null ) ? array_keys( $themes->response ) : [],
            ],
            'messages' => [],
        ];
    }

    /** @return array<string,mixed> */
    public function update_core(): array {
        if ( isset( $this->callbacks['update_core'] ) ) {
            return (array) call_user_func( $this->callbacks['update_core'] );
        }
        $this->load_upgrader_api();
        $this->refresh_update_data( 'core' );
        $before = $this->status();
        $updates = function_exists( 'get_site_transient' ) ? get_site_transient( 'update_core' ) : null;
        $candidate = null;
        foreach ( is_object( $updates ) && is_array( $updates->updates ?? null ) ? $updates->updates : [] as $update ) {
            if ( is_object( $update ) && 'upgrade' === (string) ( $update->response ?? '' ) ) {
                $candidate = $update;
                break;
            }
        }
        if ( ! $candidate ) {
            return $this->result( true, 'update_core', $before, $this->status(), [ 'requested' => 0, 'updated' => 0, 'failed' => 0 ], [], [ 'WordPress core is already current.' ] );
        }
        if ( ! class_exists( 'Core_Upgrader' ) ) {
            return $this->result( false, 'update_core', $before, $this->status(), [ 'requested' => 1, 'updated' => 0, 'failed' => 1 ], [], [ 'WordPress Core_Upgrader is unavailable.' ] );
        }
        $result = ( new \Core_Upgrader( $this->quiet_skin() ) )->upgrade( $candidate );
        $success = ! $this->is_error( $result ) && false !== $result;
        $this->refresh_update_data( 'core' );
        return $this->result( $success, 'update_core', $before, $this->status(), [ 'requested' => 1, 'updated' => $success ? 1 : 0, 'failed' => $success ? 0 : 1 ], [ $this->normalize_upgrader_result( 'wordpress', $result ) ], [ $success ? 'WordPress core updated.' : $this->error_message( $result, 'WordPress core update failed.' ) ] );
    }

    /** @return array<string,mixed> */
    public function update_plugins( array $plugins = [] ): array {
        if ( isset( $this->callbacks['update_plugins'] ) ) {
            return (array) call_user_func( $this->callbacks['update_plugins'], $plugins );
        }
        $this->load_upgrader_api();
        $this->refresh_update_data( 'plugins' );
        $before = $this->status();
        $transient = function_exists( 'get_site_transient' ) ? get_site_transient( 'update_plugins' ) : null;
        $available = is_object( $transient ) && is_array( $transient->response ?? null ) ? array_keys( $transient->response ) : [];
        $targets = [] === $plugins ? $available : array_values( array_intersect( array_map( 'strval', $plugins ), $available ) );
        if ( [] === $targets ) {
            return $this->result( true, 'update_plugins', $before, $this->status(), [ 'requested' => 0, 'updated' => 0, 'failed' => 0 ], [], [ 'No matching plugin updates are available.' ] );
        }
        if ( ! class_exists( 'Plugin_Upgrader' ) ) {
            return $this->result( false, 'update_plugins', $before, $this->status(), [ 'requested' => count( $targets ), 'updated' => 0, 'failed' => count( $targets ) ], [], [ 'WordPress Plugin_Upgrader is unavailable.' ] );
        }
        $raw = ( new \Plugin_Upgrader( $this->quiet_skin() ) )->bulk_upgrade( $targets );
        return $this->bulk_result( 'update_plugins', $before, $targets, $raw );
    }

    /** @return array<string,mixed> */
    public function update_themes( array $themes = [] ): array {
        if ( isset( $this->callbacks['update_themes'] ) ) {
            return (array) call_user_func( $this->callbacks['update_themes'], $themes );
        }
        $this->load_upgrader_api();
        $this->refresh_update_data( 'themes' );
        $before = $this->status();
        $transient = function_exists( 'get_site_transient' ) ? get_site_transient( 'update_themes' ) : null;
        $available = is_object( $transient ) && is_array( $transient->response ?? null ) ? array_keys( $transient->response ) : [];
        $targets = [] === $themes ? $available : array_values( array_intersect( array_map( 'strval', $themes ), $available ) );
        if ( [] === $targets ) {
            return $this->result( true, 'update_themes', $before, $this->status(), [ 'requested' => 0, 'updated' => 0, 'failed' => 0 ], [], [ 'No matching theme updates are available.' ] );
        }
        if ( ! class_exists( 'Theme_Upgrader' ) ) {
            return $this->result( false, 'update_themes', $before, $this->status(), [ 'requested' => count( $targets ), 'updated' => 0, 'failed' => count( $targets ) ], [], [ 'WordPress Theme_Upgrader is unavailable.' ] );
        }
        $raw = ( new \Theme_Upgrader( $this->quiet_skin() ) )->bulk_upgrade( $targets );
        return $this->bulk_result( 'update_themes', $before, $targets, $raw );
    }

    /** @return array<string,mixed> */
    public function update_all( array $plugins = [], array $themes = [] ): array {
        if ( isset( $this->callbacks['update_all'] ) ) {
            return (array) call_user_func( $this->callbacks['update_all'], $plugins, $themes );
        }
        $before = $this->status();
        $core_result = $this->update_core();
        $plugin_result = $this->update_plugins( $plugins );
        $theme_result = $this->update_themes( $themes );
        $children = [ $core_result, $plugin_result, $theme_result ];
        $counts = [ 'requested' => 0, 'updated' => 0, 'failed' => 0 ];
        foreach ( $children as $child ) {
            foreach ( $counts as $key => $value ) {
                $counts[ $key ] += (int) ( $child['counts'][ $key ] ?? 0 );
            }
        }
        return $this->result( ! in_array( false, array_column( $children, 'success' ), true ), 'update_all', $before, $this->status(), $counts, $children, [ 'Immediate WordPress updates finished.' ] );
    }

    private function load_upgrader_api(): void {
        if ( defined( 'ABSPATH' ) && ! class_exists( 'WP_Upgrader' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }
    }

    private function refresh_update_data( string $type ): void {
        if ( 'core' === $type && function_exists( 'wp_version_check' ) ) {
            wp_version_check( [], true );
        } elseif ( 'plugins' === $type && function_exists( 'wp_update_plugins' ) ) {
            wp_update_plugins();
        } elseif ( 'themes' === $type && function_exists( 'wp_update_themes' ) ) {
            wp_update_themes();
        }
    }

    private function quiet_skin(): mixed {
        return class_exists( 'Automatic_Upgrader_Skin' ) ? new \Automatic_Upgrader_Skin() : null;
    }

    /** @param array<int,string> $targets
     *  @return array<string,mixed>
     */
    private function bulk_result( string $action, array $before, array $targets, mixed $raw ): array {
        $items = [];
        $updated = 0;
        foreach ( $targets as $target ) {
            $value = is_array( $raw ) && array_key_exists( $target, $raw ) ? $raw[ $target ] : $raw;
            $ok = ! $this->is_error( $value ) && false !== $value && null !== $value;
            $updated += $ok ? 1 : 0;
            $items[] = $this->normalize_upgrader_result( $target, $value );
        }
        $failed = count( $targets ) - $updated;
        $this->refresh_update_data( 'update_plugins' === $action ? 'plugins' : 'themes' );
        return $this->result( 0 === $failed, $action, $before, $this->status(), [ 'requested' => count( $targets ), 'updated' => $updated, 'failed' => $failed ], $items, [ 0 === $failed ? 'Updates completed.' : 'One or more updates failed.' ] );
    }

    /** @return array<string,mixed> */
    private function normalize_upgrader_result( string $item, mixed $result ): array {
        return [ 'item' => $item, 'success' => ! $this->is_error( $result ) && false !== $result && null !== $result, 'message' => $this->error_message( $result, '' ) ];
    }

    private function is_error( mixed $value ): bool {
        return function_exists( 'is_wp_error' ) && is_wp_error( $value );
    }

    private function error_message( mixed $value, string $fallback ): string {
        return $this->is_error( $value ) ? (string) $value->get_error_message() : $fallback;
    }

    /** @return array<string,mixed> */
    private function result( bool $success, string $action, array $before, array $after, array $counts, array $items, array $messages ): array {
        return compact( 'success', 'action', 'before', 'after', 'counts', 'items', 'messages' );
    }
}
