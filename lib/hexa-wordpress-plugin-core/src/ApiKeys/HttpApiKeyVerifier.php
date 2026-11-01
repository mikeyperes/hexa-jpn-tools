<?php

declare( strict_types=1 );

namespace Hexa\PluginCore\ApiKeys;

/**
 * A key test that calls one read-only endpoint of the service with the key.
 * A 2xx answer means the key is active, 401 or 403 means it is not; a
 * service extends it with its endpoint, header and what to show when active.
 */
abstract class HttpApiKeyVerifier implements ApiKeyVerifier {
    abstract protected function endpoint(): string;

    /** @return array<string,string> */
    protected function headers( string $key ): array {
        return [ 'Authorization' => 'Bearer ' . $key ];
    }

    /** Checks the key's shape before any request; return a message to reject it, or '' to go on. */
    protected function shape_error( string $key ): string {
        return '';
    }

    /** @param array<string,mixed> $body */
    protected function on_active( array $body, string $key ): ApiKeyCheck {
        return ApiKeyCheck::active( 'The key is active.' );
    }

    public function verify( string $key ): ApiKeyCheck {
        $key = trim( $key );
        if ( '' === $key ) {
            return ApiKeyCheck::missing();
        }
        $shape = $this->shape_error( $key );
        if ( '' !== $shape ) {
            return ApiKeyCheck::invalid( $shape );
        }
        $response = wp_remote_get( $this->endpoint(), [ 'timeout' => 15, 'headers' => $this->headers( $key ) ] );
        if ( is_wp_error( $response ) ) {
            return ApiKeyCheck::error( 'The service could not be reached: ' . $response->get_error_message() );
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        $body = is_array( $body ) ? $body : [];
        if ( $code >= 200 && $code < 300 ) {
            return $this->on_active( $body, $key );
        }
        if ( 401 === $code || 403 === $code ) {
            $reason = is_array( $body['error'] ?? null ) ? (string) ( $body['error']['message'] ?? '' ) : '';
            return ApiKeyCheck::invalid( 'The service rejected the key' . ( '' !== $reason ? ': ' . $reason : '.' ) );
        }

        return ApiKeyCheck::error( 'The service answered with status ' . $code . '.' );
    }
}
