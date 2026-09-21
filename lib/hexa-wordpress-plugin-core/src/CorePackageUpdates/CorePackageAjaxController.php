<?php

namespace Hexa\PluginCore\CorePackageUpdates;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\PluginUpdates\UpdateProgressStore;

final class CorePackageAjaxController implements ModuleInterface {
    private CorePackageConfig $config;

    public function __construct( CorePackageConfig $config ) {
        $this->config = $config;
    }

    public function register(): void {
        add_action( 'wp_ajax_' . $this->config->ajax_action( 'force_check' ), [ $this, 'force_check' ] );
        add_action( 'wp_ajax_' . $this->config->ajax_action( 'update_core' ), [ $this, 'update_core' ] );
        add_action( 'wp_ajax_' . $this->config->ajax_action( 'get_update_progress' ), [ $this, 'get_update_progress' ] );
        add_action( 'wp_ajax_' . $this->config->ajax_action( 'download_core_zip' ), [ $this, 'download_core_zip' ] );
    }

    public function force_check(): void {
        $this->authorize( $this->config->capability() );

        $client = new CorePackageVersionClient( $this->config );
        $client->clear_cache();
        $status = ( new CorePackageStatus( $this->config, $client ) )->get( true );

        wp_send_json_success( $status );
    }

    public function update_core(): void {
        $this->authorize( $this->config->capability() );

        $result = ( new CorePackageInstaller( $this->config, new UpdateProgressStore( $this->config->progress_key() ) ) )->run_registered_hosts();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( $result );
    }

    public function get_update_progress(): void {
        $this->authorize( $this->config->capability() );

        wp_send_json_success( ( new UpdateProgressStore( $this->config->progress_key() ) )->get() );
    }

    public function download_core_zip(): void {
        $this->authorize( $this->config->download_capability() );

        $result = ( new CorePackageZipBuilder( $this->config ) )->build_current();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( $result );
    }

    public function actions(): array {
        return [
            'force_check'         => $this->config->ajax_action( 'force_check' ),
            'update_core'         => $this->config->ajax_action( 'update_core' ),
            'get_update_progress' => $this->config->ajax_action( 'get_update_progress' ),
            'download_core_zip'   => $this->config->ajax_action( 'download_core_zip' ),
        ];
    }

    private function authorize( string $capability ): void {
        if ( ! current_user_can( $capability ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        check_ajax_referer( $this->config->nonce_action(), $this->config->nonce_param() );
    }
}
