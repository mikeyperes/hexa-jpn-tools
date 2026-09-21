<?php

namespace Hexa\PluginCore\ContentCleanup;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;

final class BackupCleanupAjaxController implements ModuleInterface {
    private BackupCleanupConfig $config;

    private BackupCleanupScanner $scanner;

    public function __construct( BackupCleanupConfig|array $config ) {
        $this->config  = is_array( $config ) ? new BackupCleanupConfig( $config ) : $config;
        $this->scanner = new BackupCleanupScanner( $this->config );
    }

    public function register(): void {
        AjaxActionRegistry::create(
            [
                'capability'   => $this->config->capability(),
                'nonce_action' => $this->config->nonce_action(),
                'nonce_field'  => $this->config->nonce_field(),
            ]
        )->register(
            [
                $this->config->scan_action() => [
                    'callback' => [ $this, 'scan' ],
                ],
                $this->config->delete_action() => [
                    'callback' => [ $this, 'delete' ],
                ],
            ]
        );
    }

    public function scan( AjaxRequest $request ): array {
        return $this->scanner->scan();
    }

    public function delete( AjaxRequest $request ): array|\WP_Error {
        return $this->scanner->delete( $request->key( 'file_id' ) );
    }
}
