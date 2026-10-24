<?php

namespace Hexa\PluginCore\Fields;

/**
 * ACF detection for the Fields module.
 *
 * When ACF (free or Pro) is loaded, every Fields API call delegates to ACF so
 * behavior is unchanged. Otherwise the Fields module owns registration,
 * storage, formatting and editing natively.
 */
final class Acf {
    public static function active(): bool {
        return function_exists( 'acf_add_local_field_group' ) && function_exists( 'get_field' );
    }

    /**
     * True once ACF has started (acf/init has run). Before that, calling ACF's
     * field functions during plugin startup boots ACF too early and can fatal.
     */
    public static function ready(): bool {
        return (bool) did_action( 'acf/init' );
    }

    /** `acf` when ACF owns custom fields, `native` otherwise. */
    public static function mode(): string {
        return self::active() ? 'acf' : 'native';
    }
}
