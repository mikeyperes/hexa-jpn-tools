<?php

namespace Hexa\PluginCore\ContentTypes;

use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxFailure;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;

final class ContentTypeAjaxController {
    private ContentTypeRegistry $registry;
    private array $config;

    public function __construct( ContentTypeRegistry $registry, array $config ) {
        $this->registry = $registry;
        $this->config   = $config;
    }

    public function register(): void {
        if ( empty( $this->config['ajax_action'] ) ) {
            return;
        }

        ( new AjaxActionRegistry(
            [
                'capability'   => (string) $this->config['capability'],
                'nonce_action' => (string) $this->config['nonce_action'],
                'nonce_field'  => (string) $this->config['nonce_field'],
            ]
        ) )->register(
            [
                (string) $this->config['ajax_action'] => [ 'callback' => [ $this, 'save' ] ],
            ]
        );
    }

    /** @return array<string,mixed> */
    public function save( AjaxRequest $request ): array {
        $id         = $request->key( 'content_type_id', '', 'post' );
        $definition = $this->registry->definition( $id );
        if ( ! $definition ) {
            throw AjaxFailure::not_found( 'Unknown content type definition.', 'unknown_content_type' );
        }

        $before = $this->registry->store()->resolve( $definition );
        $saved  = $this->registry->store()->save(
            $definition,
            [
                'enabled'             => $request->bool( 'enabled', false, 'post' ),
                'singular'            => $request->text( 'singular', '', 'post' ),
                'plural'              => $request->text( 'plural', '', 'post' ),
                'rewrite_slug'        => $request->title_slug( 'rewrite_slug', '', 'post' ),
                'enabled_field_groups'=> $request->key_array( 'enabled_field_groups', 'post' ),
            ]
        );

        $slug_changed = $before['post_type']['rewrite_slug'] !== $saved['post_type']['rewrite_slug'];
        if ( $slug_changed && function_exists( 'flush_rewrite_rules' ) ) {
            flush_rewrite_rules( false );
        }

        return [
            'content_type_id' => $id,
            'enabled'         => (bool) $saved['enabled'],
            'singular'        => $saved['post_type']['singular'],
            'plural'          => $saved['post_type']['plural'],
            'rewrite_slug'    => $saved['post_type']['rewrite_slug'],
            'field_groups'    => array_column( $saved['field_groups'], 'enabled', 'id' ),
            'slug_changed'    => $slug_changed,
            'requires_reload' => true,
            'message'         => 'Content type settings saved. Registration changes apply on the next page load.',
        ];
    }
}
