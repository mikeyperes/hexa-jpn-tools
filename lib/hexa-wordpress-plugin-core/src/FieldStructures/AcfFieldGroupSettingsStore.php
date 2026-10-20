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

    /**
     * Who gets the client-friendly view of this group: a saved choice, else the
     * definition's `client_view`, else everyone below administrator.
     *
     * @param array<string,mixed> $definition
     * @return array{enabled:bool,roles:string|list<string>}
     */
    public function client_view( array $definition ): array {
        $saved = $this->all()['_client_view'][ (string) $definition['id'] ] ?? null;
        return \Hexa\PluginCore\Fields\Audience::normalize( is_array( $saved ) ? $saved : ( $definition['client_view'] ?? [] ) );
    }

    /** @param array<string,mixed> $definition @param array<string,mixed> $setting @return array{enabled:bool,roles:string|list<string>} */
    public function save_client_view( array $definition, array $setting ): array {
        $setting = \Hexa\PluginCore\Fields\Audience::normalize( $setting );
        $saved = $this->all();
        $views = is_array( $saved['_client_view'] ?? null ) ? $saved['_client_view'] : [];
        $views[ (string) $definition['id'] ] = $setting;
        $saved['_client_view'] = $views;
        if ( function_exists( 'update_option' ) ) {
            update_option( $this->option_name, $saved, false );
        }
        return $setting;
    }

    public function option_name(): string {
        return $this->option_name;
    }
}
