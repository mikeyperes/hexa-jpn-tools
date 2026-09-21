<?php

namespace Hexa\PluginCore\WpAdminComponents;

use Hexa\PluginCore\BrandColors\BrandColorProvider;

final class DetailedColorPicker {
    public static function render( array $args ): string {
        $id = self::clean_id( (string) ( $args["id"] ?? "hpc-detailed-color-picker" ) );
        $title = (string) ( $args["title"] ?? "Detailed Color Picker" );
        $description = (string) ( $args["description"] ?? "" );
        $show_primary = array_key_exists( "show_primary", $args ) ? ! empty( $args["show_primary"] ) : true;
        $show_secondary = array_key_exists( "show_secondary", $args ) ? ! empty( $args["show_secondary"] ) : true;
        $show_fonts = array_key_exists( "show_fonts", $args ) ? ! empty( $args["show_fonts"] ) : false;
        $show_elementor_import = array_key_exists( "show_elementor_import", $args ) ? ! empty( $args["show_elementor_import"] ) : true;
        $primary = self::color_args( (array) ( $args["primary"] ?? [] ), "primary_color", "Primary color", "#2d5277" );
        $secondary = self::color_args( (array) ( $args["secondary"] ?? [] ), "secondary_color", "Secondary color", "#111827" );
        $elementor = BrandColorProvider::elementor_payload( (string) $primary["default"], (string) $secondary["default"] );
        $font_controls = self::font_controls( (array) ( $args["fonts"] ?? [] ) );

        CoreUi::render_assets();
        $payload = esc_attr( wp_json_encode( $elementor ) ?: "{}" );

        ob_start();
        echo self::assets_once(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
        <section id="<?php echo esc_attr( $id ); ?>" class="hpc-detailed-color-picker" data-hpc-detailed-color-picker data-hpc-elementor-payload="<?php echo $payload; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
            <div class="hpc-detailed-color-head">
                <div>
                    <h3><?php echo esc_html( $title ); ?></h3>
                    <?php if ( "" !== $description ) : ?>
                        <p><?php echo esc_html( $description ); ?></p>
                    <?php endif; ?>
                </div>
                <?php if ( $show_elementor_import ) : ?>
                    <button type="button" class="hpc-button secondary" data-hpc-detailed-import-elementor>Import Elementor colors<?php echo $show_fonts ? " and fonts" : ""; ?></button>
                <?php endif; ?>
            </div>
            <div class="hpc-detailed-color-grid">
                <?php if ( $show_primary ) : ?>
                    <div class="hpc-detailed-color-slot" data-hpc-detailed-primary>
                        <?php echo ColorControl::render( $primary ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php endif; ?>
                <?php if ( $show_secondary ) : ?>
                    <div class="hpc-detailed-color-slot" data-hpc-detailed-secondary>
                        <?php echo ColorControl::render( $secondary ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ( $show_fonts && $font_controls ) : ?>
                <div class="hpc-detailed-font-grid">
                    <?php foreach ( $font_controls as $control ) : ?>
                        <label class="hpc-detailed-font-field">
                            <span><?php echo esc_html( (string) $control["label"] ); ?></span>
                            <input
                                type="<?php echo esc_attr( (string) $control["type"] ); ?>"
                                name="<?php echo esc_attr( (string) $control["name"] ); ?>"
                                class="<?php echo esc_attr( (string) $control["class"] ); ?>"
                                value="<?php echo esc_attr( (string) $control["value"] ); ?>"
                                data-hpc-detailed-font="<?php echo esc_attr( (string) $control["token"] ); ?>"
                                <?php echo self::number_attrs( $control ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            >
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private static function color_args( array $args, string $key, string $label, string $default ): array {
        $args["key"] = self::clean_key( (string) ( $args["key"] ?? $key ) );
        $args["label"] = (string) ( $args["label"] ?? $label );
        $args["default"] = BrandColorProvider::normalize_hex( (string) ( $args["default"] ?? $default ), $default );
        $args["value"] = BrandColorProvider::normalize_hex( (string) ( $args["value"] ?? $args["default"] ), (string) $args["default"] );
        $args["control_class"] = trim( "hpc-detailed-inner-color " . (string) ( $args["control_class"] ?? "" ) );
        $args["hex_input_class"] = trim( "hpc-detailed-color-input " . (string) ( $args["hex_input_class"] ?? "" ) );
        $args["picker_class"] = trim( "hpc-detailed-color-picker-input " . (string) ( $args["picker_class"] ?? "" ) );
        return $args;
    }

    private static function font_controls( array $controls ): array {
        $defaults = [
            [
                "key" => "primary_font_family",
                "token" => "primary_font_family",
                "label" => "Primary font family",
                "type" => "text",
                "value" => "",
            ],
            [
                "key" => "secondary_font_family",
                "token" => "secondary_font_family",
                "label" => "Secondary font family",
                "type" => "text",
                "value" => "",
            ],
        ];
        $controls = $controls ?: $defaults;
        $out = [];
        foreach ( $controls as $control ) {
            if ( ! is_array( $control ) ) {
                continue;
            }
            $key = self::clean_key( (string) ( $control["key"] ?? $control["name"] ?? "" ) );
            if ( "" === $key ) {
                continue;
            }
            $type = (string) ( $control["type"] ?? "text" );
            $out[] = [
                "key" => $key,
                "token" => self::clean_key( (string) ( $control["token"] ?? $key ) ),
                "name" => (string) ( $control["name"] ?? $key ),
                "label" => (string) ( $control["label"] ?? ucwords( str_replace( "_", " ", $key ) ) ),
                "type" => in_array( $type, [ "text", "number" ], true ) ? $type : "text",
                "value" => (string) ( $control["value"] ?? "" ),
                "class" => trim( "hpc-detailed-font-input " . (string) ( $control["class"] ?? "" ) ),
                "min" => isset( $control["min"] ) ? (int) $control["min"] : null,
                "max" => isset( $control["max"] ) ? (int) $control["max"] : null,
                "step" => isset( $control["step"] ) ? (string) $control["step"] : null,
            ];
        }
        return $out;
    }

    private static function number_attrs( array $control ): string {
        if ( "number" !== (string) $control["type"] ) {
            return "";
        }
        $attrs = [];
        foreach ( [ "min", "max", "step" ] as $name ) {
            if ( null !== $control[ $name ] ) {
                $attrs[] = $name . '="' . esc_attr( (string) $control[ $name ] ) . '"';
            }
        }
        return implode( " ", $attrs );
    }

    private static function assets_once(): string {
        static $rendered = false;
        if ( $rendered ) {
            return "";
        }
        $rendered = true;

        return <<<'HTML'
<style>.hpc-detailed-color-picker{background:#fff;border:1px solid #d9e0ea;border-radius:8px;display:grid;gap:14px;padding:16px}.hpc-detailed-color-head{align-items:flex-start;display:flex;gap:12px;justify-content:space-between}.hpc-detailed-color-head h3{font-size:15px;margin:0 0 5px}.hpc-detailed-color-head p{color:#64748b;margin:0}.hpc-detailed-color-grid{display:grid;gap:14px;grid-template-columns:repeat(2,minmax(0,1fr))}.hpc-detailed-color-slot{min-width:0}.hpc-detailed-font-grid{display:grid;gap:12px;grid-template-columns:repeat(2,minmax(0,1fr))}.hpc-detailed-font-field{display:grid;gap:5px}.hpc-detailed-font-field span{color:#314056;font-size:12px;font-weight:800;text-transform:uppercase}.hpc-detailed-font-field input{background:#fff;border:1px solid #a9b4c3;border-radius:6px;min-height:40px;padding:8px 10px;width:100%}@media(max-width:900px){.hpc-detailed-color-head,.hpc-detailed-color-grid,.hpc-detailed-font-grid{grid-template-columns:1fr}.hpc-detailed-color-head{display:grid}}</style>
<script>(function(){if(window.hexaDetailedColorPickerReady)return;window.hexaDetailedColorPickerReady=true;function parse(root){try{return JSON.parse(root.getAttribute("data-hpc-elementor-payload")||"{}")||{};}catch(e){return {};}}function setColor(root,key,value){if(!value)return;var input=root.querySelector('[data-hpc-color-hex-input][data-key="'+key+'"]');if(!input)return;input.value=value;input.dispatchEvent(new Event("input",{bubbles:true}));input.dispatchEvent(new Event("change",{bubbles:true}));}function setFont(root,token,value){if(!value)return;var input=root.querySelector('[data-hpc-detailed-font="'+token+'"]');if(input){input.value=value;input.dispatchEvent(new Event("input",{bubbles:true}));input.dispatchEvent(new Event("change",{bubbles:true}));}}document.addEventListener("click",function(event){var button=event.target.closest("[data-hpc-detailed-import-elementor]");if(!button)return;var root=button.closest("[data-hpc-detailed-color-picker]");if(!root)return;var payload=parse(root);var primary=root.querySelector("[data-hpc-detailed-primary] [data-hpc-color-hex-input]");var secondary=root.querySelector("[data-hpc-detailed-secondary] [data-hpc-color-hex-input]");if(primary&&payload.primary_color){setColor(root,primary.getAttribute("data-key"),payload.primary_color)}if(secondary&&payload.secondary_color){setColor(root,secondary.getAttribute("data-key"),payload.secondary_color)}["primary_font_family","secondary_font_family","text_font_family","accent_font_family"].forEach(function(token){setFont(root,token,payload[token]);});var old=button.textContent;button.textContent="Imported";window.setTimeout(function(){button.textContent=old;},1200);});})();</script>
HTML;
    }

    private static function clean_key( string $value ): string {
        if ( function_exists( "sanitize_key" ) ) {
            return sanitize_key( $value );
        }

        return preg_replace( "/[^a-z0-9_\-]/", "", strtolower( $value ) ) ?: "";
    }

    private static function clean_id( string $value ): string {
        if ( function_exists( "sanitize_html_class" ) ) {
            return sanitize_html_class( $value );
        }

        return preg_replace( "/[^a-zA-Z0-9_\-]/", "-", $value ) ?: "hpc-detailed-color-picker";
    }
}
