<?php

namespace Hexa\PluginCore\WpAdminComponents;

final class DynamicNotice {
    private const TONES = [ 'info', 'success', 'warning', 'error' ];

    public static function render( array $args = [] ): string {
        if ( ! array_key_exists( 'render_assets', $args ) || ! empty( $args['render_assets'] ) ) {
            self::render_assets();
        }

        $id      = self::clean_id( (string) ( $args['id'] ?? '' ) );
        $tone    = self::clean_tone( (string) ( $args['tone'] ?? 'info' ) );
        $title   = trim( (string) ( $args['title'] ?? '' ) );
        $message = trim( (string) ( $args['message'] ?? '' ) );
        $hidden  = ! array_key_exists( 'hidden', $args ) || ! empty( $args['hidden'] );
        $dismissible = ! array_key_exists( 'dismissible', $args ) || ! empty( $args['dismissible'] );

        return '<div'
            . ( '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '' )
            . ' class="hpc-dynamic-notice is-' . esc_attr( $tone ) . '"'
            . ' role="status" aria-live="polite" aria-atomic="true" data-hpc-dynamic-notice'
            . ( $hidden ? ' hidden' : '' )
            . '><div class="hpc-dynamic-notice-copy">'
            . '<strong class="hpc-dynamic-notice-title">' . esc_html( $title ) . '</strong>'
            . '<span class="hpc-dynamic-notice-message">' . esc_html( $message ) . '</span>'
            . '</div>'
            . ( $dismissible ? '<button type="button" class="hpc-dynamic-notice-dismiss" aria-label="Dismiss notification" data-hpc-dynamic-notice-dismiss>&times;</button>' : '' )
            . '</div>';
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
            .hpc-dynamic-notice{align-items:flex-start;background:#f8fbff;border:1px solid #cfe0ff;border-left:4px solid var(--hpc-blue,#3157d5);border-radius:8px;color:#253650;display:flex;gap:12px;justify-content:space-between;margin:0 0 16px;padding:12px 14px}
            .hpc-dynamic-notice[hidden]{display:none!important}
            .hpc-dynamic-notice.is-success{background:#edf9f1;border-color:#ccefd7;border-left-color:var(--hpc-green,#16803c)}
            .hpc-dynamic-notice.is-warning{background:#fff8e8;border-color:#f0d58a;border-left-color:var(--hpc-amber,#9a6700)}
            .hpc-dynamic-notice.is-error{background:#fff0f2;border-color:#ffd0d8;border-left-color:var(--hpc-red,#b42336)}
            .hpc-dynamic-notice-copy{display:grid;gap:3px;min-width:0}
            .hpc-dynamic-notice-title:empty,.hpc-dynamic-notice-message:empty{display:none}
            .hpc-dynamic-notice-message{line-height:1.45;overflow-wrap:anywhere}
            .hpc-dynamic-notice-dismiss{background:transparent;border:0;border-radius:4px;color:inherit;cursor:pointer;flex:0 0 auto;font-size:20px;line-height:1;padding:2px 5px}
            .hpc-dynamic-notice-dismiss:focus-visible{box-shadow:0 0 0 2px var(--hpc-blue,#3157d5);outline:0}
        </style>
        <script>
        (function(){
            if(window.HexaWpCoreDynamicNotice)return;
            function el(target){return typeof target==='string'?document.querySelector(target):(target&&target.jquery?target[0]:target)}
            function tone(value){return ['info','success','warning','error'].indexOf(value)>=0?value:'info'}
            function show(target,payload){
                var notice=el(target);if(!notice)return null;
                payload=typeof payload==='string'?{message:payload}:(payload||{});
                ['info','success','warning','error'].forEach(function(value){notice.classList.remove('is-'+value)});
                notice.classList.add('is-'+tone(payload.tone||'info'));
                var title=notice.querySelector('.hpc-dynamic-notice-title');
                var message=notice.querySelector('.hpc-dynamic-notice-message');
                if(title)title.textContent=payload.title||'';
                if(message)message.textContent=payload.message||'';
                notice.hidden=false;
                return notice;
            }
            window.HexaWpCoreDynamicNotice={
                show:show,
                info:function(target,title,message){return show(target,{tone:'info',title:title,message:message})},
                success:function(target,title,message){return show(target,{tone:'success',title:title,message:message})},
                warning:function(target,title,message){return show(target,{tone:'warning',title:title,message:message})},
                error:function(target,title,message){return show(target,{tone:'error',title:title,message:message})},
                hide:function(target){var notice=el(target);if(notice)notice.hidden=true;return notice}
            };
            document.addEventListener('click',function(event){
                var button=event.target.closest('[data-hpc-dynamic-notice-dismiss]');
                if(!button)return;
                var notice=button.closest('[data-hpc-dynamic-notice]');
                if(notice)notice.hidden=true;
            });
        })();
        </script>
        <?php
    }

    private static function clean_tone( string $tone ): string {
        $tone = sanitize_key( $tone );
        return in_array( $tone, self::TONES, true ) ? $tone : 'info';
    }

    private static function clean_id( string $value ): string {
        if ( '' === $value ) {
            return '';
        }
        return sanitize_html_class( $value );
    }
}
