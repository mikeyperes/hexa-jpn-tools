<?php

namespace Hexa\PluginCore\ContentCleanup;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;

final class ContentCleanupAjaxController implements ModuleInterface {
    private ContentCleanupConfig $config;

    private ContentCleanupScanner $scanner;

    public function __construct( ContentCleanupConfig|array $config ) {
        $this->config  = is_array( $config ) ? new ContentCleanupConfig( $config ) : $config;
        $this->scanner = new ContentCleanupScanner( $this->config );
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
                $this->config->trash_action() => [
                    'callback' => [ $this, 'trash' ],
                ],
                $this->config->delete_action() => [
                    'callback' => [ $this, 'delete' ],
                ],
            ]
        );
    }

    public function scan( AjaxRequest $request ): array {
        return $this->scanner->scan( $this->criteria_from_request( $request ) );
    }

    public function trash( AjaxRequest $request ): array|\WP_Error {
        return $this->scanner->trash( $request->int( 'post_id' ) );
    }

    public function delete( AjaxRequest $request ): array|\WP_Error {
        return $this->scanner->delete( $request->int( 'post_id' ) );
    }

    private function criteria_from_request( AjaxRequest $request ): array {
        return [
            'post_type'             => $request->key( 'post_type' ),
            'status'                => $request->key( 'status' ),
            'published_before_days' => $request->int( 'published_before_days' ),
            'modified_before_days'  => $request->int( 'modified_before_days' ),
            'search'                => $request->text( 'search' ),
            'limit'                 => $request->int( 'limit' ),
        ];
    }
}
