<?php

namespace Hexa\PluginCore\DirectorySearch;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

/**
 * Wires the `[hexa_directory id="…"]` shortcode and the public read-only REST
 * endpoint `GET /wp-json/hexa-plugin-core/v1/directory/{profile}`.
 *
 * Several host plugins may add this module; hooks register once per request.
 */
final class DirectorySearchModule implements ModuleInterface {
    public const SHORTCODE = 'hexa_directory';

    private static bool $registered = false;

    public function register(): void {
        if ( self::$registered || ! function_exists( 'add_action' ) ) {
            return;
        }
        self::$registered = true;

        add_shortcode( self::SHORTCODE, [ $this, 'shortcode' ] );
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /** @param array<string,mixed>|string $attributes */
    public function shortcode( $attributes = [] ): string {
        $attributes = shortcode_atts( [ 'id' => '' ], is_array( $attributes ) ? $attributes : [], self::SHORTCODE );

        return ( new DirectorySearchRenderer() )->render( (string) $attributes['id'] );
    }

    public function register_routes(): void {
        register_rest_route(
            DirectorySearchRenderer::REST_NAMESPACE,
            '/directory/(?P<profile>[a-z0-9_\-]+)',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'rest_search' ],
                'permission_callback' => [ $this, 'rest_permission' ],
            ]
        );
    }

    /** @param \WP_REST_Request $request */
    public function rest_permission( $request ): bool {
        $profile = DirectorySearchRegistry::get( (string) $request['profile'] );
        if ( null === $profile ) {
            return false;
        }

        return $profile['public'] || current_user_can( 'read' );
    }

    /** @param \WP_REST_Request $request */
    public function rest_search( $request ) {
        $profile = DirectorySearchRegistry::get( (string) $request['profile'] );
        if ( null === $profile ) {
            return new \WP_Error( 'hexa_directory_not_found', 'Unknown directory.', [ 'status' => 404 ] );
        }

        $params  = (array) $request->get_query_params();
        $input   = DirectorySearchRequest::from_input( $params, $profile );
        $base    = DirectorySearchRenderer::sanitize_base( (string) ( $params['base'] ?? '/' ) );
        $payload = ( new DirectorySearchRenderer() )->results( $profile, $input, $base );

        $response = rest_ensure_response( $payload );
        if ( $profile['public'] && ! is_user_logged_in() ) {
            $response->header( 'Cache-Control', 'public, max-age=60' );
            // Cap LiteSpeed Cache's server-side REST lifetime to the advertised 60 s (no-op without LSCWP).
            do_action( 'litespeed_control_set_ttl', 60 );
        }

        return $response;
    }
}
