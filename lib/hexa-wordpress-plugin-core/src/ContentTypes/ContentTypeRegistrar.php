<?php

namespace Hexa\PluginCore\ContentTypes;

final class ContentTypeRegistrar {
    private ContentTypeRegistry $registry;

    public function __construct( ContentTypeRegistry $registry ) {
        $this->registry = $registry;
    }

    public function register_post_types(): void {
        foreach ( $this->registry->resolved_definitions() as $definition ) {
            if ( empty( $definition['enabled'] ) || 'external' === $definition['registration_mode'] ) {
                continue;
            }

            $post_type = $definition['post_type'];
            $args      = $post_type['args'];
            $args['labels'] = array_merge( $this->post_type_labels( $post_type['singular'], $post_type['plural'] ), is_array( $args['labels'] ?? null ) ? $args['labels'] : [] );

            if ( false !== ( $args['rewrite'] ?? true ) ) {
                $rewrite = is_array( $args['rewrite'] ?? null ) ? $args['rewrite'] : [];
                $args['rewrite'] = array_merge( $rewrite, [ 'slug' => $post_type['rewrite_slug'] ] );
            }

            $taxonomy_keys = [];
            foreach ( $definition['taxonomies'] as $taxonomy ) {
                if ( ! empty( $taxonomy['enabled_default'] ) ) {
                    $taxonomy_keys[] = $taxonomy['key'];
                }
            }
            if ( $taxonomy_keys ) {
                $args['taxonomies'] = array_values( array_unique( array_merge( (array) ( $args['taxonomies'] ?? [] ), $taxonomy_keys ) ) );
            }

            if ( ! function_exists( 'post_type_exists' ) || ! post_type_exists( $post_type['key'] ) ) {
                register_post_type( $post_type['key'], $args );
            }
            $this->register_taxonomies( $definition );
        }
    }

    public function register_acf_groups(): void {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        foreach ( $this->registry->resolved_definitions() as $definition ) {
            if ( empty( $definition['enabled'] ) ) {
                continue;
            }
            foreach ( $definition['field_groups'] as $group ) {
                if ( empty( $group['enabled'] ) ) {
                    continue;
                }
                $acf = is_callable( $group['definition'] ) ? call_user_func( $group['definition'], $definition, $group ) : $group['definition'];
                if ( ! is_array( $acf ) || empty( $acf ) ) {
                    continue;
                }
                if ( empty( $acf['key'] ) && '' !== $group['group_key'] ) {
                    $acf['key'] = $group['group_key'];
                }
                if ( empty( $acf['title'] ) ) {
                    $acf['title'] = $group['label'];
                }
                $acf['location'] = $this->replace_post_type_placeholders( (array) ( $acf['location'] ?? [] ), (string) $definition['post_type']['key'] );
                acf_add_local_field_group( $acf );
            }
        }
    }

    /** @param array<string,mixed> $definition */
    private function register_taxonomies( array $definition ): void {
        foreach ( $definition['taxonomies'] as $taxonomy ) {
            if ( empty( $taxonomy['enabled_default'] ) || taxonomy_exists( $taxonomy['key'] ) ) {
                continue;
            }
            $args = $taxonomy['args'];
            $args['labels'] = array_merge( $this->taxonomy_labels( $taxonomy['singular'], $taxonomy['plural'] ), is_array( $args['labels'] ?? null ) ? $args['labels'] : [] );
            register_taxonomy( $taxonomy['key'], [ $definition['post_type']['key'] ], $args );
        }
    }

    /** @return array<string,string> */
    private function post_type_labels( string $singular, string $plural ): array {
        $lower_singular = strtolower( $singular );
        $lower_plural   = strtolower( $plural );
        return [
            'name' => $plural, 'singular_name' => $singular, 'menu_name' => $plural,
            'name_admin_bar' => $singular, 'add_new' => 'Add New', 'add_new_item' => 'Add New ' . $singular,
            'new_item' => 'New ' . $singular, 'edit_item' => 'Edit ' . $singular, 'view_item' => 'View ' . $singular,
            'all_items' => 'All ' . $plural, 'search_items' => 'Search ' . $plural,
            'parent_item_colon' => 'Parent ' . $singular . ':', 'not_found' => 'No ' . $lower_plural . ' found.',
            'not_found_in_trash' => 'No ' . $lower_plural . ' found in Trash.',
            'archives' => $singular . ' Archives', 'attributes' => $singular . ' Attributes',
            'insert_into_item' => 'Insert into ' . $lower_singular, 'uploaded_to_this_item' => 'Uploaded to this ' . $lower_singular,
            'filter_items_list' => 'Filter ' . $lower_plural . ' list', 'items_list_navigation' => $plural . ' list navigation',
            'items_list' => $plural . ' list', 'item_published' => $singular . ' published.',
            'item_updated' => $singular . ' updated.',
        ];
    }

    /** @return array<string,string> */
    private function taxonomy_labels( string $singular, string $plural ): array {
        return [
            'name' => $plural, 'singular_name' => $singular, 'search_items' => 'Search ' . $plural,
            'all_items' => 'All ' . $plural, 'edit_item' => 'Edit ' . $singular, 'update_item' => 'Update ' . $singular,
            'add_new_item' => 'Add New ' . $singular, 'new_item_name' => 'New ' . $singular . ' Name', 'menu_name' => $plural,
        ];
    }

    private function replace_post_type_placeholders( array $value, string $post_type ): array {
        array_walk_recursive(
            $value,
            static function ( &$item ) use ( $post_type ): void {
                if ( '@post_type' === $item ) {
                    $item = $post_type;
                }
            }
        );
        return $value;
    }
}
