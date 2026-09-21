<?php

namespace Hexa\PluginCore\CredentialVault;

final class CredentialStore {
    public function store( string $slug, string $key_name, string $value ): bool {
        if ( ! function_exists( 'update_option' ) ) {
            return false;
        }

        return update_option( $this->option_key( $slug, $key_name ), $this->encrypt( $value ), false );
    }

    public function get( string $slug, string $key_name ): ?string {
        if ( ! function_exists( 'get_option' ) ) {
            return null;
        }

        $stored = get_option( $this->option_key( $slug, $key_name ), null );

        if ( ! is_array( $stored ) ) {
            return null;
        }

        return $this->decrypt( $stored );
    }

    public function exists( string $slug, string $key_name ): bool {
        if ( ! function_exists( 'get_option' ) ) {
            return false;
        }

        $stored = get_option( $this->option_key( $slug, $key_name ), null );

        return is_array( $stored ) && ! empty( $stored['value'] );
    }

    public function delete( string $slug, string $key_name ): bool {
        if ( ! function_exists( 'delete_option' ) ) {
            return false;
        }

        return delete_option( $this->option_key( $slug, $key_name ) );
    }

    public function get_masked( string $slug, string $key_name, int $show_last = 4 ): string {
        return $this->mask( $this->get( $slug, $key_name ), $show_last );
    }

    public function mask( ?string $value, int $show_last = 4 ): string {
        if ( null === $value || '' === $value ) {
            return '';
        }

        $length = strlen( $value );

        if ( $length <= $show_last ) {
            return str_repeat( '*', 8 );
        }

        return str_repeat( '*', 8 ) . substr( $value, -1 * $show_last );
    }

    public function option_key( string $slug, string $key_name ): string {
        $safe_slug = function_exists( 'sanitize_key' ) ? sanitize_key( $slug ) : preg_replace( '/[^a-z0-9_\\-]/', '', strtolower( $slug ) );
        $safe_key  = function_exists( 'sanitize_key' ) ? sanitize_key( $key_name ) : preg_replace( '/[^a-z0-9_\\-]/', '', strtolower( $key_name ) );

        return 'hpc_cred_' . $safe_slug . '_' . $safe_key;
    }

    /**
     * @return array<string, string>
     */
    private function encrypt( string $value ): array {
        if ( function_exists( 'openssl_encrypt' ) ) {
            $key    = $this->secret_key();
            $iv     = random_bytes( 16 );
            $cipher = openssl_encrypt( $value, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

            if ( false !== $cipher ) {
                $mac = hash_hmac( 'sha256', $iv . $cipher, $key, true );

                return [
                    'version' => '1',
                    'alg'     => 'aes-256-cbc-hmac-sha256',
                    'iv'      => base64_encode( $iv ),
                    'value'   => base64_encode( $cipher ),
                    'mac'     => base64_encode( $mac ),
                ];
            }
        }

        return [
            'version' => '1',
            'alg'     => 'base64',
            'value'   => base64_encode( $value ),
        ];
    }

    private function decrypt( array $stored ): ?string {
        $alg = (string) ( $stored['alg'] ?? '' );

        if ( 'base64' === $alg ) {
            $decoded = base64_decode( (string) ( $stored['value'] ?? '' ), true );

            return false === $decoded ? null : $decoded;
        }

        if ( 'aes-256-cbc-hmac-sha256' !== $alg || ! function_exists( 'openssl_decrypt' ) ) {
            return null;
        }

        $iv     = base64_decode( (string) ( $stored['iv'] ?? '' ), true );
        $cipher = base64_decode( (string) ( $stored['value'] ?? '' ), true );
        $mac    = base64_decode( (string) ( $stored['mac'] ?? '' ), true );

        if ( false === $iv || false === $cipher || false === $mac ) {
            return null;
        }

        $key      = $this->secret_key();
        $expected = hash_hmac( 'sha256', $iv . $cipher, $key, true );

        if ( ! hash_equals( $expected, $mac ) ) {
            return null;
        }

        $plain = openssl_decrypt( $cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

        return false === $plain ? null : $plain;
    }

    private function secret_key(): string {
        $salt = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : '';

        if ( '' === $salt && defined( 'AUTH_KEY' ) ) {
            $salt = (string) AUTH_KEY;
        }

        if ( '' === $salt ) {
            $salt = 'hexa-plugin-core';
        }

        return hash( 'sha256', $salt, true );
    }
}
