<?php

namespace Hexa\PluginCore\Fields;

/**
 * One registration point for ACF lifecycle hooks.
 *
 * `Hooks::on( 'save_post', $callback )` listens on `acf/save_post` (fired by
 * ACF) and on `hexa_fields/save_post` (fired by the native engine), so plugin
 * callbacks run in both modes without either engine firing the other's hooks.
 * Qualified hooks such as `prepare_field/name=title` work the same way.
 */
final class Hooks {
    /** ACF's registration moments, fired natively in this order during `init`. */
    private const LIFECYCLE = [ 'include_fields', 'init' ];

    private static bool $init_scheduled = false;
    private static bool $init_fired = false;

    public static function on( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
        $hook = ltrim( $hook, '/' );
        add_filter( 'acf/' . $hook, $callback, $priority, $accepted_args );
        add_filter( 'hexa_fields/' . $hook, $callback, $priority, $accepted_args );
        if ( ! in_array( $hook, self::LIFECYCLE, true ) ) {
            return;
        }
        // `include_fields` and `init` are where hosts register fields: run late registrations at once.
        if ( Acf::active() ? did_action( 'acf/' . $hook ) && ! doing_action( 'acf/' . $hook ) : self::$init_fired && ! doing_action( 'hexa_fields/' . $hook ) ) {
            $callback();
            return;
        }
        if ( ! self::$init_scheduled ) {
            self::$init_scheduled = true;
            did_action( 'init' ) ? self::fire_init() : add_action( 'init', [ self::class, 'fire_init' ], 5 );
        }
    }

    /** Fires `hexa_fields/include_fields` then `hexa_fields/init` once, at ACF's timing, when ACF is not active. */
    public static function fire_init(): void {
        if ( self::$init_fired ) {
            return;
        }
        self::$init_fired = true;
        if ( ! Acf::active() ) {
            foreach ( self::LIFECYCLE as $hook ) {
                do_action( 'hexa_fields/' . $hook );
            }
        }
    }

    public static function off( string $hook, callable $callback, int $priority = 10 ): void {
        $hook = ltrim( $hook, '/' );
        remove_filter( 'acf/' . $hook, $callback, $priority );
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
