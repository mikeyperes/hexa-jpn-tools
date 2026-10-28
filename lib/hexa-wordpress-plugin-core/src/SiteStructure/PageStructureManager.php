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
                'site_type'            => null,
                'site_type_labels'     => [],
                'site_type_edit_url'   => '',
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

        self::$managers[ (string) $this->config['option_prefix'] ] = $this;
        PageRedirects::register();
    }

    /** Every page manager built on this request, so a page can have a parent another plugin owns. */
    private static array $managers = [];

    public function owns( string $page_key ): bool {
        return isset( $this->flat_pages()[ $page_key ] );
    }

    /**
     * The page linked to a page key from any plugin: this manager's own
     * assignment, another loaded manager's, then the
     * `hexa_site_structure_page_id` filter for plugins with their own page
     * linking (filter( int $page_id, string $page_key )).
     */
    public function resolve_page_id( string $page_key ): int {
        if ( '' === $page_key ) {
            return 0;
        }
        $id = $this->owns( $page_key ) ? $this->assigned_page_id( $page_key ) : 0;
        if ( $id <= 0 ) {
            foreach ( self::$managers as $manager ) {
                if ( $manager !== $this && $manager->owns( $page_key ) ) {
                    $id = $manager->assigned_page_id( $page_key );
                    break;
                }
            }
        }
        if ( function_exists( 'apply_filters' ) ) {
            $id = (int) apply_filters( 'hexa_site_structure_page_id', $id, $page_key );
        }

        return $id > 0 && function_exists( 'get_post' ) && get_post( $id ) && 'trash' !== get_post_status( $id ) ? $id : 0;
    }

    /**
     * Moves a page under its parent (0 = top level) and keeps its old address
     * redirecting. Returns whether the page moved.
     */
    public static function place_under_parent( int $page_id, int $parent_id ): bool {
        if ( $page_id <= 0 || $page_id === $parent_id || ! function_exists( 'get_post' ) ) {
            return false;
        }
        $page = get_post( $page_id );
        if ( ! $page instanceof \WP_Post || (int) $page->post_parent === $parent_id ) {
            return false;
        }
        $old_path = PageRedirects::path( $page_id );
        $result   = wp_update_post( [ 'ID' => $page_id, 'post_parent' => max( 0, $parent_id ) ], true );
        if ( is_wp_error( $result ) ) {
            return false;
        }
        if ( function_exists( 'clean_post_cache' ) ) {
            clean_post_cache( $page_id );
        }
        PageRedirects::remember( $page_id, $old_path );

        return true;
    }

    /** A page at the slug at any level, published first (0 when none). */
    public function find_page_by_slug( string $slug, array $only_statuses = [] ): int {
        if ( '' === $slug || ! function_exists( 'get_posts' ) ) {
            return 0;
        }
        foreach ( $only_statuses ? [ $only_statuses ] : [ [ 'publish' ], [ 'draft', 'private', 'pending' ] ] as $statuses ) {
            $ids = get_posts( [ 'name' => $slug, 'post_type' => 'page', 'post_status' => $statuses, 'posts_per_page' => 1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ] );
            if ( ! empty( $ids ) ) {
                return (int) $ids[0];
            }
        }

        return 0;
    }

    /** A page whose slug matches a pattern with * wildcards ("*mission-statement"), published first (0 when none). */
    public function find_page_by_pattern( string $pattern, array $only_statuses = [] ): int {
        global $wpdb;
        if ( '' === $pattern || ! isset( $wpdb ) ) {
            return 0;
        }
        $statuses = array_values( array_intersect( $only_statuses ?: [ 'publish', 'draft', 'private', 'pending' ], [ 'publish', 'draft', 'private', 'pending' ] ) );
        $like     = str_replace( "\0", '%', $wpdb->esc_like( str_replace( '*', "\0", $pattern ) ) );
        $in       = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        $sql      = $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_name LIKE %s AND post_status IN ($in) ORDER BY post_status = 'publish' DESC, ID ASC LIMIT 1", array_merge( [ $like ], $statuses ) ); // phpcs:ignore WordPress.DB.PreparedSQL

        return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }

    /**
     * Facts a page needs that are empty: the page definition's
     * `required_facts` (list of alternatives, any one is enough, keyed by
     * label) read through the `facts_getter` config callable.
     *
     * @return string[] labels of the missing facts
     */
    public function missing_facts( string $page_key ): array {
        $def    = $this->flat_pages()[ $page_key ] ?? [];
        $groups = is_array( $def['required_facts'] ?? null ) ? $def['required_facts'] : [];
        $getter = $this->config['facts_getter'] ?? null;
        if ( empty( $groups ) || ! is_callable( $getter ) ) {
            return [];
        }
        $missing = [];
        foreach ( $groups as $label => $alternatives ) {
            $found = false;
            foreach ( (array) $alternatives as $fact ) {
                $value = call_user_func( $getter, (string) $fact );
                if ( null !== $value && '' !== $value && [] !== $value && false !== $value ) {
                    $found = true;
                    break;
                }
            }
            if ( ! $found ) {
                $missing[] = is_string( $label ) ? $label : implode( ' or ', (array) $alternatives );
            }
        }

        return $missing;
    }

    /**
     * Makes one page exist and sit under its parent: the assigned page, else
     * a page already at its slug or one of its `slugs` alternatives (`*` is a
     * wildcard) at any level (linked, content untouched),
     * else a new page from its template. Then it is moved under its parent
     * (`parent` page key, from any plugin), with the old address redirecting.
     *
     * @return array{page_key:string,page_id:int,created:bool,moved:bool,parent_id:int}|\WP_Error
     */
    public function ensure_page( string $page_key ): array|\WP_Error {
        $def = $this->flat_pages()[ $page_key ] ?? null;
        if ( null === $def ) {
            return new \WP_Error( 'unknown_page_key', 'Unknown page key.' );
        }
        $parent_key = (string) ( $def['parent'] ?? '' );
        $parent_id  = '' !== $parent_key ? $this->resolve_page_id( $parent_key ) : 0;
        $created    = false;
        $page_id    = $this->resolve_page_id( $page_key );
        if ( $page_id <= 0 ) {
            // A published page at any of its addresses wins over a draft at the main one.
            $slugs = array_merge( [ (string) ( $def['slug'] ?? $page_key ) ], array_map( 'strval', (array) ( $def['slugs'] ?? [] ) ) );
            foreach ( [ [ 'publish' ], [ 'draft', 'private', 'pending' ] ] as $statuses ) {
                foreach ( $slugs as $slug ) {
                    $page_id = false !== strpos( $slug, '*' ) ? $this->find_page_by_pattern( $slug, $statuses ) : $this->find_page_by_slug( $slug, $statuses );
                    if ( $page_id > 0 ) {
                        break 2;
                    }
                }
            }
            if ( $page_id > 0 ) {
                $this->save_assignment( $page_key, $page_id );
            }
        }
        if ( $page_id <= 0 ) {
            $result = $this->create_page( $page_key );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
            $page_id = (int) ( $result['page_id'] ?? 0 );
            $created = true;
        }
        $moved = '' !== $parent_key && $parent_id > 0 ? self::place_under_parent( $page_id, $parent_id ) : false;
        if ( $moved ) {
            $this->log( 'Page moved under parent: ' . $page_key . ' (ID: ' . $page_id . ') -> ' . $parent_key . ' (ID: ' . $parent_id . ')' );
        }

        return [ 'page_key' => $page_key, 'page_id' => $page_id, 'created' => $created, 'moved' => $moved, 'parent_id' => $parent_id ];
    }

    /**
     * The one setup call: every page in the blueprint (or the listed keys),
     * parents before children.
     *
     * @param string[] $keys
     * @return array<string,array<string,mixed>|\WP_Error>
     */
    public function ensure_pages( array $keys = [] ): array {
        $flat    = $this->flat_pages();
        $keys    = empty( $keys ) ? array_keys( $flat ) : array_values( array_intersect( array_map( 'strval', $keys ), array_keys( $flat ) ) );
        $keys    = array_values( array_filter( $keys, fn( string $key ): bool => $this->is_enabled( $key ) ) );
        $depth   = static function ( string $key ) use ( $flat, &$depth ): int {
            $parent = (string) ( $flat[ $key ]['parent'] ?? '' );
            return '' !== $parent && isset( $flat[ $parent ] ) && $parent !== $key ? 1 + $depth( $parent ) : 0;
        };
        usort( $keys, static fn( string $a, string $b ): int => $depth( $a ) <=> $depth( $b ) );
        $results = [];
        foreach ( $keys as $key ) {
            $results[ $key ] = $this->ensure_page( $key );
        }

        return $results;
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

    /**
     * Why a page does not apply to this site ('' when it does). A page definition's 'not_for'
     * lists the site types it does not apply to; the 'site_type' callback says what the site
     * represents and 'site_type_labels' names each type ('person' => 'a person').
     */
    public function unavailable_reason( string $page_key ): string {
        $def     = $this->flat_pages()[ $page_key ] ?? [];
        $not_for = array_map( 'strval', (array) ( $def['not_for'] ?? [] ) );
        $getter  = $this->config['site_type'] ?? null;
        if ( empty( $not_for ) || ! is_callable( $getter ) ) {
            return '';
        }
        $type = (string) call_user_func( $getter );
        if ( ! in_array( $type, $not_for, true ) ) {
            return '';
        }
        $labels = is_array( $this->config['site_type_labels'] ) ? $this->config['site_type_labels'] : [];

        return 'This page does not apply to a website that represents ' . (string) ( $labels[ $type ] ?? $type ) . '.';
    }

    /**
     * A page with its own custom design (page definition 'design' => 'custom'): it is still found,
     * linked and placed under its parent, but created empty and never filled with the default text;
     * the site's custom design (for example an Elementor build) provides its content.
     */
    public function is_custom_design( string $page_key ): bool {
        return 'custom' === (string) ( $this->flat_pages()[ $page_key ]['design'] ?? '' );
    }

    /** Where the site type is changed (for the "not available" note). */
    public function site_type_edit_url(): string {
        return (string) $this->config['site_type_edit_url'];
    }

    /** Option holding each page's on/off choice (page key => bool). */
    public function enabled_option(): string {
        return (string) $this->config['option_prefix'] . 'enabled_pages';
    }

    /**
     * Whether a page is part of the site: the saved on/off choice, else on,
     * except an 'optional' page, which is on only once it has a page.
     */
    public function is_enabled( string $page_key ): bool {
        if ( '' !== $this->unavailable_reason( $page_key ) ) {
            return false;
        }
        $saved = function_exists( 'get_option' ) ? (array) get_option( $this->enabled_option(), [] ) : [];
        if ( array_key_exists( $page_key, $saved ) ) {
            return (bool) $saved[ $page_key ];
        }
        $def = $this->flat_pages()[ $page_key ] ?? [];

        return empty( $def['optional'] ) || $this->assigned_page_id( $page_key ) > 0;
    }

    /**
     * Turns a page on or off. Off pages are skipped by ensure_pages() and show as Off in the page list;
     * an existing page is left as it is.
     *
     * @return array<string,mixed>|\WP_Error
     */
    public function set_enabled( string $page_key, bool $enabled ): array|\WP_Error {
        if ( ! isset( $this->flat_pages()[ $page_key ] ) ) {
            return new \WP_Error( 'hpc_unknown_page', 'Unknown page.' );
        }
        $reason = $this->unavailable_reason( $page_key );
        if ( $enabled && '' !== $reason ) {
            return new \WP_Error( 'hpc_page_unavailable', $reason );
        }
        $saved              = (array) get_option( $this->enabled_option(), [] );
        $saved[ $page_key ] = $enabled;
        update_option( $this->enabled_option(), $saved, false );

        return [ 'page_key' => $page_key, 'enabled' => $enabled, 'message' => $enabled ? 'Page turned on.' : 'Page turned off.' ];
    }

    public function option_key( string $page_key ): string {
        return (string) $this->config['option_prefix'] . $page_key;
    }

    /** The linked page; 0 when none, or when the linked page was deleted or is in the trash. */
    public function assigned_page_id( string $page_key ): int {
        if ( isset( $this->config['assignment_getter'] ) && is_callable( $this->config['assignment_getter'] ) ) {
            $id = max( 0, (int) call_user_func( $this->config['assignment_getter'], $page_key ) );
        } else {
            $id = function_exists( 'get_option' ) ? max( 0, (int) get_option( $this->option_key( $page_key ), 0 ) ) : 0;
        }
        if ( $id > 0 && function_exists( 'get_post_status' ) ) {
            $status = get_post_status( $id );
            if ( false === $status || 'trash' === $status ) {
                return 0;
            }
        }
        return $id;
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
            $parent_page_id = $this->resolve_page_id( $parent_key );
            if ( $parent_page_id > 0 ) {
                self::place_under_parent( $page_id, $parent_page_id );
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

        $parent_id = '' !== $parent_key ? $this->resolve_page_id( $parent_key ) : 0;

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
        // A custom-design page waits as a draft until its design is built, so no empty page goes live.
        if ( $this->is_custom_design( $page_key ) ) {
            $status = 'draft';
        }

        $page_id = wp_insert_post(
            [
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_content' => $this->is_custom_design( $page_key ) ? '' : $this->template_content( $page_key ),
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
        if ( $this->is_custom_design( $page_key ) ) {
            return new \WP_Error( 'hpc_custom_design', 'This page has its own custom design; the default text is not put into it.' );
        }
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
