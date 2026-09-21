<?php

namespace Hexa\PluginCore\SiteStructure;

use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;

final class SiteStructureAjaxController {
    private PageStructureManager $manager;

    /**
     * @var array<string,mixed>
     */
    private array $config;

    /**
     * @param array<string,mixed> $config
     */
    public function __construct( PageStructureManager $manager, array $config = [] ) {
        $this->manager = $manager;
        $this->config  = array_merge(
            [
                'capability'   => 'manage_options',
                'nonce_action' => '',
                'nonce_field'  => 'nonce',
                'logger'       => null,
                'actions'      => [],
            ],
            $config
        );
    }

    public function register(): void {
        $actions = array_merge(
            [
                'assign_page'              => '',
                'create_page'              => '',
                'delete_page'              => '',
                'create_navigation_menu'   => '',
                'delete_navigation_menu'   => '',
                'create_menu_item'         => '',
                'attach_page_to_menu_item' => '',
                'attach_menu_structure'    => '',
                'menu_inventory'           => '',
                'save_template'            => '',
                'apply_template'           => '',
                'page_details'             => '',
                'page_workspace'           => '',
                'update_page_slug'         => '',
            ],
            is_array( $this->config['actions'] ) ? $this->config['actions'] : []
        );

        $registry = new AjaxActionRegistry(
            [
                'capability'   => (string) $this->config['capability'],
                'nonce_action' => (string) $this->config['nonce_action'],
                'nonce_field'  => (string) $this->config['nonce_field'],
                'logger'       => $this->config['logger'],
            ]
        );

        $registry->register( $this->action_map( $actions ) );
    }

    /**
     * @param array<string,string> $actions
     * @return array<string,array<string,mixed>>
     */
    private function action_map( array $actions ): array {
        $map = [];

        if ( '' !== (string) $actions['assign_page'] ) {
            $map['assign_page'] = [
                'action'   => (string) $actions['assign_page'],
                'callback' => fn( AjaxRequest $request ): array|\WP_Error => $this->manager->assign_page(
                    $request->key( 'page_key' ),
                    $request->int( 'page_id' ),
                    $request->key( 'parent_key' )
                ),
            ];
        }

        if ( '' !== (string) $actions['create_page'] ) {
            $map['create_page'] = [
                'action'   => (string) $actions['create_page'],
                'callback' => fn( AjaxRequest $request ): array|\WP_Error => $this->manager->create_page(
                    $request->key( 'page_key' ),
                    $request->text( 'title' ),
                    $request->title_slug( 'slug' ),
                    $request->key( 'parent_key' )
                ),
            ];
        }

        if ( '' !== (string) $actions['delete_page'] ) {
            $map['delete_page'] = [
                'action'   => (string) $actions['delete_page'],
                'callback' => fn( AjaxRequest $request ): array|\WP_Error => $this->manager->delete_page(
                    $request->key( 'page_key' ),
                    $request->int( 'page_id' )
                ),
            ];
        }

        if ( '' !== (string) $actions['create_navigation_menu'] ) {
            $map['create_navigation_menu'] = [
                'action'   => (string) $actions['create_navigation_menu'],
                'callback' => fn( AjaxRequest $request ): array|\WP_Error => $this->manager->create_navigation_menu(
                    $request->text( 'menu_name' )
                ),
            ];
        }

        if ( '' !== (string) $actions['delete_navigation_menu'] ) {
            $map['delete_navigation_menu'] = [
                'action'   => (string) $actions['delete_navigation_menu'],
                'callback' => fn( AjaxRequest $request ): array|\WP_Error => $this->manager->delete_navigation_menu(
                    $request->int( 'menu_id' )
                ),
            ];
        }

        if ( '' !== (string) $actions['create_menu_item'] ) {
            $map['create_menu_item'] = [
                'action'   => (string) $actions['create_menu_item'],
                'callback' => fn( AjaxRequest $request ): array|\WP_Error => $this->manager->create_custom_menu_item(
                    $request->int( 'menu_id' ),
                    $request->text( 'title' ),
                    $request->text( 'url' ),
                    $request->int( 'parent_item_id' )
                ),
            ];
        }

        if ( '' !== (string) $actions['attach_page_to_menu_item'] ) {
            $map['attach_page_to_menu_item'] = [
                'action'   => (string) $actions['attach_page_to_menu_item'],
                'callback' => fn( AjaxRequest $request ): array|\WP_Error => $this->manager->attach_page_to_menu_item(
                    $request->int( 'menu_id' ),
                    $request->key( 'page_key' ),
                    $request->int( 'parent_item_id' )
                ),
            ];
        }

        if ( '' !== (string) $actions['attach_menu_structure'] ) {
            $map['attach_menu_structure'] = [
                'action'   => (string) $actions['attach_menu_structure'],
                'callback' => fn( AjaxRequest $request ): array|\WP_Error => $this->manager->attach_menu_structure(
                    $request->int( 'menu_id' ),
                    $request->key( 'structure' ),
                    $request->int( 'parent_item_id' )
                ),
            ];
        }

        if ( '' !== (string) $actions['menu_inventory'] ) {
            $map['menu_inventory'] = [
                'action'   => (string) $actions['menu_inventory'],
                'callback' => fn( AjaxRequest $request ): array => $this->manager->menu_inventory_payload(),
            ];
        }

        if ( '' !== (string) $actions['save_template'] ) {
            $map['save_template'] = [
                'action'   => (string) $actions['save_template'],
                'callback' => fn( AjaxRequest $request ): array|\WP_Error => $this->manager->save_template(
                    $request->key( 'page_key' ),
                    $request->html( 'template' )
                ),
            ];
        }

        if ( '' !== (string) $actions['apply_template'] ) {
            $map['apply_template'] = [
                'action'   => (string) $actions['apply_template'],
                'callback' => fn( AjaxRequest $request ): array|\WP_Error => $this->manager->apply_template(
                    $request->key( 'page_key' ),
                    $request->int( 'page_id' ),
                    $request->bool( 'force' )
                ),
            ];
        }

        if ( '' !== (string) $actions['page_details'] ) {
            $map['page_details'] = [
                'action'   => (string) $actions['page_details'],
                'callback' => fn( AjaxRequest $request ): array|\WP_Error => $this->manager->page_payload(
                    $request->int( 'page_id' )
                ),
            ];
        }

        if ( '' !== (string) $actions['page_workspace'] ) {
            $map['page_workspace'] = [
                'action'   => (string) $actions['page_workspace'],
                'callback' => fn( AjaxRequest $request ): array|\WP_Error => $this->manager->page_workspace_payload(
                    $request->key( 'page_key' ),
                    $request->int( 'page_id' )
                ),
            ];
        }

        if ( '' !== (string) $actions['update_page_slug'] ) {
            $map['update_page_slug'] = [
                'action'   => (string) $actions['update_page_slug'],
                'callback' => fn( AjaxRequest $request ): array|\WP_Error => $this->manager->update_page_slug(
                    $request->key( 'page_key' ),
                    $request->int( 'page_id' ),
                    $request->title_slug( 'slug' )
                ),
            ];
        }

        return $map;
    }
}
