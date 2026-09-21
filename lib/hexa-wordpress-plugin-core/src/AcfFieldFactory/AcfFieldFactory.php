<?php

namespace Hexa\PluginCore\AcfFieldFactory;

final class AcfFieldFactory {
    /**
     * Builds a host-owned ACF field without deriving or changing its stable key.
     *
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public static function field( string $type, array $args = [] ): array {
        $field = $args;
        $field['key']   = (string) ( $args['key'] ?? '' );
        $field['label'] = (string) ( $args['label'] ?? '' );
        $field['name']  = (string) ( $args['name'] ?? '' );
        $field['type']  = self::clean_key( $type );

        if ( '' === $field['type'] ) {
            $field['type'] = 'text';
        }

        return $field;
    }

    public static function text( array $args = [] ): array {
        return self::field( 'text', $args );
    }

    public static function textarea( array $args = [] ): array {
        return self::field( 'textarea', array_merge( [ 'rows' => 4, 'new_lines' => '' ], $args ) );
    }

    public static function wysiwyg( array $args = [] ): array {
        return self::field( 'wysiwyg', array_merge( [ 'tabs' => 'all', 'toolbar' => 'full', 'media_upload' => 1 ], $args ) );
    }

    public static function url( array $args = [] ): array {
        return self::field( 'url', $args );
    }

    public static function email( array $args = [] ): array {
        return self::field( 'email', $args );
    }

    public static function number( array $args = [] ): array {
        return self::field( 'number', $args );
    }

    public static function date( array $args = [] ): array {
        return self::field( 'date_picker', array_merge( [ 'display_format' => 'F j, Y', 'return_format' => 'Y-m-d', 'first_day' => 1 ], $args ) );
    }

    public static function select( array $args = [] ): array {
        return self::field( 'select', array_merge( [ 'choices' => [], 'allow_null' => 0, 'multiple' => 0, 'ui' => 1, 'ajax' => 0, 'return_format' => 'value' ], $args ) );
    }

    public static function toggle( array $args = [] ): array {
        return self::field( 'true_false', array_merge( [ 'default_value' => 0, 'ui' => 1 ], $args ) );
    }

    public static function image( array $args = [] ): array {
        return self::field( 'image', array_merge( [ 'return_format' => 'id', 'preview_size' => 'medium', 'library' => 'all' ], $args ) );
    }

    public static function gallery( array $args = [] ): array {
        return self::field( 'gallery', array_merge( [ 'return_format' => 'id', 'preview_size' => 'medium', 'insert' => 'append', 'library' => 'all' ], $args ) );
    }

    public static function group( array $args = [] ): array {
        return self::field( 'group', array_merge( [ 'layout' => 'block', 'sub_fields' => [] ], $args ) );
    }

    public static function repeater( array $args = [] ): array {
        return self::field( 'repeater', array_merge( [ 'layout' => 'table', 'button_label' => 'Add Row', 'sub_fields' => [] ], $args ) );
    }

    public static function relationship( array $args = [] ): array {
        return self::field( 'relationship', array_merge( [ 'post_type' => [], 'taxonomy' => [], 'filters' => [ 'search', 'post_type', 'taxonomy' ], 'return_format' => 'id' ], $args ) );
    }

    public static function user( array $args = [] ): array {
        return self::field( 'user', array_merge( [ 'role' => [], 'allow_null' => 0, 'multiple' => 0, 'return_format' => 'id' ], $args ) );
    }

    public static function tab( array $args = [] ): array {
        return self::field( 'tab', array_merge( [ 'placement' => 'top', 'endpoint' => 0 ], $args ) );
    }

    public static function multiPostObject( array $args ): array {
        $post_types = $args["post_types"] ?? [ "post", "page" ];
        if ( ! is_array( $post_types ) || empty( $post_types ) ) {
            $post_types = [ "post", "page" ];
        }

        $field = [
            "key" => (string) ( $args["key"] ?? "" ),
            "label" => (string) ( $args["label"] ?? "" ),
            "name" => (string) ( $args["name"] ?? "" ),
            "type" => "post_object",
            "instructions" => (string) ( $args["instructions"] ?? "" ),
            "post_type" => array_values( array_filter( array_map( "sanitize_key", $post_types ) ) ),
            "return_format" => "id",
            "multiple" => 1,
            "allow_null" => 1,
            "ui" => 1,
        ];

        foreach ( [ "required", "wrapper", "conditional_logic" ] as $optional ) {
            if ( array_key_exists( $optional, $args ) ) {
                $field[ $optional ] = $args[ $optional ];
            }
        }

        return $field;
    }

    private static function clean_key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : ( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?: '' );
    }
}
