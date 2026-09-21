<?php

namespace Hexa\PluginCore\EntitySources;

final class EntityFieldInspector {
    /** @param array<string,mixed> $entity @return array<int,array<string,mixed>> */
    public function inspect( array $entity ): array {
        $groups = [ $this->wordpress_group( $entity ) ];
        if ( ! function_exists( 'get_field_objects' ) ) {
            return $groups;
        }

        $objects = get_field_objects( $entity['context'], false, true );
        if ( ! is_array( $objects ) ) {
            return $groups;
        }

        $acf_groups = [];
        foreach ( $objects as $field ) {
            if ( ! is_array( $field ) || empty( $field['name'] ) ) {
                continue;
            }
            $parent = (string) ( $field['parent'] ?? 'acf-other' );
            if ( ! isset( $acf_groups[ $parent ] ) ) {
                $group = function_exists( 'acf_get_field_group' ) ? acf_get_field_group( $parent ) : null;
                $acf_groups[ $parent ] = [
                    'key' => $parent, 'label' => is_array( $group ) ? (string) ( $group['title'] ?? 'Additional Fields' ) : 'Additional Fields',
                    'source' => $this->field_source( is_array( $group ) ? $group : [], $field ), 'fields' => [],
                ];
            }
            $name = (string) $field['name'];
            $acf_groups[ $parent ]['fields'][] = [
                'key' => (string) ( $field['key'] ?? '' ), 'name' => $name,
                'label' => (string) ( $field['label'] ?? $name ), 'type' => (string) ( $field['type'] ?? '' ),
                'value' => $this->display_value( $name, $field['value'] ?? null ),
                'set' => $this->has_value( $field['value'] ?? null ),
            ];
        }

        return array_merge( $groups, array_values( $acf_groups ) );
    }

    /** @param array<string,mixed> $entity @return array<string,mixed> */
    private function wordpress_group( array $entity ): array {
        $fields = [];
        if ( 'user' === $entity['kind'] && $entity['object'] instanceof \WP_User ) {
            $user = $entity['object'];
            $values = [
                'display_name' => $user->display_name, 'first_name' => $user->first_name, 'last_name' => $user->last_name,
                'user_login' => $user->user_login, 'user_email' => $user->user_email, 'roles' => implode( ', ', (array) $user->roles ),
                'website' => $user->user_url, 'description' => $user->description,
            ];
        } else {
            $post = $entity['object'];
            $values = [
                'title' => $entity['name'], 'post_type' => $entity['post_type'], 'status' => $entity['status'],
                'slug' => $post instanceof \WP_Post ? $post->post_name : '',
                'excerpt' => $post instanceof \WP_Post ? $post->post_excerpt : '',
            ];
        }
        foreach ( $values as $name => $value ) {
            $fields[] = [ 'key' => $name, 'name' => $name, 'label' => ucwords( str_replace( '_', ' ', $name ) ), 'type' => 'wordpress', 'value' => $this->display_value( $name, $value ), 'set' => $this->has_value( $value ) ];
        }
        return [ 'key' => 'wordpress', 'label' => 'WordPress Identity', 'source' => 'WordPress', 'fields' => $fields ];
    }

    /** @param array<string,mixed> $group @param array<string,mixed> $field */
    private function field_source( array $group, array $field ): string {
        $source = (string) ( $group['_hws_source']['name'] ?? $field['_hws_source']['name'] ?? '' );
        return '' !== $source ? $source : 'ACF';
    }

    private function display_value( string $name, mixed $value ): string {
        if ( preg_match( '/password|secret|token|api[_-]?key|credential/i', $name ) ) {
            return '[protected]';
        }
        if ( is_bool( $value ) ) {
            return $value ? 'Yes' : 'No';
        }
        if ( null === $value || false === $value || '' === $value || [] === $value ) {
            return 'Not set';
        }
        if ( is_object( $value ) ) {
            if ( isset( $value->display_name ) ) {
                return (string) $value->display_name;
            }
            if ( isset( $value->post_title ) ) {
                return (string) $value->post_title;
            }
            return get_class( $value );
        }
        if ( is_array( $value ) ) {
            $encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $this->compact_array( $value ), JSON_UNESCAPED_SLASHES ) : json_encode( $this->compact_array( $value ) );
            return $this->limit( (string) $encoded );
        }
        return $this->limit( trim( strip_tags( (string) $value ) ) );
    }

    private function compact_array( array $value ): array {
        $out = [];
        foreach ( array_slice( $value, 0, 20, true ) as $key => $item ) {
            if ( is_scalar( $item ) || null === $item ) {
                $out[ $key ] = $item;
            } elseif ( is_array( $item ) ) {
                $out[ $key ] = $this->compact_array( $item );
            } elseif ( is_object( $item ) ) {
                $out[ $key ] = $item->display_name ?? $item->post_title ?? get_class( $item );
            }
        }
        return $out;
    }

    private function limit( string $value ): string {
        return strlen( $value ) > 500 ? substr( $value, 0, 497 ) . '...' : $value;
    }

    private function has_value( mixed $value ): bool {
        return ! ( null === $value || false === $value || '' === $value || [] === $value );
    }
}
