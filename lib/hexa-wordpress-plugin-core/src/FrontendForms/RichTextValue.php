<?php

namespace Hexa\PluginCore\FrontendForms;

/**
 * WordPress-safe rich-text normalization and plain-text projection.
 */
final class RichTextValue {
    public static function sanitize( string $html ): string {
        $html = trim( $html );
        if ( "" === $html ) {
            return "";
        }
        if ( function_exists( 'wp_kses_post' ) ) {
            return trim( (string) wp_kses_post( $html ) );
        }

        return htmlspecialchars( trim( strip_tags( $html ) ), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8' );
    }

    public static function plain_text( string $html ): string {
        $text = function_exists( 'wp_strip_all_tags' )
            ? (string) wp_strip_all_tags( $html, true )
            : strip_tags( $html );
        $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        return trim( preg_replace( '/\\s+/u', ' ', $text ) ?? '' );
    }
}
