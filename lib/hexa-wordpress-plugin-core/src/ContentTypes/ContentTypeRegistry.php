<?php

namespace Hexa\PluginCore\ContentTypes;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

final class ContentTypeRegistry implements ModuleInterface {
    /** @var array<string,array<string,mixed>> */
    private array $definitions = [];
    private array $config;
    private ContentTypeSettingsStore $store;

    public function __construct( array $config = [] ) {
        $this->config = array_merge(
            [
                'option_name'  => 'hexa_content_types',
                'capability'   => 'manage_options',
                'ajax_action'  => '',
                'nonce_action' => 'hexa_content_types',
                'nonce_field'  => 'nonce',
                'hook_priority'=> 8,
            ],
            $config
        );
        $this->store = new ContentTypeSettingsStore( (string) $this->config['option_name'] );
    }

    /** @param array<string,mixed> $definition */
    public function add( array $definition ): self {
        $definition = ContentTypeDefinition::normalize( $definition );
        $this->definitions[ $definition['id'] ] = $definition;
        return $this;
    }

    /** @param array<int|string,array<string,mixed>> $definitions */
    public function add_many( array $definitions ): self {
        foreach ( $definitions as $id => $definition ) {
            if ( ! isset( $definition['id'] ) && is_string( $id ) ) {
                $definition['id'] = $id;
            }
            $this->add( $definition );
        }
        return $this;
    }

    public function register(): void {
        $registrar = new ContentTypeRegistrar( $this );
        add_action( 'init', [ $registrar, 'register_post_types' ], (int) $this->config['hook_priority'] );
        add_action( 'wp_loaded', [ $this, 'sync_rewrite_rules' ] );
        \Hexa\PluginCore\Fields\FieldGroups::ready( [ $registrar, 'register_acf_groups' ], (int) $this->config['hook_priority'] );
        ( new ContentTypeAjaxController( $this, $this->config ) )->register();
    }

    /**
     * Refresh WordPress's URL rules once when this registry's post types or
     * their URL bases change: after the host plugin is activated by any route
     * (wp-admin, WP-CLI, REST, an updater), when a type is enabled or disabled,
     * or when the permalink structure changes. Without it, new post type URLs
     * 404 until someone re-saves Settings > Permalinks.
     */
    public function sync_rewrite_rules(): bool {
        if ( ! function_exists( 'flush_rewrite_rules' ) ) {
            return false;
        }

        $signature = md5( (string) wp_json_encode( $this->rewrite_signature() ) );
        $option    = (string) $this->config['option_name'] . '_rewrite_signature';
        if ( get_option( $option, '' ) === $signature ) {
            return false;
        }

        flush_rewrite_rules( false );
        update_option( $option, $signature, false );

        return true;
    }

    /** @return array<string,mixed> */
    private function rewrite_signature(): array {
        $types = [];
        foreach ( $this->resolved_definitions() as $definition ) {
            if ( empty( $definition['enabled'] ) || 'external' === $definition['registration_mode'] ) {
                continue;
            }
            $args = $definition['post_type']['args'];
            $types[ $definition['post_type']['key'] ] = [
                'slug'       => false === ( $args['rewrite'] ?? true ) ? false : $definition['post_type']['rewrite_slug'],
                'archive'    => $args['has_archive'] ?? null,
                'taxonomies' => array_values( array_map( static fn( array $taxonomy ): array => [ $taxonomy['key'] ?? '', $taxonomy['rewrite_slug'] ?? '', ! empty( $taxonomy['enabled_default'] ) ], (array) $definition['taxonomies'] ) ),
            ];
        }
        ksort( $types );

        return [ 'structure' => (string) get_option( 'permalink_structure', '' ), 'types' => $types ];
    }

    /** @return array<int,array<string,mixed>> */
    public function definitions(): array {
        return array_values( $this->definitions );
    }

    /** @return array<int,array<string,mixed>> */
    public function resolved_definitions(): array {
        return array_map( fn( array $definition ): array => $this->store->resolve( $definition ), $this->definitions() );
    }

    /** @return array<string,mixed>|null */
    public function definition( string $id ): ?array {
        return $this->definitions[ $id ] ?? null;
    }

    public function store(): ContentTypeSettingsStore {
        return $this->store;
    }

    public function config( string $key, mixed $default = null ): mixed {
        return array_key_exists( $key, $this->config ) ? $this->config[ $key ] : $default;
    }
}
