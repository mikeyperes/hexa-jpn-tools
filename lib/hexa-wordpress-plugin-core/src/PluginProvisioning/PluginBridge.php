<?php

namespace Hexa\PluginCore\PluginProvisioning;

/**
 * REST bridge that installs or updates a plugin from its GitHub release.
 *
 * Any host plugin that bundles Core switches it on with
 * {@see PluginBridge::register()} (safe to call from every host). After one
 * Hexa plugin is on a site, an administrator's Application Password can then
 * install or update every other allowed plugin without wp-admin:
 *
 *     GET  /wp-json/hexa-plugin-core/v1/plugins/github?repo=owner/name
 *     POST /wp-json/hexa-plugin-core/v1/plugins/github  {"repo": "owner/name", "tag": "latest", "activate": true}
 *
 * Only repositories whose owner is allowed (filter
 * `hexa_plugin_core/plugin_bridge_owners`, default `mikeyperes`) and only a
 * release's attached `.zip` asset are accepted. Needs install_plugins,
 * update_plugins and activate_plugins; honours DISALLOW_FILE_MODS.
 */
final class PluginBridge {
    public const REST_NAMESPACE = 'hexa-plugin-core/v1';
    public const DEFAULT_OWNERS = [ 'mikeyperes' ];
    /** Oldest PHP the unmodified (non -php74) release zips run on. */
    public const SOURCE_PHP = '8.2';

    private static bool $registered = false;

    private function __construct() {}

    public static function register(): void {
        if ( self::$registered || ! function_exists( 'add_action' ) ) {
            return;
        }
        self::$registered = true;
        add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
    }

    public static function register_routes(): void {
        register_rest_route(
            self::REST_NAMESPACE,
            '/plugins/github',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [ self::class, 'status' ],
                    'permission_callback' => [ self::class, 'can_manage' ],
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [ self::class, 'install' ],
                    'permission_callback' => [ self::class, 'can_manage' ],
                ],
            ]
        );
    }

    public static function can_manage(): bool|\WP_Error {
        if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
            return new \WP_Error( 'hexa_plugin_bridge_file_mods_disabled', 'Plugin installation is disabled on this site (DISALLOW_FILE_MODS).', [ 'status' => 403 ] );
        }

        return current_user_can( 'install_plugins' ) && current_user_can( 'update_plugins' ) && current_user_can( 'activate_plugins' );
    }

    /** @return array<string,mixed>|\WP_Error */
    public static function status( \WP_REST_Request $request ) {
        $repo = self::allowed_repo( (string) $request->get_param( 'repo' ) );
        if ( is_wp_error( $repo ) ) {
            return $repo;
        }

        return [
            'repo'      => $repo,
            'installed' => self::installed( self::repo_folder( $repo ) ),
        ];
    }

    /** @return array<string,mixed>|\WP_Error */
    public static function install( \WP_REST_Request $request ) {
        $repo = self::allowed_repo( (string) $request->get_param( 'repo' ) );
        if ( is_wp_error( $repo ) ) {
            return $repo;
        }
        $tag      = trim( (string) ( $request->get_param( 'tag' ) ?? 'latest' ) );
        $activate = null === $request->get_param( 'activate' ) ? true : rest_sanitize_boolean( $request->get_param( 'activate' ) );

        $release = self::release( $repo, '' === $tag ? 'latest' : $tag );
        if ( is_wp_error( $release ) ) {
            return $release;
        }

        $before = self::installed( $release['folder'] );
        $result = self::upgrade( $release['zip_url'] );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $plugin_file = PluginProvisioner::find_plugin_file_by_folder( $release['folder'] );
        if ( '' === $plugin_file ) {
            return new \WP_Error( 'hexa_plugin_bridge_folder_mismatch', 'The release zip did not install into ' . $release['folder'] . '/.', [ 'status' => 500 ] );
        }
        if ( $activate ) {
            $activated = PluginProvisioner::activate_plugin_file( $plugin_file );
            if ( is_wp_error( $activated ) ) {
                return $activated;
            }
        }

        return [
            'repo'   => $repo,
            'tag'    => $release['tag'],
            'before' => $before,
            'after'  => self::installed( $release['folder'] ),
        ];
    }

    /** Owner/name for an allowed owner, or an error. */
    public static function allowed_repo( string $repo_or_url ): string|\WP_Error {
        $repo = PluginProvisioner::normalize_github_repo( $repo_or_url );
        if ( 1 !== preg_match( '#^([A-Za-z0-9-]+)/([A-Za-z0-9._-]+)$#', $repo, $parts ) ) {
            return new \WP_Error( 'hexa_plugin_bridge_invalid_repo', 'Give the GitHub repository as owner/name.', [ 'status' => 422 ] );
        }
        $owners = array_map( 'strtolower', (array) apply_filters( 'hexa_plugin_core/plugin_bridge_owners', self::DEFAULT_OWNERS ) );
        if ( ! in_array( strtolower( $parts[1] ), $owners, true ) ) {
            return new \WP_Error( 'hexa_plugin_bridge_owner_not_allowed', 'Repositories from ' . $parts[1] . ' are not allowed on this site.', [ 'status' => 403 ] );
        }

        return $repo;
    }

    /**
     * The release's `.zip` for this PHP: the `-php74.zip` build (from
     * bin/build-php74-release.sh) on PHP older than {@see self::SOURCE_PHP},
     * otherwise the normal zip. The plugin folder is the zip name without its
     * version and build suffix, which is how Hexa release zips are named
     * (`<folder>-<version>[-php74].zip`, prefixed `<folder>/`).
     *
     * @param array<string,mixed> $release GitHub release API object.
     * @return array{tag:string,zip_url:string,folder:string}|\WP_Error
     */
    public static function release_asset( string $repo, array $release, string $php_version = PHP_VERSION ) {
        $normal = null;
        $compat = null;
        foreach ( (array) ( $release['assets'] ?? [] ) as $asset ) {
            $name = (string) ( $asset['name'] ?? '' );
            $url  = (string) ( $asset['browser_download_url'] ?? '' );
            if ( ! str_ends_with( strtolower( $name ), '.zip' ) || ! str_starts_with( $url, 'https://github.com/' . $repo . '/releases/download/' ) ) {
                continue;
            }
            if ( str_ends_with( strtolower( $name ), '-php74.zip' ) ) {
                $compat = $compat ?? [ $name, $url ];
            } else {
                $normal = $normal ?? [ $name, $url ];
            }
        }

        $pick = version_compare( $php_version, self::SOURCE_PHP, '<' ) ? ( $compat ?? $normal ) : ( $normal ?? $compat );
        if ( null === $pick ) {
            return new \WP_Error( 'hexa_plugin_bridge_no_zip_asset', 'The GitHub release of ' . $repo . ' has no attached .zip asset.', [ 'status' => 424 ] );
        }

        $base   = (string) preg_replace( '/-php74$/i', '', substr( $pick[0], 0, -4 ) );
        $folder = (string) preg_replace( '/-v?\d+(?:\.\d+)*(?:[-+][A-Za-z0-9.]+)?$/', '', $base );

        return [ 'tag' => (string) ( $release['tag_name'] ?? '' ), 'zip_url' => $pick[1], 'folder' => '' !== $folder ? $folder : explode( '/', $repo )[1] ];
    }

    /** @return array{tag:string,zip_url:string,folder:string}|\WP_Error */
    private static function release( string $repo, string $tag ) {
        $path     = 'latest' === $tag ? 'releases/latest' : 'releases/tags/' . rawurlencode( $tag );
        $response = wp_remote_get(
            'https://api.github.com/repos/' . $repo . '/' . $path,
            [ 'timeout' => 20, 'headers' => [ 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'Hexa-Plugin-Core' ] ]
        );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        $data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        if ( 200 !== $code || ! is_array( $data ) ) {
            return new \WP_Error( 'hexa_plugin_bridge_release_missing', 'GitHub release ' . $tag . ' of ' . $repo . ' was not found (HTTP ' . $code . ').', [ 'status' => 424 ] );
        }

        return self::release_asset( $repo, $data );
    }

    private static function upgrade( string $zip_url ): bool|\WP_Error {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';

        $filesystem = PluginProvisioner::prepare_filesystem();
        if ( is_wp_error( $filesystem ) ) {
            return $filesystem;
        }

        $skin     = new \WP_Ajax_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader( $skin );
        $result   = $upgrader->install( $zip_url, [ 'overwrite_package' => true ] );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        if ( $skin->get_errors()->has_errors() ) {
            return new \WP_Error( 'hexa_plugin_bridge_install_failed', $skin->get_error_messages(), [ 'status' => 500 ] );
        }
        if ( true !== $result ) {
            return new \WP_Error( 'hexa_plugin_bridge_install_failed', 'WordPress could not install the release zip.', [ 'status' => 500 ] );
        }
        wp_cache_delete( 'plugins', 'plugins' );

        return true;
    }

    /** @return array{file:string,version:string,active:bool}|null */
    private static function installed( string $folder ): ?array {
        $file = PluginProvisioner::find_plugin_file_by_folder( $folder );
        if ( '' === $file ) {
            return null;
        }
        $status = PluginProvisioner::plugin_status_by_file( $file );

        return [
            'file'    => $file,
            'version' => (string) ( $status['version'] ?? '' ),
            'active'  => ! empty( $status['active'] ) || ! empty( $status['network_active'] ),
        ];
    }

    private static function repo_folder( string $repo ): string {
        return explode( '/', $repo )[1];
    }
}
