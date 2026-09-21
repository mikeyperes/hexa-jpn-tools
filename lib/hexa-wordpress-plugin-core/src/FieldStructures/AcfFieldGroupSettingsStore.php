<?php

namespace Hexa\PluginCore\FieldStructures;

final class AcfFieldGroupSettingsStore {
    public function __construct( private string $option_name ) {
    }

    /** @return array<string,int> */
    public function all(): array {
        $saved = function_exists( 'get_option' ) ? get_option( $this->option_name, [] ) : [];
        return is_array( $saved ) ? $saved : [];
    }

    /** @param array<string,mixed> $definition */
    public function enabled( array $definition ): bool {
        $saved = $this->all();
        $id = (string) $definition['id'];
        if ( array_key_exists( $id, $saved ) ) {
            return (bool) $saved[ $id ];
        }

        $legacy = (string) ( $definition['legacy_option'] ?? '' );
        if ( '' !== $legacy && function_exists( 'get_option' ) ) {
            return (bool) get_option( $legacy, (bool) $definition['enabled_default'] );
        }

        return (bool) $definition['enabled_default'];
    }

    /** @param array<string,mixed> $definition */
    public function save( array $definition, bool $enabled ): bool {
        $saved = $this->all();
        $saved[ (string) $definition['id'] ] = $enabled ? 1 : 0;
        if ( function_exists( 'update_option' ) ) {
            update_option( $this->option_name, $saved, false );
            $legacy = (string) ( $definition['legacy_option'] ?? '' );
            if ( '' !== $legacy ) {
                update_option( $legacy, $enabled ? 1 : 0, false );
            }
        }
        return $enabled;
    }

    public function option_name(): string {
        return $this->option_name;
    }
}
