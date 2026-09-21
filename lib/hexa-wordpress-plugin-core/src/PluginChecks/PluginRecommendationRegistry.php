<?php

namespace Hexa\PluginCore\PluginChecks;

final class PluginRecommendationRegistry {
    /**
     * @var array<string,array<string,mixed>>
     */
    private static array $providers = [];

    /**
     * @param array<string,mixed> $provider
     */
    public static function register_hexa_plugin( array $provider ): void {
        $provider = self::normalize_provider( $provider );
        if ( '' === $provider['id'] ) {
            return;
        }

        self::$providers[ $provider['id'] ] = $provider;
    }

    public static function reset(): void {
        self::$providers = [];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function get_registered_hexa_plugins(): array {
        return self::providers();
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function hexa_plugins(): array {
        return self::get_registered_hexa_plugins();
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function get_installed_hexa_plugins(): array {
        $installed = self::get_all_plugins_on_site( false );
        $providers = self::providers();
        $matches   = [];

        foreach ( $providers as $provider_id => $provider ) {
            $plugin_file = (string) ( $provider['plugin_file'] ?? '' );
            if ( '' !== $plugin_file && isset( $installed[ self::inventory_key( 'plugin', $plugin_file ) ] ) ) {
                $matches[ $provider_id ] = array_merge(
                    $provider,
                    [
                        'installed' => true,
                        'site_plugin' => $installed[ self::inventory_key( 'plugin', $plugin_file ) ],
                    ]
                );
            }
        }

        return $matches;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function get_all_plugins_on_site( bool $include_non_manageable = true ): array {
        self::load_plugin_functions();

        $items = [];
        foreach ( get_plugins() as $plugin_file => $plugin_data ) {
            $plugin_file = (string) $plugin_file;
            $items[ self::inventory_key( 'plugin', $plugin_file ) ] = self::site_plugin_record(
                $plugin_file,
                is_array( $plugin_data ) ? $plugin_data : [],
                'plugin',
                function_exists( 'is_plugin_active' ) && is_plugin_active( $plugin_file ),
                true
            );
        }

        if ( $include_non_manageable && function_exists( 'get_mu_plugins' ) ) {
            foreach ( get_mu_plugins() as $plugin_file => $plugin_data ) {
                $plugin_file = (string) $plugin_file;
                $items[ self::inventory_key( 'must_use', $plugin_file ) ] = self::site_plugin_record(
                    $plugin_file,
                    is_array( $plugin_data ) ? $plugin_data : [],
                    'must_use',
                    true,
                    false
                );
            }
        }

        if ( $include_non_manageable ) {
            $dropins = [];
            if ( function_exists( 'get_dropins' ) ) {
                $dropins = get_dropins();
            } elseif ( function_exists( '_get_dropins' ) ) {
                $dropins = _get_dropins();
            }

            foreach ( $dropins as $plugin_file => $plugin_data ) {
                $plugin_file = (string) $plugin_file;
                $items[ self::inventory_key( 'dropin', $plugin_file ) ] = self::site_plugin_record(
                    $plugin_file,
                    is_array( $plugin_data ) ? $plugin_data : [],
                    'dropin',
                    true,
                    false
                );
            }
        }

        ksort( $items );

        return $items;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function all_site_plugins( bool $include_non_manageable = true ): array {
        return self::get_all_plugins_on_site( $include_non_manageable );
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function get_plugins_recommended_by_hexa_plugin( string $hexa_plugin_id ): array {
        $providers = self::providers();
        $provider_id = self::clean_id( $hexa_plugin_id );

        if ( ! isset( $providers[ $provider_id ] ) ) {
            return [];
        }

        return self::definitions_for_provider( $providers[ $provider_id ] );
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function recommended_by_hexa_plugin( string $hexa_plugin_id ): array {
        return self::get_plugins_recommended_by_hexa_plugin( $hexa_plugin_id );
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function get_plugins_recommended_by_all_hexa_plugins(): array {
        $recommended = [];

        foreach ( self::providers() as $provider ) {
            foreach ( self::definitions_for_provider( $provider ) as $key => $record ) {
                if ( ! isset( $recommended[ $key ] ) ) {
                    $recommended[ $key ] = $record;
                    continue;
                }

                $recommended[ $key ]['recommended_by'] = array_values(
                    array_merge(
                        $recommended[ $key ]['recommended_by'],
                        $record['recommended_by']
                    )
                );
                $recommended[ $key ]['recommended_by_ids'] = array_values(
                    array_unique(
                        array_merge(
                            $recommended[ $key ]['recommended_by_ids'],
                            $record['recommended_by_ids']
                        )
                    )
                );
            }
        }

        ksort( $recommended );

        return $recommended;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function recommended_by_all_hexa_plugins(): array {
        return self::get_plugins_recommended_by_all_hexa_plugins();
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function get_installed_plugins_not_recommended_by_hexa_plugins( bool $include_non_manageable = false ): array {
        $installed   = self::get_all_plugins_on_site( $include_non_manageable );
        $recommended = self::get_plugins_recommended_by_all_hexa_plugins();
        $extras      = [];

        foreach ( $installed as $key => $plugin ) {
            if ( isset( $recommended[ $key ] ) ) {
                continue;
            }

            $extras[ $key ] = array_merge(
                $plugin,
                [
                    'recommended_by'     => [],
                    'recommended_by_ids' => [],
                ]
            );
        }

        ksort( $extras );

        return $extras;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function get_plugins_installed_not_recommended_by_hexa_plugins( bool $include_non_manageable = false ): array {
        return self::get_installed_plugins_not_recommended_by_hexa_plugins( $include_non_manageable );
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function installed_not_recommended_by_hexa_plugins( bool $include_non_manageable = false ): array {
        return self::get_installed_plugins_not_recommended_by_hexa_plugins( $include_non_manageable );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function get_installed_not_recommended_definitions( bool $include_non_manageable = false ): array {
        $definitions = [];

        foreach ( self::get_installed_plugins_not_recommended_by_hexa_plugins( $include_non_manageable ) as $plugin ) {
            $definitions[] = self::unlisted_definition_from_site_plugin( $plugin );
        }

        return $definitions;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function installed_not_recommended_definitions( bool $include_non_manageable = false ): array {
        return self::get_installed_not_recommended_definitions( $include_non_manageable );
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function providers(): array {
        $providers = self::$providers;

        if ( function_exists( 'apply_filters' ) ) {
            $filtered = apply_filters( 'hexa_plugin_core_recommendation_providers', $providers );
            if ( is_array( $filtered ) ) {
                $providers = $filtered;
            }
        }

        $normalized = [];
        foreach ( $providers as $key => $provider ) {
            if ( ! is_array( $provider ) ) {
                continue;
            }

            if ( ! isset( $provider['id'] ) && is_string( $key ) ) {
                $provider['id'] = $key;
            }

            $provider = self::normalize_provider( $provider );
            if ( '' !== $provider['id'] ) {
                $normalized[ $provider['id'] ] = $provider;
            }
        }

        ksort( $normalized );

        return $normalized;
    }

    /**
     * @param array<string,mixed> $provider
     * @return array<string,mixed>
     */
    private static function normalize_provider( array $provider ): array {
        $id = self::clean_id( (string) ( $provider['id'] ?? $provider['slug'] ?? $provider['plugin_file'] ?? '' ) );

        return [
            'id'          => $id,
            'name'        => (string) ( $provider['name'] ?? $provider['label'] ?? $id ),
            'plugin_file' => self::clean_plugin_file( (string) ( $provider['plugin_file'] ?? '' ) ),
            'repo'        => trim( (string) ( $provider['repo'] ?? $provider['github_repo'] ?? '' ), '/' ),
            'definitions' => isset( $provider['definitions'] ) && is_array( $provider['definitions'] ) ? $provider['definitions'] : [],
            'callback'    => $provider['callback'] ?? $provider['definitions_callback'] ?? null,
            'notes'       => (string) ( $provider['notes'] ?? $provider['description'] ?? '' ),
        ];
    }

    /**
     * @param array<string,mixed> $provider
     * @return array<string,array<string,mixed>>
     */
    private static function definitions_for_provider( array $provider ): array {
        $raw = isset( $provider['definitions'] ) && is_array( $provider['definitions'] ) ? $provider['definitions'] : [];
        $callback = $provider['callback'] ?? null;

        if ( self::is_callable_definition_source( $callback ) ) {
            $callback_result = call_user_func( $callback );
            if ( is_array( $callback_result ) ) {
                $raw = array_merge( $raw, $callback_result );
            }
        }

        if ( function_exists( 'apply_filters' ) ) {
            $filtered = apply_filters( 'hexa_plugin_core_recommended_plugin_definitions', $raw, $provider['id'], $provider );
            if ( is_array( $filtered ) ) {
                $raw = $filtered;
            }
        }

        $records = [];
        foreach ( PluginCheckService::normalize_definitions( $raw ) as $definition ) {
            if ( method_exists( $definition, 'should_not_contain' ) && $definition->should_not_contain() ) {
                continue;
            }

            $key = self::inventory_key_for_definition( $definition );
            if ( '' === $key ) {
                continue;
            }

            $records[ $key ] = self::definition_record( $definition, $provider );
        }

        return $records;
    }

    private static function is_callable_definition_source( mixed $callback ): bool {
        if ( is_array( $callback ) && isset( $callback[0], $callback[1] ) && is_string( $callback[0] ) && ! class_exists( $callback[0] ) ) {
            return false;
        }

        return is_callable( $callback );
    }

    private static function inventory_key_for_definition( PluginCheckDefinition $definition ): string {
        if ( '' === $definition->plugin_file ) {
            return '';
        }

        if ( 'must_use' === $definition->source ) {
            return self::inventory_key( 'must_use', $definition->plugin_file );
        }

        if ( 'dropin' === $definition->source ) {
            return self::inventory_key( 'dropin', $definition->plugin_file );
        }

        return self::inventory_key( 'plugin', $definition->plugin_file );
    }

    /**
     * @param array<string,mixed> $provider
     * @return array<string,mixed>
     */
    private static function definition_record( PluginCheckDefinition $definition, array $provider ): array {
        return [
            'key'                => self::inventory_key_for_definition( $definition ),
            'plugin_file'        => $definition->plugin_file,
            'name'               => $definition->name,
            'slug'               => $definition->slug,
            'source'             => $definition->source,
            'required'           => $definition->required,
            'recommended'        => $definition->recommended,
            'recommended_by'     => [
                [
                    'id'   => (string) $provider['id'],
                    'name' => (string) $provider['name'],
                ],
            ],
            'recommended_by_ids' => [ (string) $provider['id'] ],
        ];
    }

    /**
     * @param array<string,mixed> $plugin_data
     * @return array<string,mixed>
     */
    private static function site_plugin_record( string $plugin_file, array $plugin_data, string $source, bool $active, bool $manageable ): array {
        $name = (string) ( $plugin_data['Name'] ?? $plugin_data['Title'] ?? $plugin_file );

        return [
            'key'         => self::inventory_key( $source, $plugin_file ),
            'plugin_file' => self::clean_plugin_file( $plugin_file ),
            'slug'        => 'plugin' === $source ? basename( dirname( $plugin_file ) ) : basename( $plugin_file ),
            'name'        => '' !== $name ? $name : $plugin_file,
            'version'     => (string) ( $plugin_data['Version'] ?? '' ),
            'source'      => $source,
            'active'      => $active,
            'status'      => $active ? 'active' : 'inactive',
            'manageable'  => $manageable,
        ];
    }

    /**
     * @param array<string,mixed> $plugin
     * @return array<string,mixed>
     */
    private static function unlisted_definition_from_site_plugin( array $plugin ): array {
        $plugin_file = self::clean_plugin_file( (string) ( $plugin['plugin_file'] ?? '' ) );
        $source      = (string) ( $plugin['source'] ?? 'plugin' );
        $definition_source = in_array( $source, [ 'must_use', 'dropin' ], true ) ? $source : 'manual';
        $name = (string) ( $plugin['name'] ?? $plugin_file );

        return [
            'id'                 => 'unlisted-' . self::clean_id( $source . '-' . $plugin_file ),
            'name'               => $name,
            'plugin_file'        => $plugin_file,
            'slug'               => in_array( $source, [ 'must_use', 'dropin' ], true ) ? $plugin_file : dirname( $plugin_file ),
            'source'             => $definition_source,
            'required'           => false,
            'recommended'        => false,
            'should_not_contain' => false,
            'download_label'     => 'Open plugins',
            'notes'              => 'Installed on this site but not included in any registered Hexa plugin policy.',
            'checks'             => [
                'installed'     => false,
                'active'        => false,
                'up_to_date'    => false,
                'auto_update'   => false,
                'not_installed' => false,
            ],
        ];
    }

    private static function inventory_key( string $source, string $plugin_file ): string {
        $source = self::clean_source( $source );
        $plugin_file = self::clean_plugin_file( $plugin_file );

        return $source . ':' . $plugin_file;
    }

    private static function load_plugin_functions(): void {
        if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }

    private static function clean_plugin_file( string $plugin_file ): string {
        $plugin_file = trim( str_replace( '\\', '/', $plugin_file ), '/' );

        return preg_replace( '#[^a-zA-Z0-9_./\-]#', '', $plugin_file ) ?: '';
    }

    private static function clean_id( string $value ): string {
        $value = str_replace( [ '/', '.', ':' ], '-', $value );

        if ( function_exists( 'sanitize_key' ) ) {
            return sanitize_key( $value );
        }

        return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?: '';
    }

    private static function clean_source( string $source ): string {
        $source = function_exists( 'sanitize_key' ) ? sanitize_key( $source ) : strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $source ) );

        return in_array( $source, [ 'plugin', 'must_use', 'dropin' ], true ) ? $source : 'plugin';
    }
}
