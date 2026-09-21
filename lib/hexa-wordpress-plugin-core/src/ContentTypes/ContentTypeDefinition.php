<?php

namespace Hexa\PluginCore\ContentTypes;

use InvalidArgumentException;

final class ContentTypeDefinition {
    /** @param array<string,mixed> $definition @return array<string,mixed> */
    public static function normalize( array $definition ): array {
        $id       = self::key( (string) ( $definition['id'] ?? '' ) );
        $post_type = is_array( $definition['post_type'] ?? null ) ? $definition['post_type'] : [];
        $key      = self::key( (string) ( $post_type['key'] ?? $id ) );

        if ( '' === $id || '' === $key ) {
            throw new InvalidArgumentException( 'Content type definitions require an id and post_type key.' );
        }

        if ( strlen( $key ) > 20 ) {
            throw new InvalidArgumentException( 'WordPress post type keys cannot exceed 20 characters: ' . $key );
        }

        $singular = self::text( (string) ( $post_type['singular'] ?? self::title( $key ) ) );
        $plural   = self::text( (string) ( $post_type['plural'] ?? $singular . 's' ) );
        $slug     = self::slug( (string) ( $post_type['rewrite_slug'] ?? $key ) );
        $registration_mode = 'external' === (string) ( $definition['registration_mode'] ?? '' ) ? 'external' : 'owned';

        return [
            'id'              => $id,
            'owner'           => self::text( (string) ( $definition['owner'] ?? '' ) ),
            'description'     => self::text( (string) ( $definition['description'] ?? '' ) ),
            'registration_mode' => $registration_mode,
            'enabled_default' => ! array_key_exists( 'enabled_default', $definition ) || (bool) $definition['enabled_default'],
            'legacy_enabled_option' => self::key( (string) ( $definition['legacy_enabled_option'] ?? '' ) ),
            'post_type'       => [
                'key'          => $key,
                'singular'     => '' !== $singular ? $singular : self::title( $key ),
                'plural'       => '' !== $plural ? $plural : self::title( $key ) . 's',
                'rewrite_slug' => '' !== $slug ? $slug : $key,
                'args'         => is_array( $post_type['args'] ?? null ) ? $post_type['args'] : [],
            ],
            'taxonomies'      => self::normalize_taxonomies( (array) ( $definition['taxonomies'] ?? [] ) ),
            'field_groups'    => self::normalize_field_groups( (array) ( $definition['field_groups'] ?? [] ) ),
        ];
    }

    /** @param array<int|string,mixed> $groups @return array<int,array<string,mixed>> */
    private static function normalize_field_groups( array $groups ): array {
        $normalized = [];
        foreach ( $groups as $index => $group ) {
            if ( ! is_array( $group ) ) {
                continue;
            }
            if ( ! isset( $group['id'] ) && is_string( $index ) ) {
                $group['id'] = $index;
            }
            $id = self::key( (string) ( $group['id'] ?? '' ) );
            if ( '' === $id ) {
                continue;
            }
            $normalized[] = [
                'id'              => $id,
                'label'           => self::text( (string) ( $group['label'] ?? self::title( $id ) ) ),
                'description'     => self::text( (string) ( $group['description'] ?? '' ) ),
                'group_key'       => (string) ( $group['group_key'] ?? '' ),
                'enabled_default' => ! array_key_exists( 'enabled_default', $group ) || (bool) $group['enabled_default'],
                'legacy_option'   => self::key( (string) ( $group['legacy_option'] ?? '' ) ),
                'definition'      => $group['definition'] ?? [],
                'fields'          => self::text_list( $group['fields'] ?? [] ),
                'dependencies'    => self::text_list( $group['dependencies'] ?? [] ),
            ];
        }
        return $normalized;
    }

    /** @param array<int|string,mixed> $taxonomies @return array<int,array<string,mixed>> */
    private static function normalize_taxonomies( array $taxonomies ): array {
        $normalized = [];
        foreach ( $taxonomies as $index => $taxonomy ) {
            if ( is_string( $taxonomy ) ) {
                $taxonomy = [ 'key' => $taxonomy ];
            }
            if ( ! is_array( $taxonomy ) ) {
                continue;
            }
            if ( ! isset( $taxonomy['key'] ) && is_string( $index ) ) {
                $taxonomy['key'] = $index;
            }
            $key = self::key( (string) ( $taxonomy['key'] ?? '' ) );
            if ( '' === $key ) {
                continue;
            }
            $singular = self::text( (string) ( $taxonomy['singular'] ?? self::title( $key ) ) );
            $normalized[] = [
                'key'              => $key,
                'singular'         => $singular,
                'plural'           => self::text( (string) ( $taxonomy['plural'] ?? $singular . 's' ) ),
                'enabled_default'  => ! array_key_exists( 'enabled_default', $taxonomy ) || (bool) $taxonomy['enabled_default'],
                'args'             => is_array( $taxonomy['args'] ?? null ) ? $taxonomy['args'] : [],
            ];
        }
        return $normalized;
    }

    /** @return array<int,string> */
    private static function text_list( mixed $items ): array {
        if ( is_string( $items ) ) {
            $items = preg_split( '/\r\n|\r|\n/', $items ) ?: [];
        }
        if ( ! is_array( $items ) ) {
            return [];
        }
        return array_values( array_filter( array_map( static fn( mixed $item ): string => is_scalar( $item ) ? self::text( (string) $item ) : '', $items ) ) );
    }

    private static function key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', $value ) ?: '' );
    }

    private static function slug( string $value ): string {
        return function_exists( 'sanitize_title' ) ? sanitize_title( $value ) : trim( strtolower( preg_replace( '/[^a-zA-Z0-9\-]+/', '-', $value ) ?: '' ), '-' );
    }

    private static function text( string $value ): string {
        return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : trim( strip_tags( $value ) );
    }

    private static function title( string $value ): string {
        return ucwords( str_replace( [ '-', '_' ], ' ', $value ) );
    }
}
