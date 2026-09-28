<?php

namespace Hexa\PluginCore\PublicComponents;

/**
 * A `<time>` element that reads "5 min ago", "3 hours ago", "2 days ago".
 *
 * The server renders the text for the moment of the request; a tiny inline
 * script (printed once per page) recomputes it in the browser on load and
 * every minute, so a page served from a full-page cache never shows a stale
 * "last updated" age. Without JavaScript the server text and the exact date
 * in the `title` remain.
 */
final class RelativeTime {
    private static bool $script_printed = false;

    /** @param int|null $now Test hook; defaults to time(). */
    public static function html( int $timestamp, string $class = '', ?int $now = null ): string {
        if ( $timestamp <= 0 ) {
            return '';
        }
        $iso   = gmdate( 'c', $timestamp );
        $title = function_exists( 'wp_date' ) ? (string) wp_date( 'M j, Y g:i a', $timestamp ) : gmdate( 'M j, Y g:i a', $timestamp );

        return '<time class="' . esc_attr( trim( 'hexa-reltime ' . $class ) ) . '" datetime="' . esc_attr( $iso ) . '" title="' . esc_attr( $title ) . '" data-hexa-reltime>'
            . esc_html( self::text( $timestamp, $now ?? time() ) ) . '</time>' . self::script();
    }

    /** The same wording the script uses. */
    public static function text( int $timestamp, int $now ): string {
        $seconds = max( 0, $now - $timestamp );
        if ( $seconds < 60 ) {
            return 'just now';
        }
        $units = [ [ 86400, 'day' ], [ 3600, 'hour' ], [ 60, 'min' ] ];
        foreach ( $units as [ $size, $unit ] ) {
            if ( $seconds >= $size ) {
                $n = (int) floor( $seconds / $size );

                return $n . ' ' . ( 'min' === $unit ? 'min' : $unit . ( 1 === $n ? '' : 's' ) ) . ' ago';
            }
        }

        return 'just now';
    }

    private static function script(): string {
        if ( self::$script_printed ) {
            return '';
        }
        self::$script_printed = true;

        return '<script id="hexa-reltime-js">(function(){function t(s){if(s<60)return"just now";var u=[[86400,"day"],[3600,"hour"],[60,"min"]];for(var i=0;i<u.length;i++){if(s>=u[i][0]){var n=Math.floor(s/u[i][0]);return n+" "+(u[i][1]=="min"?"min":u[i][1]+(n==1?"":"s"))+" ago"}}return"just now"}'
            . 'function r(){var e=document.querySelectorAll("time[data-hexa-reltime]");for(var i=0;i<e.length;i++){var d=Date.parse(e[i].getAttribute("datetime"));if(!isNaN(d))e[i].textContent=t(Math.max(0,Math.floor((Date.now()-d)/1000)))}}'
            . 'if(document.readyState=="loading")document.addEventListener("DOMContentLoaded",r);else r();setInterval(r,60000)})();</script>';
    }
}
