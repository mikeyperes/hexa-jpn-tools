<?php

namespace Hexa\PluginCore\Calendar;

use Hexa\PluginCore\PublicComponents\ProfileStore;

/**
 * Holds the normalized calendar profiles registered during a request.
 *
 * Hosts register profiles from their own boot code, or late through the
 * `hexa_plugin_core_calendar_register` action, which fires once the first
 * time a profile is resolved.
 */
final class CalendarRegistry {
    public const REGISTER_ACTION = 'hexa_plugin_core_calendar_register';

    private static ?ProfileStore $store = null;

    /** @param array<string,mixed> $config */
    public static function register( string $id, array $config ): void {
        self::store()->register( $id, $config );
    }

    /** @return array<string,mixed>|null */
    public static function get( string $id ): ?array {
        return self::store()->get( $id );
    }

    /** @return array<string,array<string,mixed>> */
    public static function all(): array {
        return self::store()->all();
    }

    /** Test helper: forget every registered profile. */
    public static function reset(): void {
        self::store()->reset();
    }

    private static function store(): ProfileStore {
        return self::$store ??= new ProfileStore( self::REGISTER_ACTION, [ CalendarProfile::class, 'normalize' ] );
    }
}
