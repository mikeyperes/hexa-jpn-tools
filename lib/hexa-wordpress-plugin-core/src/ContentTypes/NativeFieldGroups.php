<?php

namespace Hexa\PluginCore\ContentTypes;

use Hexa\PluginCore\Fields\Acf;

/**
 * Compatibility view of content-type field groups (introduced in 3.3.0).
 *
 * Since 3.4.0 registration, storage and editing without ACF belong to the
 * generic `Hexa\PluginCore\Fields` module. This class only keeps its 3.3.0
 * public API for hosts that already call it.
 *
 * @deprecated 3.4.0 Use Hexa\PluginCore\Fields\Acf and Fields\FieldGroups.
 */
final class NativeFieldGroups {
    public const INPUT = 'hexa_fields';

    private const SUPPORTED = [ 'text', 'textarea', 'email', 'url', 'number', 'radio', 'select', 'true_false' ];

    public function __construct( private ContentTypeRegistry $registry ) {}

    public static function active(): bool {
        return ! Acf::active();
    }

    public static function mode(): string {
        return Acf::mode();
    }

    /** No-op since 3.4.0; ContentTypeRegistry registers through Fields\FieldGroups. */
    public function register( int $priority = 0 ): void {
    }

    /**
     * Enabled field groups with their simple fields flattened to meta keys.
     *
     * @return array<int,array{post_type:string,key:string,title:string,fields:array<int,array<string,mixed>>}>
     */
    public function groups( string $post_type = '' ): array {
        $groups = [];
        foreach ( $this->registry->resolved_definitions() as $definition ) {
            $key = (string) ( $definition['post_type']['key'] ?? '' );
            if ( empty( $definition['enabled'] ) || '' === $key || ( '' !== $post_type && $post_type !== $key ) ) {
                continue;
            }
            foreach ( (array) ( $definition['field_groups'] ?? [] ) as $group ) {
                if ( empty( $group['enabled'] ) ) {
                    continue;
                }
                $acf = is_callable( $group['definition'] ?? null ) ? call_user_func( $group['definition'], $definition, $group ) : ( $group['definition'] ?? [] );
                if ( ! is_array( $acf ) ) {
                    continue;
                }
                $fields = $this->flatten( (array) ( $acf['fields'] ?? [] ) );
                if ( [] !== $fields ) {
                    $groups[] = [
                        'post_type' => $key,
                        'key'       => (string) ( $acf['key'] ?? $group['group_key'] ?? $group['id'] ?? '' ),
                        'title'     => (string) ( $group['label'] ?? $acf['title'] ?? '' ),
                        'fields'    => $fields,
                    ];
                }
            }
        }
        return $groups;
    }

    /**
     * @param array<int,mixed> $fields
     * @return array<int,array<string,mixed>>
     */
    private function flatten( array $fields, string $prefix = '', string $section = '' ): array {
        $flat = [];
        foreach ( $fields as $field ) {
            if ( ! is_array( $field ) ) {
                continue;
            }
            $name = (string) ( $field['name'] ?? '' );
            $type = (string) ( $field['type'] ?? 'text' );
            if ( 1 !== preg_match( '/^[A-Za-z0-9_\-]+$/', $name ) ) {
                continue;
            }
            if ( 'group' === $type ) {
                $flat = array_merge( $flat, $this->flatten( (array) ( $field['sub_fields'] ?? [] ), $prefix . $name . '_', (string) ( $field['label'] ?? $name ) ) );
                continue;
            }
            if ( in_array( $type, self::SUPPORTED, true ) ) {
                $flat[] = [ 'meta_key' => $prefix . $name, 'key' => (string) ( $field['key'] ?? '' ), 'label' => (string) ( $field['label'] ?? $name ), 'type' => $type, 'section' => $section ];
            }
        }
        return $flat;
    }
}
