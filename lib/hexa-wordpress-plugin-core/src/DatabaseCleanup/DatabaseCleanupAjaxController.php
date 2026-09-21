<?php

namespace Hexa\PluginCore\DatabaseCleanup;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;

final class DatabaseCleanupAjaxController implements ModuleInterface {
    private DatabaseCleanupService $service;

    /**
     * @param array<string,mixed> $config
     */
    public function __construct( private array $config ) {
        $this->service = new DatabaseCleanupService( $config );
    }

    public function register(): void {
        AjaxActionRegistry::create(
            [
                'capability'   => (string) ( $this->config['capability'] ?? 'manage_options' ),
                'nonce_action' => (string) ( $this->config['nonce_action'] ?? '' ),
                'nonce_field'  => (string) ( $this->config['nonce_field'] ?? 'nonce' ),
            ]
        )->register(
            [
                (string) ( $this->config['status_action'] ?? 'hpc_database_cleanup_status' ) => [
                    'callback' => [ $this, 'status' ],
                ],
                (string) ( $this->config['start_action'] ?? 'hpc_database_cleanup_start' ) => [
                    'callback' => [ $this, 'start' ],
                ],
                (string) ( $this->config['cleanup_action'] ?? 'hpc_database_cleanup_task' ) => [
                    'callback' => [ $this, 'run_cleanup_task' ],
                ],
                (string) ( $this->config['table_action'] ?? 'hpc_database_cleanup_table' ) => [
                    'callback' => [ $this, 'optimize_table' ],
                ],
                (string) ( $this->config['finish_action'] ?? 'hpc_database_cleanup_finish' ) => [
                    'callback' => [ $this, 'finish' ],
                ],
            ]
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function status( AjaxRequest $request ): array {
        return [
            'plugin'        => $this->service->plugin_state(),
            'cleanup_tasks' => $this->service->cleanup_tasks(),
        ];
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function start( AjaxRequest $request ): array|\WP_Error {
        return $this->service->start_session();
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function run_cleanup_task( AjaxRequest $request ): array|\WP_Error {
        return $this->service->run_cleanup_task(
            $request->text( 'session_id', '', 'post' ),
            $request->key( 'task_id', '', 'post' )
        );
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function optimize_table( AjaxRequest $request ): array|\WP_Error {
        return $this->service->optimize_table(
            $request->text( 'session_id', '', 'post' ),
            $request->text( 'table', '', 'post' )
        );
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function finish( AjaxRequest $request ): array|\WP_Error {
        return $this->service->finish_session( $request->text( 'session_id', '', 'post' ) );
    }
}
