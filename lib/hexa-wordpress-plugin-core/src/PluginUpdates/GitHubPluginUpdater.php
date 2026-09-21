<?php

namespace Hexa\PluginCore\PluginUpdates;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

final class GitHubPluginUpdater implements ModuleInterface {
    private UpdaterConfig $config;

    private GitHubVersionClient $client;

    private ?string $activation_scope = null;

    public function __construct( UpdaterConfig $config, ?GitHubVersionClient $client = null ) {
        $this->config = $config;
        $this->client = $client ?: new GitHubVersionClient( $config );
    }

    public function register(): void {
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
        add_filter( 'plugins_api', [ $this, 'plugin_info' ], 10, 3 );
        add_filter( 'upgrader_pre_install', [ $this, 'pre_install' ], 9, 2 );
        add_filter( 'upgrader_source_selection', [ $this, 'source_selection' ], 10, 4 );
        add_filter( 'upgrader_post_install', [ $this, 'post_install' ], 10, 3 );
        add_filter( 'http_request_timeout', [ $this, 'http_timeout' ] );
        add_filter( 'http_request_args', [ $this, 'http_args' ], 10, 2 );
    }

    public function http_timeout( int $timeout ): int {
        return (int) $this->config->get( 'timeout', $timeout );
    }

    public function http_args( array $args, string $url ): array {
        if ( false === strpos( $url, 'github.com' ) && false === strpos( $url, 'githubusercontent.com' ) ) {
            return $args;
        }

        $args['sslverify'] = (bool) $this->config->get( 'sslverify', true );

        if ( $this->config->get( 'access_token' ) ) {
            $args['headers']['Authorization'] = 'token ' . $this->config->get( 'access_token' );
        }

        return $args;
    }

    public function check_for_update( mixed $transient ): object {
        if ( ! is_object( $transient ) ) {
            $transient = new \stdClass();
        }

        if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
            $transient->response = [];
        }

        if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
            $transient->no_update = [];
        }

        $remote_version = $this->client->remote_version();

        if ( false === $remote_version ) {
            return $transient;
        }

        $current_version = $this->config->version();

        if ( isset( $transient->checked[ $this->config->plugin_basename() ] ) ) {
            $current_version = $transient->checked[ $this->config->plugin_basename() ];
        }

        $plugin_info = (object) [
            'id'            => $this->config->github_url(),
            'slug'          => $this->config->proper_folder_name(),
            'plugin'        => $this->config->plugin_basename(),
            'new_version'   => $remote_version,
            'url'           => $this->config->github_url(),
            'package'       => $this->package_url(),
            'icons'         => [],
            'banners'       => [],
            'banners_rtl'   => [],
            'tested'        => $this->config->get( 'tested' ),
            'requires'      => $this->config->get( 'requires' ),
            'requires_php'  => $this->config->get( 'requires_php', '' ),
            'compatibility' => new \stdClass(),
        ];

        if ( version_compare( $remote_version, (string) $current_version, '>' ) ) {
            $transient->response[ $this->config->plugin_basename() ] = $plugin_info;
            unset( $transient->no_update[ $this->config->plugin_basename() ] );
        } else {
            $no_update              = clone $plugin_info;
            $no_update->new_version = $current_version;
            $transient->no_update[ $this->config->plugin_basename() ] = $no_update;
            unset( $transient->response[ $this->config->plugin_basename() ] );
        }

        return $transient;
    }

    public function plugin_info( mixed $result, string $action, object $args ): mixed {
        if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
            return $result;
        }

        if ( ! in_array( (string) $args->slug, [ $this->config->proper_folder_name(), $this->config->runtime_folder_name() ], true ) ) {
            return $result;
        }

        $remote_version = $this->client->remote_version();
        $github_data    = $this->client->repo_data();

        return (object) [
            'name'           => $this->config->plugin_name(),
            'slug'           => $this->config->proper_folder_name(),
            'version'        => $remote_version ?: $this->config->version(),
            'author'         => $this->config->get( 'author', '' ),
            'author_profile' => $this->config->get( 'homepage', '' ),
            'homepage'       => $this->config->get( 'homepage', $this->config->github_url() ),
            'requires'       => $this->config->get( 'requires' ),
            'tested'         => $this->config->get( 'tested' ),
            'downloaded'     => 0,
            'last_updated'   => $github_data && ! empty( $github_data->updated_at ) ? date( 'Y-m-d', strtotime( $github_data->updated_at ) ) : '',
            'sections'       => [
                'description' => $this->config->get( 'description', '' ),
                'changelog'   => $github_data && ! empty( $github_data->description ) ? $github_data->description : 'See GitHub repository for changelog.',
            ],
            'download_link'  => $this->package_url(),
        ];
    }

    public function pre_install( mixed $response, array $hook_extra ): mixed {
        if ( ! $this->matches_plugin_update_target( $hook_extra ) ) {
            return $response;
        }

        $this->activation_scope = $this->capture_activation_scope();

        $targets = array_unique(
            array_filter(
                [
                    WP_PLUGIN_DIR . '/' . $this->config->runtime_folder_name(),
                    WP_PLUGIN_DIR . '/' . $this->config->proper_folder_name(),
                ]
            )
        );

        foreach ( $targets as $target ) {
            if ( ! is_dir( $target ) ) {
                continue;
            }

            $purged = UpdaterFilesystem::purge_ignored_package_paths( $target, true );
            if ( is_wp_error( $purged ) ) {
                return new \WP_Error(
                    'hexa_plugin_core_update_metadata_locked',
                    'Cannot update ' . $this->config->plugin_name() . ' because the installed plugin contains locked package metadata. ' . $purged->get_error_message()
                );
            }
        }

        return $response;
    }

    public function source_selection( mixed $source, mixed $remote_source, mixed $upgrader, array $hook_extra ): mixed {
        global $wp_filesystem;

        if ( ! $this->matches_plugin_update_target( $hook_extra ) ) {
            return $source;
        }

        if ( basename( (string) $source ) === $this->config->proper_folder_name() ) {
            return trailingslashit( (string) $source );
        }

        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $target = trailingslashit( dirname( (string) $source ) ) . $this->config->proper_folder_name();

        if ( $wp_filesystem->exists( $target ) ) {
            $wp_filesystem->delete( $target, true );
        }

        if ( ! $wp_filesystem->move( (string) $source, $target, true ) ) {
            return new \WP_Error(
                'hexa_plugin_core_updater_rename_failed',
                sprintf( 'Unable to rename update package folder from %s to %s.', basename( (string) $source ), $this->config->proper_folder_name() )
            );
        }

        return trailingslashit( $target );
    }

    public function post_install( mixed $response, array $hook_extra, array $result ): mixed {
        global $wp_filesystem;

        if ( ! $this->matches_plugin_update_target( $hook_extra ) ) {
            return $response;
        }

        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $proper_destination = WP_PLUGIN_DIR . '/' . $this->config->proper_folder_name();

        if ( ! empty( $result['destination'] ) && untrailingslashit( $result['destination'] ) !== untrailingslashit( $proper_destination ) ) {
            if ( $wp_filesystem->exists( $proper_destination ) ) {
                $wp_filesystem->delete( $proper_destination, true );
            }

            if ( ! $wp_filesystem->move( $result['destination'], $proper_destination, true ) ) {
                return new \WP_Error(
                    'hexa_plugin_core_updater_destination_failed',
                    'The updated plugin could not be moved into its canonical folder.'
                );
            }
            $result['destination'] = $proper_destination;
        }

        $result['destination_name'] = $this->config->proper_folder_name();

        $legacy_destination = WP_PLUGIN_DIR . '/' . $this->config->runtime_folder_name();
        if (
            $this->config->runtime_folder_name() !== $this->config->proper_folder_name()
            && untrailingslashit( $legacy_destination ) !== untrailingslashit( $proper_destination )
            && $wp_filesystem->is_dir( $legacy_destination )
        ) {
            $wp_filesystem->delete( $legacy_destination, true );
        }

        $restored = $this->restore_activation_scope();
        if ( is_wp_error( $restored ) ) {
            return $restored;
        }

        return $response;
    }

    public function clear_cache(): void {
        $this->client->clear_cache();
        delete_site_transient( 'update_plugins' );
        delete_option( '_site_transient_update_plugins' );

        if ( function_exists( 'wp_clean_update_cache' ) ) {
            wp_clean_update_cache();
        }

        if ( function_exists( 'wp_clean_plugins_cache' ) ) {
            wp_clean_plugins_cache( true );
        }
    }

    public function get_new_version(): string|false {
        return $this->client->remote_version();
    }

    public function config(): UpdaterConfig {
        return $this->config;
    }

    private function package_url(): string {
        return $this->config->zip_url();
    }

    private function capture_activation_scope(): string {
        $this->load_plugin_functions();

        foreach ( $this->managed_basenames() as $plugin ) {
            if ( function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $plugin ) ) {
                return 'network';
            }
        }

        $active_plugins = function_exists( 'get_option' ) ? (array) get_option( 'active_plugins', [] ) : [];
        foreach ( $this->managed_basenames() as $plugin ) {
            if ( in_array( $plugin, $active_plugins, true ) ) {
                return 'site';
            }
        }

        return 'inactive';
    }

    private function restore_activation_scope(): true|\WP_Error {
        if ( null === $this->activation_scope || 'inactive' === $this->activation_scope ) {
            return true;
        }

        $this->load_plugin_functions();
        if ( function_exists( 'wp_clean_plugins_cache' ) ) {
            wp_clean_plugins_cache( true );
        }
        clearstatcache( true, WP_PLUGIN_DIR . '/' . $this->config->canonical_plugin_basename() );

        if ( ! function_exists( 'activate_plugin' ) ) {
            return new \WP_Error(
                'hexa_plugin_core_reactivation_unavailable',
                'The updated plugin could not be reactivated because the WordPress activation API is unavailable.'
            );
        }

        $network_wide = 'network' === $this->activation_scope;
        $activated    = activate_plugin( $this->config->canonical_plugin_basename(), '', $network_wide, true );
        if ( is_wp_error( $activated ) ) {
            return new \WP_Error(
                'hexa_plugin_core_reactivation_failed',
                'The plugin update installed, but WordPress could not restore its prior activation state: ' . $activated->get_error_message()
            );
        }

        $verified = $network_wide
            ? function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $this->config->canonical_plugin_basename() )
            : in_array( $this->config->canonical_plugin_basename(), (array) get_option( 'active_plugins', [] ), true );

        if ( ! $verified ) {
            return new \WP_Error(
                'hexa_plugin_core_reactivation_unverified',
                'The plugin update installed, but its prior activation state could not be verified.'
            );
        }

        return true;
    }

    /** @return list<string> */
    private function managed_basenames(): array {
        return array_values(
            array_unique(
                array_filter(
                    [
                        $this->config->plugin_basename(),
                        $this->config->canonical_plugin_basename(),
                        $this->config->runtime_folder_name() . '/' . $this->config->plugin_starter_file(),
                        $this->config->proper_folder_name() . '/' . $this->config->plugin_starter_file(),
                    ]
                )
            )
        );
    }

    private function load_plugin_functions(): void {
        if ( defined( 'ABSPATH' ) && ( ! function_exists( 'activate_plugin' ) || ! function_exists( 'is_plugin_active_for_network' ) ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }

    private function matches_plugin_update_target( array $hook_extra ): bool {
        $targets = array_unique(
            array_filter(
                [
                    $this->config->plugin_basename(),
                    $this->config->canonical_plugin_basename(),
                    $this->config->proper_folder_name() . '/' . $this->config->plugin_starter_file(),
                ]
            )
        );

        if ( ! empty( $hook_extra['plugin'] ) && is_string( $hook_extra['plugin'] ) && in_array( $hook_extra['plugin'], $targets, true ) ) {
            return true;
        }

        if ( ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
            foreach ( $hook_extra['plugins'] as $plugin ) {
                if ( is_string( $plugin ) && in_array( $plugin, $targets, true ) ) {
                    return true;
                }
            }
        }

        return false;
    }
}
