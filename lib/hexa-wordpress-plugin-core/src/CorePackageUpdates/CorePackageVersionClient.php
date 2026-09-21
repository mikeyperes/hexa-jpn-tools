<?php

namespace Hexa\PluginCore\CorePackageUpdates;

final class CorePackageVersionClient {
    private CorePackageConfig $config;

    public function __construct( CorePackageConfig $config ) {
        $this->config = $config;
    }

    public function remote_version( bool $force = false ): string|false {
        $cached = get_site_transient( $this->config->cache_key( 'remote_version' ) );

        if ( false !== $cached && ! $force ) {
            return (string) $cached;
        }

        $url = $this->config->raw_version_url();
        if ( $force ) {
            $url = add_query_arg( 'cb', time(), $url );
        }

        $response = wp_remote_get( $url, $this->request_args() );

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return false;
        }

        $version = trim( (string) wp_remote_retrieve_body( $response ) );
        if ( '' === $version ) {
            return false;
        }

        set_site_transient( $this->config->cache_key( 'remote_version' ), $version, 30 * MINUTE_IN_SECONDS );

        return $version;
    }

    public function clear_cache(): void {
        delete_site_transient( $this->config->cache_key( 'remote_version' ) );
    }

    public function request_args(): array {
        $headers = [
            'Accept'     => 'application/vnd.github+json',
            'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
        ];

        if ( $this->config->get( 'access_token' ) ) {
            $headers['Authorization'] = 'token ' . $this->config->get( 'access_token' );
        }

        return [
            'timeout'   => (int) $this->config->get( 'timeout', 15 ),
            'sslverify' => (bool) $this->config->get( 'sslverify', true ),
            'headers'   => $headers,
        ];
    }
}
