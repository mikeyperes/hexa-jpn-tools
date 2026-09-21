<?php

namespace Hexa\PluginCore\EntitySources;

use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;

final class PrimaryEntityAjaxController {
    private PrimaryEntityManager $manager;

    public function __construct( PrimaryEntityManager $manager ) {
        $this->manager = $manager;
    }

    public function register(): void {
        ( new AjaxActionRegistry(
            [
                'capability' => (string) $this->manager->config( 'capability', 'manage_options' ),
                'nonce_action' => (string) $this->manager->config( 'nonce_action', 'hexa_primary_entity' ),
                'nonce_field' => (string) $this->manager->config( 'nonce_field', 'nonce' ),
            ]
        ) )->register(
            [
                (string) $this->manager->config( 'ajax_action', 'hexa_save_primary_entity' ) => [ 'callback' => [ $this, 'save' ] ],
            ]
        );
    }

    /** @return array<string,mixed> */
    public function save( AjaxRequest $request ): array {
        $result = $this->manager->save(
            [
                'site_type' => $request->key( 'site_type', '', 'post' ), 'enabled' => $request->bool( 'enabled', false, 'post' ),
                'source' => $request->key( 'source', '', 'post' ), 'object_id' => $request->int( 'object_id', 0, 'post' ),
                'entity_type' => $request->key( 'entity_type', 'auto', 'post' ),
            ]
        );
        $preview = ( new PrimaryEntityRenderer() )->render_entity_preview(
            $result['entity'],
            $this->manager,
            (array) $this->manager->config( 'render_args', [] )
        );
        return [
            'site_type' => $result['site_type'], 'settings' => $result['settings'],
            'entity' => $result['entity'] ? [ 'id' => $result['entity']['id'], 'name' => $result['entity']['name'], 'entity_type' => $result['entity']['entity_type'] ] : null,
            'preview_html' => $preview,
            'message' => $result['entity'] ? 'Website and primary entity settings saved.' : 'Website settings saved. No primary entity is required.',
        ];
    }
}
