<?php

namespace Hexa\PluginCore\LiteSpeedCache;

/** Host-owned, array-driven LiteSpeed settings profile. */
final class Profile {
    private string $id;
    private string $label;
    private string $description;
    /** @var list<SettingDefinition> */
    private array $settings = [];

    /** @param array<string,mixed> $definition */
    public function __construct( array $definition ) {
        $this->id = self::clean_key( (string) ( $definition['id'] ?? 'profile' ) ) ?: 'profile';
        $this->label = trim( (string) ( $definition['label'] ?? ucwords( str_replace( [ '-', '_' ], ' ', $this->id ) ) ) );
        $this->description = trim( (string) ( $definition['description'] ?? '' ) );
        $seen = [];
        foreach ( (array) ( $definition['settings'] ?? [] ) as $key => $setting ) {
            if ( $setting instanceof SettingDefinition ) {
                $object = $setting;
            } elseif ( is_array( $setting ) ) {
                if ( is_string( $key ) && ! isset( $setting['id'] ) ) {
                    $setting['id'] = $key;
                }
                $object = new SettingDefinition( $setting );
            } else {
                continue;
            }
            if ( '' === $object->id() || '' === $object->option_name() || isset( $seen[ $object->id() ] ) ) {
                continue;
            }
            $this->settings[] = $object;
            $seen[ $object->id() ] = true;
        }
    }

    public function id(): string { return $this->id; }
    public function label(): string { return $this->label; }
    public function description(): string { return $this->description; }
    /** @return list<SettingDefinition> */
    public function settings(): array { return $this->settings; }

    /** @return array<string,mixed> */
    public function to_array(): array {
        return [ 'id' => $this->id, 'label' => $this->label, 'description' => $this->description, 'settings' => array_map( static fn( SettingDefinition $setting ): array => $setting->to_array(), $this->settings ) ];
    }

    private static function clean_key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : ( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?: '' );
    }
}
