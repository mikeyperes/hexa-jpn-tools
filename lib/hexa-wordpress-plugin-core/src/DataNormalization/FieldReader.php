<?php

declare( strict_types=1 );

namespace Hexa\PluginCore\DataNormalization;

/** Cached ACF-first reader for post and user fields. */
final class FieldReader {
    /** @var array<string,mixed> */
    private array $cache = [];

    public function __construct( private readonly int $object_id, private readonly string $kind = 'post' ) {}

    public function post_id(): int {
        return $this->object_id;
    }

    public function object_id(): int {
        return $this->object_id;
    }

    public function kind(): string {
        return $this->kind;
    }

    public function context(): string|int {
        return 'user' === $this->kind ? 'user_' . $this->object_id : $this->object_id;
    }

    /**
     * Read an ACF value from an explicit context with a caller-selected legacy
     * empty-value policy.
     *
     * When $empty_is_missing is false, only null, an empty string, and false
     * select the default. When true, the normal PHP empty() rules apply.
     */
    public static function acf_value(
        string $name,
        mixed $context = false,
        mixed $default = '',
        bool $empty_is_missing = false
    ): mixed {
        if ( ! function_exists( 'get_field' ) ) {
            return $default;
        }

        $value = get_field( $name, $context );
        $missing = $empty_is_missing
            ? empty( $value )
            : null === $value || '' === $value || false === $value;
        return $missing ? $default : $value;
    }

    public function first( string ...$names ): mixed {
        foreach ( $names as $name ) {
            $value = $this->read( $name );
            if ( ValueNormalizer::present( $value ) ) {
                return $value;
            }
        }
        return null;
    }

    public function read( string $name ): mixed {
        if ( array_key_exists( $name, $this->cache ) ) {
            return $this->cache[ $name ];
        }
        if ( function_exists( 'get_field' ) ) {
            $value = get_field( $name, $this->context() );
            if ( ValueNormalizer::present( $value ) ) {
                return $this->cache[ $name ] = $value;
            }
        }
        if ( 'user' === $this->kind ) {
            $value = function_exists( 'get_user_meta' ) ? get_user_meta( $this->object_id, $name, true ) : null;
        } else {
            $value = function_exists( 'get_post_meta' ) ? get_post_meta( $this->object_id, $name, true ) : null;
        }
        return $this->cache[ $name ] = $value;
    }

    public function clear_cache( ?string $name = null ): void {
        if ( null === $name ) {
            $this->cache = [];
            return;
        }
        unset( $this->cache[ $name ] );
    }
}
