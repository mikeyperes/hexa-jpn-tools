<?php

namespace Hexa\PluginCore\ContentCleanup;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;

final class ArticleMediaCleanupAjaxController implements ModuleInterface {
    private ArticleMediaCleanupConfig $config;

    private ArticleMediaCleanupScanner $scanner;

    public function __construct( ArticleMediaCleanupConfig|array $config ) {
        $this->config  = is_array( $config ) ? new ArticleMediaCleanupConfig( $config ) : $config;
        $this->scanner = new ArticleMediaCleanupScanner( $this->config );
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
                $this->config->batch_delete_action() => [
                    'callback' => [ $this, 'batch_delete' ],
                ],
            ]
        );
    }

    public function scan( AjaxRequest $request ): array {
        return $this->scanner->scan(
            [
                'post_type'   => $request->key( 'post_type' ),
                'status'      => $request->key( 'status' ),
                'keep_recent' => $request->int( 'keep_recent' ),
                'search'      => $request->text( 'search' ),
                'limit'       => $request->int( 'limit' ),
            ]
        );
    }

    public function delete( AjaxRequest $request ): array|\WP_Error {
        return $this->scanner->delete_post(
            $request->int( 'post_id' ),
            $request->bool( 'delete_media' )
        );
    }

    public function batch_delete( AjaxRequest $request ): array|\WP_Error {
        return $this->scanner->delete_batch(
            [
                'post_type'   => $request->key( 'post_type' ),
                'status'      => $request->key( 'status' ),
                'keep_recent' => $request->int( 'keep_recent' ),
                'search'      => $request->text( 'search' ),
                'limit'       => $request->int( 'limit' ),
            ],
            $request->bool( 'delete_media' ),
            $request->key( 'batch_mode' ),
            $request->int( 'batch_size' ),
            array_map( 'absint', $request->items( 'exclude_ids' ) )
        );
    }
}
