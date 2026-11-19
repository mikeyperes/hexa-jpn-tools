<?php

namespace Hexa\PluginCore\PressReleaseProfiles;

/** The public, read-only press-release profile protocol shared by consumers. */
final class PressReleaseProfilesClient {
    public function author( string $origin, int $id ): array {
        return $this->get( $origin, '/authors/' . absint( $id ) );
    }

    public function find_author( string $origin, string $profile_url ): array {
        return $this->get( $origin, '/authors', [ 'profile_url' => $profile_url ] );
    }

    public function author_releases( string $origin, int $author_id, int $page = 1 ): array {
        return $this->get( $origin, '/authors/' . absint( $author_id ) . '/releases', [ 'page' => max( 1, $page ) ] );
    }

    public function search_people( string $origin, string $query, int $page = 1 ): array {
        return $this->get( $origin, '/verified-profiles', [ 'q' => $query, 'page' => max( 1, $page ) ] );
    }

    public function person( string $origin, int $id ): array {
        return $this->get( $origin, '/verified-profiles/' . absint( $id ) );
    }

    public function releases( string $origin, int $profile_id, int $page = 1 ): array {
        return $this->get( $origin, '/verified-profiles/' . absint( $profile_id ) . '/releases', [ 'page' => max( 1, $page ) ] );
    }

    private function get( string $origin, string $path, array $query = [] ): array {
        $parts = wp_parse_url( $origin );
        if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) || empty( $parts['host'] )
            || isset( $parts['user'] ) || isset( $parts['pass'] )
            || isset( $parts['query'] ) || isset( $parts['fragment'] )
            || ! in_array( $parts['path'] ?? '', [ '', '/' ], true )
            || ( isset( $parts['port'] ) && 443 !== $parts['port'] ) ) {
            return $this->failure( 'invalid_origin', 'Choose an HTTPS site origin without a path or credentials.' );
        }
        $url = add_query_arg( $query, rtrim( $origin, '/' ) . '/wp-json/hprwc/v1' . $path );
        $response = wp_safe_remote_get( $url, [
            'timeout' => 20,
            'redirection' => 0,
            'limit_response_size' => 1048576,
            'headers' => [ 'Accept' => 'application/json' ],
        ] );
        if ( is_wp_error( $response ) ) {
            return $this->failure( 'connection_failed', 'The press-release service could not be reached.' );
        }
        $status = (int) wp_remote_retrieve_response_code( $response );
        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $decoded ) || ! isset( $decoded['success'] ) || ! is_bool( $decoded['success'] ) ) {
            return $this->failure( 'invalid_response', 'The press-release service returned an invalid response.' );
        }
        if ( ! $decoded['success'] ) {
            return $this->failure(
                sanitize_key( (string) ( $decoded['code'] ?? 'request_failed' ) ),
                sanitize_text_field( (string) ( $decoded['message'] ?? 'The requested profile is unavailable.' ) )
            );
        }
        if ( $status < 200 || $status >= 300 || ! is_array( $decoded['data'] ?? null ) ) {
            return $this->failure( 'invalid_response', 'The press-release service returned an invalid response.' );
        }
        if ( ! $this->valid_data( $decoded['data'], $origin ) ) {
            return $this->failure( 'invalid_response', 'The press-release service returned an invalid profile or release list.' );
        }
        return [ 'success' => true, 'data' => $decoded['data'] ];
    }

    private function valid_data( array $data, string $origin ): bool {
        if ( ! array_key_exists( 'items', $data ) ) {
            return $this->valid_identity( $data, $origin );
        }
        if ( ! is_array( $data['items'] ) || count( $data['items'] ) > 20 ) {
            return false;
        }
        foreach ( [ 'page', 'total', 'total_pages' ] as $key ) {
            if ( ! isset( $data[$key] ) || ! is_int( $data[$key] ) || $data[$key] < ( 'page' === $key ? 1 : 0 ) ) {
                return false;
            }
        }
        foreach ( [ 'author', 'profile' ] as $key ) {
            if ( isset( $data[$key] ) && ( ! is_array( $data[$key] ) || ! $this->valid_identity( $data[$key], $origin ) ) ) {
                return false;
            }
        }
        foreach ( $data['items'] as $item ) {
            if ( ! is_array( $item ) || ! isset( $item['id'], $item['url'] ) || ! is_int( $item['id'] ) || $item['id'] < 1
                || ! $this->same_origin( $item['url'], $origin ) ) {
                return false;
            }
            if ( isset( $data['author'] ) || isset( $data['profile'] ) ) {
                foreach ( [ 'title', 'date_gmt', 'excerpt' ] as $key ) {
                    if ( ! isset( $item[$key] ) || ! is_string( $item[$key] ) ) {
                        return false;
                    }
                }
            } elseif ( ! $this->valid_identity( $item, $origin ) || 'person' !== ( $item['entity_type'] ?? '' ) ) {
                return false;
            }
        }
        return true;
    }

    private function valid_identity( array $value, string $origin ): bool {
        return isset( $value['id'], $value['name'], $value['url'], $value['image_url'] )
            && is_int( $value['id'] ) && $value['id'] > 0 && is_string( $value['name'] ) && '' !== trim( $value['name'] )
            && is_string( $value['image_url'] ) && $this->same_origin( $value['url'], $origin );
    }

    private function same_origin( mixed $url, string $origin ): bool {
        $parts = is_string( $url ) ? wp_parse_url( $url ) : false;
        $expected = wp_parse_url( $origin );
        return is_array( $parts ) && 'https' === ( $parts['scheme'] ?? '' )
            && strtolower( $parts['host'] ?? '' ) === strtolower( $expected['host'] ?? '' )
            && ( $parts['port'] ?? 443 ) === ( $expected['port'] ?? 443 )
            && ! isset( $parts['user'] ) && ! isset( $parts['pass'] );
    }

    private function failure( string $code, string $message ): array {
        return [ 'success' => false, 'code' => $code, 'message' => $message ];
    }
}
