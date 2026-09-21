<?php

namespace Hexa\PluginCore\FrontendForms;

/**
 * Canonical field contract for reusable public-facing WordPress forms.
 */
final class FieldSchema {
    public const TYPES = [
        'text',
        'textarea',
        'email',
        'url',
        'number',
        'date',
        'select',
        'radio',
        'checkbox',
        'wysiwyg',
        'image',
        'file',
    ];

    /**
     * @param array<int,array<string,mixed>> $schema
     * @return array<int,array<string,mixed>>
     */
    public static function normalize( array $schema, array $allowed_types = self::TYPES ): array {
        $allowed_types = array_values( array_intersect( self::TYPES, array_map( 'strtolower', $allowed_types ) ) );
        $normalized = [];
        $seen = [];

        foreach ( $schema as $field ) {
            $key = strtolower( trim( (string) ( $field['key'] ?? '' ) ) );
            $type = strtolower( trim( (string) ( $field['type'] ?? 'text' ) ) );
            if ( ! preg_match( '/^[a-z][a-z0-9_]{1,63}$/', $key ) || isset( $seen[ $key ] ) || ! in_array( $type, $allowed_types, true ) ) {
                continue;
            }

            $label = self::plain_text( (string) ( $field['label'] ?? '' ), 255 );
            $options = array_values( array_unique( array_filter( array_map(
                static fn ( mixed $option ): string => self::plain_text( (string) $option, 255 ),
                (array) ( $field['options'] ?? [] )
            ) ) ) );

            $seen[ $key ] = true;
            $normalized[] = [
                'key' => $key,
                'label' => "" !== $label ? $label : ucwords( str_replace( '_', ' ', $key ) ),
                'type' => $type,
                'required' => ! empty( $field['required'] ),
                'help' => self::plain_text( (string) ( $field['help'] ?? '' ), 1000 ),
                'placeholder' => self::plain_text( (string) ( $field['placeholder'] ?? '' ), 500 ),
                'options' => $options,
            ];
        }

        return $normalized;
    }

    public static function is_upload( string $type ): bool {
        return in_array( strtolower( $type ), [ 'image', 'file' ], true );
    }

    public static function is_rich_text( string $type ): bool {
        return 'wysiwyg' === strtolower( $type );
    }

    private static function plain_text( string $value, int $length ): string {
        $value = trim( strip_tags( $value ) );
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
    }
}
