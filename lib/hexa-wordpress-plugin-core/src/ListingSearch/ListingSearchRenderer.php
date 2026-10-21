<?php

namespace Hexa\PluginCore\ListingSearch;

use Hexa\PluginCore\PublicComponents\PublicComponent;
use Hexa\PluginCore\QueryFilter\QueryFilterSet;
use Hexa\PluginCore\QueryFilter\QuerySql;

/**
 * The listing search box: a text field, the profile's filters with the number
 * of results behind each option, a clear link and a result line.
 *
 * It is an ordinary GET form for the page's own address, so it works without
 * JavaScript and every state is a shareable link. With JavaScript, typing
 * and ticking reload the same address in the background and swap in the new
 * listing (every element matching the profile's target, by default
 * `.hexa-listing-results`) and the new filter counts; Elementor widgets in
 * the swapped listing are started again, so Load more keeps working. The
 * markup inherits the site's fonts and colours; classes start with `hls-`
 * and custom properties (`--hls-accent`, `--hls-border`, `--hls-radius`,
 * `--hls-panel-bg`, `--hls-field-height`) restyle it.
 */
final class ListingSearchRenderer {
    private static bool $assets_printed = false;

    /** @var string[] Profiles rendered on this page, so a host can skip adding a second box. */
    private static array $rendered = [];

    /**
     * @param array<string,mixed> $options placeholder, target, class, hidden (extra hidden fields),
     *                                    filters (keys to show; all by default), results_one, results_many, empty, clear.
     */
    public function render( string $profile_id, array $options = [] ): string {
        $state = ListingSearch::state( $profile_id );
        if ( null === $state ) {
            return '';
        }

        $profile = $state['profile'];
        $labels  = array_merge( $profile['labels'], array_filter( array_intersect_key( $options, array_flip( [ 'results_one', 'results_many', 'empty', 'clear' ] ) ), static fn( $value ): bool => is_string( $value ) && '' !== trim( $value ) ) );
        $context = $state['context'];
        $search  = 'search' === $context['type'];
        $target  = ListingSearchProfile::selector( (string) ( $options['target'] ?? $profile['target'] ) );
        $text_name = $search ? 's' : ListingSearch::PARAM_TEXT;

        $placeholder = trim( (string) ( $options['placeholder'] ?? '' ) );
        if ( '' === $placeholder ) {
            $placeholder = '' !== $context['title'] ? sprintf( $labels['placeholder_context'], $context['title'] ) : $labels['placeholder'];
        }

        $shown = array_filter( array_map( 'trim', is_array( $options['filters'] ?? null ) ? $options['filters'] : explode( ',', (string) ( $options['filters'] ?? '' ) ) ) );
        $shown_filters = [] !== $shown ? array_intersect_key( $state['filters'], array_flip( $shown ) ) : $state['filters'];

        global $wpdb;
        $counts = [];
        $total  = 0;
        $active = $state['active'] || ( $search && '' !== $state['text'] );
        if ( is_object( $wpdb ) ) {
            $counts = QueryFilterSet::counts( $wpdb, $shown_filters, $state['values'], $state['scope'], static fn( array $values ): string => ListingSearch::ids_sql( $state, $values ) );
            if ( $active ) {
                $total = QuerySql::count( $wpdb, ListingSearch::ids_sql( $state, $state['values'] ) );
            }
        }

        $base = PublicComponent::sanitize_base( self::page_url(), [ ListingSearch::PARAM_PROFILE, ListingSearch::PARAM_TEXT, ListingSearch::PARAM_FILTER, 's', 'paged' ] );
        [ $base_path, $base_args ] = PublicComponent::split_base( $base );
        $hidden = array_merge( $base_args, array_filter( (array) ( $options['hidden'] ?? [] ), 'is_scalar' ) );
        unset( $hidden[ ListingSearch::QUERY_VAR ] );

        $dom_id = 'hls-' . $profile['id'];
        $status = $active ? ( 0 === $total ? $labels['empty'] : sprintf( 1 === $total ? $labels['results_one'] : $labels['results_many'], $total ) ) : '';

        $html = '<form class="' . esc_attr( trim( 'hls ' . $profile['class'] . ' ' . (string) ( $options['class'] ?? '' ) ) ) . '" id="' . esc_attr( $dom_id ) . '" role="search" method="get" action="' . esc_url( $base_path ) . '"'
            . ' data-hls-target="' . esc_attr( $target ) . '" data-hls-min="' . esc_attr( (string) $profile['min_chars'] ) . '" data-hls-error="' . esc_attr( $labels['error'] ) . '">'
            . PublicComponent::hidden_inputs( array_map( 'strval', $hidden ) )
            . '<input type="hidden" name="' . esc_attr( ListingSearch::PARAM_PROFILE ) . '" value="' . esc_attr( $profile['id'] ) . '">'
            . '<div class="hls-bar">'
            . '<label class="hls-field hls-field--text"><span class="hls-visually-hidden">' . esc_html( $labels['search'] ) . '</span>'
            . '<svg class="hls-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="M20 20l-3.5-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
            . '<input class="hls-input" type="search" name="' . esc_attr( $text_name ) . '" value="' . esc_attr( $state['text'] ) . '" placeholder="' . esc_attr( $placeholder ) . '" autocomplete="off" enterkeyhint="search"></label>'
            . '<div class="hls-filters">' . QueryFilterSet::controls( $shown_filters, $state['values'], ListingSearch::PARAM_FILTER, 'hls', $state['scope'], $counts ) . '</div>'
            . '<a class="hls-clear" href="' . esc_url( $base . ( $search ? ( str_contains( $base, '?' ) ? '&' : '?' ) . 's=' : '' ) ) . '"' . ( $active ? '' : ' hidden' ) . '>' . esc_html( $labels['clear'] ) . '</a>'
            . '<button class="hls-submit hls-visually-hidden" type="submit">' . esc_html( $labels['search'] ) . '</button>'
            . '</div>'
            . '<p class="hls-status" role="status" aria-live="polite"' . ( '' === $status ? ' hidden' : '' ) . '>' . esc_html( $status ) . '</p>'
            . '</form>';

        self::$rendered[] = $profile['id'];

        // Visitor text is echoed back; keep it inert to a later do_shortcode() pass.
        return $this->assets() . PublicComponent::inert( $html );
    }

    /** Whether a profile's box was already printed on this page. */
    public static function rendered( string $profile_id ): bool {
        return in_array( $profile_id, self::$rendered, true );
    }

    /** The current page's address without its page number. */
    private static function page_url(): string {
        $uri  = PublicComponent::request_uri();
        $path = (string) parse_url( $uri, PHP_URL_PATH );
        $path = (string) preg_replace( '#/page/\d+/?$#', '/', $path );
        $query = (string) parse_url( $uri, PHP_URL_QUERY );

        return $path . ( '' !== $query ? '?' . $query : '' );
    }

    private function assets(): string {
        if ( self::$assets_printed ) {
            return '';
        }
        self::$assets_printed = true;

        return '<style id="hexa-listing-search-css">' . self::css() . '</style><script id="hexa-listing-search-js">' . self::js() . '</script>';
    }

    public static function css(): string {
        return ':where(.hls){--hls-accent:currentColor;--hls-border:rgba(127,127,127,.35);--hls-radius:8px;--hls-panel-bg:Canvas;--hls-field-height:44px;--hls-muted:rgba(127,127,127,1)}'
            . '.hls{margin:0 0 24px;font:inherit;color:inherit}'
            . '.hls-bar{display:flex;flex-wrap:wrap;align-items:center;gap:10px}'
            . '.hls-field--text{position:relative;flex:1 1 260px;min-width:0;margin:0}'
            . '.hls-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);opacity:.6;pointer-events:none}'
            . '.hls .hls-input{box-sizing:border-box;width:100%;height:var(--hls-field-height);margin:0;padding:0 14px 0 42px;border:1px solid var(--hls-border);border-radius:var(--hls-radius);background:transparent;color:inherit;font:inherit;font-size:15px}'
            . '.hls .hls-input:focus{outline:none;border-color:var(--hls-accent)}'
            . '.hls-filters{display:contents}'
            . '.hls-filter{position:relative}'
            . '.hls-filter-toggle{display:flex;align-items:center;gap:8px;height:var(--hls-field-height);padding:0 14px;border:1px solid var(--hls-border);border-radius:var(--hls-radius);cursor:pointer;list-style:none;font-size:14px;white-space:nowrap;transition:border-color .2s}'
            . '.hls-filter-toggle::-webkit-details-marker{display:none}'
            . '.hls-filter-toggle::after{content:"";width:6px;height:6px;margin-left:2px;border-right:1.5px solid currentColor;border-bottom:1.5px solid currentColor;transform:translateY(-2px) rotate(45deg);opacity:.6}'
            . '.hls-filter[open] .hls-filter-toggle,.hls-filter.is-active .hls-filter-toggle,.hls-filter-toggle:hover{border-color:var(--hls-accent)}'
            . '.hls-filter-badge{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;border-radius:9px;background:var(--hls-accent);font-size:11px;line-height:1}'
            . '.hls-filter-badge{color:Canvas}'
            . '.hls-filter-panel{position:absolute;z-index:50;top:calc(100% + 6px);left:0;min-width:220px;max-width:min(320px,90vw);max-height:320px;overflow:auto;padding:6px;border:1px solid var(--hls-border);border-radius:var(--hls-radius);background:var(--hls-panel-bg);box-shadow:0 12px 32px rgba(0,0,0,.14)}'
            . '.hls-option{display:flex;align-items:center;gap:10px;min-height:38px;padding:0 8px 0 calc(8px + var(--hls-depth,0) * 16px);border-radius:6px;cursor:pointer;font-size:14px}'
            . '.hls-option:hover{background:rgba(127,127,127,.1)}'
            . '.hls-option input{width:16px;height:16px;margin:0;accent-color:var(--hls-accent)}'
            . '.hls-option-label{flex:1}.hls-count{font-size:12px;opacity:.6}'
            . '.hls .hls-select{height:var(--hls-field-height);padding:0 12px;border:1px solid var(--hls-border);border-radius:var(--hls-radius);background:transparent;color:inherit;font:inherit;font-size:14px}'
            . '.hls-field{margin:0}.hls-label{display:none}'
            . '.hls-clear{font-size:14px;color:inherit;text-decoration:underline;text-underline-offset:3px;opacity:.75}.hls-clear:hover{opacity:1}.hls-clear[hidden]{display:none}'
            . '.hls-status{margin:12px 0 0;font-size:14px;opacity:.75}.hls-status[hidden]{display:none}'
            . '.hls-visually-hidden{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}'
            . '.hexa-listing-results{transition:opacity .2s}.hexa-listing-results.hls-loading{opacity:.45;pointer-events:none}'
            . '@media(max-width:767px){.hls-field--text{flex-basis:100%}.hls-filter-panel{max-width:calc(100vw - 32px)}}'
            . '@media(prefers-reduced-motion:reduce){.hexa-listing-results,.hls-filter-toggle{transition:none}}';
    }

    public static function js(): string {
        return <<<'JS'
(function(){
function init(form){
if(form.getAttribute('data-hls-ready'))return;form.setAttribute('data-hls-ready','1');
if(!window.fetch||!window.DOMParser||!window.URLSearchParams)return;
var sel=form.getAttribute('data-hls-target')||'.hexa-listing-results',min=parseInt(form.getAttribute('data-hls-min')||'2',10);
var text=form.querySelector('.hls-input'),timer=null,controller=null,seq=0,last=location.pathname+location.search;
function url(){var p=new URLSearchParams(),on=false;Array.prototype.forEach.call(form.elements,function(el){if(!el.name||el.disabled||el.type==='submit')return;if((el.type==='checkbox'||el.type==='radio')&&!el.checked)return;var v=(el.value||'').trim();if(v!==''||el.name==='s'){p.append(el.name,v);if(el.type!=='hidden'&&v!=='')on=true;}});if(!on)p.delete('hls');var a=form.getAttribute('action')||location.pathname,q=p.toString();return a+(q?(a.indexOf('?')>-1?'&':'?')+q:'');}
function busy(on){form.setAttribute('aria-busy',on?'true':'false');Array.prototype.forEach.call(document.querySelectorAll(sel),function(t){t.classList.toggle('hls-loading',on);});}
function restart(el){if(!window.jQuery||!window.elementorFrontend||!elementorFrontend.elementsHandler)return;var $=window.jQuery;$(el).find('.elementor-element').addBack('.elementor-element').each(function(){try{elementorFrontend.elementsHandler.runReadyTrigger($(this));}catch(e){}});}
function go(){var u=url();if(u===last)return;last=u;var id=++seq;if(controller)controller.abort();controller=window.AbortController?new AbortController():null;busy(true);
var current=document.querySelectorAll(sel);if(!current.length){location.assign(u);return;}
fetch(u,{credentials:'same-origin',headers:{'Accept':'text/html'},signal:controller?controller.signal:undefined}).then(function(r){if(!r.ok&&r.status!==404)throw new Error('HTTP '+r.status);return r.text();}).then(function(html){if(id!==seq)return;var doc=new DOMParser().parseFromString(html,'text/html');
var fresh=doc.querySelectorAll(sel);Array.prototype.forEach.call(current,function(t,i){var n=fresh[i];if(n){var m=document.importNode(n,true);t.parentNode.replaceChild(m,t);restart(m);}else{t.innerHTML='';}});
var nf=doc.getElementById(form.id);if(nf){var box=form.querySelector('.hls-filters'),nb=nf.querySelector('.hls-filters');if(box&&nb){var open=[];Array.prototype.forEach.call(box.querySelectorAll('details[open]'),function(d){open.push(d.getAttribute('data-hls-key'));});box.innerHTML=nb.innerHTML;Array.prototype.forEach.call(box.querySelectorAll('details'),function(d){if(open.indexOf(d.getAttribute('data-hls-key'))>-1)d.open=true;});}
['.hls-status','.hls-clear'].forEach(function(s){var a=form.querySelector(s),b=nf.querySelector(s);if(a&&b){a.textContent=b.textContent;if(b.hasAttribute('hidden'))a.setAttribute('hidden','');else a.removeAttribute('hidden');}});}
if(window.history&&history.replaceState)history.replaceState(history.state,'',u);
document.dispatchEvent(new CustomEvent('hexa-listing:updated',{detail:{form:form,url:u}}));
}).catch(function(e){if(e&&e.name==='AbortError')return;if(id!==seq)return;last=null;var s=form.querySelector('.hls-status');if(s){s.textContent=form.getAttribute('data-hls-error')||'';s.removeAttribute('hidden');}}).then(function(){if(id===seq)busy(false);});}
form.addEventListener('submit',function(e){e.preventDefault();window.clearTimeout(timer);go();});
if(text){text.addEventListener('input',function(){window.clearTimeout(timer);var v=text.value.trim();if(v&&v.length<min)return;timer=window.setTimeout(go,350);});}
form.addEventListener('change',function(e){if(e.target===text)return;window.clearTimeout(timer);go();});
var clear=form.querySelector('.hls-clear');if(clear){clear.addEventListener('click',function(e){e.preventDefault();if(text)text.value='';Array.prototype.forEach.call(form.querySelectorAll('.hls-filters input,.hls-filters select'),function(el){if(el.type==='checkbox')el.checked=false;else el.value='';});Array.prototype.forEach.call(form.querySelectorAll('details[open]'),function(d){d.open=false;});go();});}
document.addEventListener('click',function(e){Array.prototype.forEach.call(form.querySelectorAll('details[open]'),function(d){if(!d.contains(e.target))d.open=false;});});
form.addEventListener('keydown',function(e){if(e.key==='Escape'){var d=e.target.closest?e.target.closest('details'):null;if(d){d.open=false;var s=d.querySelector('summary');if(s)s.focus();}}});
}
function boot(){Array.prototype.forEach.call(document.querySelectorAll('form.hls'),init);}
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',boot);}else{boot();}
})();
JS;
    }
}
