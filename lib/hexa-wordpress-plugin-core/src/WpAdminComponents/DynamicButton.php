<?php

namespace Hexa\PluginCore\WpAdminComponents;

final class DynamicButton {
    public static function render( array $args ): string {
        if ( ! array_key_exists( 'render_assets', $args ) || ! empty( $args['render_assets'] ) ) {
            self::render_assets();
        }

        $id            = self::clean_id( (string) ( $args['id'] ?? '' ) );
        $label         = (string) ( $args['label'] ?? 'Run' );
        $working_label = (string) ( $args['working_label'] ?? 'Working...' );
        $success_label = (string) ( $args['success_label'] ?? 'Done' );
        $error_label   = (string) ( $args['error_label'] ?? 'Failed' );
        $class         = trim( 'hpc-dynamic-button ' . (string) ( $args['class'] ?? 'hpc-button' ) );
        $disabled      = ! empty( $args['disabled'] ) ? ' disabled' : '';
        $attrs         = '';

        foreach ( (array) ( $args['attrs'] ?? [] ) as $name => $value ) {
            if ( is_bool( $value ) ) {
                if ( $value ) {
                    $attrs .= ' ' . esc_attr( (string) $name );
                }
                continue;
            }
            if ( null === $value ) {
                continue;
            }
            $attrs .= ' ' . esc_attr( (string) $name ) . '="' . esc_attr( (string) $value ) . '"';
        }

        return '<button type="button"'
            . ( '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '' )
            . ' class="' . esc_attr( $class ) . '"'
            . ' data-hpc-dynamic-button'
            . ' data-default-label="' . esc_attr( $label ) . '"'
            . ' data-working-label="' . esc_attr( $working_label ) . '"'
            . ' data-success-label="' . esc_attr( $success_label ) . '"'
            . ' data-error-label="' . esc_attr( $error_label ) . '"'
            . ' aria-live="polite"'
            . $disabled
            . $attrs
            . '><span class="hpc-dynamic-button-spinner" aria-hidden="true"></span><span class="hpc-dynamic-button-icon" aria-hidden="true"></span><span class="hpc-dynamic-button-label">' . esc_html( $label ) . '</span></button>';
    }

    public static function render_assets(): void {
        static $rendered = false;
        if ( $rendered ) {
            return;
        }
        $rendered = true;
        CoreUi::render_assets();
        ?>
        <style>
            .hpc-dynamic-button{align-items:center;gap:10px;justify-content:center;position:relative}
            .hpc-dynamic-button .hpc-dynamic-button-spinner{animation:hpc-dynamic-button-spin .72s linear infinite;background:transparent!important;border:3px solid currentColor;border-right-color:transparent;border-radius:999px;box-sizing:border-box;display:none;float:none;height:22px;margin:0;min-height:22px;min-width:22px;opacity:1;width:22px}
            .hpc-dynamic-button .hpc-dynamic-button-icon{display:none;font-size:18px;font-weight:900;line-height:1}
            .hpc-dynamic-button.is-loading .hpc-dynamic-button-spinner{display:inline-block;visibility:visible}
            .hpc-dynamic-button.is-success .hpc-dynamic-button-icon,.hpc-dynamic-button.is-error .hpc-dynamic-button-icon{display:inline-block}
            .hpc-dynamic-button.is-success .hpc-dynamic-button-icon:before{content:"\2713"}
            .hpc-dynamic-button.is-error .hpc-dynamic-button-icon:before{content:"\00d7"}
            .hpc-dynamic-button.is-error{background:#b42336!important;border-color:#b42336!important;color:#fff!important}
            .hpc-dynamic-button.is-success{background:#16803c!important;border-color:#16803c!important;color:#fff!important}
            .hpc-dynamic-button[disabled]{cursor:default;opacity:.82}
            .hpc-dynamic-button.is-loading[disabled]{opacity:1}
            @keyframes hpc-dynamic-button-spin{to{transform:rotate(360deg)}}
        </style>
        <script>
        (function(){
            if(window.HexaWpCoreDynamicButton)return;
            function el(button){return typeof button==="string"?document.querySelector(button):(button&&button.jquery?button[0]:button)}
            function label(button,text){var n=button?button.querySelector(".hpc-dynamic-button-label"):null;if(n)n.textContent=text||""}
            function clear(button){button.classList.remove("is-loading","is-success","is-error");button.removeAttribute("aria-busy")}
            function setState(button,state,text,keepDisabled){
                button=el(button);if(!button)return null;
                if(!button.dataset.defaultLabel){button.dataset.defaultLabel=(button.textContent||"").trim()}
                clear(button);
                if(state){button.classList.add("is-"+state)}
                if("loading"===state){button.disabled=true;button.setAttribute("aria-busy","true");label(button,text||button.dataset.workingLabel||"Working...")}
                else {button.disabled=!!keepDisabled;label(button,text||button.dataset.defaultLabel||"Run")}
                if("success"===state){label(button,text||button.dataset.successLabel||"Done")}
                if("error"===state){label(button,text||button.dataset.errorLabel||"Failed")}
                return button;
            }
            window.HexaWpCoreDynamicButton={
                start:function(button,text){return setState(button,"loading",text,true)},
                success:function(button,text,timeout){button=setState(button,"success",text,false);if(button&&false!==timeout){setTimeout(function(){window.HexaWpCoreDynamicButton.reset(button)},timeout||1400)}return button},
                error:function(button,text,timeout){button=setState(button,"error",text,false);if(button&&false!==timeout){setTimeout(function(){window.HexaWpCoreDynamicButton.reset(button)},timeout||1800)}return button},
                reset:function(button){button=el(button);if(!button)return null;clear(button);button.disabled=false;label(button,button.dataset.defaultLabel||"Run");return button},
                disable:function(button){button=el(button);if(button)button.disabled=true;return button},
                enable:function(button){button=el(button);if(button)button.disabled=false;return button}
            };
        })();
        </script>
        <?php
    }

    private static function clean_id( string $value ): string {
        if ( '' === $value ) {
            return '';
        }
        if ( function_exists( 'sanitize_html_class' ) ) {
            return sanitize_html_class( $value );
        }
        return preg_replace( '/[^a-zA-Z0-9_\-]/', '-', $value ) ?: '';
    }
}
