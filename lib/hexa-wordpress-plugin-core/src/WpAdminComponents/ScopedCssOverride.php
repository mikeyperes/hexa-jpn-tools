<?php

namespace Hexa\PluginCore\WpAdminComponents;

final class ScopedCssOverride {
    public static function render( array $args ): string {
        $title = trim( (string) ( $args['title'] ?? 'CSS override reference' ) );
        $selector = trim( (string) ( $args['selector'] ?? '.hpc-scope' ) );
        $html_example = self::normalize_code( (string) ( $args['html_example'] ?? '<div class="hpc-scope">Content</div>' ) );
        $css_example = self::normalize_code( (string) ( $args['css_example'] ?? ".hpc-scope {\n  color: inherit;\n}" ) );
        $instructions = isset( $args['instructions'] ) && is_array( $args['instructions'] )
            ? $args['instructions']
            : [
                'Keep the scope selector at the start of every rule.',
                'Add a WordPress body class before it when an override should affect only one page.',
                'Paste the finished CSS into the theme or page builder custom CSS area.',
            ];
        $meta_label = trim( (string) ( $args['meta_label'] ?? 'Scoped CSS' ) );
        $open = ! empty( $args["open"] );
        $setting_key = sanitize_html_class( (string) ( $args["setting_key"] ?? "" ) );
        $editor_value = self::normalize_code( (string) ( $args["value"] ?? "" ) );
        $editor_label = trim( (string) ( $args["editor_label"] ?? "CSS override" ) );
        $editor_description = trim( (string) ( $args["editor_description"] ?? "Changes save when you leave the editor." ) );
        $editor_placeholder = self::normalize_code( (string) ( $args["placeholder"] ?? $css_example ) );
        $input_class = sanitize_html_class( (string) ( $args["input_class"] ?? "" ) );
        $status_html = (string) ( $args["status_html"] ?? "" );

        if ( '' === $title ) {
            $title = 'CSS override reference';
        }
        if ( '' === $selector ) {
            $selector = '.hpc-scope';
        }

        $instructions_html = '';
        foreach ( $instructions as $instruction ) {
            $instruction = trim( (string) $instruction );
            if ( '' !== $instruction ) {
                $instructions_html .= '<li>' . esc_html( $instruction ) . '</li>';
            }
        }
        if ( '' !== $instructions_html ) {
            $instructions_html = '<ol class="hpc-scoped-css-instructions">' . $instructions_html . '</ol>';
        }

        $selector_html = '<div class="hpc-scoped-css-selector">'
            . '<div><strong>Scope selector</strong><p>Start every override rule with this selector.</p></div>'
            . '<code>' . esc_html( $selector ) . '</code>'
            . CoreUi::copy_button( $selector, 'Copy selector' )
            . '</div>';

        $editor_html = "";
        if ( "" !== $setting_key ) {
            $editor_id = "hpc-scoped-css-editor-" . substr( md5( $setting_key . $selector ), 0, 10 );
            $description_id = $editor_id . "-description";
            $editor_html = '<div class="hpc-scoped-css-editor" data-hpc-scoped-css-editor>'
                . '<label for="' . esc_attr( $editor_id ) . '"><strong>' . esc_html( $editor_label ) . '</strong><span id="' . esc_attr( $description_id ) . '">' . esc_html( $editor_description ) . '</span></label>'
                . '<textarea id="' . esc_attr( $editor_id ) . '" class="hpc-scoped-css-editor-input' . ( "" !== $input_class ? " " . esc_attr( $input_class ) : "" ) . '" data-key="' . esc_attr( $setting_key ) . '" data-hpc-scoped-css-input aria-describedby="' . esc_attr( $description_id ) . '" spellcheck="false" placeholder="' . esc_attr( $editor_placeholder ) . '">' . esc_html( $editor_value ) . '</textarea>'
                . '<div class="hpc-scoped-css-editor-status" aria-live="polite">' . $status_html . '</div></div>';
        }

        $body = '<div class="hpc-scoped-css-override-content" data-hpc-scoped-css-override data-hpc-scope-selector="' . esc_attr( $selector ) . '">'
            . $instructions_html
            . $selector_html
            . $editor_html
            . '<div class="hpc-scoped-css-examples">'
            . self::example_html( 'HTML structure', 'HTML', $html_example )
            . self::example_html( 'CSS override', 'CSS', $css_example )
            . '</div></div>';

        ob_start();
        CoreUi::render_assets();
        echo self::assets_once(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo CoreUi::detail_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            [
                'title'     => $title,
                'body_html' => $body,
                'open'      => $open,
                'class'     => 'hpc-scoped-css-override',
                'meta_html' => '' !== $meta_label ? CoreUi::pill( $meta_label, 'dark' ) : '',
            ]
        );

        return (string) ob_get_clean();
    }

    private static function example_html( string $title, string $language, string $code ): string {
        return '<section class="hpc-scoped-css-example">'
            . '<header><div><strong>' . esc_html( $title ) . '</strong><span>' . esc_html( $language ) . '</span></div>'
            . CoreUi::copy_button( $code, 'Copy' )
            . '</header><pre><code>' . esc_html( $code ) . '</code></pre></section>';
    }

    private static function normalize_code( string $code ): string {
        return trim( str_replace( [ "\r\n", "\r" ], "\n", $code ) );
    }

    private static function assets_once(): string {
        static $rendered = false;
        if ( $rendered ) {
            return '';
        }
        $rendered = true;

        return <<<'HTML'
<style>
.hpc-scoped-css-override-content{display:grid;gap:16px;min-width:0}
.hpc-scoped-css-instructions{margin:0 0 0 20px}
.hpc-scoped-css-instructions li{color:#3f4d63;line-height:1.5;margin:5px 0}
.hpc-scoped-css-selector{align-items:center;border-bottom:1px solid var(--hpc-line);display:grid;gap:12px;grid-template-columns:minmax(180px,1fr) minmax(0,2fr) auto;padding-bottom:16px}
.hpc-scoped-css-selector strong{display:block;font-size:13px;margin-bottom:4px}
.hpc-scoped-css-selector p{color:var(--hpc-muted);font-size:12px;line-height:1.45;margin:0}
.hpc-scoped-css-selector code{background:#eef2f7;border:1px solid #d7dee8;border-radius:6px;color:#172033;display:block;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;max-width:100%;overflow:auto;padding:10px 12px;white-space:nowrap}
.hpc-scoped-css-editor{display:grid;gap:8px;min-width:0}
.hpc-scoped-css-editor label{display:grid;gap:3px}
.hpc-scoped-css-editor label strong{font-size:13px}
.hpc-scoped-css-editor label span{color:var(--hpc-muted);font-size:12px;line-height:1.45}
.hpc-scoped-css-editor-input{background:#0f1720;border:1px solid #263446;border-radius:6px;color:#dbe7f3;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;font-size:13px;line-height:1.55;min-height:260px;padding:14px;resize:vertical;width:100%}
.hpc-scoped-css-editor-input:focus{border-color:var(--hpc-blue);box-shadow:0 0 0 1px var(--hpc-blue);outline:0}
.hpc-scoped-css-editor-status{align-items:center;display:flex;min-height:24px}
.hpc-scoped-css-examples{display:grid;gap:16px}
.hpc-scoped-css-example{min-width:0}
.hpc-scoped-css-example+section{border-top:1px solid var(--hpc-line);padding-top:16px}
.hpc-scoped-css-example header{align-items:center;display:flex;gap:12px;justify-content:space-between}
.hpc-scoped-css-example header div{align-items:baseline;display:flex;gap:8px}
.hpc-scoped-css-example header strong{font-size:13px}
.hpc-scoped-css-example header span{color:var(--hpc-muted);font-size:11px;font-weight:800;text-transform:uppercase}
.hpc-scoped-css-example pre{background:#0f1720;border-radius:6px;color:#dbe7f3;margin:8px 0 0;max-width:100%;overflow:auto;padding:14px}
.hpc-scoped-css-example pre code{background:transparent;color:inherit;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;line-height:1.55;padding:0;white-space:pre}
@media(max-width:782px){.hpc-scoped-css-selector{grid-template-columns:minmax(0,1fr)}.hpc-scoped-css-selector .hpc-button{justify-self:start}}
</style>
HTML;
    }
}
