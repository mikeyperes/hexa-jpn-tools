<?php

namespace Hexa\PluginCore\SchemaTools;

/**
 * Controls whether Rank Math prints its own schema block.
 *
 * Rank Math stays active for titles, descriptions and sitemaps; only its
 * JSON-LD output is affected. Where Rank Math's schema is removed, the
 * separate image schema block of Featured Image From URL (FIFU) is removed
 * too, because the Hexa graph already carries the page image. The host
 * plugin owns the setting and passes a callback that returns the mode.
 */
final class RankMathSchemaControl {
    /** Rank Math prints its schema as usual. */
    public const KEEP = 'keep';

    /** Rank Math's schema is removed on pages where a Hexa plugin prints schema. */
    public const WHERE_HEXA = 'hexa';

    /** Rank Math never prints schema. */
    public const OFF = 'off';

    /** Other plugins' wp_head schema printers that duplicate the Hexa graph. */
    private const DUPLICATE_PRINTERS = [
        [ 'Fifu_Structured_Data_Renderer', 'render_current_page' ],
    ];

    public const MODES = [
        self::KEEP       => 'On: Rank Math prints its own schema',
        self::WHERE_HEXA => 'Off where Hexa plugins print schema (recommended)',
        self::OFF        => 'Off on every page',
    ];

    /** @var callable():string */
    private static $mode_callback = null;

    private static bool $registered = false;

    /**
     * @param callable():string $mode_callback Returns one of the MODES keys.
     */
    public static function register( callable $mode_callback ): void {
        self::$mode_callback = $mode_callback;
        if ( self::$registered ) {
            return;
        }
        self::$registered = true;
        add_filter( 'rank_math/json_ld', [ self::class, 'filter' ], PHP_INT_MAX, 2 );
        add_action( 'wp', [ self::class, 'remove_duplicate_printers' ], PHP_INT_MAX );
    }

    public static function remove_duplicate_printers(): void {
        if ( is_admin() || ! self::suppressed() ) {
            return;
        }
        foreach ( self::DUPLICATE_PRINTERS as $callback ) {
            $priority = has_action( 'wp_head', $callback );
            if ( false !== $priority ) {
                remove_action( 'wp_head', $callback, (int) $priority );
            }
        }
    }

    public static function mode(): string {
        $mode = is_callable( self::$mode_callback ) ? (string) call_user_func( self::$mode_callback ) : self::WHERE_HEXA;
        return isset( self::MODES[ $mode ] ) ? $mode : self::WHERE_HEXA;
    }

    /** True when Rank Math's schema is removed on the page being viewed. */
    public static function suppressed(): bool {
        $mode = self::mode();
        return self::OFF === $mode || ( self::WHERE_HEXA === $mode && SchemaPage::has_nodes() );
    }

    /**
     * @param mixed $data
     * @param mixed $json_ld
     * @return mixed
     */
    public static function filter( $data, $json_ld = null ) {
        if ( is_admin() || ! self::suppressed() ) {
            return $data;
        }
        return [];
    }
}
