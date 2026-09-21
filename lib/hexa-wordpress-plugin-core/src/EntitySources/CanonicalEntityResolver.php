<?php

namespace Hexa\PluginCore\EntitySources;

final class CanonicalEntityResolver {
    public const TYPES = [ 'auto', 'person', 'organization', 'publication' ];

    /** @return array<string,mixed> */
    public static function settings( string $option_name = 'hws_primary_entity' ): array {
        $saved = function_exists( 'get_option' ) ? get_option( $option_name, [] ) : [];
        $saved = is_array( $saved ) ? $saved : [];
        $type  = self::key( (string) ( $saved['entity_type'] ?? 'auto' ) );
        return [
            'enabled'      => ! empty( $saved['enabled'] ),
            'source'       => self::key( (string) ( $saved['source'] ?? '' ) ),
            'kind'         => in_array( (string) ( $saved['kind'] ?? '' ), [ 'user', 'post' ], true ) ? (string) $saved['kind'] : '',
            'post_type'    => self::key( (string) ( $saved['post_type'] ?? '' ) ),
            'object_id'    => max( 0, (int) ( $saved['object_id'] ?? 0 ) ),
            'entity_type'  => in_array( $type, self::TYPES, true ) ? $type : 'auto',
            'migrated_from'=> self::text( (string) ( $saved['migrated_from'] ?? '' ) ),
        ];
    }

    /** @param array<string,mixed>|null $settings @return array<string,mixed>|null */
    public static function resolve( ?array $settings = null, string $option_name = 'hws_primary_entity' ): ?array {
        $settings = $settings ?? self::settings( $option_name );
        if ( empty( $settings['enabled'] ) || empty( $settings['object_id'] ) ) {
            return null;
        }

        $id = (int) $settings['object_id'];
        if ( 'user' === $settings['kind'] ) {
            $user = function_exists( 'get_userdata' ) ? get_userdata( $id ) : false;
            if ( ! $user instanceof \WP_User ) {
                return null;
            }
            $context = 'user_' . $id;
            $entity_type = self::resolved_type( $settings, $context, 'person' );
            return [
                'id' => $id, 'kind' => 'user', 'source' => $settings['source'], 'post_type' => '',
                'entity_type' => $entity_type, 'name' => (string) $user->display_name,
                'subtitle' => implode( ', ', (array) $user->roles ), 'status' => 'active',
                'edit_url' => function_exists( 'get_edit_user_link' ) ? (string) get_edit_user_link( $id ) : '',
                'view_url' => function_exists( 'get_author_posts_url' ) ? (string) get_author_posts_url( $id ) : '',
                'image_url' => function_exists( 'get_avatar_url' ) ? (string) get_avatar_url( $id, [ 'size' => 192 ] ) : '',
                'context' => $context, 'object' => $user, 'settings' => $settings,
                'attached_user_id' => $id, 'attached_user_name' => (string) $user->display_name,
                'attached_user_edit_url' => function_exists( 'get_edit_user_link' ) ? (string) get_edit_user_link( $id ) : '',
            ];
        }

        if ( 'post' === $settings['kind'] ) {
            $post = function_exists( 'get_post' ) ? get_post( $id ) : null;
            if ( ! $post instanceof \WP_Post || ( $settings['post_type'] && $post->post_type !== $settings['post_type'] ) ) {
                return null;
            }
            $fallback = 'organization' === $post->post_type ? 'organization' : ( 'profile' === $post->post_type ? 'person' : 'organization' );
            $entity_type = self::resolved_type( $settings, $id, $fallback );
            $attached_user_id = self::attached_user_id( $post );
            $attached_user = $attached_user_id > 0 && function_exists( 'get_userdata' ) ? get_userdata( $attached_user_id ) : false;
            return [
                'id' => $id, 'kind' => 'post', 'source' => $settings['source'], 'post_type' => $post->post_type,
                'entity_type' => $entity_type, 'name' => (string) get_the_title( $id ),
                'subtitle' => self::post_type_label( $post->post_type ), 'status' => $post->post_status,
                'edit_url' => (string) get_edit_post_link( $id, 'raw' ), 'view_url' => (string) get_permalink( $id ),
                'image_url' => (string) ( get_the_post_thumbnail_url( $id, 'medium' ) ?: '' ),
                'context' => $id, 'object' => $post, 'settings' => $settings,
                'attached_user_id' => $attached_user_id,
                'attached_user_name' => $attached_user instanceof \WP_User ? (string) $attached_user->display_name : '',
                'attached_user_edit_url' => $attached_user_id > 0 && function_exists( 'get_edit_user_link' ) ? (string) get_edit_user_link( $attached_user_id ) : '',
            ];
        }

        return null;
    }

    public static function field( array $entity, string $field, mixed $default = null ): mixed {
        $field = self::key( $field );
        if ( '' === $field || empty( $entity['id'] ) || empty( $entity['kind'] ) ) {
            return $default;
        }

        $context = $entity['context'] ?? ( 'user' === $entity['kind'] ? 'user_' . (int) $entity['id'] : (int) $entity['id'] );
        if ( function_exists( 'get_field' ) ) {
            $value = get_field( $field, $context );
            if ( self::has_value( $value ) ) {
                return $value;
            }
        }

        if ( 'user' === $entity['kind'] ) {
            $user = $entity['object'] ?? ( function_exists( 'get_userdata' ) ? get_userdata( (int) $entity['id'] ) : false );
            if ( $user instanceof \WP_User ) {
                $properties = [
                    'name' => 'display_name', 'display_name' => 'display_name', 'first_name' => 'first_name',
                    'last_name' => 'last_name', 'description' => 'description', 'biography' => 'description',
                    'website' => 'user_url', 'url' => 'user_url', 'email' => 'user_email',
                ];
                $property = $properties[ $field ] ?? '';
                if ( '' !== $property && self::has_value( $user->{$property} ?? null ) ) {
                    return $user->{$property};
                }
            }
            $value = function_exists( 'get_user_meta' ) ? get_user_meta( (int) $entity['id'], $field, true ) : null;
            return self::has_value( $value ) ? $value : $default;
        }

        $post = $entity['object'] ?? ( function_exists( 'get_post' ) ? get_post( (int) $entity['id'] ) : null );
        if ( $post instanceof \WP_Post ) {
            $properties = [
                'name' => 'post_title', 'title' => 'post_title', 'description' => 'post_excerpt',
                'summary' => 'post_excerpt', 'slug' => 'post_name', 'content' => 'post_content',
            ];
            $property = $properties[ $field ] ?? '';
            if ( '' !== $property && self::has_value( $post->{$property} ?? null ) ) {
                return $post->{$property};
            }
        }
        $value = function_exists( 'get_post_meta' ) ? get_post_meta( (int) $entity['id'], $field, true ) : null;
        return self::has_value( $value ) ? $value : $default;
    }

    public static function first_field( array $entity, array $fields, mixed $default = null ): mixed {
        foreach ( $fields as $field ) {
            $value = self::field( $entity, (string) $field, null );
            if ( self::has_value( $value ) ) {
                return $value;
            }
        }
        return $default;
    }

    /** @param array<string,mixed> $settings */
    private static function resolved_type( array $settings, string|int $context, string $fallback ): string {
        if ( isset( $settings['entity_type'] ) && 'auto' !== $settings['entity_type'] && in_array( $settings['entity_type'], self::TYPES, true ) ) {
            return (string) $settings['entity_type'];
        }
        $field_type = function_exists( 'get_field' ) ? self::key( (string) get_field( 'entity_type', $context ) ) : '';
        return in_array( $field_type, [ 'person', 'organization', 'publication' ], true ) ? $field_type : $fallback;
    }

    private static function post_type_label( string $post_type ): string {
        $object = function_exists( 'get_post_type_object' ) ? get_post_type_object( $post_type ) : null;
        return $object && isset( $object->labels->singular_name ) ? (string) $object->labels->singular_name : ucwords( str_replace( [ '-', '_' ], ' ', $post_type ) );
    }

    private static function attached_user_id( \WP_Post $post ): int {
        foreach ( [ 'attached_user', 'profile_user', 'person_user', 'author_user', 'founder_user', 'verified_user', 'user' ] as $field ) {
            $value = function_exists( 'get_field' ) ? get_field( $field, $post->ID ) : null;
            if ( null === $value || false === $value || '' === $value ) {
                $value = function_exists( 'get_post_meta' ) ? get_post_meta( $post->ID, $field, true ) : null;
            }
            $id = self::object_id( $value );
            if ( $id > 0 && function_exists( 'get_userdata' ) && get_userdata( $id ) instanceof \WP_User ) {
                return $id;
            }
        }
        $author_id = isset( $post->post_author ) ? (int) $post->post_author : 0;
        return $author_id > 0 && function_exists( 'get_userdata' ) && get_userdata( $author_id ) instanceof \WP_User ? $author_id : 0;
    }

    private static function object_id( mixed $value ): int {
        if ( $value instanceof \WP_User || $value instanceof \WP_Post ) return (int) $value->ID;
        if ( is_object( $value ) && isset( $value->ID ) ) return (int) $value->ID;
        if ( is_array( $value ) ) return (int) ( $value['ID'] ?? $value['id'] ?? 0 );
        return is_numeric( $value ) ? (int) $value : 0;
    }

    private static function has_value( mixed $value ): bool {
        return ! ( null === $value || false === $value || '' === $value || [] === $value );
    }

    private static function key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', $value ) ?: '' );
    }

    private static function text( string $value ): string {
        return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : trim( strip_tags( $value ) );
    }
}
