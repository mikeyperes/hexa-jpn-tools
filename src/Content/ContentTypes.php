<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Content;

final class ContentTypes
{
    public function register(): void
    {
        add_action('init', [$this, 'registerTypes'], 20);
    }

    public function registerTypes(): void
    {
        register_post_type('event', $this->postTypeArgs('Events', 'Event', true));
        register_post_type('service', $this->postTypeArgs('Services', 'Service', false));

        register_taxonomy('area', ['event'], [
            'labels' => [
                'name'          => __('Areas', 'hexa-jpn-tools'),
                'singular_name' => __('Area', 'hexa-jpn-tools'),
                'menu_name'     => __('Areas', 'hexa-jpn-tools'),
                'all_items'     => __('All Areas', 'hexa-jpn-tools'),
                'edit_item'     => __('Edit Area', 'hexa-jpn-tools'),
                'view_item'     => __('View Area', 'hexa-jpn-tools'),
                'update_item'   => __('Update Area', 'hexa-jpn-tools'),
                'add_new_item'  => __('Add New Area', 'hexa-jpn-tools'),
                'new_item_name' => __('New Area Name', 'hexa-jpn-tools'),
                'search_items'  => __('Search Areas', 'hexa-jpn-tools'),
                'not_found'     => __('No areas found', 'hexa-jpn-tools'),
                'no_terms'      => __('No areas', 'hexa-jpn-tools'),
                'back_to_items' => __('← Go to areas', 'hexa-jpn-tools'),
            ],
            'public'             => true,
            'publicly_queryable' => true,
            'hierarchical'       => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'rest_namespace'     => 'wp/v2',
            'rewrite'            => ['slug' => 'area', 'with_front' => true, 'hierarchical' => false],
            'query_var'          => 'area',
            'show_admin_column'  => true,
        ]);
    }

    private function postTypeArgs(string $plural, string $singular, bool $author): array
    {
        $slug = strtolower($singular);
        $supports = ['title', 'editor', 'thumbnail', 'custom-fields'];
        if ($author) {
            $supports[] = 'author';
        }

        return [
            'labels' => [
                'name'               => __($plural, 'hexa-jpn-tools'),
                'singular_name'      => __($singular, 'hexa-jpn-tools'),
                'menu_name'          => __($plural, 'hexa-jpn-tools'),
                'all_items'          => sprintf(__('All %s', 'hexa-jpn-tools'), $plural),
                'edit_item'          => sprintf(__('Edit %s', 'hexa-jpn-tools'), $singular),
                'view_item'          => sprintf(__('View %s', 'hexa-jpn-tools'), $singular),
                'add_new_item'       => sprintf(__('Add New %s', 'hexa-jpn-tools'), $singular),
                'add_new'            => sprintf(__('Add New %s', 'hexa-jpn-tools'), $singular),
                'new_item'           => sprintf(__('New %s', 'hexa-jpn-tools'), $singular),
                'search_items'       => sprintf(__('Search %s', 'hexa-jpn-tools'), $plural),
                'not_found'          => sprintf(__('No %s found', 'hexa-jpn-tools'), strtolower($plural)),
                'not_found_in_trash' => sprintf(__('No %s found in Trash', 'hexa-jpn-tools'), strtolower($plural)),
            ],
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'rest_namespace'     => 'wp/v2',
            'hierarchical'       => false,
            'supports'           => $supports,
            'taxonomies'         => ['category', 'post_tag'],
            'has_archive'        => false,
            'rewrite'            => ['slug' => $slug, 'with_front' => true, 'feeds' => false, 'pages' => true],
            'query_var'          => $slug,
            'menu_icon'          => 'dashicons-admin-post',
        ];
    }
}
