<?php

namespace Hexa\PluginCore\Fields;

/**
 * Field groups created in the ACF admin screen, stored as `acf-field-group`
 * and `acf-field` posts. Without ACF they are read from there as ACF reads
 * them, so their fields keep resolving, formatting and (when the group is
 * active) rendering. Groups registered in code with the same key win, as in
 * ACF. A trashed group is never listed, but its published fields still
 * resolve by key or name, as acf_get_field() finds them. Loaded once per
 * request, only when a native lookup needs it.
 */
final class Database {
    /** @var array<string,array<string,mixed>>|null */
    private static ?array $groups = null;

    /** In wp-admin, show active database groups on their screens. */
    public static function boot(): void {
        if ( function_exists( 'is_admin' ) && is_admin() ) {
            add_action( 'init', [ self::class, 'admin_screens' ], 20 );
        }
    }

    public static function admin_screens(): void {
        if ( ! Acf::active() && [] !== array_filter( self::groups(), static fn( array $group ): bool => ! empty( $group['active'] ) && empty( $group['trashed'] ) ) ) {
            AdminScreens::boot();
        }
    }

    /** @return array<string,array<string,mixed>> Active, disabled and trashed groups, keyed by group key, with their fields. */
    public static function groups(): array {
        if ( null !== self::$groups ) {
            return self::$groups;
        }
        self::$groups = [];
        global $wpdb;
        if ( ! is_object( $wpdb ) || ! isset( $wpdb->posts ) ) {
            return self::$groups;
        }
        $rows = $wpdb->get_results(
            "SELECT ID, post_type, post_status, post_title, post_name, post_excerpt, post_content, post_parent, menu_order FROM {$wpdb->posts}"
            . " WHERE ( post_type = 'acf-field-group' AND post_status IN ( 'publish', 'acf-disabled', 'trash' ) ) OR ( post_type = 'acf-field' AND post_status = 'publish' ) ORDER BY menu_order ASC, ID ASC"
        );
        $children = [];
        $groups = [];
        foreach ( (array) $rows as $row ) {
            if ( 'acf-field-group' === $row->post_type ) {
                $groups[] = $row;
            } else {
                $children[ (int) $row->post_parent ][] = $row;
            }
        }
        foreach ( $groups as $row ) {
            $group = array_merge(
                self::settings( (string) $row->post_content ),
                [
                    'ID'         => (int) $row->ID,
                    'key'        => (string) $row->post_name,
                    'title'      => (string) $row->post_title,
                    'menu_order' => (int) $row->menu_order,
                    'active'     => 'publish' === $row->post_status,
                    'trashed'    => 'trash' === $row->post_status,
                ]
            );
            $group['fields'] = self::fields( $children, (int) $row->ID, $group['key'] );
            self::$groups[ $group['key'] ] = $group;
        }
        return self::$groups;
    }

    /** Forget the loaded groups (tests, or after importing groups). */
    public static function reset(): void {
        self::$groups = null;
    }

    /**
     * @param array<int,array<int,object>> $children
     * @return array<int,array<string,mixed>>
     */
    private static function fields( array $children, int $parent_id, string $parent_key ): array {
        $fields = [];
        foreach ( $children[ $parent_id ] ?? [] as $row ) {
            $field = array_merge(
                self::settings( (string) $row->post_content ),
                [
                    'ID'         => (int) $row->ID,
                    'key'        => (string) $row->post_name,
                    'label'      => (string) $row->post_title,
                    'name'       => (string) $row->post_excerpt,
                    'menu_order' => (int) $row->menu_order,
                    'parent'     => $parent_key,
                ]
            );
            $subs = self::fields( $children, (int) $row->ID, $field['key'] );
            if ( 'flexible_content' === ( $field['type'] ?? '' ) ) {
                // Layout sub fields name their layout in `parent_layout`.
                foreach ( (array) ( $field['layouts'] ?? [] ) as $i => $layout ) {
                    if ( is_array( $layout ) ) {
                        $field['layouts'][ $i ]['sub_fields'] = array_values( array_filter( $subs, static fn( array $sub ): bool => ( $sub['parent_layout'] ?? '' ) === ( $layout['key'] ?? '' ) ) );
                    }
                }
            } elseif ( [] !== $subs ) {
                $field['sub_fields'] = $subs;
            }
            $fields[] = $field;
        }
        return $fields;
    }

    /** @return array<string,mixed> */
    private static function settings( string $content ): array {
        $settings = function_exists( 'maybe_unserialize' ) ? maybe_unserialize( $content ) : @unserialize( $content ); // phpcs:ignore
        return is_array( $settings ) ? $settings : [];
    }
}
