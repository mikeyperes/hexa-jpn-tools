<?php

namespace Hexa\PluginCore\CorePackageUpdates;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

/** Synchronizes verified bundled Core packages after local plugin changes. */
final class CorePackageFleetSyncModule implements ModuleInterface {
    private static bool $registered = false;
    private static bool $running = false;

    public function register(): void {
        if ( self::$registered ) {
            return;
        }
        self::$registered = true;
        add_action( 'upgrader_process_complete', [ self::class, 'after_upgrade' ], 100, 2 );
        add_action( 'activated_plugin', [ self::class, 'after_activation' ], 100, 1 );
        add_action( 'admin_init', [ self::class, 'repair_admin_drift' ], 1 );
    }

    /** @param object $upgrader @param array<string,mixed> $details */
    public static function after_upgrade( object $upgrader, array $details ): void {
        unset( $upgrader );
        if ( 'plugin' !== (string) ( $details['type'] ?? '' ) || ! in_array( (string) ( $details['action'] ?? '' ), [ 'install', 'update' ], true ) ) {
            return;
        }
        self::run();
    }

    public static function after_activation( string $plugin ): void {
        unset( $plugin );
        self::run();
    }

    public static function repair_admin_drift(): void {
        if ( function_exists( 'current_user_can' ) && ! current_user_can( 'update_plugins' ) ) {
            return;
        }
        self::run();
    }

    private static function run(): void {
        if ( self::$running ) {
            return;
        }
        self::$running = true;
        $result = ( new CorePackageFleetSynchronizer() )->synchronize();
        self::$running = false;

        if ( is_wp_error( $result ) ) {
            if ( function_exists( 'do_action' ) ) {
                do_action( 'hexa_plugin_core_package_sync_failed', $result );
            }
            return;
        }
        if ( ! empty( $result['changed'] ) && function_exists( 'do_action' ) ) {
            do_action( 'hexa_plugin_core_package_synchronized', $result );
        }
    }
}
