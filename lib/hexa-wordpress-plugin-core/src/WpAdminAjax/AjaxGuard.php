<?php

namespace Hexa\PluginCore\WpAdminAjax;

use Throwable;

final class AjaxGuard {
    public const DEFAULT_NONCE_FIELD = 'nonce';

    private function __construct() {}

    public static function create_nonce( string $action ): string {
        return function_exists( 'wp_create_nonce' ) ? wp_create_nonce( $action ) : '';
    }

    public static function request_nonce( string $field = self::DEFAULT_NONCE_FIELD ): ?string {
        $candidates = [ $field, '_ajax_nonce', '_wpnonce' ];

        foreach ( $candidates as $candidate ) {
            if ( isset( $_POST[ $candidate ] ) ) {
                $value = function_exists( 'wp_unslash' ) ? wp_unslash( $_POST[ $candidate ] ) : $_POST[ $candidate ];
                return is_string( $value ) ? $value : null;
            }

            if ( isset( $_GET[ $candidate ] ) ) {
                $value = function_exists( 'wp_unslash' ) ? wp_unslash( $_GET[ $candidate ] ) : $_GET[ $candidate ];
                return is_string( $value ) ? $value : null;
            }

            if ( isset( $_REQUEST[ $candidate ] ) ) {
                $value = function_exists( 'wp_unslash' ) ? wp_unslash( $_REQUEST[ $candidate ] ) : $_REQUEST[ $candidate ];
                return is_string( $value ) ? $value : null;
            }
        }

        return null;
    }

    public static function verify_nonce( string $action, ?string $nonce = null, string $field = self::DEFAULT_NONCE_FIELD ): bool {
        if ( null === $nonce ) {
            $nonce = self::request_nonce( $field );
        }

        if ( ! is_string( $nonce ) || '' === $nonce || ! function_exists( 'wp_verify_nonce' ) ) {
            return false;
        }

        $nonce = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $nonce ) : trim( $nonce );

        return false !== wp_verify_nonce( $nonce, $action );
    }

    public static function require_nonce_or_error(
        string $action,
        string $field = self::DEFAULT_NONCE_FIELD,
        string $message = 'Security check failed. Please refresh the page and try again.'
    ): void {
        if ( self::verify_nonce( $action, null, $field ) ) {
            return;
        }

        self::send_json_error(
            [
                'message' => $message,
                'code'    => 'invalid_nonce',
            ]
        );
    }

    public static function require_capability_or_error(
        string $capability,
        string $message = 'Unauthorized: You do not have permission to perform this action.'
    ): void {
        if ( function_exists( 'current_user_can' ) && current_user_can( $capability ) ) {
            return;
        }

        self::send_json_error(
            [
                'message' => $message,
                'code'    => 'unauthorized',
            ],
            403
        );
    }

    /**
     * @param callable():mixed $callback
     * @param array<string,mixed> $args
     */
    public static function handle( callable $callback, array $args = [] ): void {
        $capability   = isset( $args['capability'] ) ? (string) $args['capability'] : 'manage_options';
        $verify_nonce = array_key_exists( 'verify_nonce', $args ) ? (bool) $args['verify_nonce'] : true;
        $nonce_action = isset( $args['nonce_action'] ) ? (string) $args['nonce_action'] : '';
        $nonce_field  = isset( $args['nonce_field'] ) ? (string) $args['nonce_field'] : self::DEFAULT_NONCE_FIELD;
        $logger       = isset( $args['logger'] ) && is_callable( $args['logger'] ) ? $args['logger'] : null;

        try {
            self::require_capability_or_error( $capability );

            if ( $verify_nonce ) {
                self::require_nonce_or_error( $nonce_action, $nonce_field );
            }

            self::send_json_success( call_user_func( $callback ) );
        } catch ( Throwable $throwable ) {
            if ( $logger ) {
                call_user_func( $logger, $throwable );
            } elseif ( function_exists( 'error_log' ) ) {
                error_log( '[Hexa Plugin Core] AJAX error: ' . $throwable->getMessage() );
            }

            self::send_json_error(
                [
                    'message' => 'An error occurred: ' . $throwable->getMessage(),
                    'code'    => 'exception',
                ]
            );
        }
    }

    /**
     * @param mixed $data
     */
    private static function send_json_success( mixed $data ): void {
        if ( function_exists( 'wp_send_json_success' ) ) {
            wp_send_json_success( $data );
        }
    }

    /**
     * @param mixed $data
     */
    private static function send_json_error( mixed $data, int $status_code = 200 ): void {
        if ( function_exists( 'wp_send_json_error' ) ) {
            wp_send_json_error( $data, $status_code );
        }
    }
}
