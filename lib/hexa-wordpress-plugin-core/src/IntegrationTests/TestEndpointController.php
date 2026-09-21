<?php

namespace Hexa\PluginCore\IntegrationTests;

final class TestEndpointController {
    public const PAGE_SLUG = 'hexa-integration-tests';
    private static bool $registered = false;

    public static function register_once(): void {
        if ( self::$registered || ! function_exists( 'add_action' ) ) {
            return;
        }
        self::$registered = true;
        add_action( 'admin_menu', [ self::class, 'register_menu' ] );
    }

    public static function register_menu(): void {
        $hook = add_management_page(
            'Hexa Integration Tests',
            'Hexa Integration Tests',
            'manage_options',
            self::PAGE_SLUG,
            [ self::class, 'render_page' ]
        );
        if ( $hook ) {
            add_action( 'load-' . $hook, [ self::class, 'maybe_render_json' ] );
        }
    }

    public static function maybe_render_json(): void {
        if ( 'json' !== self::query( 'format' ) ) {
            return;
        }
        self::authorize();
        $report = ( new TestRunner() )->run( self::filters() );
        if ( function_exists( 'wp_send_json' ) ) {
            wp_send_json( $report, 'pass' === $report['status'] ? 200 : 500 );
        }
        header( 'Content-Type: application/json; charset=utf-8' );
        http_response_code( 'pass' === $report['status'] ? 200 : 500 );
        echo json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        exit;
    }

    public static function render_page(): void {
        self::authorize();
        $report = ( new TestRunner() )->run( self::filters() );
        echo ( new TestReportRenderer() )->render( $report, [ 'url' => self::url() ] );
    }

    public static function url( array $args = [] ): string {
        $base = function_exists( 'admin_url' ) ? admin_url( 'tools.php?page=' . self::PAGE_SLUG ) : 'tools.php?page=' . self::PAGE_SLUG;
        return $args && function_exists( 'add_query_arg' ) ? add_query_arg( $args, $base ) : $base;
    }

    /** @return array<string,string> */
    private static function filters(): array {
        return [ 'host' => self::query( 'host' ), 'test' => self::query( 'test' ) ];
    }

    private static function authorize(): void {
        if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
            if ( function_exists( 'wp_die' ) ) {
                wp_die( 'You do not have permission to run Hexa integration tests.', 'Access denied', [ 'response' => 403 ] );
            }
            throw new \RuntimeException( 'Access denied.' );
        }
    }

    private static function query( string $key ): string {
        $value = $_GET[ $key ] ?? '';
        if ( is_array( $value ) ) {
            return '';
        }
        $value = function_exists( 'wp_unslash' ) ? wp_unslash( (string) $value ) : (string) $value;
        return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : trim( strip_tags( $value ) );
    }
}
