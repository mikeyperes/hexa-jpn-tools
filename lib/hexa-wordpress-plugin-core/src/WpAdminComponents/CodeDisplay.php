<?php

namespace Hexa\PluginCore\WpAdminComponents;

/**
 * Color-coded, read-only code block with a copy button for admin screens.
 *
 * CodeDisplay::render( $code, [ 'language' => 'html'|'css'|'php'|'js'|'json'|'shortcode'|'text', 'title' => 'HTML structure', 'max_height' => 420 ] )
 *
 * Highlighting is done on the server (no script library); every piece of
 * code is escaped before markup is added.
 */
final class CodeDisplay {
    /** @param array{language?:string,title?:string,max_height?:int,class?:string,copy_label?:string} $args */
    public static function render( string $code, array $args = [] ): string {
        $language = strtolower( trim( (string) ( $args['language'] ?? 'text' ) ) );
        $title    = trim( (string) ( $args['title'] ?? '' ) );
        $height   = max( 0, (int) ( $args['max_height'] ?? 420 ) );
        $class    = trim( 'hpc-code-display ' . (string) ( $args['class'] ?? '' ) );
        $code     = rtrim( str_replace( [ "\r\n", "\r" ], "\n", $code ) );
        $labels   = [ 'html' => 'HTML', 'css' => 'CSS', 'php' => 'PHP', 'js' => 'JavaScript', 'json' => 'JSON', 'shortcode' => 'Shortcode' ];
        $badge    = $labels[ $language ] ?? 'Text';

        return self::assets()
            . '<figure class="' . esc_attr( $class ) . '" data-language="' . esc_attr( $language ) . '">'
            . '<figcaption class="hpc-code-display-bar">'
            . '<span class="hpc-code-display-dots" aria-hidden="true"><i></i><i></i><i></i></span>'
            . '<span class="hpc-code-display-title">' . esc_html( $title ) . '</span>'
            . '<span class="hpc-code-display-lang">' . esc_html( $badge ) . '</span>'
            . '<button type="button" class="hpc-code-display-copy" data-hpc-copy="' . esc_attr( $code ) . '">' . esc_html( (string) ( $args['copy_label'] ?? 'Copy' ) ) . '</button>'
            . '</figcaption>'
            . '<pre class="hpc-code-display-pre"' . ( $height > 0 ? ' style="max-height:' . $height . 'px"' : '' ) . '><code>'
            . self::highlight( $code, $language )
            . '</code></pre></figure>';
    }

    /** Escaped, color-coded markup for one piece of code. */
    public static function highlight( string $code, string $language ): string {
        switch ( $language ) {
            case 'php':
                return self::highlight_php( $code );
            case 'html':
            case 'xml':
                return self::highlight_html( $code );
            case 'css':
                return self::highlight_css( $code );
            case 'js':
            case 'json':
                return self::highlight_js( $code );
            case 'shortcode':
                return self::highlight_shortcodes( $code );
            default:
                return esc_html( $code );
        }
    }

    private static function span( string $type, string $text ): string {
        return '<span class="hcd-' . $type . '">' . esc_html( $text ) . '</span>';
    }

    private static function highlight_php( string $code ): string {
        $prefixed = ! preg_match( '/^\s*<\?php/', $code );
        $tokens   = token_get_all( $prefixed ? "<?php\n" . $code : $code );
        $out      = '';
        foreach ( $tokens as $index => $token ) {
            if ( $prefixed && 0 === $index ) {
                continue;
            }
            if ( is_string( $token ) ) {
                $out .= self::span( 'punct', $token );
                continue;
            }
            [ $id, $text ] = $token;
            switch ( $id ) {
                case T_COMMENT:
                case T_DOC_COMMENT:
                    $out .= self::span( 'comment', $text );
                    break;
                case T_CONSTANT_ENCAPSED_STRING:
                case T_ENCAPSED_AND_WHITESPACE:
                    $out .= self::span( 'string', $text );
                    break;
                case T_VARIABLE:
                    $out .= self::span( 'variable', $text );
                    break;
                case T_LNUMBER:
                case T_DNUMBER:
                    $out .= self::span( 'number', $text );
                    break;
                case T_OPEN_TAG:
                case T_CLOSE_TAG:
                    $out .= self::span( 'tag', $text );
                    break;
                case T_STRING:
                    $out .= self::span( preg_match( '/^(true|false|null)$/i', $text ) ? 'keyword' : 'function', $text );
                    break;
                case T_WHITESPACE:
                case T_INLINE_HTML:
                    $out .= esc_html( $text );
                    break;
                default:
                    $out .= preg_match( '/^[a-z_]+$/i', $text ) ? self::span( 'keyword', $text ) : esc_html( $text );
            }
        }
        return $out;
    }

    private static function highlight_html( string $code ): string {
        $out    = '';
        $offset = 0;
        preg_match_all( '/<!--.*?-->|<\/?[a-zA-Z][^>]*>|\[\/?[a-z0-9_\-]+[^\]]*\]/is', $code, $matches, PREG_OFFSET_CAPTURE );
        foreach ( $matches[0] as [ $text, $pos ] ) {
            $out   .= esc_html( substr( $code, $offset, $pos - $offset ) );
            $offset = $pos + strlen( $text );
            if ( str_starts_with( $text, '<!--' ) ) {
                $out .= self::span( 'comment', $text );
            } elseif ( str_starts_with( $text, '[' ) ) {
                $out .= self::highlight_shortcodes( $text );
            } else {
                $out .= self::highlight_tag( $text );
            }
        }
        return $out . esc_html( substr( $code, $offset ) );
    }

    private static function highlight_tag( string $tag ): string {
        if ( ! preg_match( '/^(<\/?)([a-zA-Z][\w:-]*)(.*?)(\/?>)$/s', $tag, $m ) ) {
            return esc_html( $tag );
        }
        return self::span( 'punct', $m[1] ) . self::span( 'tag', $m[2] ) . self::highlight_attributes( $m[3] ) . self::span( 'punct', $m[4] );
    }

    /** name="value" pairs, bare names and the space between them. */
    private static function highlight_attributes( string $text ): string {
        $out = '';
        preg_match_all( '/([\w:@.-]+)(\s*=\s*)("[^"]*"|\'[^\']*\'|[^\s>\]]+)|([\w:@.-]+)|([^\w:@.-]+)/s', $text, $parts, PREG_SET_ORDER );
        foreach ( $parts as $a ) {
            if ( isset( $a[5] ) && '' !== $a[5] ) {
                $out .= esc_html( $a[5] );
            } elseif ( isset( $a[4] ) && '' !== $a[4] ) {
                $out .= self::span( 'attr', $a[4] );
            } else {
                $out .= self::span( 'attr', $a[1] ) . esc_html( $a[2] ) . self::span( 'string', $a[3] );
            }
        }
        return $out;
    }

    private static function highlight_shortcodes( string $code ): string {
        $out    = '';
        $offset = 0;
        preg_match_all( '/\[(\/?)([a-z0-9_\-]+)([^\]]*)\]/i', $code, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER );
        foreach ( $matches as $m ) {
            $out   .= esc_html( substr( $code, $offset, $m[0][1] - $offset ) );
            $offset = $m[0][1] + strlen( $m[0][0] );
            $out   .= self::span( 'punct', '[' . $m[1][0] ) . self::span( 'tag', $m[2][0] ) . self::highlight_attributes( $m[3][0] ) . self::span( 'punct', ']' );
        }
        return $out . esc_html( substr( $code, $offset ) );
    }

    private static function highlight_css( string $code ): string {
        $out    = '';
        $offset = 0;
        preg_match_all( '/\/\*.*?\*\/|"[^"]*"|\'[^\']*\'|[^{};\/]+\{|[\w-]+\s*:(?!:)|#[0-9a-fA-F]{3,8}\b|var\(--[\w-]+\)|--[\w-]+|-?\d*\.?\d+[a-z%]*/s', $code, $matches, PREG_OFFSET_CAPTURE );
        foreach ( $matches[0] as [ $text, $pos ] ) {
            $out   .= esc_html( substr( $code, $offset, $pos - $offset ) );
            $offset = $pos + strlen( $text );
            if ( str_starts_with( $text, '/*' ) ) {
                $out .= self::span( 'comment', $text );
            } elseif ( str_ends_with( $text, '{' ) ) {
                $selector = substr( $text, 0, -1 );
                $out     .= self::span( 'selector', rtrim( $selector ) ) . esc_html( substr( $selector, strlen( rtrim( $selector ) ) ) ) . self::span( 'punct', '{' );
            } elseif ( str_ends_with( rtrim( $text ), ':' ) ) {
                $out .= self::span( 'attr', rtrim( substr( rtrim( $text ), 0, -1 ) ) ) . self::span( 'punct', ':' );
            } elseif ( '"' === $text[0] || "'" === $text[0] ) {
                $out .= self::span( 'string', $text );
            } elseif ( str_starts_with( $text, 'var(' ) || str_starts_with( $text, '--' ) ) {
                $out .= self::span( 'variable', $text );
            } else {
                $out .= self::span( 'number', $text );
            }
        }
        return $out . esc_html( substr( $code, $offset ) );
    }

    private static function highlight_js( string $code ): string {
        $out    = '';
        $offset = 0;
        preg_match_all( '/\/\/[^\n]*|\/\*.*?\*\/|"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|`[^`]*`|\b(?:const|let|var|function|return|if|else|for|while|new|true|false|null|this|class|import|export|from|async|await)\b|\b\d+(?:\.\d+)?\b/s', $code, $matches, PREG_OFFSET_CAPTURE );
        foreach ( $matches[0] as [ $text, $pos ] ) {
            $out   .= esc_html( substr( $code, $offset, $pos - $offset ) );
            $offset = $pos + strlen( $text );
            $type   = '/' === $text[0] ? 'comment' : ( in_array( $text[0], [ '"', "'", '`' ], true ) ? 'string' : ( ctype_digit( $text[0] ) ? 'number' : 'keyword' ) );
            $out   .= self::span( $type, $text );
        }
        return $out . esc_html( substr( $code, $offset ) );
    }

    /** Styles plus one copy handler shared with ShortcodeBreakdown (works without CoreUi's assets). */
    public static function assets(): string {
        static $printed = false;
        if ( $printed ) {
            return '';
        }
        $printed = true;
        return '<style>'
            . '.hpc-code-display{background:#0f172a;border:1px solid #1e293b;border-radius:10px;box-shadow:0 1px 2px rgba(15,23,42,.08);margin:0 0 14px;overflow:hidden}'
            . '.hpc-code-display-bar{align-items:center;background:#111c33;border-bottom:1px solid #1e293b;display:flex;gap:10px;margin:0;padding:8px 12px}'
            . '.hpc-code-display-dots{display:inline-flex;gap:5px}.hpc-code-display-dots i{border-radius:50%;display:block;height:9px;width:9px}'
            . '.hpc-code-display-dots i:nth-child(1){background:#f87171}.hpc-code-display-dots i:nth-child(2){background:#fbbf24}.hpc-code-display-dots i:nth-child(3){background:#34d399}'
            . '.hpc-code-display-title{color:#cbd5e1;flex:1;font-size:12px;font-weight:600}'
            . '.hpc-code-display-lang{background:#1e293b;border-radius:999px;color:#93c5fd;font-size:10px;font-weight:700;letter-spacing:.06em;padding:2px 8px;text-transform:uppercase}'
            . '.hpc-code-display-copy{background:#1e293b;border:1px solid #334155;border-radius:6px;color:#e2e8f0;cursor:pointer;font-size:12px;padding:3px 10px}.hpc-code-display-copy:hover{background:#334155}'
            . '.hpc-code-display-pre{background:transparent;color:#e2e8f0;font:12.5px/1.6 ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;margin:0;overflow:auto;padding:14px 16px;tab-size:4;white-space:pre}'
            . '.hpc-code-display-pre code{background:none;color:inherit;font:inherit;padding:0}'
            . '.hcd-tag{color:#7dd3fc}.hcd-attr{color:#c4b5fd}.hcd-string{color:#86efac}.hcd-comment{color:#64748b;font-style:italic}.hcd-keyword{color:#f472b6}'
            . '.hcd-function,.hcd-selector{color:#fde68a}.hcd-variable{color:#fca5a5}.hcd-number{color:#fdba74}.hcd-punct{color:#94a3b8}'
            . '</style>'
            . '<script>(function(){if(window.hpcCodeCopyBound)return;window.hpcCodeCopyBound=1;document.addEventListener("click",function(e){var b=e.target.closest&&e.target.closest(".hpc-code-display-copy,.hpc-sb-copy");if(!b||!navigator.clipboard)return;e.preventDefault();e.stopPropagation();navigator.clipboard.writeText(b.getAttribute("data-hpc-copy")||"").then(function(){var o=b.textContent;b.textContent="Copied";setTimeout(function(){b.textContent=o},1200)})},true)})();</script>';
    }
}
