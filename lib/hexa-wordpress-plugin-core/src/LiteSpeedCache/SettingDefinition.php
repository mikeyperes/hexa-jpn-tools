<?php

namespace Hexa\PluginCore\LiteSpeedCache;

/** One host-supplied LiteSpeed option/path expectation. */
final class SettingDefinition {
    /** @var array<string,mixed> */
    private array $definition;

    /** @param array<string,mixed> $definition */
    public function __construct( array $definition ) {
        $path = $definition['option_path'] ?? $definition['path'] ?? [];
        if ( is_string( $path ) ) {
            $path = '' === trim( $path ) ? [] : explode( '.', trim( $path, '.' ) );
        }
        $path = array_values( array_filter( array_map( 'strval', is_array( $path ) ? $path : [] ), static fn( string $part ): bool => '' !== $part ) );
        $option_name = trim( (string) ( $definition['option_name'] ?? $definition['option'] ?? '' ) );
        $id = self::clean_key( (string) ( $definition['id'] ?? $option_name . ( [] !== $path ? '_' . implode( '_', $path ) : '' ) ) );
        $this->definition = array_merge( [
            'id'          => $id,
            'label'       => '' !== $id ? ucwords( str_replace( [ '-', '_' ], ' ', $id ) ) : 'LiteSpeed setting',
            'description' => '',
            'option_name' => $option_name,
            'option_path' => $path,
            'expected'    => null,
            'cast'        => '',
        ], $definition, [ 'id' => $id, 'option_name' => $option_name, 'option_path' => $path ] );
    }

    public function id(): string { return (string) $this->definition['id']; }
    public function option_name(): string { return (string) $this->definition['option_name']; }
    /** @return list<string> */
    public function option_path(): array { return $this->definition['option_path']; }
    public function expected(): mixed { return $this->definition['expected']; }
    public function label(): string { return (string) $this->definition['label']; }
    public function description(): string { return (string) $this->definition['description']; }
    public function cast(): string { return (string) $this->definition['cast']; }
    public function get( string $key, mixed $default = null ): mixed { return array_key_exists( $key, $this->definition ) ? $this->definition[ $key ] : $default; }

    /** @return array<string,mixed> */
    public function to_array(): array { return $this->definition; }

    private static function clean_key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : ( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?: '' );
    }
}
