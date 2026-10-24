<?php

namespace Hexa\PluginCore\WpAdminComponents;

/**
 * Read-only notice a plugin shows above data it displays but does not own,
 * pointing to the plugin that owns and edits it.
 *
 * SourceBanner::render( [ 'owner' => 'HWS Base Tools', 'edit_url' => admin_url( '...' ) ] )
 * prints: "Original content at HWS Base Tools. Click here to edit."
 */
final class SourceBanner {
    /** @param array{owner?:string,edit_url?:string,message?:string,link_label?:string,class?:string} $args */
    public static function render( array $args ): string {
        $owner = trim( (string) ( $args['owner'] ?? '' ) );
        if ( '' === $owner ) {
            return '';
        }
        $url = trim( (string) ( $args['edit_url'] ?? '' ) );
        $message = trim( (string) ( $args['message'] ?? '' ) );
        if ( '' === $message ) {
            $message = sprintf( 'Original content at %s.', $owner );
        }
        $label = trim( (string) ( $args['link_label'] ?? '' ) );
        $label = '' !== $label ? $label : 'Click here to edit.';
        $class = trim( 'hpc-source-banner ' . (string) ( $args['class'] ?? '' ) );

        return self::assets()
            . '<div class="' . esc_attr( $class ) . '" role="note" data-hpc-source-banner>'
            . '<span class="dashicons dashicons-lock" aria-hidden="true"></span>'
            . '<span>' . esc_html( $message )
            . ( '' !== $url ? ' <a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>' : '' )
            . '</span></div>';
    }

    private static function assets(): string {
        static $printed = false;
        if ( $printed ) {
            return '';
        }
        $printed = true;
        return '<style>.hpc-source-banner{align-items:center;background:#f6f7f7;border:1px solid #dcdcde;border-left:4px solid #8c8f94;border-radius:6px;color:#3c434a;display:flex;gap:8px;margin:0 0 12px;padding:9px 12px}.hpc-source-banner .dashicons{color:#8c8f94;flex:0 0 auto}.hpc-source-banner a{font-weight:600}</style>';
    }
}
