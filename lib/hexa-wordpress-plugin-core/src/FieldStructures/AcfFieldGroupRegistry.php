<?php

namespace Hexa\PluginCore\FieldStructures;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use InvalidArgumentException;

final class AcfFieldGroupRegistry implements ModuleInterface {
    /** @var array<string,array<string,mixed>> */
    private array $definitions = [];
    private array $config;
    private AcfFieldGroupSettingsStore $store;

    public function __construct( array $config = [] ) {
        $this->config = array_merge(
            [
                'option_name' => 'hexa_acf_field_groups', 'capability' => 'manage_options',
                'ajax_action' => '', 'nonce_action' => 'hexa_acf_field_groups',
                'nonce_field' => 'nonce', 'hook_priority' => 8, 'after_save' => null,
            ],
            $config
        );
        $this->store = new AcfFieldGroupSettingsStore( (string) $this->config['option_name'] );
    }

    /** @param array<string,mixed> $definition */
    public function add( array $definition ): self {
        $id = sanitize_key( (string) ( $definition['id'] ?? '' ) );
        if ( '' === $id ) {
            throw new InvalidArgumentException( 'ACF field-group definitions require an id.' );
        }
        $definition = array_merge(
            [
                'id' => $id, 'label' => ucwords( str_replace( [ '-', '_' ], ' ', $id ) ),
                'description' => '', 'group_key' => '', 'enabled_default' => true,
                'legacy_option' => '', 'definition' => [], 'fields' => [],
                'location' => '', 'dependencies' => [], 'available_when' => null,
            ],
            $definition
        );
        $definition['id'] = $id;
        $definition['label'] = sanitize_text_field( (string) $definition['label'] );
        $definition['description'] = sanitize_text_field( (string) $definition['description'] );
        $definition['group_key'] = sanitize_key( (string) $definition['group_key'] );
        $definition['legacy_option'] = sanitize_key( (string) $definition['legacy_option'] );
        $definition['location'] = sanitize_text_field( (string) $definition['location'] );
        $definition['fields'] = $this->text_list( $definition['fields'] );
        $definition['dependencies'] = $this->text_list( $definition['dependencies'] );
        $definition['enabled_default'] = (bool) $definition['enabled_default'];
        $this->definitions[ $id ] = $definition;
        return $this;
    }

    public function register(): void {
        add_filter( 'acf/load_field_group', [ $this, 'apply_managed_state' ], 999 );
        add_action( 'acf/init', [ $this, 'register_groups' ], (int) $this->config['hook_priority'] );
        ( new AcfFieldGroupAjaxController( $this, $this->config ) )->register();
    }

    /** @param array<string,mixed> $field_group */
    public function apply_managed_state( array $field_group ): array {
        $group_key = sanitize_key( (string) ( $field_group['key'] ?? '' ) );
        if ( '' === $group_key ) {
            return $field_group;
        }

        foreach ( $this->definitions as $definition ) {
            if ( $group_key !== (string) $definition['group_key'] ) {
                continue;
            }
            if ( ! $this->store->enabled( $definition ) ) {
                $field_group['active'] = false;
            }
            break;
        }

        return $field_group;
    }

    public function register_groups(): void {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }
        foreach ( $this->resolved_definitions() as $definition ) {
            if ( empty( $definition['enabled'] ) || empty( $definition['available'] ) ) {
                continue;
            }
            $group = is_callable( $definition['definition'] )
                ? call_user_func( $definition['definition'], $definition )
                : $definition['definition'];
            if ( ! is_array( $group ) || empty( $group ) ) {
                continue;
            }
            if ( empty( $group['key'] ) && '' !== $definition['group_key'] ) {
                $group['key'] = $definition['group_key'];
            }
            if ( empty( $group['title'] ) ) {
                $group['title'] = $definition['label'];
            }
            acf_add_local_field_group( $group );
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function resolved_definitions(): array {
        return array_map(
            function ( array $definition ): array {
                $definition['enabled'] = $this->store->enabled( $definition );
                $definition['available'] = ! is_callable( $definition['available_when'] ) || (bool) call_user_func( $definition['available_when'] );
                return $definition;
            },
            array_values( $this->definitions )
        );
    }

    /** @return array<string,mixed>|null */
    public function definition( string $id ): ?array {
        return $this->definitions[ sanitize_key( $id ) ] ?? null;
    }

    public function store(): AcfFieldGroupSettingsStore {
        return $this->store;
    }

    public function config( string $key, mixed $default = null ): mixed {
        return array_key_exists( $key, $this->config ) ? $this->config[ $key ] : $default;
    }

    /** @return array<int,string> */
    private function text_list( mixed $items ): array {
        if ( is_string( $items ) ) {
            $items = preg_split( '/\r\n|\r|\n/', $items ) ?: [];
        }
        if ( ! is_array( $items ) ) {
            return [];
        }
        return array_values( array_filter( array_map( static fn( mixed $item ): string => is_scalar( $item ) ? sanitize_text_field( (string) $item ) : '', $items ) ) );
    }
}
