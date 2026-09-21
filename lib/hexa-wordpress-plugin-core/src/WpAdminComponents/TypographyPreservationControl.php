<?php

namespace Hexa\PluginCore\WpAdminComponents;

use Hexa\PluginCore\Typography\TypographyPreservation;

final class TypographyPreservationControl {
    public static function render( array $args ): string {
        $prefix = sanitize_key( (string) ( $args["prefix"] ?? "" ) );
        if ( "" === $prefix ) {
            return "";
        }

        $properties = isset( $args["properties"] ) && is_array( $args["properties"] ) ? $args["properties"] : TypographyPreservation::PROPERTIES;
        $settings = isset( $args["settings"] ) && is_array( $args["settings"] ) ? $args["settings"] : [];
        $defaults = $args["defaults"] ?? true;
        $values = TypographyPreservation::values( $settings, $prefix, $defaults, $properties );
        $targets = isset( $args["targets"] ) && is_array( $args["targets"] ) ? $args["targets"] : [];
        $labels = array_merge(
            [
                "font_family" => "Use site font",
                "font_size" => "Use site text size",
                "font_color" => "Use site text color",
                "font_weight" => "Use site font weight",
            ],
            isset( $args["labels"] ) && is_array( $args["labels"] ) ? $args["labels"] : []
        );
        $title = (string) ( $args["title"] ?? "Site typography" );
        $description = (string) ( $args["description"] ?? "Turn on a site value to inherit it instead of applying the custom value." );
        $input_class = trim( "hpc-typography-preserve-setting " . (string) ( $args["input_class"] ?? "" ) );
        $control_class = trim( "hpc-typography-preservation-control " . (string) ( $args["control_class"] ?? "" ) );

        $html = self::assets();
        $html .= '<section class="' . esc_attr( $control_class ) . '" data-hpc-typography-control data-hpc-typography-prefix="' . esc_attr( $prefix ) . '">';
        $html .= '<header class="hpc-typography-preservation-head"><h3>' . esc_html( $title ) . '</h3>';
        if ( "" !== $description ) {
            $html .= '<p>' . esc_html( $description ) . '</p>';
        }
        $html .= '</header><div class="hpc-typography-preservation-toggles">';

        foreach ( $values as $property => $preserve ) {
            $html .= self::render_toggle(
                [
                    "prefix" => $prefix,
                    "property" => $property,
                    "checked" => (bool) $preserve,
                    "label" => (string) ( $labels[ $property ] ?? $property ),
                    "targets" => isset( $targets[ $property ] ) ? (array) $targets[ $property ] : [],
                    "input_class" => $input_class,
                ]
            );
        }

        return $html . '</div></section>';
    }

    public static function render_toggle( array $args ): string {
        $prefix = sanitize_key( (string) ( $args["prefix"] ?? "" ) );
        $property = sanitize_key( (string) ( $args["property"] ?? "" ) );
        if ( "" === $prefix || ! in_array( $property, TypographyPreservation::PROPERTIES, true ) ) {
            return "";
        }

        $labels = [
            "font_family" => "Use site font",
            "font_size" => "Use site text size",
            "font_color" => "Use site text color",
            "font_weight" => "Use site font weight",
        ];
        $setting_key = TypographyPreservation::setting_key( $prefix, $property );
        $target_keys = array_values( array_filter( array_map( 'sanitize_key', (array) ( $args["targets"] ?? [] ) ) ) );
        $input_class = trim( "hpc-typography-preserve-setting " . (string) ( $args["input_class"] ?? "" ) );

        return CoreUi::toggle(
            $setting_key,
            ! empty( $args["checked"] ),
            (string) ( $args["label"] ?? $labels[ $property ] ),
            [
                "id" => "hpc-typography-" . $prefix . "-" . $property,
                "class" => trim( "hpc-typography-preservation-toggle " . (string) ( $args["class"] ?? "" ) ),
                "input_class" => $input_class,
                "data" => [
                    "key" => $setting_key,
                    "hpc_typography_preserve_setting" => "1",
                    "hpc_typography_property" => $property,
                    "hpc_typography_targets" => implode( ",", $target_keys ),
                ],
            ]
        );
    }

    public static function assets(): string {
        static $rendered = false;
        if ( $rendered ) {
            return "";
        }
        $rendered = true;
        return <<<'HTML'
<style>.hpc-typography-preservation-control{border:1px solid #d8dee8;border-radius:6px;display:grid;gap:12px;padding:14px}.hpc-typography-preservation-head h3{font-size:14px;letter-spacing:0;margin:0 0 4px}.hpc-typography-preservation-head p{color:#64748b;margin:0}.hpc-typography-preservation-toggles{display:grid;gap:9px;grid-template-columns:repeat(auto-fit,minmax(210px,1fr))}.hpc-typography-preservation-toggle{margin:0}.hpc-typography-value-inherited{filter:grayscale(1);opacity:.45;pointer-events:none}</style>
<script>(function(){if(window.hexaTypographyPreservationReady)return;window.hexaTypographyPreservationReady=true;function clean(value){return String(value||"").toLowerCase().replace(/_/g,"-").replace(/[^a-z0-9-]/g,"")}function formControls(scope,key){if(!scope||!key)return[];key=key.replace(/"/g,"");var nodes=Array.from(scope.querySelectorAll('[data-key="'+key+'"]')).filter(function(node){return node.matches("input,select,textarea")});scope.querySelectorAll('[data-hpc-color-control][data-key="'+key+'"]').forEach(function(colorControl){nodes=nodes.concat(Array.from(colorControl.querySelectorAll("[data-hpc-color-picker],[data-hpc-color-hex-input],[data-hpc-color-value-input],[data-hpc-brand-color-import],[data-hpc-color-inherit]")))});return nodes.filter(function(node,index){return!node.matches("[data-hpc-typography-preserve-setting],[data-hpc-typography-mode-setting]")&&nodes.indexOf(node)===index})}function valueRegion(target){if(!target||!target.closest)return null;return target.closest(".hpc-color-row,.hpc-font-family-field,.hpc-typography-number-control,.hpc-typography-style-field")}function setTargetState(target,disabled){if(!target)return;target.disabled=disabled;target.setAttribute("aria-disabled",disabled?"true":"false");var region=valueRegion(target);if(region){region.classList.toggle("hpc-typography-value-inherited",disabled);region.setAttribute("aria-disabled",disabled?"true":"false")}}function mode(control){var input=control?control.querySelector("[data-hpc-typography-mode-setting]:checked"):null;return input?input.value:"custom"}function sync(control){if(!control)return;var prefix=clean(control.getAttribute("data-hpc-typography-prefix")),scope=control.closest("[data-hpc-typography-scope]")||control.parentElement,currentMode=mode(control),modeClass="hpc-typography-"+prefix+"-mode-"+clean(currentMode);if(scope){["template-default","site-inherit","custom"].forEach(function(item){scope.classList.remove("hpc-typography-"+prefix+"-mode-"+item)});scope.classList.add(modeClass)}control.querySelectorAll("[data-hpc-typography-preserve-setting]").forEach(function(input){var property=clean(input.getAttribute("data-hpc-typography-property")),preserve=currentMode==="site_inherit"?true:(currentMode==="template_default"?false:!!input.checked),className="hpc-typography-"+prefix+"-preserve-"+property;if(scope)scope.classList.toggle(className,preserve);input.disabled=currentMode!=="custom";input.setAttribute("aria-disabled",currentMode!=="custom"?"true":"false");String(input.getAttribute("data-hpc-typography-targets")||"").split(",").filter(Boolean).forEach(function(key){formControls(scope,key).forEach(function(target){if(target!==input)setTargetState(target,currentMode!=="custom"||preserve)})});document.dispatchEvent(new CustomEvent("hexa-typography-preserve-change",{detail:{control:control,prefix:prefix,property:property,preserve:preserve,mode:currentMode,scope:scope}}))})}function init(root){var controls=[];if(root&&root.matches&&root.matches("[data-hpc-typography-control]"))controls.push(root);if(root&&root.querySelectorAll)controls=controls.concat(Array.from(root.querySelectorAll("[data-hpc-typography-control]")));controls.forEach(sync)}document.addEventListener("change",function(event){var input=event.target.closest("[data-hpc-typography-preserve-setting],[data-hpc-typography-mode-setting]");if(input)sync(input.closest("[data-hpc-typography-control]"))});document.addEventListener("hexa-core-host-tab-loaded",function(event){init(event.detail&&event.detail.panel?event.detail.panel:document)});window.hexaPluginCoreInitTypographyPreservation=init;if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",function(){init(document)});else init(document)})();</script>
HTML;
    }
}
