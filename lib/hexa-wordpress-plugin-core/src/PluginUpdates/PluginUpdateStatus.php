<?php

namespace Hexa\PluginCore\PluginUpdates;

final class PluginUpdateStatus {
    private UpdaterConfig $config;

    private GitHubVersionClient $client;

    public function __construct( UpdaterConfig $config, ?GitHubVersionClient $client = null ) {
        $this->config = $config;
        $this->client = $client ?: new GitHubVersionClient( $config );
    }

    public function get( bool $force = false ): array {
        $latest        = $this->client->remote_version( $force );
        $core_response = $this->core_update_response();

        return [
            'plugin_name'         => $this->config->plugin_name(),
            'plugin_slug'         => $this->config->plugin_slug(),
            'plugin_basename'     => $this->config->plugin_basename(),
            'canonical_basename'  => $this->config->canonical_plugin_basename(),
            'runtime_folder_name' => $this->config->runtime_folder_name(),
            'proper_folder_name'  => $this->config->proper_folder_name(),
            'github_repo'         => $this->config->github_repo(),
            'github_branch'       => $this->config->github_branch(),
            'github_url'          => $this->config->github_url(),
            'current_version'     => $this->config->version(),
            'latest_version'      => $latest ?: 'Unknown',
            'git_version'         => $latest ?: 'Unknown',
            'update_available'    => $latest ? version_compare( $latest, $this->config->version(), '>' ) : false,
            'core_detected'       => (bool) $core_response,
            'core_version'        => $core_response->new_version ?? '',
            'core_plugin'         => $core_response->plugin ?? '',
        ];
    }

    public function core_update_response( mixed $transient = null ): object|false {
        if ( null === $transient ) {
            $transient = get_site_transient( 'update_plugins' );
        }

        if ( ! is_object( $transient ) || empty( $transient->response ) || ! is_array( $transient->response ) ) {
            return false;
        }

        foreach ( $this->config->managed_basenames() as $basename ) {
            if ( isset( $transient->response[ $basename ] ) ) {
                return $transient->response[ $basename ];
            }
        }

        return false;
    }
}
