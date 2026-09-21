<?php

namespace Hexa\PluginCore\WpAdminComponents;

/**
 * Reusable visual template selector for Hexa plugin admin screens.
 *
 * Hosts own template renderers and persistence. Core owns the accessible
 * selection UI, stable preview dimensions, responsive grid, and change event.
 */
final class TemplateSelectionControl {
    /**
     * @param array{
     *     id?:string,
     *     name?:string,
     *     value?:string,
     *     title?:string,
     *     description?:string,
     *     templates?:array<string,array<string,mixed>>,
     *     custom?:array<string,mixed>|false,
     *     custom_value?:string,
     *     custom_control?:string,
     *     input_class?:string,
     *     input_data?:array<string,scalar>,
     *     preview_height?:int,
     *     preview_width?:int,
     *     columns?:int,
     *     class?:string
     * } $args
     */
    public static function render( array $args ): string {
        $id          = self::clean_id( (string) ( $args['id'] ?? 'hpc-template-selection' ) );
        $name        = trim( (string) ( $args['name'] ?? $id ) );
        $title       = (string) ( $args['title'] ?? 'Choose a template' );
        $description = (string) ( $args['description'] ?? '' );
        $templates   = self::templates( (array) ( $args['templates'] ?? [] ) );
        $custom      = self::custom_option( $args );
        $custom_control = self::custom_control( $args );

        if ( [] === $templates && null === $custom ) {
            return '';
        }

        if ( null !== $custom ) {
            $templates[ $custom['value'] ] = $custom;
        }

        $selected = sanitize_key( (string) ( $args['value'] ?? '' ) );
        if ( ! isset( $templates[ $selected ] ) ) {
            $selected = (string) array_key_first( $templates );
        }

        $custom_as_toggle  = null !== $custom && 'toggle' === $custom_control;
        $custom_is_selected = $custom_as_toggle && $selected === $custom['value'];
        $input_class   = self::classes( 'hpc-template-selection-input ' . (string) ( $args['input_class'] ?? '' ) );
        $control_class = self::classes( 'hpc-template-selection ' . (string) ( $args['class'] ?? '' ) );
        if ( $custom_is_selected ) {
            $control_class = self::classes( $control_class . ' is-custom-mode' );
        }
        $input_data    = self::data_attributes( (array) ( $args['input_data'] ?? [] ) );
        $columns        = min( 4, max( 1, (int) ( $args['columns'] ?? 3 ) ) );
        $tablet_columns = min( 2, $columns );
        $height         = min( 420, max( 140, (int) ( $args['preview_height'] ?? 210 ) ) );
        $width          = min( 1800, max( 320, (int) ( $args['preview_width'] ?? 720 ) ) );
        $selected_text  = (string) ( $templates[ $selected ]['label'] ?? $selected );

        CoreUi::render_assets();

        ob_start();
        echo self::assets_once(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
        <section
            id="<?php echo esc_attr( $id ); ?>"
            class="<?php echo esc_attr( $control_class ); ?>"
            style="--hpc-template-columns:<?php echo esc_attr( (string) $columns ); ?>;--hpc-template-tablet-columns:<?php echo esc_attr( (string) $tablet_columns ); ?>;--hpc-template-preview-height:<?php echo esc_attr( (string) $height ); ?>px"
            data-hpc-template-selection
            data-hpc-template-selection-name="<?php echo esc_attr( $name ); ?>"
            data-hpc-template-selection-value="<?php echo esc_attr( $selected ); ?>"
            data-hpc-template-custom-control="<?php echo esc_attr( $custom_control ); ?>"
        >
            <header class="hpc-template-selection-head">
                <div>
                    <h3><?php echo esc_html( $title ); ?></h3>
                    <?php if ( '' !== $description ) : ?>
                        <p><?php echo esc_html( $description ); ?></p>
                    <?php endif; ?>
                </div>
                <span class="hpc-template-selection-current">Selected: <strong data-hpc-template-selection-current><?php echo esc_html( $selected_text ); ?></strong></span>
            </header>
            <?php if ( $custom_as_toggle ) : ?>
                <div class="hpc-template-selection-custom-bar">
                    <div>
                        <?php echo CoreUi::toggle(
                            $name . '_custom',
                            $custom_is_selected,
                            (string) $custom['toggle_label'],
                            [
                                'id'          => $id . '-custom-toggle',
                                'class'       => 'hpc-template-selection-custom-toggle-control',
                                'input_class' => 'hpc-template-selection-custom-toggle',
                                'data'        => [ 'hpc_template_custom_toggle' => '1' ],
                            ]
                        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <p><?php echo esc_html( (string) $custom['toggle_description'] ); ?></p>
                    </div>
                    <input
                        id="<?php echo esc_attr( $id . '-custom-choice' ); ?>"
                        class="<?php echo esc_attr( self::classes( $input_class . ' hpc-template-selection-custom-input' ) ); ?>"
                        type="radio"
                        name="<?php echo esc_attr( $name ); ?>"
                        value="<?php echo esc_attr( (string) $custom['value'] ); ?>"
                        data-hpc-template-selection-input
                        data-hpc-template-custom-input
                        data-template-label="<?php echo esc_attr( (string) $custom['label'] ); ?>"
                        <?php echo $input_data . self::data_attributes( (array) $custom['data'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php checked( $custom_is_selected ); ?>
                        hidden
                    >
                </div>
            <?php endif; ?>
            <div class="hpc-template-selection-grid" role="radiogroup" aria-label="<?php echo esc_attr( $title ); ?>"<?php echo $custom_is_selected ? ' aria-disabled="true"' : ''; ?>>
                <?php foreach ( $templates as $value => $template ) :
                    $option_id     = self::clean_id( $id . '-' . $value );
                    $is_selected   = $selected === $value;
                    $is_custom     = ! empty( $template['custom'] );
                    if ( $custom_as_toggle && $is_custom ) {
                        continue;
                    }
                    $option_height = min( 420, max( 140, (int) ( $template['preview_height'] ?? $height ) ) );
                    $option_width  = min( 1800, max( 320, (int) ( $template['preview_width'] ?? $width ) ) );
                    $option_data   = self::data_attributes( (array) ( $template['data'] ?? [] ) );
                    ?>
                    <article class="hpc-template-selection-card<?php echo $is_selected ? ' is-selected' : ''; ?><?php echo $is_custom ? ' is-custom' : ''; ?>" data-hpc-template-selection-option data-template-value="<?php echo esc_attr( $value ); ?>">
                        <label class="hpc-template-selection-label" for="<?php echo esc_attr( $option_id ); ?>">
                            <input
                                id="<?php echo esc_attr( $option_id ); ?>"
                                class="<?php echo esc_attr( $input_class ); ?>"
                                type="radio"
                                name="<?php echo esc_attr( $name ); ?>"
                                value="<?php echo esc_attr( $value ); ?>"
                                data-hpc-template-selection-input
                                data-template-label="<?php echo esc_attr( (string) $template['label'] ); ?>"
                                <?php echo $input_data . $option_data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <?php checked( $is_selected ); ?>
                                <?php echo $custom_is_selected ? ' disabled' : ''; ?>
                            >
                            <span class="hpc-template-selection-card-head">
                                <span class="hpc-template-selection-radio" aria-hidden="true"></span>
                                <span class="hpc-template-selection-copy">
                                    <strong><?php echo esc_html( (string) $template['label'] ); ?></strong>
                                    <?php if ( '' !== (string) $template['description'] ) : ?>
                                        <small><?php echo esc_html( (string) $template['description'] ); ?></small>
                                    <?php endif; ?>
                                </span>
                                <span class="hpc-template-selection-selected">Selected</span>
                            </span>
                            <span class="hpc-template-selection-preview" style="--hpc-template-option-height:<?php echo esc_attr( (string) $option_height ); ?>px" data-hpc-template-preview aria-hidden="true">
                                <?php if ( $is_custom ) : ?>
                                    <span class="hpc-template-selection-custom-preview">
                                        <?php echo self::custom_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        <strong>No plugin design output</strong>
                                        <small>Use your theme or page builder without injected markup or styles.</small>
                                    </span>
                                <?php else : ?>
                                    <span class="hpc-template-selection-preview-canvas" style="width:<?php echo esc_attr( (string) $option_width ); ?>px" data-hpc-template-preview-canvas data-preview-width="<?php echo esc_attr( (string) $option_width ); ?>">
                                        <?php echo (string) $template['preview_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                        </label>
                        <?php if ( '' !== (string) $template['actions_html'] ) : ?>
                            <div class="hpc-template-selection-actions"><?php echo (string) $template['actions_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    /** @return array<string,array<string,mixed>> */
    private static function templates( array $templates ): array {
        $normalized = [];
        foreach ( $templates as $value => $template ) {
            if ( ! is_array( $template ) ) {
                $template = [ 'label' => (string) $template ];
            }
            $value = sanitize_key( (string) ( $template['value'] ?? $value ) );
            if ( '' === $value ) {
                continue;
            }
            $normalized[ $value ] = [
                'value'          => $value,
                'label'          => (string) ( $template['label'] ?? ucwords( str_replace( [ '-', '_' ], ' ', $value ) ) ),
                'description'    => (string) ( $template['description'] ?? '' ),
                'preview_html'   => (string) ( $template['preview_html'] ?? '' ),
                'actions_html'   => (string) ( $template['actions_html'] ?? '' ),
                'preview_height' => isset( $template['preview_height'] ) ? (int) $template['preview_height'] : null,
                'preview_width'  => isset( $template['preview_width'] ) ? (int) $template['preview_width'] : null,
                'data'           => isset( $template['data'] ) && is_array( $template['data'] ) ? $template['data'] : [],
                'custom'         => false,
            ];
        }
        return $normalized;
    }

    /** @return array<string,mixed>|null */
    private static function custom_option( array $args ): ?array {
        if ( array_key_exists( 'custom', $args ) && false === $args['custom'] ) {
            return null;
        }

        $custom = isset( $args['custom'] ) && is_array( $args['custom'] ) ? $args['custom'] : [];
        $value  = sanitize_key( (string) ( $custom['value'] ?? $args['custom_value'] ?? 'custom' ) );
        if ( '' === $value ) {
            $value = 'custom';
        }

        return [
            'value'          => $value,
            'label'          => (string) ( $custom['label'] ?? "I'm going to design it myself" ),
            'description'    => (string) ( $custom['description'] ?? 'Disable the plugin template for this placement and provide your own design.' ),
            'preview_html'   => '',
            'actions_html'   => (string) ( $custom['actions_html'] ?? '' ),
            'preview_height' => isset( $custom['preview_height'] ) ? (int) $custom['preview_height'] : null,
            'preview_width'  => isset( $custom['preview_width'] ) ? (int) $custom['preview_width'] : null,
            'data'           => isset( $custom['data'] ) && is_array( $custom['data'] ) ? $custom['data'] : [],
            'custom'         => true,
            'toggle_label'   => (string) ( $custom['toggle_label'] ?? 'No plugin design' ),
            'toggle_description' => (string) ( $custom['toggle_description'] ?? $custom['description'] ?? 'Disable the plugin design and use your theme or page builder instead.' ),
        ];
    }

    private static function custom_control( array $args ): string {
        return 'toggle' === sanitize_key( (string) ( $args['custom_control'] ?? 'card' ) ) ? 'toggle' : 'card';
    }

    private static function data_attributes( array $attributes ): string {
        $html = '';
        foreach ( $attributes as $key => $value ) {
            if ( ! is_scalar( $value ) ) {
                continue;
            }
            $key = sanitize_key( str_replace( '_', '-', (string) $key ) );
            if ( '' !== $key ) {
                $html .= ' data-' . $key . '="' . esc_attr( (string) $value ) . '"';
            }
        }
        return $html;
    }

    private static function classes( string $classes ): string {
        $normalized = [];
        foreach ( preg_split( '/\s+/', trim( $classes ) ) ?: [] as $class ) {
            $class = sanitize_html_class( $class );
            if ( '' !== $class ) {
                $normalized[] = $class;
            }
        }
        return implode( ' ', array_unique( $normalized ) );
    }

    private static function clean_id( string $value ): string {
        $id = sanitize_html_class( $value );
        return '' !== $id ? $id : 'hpc-template-selection';
    }

    private static function custom_icon(): string {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/><path d="m15 5 3 3"/></svg>';
    }

    private static function assets_once(): string {
        static $rendered = false;
        if ( $rendered ) {
            return '';
        }
        $rendered = true;

        return <<<'HTML'
<style id="hpc-template-selection-assets">
.hpc-template-selection{background:#fff;border:1px solid #d9e0ea;border-radius:8px;display:grid;gap:0;min-width:0;overflow:hidden}
.hpc-template-selection-head{align-items:flex-start;border-bottom:1px solid #e4e9f0;display:flex;gap:18px;justify-content:space-between;padding:16px 18px}
.hpc-template-selection-head h3{font-size:16px;letter-spacing:0;margin:0 0 5px}.hpc-template-selection-head p{color:#64748b;line-height:1.5;margin:0;max-width:76ch}
.hpc-template-selection-current{background:#eef3ff;border:1px solid #d7e2ff;border-radius:999px;color:#2944ad;flex:0 0 auto;font-size:12px;font-weight:700;padding:7px 10px}
.hpc-template-selection-custom-bar{align-items:center;background:#fbfcfe;border-bottom:1px solid #e4e9f0;display:flex;justify-content:space-between;padding:14px 18px}.hpc-template-selection-custom-bar>div{display:grid;gap:5px}.hpc-template-selection-custom-bar .hpc-toggle{font-weight:800;margin:0}.hpc-template-selection-custom-bar p{color:#64748b;font-size:12px;line-height:1.45;margin:0 0 0 44px;max-width:76ch}
.hpc-template-selection-grid{display:grid;gap:14px;grid-template-columns:repeat(var(--hpc-template-columns,3),minmax(0,1fr));padding:16px}
.hpc-template-selection.is-custom-mode .hpc-template-selection-grid{background:#f8fafc;opacity:.48;pointer-events:none}.hpc-template-selection.is-custom-mode .hpc-template-selection-current{background:#f1f3f5;border-color:#d9dee5;color:#475569}
.hpc-template-selection-card{background:#fff;border:1px solid #d8dee8;border-radius:8px;display:flex;flex-direction:column;min-width:0;overflow:hidden;transition:border-color .15s,box-shadow .15s}
.hpc-template-selection-card:hover{border-color:#aebbd0}.hpc-template-selection-card.is-selected{border-color:#3157d5;box-shadow:0 0 0 1px #3157d5}
.hpc-template-selection-label{cursor:pointer;display:flex;flex:1 1 auto;flex-direction:column;margin:0;min-width:0}.hpc-template-selection-input{clip:rect(0 0 0 0);height:1px;margin:-1px;overflow:hidden;position:absolute;width:1px}
.hpc-template-selection-card-head{align-items:flex-start;display:flex;gap:10px;min-height:92px;padding:14px}.hpc-template-selection-radio{background:#fff;border:2px solid #a9b4c3;border-radius:50%;box-shadow:inset 0 0 0 3px #fff;flex:0 0 auto;height:18px;margin-top:1px;width:18px}
.hpc-template-selection-input:checked+.hpc-template-selection-card-head .hpc-template-selection-radio{background:#3157d5;border-color:#3157d5}.hpc-template-selection-input:focus-visible+.hpc-template-selection-card-head{box-shadow:inset 0 0 0 2px #3157d5}
.hpc-template-selection-copy{display:block;min-width:0}.hpc-template-selection-copy strong,.hpc-template-selection-copy small{display:block}.hpc-template-selection-copy strong{color:#172033;font-size:14px;line-height:1.3}.hpc-template-selection-copy small{color:#64748b;font-size:12px;line-height:1.4;margin-top:4px}
.hpc-template-selection-selected{background:#3157d5;border-radius:999px;color:#fff;display:none;flex:0 0 auto;font-size:10px;font-weight:800;margin-left:auto;padding:4px 7px;text-transform:uppercase}.hpc-template-selection-card.is-selected .hpc-template-selection-selected{display:inline-flex}
.hpc-template-selection-preview{background:#f7f9fc;border-top:1px solid #e9edf3;display:block;height:var(--hpc-template-option-height,var(--hpc-template-preview-height,210px));overflow:hidden;pointer-events:none;position:relative;width:100%}
.hpc-template-selection-preview-canvas{display:block;left:0;position:absolute;top:0;transform:scale(var(--hpc-template-preview-scale,1));transform-origin:top left}.hpc-template-selection-preview-canvas *{pointer-events:none!important}
.hpc-template-selection-custom-preview{align-items:center;color:#475569;display:flex;flex-direction:column;height:100%;justify-content:center;padding:22px;text-align:center}.hpc-template-selection-custom-preview svg{color:#3157d5;height:36px;margin-bottom:10px;width:36px}.hpc-template-selection-custom-preview strong{color:#172033;font-size:14px}.hpc-template-selection-custom-preview small{font-size:12px;line-height:1.45;margin-top:5px;max-width:30ch}
.hpc-template-selection-actions{align-items:center;background:#fbfcfe;border-top:1px solid #e9edf3;display:flex;flex-wrap:wrap;gap:8px;margin-top:auto;padding:10px 12px}.hpc-template-selection-actions .button,.hpc-template-selection-actions .hpc-button{margin:0}
@media(max-width:1100px){.hpc-template-selection-grid{grid-template-columns:repeat(var(--hpc-template-tablet-columns,2),minmax(0,1fr))}}
@media(max-width:782px){.hpc-template-selection-head{display:grid}.hpc-template-selection-current{justify-self:start}.hpc-template-selection-custom-bar p{margin-left:0}.hpc-template-selection-grid{grid-template-columns:1fr}.hpc-template-selection-card-head{min-height:0}}
</style>
<script id="hpc-template-selection-script">
(function(){
    if(window.hexaTemplateSelectionReady)return;
    window.hexaTemplateSelectionReady=true;
    function fit(viewport){
        if(!viewport)return;
        var canvas=viewport.querySelector("[data-hpc-template-preview-canvas]");
        if(!canvas)return;
        var width=parseFloat(canvas.getAttribute("data-preview-width")||canvas.style.width||"0");
        if(!width)return;
        var scale=Math.min(1,Math.max(.1,viewport.clientWidth/width));
        canvas.style.setProperty("--hpc-template-preview-scale",String(scale));
    }
    function sync(control,emit){
        if(!control)return;
        var input=control.querySelector("[data-hpc-template-selection-input]:checked");
        if(!input)return;
        var value=input.value||"",label=input.getAttribute("data-template-label")||value;
        var custom=input.hasAttribute("data-hpc-template-custom-input"),toggle=control.querySelector("[data-hpc-template-custom-toggle]"),grid=control.querySelector(".hpc-template-selection-grid");
        control.setAttribute("data-hpc-template-selection-value",value);
        if(!custom)control.setAttribute("data-hpc-template-selection-last",value);
        control.classList.toggle("is-custom-mode",custom);
        if(toggle)toggle.checked=custom;
        if(grid){if(custom)grid.setAttribute("aria-disabled","true");else grid.removeAttribute("aria-disabled");}
        control.querySelectorAll("[data-hpc-template-selection-input]:not([data-hpc-template-custom-input])").forEach(function(option){option.disabled=custom;});
        control.querySelectorAll("[data-hpc-template-selection-option]").forEach(function(card){card.classList.toggle("is-selected",!!card.querySelector("[data-hpc-template-selection-input]:checked"));});
        var current=control.querySelector("[data-hpc-template-selection-current]");
        if(current)current.textContent=label;
        control.querySelectorAll("[data-hpc-template-preview]").forEach(fit);
        if(emit){
            var detail={control:control,input:input,name:control.getAttribute("data-hpc-template-selection-name")||input.name,value:value,label:label};
            control.dispatchEvent(new CustomEvent("hexa-template-selection-change",{bubbles:true,detail:detail}));
        }
    }
    var observer=window.ResizeObserver?new ResizeObserver(function(entries){entries.forEach(function(entry){fit(entry.target);});}):null;
    function init(root){(root||document).querySelectorAll("[data-hpc-template-selection]").forEach(function(control){sync(control,false);control.querySelectorAll("[data-hpc-template-preview]").forEach(function(viewport){if(observer)observer.observe(viewport);});});}
    document.addEventListener("change",function(event){
        var toggle=event.target.closest("[data-hpc-template-custom-toggle]");
        if(toggle){
            var control=toggle.closest("[data-hpc-template-selection]"),custom=control&&control.querySelector("[data-hpc-template-custom-input]");
            if(!control||!custom)return;
            if(toggle.checked){
                var active=control.querySelector("[data-hpc-template-selection-input]:checked:not([data-hpc-template-custom-input])");
                if(active)control.setAttribute("data-hpc-template-selection-last",active.value||"");
                custom.checked=true;
                custom.dispatchEvent(new Event("change",{bubbles:true}));
            }else{
                var choices=control.querySelectorAll("[data-hpc-template-selection-input]:not([data-hpc-template-custom-input])");
                choices.forEach(function(choice){choice.disabled=false;});
                var last=control.getAttribute("data-hpc-template-selection-last")||"",fallback=null;
                choices.forEach(function(choice){if(!fallback&&choice.value===last)fallback=choice;});
                if(!fallback)fallback=choices[0]||null;
                if(fallback){fallback.checked=true;fallback.dispatchEvent(new Event("change",{bubbles:true}));}
            }
            return;
        }
        var input=event.target.closest("[data-hpc-template-selection-input]");if(input)sync(input.closest("[data-hpc-template-selection]"),true);
    });
    document.addEventListener("hexa-core-host-tab-loaded",function(event){init(event.detail&&event.detail.panel?event.detail.panel:document);});
    window.addEventListener("resize",function(){document.querySelectorAll("[data-hpc-template-preview]").forEach(fit);});
    window.hexaTemplateSelection={init:init,sync:sync,fit:fit};
    if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",function(){init(document);});else init(document);
})();
</script>
HTML;
    }
}
