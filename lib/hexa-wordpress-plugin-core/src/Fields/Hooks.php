<?php

namespace Hexa\PluginCore\Fields;

/** Registers and dispatches the lifecycle hooks owned by Core Fields. */
final class Hooks {
    /** ACF's registration moments, fired natively in this order during `init`. */
    private const LIFECYCLE = [ 'include_fields', 'init' ];

    private static bool $init_scheduled = false;
    private static bool $init_fired = false;

    public static function on( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
        $hook = ltrim( $hook, '/' );
        // Bug: ACF callbacks also handled Hexa fields when both engines were loaded.
        // Fix: Keep registration and dispatch on Core's hooks. Why: each engine owns its own fields.
        add_filter( 'hexa_fields/' . $hook, $callback, $priority, $accepted_args );
        if ( ! in_array( $hook, self::LIFECYCLE, true ) ) {
            return;
        }
        // `include_fields` and `init` are where hosts register fields: run late registrations at once.
        if ( did_action( 'hexa_fields/' . $hook ) && ! doing_action( 'hexa_fields/' . $hook ) ) {
            $callback();
            return;
        }
        if ( ! self::$init_scheduled ) {
            self::$init_scheduled = true;
            did_action( 'init' ) ? self::fire_init() : add_action( 'init', [ self::class, 'fire_init' ], 5 );
        }
    }

    /** Fires Core field registration in order, once per request. */
    public static function fire_init(): void {
        if ( self::$init_fired ) {
            return;
        }
        self::$init_fired = true;
        foreach ( self::LIFECYCLE as $hook ) {
            self::action( $hook );
        }
    }

    public static function off( string $hook, callable $callback, int $priority = 10 ): void {
        $hook = ltrim( $hook, '/' );
        remove_filter( 'hexa_fields/' . $hook, $callback, $priority );
    }

    /** Fires a native action. Never fires `acf/*`. */
    public static function action( string $hook, mixed ...$args ): void {
        do_action( 'hexa_fields/' . $hook, ...$args );
    }

    /**
     * Applies a native field filter in ACF's order: generic, then `type=`,
     * `name=` and `key=` variants.
     *
     * @param array<string,mixed> $field
     */
    public static function field_filter( string $hook, mixed $value, array $field, mixed ...$args ): mixed {
        $value = apply_filters( 'hexa_fields/' . $hook, $value, ...$args );
        foreach ( [ 'type', 'name', 'key' ] as $qualifier ) {
            $qualified = (string) ( $field[ $qualifier ] ?? '' );
            if ( '' !== $qualified ) {
                $value = apply_filters( 'hexa_fields/' . $hook . '/' . $qualifier . '=' . $qualified, $value, ...$args );
            }
        }
        return $value;
    }

    /**
     * Fires a native field action in ACF's order.
     *
     * @param array<string,mixed> $field
     */
    public static function field_action( string $hook, array $field ): void {
        do_action( 'hexa_fields/' . $hook, $field );
        foreach ( [ 'type', 'name', 'key' ] as $qualifier ) {
            $qualified = (string) ( $field[ $qualifier ] ?? '' );
            if ( '' !== $qualified ) {
                do_action( 'hexa_fields/' . $hook . '/' . $qualifier . '=' . $qualified, $field );
            }
        }
    }
}
