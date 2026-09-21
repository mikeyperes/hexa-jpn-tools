<?php

namespace Hexa\PluginCore\WpAdminAjax;

final class AjaxRequest {
    private array $post;

    private array $get;

    private array $request;

    public function __construct( ?array $post = null, ?array $get = null, ?array $request = null ) {
        $this->post    = self::unslash_deep( $post ?? $_POST );
        $this->get     = self::unslash_deep( $get ?? $_GET );
        $this->request = self::unslash_deep( $request ?? $_REQUEST );
    }

    public function raw( string $key, mixed $default = null, string $source = 'request' ): mixed {
        $items = $this->source( $source );

        return array_key_exists( $key, $items ) ? $items[ $key ] : $default;
    }

    public function has( string $key, string $source = 'request' ): bool {
        return array_key_exists( $key, $this->source( $source ) );
    }

    public function text( string $key, string $default = '', string $source = 'request' ): string {
        $value = $this->raw( $key, $default, $source );
        $value = is_scalar( $value ) ? (string) $value : $default;

        return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : trim( strip_tags( $value ) );
    }

    public function key( string $key, string $default = '', string $source = 'request' ): string {
        $value = $this->raw( $key, $default, $source );
        $value = is_scalar( $value ) ? (string) $value : $default;

        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : preg_replace( '/[^a-z0-9_\\-]/', '', strtolower( $value ) );
    }

    public function title_slug( string $key, string $default = '', string $source = 'request' ): string {
        $value = $this->raw( $key, $default, $source );
        $value = is_scalar( $value ) ? (string) $value : $default;

        return function_exists( 'sanitize_title' ) ? sanitize_title( $value ) : $this->key_from_string( $value );
    }

    public function html( string $key, string $default = '', string $source = 'request' ): string {
        $value = $this->raw( $key, $default, $source );
        $value = is_scalar( $value ) ? (string) $value : $default;

        return function_exists( 'wp_kses_post' ) ? wp_kses_post( $value ) : $value;
    }

    public function url( string $key, string $default = '', string $source = 'request' ): string {
        $value = $this->raw( $key, $default, $source );
        $value = is_scalar( $value ) ? (string) $value : $default;

        return function_exists( 'esc_url_raw' ) ? esc_url_raw( $value ) : filter_var( $value, FILTER_SANITIZE_URL );
    }

    public function int( string $key, int $default = 0, string $source = 'request' ): int {
        $value = $this->raw( $key, $default, $source );

        return function_exists( 'absint' ) ? absint( $value ) : abs( (int) $value );
    }

    public function bool( string $key, bool $default = false, string $source = 'request' ): bool {
        if ( ! $this->has( $key, $source ) ) {
            return $default;
        }

        $value = $this->raw( $key, $default, $source );

        if ( is_bool( $value ) ) {
            return $value;
        }

        if ( is_numeric( $value ) ) {
            return (bool) (int) $value;
        }

        return in_array( strtolower( (string) $value ), [ '1', 'true', 'yes', 'on' ], true );
    }

    public function items( string $key, string $source = 'request' ): array {
        $value = $this->raw( $key, [], $source );

        if ( is_array( $value ) ) {
            return array_values( $value );
        }

        return null === $value || '' === $value ? [] : [ $value ];
    }

    public function text_array( string $key, string $source = 'request' ): array {
        return array_values(
            array_filter(
                array_map(
                    fn( mixed $value ): string => is_scalar( $value )
                        ? ( function_exists( 'sanitize_text_field' ) ? sanitize_text_field( (string) $value ) : trim( strip_tags( (string) $value ) ) )
                        : '',
                    $this->items( $key, $source )
                ),
                static fn( string $value ): bool => '' !== $value
            )
        );
    }

    public function key_array( string $key, string $source = 'request' ): array {
        return array_values(
            array_filter(
                array_map(
                    fn( mixed $value ): string => is_scalar( $value )
                        ? ( function_exists( 'sanitize_key' ) ? sanitize_key( (string) $value ) : $this->key_from_string( (string) $value ) )
                        : '',
                    $this->items( $key, $source )
                ),
                static fn( string $value ): bool => '' !== $value
            )
        );
    }

    public function all( string $source = 'request' ): array {
        return $this->source( $source );
    }

    private function source( string $source ): array {
        return match ( $source ) {
            'post' => $this->post,
            'get' => $this->get,
            default => $this->request,
        };
    }

    private static function unslash_deep( mixed $value ): mixed {
        if ( function_exists( 'wp_unslash' ) ) {
            return wp_unslash( $value );
        }

        if ( is_array( $value ) ) {
            return array_map( [ self::class, 'unslash_deep' ], $value );
        }

        return is_string( $value ) ? stripslashes( $value ) : $value;
    }

    private function key_from_string( string $value ): string {
        return preg_replace( '/[^a-z0-9_\\-]/', '', strtolower( $value ) ) ?: '';
    }
}
