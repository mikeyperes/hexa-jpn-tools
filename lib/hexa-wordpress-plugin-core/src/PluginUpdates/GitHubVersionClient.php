<?php

namespace Hexa\PluginCore\PluginUpdates;

final class GitHubVersionClient {
    private UpdaterConfig $config;

    private mixed $repo_data = null;

    public function __construct( UpdaterConfig $config ) {
        $this->config = $config;
    }

    public function remote_version( bool $force = false ): string|false {
        $transient_key = $this->config->cache_key( 'github_version' );
        $cached        = get_site_transient( $transient_key );

        if ( false !== $cached && ! $force ) {
            return (string) $cached;
        }

        $version = $this->version_from_ref( $this->config->github_branch(), $force );

        if ( false !== $version ) {
            set_site_transient( $transient_key, $version, 30 * MINUTE_IN_SECONDS );
        }

        return $version;
    }

    /**
     * The "Requires PHP" of the source the updater offers (the branch's main
     * plugin file), or '' when it declares none or cannot be read.
     */
    public function remote_requires_php( bool $force = false ): string {
        $transient_key = $this->config->cache_key( 'github_requires_php' );
        $cached        = get_site_transient( $transient_key );

        if ( false !== $cached && ! $force ) {
            return (string) $cached;
        }

        $body = $this->main_file_from_ref( $this->config->github_branch(), $force );
        if ( false === $body ) {
            return '';
        }

        $requires = self::extract_requires_php( $body );
        set_site_transient( $transient_key, $requires, 30 * MINUTE_IN_SECONDS );

        return $requires;
    }

    /**
     * URL of the release's PHP 7.4 build (`<folder>-<version>-php74.zip`, made
     * by bin/build-php74-release.sh) for this version, or '' when none exists.
     */
    public function compat_package_url( string $version ): string {
        $transient_key = $this->config->cache_key( 'github_php74_' . md5( $version ) );
        $cached        = get_site_transient( $transient_key );

        if ( false !== $cached ) {
            return (string) $cached;
        }

        $url = '';
        foreach ( [ 'v' . $version, $version ] as $tag ) {
            $response = wp_remote_get( 'https://api.github.com/repos/' . $this->config->github_repo() . '/releases/tags/' . rawurlencode( $tag ), $this->request_args( 15 ) );
            if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
                continue;
            }
            $release = json_decode( (string) wp_remote_retrieve_body( $response ), true );
            $url     = is_array( $release ) ? self::compat_asset_url( $this->config->github_repo(), $release ) : '';
            break;
        }

        set_site_transient( $transient_key, $url, 30 * MINUTE_IN_SECONDS );

        return $url;
    }

    /** @param array<string,mixed> $release GitHub release API object. */
    public static function compat_asset_url( string $repo, array $release ): string {
        foreach ( (array) ( $release['assets'] ?? [] ) as $asset ) {
            $name = strtolower( (string) ( $asset['name'] ?? '' ) );
            $url  = (string) ( $asset['browser_download_url'] ?? '' );
            if ( str_ends_with( $name, '-php74.zip' ) && str_starts_with( $url, 'https://github.com/' . $repo . '/releases/download/' ) ) {
                return $url;
            }
        }

        return '';
    }

    public function version_from_ref( string $ref, bool $cache_bust = false ): string|false {
        $body = $this->main_file_from_ref( $ref, $cache_bust );

        return false === $body ? false : self::extract_version( $body );
    }

    private function main_file_from_ref( string $ref, bool $cache_bust = false ): string|false {
        $url = trailingslashit( 'https://raw.githubusercontent.com/' . $this->config->github_repo() . '/' . trim( $ref, '/' ) )
            . $this->config->plugin_starter_file();

        if ( $cache_bust ) {
            $url = add_query_arg( 'cb', time(), $url );
        }

        $response = wp_remote_get( $url, $this->request_args( 15 ) );

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return false;
        }

        return (string) wp_remote_retrieve_body( $response );
    }

    public function repo_data( bool $force = false ): object|false {
        if ( null !== $this->repo_data && ! $force ) {
            return $this->repo_data;
        }

        $transient_key = $this->config->cache_key( 'github_repo' );
        $cached        = get_site_transient( $transient_key );

        if ( false !== $cached && ! $force ) {
            $this->repo_data = $cached;

            return $this->repo_data;
        }

        $response = wp_remote_get( $this->config->github_api_url(), $this->request_args( 15 ) );

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return false;
        }

        $data = json_decode( (string) wp_remote_retrieve_body( $response ) );

        if ( ! is_object( $data ) || isset( $data->message ) ) {
            return false;
        }

        set_site_transient( $transient_key, $data, 30 * MINUTE_IN_SECONDS );
        $this->repo_data = $data;

        return $this->repo_data;
    }

    public function commits( int $limit = 30 ): array {
        $limit = max( 1, min( 100, $limit ) );
        $url   = add_query_arg( 'per_page', $limit, $this->config->github_api_url() . '/commits' );

        $response = wp_remote_get( $url, $this->request_args( 15 ) );

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return [];
        }

        $commits = json_decode( (string) wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $commits ) ) {
            return [];
        }

        $versions = [];
        foreach ( $commits as $index => $commit ) {
            if ( empty( $commit['sha'] ) || empty( $commit['commit']['message'] ) ) {
                continue;
            }

            $sha        = (string) $commit['sha'];
            $message    = strtok( (string) $commit['commit']['message'], "\n" );
            $date       = ! empty( $commit['commit']['committer']['date'] ) ? date( 'M j, Y', strtotime( (string) $commit['commit']['committer']['date'] ) ) : '';
            $version    = $index < 10 ? $this->version_from_ref( $sha ) : false;
            $short_sha  = substr( $sha, 0, 7 );
            $label_text = strlen( $message ) > 40 ? substr( $message, 0, 37 ) . '...' : $message;

            if ( ! $version && preg_match( '/v?(\d+\.\d+(?:\.\d+)?)/i', $message, $matches ) ) {
                $version = $matches[1];
            }

            $versions[] = [
                'name'    => $version ? 'v' . $version . ' - ' . $label_text . ' (' . $date . ')' : $short_sha . ' - ' . $label_text . ' (' . $date . ')',
                'sha'     => $sha,
                'version' => $version ?: '',
                'zip_url' => $this->config->zip_url( $sha ),
            ];
        }

        return $versions;
    }

    public function clear_cache(): void {
        delete_site_transient( $this->config->cache_key( 'github_version' ) );
        delete_site_transient( $this->config->cache_key( 'github_repo' ) );
    }

    public static function extract_requires_php( string $plugin_file_contents ): string {
        if ( preg_match( '/^[\s\*]*Requires PHP:\s*([0-9][0-9.]*)/mi', $plugin_file_contents, $matches ) ) {
            return trim( $matches[1] );
        }

        return '';
    }

    public static function extract_version( string $plugin_file_contents ): string|false {
        if ( preg_match( '/^[\s\*]*Version:\s*(.+)$/mi', $plugin_file_contents, $matches ) ) {
            return trim( $matches[1] );
        }

        return false;
    }

    private function request_args( int $timeout ): array {
        $headers = [
            'Accept'     => 'application/vnd.github+json',
            'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
        ];

        if ( $this->config->get( 'access_token' ) ) {
            $headers['Authorization'] = 'token ' . $this->config->get( 'access_token' );
        }

        return [
            'timeout'   => $timeout,
            'sslverify' => (bool) $this->config->get( 'sslverify', true ),
            'headers'   => $headers,
        ];
    }
}
