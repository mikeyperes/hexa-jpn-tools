<?php

namespace Hexa\PluginCore\PluginChecks;

use Hexa\PluginCore\PluginProvisioning\PluginProvisioner;

final class PluginCheckService {
    /**
     * @param array<int,PluginCheckDefinition|array<string,mixed>> $definitions
     * @return array<int,PluginCheckDefinition>
     */
    public static function normalize_definitions( array $definitions ): array {
        $items = [];
        foreach ( $definitions as $definition ) {
            if ( $definition instanceof PluginCheckDefinition ) {
                $items[] = $definition;
                continue;
            }

            if ( is_array( $definition ) ) {
                $items[] = PluginCheckDefinition::from_array( $definition );
            }
        }

        return $items;
    }

    /**
     * @param array<int,PluginCheckDefinition|array<string,mixed>> $definitions
     * @return array<string,PluginCheckDefinition>
     */
    public static function definitions_by_id( array $definitions ): array {
        $map = [];
        foreach ( self::normalize_definitions( $definitions ) as $definition ) {
            $map[ $definition->id ] = $definition;
        }

        return $map;
    }

    /**
     * @return array<string,mixed>
     */
    public static function status( PluginCheckDefinition $definition ): array {
        self::load_plugin_functions();

        $plugins      = self::inventory_plugins( $definition );
        $plugin_file  = $definition->plugin_file;
        $installed    = '' !== $plugin_file && isset( $plugins[ $plugin_file ] );
        $resolved_by  = in_array( $definition->source, [ 'must_use', 'dropin' ], true ) ? $definition->source : 'plugin_file';

        if ( ! $installed && '' !== $definition->slug && ! in_array( $definition->source, [ 'must_use', 'dropin' ], true ) ) {
            $found = PluginProvisioner::find_plugin_file_by_folder( $definition->slug );
            if ( '' !== $found ) {
                $plugin_file = $found;
                $installed   = isset( $plugins[ $plugin_file ] );
                $resolved_by = 'folder';
            }
        }

        $version          = $installed ? (string) ( $plugins[ $plugin_file ]['Version'] ?? '' ) : '';
        $active           = in_array( $definition->source, [ 'must_use', 'dropin' ], true ) ? $installed : ( $installed && is_plugin_active( $plugin_file ) );
        $auto_updates     = (array) get_site_option( 'auto_update_plugins', [] );
        $auto_update      = ! in_array( $definition->source, [ 'must_use', 'dropin' ], true ) && $installed && in_array( $plugin_file, $auto_updates, true );
        $updates          = self::plugin_updates();
        $update_available = ! in_array( $definition->source, [ 'must_use', 'dropin' ], true ) && $installed && isset( $updates[ $plugin_file ] );
        $new_version      = $update_available ? (string) ( $updates[ $plugin_file ]->update->new_version ?? '' ) : '';
        $up_to_date       = $installed && ! $update_available;
        $recommended      = property_exists( $definition, 'recommended' ) ? (bool) $definition->recommended : (bool) $definition->required;
        $should_not_contain = method_exists( $definition, 'should_not_contain' ) ? $definition->should_not_contain() : false;
        $auto_update_expected = property_exists( $definition, 'auto_update_expected' ) ? (bool) $definition->auto_update_expected : false;

        $required_failures = [];
        if ( ! empty( $definition->checks['not_installed'] ) && $installed ) {
            $required_failures[] = $active ? 'forbidden_active' : 'forbidden_installed';
        }
        if ( ! empty( $definition->checks['installed'] ) && ! $installed ) {
            $required_failures[] = 'missing';
        }
        if ( ! empty( $definition->checks['active'] ) && ! $active ) {
            $required_failures[] = 'inactive';
        }
        if ( ! empty( $definition->checks['up_to_date'] ) && $installed && ! $up_to_date ) {
            $required_failures[] = 'outdated';
        }
        if ( ! empty( $definition->checks['auto_update'] ) && $installed && $auto_update_expected !== $auto_update ) {
            $required_failures[] = $auto_update ? 'auto_update_enabled' : 'auto_update_disabled';
        }

        return [
            'id'                => $definition->id,
            'name'              => $definition->name,
            'plugin_file'       => $plugin_file,
            'configured_file'   => $definition->plugin_file,
            'slug'              => $definition->slug,
            'source'            => $definition->source,
            'required'          => $definition->required,
            'recommended'       => $recommended,
            'should_not_contain' => $should_not_contain,
            'auto_update_expected' => $auto_update_expected,
            'checks'            => $definition->checks,
            'installed'         => $installed,
            'active'            => $active,
            'auto_update'       => $auto_update,
            'version'           => $version,
            'up_to_date'        => $up_to_date,
            'update_available'  => $update_available,
            'new_version'       => $new_version,
            'installable'       => $definition->supports_ajax_install(),
            'removable'         => $should_not_contain && $installed && ! in_array( $definition->source, [ 'must_use', 'dropin' ], true ),
            'download_url'      => self::download_url( $definition ),
            'download_label'    => $definition->download_label,
            'notes'             => $definition->notes,
            'resolved_by'       => $resolved_by,
            'required_failures' => $required_failures,
            'ok'                => [] === $required_failures,
        ];
    }

    /**
     * @param array<int,PluginCheckDefinition|array<string,mixed>> $definitions
     * @return array<int,array<string,mixed>>
     */
    public static function statuses( array $definitions ): array {
        return array_map(
            static fn( PluginCheckDefinition $definition ): array => self::status( $definition ),
            self::normalize_definitions( $definitions )
        );
    }

    /**
     * @param array<int,array<string,mixed>> $statuses
     * @return array<string,int>
     */
    public static function summary( array $statuses ): array {
        $summary = [
            'total'     => count( $statuses ),
            'ready'     => 0,
            'missing'   => 0,
            'inactive'  => 0,
            'outdated'  => 0,
            'unwanted'  => 0,
            'attention' => 0,
        ];

        foreach ( $statuses as $status ) {
            $forbidden = ! empty( $status['should_not_contain'] );
            $installed = ! empty( $status['installed'] );
            $active    = ! empty( $status['active'] );
            $checks    = isset( $status['checks'] ) && is_array( $status['checks'] ) ? $status['checks'] : [];

            if ( ! empty( $status['ok'] ) ) {
                $summary['ready']++;
            } else {
                $summary['attention']++;
            }

            if ( $forbidden ) {
                if ( $installed ) {
                    $summary['unwanted']++;
                }
            } else {
                if ( ! $installed && ( ! empty( $status['required'] ) || ! empty( $checks['installed'] ) ) ) {
                    $summary['missing']++;
                } elseif ( $installed && ! empty( $checks['active'] ) && ! $active ) {
                    $summary['inactive']++;
                }
            }

            if ( $installed && ! empty( $status['update_available'] ) ) {
                $summary['outdated']++;
            }
        }

        return $summary;
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public static function install_and_activate( PluginCheckDefinition $definition ): array|\WP_Error {
        self::load_plugin_functions();

        $status = self::status( $definition );
        if ( ! empty( $status['installed'] ) ) {
            return self::activate( $definition );
        }

        if ( 'wordpress_org' === $definition->source ) {
            $installed = PluginProvisioner::install_wordpress_org_plugin( $definition->wp_org_slug, true );
            if ( is_wp_error( $installed ) ) {
                return $installed;
            }

            return [
                'message' => (string) ( $installed['message'] ?? 'Plugin installed and activated.' ),
                'status'  => self::status( $definition ),
            ];
        }

        if ( 'github' === $definition->source ) {
            $installed = PluginProvisioner::ensure_github_plugin_active(
                $definition->slug,
                $definition->github_repo,
                [ 'branch' => $definition->github_branch ]
            );
            if ( is_wp_error( $installed ) ) {
                return $installed;
            }

            return [
                'message' => (string) ( $installed['message'] ?? 'Plugin installed and activated.' ),
                'status'  => self::status( $definition ),
            ];
        }

        return new \WP_Error( 'hexa_plugin_check_manual_install_required', 'This plugin requires a manual or pro download.' );
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public static function activate( PluginCheckDefinition $definition ): array|\WP_Error {
        $status = self::status( $definition );
        if ( empty( $status['installed'] ) || empty( $status['plugin_file'] ) ) {
            return new \WP_Error( 'hexa_plugin_check_not_installed', 'Plugin is not installed.' );
        }

        $activated = PluginProvisioner::activate_plugin_file( (string) $status['plugin_file'] );
        if ( is_wp_error( $activated ) ) {
            return $activated;
        }

        return [
            'message' => (string) ( $activated['message'] ?? 'Plugin activated.' ),
            'status'  => self::status( $definition ),
        ];
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public static function deactivate( PluginCheckDefinition $definition ): array|\WP_Error {
        if ( ! $definition->should_not_contain() ) {
            return new \WP_Error( 'hexa_plugin_check_deactivate_not_allowed', 'Deactivate is only available for plugins that should not be installed.' );
        }

        if ( in_array( $definition->source, [ 'must_use', 'dropin' ], true ) ) {
            return new \WP_Error( 'hexa_plugin_check_deactivate_unsupported', 'Must-use plugins and drop-ins cannot be deactivated from this inventory action.' );
        }

        if ( function_exists( 'current_user_can' ) && ! current_user_can( 'deactivate_plugins' ) ) {
            return new \WP_Error( 'hexa_plugin_check_deactivate_forbidden', 'You do not have permission to deactivate plugins.' );
        }

        $status = self::status( $definition );
        if ( empty( $status['installed'] ) || empty( $status['plugin_file'] ) ) {
            return new \WP_Error( 'hexa_plugin_check_not_installed', 'Plugin is not installed.' );
        }

        if ( empty( $status['active'] ) ) {
            return [
                'message' => $definition->name . ' is already inactive.',
                'status'  => $status,
            ];
        }

        deactivate_plugins( (string) $status['plugin_file'], false, false );

        $next = self::status( $definition );
        if ( ! empty( $next['active'] ) ) {
            return new \WP_Error( 'hexa_plugin_check_deactivate_failed', 'Plugin could not be deactivated.' );
        }

        return [
            'message' => $definition->name . ' deactivated.',
            'status'  => $next,
        ];
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public static function delete( PluginCheckDefinition $definition ): array|\WP_Error {
        if ( ! $definition->should_not_contain() ) {
            return new \WP_Error( 'hexa_plugin_check_delete_not_allowed', 'Delete is only available for plugins that should not be installed.' );
        }

        $status = self::status( $definition );
        if ( empty( $status['installed'] ) || empty( $status['plugin_file'] ) ) {
            return [
                'message' => $definition->name . ' is already absent.',
                'status'  => $status,
            ];
        }

        if ( in_array( $definition->source, [ 'must_use', 'dropin' ], true ) ) {
            return new \WP_Error( 'hexa_plugin_check_delete_unsupported', 'Must-use plugins and drop-ins cannot be deleted from this inventory action.' );
        }

        if ( function_exists( 'current_user_can' ) && ! current_user_can( 'delete_plugins' ) ) {
            return new \WP_Error( 'hexa_plugin_check_delete_forbidden', 'You do not have permission to delete plugins.' );
        }

        if ( ! empty( $status['active'] ) ) {
            $deactivated = self::deactivate( $definition );
            if ( is_wp_error( $deactivated ) ) {
                return $deactivated;
            }
        }

        if ( ! function_exists( 'delete_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $deleted = delete_plugins( [ (string) $status['plugin_file'] ] );
        if ( is_wp_error( $deleted ) ) {
            return $deleted;
        }
        if ( false === $deleted ) {
            return new \WP_Error( 'hexa_plugin_check_delete_failed', 'Plugin could not be deleted.' );
        }

        self::clear_plugin_inventory_cache();

        return [
            'message' => $definition->name . ' deleted.',
            'status'  => self::status( $definition ),
        ];
    }

    public static function refresh_update_cache(): void {
        if ( function_exists( 'wp_clean_update_cache' ) ) {
            wp_clean_update_cache();
        }
        if ( function_exists( 'delete_site_transient' ) ) {
            delete_site_transient( 'update_plugins' );
        }
        if ( function_exists( 'wp_update_plugins' ) ) {
            wp_update_plugins();
        }
    }

    private static function download_url( PluginCheckDefinition $definition ): string {
        if ( '' !== $definition->download_url ) {
            return $definition->download_url;
        }

        if ( 'wordpress_org' === $definition->source && '' !== $definition->wp_org_slug && function_exists( 'admin_url' ) ) {
            return admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . rawurlencode( $definition->wp_org_slug ) );
        }

        if ( 'github' === $definition->source && '' !== $definition->github_repo ) {
            return 'https://github.com/' . PluginProvisioner::normalize_github_repo( $definition->github_repo );
        }

        return function_exists( 'admin_url' ) ? admin_url( 'plugin-install.php?tab=upload' ) : '';
    }

    private static function plugin_updates(): array {
        self::load_update_functions();

        return function_exists( 'get_plugin_updates' ) ? get_plugin_updates() : [];
    }

    private static function load_plugin_functions(): void {
        if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function inventory_plugins( PluginCheckDefinition $definition ): array {
        if ( 'must_use' === $definition->source ) {
            return function_exists( 'get_mu_plugins' ) ? get_mu_plugins() : [];
        }

        if ( 'dropin' === $definition->source ) {
            if ( function_exists( 'get_dropins' ) ) {
                return get_dropins();
            }

            return function_exists( '_get_dropins' ) ? _get_dropins() : [];
        }

        return get_plugins();
    }

    private static function clear_plugin_inventory_cache(): void {
        if ( function_exists( 'wp_clean_plugins_cache' ) ) {
            wp_clean_plugins_cache( true );
        }

        if ( function_exists( 'wp_cache_delete' ) ) {
            wp_cache_delete( 'plugins', 'plugins' );
        }
    }

    private static function load_update_functions(): void {
        if ( ! function_exists( 'get_plugin_updates' ) ) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }
    }
}
