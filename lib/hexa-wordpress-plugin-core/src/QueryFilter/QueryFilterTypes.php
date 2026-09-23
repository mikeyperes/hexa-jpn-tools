<?php

namespace Hexa\PluginCore\QueryFilter;

use Hexa\PluginCore\PublicComponents\ProfileValues;

/**
 * Registry of filter types. Built-ins: `meta`, `taxonomy`, `date_range`, and
 * `callback`. Hosts add a type with register() at any point before profiles
 * are first rendered (profiles normalize lazily), or on the
 * `hexa_plugin_core_query_filter_types` action, which fires once at the first
 * type lookup. Built-ins cannot be replaced, so one plugin cannot change
 * another plugin's filters.
 */
final class QueryFilterTypes {
    public const REGISTER_ACTION = 'hexa_plugin_core_query_filter_types';

    /** @var array<string,QueryFilterType> */
    private static array $types = [];

    private static bool $booted = false;

    public static function register( string $name, QueryFilterType $type ): void {
        self::seed();
        $name = ProfileValues::key( $name );
        if ( '' !== $name && ! isset( self::builtins()[ $name ] ) ) {
            self::$types[ $name ] = $type;
        }
    }

    public static function get( string $name ): ?QueryFilterType {
        self::boot();

        return self::$types[ ProfileValues::key( $name ) ] ?? null;
    }

    /** @return string[] */
    public static function names(): array {
        self::boot();

        return array_keys( self::$types );
    }

    /** Lookups fire the registration action once, after every plugin has had the chance to hook it. */
    private static function boot(): void {
        self::seed();
        if ( self::$booted ) {
            return;
        }

        self::$booted = true;
        if ( function_exists( 'do_action' ) ) {
            do_action( self::REGISTER_ACTION );
        }
    }

    private static function seed(): void {
        self::$types += self::builtins();
    }

    /** @return array<string,QueryFilterType> */
    private static function builtins(): array {
        static $builtins = null;

        return $builtins ??= [
            'meta'       => new MetaFilterType(),
            'taxonomy'   => new TaxonomyFilterType(),
            'date_range' => new DateRangeFilterType(),
            'callback'   => new CallbackFilterType(),
        ];
    }
}
