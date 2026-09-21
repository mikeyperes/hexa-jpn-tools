<?php

namespace Hexa\PluginCore\Taxonomies;

use InvalidArgumentException;

final class TaxonomyDefinition {
    /** @param array<string,mixed> $definition @return array<string,mixed> */
    public static function normalize( array $definition ): array {
        $id = self::key( (string) ( $definition['id'] ?? $definition['taxonomy'] ?? '' ) );
        $taxonomy = self::key( (string) ( $definition['taxonomy'] ?? $id ) );
        if ( '' === $id || '' === $taxonomy ) {
            throw new InvalidArgumentException( 'Taxonomy definitions require an id and taxonomy key.' );
        }
        if ( strlen( $taxonomy ) > 32 ) {
            throw new InvalidArgumentException( 'WordPress taxonomy keys cannot exceed 32 characters: ' . $taxonomy );
        }

        return [
            'id'           => $id,
            'taxonomy'     => $taxonomy,
            'label'        => self::text( (string) ( $definition['label'] ?? self::title( $taxonomy ) ) ),
            'description'  => self::text( (string) ( $definition['description'] ?? '' ) ),
            'owner'        => self::text( (string) ( $definition['owner'] ?? '' ) ),
            'object_types' => $definition['object_types'] ?? [],
            'args'         => $definition['args'] ?? [],
            'enabled'      => $definition['enabled'] ?? true,
        ];
    }

    private static function key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', $value ) ?: '' );
    }

    private static function text( string $value ): string {
        return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : trim( strip_tags( $value ) );
    }

    private static function title( string $value ): string {
        return ucwords( str_replace( [ '-', '_' ], ' ', $value ) );
    }
}
