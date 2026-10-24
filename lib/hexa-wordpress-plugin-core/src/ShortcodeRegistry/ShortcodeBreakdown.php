<?php

namespace Hexa\PluginCore\ShortcodeRegistry;

use Hexa\PluginCore\WpAdminComponents\CodeDisplay;

/**
 * One shortcode explained: the shortcode with a copy button, what it does,
 * and every attribute with its default and a plain explanation.
 *
 * ShortcodeBreakdown::render( [
 *     'tag'         => 'hws_publication_founders',
 *     'title'       => 'Founders',
 *     'description' => 'Shows the publication founders.',
 *     'aliases'     => [ 'smpi_founders' ],
 *     'attributes'  => [
 *         'style' => [ 'default' => 'saved setting', 'description' => 'Card look.', 'options' => [ 'cards', 'list' ] ],
 *     ],
 *     'examples'    => [ [ 'label' => 'List look', 'shortcode' => '[hws_publication_founders style="list"]' ] ],
 * ] )
 */
final class ShortcodeBreakdown {
    /** @param array<string,mixed> $args */
    public static function render( array $args ): string {
        $tag = trim( (string) ( $args['tag'] ?? '' ), "[] \t\n" );
        if ( '' === $tag ) {
            return '';
        }
        $title       = trim( (string) ( $args['title'] ?? '' ) );
        $description = trim( (string) ( $args['description'] ?? '' ) );
        $aliases     = array_filter( array_map( 'strval', (array) ( $args['aliases'] ?? [] ) ) );
        $attributes  = is_array( $args['attributes'] ?? null ) ? $args['attributes'] : [];
        $examples    = is_array( $args['examples'] ?? null ) ? $args['examples'] : [];
        $shortcode   = '[' . $tag . ']';

        $html  = CodeDisplay::assets() . self::assets();
        $html .= '<section class="hpc-shortcode-breakdown">';
        $html .= '<header class="hpc-sb-head"><div>';
        if ( '' !== $title ) {
            $html .= '<h3>' . esc_html( $title ) . '</h3>';
        }
        if ( '' !== $description ) {
            $html .= '<p>' . esc_html( $description ) . '</p>';
        }
        $html .= '</div></header>';

        $html .= '<div class="hpc-sb-code"><code>' . CodeDisplay::highlight( $shortcode, 'shortcode' ) . '</code>'
            . '<button type="button" class="hpc-sb-copy" data-hpc-copy="' . esc_attr( $shortcode ) . '">Copy</button></div>';

        if ( $aliases ) {
            $html .= '<p class="hpc-sb-aliases">Also works as: ';
            foreach ( $aliases as $alias ) {
                $html .= '<code>[' . esc_html( trim( $alias, '[]' ) ) . ']</code> ';
            }
            $html .= '</p>';
        }

        $html .= '<h4 class="hpc-sb-label">Attributes</h4>';
        if ( ! $attributes ) {
            $html .= '<p class="hpc-sb-empty">This shortcode takes no attributes.</p>';
        } else {
            $html .= '<dl class="hpc-sb-attrs">';
            foreach ( $attributes as $name => $attribute ) {
                $attribute = is_array( $attribute ) ? $attribute : [ 'description' => (string) $attribute ];
                $default   = array_key_exists( 'default', $attribute ) ? (string) $attribute['default'] : '';
                $options   = array_map( 'strval', (array) ( $attribute['options'] ?? [] ) );
                $html     .= '<div class="hpc-sb-attr"><dt><code>' . esc_html( (string) $name ) . '</code>'
                    . '<span class="hpc-sb-default">Default: ' . ( '' !== $default ? '<code>' . esc_html( $default ) . '</code>' : '<em>empty</em>' ) . '</span></dt>'
                    . '<dd>' . esc_html( (string) ( $attribute['description'] ?? '' ) );
                if ( $options ) {
                    $html .= '<span class="hpc-sb-options">Choices: ';
                    foreach ( $options as $option ) {
                        $html .= '<code>' . esc_html( $option ) . '</code> ';
                    }
                    $html .= '</span>';
                }
                $html .= '</dd></div>';
            }
            $html .= '</dl>';
        }

        if ( $examples ) {
            $html .= '<h4 class="hpc-sb-label">Examples</h4><div class="hpc-sb-examples">';
            foreach ( $examples as $example ) {
                $example = is_array( $example ) ? $example : [ 'shortcode' => (string) $example ];
                $code    = (string) ( $example['shortcode'] ?? '' );
                $html   .= '<div class="hpc-sb-example">'
                    . ( ! empty( $example['label'] ) ? '<span>' . esc_html( (string) $example['label'] ) . '</span>' : '' )
                    . '<div class="hpc-sb-code"><code>' . CodeDisplay::highlight( $code, 'shortcode' ) . '</code>'
                    . '<button type="button" class="hpc-sb-copy" data-hpc-copy="' . esc_attr( $code ) . '">Copy</button></div></div>';
            }
            $html .= '</div>';
        }

        return $html . '</section>';
    }

    private static function assets(): string {
        static $printed = false;
        if ( $printed ) {
            return '';
        }
        $printed = true;
        return '<style>'
            . '.hpc-shortcode-breakdown{background:#fff;border:1px solid #dcdcde;border-radius:10px;margin:0 0 16px;padding:16px 18px}'
            . '.hpc-sb-head h3{font-size:15px;margin:0 0 4px}.hpc-sb-head p{color:#50575e;margin:0 0 12px}'
            . '.hpc-sb-code{align-items:center;background:#0f172a;border-radius:8px;display:flex;gap:10px;justify-content:space-between;padding:10px 12px}'
            . '.hpc-sb-code code{background:none;color:#e2e8f0;font:13px/1.5 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;padding:0;white-space:pre-wrap;word-break:break-word}'
            . '.hpc-sb-copy{background:#1e293b;border:1px solid #334155;border-radius:6px;color:#e2e8f0;cursor:pointer;flex:0 0 auto;font-size:12px;padding:3px 10px}.hpc-sb-copy:hover{background:#334155}'
            . '.hpc-sb-aliases{color:#50575e;font-size:12px;margin:8px 0 0}.hpc-sb-aliases code,.hpc-sb-options code,.hpc-sb-default code{background:#f0f0f1;border-radius:4px;font-size:12px;padding:1px 5px}'
            . '.hpc-sb-label{color:#646970;font-size:11px;letter-spacing:.06em;margin:16px 0 8px;text-transform:uppercase}'
            . '.hpc-sb-attrs{border:1px solid #e2e4e7;border-radius:8px;margin:0;overflow:hidden}'
            . '.hpc-sb-attr{border-top:1px solid #e2e4e7;padding:10px 12px}.hpc-sb-attr:first-child{border-top:0}'
            . '.hpc-sb-attr dt{align-items:center;display:flex;flex-wrap:wrap;gap:10px;margin:0 0 4px}'
            . '.hpc-sb-attr dt>code{background:#eef2ff;border-radius:4px;color:#4338ca;font-weight:700;padding:2px 6px}'
            . '.hpc-sb-default{color:#646970;font-size:12px}.hpc-sb-attr dd{color:#3c434a;margin:0}'
            . '.hpc-sb-options{color:#646970;display:block;font-size:12px;margin-top:4px}'
            . '.hpc-sb-empty{color:#646970;font-style:italic;margin:0}'
            . '.hpc-sb-examples{display:grid;gap:8px}.hpc-sb-example>span{color:#3c434a;display:block;font-size:12px;font-weight:600;margin:0 0 4px}'
            . '</style>';
    }
}
