<?php

namespace Hexa\PluginCore\SiteStructure;

final class PageStructureMenuService {
    private PageStructureManager $manager;

    /**
     * @var array<string,mixed>
     */
    private array $config;

    /**
     * @param array<string,mixed> $config
     */
    public function __construct( PageStructureManager $manager, array $config ) {
        $this->manager = $manager;
        $this->config  = $config;
    }

    public function menu_item_labels( array $items ): array {
        $by_id = [];
        foreach ( $items as $item ) {
            if ( is_object( $item ) && isset( $item->ID ) ) {
                $by_id[ (int) $item->ID ] = $item;
            }
        }

        $rows = [];
        foreach ( $items as $item ) {
            if ( ! is_object( $item ) || ! isset( $item->ID ) ) {
                continue;
            }

            $depth     = 0;
            $parent_id = (int) ( $item->menu_item_parent ?? 0 );
            $seen      = [];
            while ( $parent_id > 0 && isset( $by_id[ $parent_id ] ) && ! isset( $seen[ $parent_id ] ) ) {
                $seen[ $parent_id ] = true;
                $depth++;
                $parent_id = (int) ( $by_id[ $parent_id ]->menu_item_parent ?? 0 );
            }

            $title = trim( (string) ( $item->title ?? '' ) );
            if ( '' === $title ) {
                $title = '(Untitled item #' . (int) $item->ID . ')';
            }

            $rows[] = [
                'id'        => (int) $item->ID,
                'title'     => $title,
                'label'     => str_repeat( '-- ', $depth ) . $title,
                'depth'     => $depth,
                'parent_id' => (int) ( $item->menu_item_parent ?? 0 ),
                'object_id' => (int) ( $item->object_id ?? 0 ),
                'object'    => (string) ( $item->object ?? '' ),
                'type'      => (string) ( $item->type ?? '' ),
                'url'       => (string) ( $item->url ?? '' ),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int,object>|null $menus
     */
    public function guess_menu_id_for_structure( string $structure_key, ?array $menus = null ): int {
        $menus = null === $menus && function_exists( 'wp_get_nav_menus' ) ? wp_get_nav_menus() : ( $menus ?? [] );
        $terms = $this->config['menu_guess_terms'][ $structure_key ] ?? [ $structure_key ];

        foreach ( $menus as $menu ) {
            $name = strtolower( (string) ( $menu->name ?? '' ) );
            foreach ( $terms as $needle ) {
                $needle = strtolower( (string) $needle );
                if ( '' !== $needle && false !== strpos( $name, $needle ) ) {
                    return (int) ( $menu->term_id ?? 0 );
                }
            }
        }

        return ! empty( $menus[0]->term_id ) ? (int) $menus[0]->term_id : 0;
    }

    public function get_menu_item_from_menu( int $menu_id, int $menu_item_id ): ?object {
        if ( $menu_id <= 0 || $menu_item_id <= 0 || ! function_exists( 'wp_get_nav_menu_items' ) ) {
            return null;
        }

        $items = wp_get_nav_menu_items( $menu_id ) ?: [];
        foreach ( $items as $item ) {
            if ( (int) $item->ID === $menu_item_id ) {
                return $item;
            }
        }

        return null;
    }

    public function find_page_menu_item( int $menu_id, int $page_id ): ?object {
        $items = function_exists( 'wp_get_nav_menu_items' ) ? ( wp_get_nav_menu_items( $menu_id ) ?: [] ) : [];
        foreach ( $items as $item ) {
            if ( 'post_type' === ( $item->type ?? '' ) && 'page' === ( $item->object ?? '' ) && (int) ( $item->object_id ?? 0 ) === $page_id ) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function upsert_page_menu_item( int $menu_id, int $page_id, int $parent_menu_item_id = 0 ): array|\WP_Error {
        $existing    = $this->find_page_menu_item( $menu_id, $page_id );
        $existing_id = $existing ? (int) $existing->ID : 0;

        $result = function_exists( 'wp_update_nav_menu_item' )
            ? wp_update_nav_menu_item(
                $menu_id,
                $existing_id,
                [
                    'menu-item-object-id' => $page_id,
                    'menu-item-object'    => 'page',
                    'menu-item-type'      => 'post_type',
                    'menu-item-parent-id' => $parent_menu_item_id,
                    'menu-item-status'    => 'publish',
                    'menu-item-title'     => function_exists( 'get_the_title' ) ? get_the_title( $page_id ) : '',
                ]
            )
            : new \WP_Error( 'wordpress_unavailable', 'WordPress menu updates are unavailable.' );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return [
            'success'      => true,
            'message'      => $existing_id ? 'Menu item updated.' : 'Menu item created.',
            'menu_item_id' => (int) $result,
            'created'      => ! $existing_id,
        ];
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function create_custom_menu_item( int $menu_id, string $title, string $url, int $parent_menu_item_id = 0 ): array|\WP_Error {
        $menu = $menu_id > 0 && function_exists( 'wp_get_nav_menu_object' ) ? wp_get_nav_menu_object( $menu_id ) : null;
        if ( ! $menu ) {
            return new \WP_Error( 'menu_not_found', 'Menu not found.' );
        }

        $title = trim( wp_strip_all_tags( $title ) );
        if ( '' === $title ) {
            return new \WP_Error( 'missing_menu_item_title', 'Menu item title is required.' );
        }

        $url = trim( $url );
        if ( '' === $url ) {
            return new \WP_Error( 'missing_menu_item_url', 'Menu item URL is required.' );
        }

        if ( '#' !== $url ) {
            $url = function_exists( 'esc_url_raw' ) ? esc_url_raw( $url ) : filter_var( $url, FILTER_SANITIZE_URL );
        }

        if ( '' === $url ) {
            return new \WP_Error( 'invalid_menu_item_url', 'Menu item URL is invalid.' );
        }

        if ( $parent_menu_item_id > 0 && ! $this->get_menu_item_from_menu( $menu_id, $parent_menu_item_id ) ) {
            return new \WP_Error( 'invalid_parent_item', 'Parent menu item does not belong to the selected menu.' );
        }

        $result = function_exists( 'wp_update_nav_menu_item' )
            ? wp_update_nav_menu_item(
                $menu_id,
                0,
                [
                    'menu-item-title'     => $title,
                    'menu-item-url'       => $url,
                    'menu-item-type'      => 'custom',
                    'menu-item-parent-id' => $parent_menu_item_id,
                    'menu-item-status'    => 'publish',
                ]
            )
            : new \WP_Error( 'wordpress_unavailable', 'WordPress menu updates are unavailable.' );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $this->log( 'Custom menu item created: ' . $title . ' -> ' . $menu->name );

        return [
            'menu_id'      => $menu_id,
            'menu_item_id' => (int) $result,
            'title'        => $title,
            'url'          => $url,
            'message'      => 'Menu item created.',
        ];
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function create_navigation_menu( string $menu_name ): array|\WP_Error {
        $menu_name = trim( $menu_name );
        if ( '' === $menu_name ) {
            return new \WP_Error( 'missing_menu_name', 'Menu name is required.' );
        }

        $existing = function_exists( 'wp_get_nav_menu_object' ) ? wp_get_nav_menu_object( $menu_name ) : null;
        if ( $existing ) {
            return [
                'menu_id' => (int) $existing->term_id,
                'name'    => $existing->name,
                'message' => 'Menu already exists.',
            ];
        }

        $menu_id = function_exists( 'wp_create_nav_menu' ) ? wp_create_nav_menu( $menu_name ) : new \WP_Error( 'wordpress_unavailable', 'WordPress menu creation is unavailable.' );
        if ( is_wp_error( $menu_id ) ) {
            return $menu_id;
        }

        $this->log( 'Navigation menu created: ' . $menu_name . ' (ID: ' . (int) $menu_id . ')' );

        return [
            'menu_id' => (int) $menu_id,
            'name'    => $menu_name,
            'message' => 'Menu created.',
        ];
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function delete_navigation_menu( int $menu_id ): array|\WP_Error {
        $menu = $menu_id > 0 && function_exists( 'wp_get_nav_menu_object' ) ? wp_get_nav_menu_object( $menu_id ) : null;
        if ( ! $menu ) {
            return new \WP_Error( 'menu_not_found', 'Menu not found.' );
        }

        $deleted = function_exists( 'wp_delete_nav_menu' ) ? wp_delete_nav_menu( $menu_id ) : false;
        if ( ! $deleted || is_wp_error( $deleted ) ) {
            return is_wp_error( $deleted ) ? $deleted : new \WP_Error( 'menu_delete_failed', 'Menu deletion failed.' );
        }

        $this->log( 'Navigation menu deleted: ' . $menu->name . ' (ID: ' . $menu_id . ')' );

        return [
            'menu_id' => $menu_id,
            'name'    => $menu->name,
            'message' => 'Menu deleted.',
        ];
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function attach_page_to_menu_item( int $menu_id, string $page_key, int $parent_item_id = 0 ): array|\WP_Error {
        $flat_pages = $this->manager->flat_pages();

        if ( $menu_id <= 0 || ! function_exists( 'wp_get_nav_menu_object' ) || ! wp_get_nav_menu_object( $menu_id ) ) {
            return new \WP_Error( 'menu_not_found', 'Menu not found.' );
        }

        if ( '' === $page_key || empty( $flat_pages[ $page_key ] ) ) {
            return new \WP_Error( 'unknown_page_key', 'Unknown page key.' );
        }

        if ( $parent_item_id > 0 && ! $this->get_menu_item_from_menu( $menu_id, $parent_item_id ) ) {
            return new \WP_Error( 'invalid_parent_item', 'Parent menu item does not belong to the selected menu.' );
        }

        $page_id = $this->manager->assigned_page_id( $page_key );
        if ( $page_id <= 0 || ! function_exists( 'get_post_status' ) || 'publish' !== get_post_status( $page_id ) ) {
            return new \WP_Error( 'page_not_assigned', (string) $flat_pages[ $page_key ]['title'] . ' is not assigned to a published page.' );
        }

        $result = $this->upsert_page_menu_item( $menu_id, $page_id, $parent_item_id );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $menu = wp_get_nav_menu_object( $menu_id );
        $this->log( 'Attached page to menu: ' . $page_key . ' -> ' . $menu->name );

        return [
            'message'      => $flat_pages[ $page_key ]['title'] . ' attached to ' . $menu->name . '.',
            'menu_item_id' => (int) $result['menu_item_id'],
        ];
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function attach_menu_structure( int $menu_id, string $structure_key, int $parent_item_id = 0 ): array|\WP_Error {
        $structures = $this->manager->menu_structures();
        $flat_pages = $this->manager->flat_pages();

        if ( $menu_id <= 0 || ! function_exists( 'wp_get_nav_menu_object' ) || ! wp_get_nav_menu_object( $menu_id ) ) {
            return new \WP_Error( 'menu_not_found', 'Menu not found.' );
        }

        if ( '' === $structure_key || empty( $structures[ $structure_key ] ) ) {
            return new \WP_Error( 'unknown_menu_structure', 'Unknown menu structure.' );
        }

        if ( $parent_item_id > 0 && ! $this->get_menu_item_from_menu( $menu_id, $parent_item_id ) ) {
            return new \WP_Error( 'invalid_parent_item', 'Parent menu item does not belong to the selected menu.' );
        }

        $added          = 0;
        $updated        = 0;
        $skipped        = 0;
        $created_by_key = [];

        foreach ( $structures[ $structure_key ]['page_keys'] ?? [] as $page_key ) {
            $page_key = (string) $page_key;
            if ( empty( $flat_pages[ $page_key ] ) ) {
                $skipped++;
                continue;
            }

            $page_id = $this->manager->assigned_page_id( $page_key );
            if ( $page_id <= 0 || ! function_exists( 'get_post_status' ) || 'publish' !== get_post_status( $page_id ) ) {
                $skipped++;
                continue;
            }

            $item_parent_id = $parent_item_id;
            $parent_key     = $flat_pages[ $page_key ]['parent'] ?? null;
            if ( $parent_key && isset( $created_by_key[ $parent_key ] ) ) {
                $item_parent_id = (int) $created_by_key[ $parent_key ];
            }

            $result = $this->upsert_page_menu_item( $menu_id, $page_id, $item_parent_id );
            if ( is_wp_error( $result ) ) {
                return $result;
            }

            $created_by_key[ $page_key ] = (int) $result['menu_item_id'];
            if ( ! empty( $result['created'] ) ) {
                $added++;
            } else {
                $updated++;
            }
        }

        $menu    = wp_get_nav_menu_object( $menu_id );
        $label   = (string) ( $structures[ $structure_key ]['title'] ?? $structure_key );
        $message = $label . ' attached to ' . $menu->name . ': ' . $added . ' added, ' . $updated . ' updated, ' . $skipped . ' skipped.';
        $this->log( 'Navigation structure attached: ' . $message );

        return [
            'message' => $message,
            'added'   => $added,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function menu_inventory_payload(): array {
        $menus = function_exists( 'wp_get_nav_menus' ) ? ( wp_get_nav_menus() ?: [] ) : [];
        $rows  = [];

        foreach ( $menus as $menu ) {
            $menu_id = (int) ( $menu->term_id ?? 0 );
            if ( $menu_id <= 0 ) {
                continue;
            }

            $items  = function_exists( 'wp_get_nav_menu_items' ) ? ( wp_get_nav_menu_items( $menu_id ) ?: [] ) : [];
            $rows[] = [
                'menu'  => [
                    'id'       => $menu_id,
                    'name'     => (string) ( $menu->name ?? '' ),
                    'slug'     => (string) ( $menu->slug ?? '' ),
                    'edit_url' => function_exists( 'admin_url' ) ? admin_url( 'nav-menus.php?menu=' . $menu_id ) : '',
                ],
                'items' => $this->menu_item_labels( $items ),
            ];
        }

        return [
            'menus'   => $rows,
            'message' => 'Menu inventory refreshed.',
        ];
    }

    private function log( string $message ): void {
        if ( isset( $this->config['logger'] ) && is_callable( $this->config['logger'] ) ) {
            call_user_func( $this->config['logger'], $message );
        }
    }
}
