<?php

namespace Hexa\PluginCore\SearchDisplay;

/**
 * Renders reusable front-end WordPress search forms.
 *
 * Admin previews and host-plugin shortcodes must call this same renderer so
 * the previewed template is the markup that ships to the front end.
 */
final class SearchDisplayRenderer {
    public const STYLES = [ 'icon-reveal', 'overlay', 'pill', 'underline', 'command' ];

    private static int $instance_count = 0;

    /**
     * @return array<string,array{label:string,behavior:string,description:string,best_for:string}>
     */
    public static function styles(): array {
        return [
            'icon-reveal' => [
                'label'       => 'Icon reveal',
                'behavior'    => 'Click to expand',
                'description' => 'Just a magnifier. Click it and the field slides open in place; Escape or a click away tucks it back to the icon.',
                'best_for'    => 'Dense headers where navigation space is tight and search is secondary.',
            ],
            'overlay' => [
                'label'       => 'Overlay',
                'behavior'    => 'Opens a popup',
                'description' => 'A small trigger opens a centered search panel over a dimmed page. Escape or a backdrop click closes it; Cmd/Ctrl+K opens it from anywhere.',
                'best_for'    => 'Search-forward publications that want a large, deliberate search moment.',
            ],
            'pill' => [
                'label'       => 'Pill',
                'behavior'    => 'Always visible',
                'description' => 'A soft rounded field that reads as friendly and obvious.',
                'best_for'    => 'General-interest publications that want search plainly available.',
            ],
            'underline' => [
                'label'       => 'Underline',
                'behavior'    => 'Always visible',
                'description' => 'No box, just a hairline that picks up the accent on focus.',
                'best_for'    => 'Minimalist, type-led designs and serif mastheads.',
            ],
            'command' => [
                'label'       => 'Command bar',
                'behavior'    => 'Always visible',
                'description' => 'A prominent bar with an explicit button and keyboard hint.',
                'best_for'    => 'Archives and search-heavy publications that want search front and center.',
            ],
        ];
    }

    /**
     * @param array{style?:string,accent?:string,placeholder?:string,action_url?:string,label?:string,radius?:string,id?:string,hidden_fields?:array<string,scalar>} $args
     */
    public static function render( array $args = [] ): string {
        $style = self::style( (string) ( $args['style'] ?? 'pill' ) );
        $accent = self::sanitize_color( (string) ( $args['accent'] ?? '' ) );
        $radius = self::sanitize_length( (string) ( $args['radius'] ?? '' ) );
        $placeholder = trim( (string) ( $args['placeholder'] ?? '' ) );
        $label = trim( (string) ( $args['label'] ?? '' ) );
        $action = trim( (string) ( $args['action_url'] ?? '' ) );

        if ( '' === $placeholder ) {
            $placeholder = 'Search...';
        }
        if ( '' === $label ) {
            $label = 'Search';
        }
        if ( '' === $action ) {
            $action = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '/';
        }

        $uid = self::instance_id( $style, (string) ( $args['id'] ?? '' ) );
        $hidden_fields = self::hidden_fields_html( $args['hidden_fields'] ?? [] );
        $variables = '';
        if ( '' !== $accent ) {
            $variables .= '--sd-accent:' . $accent . ';';
        }
        if ( '' !== $radius ) {
            $variables .= '--sd-radius:' . $radius . ';';
        }
        $style_attribute = '' !== $variables ? ' style="' . esc_attr( $variables ) . '"' : '';

        $output  = self::assets();
        $output .= '<div class="hexa-search hexa-search--' . esc_attr( $style ) . '"' . $style_attribute
            . ' data-hexa-search data-style="' . esc_attr( $style ) . '">';
        $output .= self::widget_html( $style, $uid, $action, $placeholder, $label, $hidden_fields );
        $output .= '</div>';

        if ( in_array( $style, [ 'overlay', 'command' ], true ) ) {
            $output .= self::overlay_html( $uid, $action, $placeholder, $label, $style_attribute, $hidden_fields );
        }

        return $output;
    }

    private static function style( string $style ): string {
        $style = sanitize_key( $style );

        return in_array( $style, self::STYLES, true ) ? $style : 'pill';
    }

    private static function instance_id( string $style, string $requested_id ): string {
        $requested_id = sanitize_html_class( $requested_id );
        if ( '' !== $requested_id ) {
            return $requested_id;
        }

        ++self::$instance_count;

        return 'hexa-search-' . substr( md5( $style . '|' . self::$instance_count ), 0, 10 );
    }

    private static function widget_html( string $style, string $uid, string $action, string $placeholder, string $label, string $hidden_fields ): string {
        $action = esc_url( $action );
        $placeholder = esc_attr( $placeholder );
        $label_attribute = esc_attr( $label );
        $label_html = esc_html( $label );
        $icon = self::icon();

        switch ( $style ) {
            case 'icon-reveal':
                return '<form class="sd-reveal-form" role="search" action="' . $action . '" method="get">'
                    . $hidden_fields . '<button type="button" class="sd-reveal-btn" data-sd-toggle aria-label="' . $label_attribute . '" aria-expanded="false">' . $icon . '</button>'
                    . '<span class="sd-reveal-field"><input class="sd-input" type="search" name="s" placeholder="' . $placeholder . '" aria-label="' . $label_attribute . '" tabindex="-1"></span>'
                    . '</form>';

            case 'overlay':
                return '<button type="button" class="sd-ov-trigger" data-sd-open="' . esc_attr( $uid . '-dialog' ) . '" aria-haspopup="dialog" aria-controls="' . esc_attr( $uid . '-dialog' ) . '" aria-expanded="false">'
                    . $icon . '<span>' . $label_html . '</span><span class="sd-k" aria-hidden="true">&#8984;K</span></button>';

            case 'underline':
                return '<form class="sd-underline" role="search" action="' . $action . '" method="get">'
                    . $hidden_fields . $icon . '<input class="sd-input" type="search" name="s" placeholder="' . $placeholder . '" aria-label="' . $label_attribute . '"></form>';

            case 'command':
                return '<form class="sd-cmd" role="search" action="' . $action . '" method="get">'
                    . $hidden_fields . $icon . '<input class="sd-input" type="search" name="s" placeholder="' . $placeholder . '" aria-label="' . $label_attribute . '">'
                    . '<button class="sd-cmd-k" type="button" data-sd-open="' . esc_attr( $uid . '-dialog' ) . '" aria-label="Open expanded search" aria-controls="' . esc_attr( $uid . '-dialog' ) . '" aria-expanded="false">&#8984;K</button>'
                    . '<button class="sd-cmd-go" type="submit">' . $label_html . '</button></form>';

            case 'pill':
            default:
                return '<form class="sd-pill" role="search" action="' . $action . '" method="get">'
                    . $hidden_fields . $icon . '<input class="sd-input" type="search" name="s" placeholder="' . $placeholder . '" aria-label="' . $label_attribute . '"></form>';
        }
    }

    private static function overlay_html( string $uid, string $action, string $placeholder, string $label, string $style_attribute, string $hidden_fields ): string {
        return '<div class="hexa-search-overlay" id="' . esc_attr( $uid . '-dialog' ) . '" role="dialog" aria-modal="true" aria-label="' . esc_attr( $label ) . '"' . $style_attribute . ' hidden>'
            . '<div class="sd-ov-panel" role="document"><form class="sd-ov-bar" role="search" action="' . esc_url( $action ) . '" method="get">'
            . $hidden_fields . self::icon()
            . '<input class="sd-input" type="search" name="s" placeholder="' . esc_attr( $placeholder ) . '" aria-label="' . esc_attr( $label ) . '">'
            . '<button class="sd-ov-close" type="button" data-sd-close aria-label="Close search">Esc</button>'
            . '</form></div></div>';
    }

    /** @param mixed $fields */
    private static function hidden_fields_html( $fields ): string {
        if ( ! is_array( $fields ) ) {
            return '';
        }

        $html = '';
        $count = 0;
        foreach ( $fields as $name => $value ) {
            if ( ! is_string( $name ) || ! is_scalar( $value ) ) {
                continue;
            }

            $name = sanitize_key( $name );
            if ( '' === $name ) {
                continue;
            }

            $html .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';
            if ( ++$count >= 10 ) {
                break;
            }
        }

        return $html;
    }

    private static function icon(): string {
        return '<svg class="sd-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"></circle><path d="m21 21-4.3-4.3"></path></svg>';
    }

    /** Shared CSS and delegated behavior, emitted at most once per request. */
    private static function assets(): string {
        static $rendered = false;

        if ( $rendered ) {
            return '';
        }
        $rendered = true;

        return <<<'HTML'
<style id="hexa-search-display-css">
.hexa-search{--sd-accent:#1b2230;--sd-ink:#1b2230;--sd-bg:#fff;--sd-line:#cfd6e0;--sd-muted:#69737f;--sd-radius:12px;--sd-font:inherit;display:inline-block;max-width:100%}
.hexa-search *,.hexa-search-overlay *{box-sizing:border-box}
.hexa-search .sd-ico,.hexa-search-overlay .sd-ico{width:18px;height:18px;display:block;flex:0 0 auto}
.hexa-search .sd-input,.hexa-search-overlay .sd-input{width:100%;min-width:0;margin:0;padding:0;border:0;outline:0;background:transparent;color:var(--sd-ink);font-family:var(--sd-font);font-size:15px;line-height:1.4;box-shadow:none}
.hexa-search .sd-input::placeholder,.hexa-search-overlay .sd-input::placeholder{color:var(--sd-muted);opacity:1}
.hexa-search .sd-k,.hexa-search .sd-cmd-k{margin:0;padding:1px 6px;border:1px solid var(--sd-line);border-radius:5px;background:transparent;color:var(--sd-muted);font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:11px;line-height:1.5}
.hexa-search .sd-cmd-k{cursor:pointer}
.hexa-search .sd-reveal-form{display:inline-flex;width:44px;height:44px;align-items:center;overflow:hidden;border:1px solid var(--sd-line);border-radius:999px;background:var(--sd-bg);transition:width .32s cubic-bezier(.4,0,.2,1),border-color .2s,box-shadow .2s}
.hexa-search--icon-reveal.is-open .sd-reveal-form{width:min(300px,72vw);border-color:var(--sd-accent);box-shadow:0 0 0 3px rgba(27,34,48,.14)}
.hexa-search .sd-reveal-btn{display:inline-flex;width:44px;height:44px;flex:0 0 44px;align-items:center;justify-content:center;margin:0;padding:0;border:0;background:transparent;color:var(--sd-ink);cursor:pointer}
.hexa-search .sd-reveal-field{min-width:0;flex:1 1 auto;padding-right:16px;opacity:0;transition:opacity .18s}
.hexa-search--icon-reveal.is-open .sd-reveal-field{opacity:1}
.hexa-search .sd-ov-trigger{display:inline-flex;height:42px;align-items:center;gap:8px;margin:0;padding:0 16px;border:1px solid var(--sd-line);border-radius:999px;background:var(--sd-bg);color:var(--sd-ink);font:600 13.5px/1 var(--sd-font);cursor:pointer}
.hexa-search .sd-ov-trigger:hover,.hexa-search .sd-ov-trigger:focus-visible{border-color:var(--sd-accent);color:var(--sd-accent)}
.hexa-search .sd-pill{display:inline-flex;width:320px;max-width:100%;height:46px;align-items:center;gap:10px;padding:0 16px;border:1px solid var(--sd-line);border-radius:999px;background:var(--sd-bg);color:var(--sd-muted);transition:border-color .18s,box-shadow .18s}
.hexa-search .sd-pill:focus-within{border-color:var(--sd-accent);box-shadow:0 0 0 3px rgba(27,34,48,.14)}
.hexa-search .sd-pill .sd-ico,.hexa-search .sd-underline .sd-ico{color:var(--sd-muted)}
.hexa-search .sd-pill:focus-within .sd-ico,.hexa-search .sd-underline:focus-within .sd-ico{color:var(--sd-accent)}
.hexa-search .sd-underline{display:inline-flex;width:320px;max-width:100%;height:42px;align-items:center;gap:11px;border-bottom:1.5px solid var(--sd-line);color:var(--sd-muted);transition:border-color .18s}
.hexa-search .sd-underline:focus-within{border-bottom-color:var(--sd-accent)}
.hexa-search .sd-underline .sd-input{font-size:16px}
.hexa-search .sd-cmd{display:inline-flex;width:420px;max-width:100%;height:52px;align-items:center;gap:12px;padding:0 8px 0 18px;border:1px solid var(--sd-line);border-radius:var(--sd-radius);background:var(--sd-bg);color:var(--sd-muted);box-shadow:0 1px 2px rgba(16,24,40,.04),0 12px 32px rgba(16,24,40,.08)}
.hexa-search .sd-cmd:focus-within{border-color:var(--sd-accent)}
.hexa-search .sd-cmd .sd-ico{color:var(--sd-accent)}
.hexa-search .sd-cmd-go{height:38px;flex:0 0 auto;margin:0;padding:0 15px;border:0;border-radius:8px;background:var(--sd-accent);color:#fff;font:650 13.5px/1 var(--sd-font);cursor:pointer}
.hexa-search-overlay[hidden]{display:none!important}
.hexa-search-overlay-open{overflow:hidden}
.hexa-search-overlay{--sd-accent:#1b2230;--sd-ink:#1b2230;--sd-line:#dfe4eb;--sd-muted:#69737f;--sd-font:inherit;position:fixed;inset:0;z-index:100000;display:flex;align-items:flex-start;justify-content:center;padding:14vh 20px 20px;background:rgba(10,14,20,.62)}
.hexa-search-overlay .sd-ov-panel{width:min(620px,100%);overflow:hidden;border:1px solid #e2e7ee;border-radius:16px;background:#fff;box-shadow:0 30px 80px rgba(0,0,0,.45)}
.hexa-search-overlay .sd-ov-bar{display:flex;align-items:center;gap:14px;padding:20px 22px}
.hexa-search-overlay .sd-ico{width:22px;height:22px;color:var(--sd-accent)}
.hexa-search-overlay .sd-input{font-size:20px}
.hexa-search-overlay .sd-ov-close{flex:0 0 auto;margin:0;padding:3px 7px;border:1px solid #dfe4eb;border-radius:6px;background:#fff;color:#69737f;font:11px/1.5 ui-monospace,Menlo,monospace;cursor:pointer}
@media(max-width:480px){.hexa-search .sd-cmd{height:auto;min-height:52px;flex-wrap:wrap;padding:8px 8px 8px 14px}.hexa-search .sd-cmd-k{display:none}.hexa-search .sd-cmd-go{margin-left:auto}.hexa-search-overlay{padding:10vh 12px 12px}.hexa-search-overlay .sd-ov-bar{padding:16px}}
@media(prefers-reduced-motion:reduce){.hexa-search *{transition:none!important}}
</style>
<script id="hexa-search-display-js">
(function(){
  if(window.__hexaSearchDisplayReady)return;
  window.__hexaSearchDisplayReady=true;
  var activeDialog=null;
  var activeTrigger=null;
  function dialogFor(trigger){var id=trigger&&trigger.getAttribute('data-sd-open');return id?document.getElementById(id):null;}
  function openDialog(trigger){var dialog=dialogFor(trigger);if(!dialog)return;activeDialog=dialog;activeTrigger=trigger;dialog.hidden=false;trigger.setAttribute('aria-expanded','true');document.documentElement.classList.add('hexa-search-overlay-open');var input=dialog.querySelector('.sd-input');if(input)window.setTimeout(function(){input.focus();},50);}
  function closeDialog(){if(!activeDialog)return;activeDialog.hidden=true;if(activeTrigger){activeTrigger.setAttribute('aria-expanded','false');activeTrigger.focus();}document.documentElement.classList.remove('hexa-search-overlay-open');activeDialog=null;activeTrigger=null;}
  function closeReveal(widget){var button=widget.querySelector('[data-sd-toggle]');var input=widget.querySelector('.sd-input');widget.classList.remove('is-open');if(button)button.setAttribute('aria-expanded','false');if(input)input.setAttribute('tabindex','-1');}
  function openReveal(widget){var button=widget.querySelector('[data-sd-toggle]');var input=widget.querySelector('.sd-input');widget.classList.add('is-open');if(button)button.setAttribute('aria-expanded','true');if(input){input.removeAttribute('tabindex');window.setTimeout(function(){input.focus();},120);}}
  document.addEventListener('click',function(event){
    var open=event.target.closest('[data-sd-open]');if(open){event.preventDefault();openDialog(open);return;}
    var close=event.target.closest('[data-sd-close]');if(close){event.preventDefault();closeDialog();return;}
    var toggle=event.target.closest('[data-sd-toggle]');
    if(toggle){var widget=toggle.closest('.hexa-search');if(!widget)return;var input=widget.querySelector('.sd-input');if(widget.classList.contains('is-open')){if(input&&input.value.trim()){widget.querySelector('form').requestSubmit();}else{closeReveal(widget);}}else{openReveal(widget);}return;}
    if(activeDialog&&event.target===activeDialog){closeDialog();return;}
    document.querySelectorAll('.hexa-search--icon-reveal.is-open').forEach(function(widget){if(!widget.contains(event.target)){var input=widget.querySelector('.sd-input');if(!input||!input.value.trim())closeReveal(widget);}});
  });
  document.addEventListener('keydown',function(event){
    if((event.key==='k'||event.key==='K')&&(event.metaKey||event.ctrlKey)){var trigger=document.querySelector('[data-sd-open]');if(trigger){event.preventDefault();activeDialog?closeDialog():openDialog(trigger);}return;}
    if(event.key!=='Escape')return;
    if(activeDialog)closeDialog();
    document.querySelectorAll('.hexa-search--icon-reveal.is-open').forEach(function(widget){var input=widget.querySelector('.sd-input');if(!input||!input.value.trim())closeReveal(widget);});
  });
  document.addEventListener('hexa-core-host-tab-before-load',function(){if(activeDialog)closeDialog();});
})();
</script>
HTML;
    }

    private static function sanitize_color( string $value ): string {
        $value = trim( $value );
        if ( '' === $value ) {
            return '';
        }
        if ( preg_match( '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value ) ) {
            return $value;
        }

        $named = [
            'ink'     => '#1b2230',
            'blue'    => '#2f6df6',
            'crimson' => '#d3324a',
            'forest'  => '#1f8a5b',
            'amber'   => '#b9761b',
        ];

        return $named[ strtolower( $value ) ] ?? '';
    }

    private static function sanitize_length( string $value ): string {
        $value = trim( $value );
        if ( '' === $value || ! preg_match( '/^\d{1,3}(?:px|rem|em|%)?$/', $value ) ) {
            return '';
        }

        return ctype_digit( $value ) ? $value . 'px' : $value;
    }
}
