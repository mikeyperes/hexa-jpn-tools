<?php

namespace Hexa\PluginCore\SchemaTools;

/**
 * Stable @id conventions shared by every Hexa plugin, so nodes built by
 * different plugins for the same thing merge into one node on the page.
 */
final class SchemaIds {
    public static function home(): string {
        return trailingslashit( home_url( '/' ) );
    }

    public static function organization(): string {
        return self::home() . '#organization';
    }

    public static function website(): string {
        return self::home() . '#website';
    }

    public static function person(): string {
        return self::home() . '#person';
    }

    public static function webpage( string $url ): string {
        return self::for_url( $url, 'webpage' );
    }

    public static function article( string $url ): string {
        return self::for_url( $url, 'article' );
    }

    public static function primary_image( string $url ): string {
        return self::for_url( $url, 'primaryimage' );
    }

    public static function breadcrumb( string $url ): string {
        return self::for_url( $url, 'breadcrumb' );
    }

    public static function for_url( string $url, string $fragment ): string {
        $url = '' !== trim( $url ) ? trim( $url ) : self::home();
        $url = (string) preg_replace( '/#.*$/', '', $url );
        return $url . '#' . ltrim( $fragment, '#' );
    }
}
