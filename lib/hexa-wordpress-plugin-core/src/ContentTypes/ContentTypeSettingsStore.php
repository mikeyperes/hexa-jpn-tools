<?php

namespace Hexa\PluginCore\ContentTypes;

final class ContentTypeSettingsStore {
    private string $option_name;

    public function __construct( string $option_name ) {
        $this->option_name = $option_name;
    }

    /** @return array<string,array<string,mixed>> */
    public function all(): array {
        $settings = function_exists( 'get_option' ) ? get_option( $this->option_name, [] ) : [];
        return is_array( $settings ) ? $settings : [];
    }

    /** @param array<string,mixed> $definition @return array<string,mixed> */
    public function resolve( array $definition ): array {
        $settings = $this->all();
        $saved    = is_array( $settings[ $definition['id'] ] ?? null ) ? $settings[ $definition['id'] ] : [];
        $post     = $definition['post_type'];

        $legacy_enabled = (string) ( $definition['legacy_enabled_option'] ?? '' );
        $default_enabled = '' !== $legacy_enabled && function_exists( 'get_option' )
            ? (bool) get_option( $legacy_enabled, (bool) $definition['enabled_default'] )
            : (bool) $definition['enabled_default'];
        $definition['enabled'] = array_key_exists( 'enabled', $saved ) ? (bool) $saved['enabled'] : $default_enabled;
        $definition['post_type']['singular'] = $this->text( (string) ( $saved['singular'] ?? $post['singular'] ), (string) $post['singular'] );
        $definition['post_type']['plural'] = $this->text( (string) ( $saved['plural'] ?? $post['plural'] ), (string) $post['plural'] );
        $definition['post_type']['rewrite_slug'] = $this->slug( (string) ( $saved['rewrite_slug'] ?? $post['rewrite_slug'] ), (string) $post['rewrite_slug'] );

        $saved_groups = is_array( $saved['field_groups'] ?? null ) ? $saved['field_groups'] : [];
        foreach ( $definition['field_groups'] as &$group ) {
            $legacy_group = (string) ( $group['legacy_option'] ?? '' );
            $default_group = '' !== $legacy_group && function_exists( 'get_option' )
                ? (bool) get_option( $legacy_group, (bool) $group['enabled_default'] )
                : (bool) $group['enabled_default'];
            $group['enabled'] = array_key_exists( $group['id'], $saved_groups ) ? (bool) $saved_groups[ $group['id'] ] : $default_group;
        }
        unset( $group );

        return $definition;
    }

    /**
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    public function save( array $definition, array $values ): array {
        $settings = $this->all();
        $current  = $this->resolve( $definition );
        $groups   = [];
        $enabled_groups = array_map( 'strval', (array) ( $values['enabled_field_groups'] ?? [] ) );

        foreach ( $definition['field_groups'] as $group ) {
            $groups[ $group['id'] ] = in_array( (string) $group['id'], $enabled_groups, true ) ? 1 : 0;
        }

        $settings[ $definition['id'] ] = [
            'enabled'       => ! empty( $values['enabled'] ) ? 1 : 0,
            'singular'      => $this->text( (string) ( $values['singular'] ?? '' ), (string) $current['post_type']['singular'] ),
            'plural'        => $this->text( (string) ( $values['plural'] ?? '' ), (string) $current['post_type']['plural'] ),
            'rewrite_slug'  => $this->slug( (string) ( $values['rewrite_slug'] ?? '' ), (string) $current['post_type']['rewrite_slug'] ),
            'field_groups'  => $groups,
        ];

        if ( function_exists( 'update_option' ) ) {
            update_option( $this->option_name, $settings, false );
            $legacy_enabled = (string) ( $definition['legacy_enabled_option'] ?? '' );
            if ( '' !== $legacy_enabled ) {
                update_option( $legacy_enabled, ! empty( $settings[ $definition['id'] ]['enabled'] ) ? 1 : 0, false );
            }
            foreach ( $definition['field_groups'] as $group ) {
                $legacy_group = (string) ( $group['legacy_option'] ?? '' );
                if ( '' !== $legacy_group ) {
                    update_option( $legacy_group, ! empty( $groups[ $group['id'] ] ) ? 1 : 0, false );
                }
            }
        }

        return $this->resolve( $definition );
    }

    public function option_name(): string {
        return $this->option_name;
    }

    private function text( string $value, string $fallback ): string {
        $value = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : trim( strip_tags( $value ) );
        return '' !== $value ? $value : $fallback;
    }

    private function slug( string $value, string $fallback ): string {
        $value = function_exists( 'sanitize_title' ) ? sanitize_title( $value ) : trim( strtolower( preg_replace( '/[^a-zA-Z0-9\-]+/', '-', $value ) ?: '' ), '-' );
        return '' !== $value ? $value : $fallback;
    }
}
