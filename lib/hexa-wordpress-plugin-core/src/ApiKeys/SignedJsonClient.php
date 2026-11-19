<?php

declare( strict_types=1 );

namespace Hexa\PluginCore\ApiKeys;

/** WordPress transport for nonce-protected, domain-bound HMAC JSON calls. */
final class SignedJsonClient {
    private string $header_prefix;

    public function __construct( string $header_prefix ) {
        $this->header_prefix = $header_prefix;
    }

    /** @return array|\WP_Error The ordinary WordPress HTTP response. */
    public function post( string $url, array $payload, string $key, string $domain, int $timeout ) {
        $parts = wp_parse_url( $url );
        if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) || empty( $parts['host'] )
            || isset( $parts['query'] ) || isset( $parts['fragment'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
            return new \WP_Error( 'hexa_signed_url_invalid', 'Signed requests require an HTTPS address without credentials, a query or a fragment.' );
        }
        $domain = $this->domain( $domain );
        if ( '' === $key || '' === $domain ) {
            return new \WP_Error( 'hexa_signed_identity_missing', 'A site key and domain are required.' );
        }
        $body = wp_json_encode( empty( $payload ) ? (object) [] : $payload );
        if ( false === $body ) {
            return new \WP_Error( 'hexa_signed_json_invalid', 'The request could not be encoded as JSON.' );
        }
        try {
            $nonce = bin2hex( random_bytes( 32 ) );
        } catch ( \Throwable $exception ) {
            return new \WP_Error( 'hexa_signed_nonce_failed', 'A secure request nonce could not be generated.' );
        }
        $timestamp = (string) time();
        $headers = $this->headers( $parts['path'] ?? '/', $body, $key, $domain, $timestamp, $nonce );

        return wp_remote_post( $url, [
            'timeout' => $timeout,
            'redirection' => 0,
            'sslverify' => true,
            'cookies' => [],
            'headers' => $headers,
            'body' => $body,
        ] );
    }

    private function domain( string $value ): string {
        $url = false === strpos( $value, '://' ) ? 'https://' . $value : $value;
        $host = strtolower( rtrim( (string) wp_parse_url( $url, PHP_URL_HOST ), '.' ) );
        $host = (string) preg_replace( '/^www\./', '', $host );
        if ( filter_var( $host, FILTER_VALIDATE_IP ) || ! filter_var( $host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) || false === strpos( $host, '.' ) ) {
            return '';
        }

        return $host;
    }

    private function headers( string $path, string $body, string $key, string $domain, string $timestamp, string $nonce ): array {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            $this->header_prefix . 'Key' => $key,
            $this->header_prefix . 'Domain' => $domain,
            $this->header_prefix . 'Timestamp' => $timestamp,
            $this->header_prefix . 'Nonce' => $nonce,
            $this->header_prefix . 'Signature' => HmacSignature::sign( 'POST', $path, $timestamp, $nonce, $domain, $body, $key ),
        ];
    }
}
