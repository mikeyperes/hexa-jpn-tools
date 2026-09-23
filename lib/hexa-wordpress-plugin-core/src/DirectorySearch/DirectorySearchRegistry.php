<?php

namespace Hexa\PluginCore\DirectorySearch;

/**
 * Holds the normalized directory search profiles registered during a request.
 *
 * Hosts register profiles from their own boot code, or late through the
 * `hexa_plugin_core_directory_search_register` action, which fires once the
 * first time a profile is resolved.
 */
final class DirectorySearchRegistry {
    /** @var array<string,array<string,mixed>> */
    private static array $profiles = [];

    private static bool $late_registration_done = false;

    /** @param array<string,mixed> $config */
    public static function register( string $id, array $config ): void {
        $profile = DirectorySearchProfile::normalize( $id, $config );
        self::$profiles[ $profile['id'] ] = $profile;
    }

    /** @return array<string,mixed>|null */
    public static function get( string $id ): ?array {
        self::run_late_registration();

        return self::$profiles[ DirectorySearchProfile::key( $id ) ] ?? null;
    }

    /** @return string[] */
    public static function ids(): array {
        self::run_late_registration();

        return array_keys( self::$profiles );
    }

    /** Test helper: forget every registered profile. */
    public static function reset(): void {
        self::$profiles = [];
        self::$late_registration_done = false;
    }

    private static function run_late_registration(): void {
        if ( self::$late_registration_done ) {
            return;
        }

        self::$late_registration_done = true;
        if ( function_exists( 'do_action' ) ) {
            do_action( 'hexa_plugin_core_directory_search_register' );
        }
    }
}
