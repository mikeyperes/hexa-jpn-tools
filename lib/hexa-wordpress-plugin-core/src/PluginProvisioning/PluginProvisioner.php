<?php

namespace Hexa\PluginCore\PluginProvisioning;

use Hexa\PluginCore\PluginUpdates\UpdaterFilesystem;

final class PluginProvisioner {
    private function __construct() {}

    public static function normalize_github_repo( string $repo_or_url ): string {
        $repo_or_url = trim( $repo_or_url );
        $repo_or_url = preg_replace( '#^git@github\.com:#', '', $repo_or_url );
        $repo_or_url = preg_replace( '#^https?://github\.com/#', '', $repo_or_url );
        $repo_or_url = preg_replace( '#\.git$#', '', (string) $repo_or_url );
        $repo_or_url = preg_replace( '#/archive/.*$#', '', (string) $repo_or_url );

        return trim( (string) $repo_or_url, '/' );
    }

    public static function find_plugin_file_by_folder( string $folder ): string {
        self::load_plugin_functions();

        $folder = self::sanitize_slug( $folder );
        wp_cache_delete( 'plugins', 'plugins' );

        foreach ( get_plugins() as $plugin_file => $plugin_data ) {
            if ( dirname( $plugin_file ) === $folder && ! empty( $plugin_data['Name'] ) ) {
                return $plugin_file;
            }
        }

        return '';
    }

    /**
     * @return array<string,mixed>
     */
    public static function plugin_status_by_folder( string $folder ): array {
        self::load_plugin_functions();

        $folder      = self::sanitize_slug( $folder );
        $plugin_file = self::find_plugin_file_by_folder( $folder );
        $installed   = $plugin_file !== '';
        $network_active = $installed && self::plugin_is_network_active( $plugin_file );

        return [
            'slug'        => $folder,
            'folder'      => $folder,
            'plugin_file' => $plugin_file,
            'installed'   => $installed,
            'active'      => $installed && self::plugin_is_active( $plugin_file ),
            'network_active' => $network_active,
            'folder_path' => WP_PLUGIN_DIR . '/' . $folder,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function plugin_status_by_file( string $plugin_file ): array {
        self::load_plugin_functions();

        $plugin_file    = trim( wp_normalize_path( $plugin_file ), '/' );
        $all_plugins    = get_plugins();
        $auto_updates   = (array) get_site_option( 'auto_update_plugins', [] );
        $network_active = self::plugin_is_network_active( $plugin_file );

        return [
            'installed'   => isset( $all_plugins[ $plugin_file ] ),
            'active'      => self::plugin_is_active( $plugin_file ),
            'network_active' => $network_active,
            'auto_update' => in_array( $plugin_file, $auto_updates, true ),
            'version'     => isset( $all_plugins[ $plugin_file ] ) ? $all_plugins[ $plugin_file ]['Version'] : null,
        ];
    }

    public static function prepare_filesystem(): mixed {
        require_once ABSPATH . 'wp-admin/includes/file.php';

        global $wp_filesystem;

        if ( ! function_exists( 'WP_Filesystem' ) || ! WP_Filesystem() || ! $wp_filesystem ) {
            return new \WP_Error( 'hexa_plugin_core_filesystem_unavailable', 'WordPress filesystem is unavailable.' );
        }

        return $wp_filesystem;
    }

    public static function cleanup_work_dir( string $path, string $prefix = 'hexa-github-plugin-' ): void {
        $upgrade_dir = trailingslashit( WP_CONTENT_DIR ) . 'upgrade/';
        $path        = wp_normalize_path( $path );

        if ( $path === '' || strpos( $path, wp_normalize_path( $upgrade_dir . $prefix ) ) !== 0 ) {
            return;
        }

        $filesystem = self::prepare_filesystem();
        if ( is_wp_error( $filesystem ) ) {
            return;
        }

        $filesystem->delete( $path, true );
    }

    public static function normalize_github_folder( string $slug ) {
        self::load_plugin_functions();

        $filesystem = self::prepare_filesystem();
        if ( is_wp_error( $filesystem ) ) {
            return $filesystem;
        }

        $slug = self::sanitize_slug( $slug );
        $dest = trailingslashit( WP_PLUGIN_DIR ) . $slug;

        if ( is_dir( $dest ) ) {
            return true;
        }

        foreach ( [ $slug . '-main', $slug . '-master' ] as $github_folder ) {
            $source = trailingslashit( WP_PLUGIN_DIR ) . $github_folder;

            if ( ! is_dir( $source ) ) {
                continue;
            }

            foreach ( get_plugins() as $plugin_file => $plugin_data ) {
                if ( dirname( $plugin_file ) === $github_folder && self::plugin_is_active( $plugin_file ) ) {
                    deactivate_plugins( $plugin_file, true, self::plugin_is_network_active( $plugin_file ) );
                }
            }

            $copied = UpdaterFilesystem::copy_directory_clean( $source, $dest );
            if ( is_wp_error( $copied ) ) {
                return $copied;
            }

            $filesystem->delete( $source, true );
            wp_cache_delete( 'plugins', 'plugins' );
            return true;
        }

        return true;
    }

    /**
     * @param array<string,mixed> $args
     */
    public static function install_github_plugin( string $slug, string $repo_or_url, array $args = [] ): string|\WP_Error {
        self::load_plugin_functions();

        $filesystem = self::prepare_filesystem();
        if ( is_wp_error( $filesystem ) ) {
            return $filesystem;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $slug        = self::sanitize_slug( $slug );
        $branch      = ! empty( $args['branch'] ) ? trim( (string) $args['branch'], '/' ) : 'main';
        $timeout     = isset( $args['timeout'] ) ? max( 15, (int) $args['timeout'] ) : 60;
        $work_prefix = ! empty( $args['work_prefix'] ) ? sanitize_key( (string) $args['work_prefix'] ) : 'hexa-github-plugin';
        $repo        = self::normalize_github_repo( $repo_or_url );
        $zip_url     = 'https://github.com/' . $repo . '/archive/refs/heads/' . rawurlencode( $branch ) . '.zip';
        $dest_dir    = trailingslashit( WP_PLUGIN_DIR ) . $slug;
        $work_dir    = trailingslashit( WP_CONTENT_DIR ) . 'upgrade/' . $work_prefix . '-' . $slug . '-' . wp_generate_password( 8, false, false );
        $tmp_file    = download_url( $zip_url, $timeout );

        if ( is_wp_error( $tmp_file ) ) {
            return $tmp_file;
        }

        if ( is_dir( $dest_dir ) ) {
            @unlink( $tmp_file );
            return new \WP_Error( 'hexa_plugin_core_folder_exists', 'Plugin folder already exists: ' . $slug );
        }

        if ( ! wp_mkdir_p( $work_dir ) ) {
            @unlink( $tmp_file );
            return new \WP_Error( 'hexa_plugin_core_work_dir_failed', 'Could not create temporary install directory.' );
        }

        $unzipped = unzip_file( $tmp_file, $work_dir );
        @unlink( $tmp_file );

        if ( is_wp_error( $unzipped ) ) {
            self::cleanup_work_dir( $work_dir, $work_prefix . '-' );
            return $unzipped;
        }

        $source_dir = self::find_extracted_source_dir( $work_dir, $slug );
        if ( $source_dir === '' ) {
            self::cleanup_work_dir( $work_dir, $work_prefix . '-' );
            return new \WP_Error( 'hexa_plugin_core_zip_empty', 'GitHub ZIP did not contain a plugin folder.' );
        }

        $copied = UpdaterFilesystem::copy_directory_clean( $source_dir, $dest_dir );
        if ( is_wp_error( $copied ) ) {
            self::cleanup_work_dir( $work_dir, $work_prefix . '-' );
            return $copied;
        }

        self::cleanup_work_dir( $work_dir, $work_prefix . '-' );
        wp_cache_delete( 'plugins', 'plugins' );

        $plugin_file = self::find_plugin_file_by_folder( $slug );
        if ( $plugin_file === '' ) {
            return new \WP_Error( 'hexa_plugin_core_plugin_header_missing', 'Installed folder does not contain a WordPress plugin header.' );
        }

        return $plugin_file;
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public static function install_wordpress_org_plugin( string $slug, bool $activate = true ): array|\WP_Error {
        self::load_plugin_functions();

        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $slug = self::sanitize_slug( $slug );
        if ( $slug === '' ) {
            return new \WP_Error( 'hexa_plugin_core_missing_slug', 'No plugin slug provided.' );
        }

        $api = plugins_api(
            'plugin_information',
            [
                'slug'   => $slug,
                'fields' => [
                    'sections' => false,
                ],
            ]
        );

        if ( is_wp_error( $api ) ) {
            return $api;
        }

        $skin     = new \WP_Ajax_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader( $skin );
        $result   = $upgrader->install( $api->download_link );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( false === $result ) {
            return new \WP_Error( 'hexa_plugin_core_install_failed', 'Installation failed.' );
        }

        $plugin_file = (string) $upgrader->plugin_info();
        $activated   = false;
        $message     = 'Plugin installed successfully.';

        if ( $activate && $plugin_file !== '' ) {
            $activated_result = self::activate_plugin_file( $plugin_file );
            if ( is_wp_error( $activated_result ) ) {
                return [
                    'message'     => 'Installed but activation failed: ' . $activated_result->get_error_message(),
                    'activated'   => false,
                    'plugin_file' => $plugin_file,
                ];
            }

            $activated = true;
            $message   = 'Plugin installed and activated successfully.';
        }

        return [
            'message'     => $message,
            'activated'   => $activated,
            'plugin_file' => $plugin_file,
        ];
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public static function activate_plugin_file( string $plugin_file ): array|\WP_Error {
        self::load_plugin_functions();

        $plugin_file = trim( wp_normalize_path( $plugin_file ), '/' );
        if ( $plugin_file === '' ) {
            return new \WP_Error( 'hexa_plugin_core_missing_plugin_file', 'No plugin file provided.' );
        }

        if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
            return new \WP_Error( 'hexa_plugin_core_plugin_file_missing', 'Plugin file not found: ' . $plugin_file );
        }

        if ( self::plugin_is_active( $plugin_file ) ) {
            return [
                'message'     => 'Plugin is already active.',
                'activated'   => true,
                'plugin_file' => $plugin_file,
            ];
        }

        $result = activate_plugin( $plugin_file );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return [
            'message'     => 'Plugin activated successfully.',
            'activated'   => true,
            'plugin_file' => $plugin_file,
        ];
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>|\WP_Error
     */
    public static function ensure_github_plugin_active( string $slug, string $repo_or_url, array $args = [] ): array|\WP_Error {
        $slug       = self::sanitize_slug( $slug );
        $normalized = self::normalize_github_folder( $slug );

        if ( is_wp_error( $normalized ) ) {
            return $normalized;
        }

        $status      = self::plugin_status_by_folder( $slug );
        $plugin_file = (string) $status['plugin_file'];
        $message     = '';

        if ( ! $status['installed'] ) {
            $installed = self::install_github_plugin( $slug, $repo_or_url, $args );
            if ( is_wp_error( $installed ) ) {
                return $installed;
            }

            $plugin_file = $installed;
            $message     = 'Installed from GitHub and normalized to folder ' . $slug . '.';
        }

        if ( $plugin_file === '' ) {
            return new \WP_Error( 'hexa_plugin_core_plugin_file_missing_after_install', 'Plugin file could not be found after install.' );
        }

        $activation = self::activate_plugin_file( $plugin_file );
        if ( is_wp_error( $activation ) ) {
            return $activation;
        }

        $status = self::plugin_status_by_folder( $slug );

        return [
            'message'     => $message ? $message . ' Activated.' : ( $activation['message'] ?? 'Activated.' ),
            'slug'        => $slug,
            'installed'   => $status['installed'],
            'active'      => $status['active'],
            'plugin_file' => $status['plugin_file'],
            'folder'      => $status['folder'],
            'folder_path' => $status['folder_path'],
        ];
    }

    private static function load_plugin_functions(): void {
        if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }

    private static function plugin_is_active( string $plugin_file ): bool {
        return ( function_exists( 'is_plugin_active' ) && is_plugin_active( $plugin_file ) )
            || self::plugin_is_network_active( $plugin_file );
    }

    private static function plugin_is_network_active( string $plugin_file ): bool {
        if ( function_exists( 'is_plugin_active_for_network' ) ) {
            return is_plugin_active_for_network( $plugin_file );
        }

        if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_site_option' ) ) {
            $network_plugins = get_site_option( 'active_sitewide_plugins', [] );
            return is_array( $network_plugins ) && isset( $network_plugins[ $plugin_file ] );
        }

        return false;
    }

    private static function sanitize_slug( string $slug ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $slug ) : preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $slug ) );
    }

    private static function find_extracted_source_dir( string $work_dir, string $slug ): string {
        $dirs = glob( trailingslashit( $work_dir ) . '*', GLOB_ONLYDIR );
        if ( empty( $dirs ) ) {
            return '';
        }

        foreach ( $dirs as $dir ) {
            if ( in_array( basename( $dir ), [ $slug, $slug . '-main', $slug . '-master' ], true ) ) {
                return $dir;
            }
        }

        return (string) $dirs[0];
    }
}
