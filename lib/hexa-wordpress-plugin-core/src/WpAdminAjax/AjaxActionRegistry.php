<?php

namespace Hexa\PluginCore\WpAdminAjax;

use Throwable;

final class AjaxActionRegistry {
    /**
     * @var array<string,mixed>
     */
    private array $defaults;

    /**
     * @param array<string,mixed> $defaults
     */
    public function __construct( array $defaults = [] ) {
        $this->defaults = array_merge(
            [
                'action_prefix'      => '',
                'capability'         => 'manage_options',
                'nonce_action'       => '',
                'nonce_field'        => AjaxGuard::DEFAULT_NONCE_FIELD,
                'verify_nonce'       => true,
                'nopriv'             => false,
                'permission_message' => 'Permission denied.',
                'nonce_message'      => 'Security check failed. Please refresh the page and try again.',
                'logger'             => null,
            ],
            $defaults
        );
    }

    /**
     * @param array<string,mixed> $defaults
     */
    public static function create( array $defaults = [] ): self {
        return new self( $defaults );
    }

    /**
     * @param array<string,mixed> $actions
     */
    public function register( array $actions ): void {
        foreach ( $actions as $key => $config ) {
            $config = $this->normalize_action_config( (string) $key, $config );
            $this->register_single_action( $config );
        }
    }

    /**
     * @param array<string,mixed> $config
     */
    public function register_single_action( array $config ): void {
        $action = isset( $config['action'] ) ? (string) $config['action'] : '';
        if ( '' === $action || empty( $config['callback'] ) || ! is_callable( $config['callback'] ) ) {
            return;
        }

        add_action(
            'wp_ajax_' . $action,
            function () use ( $config ): void {
                $this->dispatch( $config );
            }
        );

        if ( ! empty( $config['nopriv'] ) ) {
            add_action(
                'wp_ajax_nopriv_' . $action,
                function () use ( $config ): void {
                    $this->dispatch( $config );
                }
            );
        }
    }

    /**
     * @param array<string,mixed> $config
     */
    public function dispatch( array $config ): void {
        try {
            $capability = isset( $config['capability'] ) ? (string) $config['capability'] : '';
            if ( '' !== $capability ) {
                AjaxGuard::require_capability_or_error(
                    $capability,
                    isset( $config['permission_message'] ) ? (string) $config['permission_message'] : 'Permission denied.'
                );
            }

            if ( ! empty( $config['verify_nonce'] ) ) {
                AjaxGuard::require_nonce_or_error(
                    isset( $config['nonce_action'] ) ? (string) $config['nonce_action'] : '',
                    isset( $config['nonce_field'] ) ? (string) $config['nonce_field'] : AjaxGuard::DEFAULT_NONCE_FIELD,
                    isset( $config['nonce_message'] ) ? (string) $config['nonce_message'] : 'Security check failed. Please refresh the page and try again.'
                );
            }

            $request = new AjaxRequest();
            $result  = call_user_func( $config['callback'], $request, $config );

            if ( class_exists( '\WP_Error' ) && $result instanceof \WP_Error ) {
                throw new AjaxFailure(
                    $result->get_error_message(),
                    400,
                    $result->get_error_code() ?: 'wp_error'
                );
            }

            $this->send_success( null === $result ? [] : $result );
        } catch ( AjaxFailure $failure ) {
            $this->send_error( $failure->payload(), $failure->status_code() );
        } catch ( Throwable $throwable ) {
            $this->log_throwable( $throwable, $config );
            $this->send_error(
                [
                    'message' => 'An error occurred: ' . $throwable->getMessage(),
                    'code'    => 'exception',
                ],
                500
            );
        }
    }

    /**
     * @param mixed $config
     * @return array<string,mixed>
     */
    private function normalize_action_config( string $key, mixed $config ): array {
        if ( is_callable( $config ) ) {
            $config = [ 'callback' => $config ];
        }

        $config = is_array( $config ) ? $config : [];
        $config = array_merge( $this->defaults, $config );

        $action = isset( $config['action'] ) ? (string) $config['action'] : $key;
        $prefix = isset( $config['action_prefix'] ) ? trim( (string) $config['action_prefix'], '_' ) : '';

        if ( '' !== $prefix && 0 !== strpos( $action, $prefix . '_' ) ) {
            $action = $prefix . '_' . $action;
        }

        $config['action'] = $action;

        return $config;
    }

    /**
     * @param mixed $data
     */
    private function send_success( mixed $data ): void {
        if ( function_exists( 'wp_send_json_success' ) ) {
            wp_send_json_success( $data );
        }
    }

    /**
     * @param mixed $data
     */
    private function send_error( mixed $data, int $status_code ): void {
        if ( function_exists( 'wp_send_json_error' ) ) {
            wp_send_json_error( $data, $status_code );
        }
    }

    /**
     * @param array<string,mixed> $config
     */
    private function log_throwable( Throwable $throwable, array $config ): void {
        if ( isset( $config['logger'] ) && is_callable( $config['logger'] ) ) {
            call_user_func( $config['logger'], $throwable );
            return;
        }

        if ( function_exists( 'error_log' ) ) {
            error_log( '[Hexa Plugin Core] AJAX error: ' . $throwable->getMessage() );
        }
    }
}
