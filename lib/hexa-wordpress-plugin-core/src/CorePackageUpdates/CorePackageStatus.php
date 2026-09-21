<?php

namespace Hexa\PluginCore\CorePackageUpdates;

final class CorePackageStatus {
    private CorePackageConfig $config;

    private CorePackageVersionClient $client;

    public function __construct( CorePackageConfig $config, ?CorePackageVersionClient $client = null ) {
        $this->config = $config;
        $this->client = $client ?: new CorePackageVersionClient( $config );
    }

    public function get( bool $force = false ): array {
        $current = $this->config->current_version();
        $latest  = $this->client->remote_version( $force );

        return [
            'package_name'     => $this->config->package_name(),
            'core_root'        => $this->config->core_root(),
            'github_repo'      => $this->config->github_repo(),
            'github_branch'    => $this->config->github_branch(),
            'github_url'       => $this->config->github_url(),
            'current_version'  => $current,
            'latest_version'   => $latest ?: 'Unknown',
            'git_version'      => $latest ?: 'Unknown',
            'update_available' => $latest ? version_compare( $latest, $current, '>' ) : false,
        ];
    }
}
