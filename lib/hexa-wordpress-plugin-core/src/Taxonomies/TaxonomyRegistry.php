<?php

namespace Hexa\PluginCore\Taxonomies;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

final class TaxonomyRegistry implements ModuleInterface {
    /** @var array<string,array<string,mixed>> */
    private array $definitions = [];

    public function __construct( private array $config = [] ) {
        $this->config = array_merge( [ 'hook_priority' => 8 ], $this->config );
    }

    /** @param array<string,mixed> $definition */
    public function add( array $definition ): self {
        $definition = TaxonomyDefinition::normalize( $definition );
        $this->definitions[ $definition['id'] ] = $definition;
        return $this;
    }

    public function register(): void {
        add_action( 'init', [ $this, 'register_taxonomies' ], (int) $this->config['hook_priority'] );
    }

    public function register_taxonomies(): void {
        foreach ( $this->resolved_definitions() as $definition ) {
            if ( empty( $definition['enabled'] ) || empty( $definition['object_types'] ) ) {
                continue;
            }

            if ( function_exists( 'taxonomy_exists' ) && taxonomy_exists( $definition['taxonomy'] ) ) {
                if ( function_exists( 'register_taxonomy_for_object_type' ) ) {
                    foreach ( $definition['object_types'] as $object_type ) {
                        register_taxonomy_for_object_type( $definition['taxonomy'], $object_type );
                    }
                }
                continue;
            }

            register_taxonomy( $definition['taxonomy'], $definition['object_types'], $definition['args'] );
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function definitions(): array {
        return array_values( $this->definitions );
    }

    /** @return array<int,array<string,mixed>> */
    public function resolved_definitions(): array {
        return array_map(
            function ( array $definition ): array {
                $definition['enabled'] = is_callable( $definition['enabled'] )
                    ? (bool) call_user_func( $definition['enabled'], $definition )
                    : (bool) $definition['enabled'];
                $object_types = is_callable( $definition['object_types'] )
                    ? call_user_func( $definition['object_types'], $definition )
                    : $definition['object_types'];
                $args = is_callable( $definition['args'] )
                    ? call_user_func( $definition['args'], $definition )
                    : $definition['args'];
                $definition['object_types'] = self::keys( is_array( $object_types ) ? $object_types : [] );
                $definition['args'] = is_array( $args ) ? $args : [];
                return $definition;
            },
            $this->definitions()
        );
    }

    /** @param array<int,mixed> $values @return array<int,string> */
    private static function keys( array $values ): array {
        $keys = [];
        foreach ( $values as $value ) {
            if ( ! is_scalar( $value ) ) {
                continue;
            }
            $key = function_exists( 'sanitize_key' ) ? sanitize_key( (string) $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) ?: '' );
            if ( '' !== $key ) {
                $keys[ $key ] = $key;
            }
        }
        return array_values( $keys );
    }
}
