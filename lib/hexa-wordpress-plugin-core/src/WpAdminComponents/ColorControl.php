<?php

namespace Hexa\PluginCore\WpAdminComponents;

use Hexa\PluginCore\BrandColors\BrandColorProvider;

final class ColorControl {
    public static function render( array $args ): string {
        $key = self::clean_key( (string) ( $args["key"] ?? "brand_primary_color" ) );
        $label = (string) ( $args["label"] ?? "Color" );
        $fallback = BrandColorProvider::normalize_hex( (string) ( $args["default"] ?? "#2d5277" ), "#2d5277" );
        $allow_inherit = ! empty( $args["allow_inherit"] );
        $inherited_value = BrandColorProvider::normalize_hex( (string) ( $args["inherited_value"] ?? $fallback ), $fallback );
        $raw_value = (string) ( $args["value"] ?? ( $allow_inherit ? "" : $fallback ) );
        $explicit_value = $allow_inherit ? self::optional_hex( $raw_value ) : BrandColorProvider::normalize_hex( $raw_value, $fallback );
        $is_inherited = $allow_inherit && "" === $explicit_value;
        $value = $is_inherited ? $inherited_value : $explicit_value;
        $brand = BrandColorProvider::primary_color( $fallback );
        $description = (string) ( $args["description"] ?? "" );
        $control_class = trim( "hpc-color-control " . (string) ( $args["control_class"] ?? "" ) );
        $picker_class = trim( "hpc-color-picker " . (string) ( $args["picker_class"] ?? "" ) );
        $hex_input_class = trim( "hpc-color-hex-input " . (string) ( $args["hex_input_class"] ?? "" ) );
        $value_input_class = trim( "hpc-color-value-input " . (string) ( $args["value_input_class"] ?? "" ) );
        $disabled = ! empty( $args["disabled"] ) ? " disabled" : "";
        $import_brand = ! empty( $args["import_brand"] );
        $import_class = trim( "hpc-button secondary hpc-brand-import " . (string) ( $args["import_button_class"] ?? "" ) );
        $import_label = (string) ( $args["import_label"] ?? "Import HWS primary" );
        $inherit_class = trim( "hpc-button secondary hpc-color-inherit " . (string) ( $args["inherit_button_class"] ?? "" ) );
        $inherit_label = (string) ( $args["inherit_label"] ?? "Use inherited" );
        $show_inherit_action = ! array_key_exists( "show_inherit_action", $args ) || ! empty( $args["show_inherit_action"] );
        $inherited_status_label = (string) ( $args["inherited_status_label"] ?? "Inherited" );
        $custom_status_label = (string) ( $args["custom_status_label"] ?? "Custom" );
        $status_html = (string) ( $args["status_html"] ?? "" );
        $name = (string) ( $args["name"] ?? $key );
        $id = self::clean_id( (string) ( $args["id"] ?? "hpc-color-" . $key ) );
        $show_picker = array_key_exists( "show_picker", $args ) ? ! empty( $args["show_picker"] ) : true;
        $show_hex_input = array_key_exists( "show_hex_input", $args ) ? ! empty( $args["show_hex_input"] ) : true;
        $show_rgb = array_key_exists( "show_rgb", $args ) ? ! empty( $args["show_rgb"] ) : true;
        $show_hex_code = array_key_exists( "show_hex_code", $args ) ? ! empty( $args["show_hex_code"] ) : true;
        $show_swatch = array_key_exists( "show_swatch", $args ) ? ! empty( $args["show_swatch"] ) : true;
        $show_copy = array_key_exists( "show_copy", $args ) ? ! empty( $args["show_copy"] ) : true;
        $header_action_html = (string) ( $args["header_action_html"] ?? "" );
        $preview_scope = trim( (string) ( $args["preview_scope"] ?? "" ) );
        $preview_variables = self::preview_variables( $args["preview_variables"] ?? [] );

        ob_start();
        CoreUi::render_assets();
        echo self::assets_once(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
        <div
            class="<?php echo esc_attr( $control_class . ( $is_inherited ? " is-inherited" : "" ) ); ?>"
            data-hpc-color-control
            data-key="<?php echo esc_attr( $key ); ?>"
            data-hpc-brand-primary="<?php echo esc_attr( $brand ); ?>"
            data-hpc-color-inherit-enabled="<?php echo $allow_inherit ? "true" : "false"; ?>"
            data-hpc-color-inherited="<?php echo $is_inherited ? "true" : "false"; ?>"
            data-hpc-inherited-value="<?php echo esc_attr( $inherited_value ); ?>"
            data-hpc-color-effective="<?php echo esc_attr( $value ); ?>"
            data-hpc-inherited-label="<?php echo esc_attr( $inherited_status_label ); ?>"
            data-hpc-custom-label="<?php echo esc_attr( $custom_status_label ); ?>"
            data-hpc-color-preview-scope="<?php echo esc_attr( $preview_scope ); ?>"
            data-hpc-color-preview-variables="<?php echo esc_attr( self::json( $preview_variables ) ); ?>"
        >
            <div class="hpc-color-head">
                <div>
                    <h3><?php echo esc_html( $label ); ?></h3>
                    <?php if ( "" !== $description ) : ?>
                        <p><?php echo esc_html( $description ); ?></p>
                    <?php endif; ?>
                </div>
                <?php if ( "" !== $header_action_html ) : ?>
                    <div class="hpc-color-head-action"><?php echo $header_action_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php endif; ?>
            </div>
            <div class="hpc-color-row">
                <?php if ( $allow_inherit ) : ?>
                    <input
                        class="<?php echo esc_attr( $value_input_class ); ?>"
                        type="hidden"
                        name="<?php echo esc_attr( $name ); ?>"
                        data-key="<?php echo esc_attr( $key ); ?>"
                        data-hpc-color-value-input
                        value="<?php echo esc_attr( $explicit_value ); ?>"
                        <?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    >
                <?php endif; ?>
                <?php if ( $show_picker ) : ?>
                    <label class="hpc-color-picker-shell" for="<?php echo esc_attr( $id ); ?>">
                        <span>Picker</span>
                        <input id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( $picker_class ); ?>" type="color" value="<?php echo esc_attr( $value ); ?>" data-hpc-color-picker<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                    </label>
                <?php endif; ?>
                <?php if ( $show_hex_input ) : ?>
                    <label class="hpc-color-hex-shell">
                        <span>Hex</span>
                        <input
                            class="<?php echo esc_attr( $hex_input_class ); ?>"
                            type="text"
                            <?php if ( ! $allow_inherit ) : ?>
                                name="<?php echo esc_attr( $name ); ?>"
                            <?php endif; ?>
                            data-key="<?php echo esc_attr( $key ); ?>"
                            data-hpc-color-hex-input
                            value="<?php echo esc_attr( $value ); ?>"
                            inputmode="text"
                            maxlength="7"
                            autocomplete="off"
                            spellcheck="false"
                            pattern="#?[0-9a-fA-F]{6}"
                            <?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        >
                    </label>
                <?php endif; ?>
                <?php if ( $show_rgb ) : ?>
                    <div class="hpc-color-value">
                        <span>RGB</span>
                        <code data-hpc-color-rgb data-smpi-color-rgb><?php echo esc_html( BrandColorProvider::rgb_string( $value ) ); ?></code>
                    </div>
                <?php endif; ?>
                <?php if ( $show_hex_code ) : ?>
                    <code class="hpc-color-hex-code" data-hpc-color-hex data-smpi-color-hex><?php echo esc_html( $value ); ?></code>
                <?php endif; ?>
                <?php if ( $show_swatch ) : ?>
                    <span class="hpc-color-swatch smpi-color-swatch" data-hpc-color-swatch style="background:<?php echo esc_attr( $value ); ?>"></span>
                <?php endif; ?>
                <?php if ( $show_copy ) : ?>
                    <?php echo CoreUi::copy_button( $value, $is_inherited ? "Copy inherited hex" : "Copy hex" ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endif; ?>
                <?php if ( $import_brand ) : ?>
                    <button type="button" class="<?php echo esc_attr( $import_class ); ?>" data-hpc-brand-color-import data-smpi-import-brand-color data-key="<?php echo esc_attr( $key ); ?>" data-brand-color="<?php echo esc_attr( $brand ); ?>"<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $import_label ); ?></button>
                <?php endif; ?>
                <?php if ( $allow_inherit && $show_inherit_action ) : ?>
                    <span class="hpc-color-inherit-state" data-hpc-color-inherit-state aria-live="polite"><?php echo esc_html( $is_inherited ? $inherited_status_label : $custom_status_label ); ?></span>
                    <button type="button" class="<?php echo esc_attr( $inherit_class ); ?>" data-hpc-color-inherit<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $inherit_label ); ?></button>
                <?php endif; ?>
                <?php echo $status_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function assets_once(): string {
        static $rendered = false;
        if ( $rendered ) {
            return "";
        }
        $rendered = true;

        return <<<'HTML'
<style>.hpc-color-control{display:grid;gap:10px;min-width:0}.hpc-color-head{align-items:center;display:flex;flex-wrap:wrap;gap:10px}.hpc-color-head h3{font-size:13px;letter-spacing:0;margin:0 0 5px;text-transform:uppercase}.hpc-color-head p{color:#64748b;margin:0}.hpc-color-head-action{align-items:center;display:flex}.hpc-color-row{align-items:end;display:flex;flex-wrap:wrap;gap:10px}.hpc-color-picker-shell,.hpc-color-hex-shell,.hpc-color-value{display:grid;gap:5px}.hpc-color-picker-shell span,.hpc-color-hex-shell span,.hpc-color-value span{color:#475569;font-size:11px;font-weight:800;letter-spacing:0;text-transform:uppercase}.hpc-color-picker{height:42px;width:64px}.hpc-color-hex-input{background:#fff;border:1px solid #a9b4c3;border-radius:6px;min-height:40px;padding:8px 10px;width:130px}.hpc-color-value code,.hpc-color-hex-code{align-items:center;background:#eef0f3;border-radius:6px;color:#172033;display:inline-flex;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;min-height:40px;padding:8px 10px}.hpc-color-swatch{border:1px solid #cbd5e1;border-radius:10px;display:inline-block;height:40px;width:40px}.hpc-brand-import{white-space:nowrap}.hpc-color-inherit-state{align-items:center;background:#e8f1f8;border-radius:999px;color:#31546f;display:inline-flex;font-size:11px;font-weight:800;min-height:30px;padding:3px 10px;text-transform:uppercase}.hpc-color-control:not(.is-inherited) .hpc-color-inherit-state{background:#edf7ef;color:#216e39}.hpc-color-control.is-invalid .hpc-color-hex-input{border-color:#b32d2e;box-shadow:0 0 0 1px #b32d2e}</style>
<script>(function(){if(window.hexaColorControlReady)return;window.hexaColorControlReady=true;function hex(v){v=String(v||"").trim().toLowerCase();if(v&&v.charAt(0)!=="#")v="#"+v;if(/^#[0-9a-f]{3}$/.test(v)){v="#"+v[1]+v[1]+v[2]+v[2]+v[3]+v[3]}return /^#[0-9a-f]{6}$/.test(v)?v:""}function rgb(h){h=hex(h);if(!h)return "";return "rgb("+parseInt(h.slice(1,3),16)+", "+parseInt(h.slice(3,5),16)+", "+parseInt(h.slice(5,7),16)+")"}function storage(control){return control?control.querySelector("[data-hpc-color-value-input]"):null}function inheritedValue(control){return hex(control?control.getAttribute("data-hpc-inherited-value"):"")}function setState(control,isInherited){if(!control)return;control.classList.toggle("is-inherited",!!isInherited);control.setAttribute("data-hpc-color-inherited",isInherited?"true":"false");var state=control.querySelector("[data-hpc-color-inherit-state]");if(state){state.textContent=control.getAttribute(isInherited?"data-hpc-inherited-label":"data-hpc-custom-label")||(isInherited?"Inherited":"Custom")}}function sync(control,value,isInherited){var h=hex(value);if(!control||!h){if(control)control.classList.add("is-invalid");return ""}control.classList.remove("is-invalid");var picker=control.querySelector("[data-hpc-color-picker]");var input=control.querySelector("[data-hpc-color-hex-input]");var swatch=control.querySelector("[data-hpc-color-swatch]");var hexEl=control.querySelector("[data-hpc-color-hex]");var rgbEl=control.querySelector("[data-hpc-color-rgb]");var copy=control.querySelector("[data-hpc-copy]");if(picker)picker.value=h;if(input)input.value=h;if(swatch)swatch.style.background=h;if(hexEl)hexEl.textContent=h;if(rgbEl)rgbEl.textContent=rgb(h);if(copy){copy.setAttribute("data-hpc-copy",h);copy.setAttribute("aria-label",isInherited?"Copy inherited hex":"Copy hex")}control.setAttribute("data-hpc-color-effective",h);setState(control,!!isInherited);document.dispatchEvent(new CustomEvent("hexa-color-change",{detail:{control:control,key:control.getAttribute("data-key")||"",color:h,inherited:!!isInherited}}));return h}function dispatchChange(input){if(input)input.dispatchEvent(new Event("change",{bubbles:true}))}function commit(control,value){var h=sync(control,value,false);if(!h)return;var valueInput=storage(control);if(valueInput){valueInput.value=h;dispatchChange(valueInput);return}var input=control.querySelector("[data-hpc-color-hex-input]");if(input)dispatchChange(input)}function applyByKey(key,value,shouldCommit){document.querySelectorAll('[data-hpc-color-control][data-key="'+String(key).replace(/"/g,"")+'"]').forEach(function(control){if(shouldCommit)commit(control,value);else sync(control,value,false)})}document.addEventListener("input",function(event){var picker=event.target.closest("[data-hpc-color-picker]");var input=event.target.closest("[data-hpc-color-hex-input]");if(!picker&&!input)return;var control=event.target.closest("[data-hpc-color-control]");var h=sync(control,event.target.value,false);var valueInput=storage(control);if(h&&valueInput)valueInput.value=h});document.addEventListener("change",function(event){var picker=event.target.closest("[data-hpc-color-picker]");var input=event.target.closest("[data-hpc-color-hex-input]");if(!picker&&!input)return;var control=event.target.closest("[data-hpc-color-control]");if(storage(control)){commit(control,event.target.value);return}if(picker){var h=sync(control,picker.value,false);var hexInput=control?control.querySelector("[data-hpc-color-hex-input]"):null;if(hexInput&&h)dispatchChange(hexInput);return}sync(control,input.value,false)});document.addEventListener("click",function(event){var inherit=event.target.closest("[data-hpc-color-inherit]");if(inherit){event.preventDefault();var control=inherit.closest("[data-hpc-color-control]");var valueInput=storage(control);var value=inheritedValue(control);if(!control||!valueInput||!value)return;valueInput.value="";sync(control,value,true);dispatchChange(valueInput);return}var brand=event.target.closest("[data-hpc-brand-color-import]");if(!brand)return;var brandControl=brand.closest("[data-hpc-color-control]");var brandValue=hex(brand.getAttribute("data-brand-color"));if(!brandControl||!brandValue)return;var brandInput=storage(brandControl);if(brandInput)brandInput.value=brandValue;sync(brandControl,brandValue,false)});window.hexaColorControl={sync:sync,commit:commit,applyByKey:applyByKey}})();</script>
<script>(function(){if(window.hexaColorPreviewReady)return;window.hexaColorPreviewReady=true;function hex(v){v=String(v||"").trim().toLowerCase();if(v&&v.charAt(0)!=="#")v="#"+v;if(/^#[0-9a-f]{3}$/.test(v))v="#"+v[1]+v[1]+v[2]+v[2]+v[3]+v[3];return /^#[0-9a-f]{6}$/.test(v)?v:""}function rgb(v){v=hex(v);return v?{r:parseInt(v.slice(1,3),16),g:parseInt(v.slice(3,5),16),b:parseInt(v.slice(5,7),16)}:null}function transform(v,rule){var c=rgb(v);rule=String(rule||"color").toLowerCase();if(!c)return"";if(!rule||rule==="color")return hex(v);if(rule==="contrast")return((c.r*299+c.g*587+c.b*114)/1000)>=150?"#111111":"#ffffff";if(rule.indexOf("rgba:")===0){var a=Math.max(0,Math.min(1,parseFloat(rule.slice(5))||0));return"rgba("+c.r+","+c.g+","+c.b+","+a+")"}return""}function map(control){try{return JSON.parse(control.getAttribute("data-hpc-color-preview-variables")||"{}")||{}}catch(e){return{}}}function scope(control){var root=control?control.closest("[data-hpc-color-preview-scope-root]"):null;if(root)return root;var selector=control?control.getAttribute("data-hpc-color-preview-scope"):"";if(selector){try{return document.querySelector(selector)}catch(e){}}return null}function sync(control,color,inherited){var host=scope(control);if(!control||!host)return;Object.keys(map(control)).forEach(function(variable){if(!/^--[a-z0-9_-]+$/.test(variable))return;var value=inherited?"":transform(color,map(control)[variable]);if(value)host.style.setProperty(variable,value);else host.style.removeProperty(variable)})}function init(root){(root||document).querySelectorAll("[data-hpc-color-preview-variables]").forEach(function(control){sync(control,control.getAttribute("data-hpc-color-effective"),control.getAttribute("data-hpc-color-inherited")==="true")})}document.addEventListener("hexa-color-change",function(event){var detail=event.detail||{};sync(detail.control,detail.color,detail.inherited)});document.addEventListener("hexa-core-host-tab-loaded",function(event){init(event.detail&&event.detail.panel?event.detail.panel:document)});window.hexaColorPreview={init:init,sync:sync};if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",function(){init(document)});else init(document)})();</script>
HTML;
    }

    private static function optional_hex( string $value ): string {
        $value = strtolower( trim( $value ) );
        if ( "" === $value ) {
            return "";
        }
        if ( "#" !== substr( $value, 0, 1 ) ) {
            $value = "#" . $value;
        }
        if ( preg_match( "/^#[0-9a-f]{3}$/", $value ) ) {
            $value = "#" . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3];
        }

        return preg_match( "/^#[0-9a-f]{6}$/", $value ) ? $value : "";
    }

    private static function clean_key( string $value ): string {
        if ( function_exists( "sanitize_key" ) ) {
            return sanitize_key( $value );
        }

        return preg_replace( "/[^a-z0-9_\-]/", "", strtolower( $value ) ) ?: "color";
    }

    private static function clean_id( string $value ): string {
        if ( function_exists( "sanitize_html_class" ) ) {
            return sanitize_html_class( $value );
        }

        return preg_replace( "/[^a-zA-Z0-9_\-]/", "-", $value ) ?: "hpc-color";
    }

    private static function preview_variables( $variables ): array {
        if ( ! is_array( $variables ) ) {
            return [];
        }

        $clean = [];
        foreach ( $variables as $variable => $transform ) {
            $variable = trim( (string) $variable );
            $transform = strtolower( trim( (string) $transform ) );
            $valid_transform = in_array( $transform, [ "", "color", "contrast" ], true ) || preg_match( "/^rgba:(?:0(?:\.\d+)?|1(?:\.0+)?)$/", $transform );
            if ( preg_match( "/^--[a-z0-9_-]+$/", $variable ) && $valid_transform ) {
                $clean[ $variable ] = "" === $transform ? "color" : $transform;
            }
        }
        return $clean;
    }

    private static function json( array $value ): string {
        $json = function_exists( "wp_json_encode" ) ? wp_json_encode( $value ) : json_encode( $value );
        return is_string( $json ) ? $json : "{}";
    }
}
