<?php

namespace Hexa\PluginCore\ShortcodeRegistry;

use Hexa\PluginCore\WpAdminComponents\CoreUi;

final class ShortcodeDisplayRenderer {
    /**
     * @param ShortcodeRegistry|array<int|string,mixed> $shortcodes
     * @param array<string,mixed> $args
     */
    public function render( ShortcodeRegistry|array $shortcodes, array $args = [] ): string {
        $items = $shortcodes instanceof ShortcodeRegistry ? array_values( $shortcodes->all() ) : array_values( $shortcodes );
        $items = array_map( [ $this, 'normalize_item' ], $items );

        ob_start();
        CoreUi::render_assets();
        $this->render_styles();
        ?>
        <div class="hpc-ui hpc-shortcode-display">
            <?php if ( ! empty( $args['title'] ) ) : ?>
                <div class="hpc-shortcode-display-head">
                    <h2><?php echo esc_html( (string) $args['title'] ); ?></h2>
                    <?php if ( ! empty( $args['description'] ) ) : ?>
                        <p><?php echo esc_html( (string) $args['description'] ); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="hpc-shortcode-list">
                <?php foreach ( $items as $item ) : ?>
                    <?php echo $this->render_item( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param mixed $item
     * @return array<string,mixed>
     */
    private function normalize_item( mixed $item ): array {
        if ( $item instanceof ShortcodeDefinition ) {
            return [
                'id'          => $item->id,
                'label'       => $item->label,
                'shortcode'   => $item->shortcode(),
                'description' => $item->description,
                'test_method' => $item->test_method,
                'examples'    => [
                    [
                        'label'      => $item->label,
                        'shortcode'  => $item->shortcode( $item->default_input ),
                        'parameters' => '' !== $item->default_input ? [ $item->input_label => $item->default_input ] : [],
                    ],
                ],
            ];
        }

        $item = is_array( $item ) ? $item : [];
        $tag  = isset( $item['tag'] ) ? (string) $item['tag'] : ( isset( $item['id'] ) ? (string) $item['id'] : '' );

        if ( empty( $item['shortcode'] ) && '' !== $tag ) {
            $item['shortcode'] = '[' . $tag . ']';
        }

        $item['label']       = isset( $item['label'] ) ? (string) $item['label'] : ( '' !== $tag ? $tag : 'Shortcode' );
        $item['shortcode']   = isset( $item['shortcode'] ) ? (string) $item['shortcode'] : '';
        $item['description'] = isset( $item['description'] ) ? (string) $item['description'] : '';
        $item['test_method'] = isset( $item['test_method'] ) ? (string) $item['test_method'] : '';
        $item['source']      = isset( $item['source'] ) ? (string) $item['source'] : '';
        $item['provider']    = isset( $item['provider'] ) ? (string) $item['provider'] : '';
        $item['examples']    = isset( $item['examples'] ) && is_array( $item['examples'] ) ? $item['examples'] : [];
        $item['parameters']  = isset( $item['parameters'] ) && is_array( $item['parameters'] ) ? $item['parameters'] : [];
        $item['evaluate']    = array_key_exists( 'evaluate', $item ) ? (bool) $item['evaluate'] : true;

        if ( empty( $item['examples'] ) && '' !== $item['shortcode'] ) {
            $item['examples'] = [
                [
                    'label'      => 'Default',
                    'shortcode'  => $item['shortcode'],
                    'parameters' => $item['parameters'],
                ],
            ];
        }

        return $item;
    }

    /**
     * @param array<string,mixed> $item
     */
    private function render_item( array $item ): string {
        $output = $this->resolve_output( $item );

        ob_start();
        ?>
        <article class="hpc-shortcode-row">
            <div class="hpc-shortcode-main">
                <div class="hpc-shortcode-title">
                    <strong><?php echo esc_html( (string) $item['label'] ); ?></strong>
                    <?php if ( ! empty( $item['provider'] ) ) : ?>
                        <span class="hpc-pill dark"><?php echo esc_html( (string) $item['provider'] ); ?></span>
                    <?php endif; ?>
                </div>
                <?php if ( '' !== (string) $item['description'] ) : ?>
                    <p><?php echo esc_html( (string) $item['description'] ); ?></p>
                <?php endif; ?>
                <?php if ( '' !== (string) $item['source'] ) : ?>
                    <p class="hpc-small">Source: <span class="hpc-code"><?php echo esc_html( (string) $item['source'] ); ?></span></p>
                <?php endif; ?>
                <?php if ( '' !== (string) $item['test_method'] ) : ?>
                    <p class="hpc-small">Test: <?php echo esc_html( (string) $item['test_method'] ); ?></p>
                <?php endif; ?>
            </div>

            <div class="hpc-shortcode-code">
                <span class="hpc-shortcode-label">Shortcode</span>
                <code><?php echo esc_html( (string) $item['shortcode'] ); ?></code>
                <?php echo CoreUi::copy_button( (string) $item['shortcode'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>

            <div class="hpc-shortcode-output">
                <span class="hpc-shortcode-label">Real output value</span>
                <?php echo $this->render_output( $output ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>

            <div class="hpc-shortcode-examples">
                <span class="hpc-shortcode-label">Examples with parameters</span>
                <?php echo $this->render_examples( $item['examples'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </article>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array<string,mixed> $item
     */
    private function resolve_output( array $item ): mixed {
        if ( array_key_exists( 'output_html', $item ) ) {
            return $item['output_html'];
        }

        if ( array_key_exists( 'output', $item ) ) {
            return is_callable( $item['output'] ) ? call_user_func( $item['output'], $item ) : $item['output'];
        }

        if ( ! empty( $item['evaluate'] ) && '' !== (string) $item['shortcode'] && function_exists( 'do_shortcode' ) ) {
            return do_shortcode( (string) $item['shortcode'] );
        }

        return '';
    }

    private function render_output( mixed $output ): string {
        if ( is_array( $output ) || is_object( $output ) ) {
            $output = function_exists( 'wp_json_encode' ) ? wp_json_encode( $output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) : json_encode( $output );
        }

        $output = trim( (string) $output );

        if ( '' === $output ) {
            return '<span class="hpc-shortcode-empty">Empty</span>';
        }

        $text = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $output ) : strip_tags( $output );
        $text = trim( (string) $text );

        if ( '' === $text ) {
            return '<div class="hpc-shortcode-rendered">' . ( function_exists( 'wp_kses_post' ) ? wp_kses_post( $output ) : $output ) . '</div>';
        }

        return '<div class="hpc-shortcode-rendered">'
            . ( function_exists( 'wp_kses_post' ) ? wp_kses_post( $output ) : $output )
            . '</div><pre class="hpc-shortcode-raw">'
            . esc_html( $text )
            . '</pre>';
    }

    /**
     * @param array<int|string,mixed> $examples
     */
    private function render_examples( array $examples ): string {
        if ( empty( $examples ) ) {
            return '<span class="hpc-shortcode-empty">No examples registered.</span>';
        }

        $html = '<div class="hpc-shortcode-example-list">';
        foreach ( $examples as $example ) {
            $example = $this->normalize_example( $example );
            $html .= '<div class="hpc-shortcode-example">';
            $html .= '<div><strong>' . esc_html( $example['label'] ) . '</strong>';
            if ( '' !== $example['description'] ) {
                $html .= '<p>' . esc_html( $example['description'] ) . '</p>';
            }
            $html .= '</div><code>' . esc_html( $example['shortcode'] ) . '</code>';
            $html .= CoreUi::copy_button( $example['shortcode'], 'Copy example' );
            $html .= $this->render_parameters( $example['parameters'] );
            $html .= '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @param mixed $example
     * @return array{label:string,shortcode:string,description:string,parameters:array<string,mixed>}
     */
    private function normalize_example( mixed $example ): array {
        if ( is_string( $example ) ) {
            return [
                'label'       => 'Example',
                'shortcode'   => $example,
                'description' => '',
                'parameters'  => [],
            ];
        }

        $example = is_array( $example ) ? $example : [];

        return [
            'label'       => isset( $example['label'] ) ? (string) $example['label'] : 'Example',
            'shortcode'   => isset( $example['shortcode'] ) ? (string) $example['shortcode'] : '',
            'description' => isset( $example['description'] ) ? (string) $example['description'] : '',
            'parameters'  => isset( $example['parameters'] ) && is_array( $example['parameters'] ) ? $example['parameters'] : [],
        ];
    }

    /**
     * @param array<string,mixed> $parameters
     */
    private function render_parameters( array $parameters ): string {
        if ( empty( $parameters ) ) {
            return '<p class="hpc-small">Parameters: none</p>';
        }

        $html = '<div class="hpc-shortcode-params">';
        foreach ( $parameters as $key => $value ) {
            $encoded = is_scalar( $value )
                ? (string) $value
                : ( function_exists( 'wp_json_encode' ) ? wp_json_encode( $value ) : json_encode( $value ) );
            $html .= '<span><b>' . esc_html( (string) $key ) . '</b>=<code>' . esc_html( (string) $encoded ) . '</code></span>';
        }
        $html .= '</div>';

        return $html;
    }

    private function render_styles(): void {
        static $rendered = false;

        if ( $rendered ) {
            return;
        }

        $rendered = true;
        ?>
        <style>
            .hpc-shortcode-display-head{margin:0 0 14px}
            .hpc-shortcode-display-head h2{font-size:18px;margin:0 0 6px}
            .hpc-shortcode-display-head p{color:#4b5563;margin:0}
            .hpc-shortcode-list{display:grid;gap:14px}
            .hpc-shortcode-row{background:#fff;border:1px solid #d9e0ea;border-radius:8px;display:grid;gap:14px;grid-template-columns:minmax(220px,1fr) minmax(260px,1fr);padding:16px}
            .hpc-shortcode-title{align-items:center;display:flex;flex-wrap:wrap;gap:8px;margin:0 0 6px}
            .hpc-shortcode-title strong{font-size:15px}
            .hpc-shortcode-main p{color:#3f4d63;line-height:1.5;margin:0 0 8px}
            .hpc-shortcode-code,.hpc-shortcode-output,.hpc-shortcode-examples{background:#fbfcfe;border:1px solid #e3e8f0;border-radius:8px;padding:12px}
            .hpc-shortcode-examples{grid-column:1/-1}
            .hpc-shortcode-label{color:#637085;display:block;font-size:11px;font-weight:800;letter-spacing:.06em;margin:0 0 7px;text-transform:uppercase}
            .hpc-shortcode-code code,.hpc-shortcode-example code,.hpc-shortcode-raw{background:#eef0f2;border-radius:5px;color:#263244;display:block;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;padding:7px 9px;white-space:pre-wrap;word-break:break-word}
            .hpc-shortcode-code .hpc-button,.hpc-shortcode-example .hpc-button{margin-top:8px}
            .hpc-shortcode-rendered{background:#fff;border:1px solid #e3e8f0;border-radius:6px;color:#1f2937;margin:0 0 8px;max-height:180px;overflow:auto;padding:9px}
            .hpc-shortcode-raw{font-size:12px;margin:0;max-height:140px;overflow:auto}
            .hpc-shortcode-empty{color:#64748b;font-style:italic}
            .hpc-shortcode-example-list{display:grid;gap:10px}
            .hpc-shortcode-example{border:1px solid #e3e8f0;border-radius:8px;display:grid;gap:8px;grid-template-columns:minmax(180px,260px) minmax(260px,1fr) auto;padding:10px}
            .hpc-shortcode-example p{color:#64748b;font-size:12px;margin:3px 0 0}
            .hpc-shortcode-params{display:flex;flex-wrap:wrap;gap:6px;grid-column:1/-1}
            .hpc-shortcode-params span{background:#eef6ff;border:1px solid #d4e7ff;border-radius:999px;color:#263244;font-size:12px;padding:5px 8px}
            .hpc-shortcode-params code{display:inline;padding:1px 4px}
            @media(max-width:980px){.hpc-shortcode-row,.hpc-shortcode-example{grid-template-columns:1fr}.hpc-shortcode-example .hpc-button{width:max-content}}
        </style>
        <?php
    }
}
