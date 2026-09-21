<?php

namespace Hexa\PluginCore\WpAdminComponents;

use Hexa\PluginCore\Typography\TypographyPreservation;
use Hexa\PluginCore\Typography\TemplateTypography;

final class TypographyControl {
    public static function render( array $args ): string {
        $prefix = sanitize_key( (string) ( $args["prefix"] ?? "" ) );
        if ( "" === $prefix ) {
            return "";
        }

        $settings = isset( $args["settings"] ) && is_array( $args["settings"] ) ? $args["settings"] : [];
        $defaults = $args["defaults"] ?? true;
        $input_class = (string) ( $args["input_class"] ?? "" );
        $title = (string) ( $args["title"] ?? "Typography" );
        $description = (string) ( $args["description"] ?? "Use the original template, the site's typography, or selected custom values." );
        $control_class = trim( "hpc-typography-control " . (string) ( $args["control_class"] ?? "" ) );
        $family = self::config( $args["font_family"] ?? [] );
        $weight = self::config( $args["font_weight"] ?? [] );
        $color = self::config( $args["font_color"] ?? [] );
        $sizes = self::configs( $args["font_size"] ?? [] );
        $styles = self::configs( $args["font_style"] ?? [] );
        if ( [] === $family ) {
            $weight = [];
        } elseif ( [] !== $styles && ( ! array_key_exists( "disable_when_preserved", $family ) || ! empty( $family["disable_when_preserved"] ) ) ) {
            $family_targets = array_key_exists( "targets", $family ) ? (array) $family["targets"] : [ $family["key"] ];
            foreach ( $styles as $style ) {
                $family_targets[] = $style["key"];
            }
            $family["targets"] = array_values( array_unique( array_filter( array_map( "sanitize_key", $family_targets ) ) ) );
        }
        $properties = [];

        if ( [] !== $family ) {
            $properties[] = "font_family";
        }
        if ( [] !== $weight ) {
            $properties[] = "font_weight";
        }
        if ( [] !== $color ) {
            $properties[] = "font_color";
        }
        if ( [] !== $sizes ) {
            $properties[] = "font_size";
        }
        if ( [] === $properties && [] === $styles ) {
            return "";
        }

        $mode_enabled = ! empty( $args["mode_control"] );
        $mode_key = TemplateTypography::setting_key( $prefix );
        $mode = $mode_enabled
            ? TemplateTypography::normalize_mode( (string) ( $settings[ $mode_key ] ?? TemplateTypography::TEMPLATE_DEFAULT ) )
            : TemplateTypography::CUSTOM;
        $preview_variables = isset( $args["preview_variables"] ) && is_array( $args["preview_variables"] ) ? $args["preview_variables"] : [];
        $values = TypographyPreservation::values( $settings, $prefix, $defaults, $properties );
        ob_start();
        CoreUi::render_assets();
        $html = (string) ob_get_clean() . TypographyPreservationControl::assets() . self::assets();
        $html .= '<section class="' . esc_attr( $control_class ) . '" data-hpc-typography-control data-hpc-typography-prefix="' . esc_attr( $prefix ) . '" data-hpc-typography-mode="' . esc_attr( $mode ) . '" data-hpc-typography-preview-variables="' . esc_attr( self::json( $preview_variables ) ) . '">';
        $html .= '<header class="hpc-typography-control-head"><h3>' . esc_html( $title ) . '</h3>';
        if ( "" !== $description ) {
            $html .= '<p>' . esc_html( $description ) . '</p>';
        }
        $html .= '</header>';
        if ( $mode_enabled ) {
            $html .= self::mode_control( $prefix, $mode_key, $mode, $input_class );
        }
        $html .= '<div class="hpc-typography-control-fields" data-hpc-typography-custom-fields' . ( TemplateTypography::CUSTOM === $mode ? '' : ' hidden' ) . '>';

        if ( [] !== $family ) {
            $family["value"] = (string) ( $family["value"] ?? $settings[ $family["key"] ] ?? "template" );
            $family["select_class"] = trim( (string) ( $family["select_class"] ?? $input_class ) );
            $family["family_action_html"] = self::toggle( $prefix, "font_family", $values, $family, $input_class );
            if ( [] !== $weight ) {
                $family["weight_key"] = $weight["key"];
                $family["weight_value"] = (string) ( $weight["value"] ?? $settings[ $weight["key"] ] ?? "inherit" );
                $family["weight_label"] = (string) ( $weight["label"] ?? "Font weight" );
                $family["weight_select_class"] = trim( (string) ( $weight["select_class"] ?? $input_class ) );
                $family["weight_action_html"] = self::toggle( $prefix, "font_weight", $values, $weight, $input_class );
            }
            $html .= '<div class="hpc-typography-control-block hpc-typography-control-font">' . FontFamilyControl::render( $family ) . '</div>';
        }

        if ( [] !== $color ) {
            $color["value"] = (string) ( $color["value"] ?? $settings[ $color["key"] ] ?? $color["default"] ?? "#2d5277" );
            $color["hex_input_class"] = trim( (string) ( $color["hex_input_class"] ?? $input_class ) );
            $color["header_action_html"] = self::toggle( $prefix, "font_color", $values, $color, $input_class );
            $html .= '<div class="hpc-typography-control-block">' . ColorControl::render( $color ) . '</div>';
        }

        if ( [] !== $sizes ) {
            $html .= '<div class="hpc-typography-control-block hpc-typography-control-row"><div class="hpc-typography-number-fields">';
            $size_targets = [];
            foreach ( $sizes as $size ) {
                $key = $size["key"];
                $min = isset( $size["min"] ) ? (int) $size["min"] : 8;
                $max = isset( $size["max"] ) ? (int) $size["max"] : 200;
                $value = isset( $size["value"] ) ? (int) $size["value"] : (int) ( $settings[ $key ] ?? $min );
                $value = max( $min, min( $max, $value ) );
                $size_targets[] = $key;
                $html .= '<div class="hpc-typography-number-control"><div class="hpc-typography-number-head"><h3>' . esc_html( (string) ( $size["label"] ?? "Font size" ) ) . '</h3>';
                if ( ! empty( $size["description"] ) ) {
                    $html .= '<p>' . esc_html( (string) $size["description"] ) . '</p>';
                }
                $html .= '</div><label class="hpc-typography-number-field"><input type="number" min="' . esc_attr( (string) $min ) . '" max="' . esc_attr( (string) $max ) . '" step="' . esc_attr( (string) ( $size["step"] ?? 1 ) ) . '" class="' . esc_attr( trim( "hpc-typography-number-input " . (string) ( $size["input_class"] ?? $input_class ) ) ) . '" data-key="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $value ) . '"><span>' . esc_html( (string) ( $size["suffix"] ?? "" ) ) . '</span></label></div>';
            }
            $size_toggle = $sizes[0];
            $size_toggle["targets"] = $size_targets;
            $html .= '</div><div class="hpc-typography-control-action">' . self::toggle( $prefix, "font_size", $values, $size_toggle, $input_class ) . '</div></div>';
        }

        if ( [] !== $styles ) {
            $html .= '<div class="hpc-typography-control-block hpc-typography-style-fields">';
            foreach ( $styles as $style ) {
                $key = $style["key"];
                $options = self::style_options( $style );
                $value = sanitize_key( (string) ( $style["value"] ?? $settings[ $key ] ?? array_key_first( $options ) ) );
                if ( ! isset( $options[ $value ] ) ) {
                    $value = (string) array_key_first( $options );
                }
                $html .= '<label class="hpc-typography-style-field"><span>' . esc_html( (string) ( $style["label"] ?? "Font style" ) ) . '</span>';
                if ( ! empty( $style["description"] ) ) {
                    $html .= '<small>' . esc_html( (string) $style["description"] ) . '</small>';
                }
                $html .= '<select class="' . esc_attr( trim( "hpc-typography-style-select " . (string) ( $style["select_class"] ?? $input_class ) ) ) . '" data-key="' . esc_attr( $key ) . '" data-hpc-typography-style-select>';
                foreach ( $options as $option_value => $option_label ) {
                    $html .= '<option value="' . esc_attr( (string) $option_value ) . '" data-css="' . esc_attr( (string) $option_value ) . '"' . selected( $value, (string) $option_value, false ) . '>' . esc_html( (string) $option_label ) . '</option>';
                }
                $html .= '</select></label>';
            }
            $html .= '</div>';
        }

        return $html . '</div></section>';
    }

    private static function toggle( string $prefix, string $property, array $values, array $config, string $input_class ): string {
        $targets = array_key_exists( "targets", $config )
            ? (array) $config["targets"]
            : ( ! array_key_exists( "disable_when_preserved", $config ) || ! empty( $config["disable_when_preserved"] ) ? [ $config["key"] ] : [] );

        return TypographyPreservationControl::render_toggle(
            [
                "prefix" => $prefix,
                "property" => $property,
                "checked" => ! empty( $values[ $property ] ),
                "label" => (string) ( $config["preserve_label"] ?? self::preserve_label( $property ) ),
                "targets" => $targets,
                "input_class" => $input_class,
                "class" => "hpc-typography-adjacent-toggle",
            ]
        );
    }

    private static function preserve_label( string $property ): string {
        return [
            "font_family" => "Use site font",
            "font_size" => "Use site text size",
            "font_color" => "Use site text color",
            "font_weight" => "Use site font weight",
        ][ $property ] ?? "Use site value";
    }

    private static function mode_control( string $prefix, string $mode_key, string $mode, string $input_class ): string {
        $html = '<div class="hpc-typography-mode-options" role="radiogroup" aria-label="Typography source">';
        foreach ( TemplateTypography::options() as $value => $option ) {
            $id = 'hpc-typography-mode-' . $prefix . '-' . $value;
            $html .= '<label class="hpc-typography-mode-option" for="' . esc_attr( $id ) . '">'
                . '<input id="' . esc_attr( $id ) . '" class="' . esc_attr( trim( 'hpc-typography-mode-input ' . $input_class ) ) . '" type="radio" name="' . esc_attr( 'hpc_' . $mode_key ) . '" value="' . esc_attr( $value ) . '" data-key="' . esc_attr( $mode_key ) . '" data-hpc-typography-mode-setting' . checked( $mode, $value, false ) . '>'
                . '<span><strong>' . esc_html( (string) $option['label'] ) . '</strong><small>' . esc_html( (string) $option['description'] ) . '</small></span>'
                . '</label>';
        }

        return $html . '</div>';
    }

    private static function config( $config ): array {
        return is_array( $config ) && isset( $config["key"] ) && "" !== sanitize_key( (string) $config["key"] )
            ? array_merge( $config, [ "key" => sanitize_key( (string) $config["key"] ) ] )
            : [];
    }

    private static function configs( $configs ): array {
        if ( ! is_array( $configs ) || [] === $configs ) {
            return [];
        }
        if ( isset( $configs["key"] ) ) {
            $configs = [ $configs ];
        }
        return array_values( array_filter( array_map( [ self::class, "config" ], $configs ) ) );
    }

    private static function style_options( array $config ): array {
        $options = isset( $config["options"] ) && is_array( $config["options"] ) ? $config["options"] : [ "normal" => "Normal", "italic" => "Italic" ];
        $clean = [];
        foreach ( $options as $value => $label ) {
            $value = sanitize_key( (string) $value );
            if ( in_array( $value, [ "normal", "italic", "oblique" ], true ) ) {
                $clean[ $value ] = (string) $label;
            }
        }
        return [] !== $clean ? $clean : [ "normal" => "Normal", "italic" => "Italic" ];
    }

    private static function assets(): string {
        static $rendered = false;
        if ( $rendered ) {
            return "";
        }
        $rendered = true;

        return <<<'HTML'
<style>.hpc-typography-control{border:1px solid #d8dee8;border-radius:6px;display:grid;gap:0;overflow:hidden}.hpc-typography-control-head{padding:14px 16px}.hpc-typography-control-head h3{font-size:15px;letter-spacing:0;margin:0 0 4px}.hpc-typography-control-head p{color:#64748b;margin:0}.hpc-typography-mode-options{border-top:1px solid #e4e9f0;display:grid;gap:8px;grid-template-columns:repeat(3,minmax(0,1fr));padding:14px 16px}.hpc-typography-mode-option{align-items:flex-start;background:#fff;border:1px solid #d8dee8;border-radius:6px;cursor:pointer;display:flex;gap:9px;padding:11px 12px}.hpc-typography-mode-option:has(input:checked){background:#f1f5fb;border-color:#3157d5;box-shadow:inset 0 0 0 1px #3157d5}.hpc-typography-mode-option input{margin-top:2px}.hpc-typography-mode-option strong,.hpc-typography-mode-option small{display:block}.hpc-typography-mode-option small{color:#64748b;line-height:1.35;margin-top:2px}.hpc-typography-control-fields[hidden]{display:none}.hpc-typography-control-block{border-top:1px solid #e4e9f0;padding:16px}.hpc-typography-control-row{align-items:start;display:grid;gap:12px}.hpc-typography-control-main{min-width:0}.hpc-typography-control-action{align-items:center;display:flex;grid-row:1;justify-content:flex-start;min-height:0;white-space:normal}.hpc-typography-number-fields{display:flex;flex-wrap:wrap;gap:18px;grid-row:2}.hpc-typography-style-fields{display:flex;flex-wrap:wrap;gap:18px}.hpc-typography-number-control{display:grid;gap:9px}.hpc-typography-number-head h3{font-size:13px;letter-spacing:0;margin:0 0 4px;text-transform:uppercase}.hpc-typography-number-head p{color:#64748b;margin:0}.hpc-typography-number-field{align-items:center;display:flex;gap:8px}.hpc-typography-number-input{background:#fff;border:1px solid #a9b4c3;border-radius:6px;min-height:40px;padding:8px 10px;width:110px}.hpc-typography-style-field{display:grid;gap:5px;min-width:220px}.hpc-typography-style-field>span{color:#475569;font-size:11px;font-weight:800;text-transform:uppercase}.hpc-typography-style-field>small{color:#64748b}.hpc-typography-style-select{background:#fff;border:1px solid #a9b4c3;border-radius:6px;min-height:40px;padding:7px 34px 7px 10px;width:100%}.hpc-typography-adjacent-toggle{margin:0}.hpc-typography-control .hpc-color-head{align-items:start;display:grid;grid-template-columns:minmax(0,1fr);justify-items:start}.hpc-typography-control .hpc-font-family-field-set{align-items:start;grid-template-columns:minmax(0,1fr);min-width:min(100%,510px)}.hpc-typography-control .hpc-font-family-field-action{grid-row:1;justify-content:flex-start;min-height:0}.hpc-typography-control .hpc-font-family-field-set .hpc-font-family-field{grid-row:2}@media(max-width:900px){.hpc-typography-mode-options{grid-template-columns:1fr}}</style>
<script>(function(){if(window.hexaTemplateTypographyReady)return;window.hexaTemplateTypographyReady=true;function sync(control){if(!control)return;var input=control.querySelector("[data-hpc-typography-mode-setting]:checked"),mode=input?input.value:"custom",fields=control.querySelector("[data-hpc-typography-custom-fields]");control.setAttribute("data-hpc-typography-mode",mode);if(fields)fields.hidden=mode!=="custom";if(window.hexaPluginCoreInitTypographyPreservation)window.hexaPluginCoreInitTypographyPreservation(control);document.dispatchEvent(new CustomEvent("hexa-template-typography-change",{detail:{control:control,prefix:control.getAttribute("data-hpc-typography-prefix")||"",mode:mode,scope:control.closest("[data-hpc-typography-scope]")||control.parentElement}}))}function init(root){(root||document).querySelectorAll("[data-hpc-typography-control]").forEach(sync)}document.addEventListener("change",function(event){var input=event.target.closest("[data-hpc-typography-mode-setting]");if(input)sync(input.closest("[data-hpc-typography-control]"))});document.addEventListener("hexa-core-host-tab-loaded",function(event){init(event.detail&&event.detail.panel?event.detail.panel:document)});window.hexaTemplateTypography={init:init,sync:sync};if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",function(){init(document)});else init(document)})();</script>
<script>(function(){if(window.hexaTemplateTypographyPreviewReady)return;window.hexaTemplateTypographyPreviewReady=true;function json(control){try{return JSON.parse(control.getAttribute("data-hpc-typography-preview-variables")||"{}")||{}}catch(e){return{}}}function clean(value){return String(value||"").replace(/"/g,"")}function scope(control){return control?control.closest("[data-hpc-typography-scope]")||control.parentElement:null}function mode(control){var input=control?control.querySelector("[data-hpc-typography-mode-setting]:checked"):null;return input?input.value:"custom"}function preserved(control,property){if(mode(control)!=="custom")return true;var input=control.querySelector('[data-hpc-typography-preserve-setting][data-hpc-typography-property="'+clean(property)+'"]');return !!(input&&input.checked)}function value(control,key,config){var selector='input[data-key="'+clean(key)+'"],select[data-key="'+clean(key)+'"],textarea[data-key="'+clean(key)+'"]',input=Array.from(control.querySelectorAll(selector)).find(function(node){return!node.matches("[data-hpc-typography-mode-setting],[data-hpc-typography-preserve-setting],[data-hpc-color-picker]")});if(!input)return"";if(input.options){var option=input.options[input.selectedIndex];return option?String(option.getAttribute("data-css")||"").trim():""}var raw=String(input.value||"").trim(),type=String(config.type||"");if(type==="color"){if(raw&&raw.charAt(0)!=="#")raw="#"+raw;return /^#[0-9a-fA-F]{6}$/.test(raw)?raw.toLowerCase():""}if(type==="size")return /^-?[0-9]+(?:\.[0-9]+)?$/.test(raw)?raw+String(config.suffix||"px"):"";if(type==="style")return /^(normal|italic|oblique)$/.test(raw)?raw:"normal";return raw}function sync(control){if(!control)return;var host=scope(control),map=json(control),current=mode(control);if(!host)return;Object.keys(map).forEach(function(key){var config=map[key]||{},variable=String(config.variable||"");if(!/^--[a-z0-9_-]+$/.test(variable))return;var resolved=current==="custom"&&!preserved(control,config.preserve_property||config.property)?value(control,key,config):"";if(resolved)host.style.setProperty(variable,resolved);else host.style.removeProperty(variable)})}function init(root){(root||document).querySelectorAll("[data-hpc-typography-preview-variables]").forEach(sync)}document.addEventListener("hexa-template-typography-change",function(event){sync(event.detail&&event.detail.control)});document.addEventListener("hexa-typography-preserve-change",function(event){sync(event.detail&&event.detail.control)});document.addEventListener("hexa-color-change",function(event){var colorControl=event.detail&&event.detail.control,control=colorControl?colorControl.closest("[data-hpc-typography-preview-variables]"):null;if(control)sync(control)});document.addEventListener("input",function(event){var control=event.target.closest("[data-hpc-typography-preview-variables]");if(control)sync(control)});document.addEventListener("change",function(event){var control=event.target.closest("[data-hpc-typography-preview-variables]");if(control)sync(control)});document.addEventListener("hexa-core-host-tab-loaded",function(event){init(event.detail&&event.detail.panel?event.detail.panel:document)});window.hexaTemplateTypographyPreview={init:init,sync:sync};if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",function(){init(document)});else init(document)})();</script>
<script>(function(){if(window.hexaTemplateTypographyPreviewStateReady)return;window.hexaTemplateTypographyPreviewStateReady=true;function clean(value){return String(value||"").toLowerCase().replace(/_/g,"-").replace(/[^a-z0-9-]/g,"")}function map(control){try{return JSON.parse(control.getAttribute("data-hpc-typography-preview-variables")||"{}")||{}}catch(e){return{}}}function sync(control){if(!control)return;var host=control.closest("[data-hpc-typography-scope]")||control.parentElement,prefix=clean(control.getAttribute("data-hpc-typography-prefix")),active={},properties=["font-family","font-weight","font-color","font-size","font-style"];if(!host||!prefix)return;Object.keys(map(control)).forEach(function(key){var config=map(control)[key]||{},property=clean(config.property),variable=String(config.variable||"");if(property&&variable&&host.style.getPropertyValue(variable).trim())active[property]=true});properties.forEach(function(property){host.classList.toggle("hpc-typography-"+prefix+"-custom-"+property,!!active[property])})}function init(root){(root||document).querySelectorAll("[data-hpc-typography-preview-variables]").forEach(sync)}document.addEventListener("hexa-template-typography-change",function(event){setTimeout(function(){sync(event.detail&&event.detail.control)},0)});document.addEventListener("hexa-typography-preserve-change",function(event){setTimeout(function(){sync(event.detail&&event.detail.control)},0)});document.addEventListener("hexa-color-change",function(event){var colorControl=event.detail&&event.detail.control,control=colorControl?colorControl.closest("[data-hpc-typography-preview-variables]"):null;if(control)setTimeout(function(){sync(control)},0)});document.addEventListener("input",function(event){var control=event.target.closest("[data-hpc-typography-preview-variables]");if(control)setTimeout(function(){sync(control)},0)});document.addEventListener("change",function(event){var control=event.target.closest("[data-hpc-typography-preview-variables]");if(control)setTimeout(function(){sync(control)},0)});document.addEventListener("hexa-core-host-tab-loaded",function(event){setTimeout(function(){init(event.detail&&event.detail.panel?event.detail.panel:document)},0)});if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",function(){setTimeout(function(){init(document)},0)});else setTimeout(function(){init(document)},0)})();</script>
HTML;
    }

    private static function json( array $value ): string {
        $json = function_exists( "wp_json_encode" ) ? wp_json_encode( $value ) : json_encode( $value );
        return is_string( $json ) ? $json : "{}";
    }
}
