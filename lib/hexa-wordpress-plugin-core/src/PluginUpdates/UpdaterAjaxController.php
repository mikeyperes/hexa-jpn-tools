<?php

namespace Hexa\PluginCore\PluginUpdates;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

final class UpdaterAjaxController implements ModuleInterface {
    private UpdaterConfig $config;

    public function __construct( UpdaterConfig $config ) {
        $this->config = $config;
    }

    public function register(): void {
        add_action( 'wp_ajax_' . $this->config->ajax_action( 'force_update_check' ), [ $this, 'force_update_check' ] );
        add_action( 'wp_ajax_' . $this->config->ajax_action( 'direct_update_plugin' ), [ $this, 'direct_update_plugin' ] );
        add_action( 'wp_ajax_' . $this->config->ajax_action( 'get_update_progress' ), [ $this, 'get_update_progress' ] );
        add_action( 'wp_ajax_' . $this->config->ajax_action( 'download_plugin_zip' ), [ $this, 'download_plugin_zip' ] );
        add_action( 'wp_ajax_' . $this->config->ajax_action( 'load_github_versions' ), [ $this, 'load_github_versions' ] );
        add_action( 'wp_ajax_' . $this->config->ajax_action( 'download_specific_version' ), [ $this, 'download_specific_version' ] );
    }

    public function force_update_check(): void {
        $this->authorize( $this->config->capability() );

        ( new GitHubPluginUpdater( $this->config ) )->clear_cache();
        wp_update_plugins();

        $client        = new GitHubVersionClient( $this->config );
        $status_reader = new PluginUpdateStatus( $this->config, $client );
        $status        = $status_reader->get( true );
        $core_response = $status_reader->core_update_response();

        wp_send_json_success(
            array_merge(
                $status,
                [
                    'message'       => 'Update check complete.',
                    'new_version'   => $status['latest_version'],
                    'core_detected' => (bool) $core_response,
                    'core_version'  => $core_response->new_version ?? '',
                    'core_plugin'   => $core_response->plugin ?? '',
                ]
            )
        );
    }

    public function direct_update_plugin(): void {
        $this->authorize( $this->config->capability() );

        $result = ( new DirectPluginInstaller( $this->config ) )->run();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( $result );
    }

    public function get_update_progress(): void {
        $this->authorize( $this->config->capability() );

        wp_send_json_success( ( new UpdateProgressStore( $this->config->progress_key() ) )->get() );
    }

    public function download_plugin_zip(): void {
        $this->authorize( $this->config->download_capability() );

        $result = ( new PluginZipBuilder( $this->config ) )->build_current();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( $result );
    }

    public function load_github_versions(): void {
        $this->authorize( $this->config->download_capability() );

        $versions = ( new GitHubVersionClient( $this->config ) )->commits( 30 );

        wp_send_json_success(
            [
                'versions' => $versions,
                'count'    => count( $versions ),
            ]
        );
    }

    public function download_specific_version(): void {
        $this->authorize( $this->config->download_capability() );

        $version = isset( $_POST['version'] ) ? sanitize_text_field( wp_unslash( $_POST['version'] ) ) : '';
        $sha     = isset( $_POST['sha'] ) ? sanitize_text_field( wp_unslash( $_POST['sha'] ) ) : '';

        if ( '' === $version ) {
            wp_send_json_error( 'No version specified.' );
        }

        $result = ( new PluginZipBuilder( $this->config ) )->build_ref( $version, $sha );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( $result );
    }

    public function actions(): array {
        return [
            'force_update_check'       => $this->config->ajax_action( 'force_update_check' ),
            'direct_update_plugin'     => $this->config->ajax_action( 'direct_update_plugin' ),
            'get_update_progress'      => $this->config->ajax_action( 'get_update_progress' ),
            'download_plugin_zip'      => $this->config->ajax_action( 'download_plugin_zip' ),
            'load_github_versions'     => $this->config->ajax_action( 'load_github_versions' ),
            'download_specific_version'=> $this->config->ajax_action( 'download_specific_version' ),
        ];
    }

    private function authorize( string $capability ): void {
        if ( ! current_user_can( $capability ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        check_ajax_referer( $this->config->nonce_action(), $this->config->nonce_param() );
    }
}
