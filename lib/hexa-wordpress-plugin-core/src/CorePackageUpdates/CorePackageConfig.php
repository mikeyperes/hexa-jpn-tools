<?php

namespace Hexa\PluginCore\CorePackageUpdates;

use Hexa\PluginCore\CoreRuntime\CoreVersion;
use Hexa\PluginCore\PluginUpdates\UpdaterConfig;

final class CorePackageConfig {
    private array $values;

    public function __construct( array $values = [] ) {
        $defaults = [
            'package_name'       => CoreVersion::PACKAGE_NAME,
            'core_root'          => CoreVersion::root_path(),
            'github_repo'        => CoreVersion::GITHUB_REPO,
            'github_branch'      => 'main',
            'version_file'       => CoreVersion::VERSION_FILE,
            'sslverify'          => true,
            'access_token'       => '',
            'timeout'            => 15,
            'capability'         => 'update_plugins',
            'download_capability'=> 'manage_options',
            'nonce_action'       => 'hexa_plugin_core_package_updater',
            'nonce_param'        => 'nonce',
            'ajax_action_prefix' => 'hexa_plugin_core_package',
            'cache_key'          => 'hexa_plugin_core_package_version',
            'progress_key'       => '',
        ];

        $values = array_merge( $defaults, $values );

        $values['core_root']     = rtrim( (string) $values['core_root'], '/\\' );
        $values['github_repo']   = UpdaterConfig::normalize_github_repo( (string) $values['github_repo'] );
        $values['github_branch'] = trim( (string) $values['github_branch'], '/' );
        $values['version_file']  = ltrim( (string) $values['version_file'], '/\\' );

        if ( empty( $values['progress_key'] ) ) {
            $values['progress_key'] = 'hexa_plugin_core_package_update_progress_' . md5( (string) $values['core_root'] . '|' . (string) $values['github_repo'] . '|' . (string) $values['github_branch'] );
        }

        $this->values = $values;
    }

    public static function from_core_root( string $core_root, array $overrides = [] ): self {
        return new self( array_merge( [ 'core_root' => $core_root ], $overrides ) );
    }

    public function get( string $key, mixed $default = null ): mixed {
        return array_key_exists( $key, $this->values ) ? $this->values[ $key ] : $default;
    }

    public function package_name(): string {
        return (string) $this->get( 'package_name' );
    }

    public function core_root(): string {
        return (string) $this->get( 'core_root' );
    }

    public function github_repo(): string {
        return (string) $this->get( 'github_repo' );
    }

    public function github_branch(): string {
        return (string) $this->get( 'github_branch' );
    }

    public function version_file(): string {
        return (string) $this->get( 'version_file' );
    }

    public function proper_folder_name(): string {
        $parts = explode( '/', $this->github_repo() );

        return sanitize_file_name( end( $parts ) ?: 'hexa-wordpress-plugin-core' );
    }

    public function current_version(): string {
        return CoreVersion::current( $this->core_root() );
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

    public function ajax_action( string $suffix ): string {
        return (string) $this->get( 'ajax_action_prefix' ) . '_' . $suffix;
    }

    public function cache_key( string $suffix = '' ): string {
        $key = (string) $this->get( 'cache_key' );

        return '' !== $suffix ? $key . '_' . $suffix : $key;
    }

    public function progress_key(): string {
        return (string) $this->get( 'progress_key' );
    }

    public function github_url(): string {
        return 'https://github.com/' . $this->github_repo();
    }

    public function github_api_url(): string {
        return 'https://api.github.com/repos/' . $this->github_repo();
    }

    public function raw_version_url(): string {
        return trailingslashit( 'https://raw.githubusercontent.com/' . $this->github_repo() . '/' . $this->github_branch() )
            . $this->version_file();
    }

    public function zip_url( ?string $ref = null ): string {
        return 'https://github.com/' . $this->github_repo() . '/archive/' . rawurlencode( $ref ?: $this->github_branch() ) . '.zip';
    }
}
