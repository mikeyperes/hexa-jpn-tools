<?php

namespace Hexa\PluginCore\EntitySources;

final class PrimaryEntityManager {
    private array $config;

    public function __construct( array $config = [] ) {
        $this->config = array_merge(
            [
                'entity_option' => 'hws_primary_entity', 'site_type_option' => 'hws_site_type',
                'site_types' => [], 'sources' => [], 'migration_flag' => 'hws_primary_entity_migrated_v1',
                'site_entity_types' => [], 'allow_entity_type_selection' => true,
                'allow_empty_site_type' => false, 'site_type_placeholder' => 'Select website type',
                'legacy_resolvers' => [], 'render_args' => [],
            ],
            $config
        );
    }

    /** @return array<string,string> */
    public function site_types(): array {
        return (array) $this->config['site_types'];
    }

    /** @return array<string,array<string,mixed>> */
    public function sources(): array {
        $sources = [];
        foreach ( (array) $this->config['sources'] as $id => $source ) {
            if ( ! is_array( $source ) ) {
                continue;
            }
            $id = sanitize_key( is_string( $id ) ? $id : (string) ( $source['id'] ?? '' ) );
            $kind = in_array( (string) ( $source['kind'] ?? '' ), [ 'user', 'post' ], true ) ? (string) $source['kind'] : '';
            if ( '' === $id || '' === $kind ) {
                continue;
            }
            $sources[ $id ] = [
                'id' => $id, 'label' => sanitize_text_field( (string) ( $source['label'] ?? $id ) ),
                'kind' => $kind, 'post_type' => sanitize_key( (string) ( $source['post_type'] ?? '' ) ),
                'description' => sanitize_text_field( (string) ( $source['description'] ?? '' ) ),
            ];
        }
        return $sources;
    }

    public function site_type(): string {
        $default = ! empty( $this->config['allow_empty_site_type'] ) ? '' : ( array_key_first( $this->site_types() ) ?: 'other' );
        $value = sanitize_key( (string) get_option( (string) $this->config['site_type_option'], $default ) );
        if ( '' === $value && ! empty( $this->config['allow_empty_site_type'] ) ) {
            return '';
        }
        return array_key_exists( $value, $this->site_types() ) ? $value : $default;
    }

    public function entity_type_for_site_type( ?string $site_type = null ): string {
        $site_type = sanitize_key( $site_type ?? $this->site_type() );
        $map = (array) $this->config['site_entity_types'];
        $mapped = sanitize_key( (string) ( $map[ $site_type ] ?? 'auto' ) );
        return in_array( $mapped, CanonicalEntityResolver::TYPES, true ) ? $mapped : 'auto';
    }

    public function entity_type_label( ?string $site_type = null ): string {
        if ( '' === sanitize_key( $site_type ?? $this->site_type() ) ) {
            return 'Not set';
        }
        $type = $this->entity_type_for_site_type( $site_type );
        return 'auto' === $type ? 'Automatic' : ucfirst( $type );
    }

    /** @return array<string,mixed> */
    public function settings(): array {
        $settings = CanonicalEntityResolver::settings( (string) $this->config['entity_option'] );
        $derived_type = $this->entity_type_for_site_type();
        if ( 'auto' !== $derived_type ) {
            $settings['entity_type'] = $derived_type;
        }
        return $settings;
    }

    /** @return array<string,mixed>|null */
    public function resolve(): ?array {
        return CanonicalEntityResolver::resolve( $this->settings(), (string) $this->config['entity_option'] );
    }

    /** @param array<string,mixed> $values @return array<string,mixed> */
    public function save( array $values ): array {
        $site_type = sanitize_key( (string) ( $values['site_type'] ?? '' ) );
        $empty_allowed = ! empty( $this->config['allow_empty_site_type'] );
        if ( ! ( $empty_allowed && '' === $site_type ) && ! array_key_exists( $site_type, $this->site_types() ) ) {
            $site_type = $this->site_type();
        }

        $sources = $this->sources();
        $source_id = sanitize_key( (string) ( $values['source'] ?? '' ) );
        if ( ! isset( $sources[ $source_id ] ) && 1 === count( $sources ) ) {
            $source_id = (string) array_key_first( $sources );
        }
        $source = $sources[ $source_id ] ?? null;
        $type = $this->entity_type_for_site_type( $site_type );
        if ( 'auto' === $type && ! empty( $this->config['allow_entity_type_selection'] ) ) {
            $requested_type = sanitize_key( (string) ( $values['entity_type'] ?? 'auto' ) );
            $type = in_array( $requested_type, CanonicalEntityResolver::TYPES, true ) ? $requested_type : 'auto';
        }

        $settings = [
            'enabled' => ! empty( $values['enabled'] ) && null !== $source && ! empty( $values['object_id'] ) ? 1 : 0,
            'source' => $source_id, 'kind' => $source['kind'] ?? '', 'post_type' => $source['post_type'] ?? '',
            'object_id' => max( 0, (int) ( $values['object_id'] ?? 0 ) ), 'entity_type' => $type,
            'migrated_from' => sanitize_text_field( (string) ( $values['migrated_from'] ?? $this->settings()['migrated_from'] ) ),
        ];

        update_option( (string) $this->config['site_type_option'], $site_type, false );
        update_option( (string) $this->config['entity_option'], $settings, false );
        return [ 'site_type' => $site_type, 'settings' => $settings, 'entity' => CanonicalEntityResolver::resolve( $settings ) ];
    }

    public function migrate_legacy(): void {
        $flag = (string) $this->config['migration_flag'];
        if ( get_option( $flag, false ) || ! empty( $this->settings()['object_id'] ) ) {
            return;
        }
        foreach ( (array) $this->config['legacy_resolvers'] as $resolver ) {
            if ( ! is_callable( $resolver ) ) {
                continue;
            }
            $candidate = call_user_func( $resolver );
            if ( ! is_array( $candidate ) || empty( $candidate['object_id'] ) || empty( $candidate['source'] ) ) {
                continue;
            }
            $candidate['enabled'] = 1;
            $this->save( array_merge( [ 'site_type' => $this->site_type(), 'entity_type' => 'auto' ], $candidate ) );
            break;
        }
        update_option( $flag, 1, false );
    }

    public function config( string $key, mixed $default = null ): mixed {
        return array_key_exists( $key, $this->config ) ? $this->config[ $key ] : $default;
    }
}
