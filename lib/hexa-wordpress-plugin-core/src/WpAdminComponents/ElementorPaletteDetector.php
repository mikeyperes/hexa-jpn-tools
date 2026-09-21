<?php

namespace Hexa\PluginCore\WpAdminComponents;

use Hexa\PluginCore\BrandColors\BrandColorProvider;

final class ElementorPaletteDetector {
    /**
     * @param array<string,mixed> $args
     */
    public static function render( array $args = [] ): string {
        $id          = self::clean_id( (string) ( $args['id'] ?? 'hpc-elementor-palette' ) );
        $title       = (string) ( $args['title'] ?? 'Elementor palette' );
        $description = (string) ( $args['description'] ?? 'Reference only. Load the Elementor site colors, then copy any value into the fields you need.' );
        $button      = (string) ( $args['button_label'] ?? 'Load Elementor colors' );
        $empty       = (string) ( $args['empty_label'] ?? 'Click "Load Elementor colors" to show your Elementor palette.' );
        $auto_load   = ! empty( $args['auto_load'] );
        $palette     = isset( $args['palette'] ) && is_array( $args['palette'] ) ? $args['palette'] : BrandColorProvider::elementor_palette();

        CoreUi::render_assets();
        $payload = esc_attr( wp_json_encode( self::normalize_palette( $palette ) ) ?: '[]' );

        ob_start();
        echo self::assets_once(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
        <section id="<?php echo esc_attr( $id ); ?>" class="hpc-elementor-palette" data-hpc-elementor-palette data-palette="<?php echo $payload; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" data-loaded="<?php echo $auto_load ? '1' : '0'; ?>">
            <div class="hpc-elementor-palette-head">
                <div>
                    <h3><?php echo esc_html( $title ); ?></h3>
                    <?php if ( '' !== $description ) : ?>
                        <p><?php echo esc_html( $description ); ?></p>
                    <?php endif; ?>
                </div>
                <button type="button" class="hpc-button secondary" data-hpc-elementor-palette-load><?php echo esc_html( $button ); ?></button>
            </div>
            <div class="hpc-elementor-palette-grid" data-hpc-elementor-palette-grid data-empty="<?php echo esc_attr( $empty ); ?>"></div>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array<int,mixed> $palette
     * @return array<int,array{id:string,label:string,hex:string,source:string}>
     */
    private static function normalize_palette( array $palette ): array {
        $out = [];
        foreach ( $palette as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $hex = BrandColorProvider::normalize_hex( (string) ( $item['hex'] ?? $item['color'] ?? '' ), '' );
            if ( '#000000' === $hex && ! preg_match( '/^#?0{6}$/', (string) ( $item['hex'] ?? $item['color'] ?? '' ) ) ) {
                continue;
            }

            $id      = self::clean_key( (string) ( $item['id'] ?? $item['_id'] ?? $item['key'] ?? 'color' ) );
            $label   = (string) ( $item['label'] ?? $item['title'] ?? ucwords( str_replace( [ '_', '-' ], ' ', $id ) ) );
            $source  = (string) ( $item['source'] ?? 'Elementor' );
            $out[] = [
                'id'     => $id,
                'label'  => $label,
                'hex'    => $hex,
                'source' => $source,
            ];
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
<style>.hpc-elementor-palette{background:#fff;border:1px solid #e6e9ee;border-radius:10px;padding:16px 18px}.hpc-elementor-palette-head{align-items:center;display:flex;gap:12px;justify-content:space-between;margin-bottom:14px}.hpc-elementor-palette-head h3{font-size:13px;font-weight:800;letter-spacing:.07em;margin:0;text-transform:uppercase}.hpc-elementor-palette-head p{color:#646970;font-size:12.5px;margin:7px 0 0}.hpc-elementor-palette-grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fill,minmax(180px,1fr))}.hpc-elementor-palette[data-loaded="0"] .hpc-elementor-palette-grid:before{color:#646970;content:attr(data-empty);font-size:12.5px}.hpc-elementor-palette-card{background:#fff;border:1px solid #e6e9ee;border-radius:9px;display:grid;gap:8px;padding:12px}.hpc-elementor-palette-swatch{border:1px solid #d7dbe0;border-radius:7px;height:46px}.hpc-elementor-palette-name{color:#1d2327;font-size:12px;font-weight:700;line-height:1.3}.hpc-elementor-palette-hex{color:#50575e;font-family:Menlo,Consolas,monospace;font-size:12px}.hpc-elementor-palette-copy.is-ok{border-color:#118a3d;color:#118a3d}.hpc-elementor-palette-copy.is-err{border-color:#d63638;color:#d63638}@media(max-width:680px){.hpc-elementor-palette-head{align-items:flex-start;display:grid}.hpc-elementor-palette-head .hpc-button{justify-content:center;width:100%}}</style>
<script>(function(){if(window.HexaElementorPaletteDetectorReady)return;window.HexaElementorPaletteDetectorReady=true;function card(item){var hex=String(item.hex||"");var name=String(item.label||item.id||"Color");var source=String(item.source||"Elementor");return '<div class="hpc-elementor-palette-card"><div class="hpc-elementor-palette-swatch" style="background:'+hex.replace(/"/g,'&quot;')+'"></div><div class="hpc-elementor-palette-name">'+name.replace(/</g,'&lt;')+' · '+source.replace(/</g,'&lt;')+'</div><div class="hpc-elementor-palette-hex">'+hex+'</div><button type="button" class="hpc-button secondary hpc-elementor-palette-copy" data-hpc-copy="'+hex+'">Copy hex</button></div>';}function render(root){var grid=root.querySelector("[data-hpc-elementor-palette-grid]");if(!grid)return;var payload=[];try{payload=JSON.parse(root.getAttribute("data-palette")||"[]")||[];}catch(e){payload=[];}root.setAttribute("data-loaded","1");grid.innerHTML=payload.length?payload.map(card).join(""):'<p class="hpc-muted">No Elementor colors found.</p>';root.dispatchEvent(new CustomEvent("hexa:elementorPaletteLoaded",{bubbles:true,detail:{palette:payload}}));}document.addEventListener("click",function(event){var button=event.target.closest("[data-hpc-elementor-palette-load]");if(!button)return;var root=button.closest("[data-hpc-elementor-palette]");if(!root)return;render(root);});document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll('[data-hpc-elementor-palette][data-loaded="1"]').forEach(render);});})();</script>
HTML;
    }

    private static function clean_key( string $value ): string {
        if ( function_exists( 'sanitize_key' ) ) {
            return sanitize_key( $value );
        }

        return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?: 'color';
    }

    private static function clean_id( string $value ): string {
        if ( function_exists( 'sanitize_html_class' ) ) {
            return sanitize_html_class( $value );
        }

        return preg_replace( '/[^a-zA-Z0-9_\-]/', '-', $value ) ?: 'hpc-elementor-palette';
    }
}
