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

    /** `acf` when ACF owns custom fields, `native` otherwise. */
    public static function mode(): string {
        return self::active() ? 'acf' : 'native';
    }
}
