<?php

namespace Hexa\PluginCore\PublicComponents;

/**
 * Request, URL, output, and REST helpers shared by the server-rendered public
 * components (DirectorySearch and Calendar).
 */
final class PublicComponent {
    public const REST_NAMESPACE = 'hexa-plugin-core/v1';

    /** REST parameter carrying the embedding page path; it only builds links. */
    public const PARAM_BASE = 'base';

    /** Browser and LiteSpeed lifetime of anonymous public REST responses. */
    public const REST_TTL = 60;

    /**
     * Campaign and click identifiers never carried into component links: page
     * caches store the first visitor's HTML, so kept values would leak into
     * every later visitor's links.
     */
    public const TRACKING_PARAMS = [ 'gclid', 'gbraid', 'wbraid', 'dclid', 'fbclid', 'msclkid', 'ttclid', 'twclid', 'li_fat_id', 'igshid', 'mc_cid', 'mc_eid', '_ga', '_gl', '_hsenc', '_hsmi', 'mkt_tok', 'yclid' ];

    /**
     * Raw, unslashed request parameters for a public read-only view.
     *
     * @param array<string,mixed>|null $input Explicit input; defaults to $_GET.
     * @return array<string,mixed>
     */
    public static function input( ?array $input = null ): array {
        if ( null !== $input ) {
            return $input;
        }

        $get = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only view.

        return function_exists( 'wp_unslash' ) ? (array) wp_unslash( $get ) : $get;
    }

    /** A scalar visitor parameter as a string, or $default for missing or array-shaped input. @param mixed $value */
    public static function scalar( $value, string $default = '' ): string {
        return is_scalar( $value ) ? (string) $value : $default;
    }

    /** The current request URI, for sanitize_base(). */
    public static function request_uri(): string {
        return isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- reduced by sanitize_base().
    }

    public static function can_view( bool $public ): bool {
        return $public || ( function_exists( 'current_user_can' ) && current_user_can( 'read' ) );
    }

    /** Live requests work for public profiles and for signed-in readers of private ones. */
    public static function can_go_live( bool $public ): bool {
        return $public || ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() );
    }

    /**
     * Reduces a URL or request URI to a same-site root-relative path (max 255
     * characters) plus the page's own query arguments, minus the component's.
     *
     * @param string[] $own Component parameter names to drop.
     */
    public static function sanitize_base( string $url, array $own ): string {
        $path = (string) preg_replace( '#[^A-Za-z0-9/_\-.~%]#', '', (string) parse_url( $url, PHP_URL_PATH ) );
        if ( '' === $path || '/' !== $path[0] || str_starts_with( $path, '//' ) ) {
            $path = '/';
        }
        $path = substr( $path, 0, 255 );

        $args = [];
        parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $args );
        foreach ( array_merge( $own, [ self::PARAM_BASE ], self::TRACKING_PARAMS ) as $name ) {
            unset( $args[ $name ] );
        }
        foreach ( array_keys( $args ) as $name ) {
            if ( is_string( $name ) && str_starts_with( strtolower( $name ), 'utm_' ) ) {
                unset( $args[ $name ] );
            }
        }
        $args = array_slice( array_filter( $args, static fn( $value, $key ): bool => is_scalar( $value ) && is_string( $key ) && strlen( $key ) <= 64 && strlen( (string) $value ) <= 128, ARRAY_FILTER_USE_BOTH ), 0, 8, true );

        return $path . ( [] !== $args ? '?' . http_build_query( $args ) : '' );
    }

    /** @return array{0:string,1:array<string,string>} Path and the page's own query arguments. */
    public static function split_base( string $base ): array {
        $args = [];
        parse_str( (string) parse_url( $base, PHP_URL_QUERY ), $args );

        return [ (string) parse_url( $base, PHP_URL_PATH ) ?: '/', array_map( 'strval', array_filter( $args, 'is_scalar' ) ) ];
    }

    /** A link to the base page with the component's arguments applied. @param array<string,mixed> $args */
    public static function url( string $base, array $args ): string {
        [ $path, $own ] = self::split_base( $base );
        $query = http_build_query( array_merge( $own, $args ) );

        return $path . ( '' !== $query ? '?' . $query : '' );
    }

    /** Hidden inputs that keep the page's own query arguments on a GET form. @param array<string,string> $args */
    public static function hidden_inputs( array $args ): string {
        $html = '';
        foreach ( $args as $name => $value ) {
            $html .= '<input type="hidden" name="' . esc_attr( (string) $name ) . '" value="' . esc_attr( (string) $value ) . '">';
        }

        return $html;
    }

    /** Makes already-escaped text inert to do_shortcode(); zero-padded entities survive unescape_invalid_shortcodes(). */
    public static function inert( string $html ): string {
        return strtr( $html, [ '[' => '&#091;', ']' => '&#093;' ] );
    }

    public static function endpoint( string $route ): string {
        return function_exists( 'rest_url' ) ? rest_url( self::REST_NAMESPACE . '/' . ltrim( $route, '/' ) ) : '';
    }

    /** Data attributes that let a component's script call its REST route. */
    public static function live_attributes( string $prefix, string $route, bool $public ): string {
        if ( ! self::can_go_live( $public ) ) {
            return '';
        }

        $html = ' data-' . $prefix . '-endpoint="' . esc_url( self::endpoint( $route ) ) . '"';
        if ( ! $public && function_exists( 'wp_create_nonce' ) ) {
            $html .= ' data-' . $prefix . '-nonce="' . esc_attr( wp_create_nonce( 'wp_rest' ) ) . '"';
        }

        return $html;
    }

    /**
     * Wraps a public payload. Anonymous responses are browser-cacheable for
     * REST_TTL seconds and cap LiteSpeed Cache's REST lifetime to match.
     *
     * @param array<string,mixed> $payload
     * @return \WP_REST_Response|mixed
     */
    public static function rest_response( array $payload, bool $public ) {
        $response = rest_ensure_response( $payload );
        if ( $public && ! is_user_logged_in() ) {
            $response->header( 'Cache-Control', 'public, max-age=' . self::REST_TTL );
            do_action( 'litespeed_control_set_ttl', self::REST_TTL ); // No-op without LiteSpeed Cache.
        }

        return $response;
    }
}
