<?php

namespace Hexa\PluginCore\SiteStructure;

final class PageStructureManager {
    /**
     * @var array<string,mixed>
     */
    private array $config;

    private PageStructureMenuService $menu_service;

    private PageStructureTemplateService $template_service;

    /**
     * @param array<string,mixed> $config
     */
    public function __construct( array $config ) {
        $this->config = array_merge(
            [
                'pages'                => [],
                'menu_structures'      => [],
                'option_prefix'        => '',
                'template_option_prefix' => '',
                'managed_meta_key'     => '_hexa_managed_page',
                'managed_key_meta_key' => '_hexa_page_key',
                'logger'               => null,
                'created_page_status'  => 'publish',
                'select_post_statuses' => [ 'publish' ],
                'assignment_statuses'  => [ 'publish' ],
                'reuse_existing_pages' => false,
                'assignment_getter'    => null,
                'assignment_saver'     => null,
                'assignment_deleter'   => null,
                'default_templates'    => [],
                'default_template_getter' => null,
                'template_getter'      => null,
                'template_saver'       => null,
                'template_kses'        => true,
                'page_detail_renderer' => null,
                'menu_guess_terms'     => [
                    'header'     => [ 'header', 'main', 'primary', 'top' ],
                    'footer'     => [ 'footer', 'bottom' ],
                    'sub_footer' => [ 'sub-footer', 'sub footer', 'subfooter', 'legal', 'secondary' ],
                ],
            ],
            $config
        );

        $this->menu_service     = new PageStructureMenuService( $this, $this->config );
        $this->template_service = new PageStructureTemplateService( $this, $this->config );
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function pages(): array {
        return is_array( $this->config['pages'] ) ? $this->config['pages'] : [];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function flat_pages(): array {
        $flat = [];
        $this->flatten_pages( $this->pages(), $flat );

        return $flat;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function menu_structures(): array {
        return is_array( $this->config['menu_structures'] ) ? $this->config['menu_structures'] : [];
    }

    public function option_key( string $page_key ): string {
        return (string) $this->config['option_prefix'] . $page_key;
    }

    public function assigned_page_id( string $page_key ): int {
        if ( isset( $this->config['assignment_getter'] ) && is_callable( $this->config['assignment_getter'] ) ) {
            return max( 0, (int) call_user_func( $this->config['assignment_getter'], $page_key ) );
        }

        return function_exists( 'get_option' ) ? max( 0, (int) get_option( $this->option_key( $page_key ), 0 ) ) : 0;
    }

    public function assigned_page( string $page_key ): ?\WP_Post {
        $page_id = $this->assigned_page_id( $page_key );
        if ( $page_id <= 0 || ! function_exists( 'get_post' ) ) {
            return null;
        }

        $page = get_post( $page_id );

        return $page instanceof \WP_Post ? $page : null;
    }

    public function is_assigned_to_published_page( string $page_key ): bool {
        $page = $this->assigned_page( $page_key );

        return $page instanceof \WP_Post && 'publish' === $page->post_status;
    }

    public function is_assigned_page_set( string $page_key ): bool {
        $page = $this->assigned_page( $page_key );
        if ( ! $page instanceof \WP_Post ) {
            return false;
        }

        $statuses = is_array( $this->config['assignment_statuses'] ) ? array_map( 'strval', $this->config['assignment_statuses'] ) : [];
        if ( empty( $statuses ) ) {
            return true;
        }

        return in_array( (string) $page->post_status, $statuses, true );
    }

    /**
     * @return \WP_Post[]
     */
    public function all_pages(): array {
        if ( ! function_exists( 'get_posts' ) ) {
            return [];
        }

        $statuses = is_array( $this->config['select_post_statuses'] ) && ! empty( $this->config['select_post_statuses'] )
            ? array_values( array_map( 'strval', $this->config['select_post_statuses'] ) )
            : [ 'publish' ];
        $pages = get_posts(
            [
                'post_type'      => 'page',
                'posts_per_page' => -1,
                'post_status'    => $statuses,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]
        );

        return is_array( $pages ) ? $pages : [];
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function assign_page( string $page_key, int $page_id, string $parent_key = '' ): array|\WP_Error {
        $flat_pages = $this->flat_pages();
        if ( '' === $page_key || ! isset( $flat_pages[ $page_key ] ) ) {
            return new \WP_Error( 'unknown_page_key', 'Unknown page key.' );
        }

        if ( $page_id > 0 && ( ! function_exists( 'get_post' ) || ! get_post( $page_id ) ) ) {
            return new \WP_Error( 'page_not_found', 'Selected page was not found.' );
        }

        $this->save_assignment( $page_key, $page_id );

        if ( $page_id > 0 && '' !== $parent_key ) {
            $parent_page_id = $this->assigned_page_id( $parent_key );
            if ( $parent_page_id > 0 && function_exists( 'wp_update_post' ) ) {
                wp_update_post(
                    [
                        'ID'          => $page_id,
                        'post_parent' => $parent_page_id,
                    ]
                );
            }
        }

        $this->log( 'Page assigned: ' . $page_key . ' = ' . $page_id );

        $payload = [
            'page_key' => $page_key,
            'page_id'  => $page_id,
        ];

        if ( $page_id > 0 ) {
            $payload = array_merge( $payload, $this->page_payload( $page_id ) );
        }

        return $payload;
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function create_page( string $page_key, string $title = '', string $slug = '', string $parent_key = '' ): array|\WP_Error {
        $flat_pages = $this->flat_pages();
        if ( '' === $page_key || ! isset( $flat_pages[ $page_key ] ) ) {
            return new \WP_Error( 'unknown_page_key', 'Unknown page key.' );
        }

        $page_def = $flat_pages[ $page_key ];
        $title    = '' !== $title ? $title : (string) ( $page_def['title'] ?? '' );
        $slug     = '' !== $slug ? $slug : (string) ( $page_def['slug'] ?? $page_key );
        $parent_key = '' !== $parent_key ? $parent_key : (string) ( $page_def['parent'] ?? '' );

        if ( '' === $title ) {
            return new \WP_Error( 'missing_title', 'Page title is required.' );
        }

        $existing_assigned = $this->assigned_page_id( $page_key );
        if ( $existing_assigned > 0 && function_exists( 'get_post' ) && get_post( $existing_assigned ) ) {
            return $this->page_payload( $existing_assigned, true, 'Page already assigned.' );
        }

        $parent_id = '' !== $parent_key ? $this->assigned_page_id( $parent_key ) : 0;

        $existing_pages = function_exists( 'get_posts' )
            ? get_posts(
                array_filter(
                    [
                        'name'           => $slug,
                        'post_type'      => 'page',
                        'post_status'    => 'any',
                        'posts_per_page' => 1,
                        'post_parent'    => $parent_id ?: null,
                    ],
                    static fn( mixed $value ): bool => null !== $value
                )
            )
            : [];

        if ( ! empty( $existing_pages ) ) {
            $existing = $existing_pages[0];
            if ( $existing instanceof \WP_Post && $this->is_managed_page( $existing->ID, $page_key ) ) {
                $this->mark_managed_page( $existing->ID, $page_key );
                $this->save_assignment( $page_key, (int) $existing->ID );

                return $this->page_payload( $existing->ID, true, 'Existing managed page assigned.' );
            }

            if ( ! empty( $this->config['reuse_existing_pages'] ) && $existing instanceof \WP_Post ) {
                $this->save_assignment( $page_key, (int) $existing->ID );

                return $this->page_payload( (int) $existing->ID, true, 'Existing page assigned.' );
            }

            return new \WP_Error( 'duplicate_slug', 'A page with this slug already exists. Assign it from the dropdown instead of creating a duplicate.' );
        }

        if ( ! function_exists( 'wp_insert_post' ) ) {
            return new \WP_Error( 'wordpress_unavailable', 'WordPress page creation is unavailable.' );
        }

        $status = sanitize_key( (string) $this->config['created_page_status'] );
        if ( '' === $status ) {
            $status = 'publish';
        }

        $page_id = wp_insert_post(
            [
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_content' => $this->template_content( $page_key ),
                'post_status'  => $status,
                'post_type'    => 'page',
                'post_parent'  => $parent_id,
            ]
        );

        if ( is_wp_error( $page_id ) ) {
            return $page_id;
        }

        $page_id = (int) $page_id;
        $this->mark_managed_page( $page_id, $page_key );
        $this->save_assignment( $page_key, $page_id );
        $this->log( 'Page created: ' . $title . ' (ID: ' . $page_id . ', key: ' . $page_key . ')' );

        return $this->page_payload( $page_id );
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function delete_page( string $page_key, int $page_id = 0 ): array|\WP_Error {
        if ( '' === $page_key ) {
            return new \WP_Error( 'missing_page_key', 'Page key is required.' );
        }

        $assigned_page_id = $this->assigned_page_id( $page_key );
        if ( $assigned_page_id > 0 ) {
            $page_id = $assigned_page_id;
        }

        if ( $page_id <= 0 ) {
            return new \WP_Error( 'missing_page_id', 'Page ID is required.' );
        }

        $this->delete_assignment( $page_key );

        if ( ! $this->is_managed_page( $page_id, $page_key ) ) {
            $this->log( 'Page unassigned without deletion: ' . $page_key . ' (ID: ' . $page_id . ')' );

            return [
                'page_key' => $page_key,
                'page_id'  => $page_id,
                'trashed'  => false,
                'message'  => 'Page unassigned. Existing content was left intact.',
            ];
        }

        if ( ! function_exists( 'wp_trash_post' ) || ! wp_trash_post( $page_id ) ) {
            return new \WP_Error( 'delete_failed', 'Failed to delete page.' );
        }

        $this->log( 'Page deleted: ' . $page_key . ' (ID: ' . $page_id . ')' );

        return [
            'page_key' => $page_key,
            'page_id'  => $page_id,
            'trashed'  => true,
        ];
    }

    /**
     * @param array<int,object> $items
     * @return array<int,array<string,mixed>>
     */
    public function menu_item_labels( array $items ): array {
        return $this->menu_service->menu_item_labels( $items );
    }

    /**
     * @param array<int,object>|null $menus
     */
    public function guess_menu_id_for_structure( string $structure_key, ?array $menus = null ): int {
        return $this->menu_service->guess_menu_id_for_structure( $structure_key, $menus );
    }

    public function get_menu_item_from_menu( int $menu_id, int $menu_item_id ): ?object {
        return $this->menu_service->get_menu_item_from_menu( $menu_id, $menu_item_id );
    }

    public function find_page_menu_item( int $menu_id, int $page_id ): ?object {
        return $this->menu_service->find_page_menu_item( $menu_id, $page_id );
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function upsert_page_menu_item( int $menu_id, int $page_id, int $parent_menu_item_id = 0 ): array|\WP_Error {
        return $this->menu_service->upsert_page_menu_item( $menu_id, $page_id, $parent_menu_item_id );
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function create_custom_menu_item( int $menu_id, string $title, string $url, int $parent_menu_item_id = 0 ): array|\WP_Error {
        return $this->menu_service->create_custom_menu_item( $menu_id, $title, $url, $parent_menu_item_id );
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function create_navigation_menu( string $menu_name ): array|\WP_Error {
        return $this->menu_service->create_navigation_menu( $menu_name );
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function delete_navigation_menu( int $menu_id ): array|\WP_Error {
        return $this->menu_service->delete_navigation_menu( $menu_id );
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function attach_page_to_menu_item( int $menu_id, string $page_key, int $parent_item_id = 0 ): array|\WP_Error {
        return $this->menu_service->attach_page_to_menu_item( $menu_id, $page_key, $parent_item_id );
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function attach_menu_structure( int $menu_id, string $structure_key, int $parent_item_id = 0 ): array|\WP_Error {
        return $this->menu_service->attach_menu_structure( $menu_id, $structure_key, $parent_item_id );
    }

    /**
     * @return array<string,mixed>
     */
    public function menu_inventory_payload(): array {
        return $this->menu_service->menu_inventory_payload();
    }

    public function default_template( string $page_key ): string {
        return $this->template_service->default_template( $page_key );
    }

    public function stored_template( string $page_key ): string {
        return $this->template_service->stored_template( $page_key );
    }

    public function template_content( string $page_key ): string {
        return $this->template_service->template_content( $page_key );
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function page_workspace_payload( string $page_key, int $page_id = 0 ): array|\WP_Error {
        return $this->template_service->page_workspace_payload( $page_key, $page_id );
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function save_template( string $page_key, string $template ): array|\WP_Error {
        return $this->template_service->save_template( $page_key, $template );
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function apply_template( string $page_key, int $page_id = 0, bool $force = false ): array|\WP_Error {
        return $this->template_service->apply_template( $page_key, $page_id, $force );
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function update_page_slug( string $page_key, int $page_id, string $slug ): array|\WP_Error {
        return $this->template_service->update_page_slug( $page_key, $page_id, $slug );
    }

    /**
     * @return array<string,mixed>
     */
    public function page_payload( int $page_id, bool $existing = false, string $message = '' ): array {
        return $this->template_service->page_payload( $page_id, $existing, $message );
    }

    public function mark_managed_page( int $page_id, string $page_key ): void {
        if ( function_exists( 'update_post_meta' ) ) {
            update_post_meta( $page_id, (string) $this->config['managed_meta_key'], 1 );
            update_post_meta( $page_id, (string) $this->config['managed_key_meta_key'], $page_key );
        }
    }

    public function is_managed_page( int $page_id, string $page_key = '' ): bool {
        if ( $page_id <= 0 || ! function_exists( 'get_post_type' ) || 'page' !== get_post_type( $page_id ) ) {
            return false;
        }

        if ( ! function_exists( 'get_post_meta' ) || ! get_post_meta( $page_id, (string) $this->config['managed_meta_key'], true ) ) {
            return false;
        }

        if ( '' === $page_key ) {
            return true;
        }

        $stored_key = (string) get_post_meta( $page_id, (string) $this->config['managed_key_meta_key'], true );

        return '' === $stored_key || $stored_key === $page_key;
    }

    /**
     * @param array<string,array<string,mixed>> $pages
     * @param array<string,array<string,mixed>> $flat
     */
    private function flatten_pages( array $pages, array &$flat, ?string $parent_key = null ): void {
        foreach ( $pages as $page_key => $page_data ) {
            $page_key  = (string) $page_key;
            $page_data = is_array( $page_data ) ? $page_data : [];

            $children = isset( $page_data['children'] ) && is_array( $page_data['children'] ) ? $page_data['children'] : [];
            $page_data['key']      = $page_key;
            $page_data['parent']   = $page_data['parent'] ?? $parent_key;
            $page_data['children'] = $children;
            $flat[ $page_key ]     = $page_data;

            if ( ! empty( $children ) ) {
                $this->flatten_pages( $children, $flat, $page_key );
            }
        }
    }

    private function save_assignment( string $page_key, int $page_id ): void {
        if ( isset( $this->config['assignment_saver'] ) && is_callable( $this->config['assignment_saver'] ) ) {
            call_user_func( $this->config['assignment_saver'], $page_key, max( 0, $page_id ) );
            return;
        }

        if ( function_exists( 'update_option' ) ) {
            update_option( $this->option_key( $page_key ), max( 0, $page_id ), false );
        }
    }

    private function delete_assignment( string $page_key ): void {
        if ( isset( $this->config['assignment_deleter'] ) && is_callable( $this->config['assignment_deleter'] ) ) {
            call_user_func( $this->config['assignment_deleter'], $page_key );
            return;
        }

        if ( function_exists( 'delete_option' ) ) {
            delete_option( $this->option_key( $page_key ) );
        }
    }

    private function log( string $message ): void {
        if ( isset( $this->config['logger'] ) && is_callable( $this->config['logger'] ) ) {
            call_user_func( $this->config['logger'], $message );
        }
    }
}
