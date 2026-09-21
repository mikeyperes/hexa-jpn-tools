<?php

namespace Hexa\PluginCore\WpAdminComponents;

use Hexa\PluginCore\BrandColors\BrandColorProvider;
use Hexa\PluginCore\BrandColors\TemplateColorResolver;

/**
 * Shared source selector and custom picker for template-owned accent colors.
 */
final class TemplateColorControl {
    public static function render( array $args ): string {
        $source_key = self::clean_key( (string) ( $args["source_key"] ?? "" ) );
        $custom_key = self::clean_key( (string) ( $args["custom_key"] ?? "" ) );
        if ( "" === $source_key || "" === $custom_key ) {
            return "";
        }

        $template_key = self::clean_key( (string) ( $args["template_key"] ?? "" ) );
        $template = self::clean_key( (string) ( $args["template"] ?? "" ) );
        $source = TemplateColorResolver::normalize_source( (string) ( $args["source"] ?? TemplateColorResolver::TEMPLATE_DEFAULT ) );
        $palettes = isset( $args["palettes"] ) && is_array( $args["palettes"] ) ? $args["palettes"] : [];
        $variables = isset( $args["variables"] ) && is_array( $args["variables"] ) ? $args["variables"] : [];
        $fallback = BrandColorProvider::normalize_hex( (string) ( $args["fallback"] ?? "#2d5277" ), "#2d5277" );
        $primary = BrandColorProvider::normalize_hex( (string) ( $args["primary"] ?? BrandColorProvider::primary_color( $fallback ) ), $fallback );
        $secondary = BrandColorProvider::normalize_hex( (string) ( $args["secondary"] ?? BrandColorProvider::secondary_color( "#111827" ) ), "#111827" );
        $custom = self::optional_hex( (string) ( $args["custom"] ?? "" ) );
        $native_palette = TemplateColorResolver::template_palette( $template, $palettes, $fallback );
        $native = isset( $native_palette["accent"] ) ? (string) $native_palette["accent"] : (string) reset( $native_palette );
        $custom_display = "" !== $custom ? $custom : $native;
        $input_class = trim( (string) ( $args["input_class"] ?? "" ) );
        $title = (string) ( $args["title"] ?? "Template color" );
        $description = (string) ( $args["description"] ?? "Change only the selected design's mapped colors. Layout and typography stay unchanged." );
        $control_class = trim( "hpc-template-color-control " . (string) ( $args["control_class"] ?? "" ) );
        $preview_scope = trim( (string) ( $args["preview_scope"] ?? "" ) );
        $status_html = (string) ( $args["status_html"] ?? "" );
        $options = TemplateColorResolver::source_options();
        $swatches = [
            TemplateColorResolver::TEMPLATE_DEFAULT => $native,
            TemplateColorResolver::SITE_PRIMARY => $primary,
            TemplateColorResolver::SITE_SECONDARY => $secondary,
            TemplateColorResolver::CUSTOM => $custom_display,
        ];

        ob_start();
        CoreUi::render_assets();
        echo self::assets_once(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
        <section
            class="<?php echo esc_attr( $control_class ); ?>"
            data-hpc-template-color-control
            data-hpc-template-color-source="<?php echo esc_attr( $source ); ?>"
            data-hpc-template-color-source-key="<?php echo esc_attr( $source_key ); ?>"
            data-hpc-template-color-custom-key="<?php echo esc_attr( $custom_key ); ?>"
            data-hpc-template-color-template-key="<?php echo esc_attr( $template_key ); ?>"
            data-hpc-template-color-template="<?php echo esc_attr( $template ); ?>"
            data-hpc-template-color-primary="<?php echo esc_attr( $primary ); ?>"
            data-hpc-template-color-secondary="<?php echo esc_attr( $secondary ); ?>"
            data-hpc-template-color-fallback="<?php echo esc_attr( $fallback ); ?>"
            data-hpc-template-color-palettes="<?php echo esc_attr( self::json( $palettes ) ); ?>"
            data-hpc-template-color-variables="<?php echo esc_attr( self::json( $variables ) ); ?>"
            data-hpc-template-color-preview-scope="<?php echo esc_attr( $preview_scope ); ?>"
        >
            <header class="hpc-template-color-head">
                <div>
                    <h3><?php echo esc_html( $title ); ?></h3>
                    <?php if ( "" !== $description ) : ?>
                        <p><?php echo esc_html( $description ); ?></p>
                    <?php endif; ?>
                </div>
                <button type="button" class="hpc-button secondary hpc-template-color-reset" data-hpc-template-color-reset>Reset</button>
            </header>
            <div class="hpc-template-color-options" role="radiogroup" aria-label="<?php echo esc_attr( $title ); ?>">
                <?php foreach ( $options as $value => $option ) : ?>
                    <?php $id = "hpc-template-color-" . $source_key . "-" . $value; ?>
                    <label class="hpc-template-color-option" for="<?php echo esc_attr( $id ); ?>">
                        <input
                            id="<?php echo esc_attr( $id ); ?>"
                            class="<?php echo esc_attr( trim( "hpc-template-color-source-input " . $input_class ) ); ?>"
                            type="radio"
                            name="<?php echo esc_attr( "hpc_" . $source_key ); ?>"
                            value="<?php echo esc_attr( $value ); ?>"
                            data-key="<?php echo esc_attr( $source_key ); ?>"
                            data-hpc-template-color-source-input
                            <?php checked( $source, $value ); ?>
                        >
                        <span class="hpc-template-color-option-swatch" data-hpc-template-color-option-swatch="<?php echo esc_attr( $value ); ?>" style="background:<?php echo esc_attr( $swatches[ $value ] ); ?>"></span>
                        <span class="hpc-template-color-option-copy">
                            <strong><?php echo esc_html( (string) $option["label"] ); ?></strong>
                            <small><?php echo esc_html( (string) $option["description"] ); ?></small>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="hpc-template-color-custom" data-hpc-template-color-custom <?php echo TemplateColorResolver::CUSTOM === $source ? "" : "hidden"; ?>>
                <?php
                echo ColorControl::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    [
                        "key" => $custom_key,
                        "label" => "Custom design color",
                        "value" => $custom,
                        "default" => $native,
                        "allow_inherit" => true,
                        "inherited_value" => $native,
                        "show_inherit_action" => false,
                        "control_class" => "hpc-template-color-custom-picker",
                        "hex_input_class" => "hpc-template-color-custom-input",
                        "value_input_class" => trim( "hpc-template-color-custom-value " . $input_class ),
                        "picker_class" => "hpc-template-color-custom-picker-input",
                        "show_copy" => true,
                    ]
                );
                ?>
            </div>
            <?php echo $status_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </section>
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
<style>.hpc-template-color-control{border:1px solid #d8dee8;border-radius:6px;display:grid;gap:14px;padding:16px}.hpc-template-color-head{align-items:flex-start;display:flex;gap:16px;justify-content:space-between}.hpc-template-color-head h3{font-size:15px;letter-spacing:0;margin:0 0 4px}.hpc-template-color-head p{color:#64748b;margin:0}.hpc-template-color-options{display:grid;gap:8px;grid-template-columns:repeat(2,minmax(0,1fr))}.hpc-template-color-option{align-items:center;background:#fff;border:1px solid #d8dee8;border-radius:6px;cursor:pointer;display:grid;gap:10px;grid-template-columns:auto auto minmax(0,1fr);padding:11px 12px}.hpc-template-color-option:has(input:checked){background:#f1f5fb;border-color:#3157d5;box-shadow:inset 0 0 0 1px #3157d5}.hpc-template-color-option input{margin:0}.hpc-template-color-option-swatch{border:1px solid rgba(15,23,42,.18);border-radius:4px;height:28px;width:28px}.hpc-template-color-option-copy{min-width:0}.hpc-template-color-option-copy strong,.hpc-template-color-option-copy small{display:block}.hpc-template-color-option-copy small{color:#64748b;line-height:1.35;margin-top:2px}.hpc-template-color-custom[hidden]{display:none}.hpc-template-color-custom{border-top:1px solid #e4e9f0;padding-top:14px}.hpc-template-color-reset{flex:0 0 auto}@media(max-width:780px){.hpc-template-color-options{grid-template-columns:1fr}.hpc-template-color-head{align-items:stretch;flex-direction:column}.hpc-template-color-reset{align-self:flex-start}}</style>
<script>
(function(){
    if(window.hexaTemplateColorControlReady)return;
    window.hexaTemplateColorControlReady=true;
    var DEFAULT="template_default",PRIMARY="site_primary",SECONDARY="site_secondary",CUSTOM="custom";
    function hex(value){value=String(value||"").trim().toLowerCase();if(value&&value.charAt(0)!=="#")value="#"+value;if(/^#[0-9a-f]{3}$/.test(value))value="#"+value[1]+value[1]+value[2]+value[2]+value[3]+value[3];return /^#[0-9a-f]{6}$/.test(value)?value:""}
    function json(control,name){try{return JSON.parse(control.getAttribute(name)||"{}")||{}}catch(e){return{}}}
    function source(control){var checked=control.querySelector("[data-hpc-template-color-source-input]:checked");return checked?checked.value:DEFAULT}
    function nestedColorControl(control){return control?control.querySelector("[data-hpc-template-color-custom] [data-hpc-color-control]"):null}
    function explicitCustom(control){var nested=nestedColorControl(control),storage=nested?nested.querySelector("[data-hpc-color-value-input]"):null,input=nested?nested.querySelector("[data-hpc-color-hex-input]"):null;return hex(storage?storage.value:(input?input.value:""))}
    function native(control){var palettes=json(control,"data-hpc-template-color-palettes"),template=control.getAttribute("data-hpc-template-color-template")||"",palette=palettes[template]||palettes["*"]||{},keys=Object.keys(palette),value=palette.accent||(keys.length?palette[keys[0]]:control.getAttribute("data-hpc-template-color-fallback"));return hex(value)||"#2d5277"}
    function custom(control){return explicitCustom(control)||native(control)}
    function syncCustomDisplay(control){var nested=nestedColorControl(control),value=explicitCustom(control),fallback=native(control);if(!nested)return;nested.setAttribute("data-hpc-inherited-value",fallback);if(window.hexaColorControl&&window.hexaColorControl.sync)window.hexaColorControl.sync(nested,value||fallback,!value)}
    function base(control,mode){if(mode===PRIMARY)return hex(control.getAttribute("data-hpc-template-color-primary"))||"#2d5277";if(mode===SECONDARY)return hex(control.getAttribute("data-hpc-template-color-secondary"))||"#111827";if(mode===CUSTOM)return custom(control);return native(control)}
    function rgb(color){color=hex(color)||"#2d5277";return{r:parseInt(color.slice(1,3),16),g:parseInt(color.slice(3,5),16),b:parseInt(color.slice(5,7),16)}}
    function transform(color,rule){rule=String(rule||"color").toLowerCase();if(!rule||rule==="color")return color;if(rule==="contrast"){var c=rgb(color),l=(c.r*299+c.g*587+c.b*114)/1000;return l>=150?"#111111":"#ffffff"}if(rule.indexOf("rgba:")===0){var a=Math.max(0,Math.min(1,parseFloat(rule.slice(5))||0)),v=rgb(color);return "rgba("+v.r+","+v.g+","+v.b+","+a+")"}return ""}
    function scope(control){var closest=control.closest("[data-hpc-template-color-scope]");if(closest)return closest;var selector=control.getAttribute("data-hpc-template-color-preview-scope");if(selector){try{return document.querySelector(selector)}catch(e){}}return null}
    function updateSwatches(control){var nativeSwatch=control.querySelector('[data-hpc-template-color-option-swatch="'+DEFAULT+'"]'),customSwatch=control.querySelector('[data-hpc-template-color-option-swatch="'+CUSTOM+'"]');if(nativeSwatch)nativeSwatch.style.background=native(control);if(customSwatch)customSwatch.style.background=custom(control)}
    function apply(control,mode,color,template){if(!control)return{};if(template!==undefined&&template!==null)control.setAttribute("data-hpc-template-color-template",String(template));mode=mode||source(control);var explicit=hex(color);if(!explicit)syncCustomDisplay(control);color=explicit||base(control,mode);var variables=json(control,"data-hpc-template-color-variables"),host=scope(control),resolved={};Object.keys(variables).forEach(function(variable){if(!/^--[a-z0-9_-]+$/.test(variable))return;var value=mode===DEFAULT?"":transform(color,variables[variable]);if(host){if(value)host.style.setProperty(variable,value);else host.style.removeProperty(variable)}if(value)resolved[variable]=value});control.setAttribute("data-hpc-template-color-source",mode);var customPanel=control.querySelector("[data-hpc-template-color-custom]");if(customPanel)customPanel.hidden=mode!==CUSTOM;updateSwatches(control);document.dispatchEvent(new CustomEvent("hexa-template-color-change",{detail:{control:control,source:mode,color:color,template:control.getAttribute("data-hpc-template-color-template")||"",variables:resolved,scope:host}}));return resolved}
    function init(root){(root||document).querySelectorAll("[data-hpc-template-color-control]").forEach(function(control){apply(control)})}
    function applyByKey(key,mode,color){document.querySelectorAll('[data-hpc-template-color-source-key="'+String(key).replace(/"/g,"")+'"]') .forEach(function(control){var input=control.querySelector('[data-hpc-template-color-source-input][value="'+mode+'"]');if(input)input.checked=true;apply(control,mode,color)})}
    document.addEventListener("change",function(event){var sourceInput=event.target.closest("[data-hpc-template-color-source-input]");if(sourceInput){apply(sourceInput.closest("[data-hpc-template-color-control]"),sourceInput.value);return}var control=event.target.closest("[data-hpc-template-color-control]");if(control&&event.target.closest("[data-hpc-color-picker],[data-hpc-color-hex-input]")){var customInput=control.querySelector('[data-hpc-template-color-source-input][value="'+CUSTOM+'"]'),changed=customInput&&!customInput.checked;if(customInput)customInput.checked=true;apply(control,CUSTOM,event.target.value);if(changed)customInput.dispatchEvent(new Event("change",{bubbles:true}));return}var key=event.target&&event.target.getAttribute?event.target.getAttribute("data-key"):"";if(!key)return;document.querySelectorAll('[data-hpc-template-color-template-key="'+key.replace(/"/g,"")+'"]') .forEach(function(item){item.setAttribute("data-hpc-template-color-template",event.target.value);apply(item)})});
    document.addEventListener("input",function(event){var control=event.target.closest("[data-hpc-template-color-control]");if(!control||!event.target.closest("[data-hpc-color-picker],[data-hpc-color-hex-input]"))return;var customInput=control.querySelector('[data-hpc-template-color-source-input][value="'+CUSTOM+'"]');if(customInput)customInput.checked=true;apply(control,CUSTOM,event.target.value)});
    document.addEventListener("click",function(event){var reset=event.target.closest("[data-hpc-template-color-reset]");if(!reset)return;event.preventDefault();var control=reset.closest("[data-hpc-template-color-control]"),input=control?control.querySelector('[data-hpc-template-color-source-input][value="'+DEFAULT+'"]'):null,nested=nestedColorControl(control),storage=nested?nested.querySelector("[data-hpc-color-value-input]"):null;if(!control||!input)return;if(storage){storage.value="";storage.dispatchEvent(new Event("change",{bubbles:true}))}input.checked=true;syncCustomDisplay(control);apply(control,DEFAULT);input.dispatchEvent(new Event("change",{bubbles:true}))});
    document.addEventListener("hexa-core-host-tab-loaded",function(event){init(event.detail&&event.detail.panel?event.detail.panel:document)});
    window.hexaTemplateColorControl={init:init,apply:apply,applyByKey:applyByKey};
    if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",function(){init(document)});else init(document);
})();
</script>
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
        return preg_replace( "/[^a-z0-9_\-]/", "", strtolower( $value ) ) ?: "";
    }

    private static function json( array $value ): string {
        $json = function_exists( "wp_json_encode" ) ? wp_json_encode( $value ) : json_encode( $value );
        return is_string( $json ) ? $json : "{}";
    }
}
