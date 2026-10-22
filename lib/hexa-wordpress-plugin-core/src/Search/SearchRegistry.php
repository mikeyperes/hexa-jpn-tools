<?php

namespace Hexa\PluginCore\Search;

use Hexa\PluginCore\PublicComponents\ProfileStore;

/**
 * Holds the search profiles registered during a request. Hosts register from
 * their boot code or on the `hexa_plugin_core_search_register` action.
 */
final class SearchRegistry {
    public const REGISTER_ACTION = 'hexa_plugin_core_search_register';

    private static ?ProfileStore $store = null;

    /** @param array<string,mixed> $config */
    public static function register( string $id, array $config ): void {
        self::store()->register( $id, $config );
    }

    /** @return array<string,mixed>|null */
    public static function get( string $id ): ?array {
        return self::store()->get( $id );
    }

    /** Test helper. */
    public static function reset(): void {
        self::store()->reset();
    }

    private static function store(): ProfileStore {
        return self::$store ??= new ProfileStore( self::REGISTER_ACTION, [ SearchProfile::class, 'normalize' ] );
    }
}
