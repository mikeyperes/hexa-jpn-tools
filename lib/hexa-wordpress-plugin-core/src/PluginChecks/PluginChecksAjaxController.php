<?php

namespace Hexa\PluginCore\PluginChecks;

use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxFailure;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;

final class PluginChecksAjaxController {
    private array $definitions;
    private array $config;

    /**
     * @param array<int,PluginCheckDefinition|array<string,mixed>> $definitions
     * @param array<string,mixed> $config
     */
    public function __construct( array $definitions, array $config = [] ) {
        $this->definitions = PluginCheckService::normalize_definitions( $definitions );
        $this->config      = array_merge(
            [
                'capability'    => 'update_plugins',
                'nonce_action'  => '',
                'nonce_field'   => 'nonce',
                'action_prefix' => 'hexa_plugin_checks',
            ],
            $config
        );
    }

    public function register(): void {
        if ( ! class_exists( AjaxActionRegistry::class ) ) {
            return;
        }

        ( new AjaxActionRegistry(
            [
                'capability'    => (string) $this->config['capability'],
                'nonce_action'  => (string) $this->config['nonce_action'],
                'nonce_field'   => (string) $this->config['nonce_field'],
                'action_prefix' => (string) $this->config['action_prefix'],
            ]
        ) )->register(
            [
                'status' => [
                    'callback' => [ $this, 'status' ],
                ],
                'refresh' => [
                    'callback' => [ $this, 'refresh' ],
                ],
                'install_activate' => [
                    'callback' => [ $this, 'install_activate' ],
                ],
                'activate' => [
                    'callback' => [ $this, 'activate' ],
                ],
            ]
        );
    }

    public function status( AjaxRequest $request ): array {
        return $this->payload( [ 'Status refreshed.' ] );
    }

    public function refresh( AjaxRequest $request ): array {
        PluginCheckService::refresh_update_cache();

        return $this->payload( [ 'WordPress plugin update cache refreshed.' ] );
    }

    public function install_activate( AjaxRequest $request ): array {
        $definition = $this->definition_from_request( $request );
        $result     = PluginCheckService::install_and_activate( $definition );

        if ( is_wp_error( $result ) ) {
            throw AjaxFailure::bad_request( $result->get_error_message(), $result->get_error_code() ?: 'plugin_install_failed' );
        }

        return $this->payload(
            [
                'Installing and activating ' . $definition->name . '.',
                (string) ( $result['message'] ?? 'Plugin installed and activated.' ),
            ],
            $definition->id
        );
    }

    public function activate( AjaxRequest $request ): array {
        $definition = $this->definition_from_request( $request );
        $result     = PluginCheckService::activate( $definition );

        if ( is_wp_error( $result ) ) {
            throw AjaxFailure::bad_request( $result->get_error_message(), $result->get_error_code() ?: 'plugin_activation_failed' );
        }

        return $this->payload(
            [
                'Activating ' . $definition->name . '.',
                (string) ( $result['message'] ?? 'Plugin activated.' ),
            ],
            $definition->id
        );
    }

    private function definition_from_request( AjaxRequest $request ): PluginCheckDefinition {
        $plugin_id = $request->text( 'plugin_id', '', 'post' );
        $map       = PluginCheckService::definitions_by_id( $this->definitions );

        if ( '' === $plugin_id || ! isset( $map[ $plugin_id ] ) ) {
            throw AjaxFailure::bad_request( 'Invalid plugin check ID.', 'invalid_plugin_id' );
        }

        return $map[ $plugin_id ];
    }

    /**
     * @param array<int,string> $log
     * @return array<string,mixed>
     */
    private function payload( array $log = [], string $plugin_id = '' ): array {
        $renderer = new PluginChecksRenderer();
        $args     = [
            'ajax_url'    => function_exists( 'admin_url' ) ? admin_url( 'admin-ajax.php' ) : '',
            'nonce'       => function_exists( 'wp_create_nonce' ) ? wp_create_nonce( (string) $this->config['nonce_action'] ) : '',
            'nonce_field' => (string) $this->config['nonce_field'],
            'actions'     => [
                'status'           => (string) $this->config['action_prefix'] . '_status',
                'refresh'          => (string) $this->config['action_prefix'] . '_refresh',
                'install_activate' => (string) $this->config['action_prefix'] . '_install_activate',
                'activate'         => (string) $this->config['action_prefix'] . '_activate',
            ],
        ];

        $statuses = PluginCheckService::statuses( $this->definitions );

        return [
            'plugin_id'    => $plugin_id,
            'summary'      => PluginCheckService::summary( $statuses ),
            'statuses'     => $statuses,
            'content_html' => $renderer->render_content( $this->definitions, $args ),
            'log'          => $log,
        ];
    }
}
