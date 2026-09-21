<?php

namespace Hexa\PluginCore\PluginUpdates;

use InvalidArgumentException;

final class UpdaterConfig {
    private const DEFAULTS = [
        'github_branch'      => 'main',
        'requires'           => '5.0',
        'tested'             => '6.4',
        'requires_php'       => '',
        'readme'             => 'README.md',
        'sslverify'          => true,
        'access_token'       => '',
        'timeout'            => 15,
        'capability'         => 'update_plugins',
        'download_capability'=> 'manage_options',
        'nonce_action'       => 'hexa_plugin_core_updater',
        'nonce_param'        => 'nonce',
        'ajax_action_prefix' => '',
        'progress_key'       => '',
    ];

    private array $values;

    public function __construct( array $values ) {
        $values = array_merge( self::DEFAULTS, $values );

        if ( empty( $values['github_repo'] ) && ! empty( $values['github_url'] ) ) {
            $values['github_repo'] = self::normalize_github_repo( (string) $values['github_url'] );
        } elseif ( ! empty( $values['github_repo'] ) ) {
            $values['github_repo'] = self::normalize_github_repo( (string) $values['github_repo'] );
        }

        if ( empty( $values['plugin_basename'] ) && ! empty( $values['slug'] ) ) {
            $values['plugin_basename'] = $values['slug'];
        }

        if ( empty( $values['plugin_slug'] ) && ! empty( $values['proper_folder_name'] ) ) {
            $values['plugin_slug'] = $values['proper_folder_name'];
        }

        if ( empty( $values['plugin_slug'] ) && ! empty( $values['plugin_basename'] ) ) {
            $values['plugin_slug'] = dirname( (string) $values['plugin_basename'] );
        }

        if ( empty( $values['plugin_starter_file'] ) && ! empty( $values['plugin_basename'] ) ) {
            $values['plugin_starter_file'] = basename( (string) $values['plugin_basename'] );
        }

        if ( empty( $values['plugin_basename'] ) && ! empty( $values['plugin_slug'] ) && ! empty( $values['plugin_starter_file'] ) ) {
            $values['plugin_basename'] = trim( (string) $values['plugin_slug'], '/' ) . '/' . basename( (string) $values['plugin_starter_file'] );
        }

        if ( empty( $values['canonical_plugin_basename'] ) && ! empty( $values['plugin_slug'] ) && ! empty( $values['plugin_starter_file'] ) ) {
            $values['canonical_plugin_basename'] = trim( (string) $values['plugin_slug'], '/' ) . '/' . basename( (string) $values['plugin_starter_file'] );
        }

        if ( empty( $values['runtime_folder_name'] ) && ! empty( $values['plugin_basename'] ) ) {
            $values['runtime_folder_name'] = dirname( (string) $values['plugin_basename'] );
        }

        if ( empty( $values['proper_folder_name'] ) && ! empty( $values['plugin_slug'] ) ) {
            $values['proper_folder_name'] = $values['plugin_slug'];
        }

        if ( empty( $values['ajax_action_prefix'] ) && ! empty( $values['plugin_slug'] ) ) {
            $values['ajax_action_prefix'] = 'hexa_plugin_core_' . self::key( (string) $values['plugin_slug'] );
        }

        if ( empty( $values['progress_key'] ) && ! empty( $values['plugin_slug'] ) ) {
            $values['progress_key'] = 'hexa_plugin_core_update_progress_' . md5( (string) $values['plugin_slug'] );
        }

        foreach ( [ 'plugin_basename', 'plugin_slug', 'plugin_starter_file', 'github_repo', 'version', 'plugin_name' ] as $required ) {
            if ( empty( $values[ $required ] ) ) {
                throw new InvalidArgumentException( "Missing updater config key: {$required}" );
            }
        }

        $values['plugin_basename']           = trim( (string) $values['plugin_basename'], '/' );
        $values['plugin_slug']               = trim( (string) $values['plugin_slug'], '/' );
        $values['runtime_folder_name']       = trim( (string) $values['runtime_folder_name'], '/' );
        $values['proper_folder_name']        = trim( (string) $values['proper_folder_name'], '/' );
        $values['plugin_starter_file']       = basename( (string) $values['plugin_starter_file'] );
        $values['canonical_plugin_basename'] = trim( (string) $values['canonical_plugin_basename'], '/' );
        $values['github_branch']             = trim( (string) $values['github_branch'], '/' );

        $this->values = $values;
    }

    public static function from_plugin_file( string $plugin_file, string $github_repo_or_url, array $overrides = [] ): self {
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_data = get_plugin_data( $plugin_file, false, false );
        $basename    = function_exists( 'plugin_basename' ) ? plugin_basename( $plugin_file ) : basename( dirname( $plugin_file ) ) . '/' . basename( $plugin_file );

        return new self(
            array_merge(
                [
                    'plugin_file'          => $plugin_file,
                    'plugin_basename'      => $basename,
                    'plugin_slug'          => dirname( $basename ),
                    'plugin_starter_file'  => basename( $plugin_file ),
                    'github_repo'          => $github_repo_or_url,
                    'plugin_name'          => $plugin_data['Name'] ?? basename( dirname( $plugin_file ) ),
                    'version'              => $plugin_data['Version'] ?? '0.0.0',
                    'author'               => $plugin_data['Author'] ?? '',
                    'homepage'             => $plugin_data['PluginURI'] ?? '',
                    'description'          => $plugin_data['Description'] ?? '',
                ],
                $overrides
            )
        );
    }

    public static function from_slug_and_github_url( string $plugin_slug, string $github_repo_or_url, array $overrides = [] ): self {
        $plugin_slug  = trim( $plugin_slug, '/' );
        $starter_file = ! empty( $overrides['plugin_starter_file'] )
            ? basename( (string) $overrides['plugin_starter_file'] )
            : $plugin_slug . '.php';

        return new self(
            array_merge(
                [
                    'plugin_slug'               => $plugin_slug,
                    'proper_folder_name'        => $plugin_slug,
                    'runtime_folder_name'       => $plugin_slug,
                    'plugin_starter_file'       => $starter_file,
                    'plugin_basename'           => $plugin_slug . '/' . $starter_file,
                    'canonical_plugin_basename' => $plugin_slug . '/' . $starter_file,
                    'github_repo'               => $github_repo_or_url,
                    'plugin_name'               => $overrides['plugin_name'] ?? $plugin_slug,
                    'version'                   => $overrides['version'] ?? '0.0.0',
                ],
                $overrides
            )
        );
    }

    public static function normalize_github_repo( string $repo_or_url ): string {
        $repo_or_url = trim( $repo_or_url );
        $repo_or_url = preg_replace( '#^git@github\.com:#', '', $repo_or_url );
        $repo_or_url = preg_replace( '#^https?://github\.com/#', '', $repo_or_url );
        $repo_or_url = preg_replace( '#\.git$#', '', (string) $repo_or_url );
        $repo_or_url = preg_replace( '#/archive/.*$#', '', (string) $repo_or_url );

        return trim( (string) $repo_or_url, '/' );
    }

    public function get( string $key, mixed $default = null ): mixed {
        return array_key_exists( $key, $this->values ) ? $this->values[ $key ] : $default;
    }

    public function all(): array {
        return $this->values;
    }

    public function plugin_basename(): string {
        return (string) $this->get( 'plugin_basename' );
    }

    public function canonical_plugin_basename(): string {
        return (string) $this->get( 'canonical_plugin_basename' );
    }

    public function plugin_slug(): string {
        return (string) $this->get( 'plugin_slug' );
    }

    public function runtime_folder_name(): string {
        return (string) $this->get( 'runtime_folder_name' );
    }

    public function proper_folder_name(): string {
        return (string) $this->get( 'proper_folder_name' );
    }

    public function plugin_starter_file(): string {
        return (string) $this->get( 'plugin_starter_file' );
    }

    public function github_repo(): string {
        return (string) $this->get( 'github_repo' );
    }

    public function github_branch(): string {
        return (string) $this->get( 'github_branch' );
    }

    public function version(): string {
        return (string) $this->get( 'version' );
    }

    public function plugin_name(): string {
        return (string) $this->get( 'plugin_name' );
    }

    public function capability(): string {
        return (string) $this->get( 'capability' );
    }

    public function download_capability(): string {
        return (string) $this->get( 'download_capability' );
    }

    public function nonce_action(): string {
        return (string) $this->get( 'nonce_action' );
    }

    public function nonce_param(): string {
        return (string) $this->get( 'nonce_param' );
    }

    public function ajax_action_prefix(): string {
        return (string) $this->get( 'ajax_action_prefix' );
    }

    public function progress_key(): string {
        return (string) $this->get( 'progress_key' );
    }

    public function github_api_url(): string {
        return 'https://api.github.com/repos/' . $this->github_repo();
    }

    public function raw_base_url(): string {
        return 'https://raw.githubusercontent.com/' . $this->github_repo() . '/' . $this->github_branch();
    }

    public function github_url(): string {
        return 'https://github.com/' . $this->github_repo();
    }

    public function zip_url( ?string $ref = null ): string {
        $ref = $ref ?: $this->github_branch();

        if ( preg_match( '/^[a-f0-9]{7,40}$/i', $ref ) ) {
            return 'https://github.com/' . $this->github_repo() . '/archive/' . $ref . '.zip';
        }

        return 'https://github.com/' . $this->github_repo() . '/archive/refs/heads/' . trim( $ref, '/' ) . '.zip';
    }

    public function managed_basenames(): array {
        return array_values(
            array_unique(
                array_filter(
                    [
                        $this->plugin_basename(),
                        $this->canonical_plugin_basename(),
                    ]
                )
            )
        );
    }

    public function ajax_action( string $name ): string {
        return $this->ajax_action_prefix() . '_' . self::key( $name );
    }

    public function cache_key( string $type ): string {
        return 'hexa_plugin_core_' . self::key( $type ) . '_' . md5( $this->plugin_basename() . '|' . $this->github_repo() . '|' . $this->github_branch() );
    }

    private static function key( string $value ): string {
        $value = strtolower( $value );
        $value = preg_replace( '/[^a-z0-9_]+/', '_', $value );

        return trim( (string) $value, '_' );
    }
}
