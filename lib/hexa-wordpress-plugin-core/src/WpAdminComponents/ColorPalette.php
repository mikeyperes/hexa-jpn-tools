<?php

namespace Hexa\PluginCore\WpAdminComponents;

use Hexa\PluginCore\BrandColors\BrandColorProvider;

final class ColorPalette {
    /**
     * Render a generic saved-color palette. This component is intentionally
     * storage-agnostic: callers pass field names/classes and collect values.
     *
     * @param array<string,mixed> $args
     */
    public static function render( array $args ): string {
        $id          = self::clean_id( (string) ( $args['id'] ?? 'hpc-color-palette' ) );
        $title       = (string) ( $args['title'] ?? 'Colors' );
        $description = (string) ( $args['description'] ?? '' );
        $colors      = self::normalize_colors( isset( $args['colors'] ) && is_array( $args['colors'] ) ? $args['colors'] : [] );
        $detector    = $args['elementor_detector'] ?? null;

        CoreUi::render_assets();

        ob_start();
        echo self::assets_once(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
        <section id="<?php echo esc_attr( $id ); ?>" class="hpc-color-palette" data-hpc-color-palette>
            <h3 class="hpc-color-palette-title"><?php echo esc_html( $title ); ?></h3>
            <?php if ( '' !== $description ) : ?>
                <p class="hpc-color-palette-note"><?php echo esc_html( $description ); ?></p>
            <?php endif; ?>
            <div class="hpc-color-palette-list">
                <?php foreach ( $colors as $color ) : ?>
                    <div class="hpc-color-palette-field">
                        <?php echo ColorControl::render( $color ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        $html = (string) ob_get_clean();

        if ( is_array( $detector ) ) {
            $html .= ElementorPaletteDetector::render( $detector );
        }

        return $html;
    }

    /**
     * @param array<int,mixed> $colors
     * @return array<int,array<string,mixed>>
     */
    private static function normalize_colors( array $colors ): array {
        $out = [];
        foreach ( $colors as $key => $color ) {
            if ( ! is_array( $color ) ) {
                continue;
            }

            $field_key = self::clean_key( (string) ( $color['key'] ?? ( is_string( $key ) ? $key : '' ) ) );
            if ( '' === $field_key ) {
                continue;
            }

            $default = BrandColorProvider::normalize_hex( (string) ( $color['default'] ?? $color['value'] ?? '#000000' ), '#000000' );
            $out[] = array_merge(
                $color,
                [
                    'key'     => $field_key,
                    'label'   => (string) ( $color['label'] ?? ucwords( str_replace( '_', ' ', $field_key ) ) ),
                    'value'   => BrandColorProvider::normalize_hex( (string) ( $color['value'] ?? $default ), $default ),
                    'default' => $default,
                ]
            );
        }

        return $out;
    }

    private static function assets_once(): string {
        static $done = false;
        if ( $done ) {
            return '';
        }
        $done = true;

        return <<<'HTML'
<style>.hpc-color-palette{background:#fff;border:1px solid #e6e9ee;border-radius:10px;padding:16px 18px}.hpc-color-palette-title{color:#1d2327;font-size:13px;font-weight:800;letter-spacing:.07em;margin:0 0 14px;text-transform:uppercase}.hpc-color-palette-note{color:#646970;font-size:12.5px;margin:0 0 14px}.hpc-color-palette-list{display:grid;gap:14px;grid-template-columns:repeat(3,minmax(0,1fr))}.hpc-color-palette-field{background:#fdfdfe;border:1px solid #e6e9ee;border-radius:9px;padding:13px 14px}.hpc-color-palette-field .hpc-color-row{align-items:stretch;flex-direction:column;flex-wrap:wrap;gap:9px}.hpc-color-palette-field .hpc-color-picker-shell,.hpc-color-palette-field .hpc-color-hex-shell{width:100%}.hpc-color-palette-field .hpc-color-picker{height:42px;width:100%}.hpc-color-palette-field .hpc-color-hex-input{width:100%}.hpc-color-palette-field [data-hpc-copy],.hpc-color-palette-field .hpc-button{justify-content:center;width:100%}.hpc-color-palette-field .hpc-color-value,.hpc-color-palette-field .hpc-color-hex-code,.hpc-color-palette-field .hpc-color-swatch{display:none!important}@media(max-width:1100px){.hpc-color-palette-list{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:680px){.hpc-color-palette-list{grid-template-columns:1fr}}</style>
HTML;
    }

    private static function clean_key( string $value ): string {
        if ( function_exists( 'sanitize_key' ) ) {
            return sanitize_key( $value );
        }

        return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?: '';
    }

    private static function clean_id( string $value ): string {
        if ( function_exists( 'sanitize_html_class' ) ) {
            return sanitize_html_class( $value );
        }

        return preg_replace( '/[^a-zA-Z0-9_\-]/', '-', $value ) ?: 'hpc-color-palette';
    }
}
