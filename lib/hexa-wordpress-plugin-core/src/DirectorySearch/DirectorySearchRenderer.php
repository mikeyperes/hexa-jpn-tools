<?php

namespace Hexa\PluginCore\DirectorySearch;

use Hexa\PluginCore\PublicComponents\PublicComponent;
use Hexa\PluginCore\QueryFilter\QueryFilterSet;

/**
 * Renders a directory search component and its result fragments.
 *
 * The first page is always rendered on the server, so the directory works
 * without JavaScript, pagination links are crawlable, and filtered URLs are
 * shareable. When JavaScript runs, the same markup upgrades to live search
 * against the REST endpoint.
 */
final class DirectorySearchRenderer {
    public const REST_NAMESPACE = PublicComponent::REST_NAMESPACE;

    /** Hidden field naming the directory that owns the URL state. */
    public const PARAM_DIRECTORY = 'hds';

    /** Former owner parameter; still honored, but common web firewalls (ModSecurity/Imunify360) block `dir=`. */
    public const PARAM_DIRECTORY_LEGACY = 'dir';

    private static bool $assets_printed = false;

    /** @param array<string,mixed>|null $input Raw, unslashed request parameters; defaults to $_GET. */
    public function render( string $profile_id, ?array $input = null ): string {
        $profile = DirectorySearchRegistry::get( $profile_id );
        if ( null === $profile ) {
            return '';
        }
        if ( ! PublicComponent::can_view( $profile['public'] ) ) {
            return '';
        }

        $input = PublicComponent::input( $input );
        $owner = $input[ self::PARAM_DIRECTORY ] ?? ( $input[ self::PARAM_DIRECTORY_LEGACY ] ?? null );
        if ( null !== $owner && PublicComponent::scalar( $owner ) !== $profile['id'] ) {
            $input = []; // URL state belongs to another directory on this page.
        }

        $request = DirectorySearchRequest::from_input( $input, $profile );
        $base    = self::sanitize_base( PublicComponent::request_uri() );
        $payload = $this->results( $profile, $request, $base );
        $dom_id  = 'hds-' . $profile['id'];
        $labels  = $profile['labels'];

        [ $base_path, $base_args ] = PublicComponent::split_base( $base );

        $html = '<div class="hds ' . esc_attr( $profile['class'] ) . '" id="' . esc_attr( $dom_id ) . '"'
            . PublicComponent::live_attributes( 'hds', 'directory/' . $profile['id'], $profile['public'] )
            . ' data-hds-base="' . esc_attr( $base ) . '"'
            . ' data-hds-min="' . esc_attr( (string) $profile['min_chars'] ) . '"'
            . ' data-hds-min-label="' . esc_attr( sprintf( $labels['min_chars'], $profile['min_chars'] ) ) . '"'
            . ' data-hds-error-label="' . esc_attr( $labels['error'] ) . '">';
        $html .= '<form class="hds-form" role="search" method="get" action="' . esc_url( $base_path ) . '">';
        $html .= PublicComponent::hidden_inputs( $base_args );
        $html .= '<input type="hidden" name="' . esc_attr( self::PARAM_DIRECTORY ) . '" value="' . esc_attr( $profile['id'] ) . '">';
        $html .= '<label class="hds-field hds-field--q"><span class="hds-label">' . esc_html( $labels['search'] ) . '</span>'
            . '<input class="hds-input" type="search" name="' . esc_attr( DirectorySearchRequest::PARAM_QUERY ) . '" value="' . esc_attr( $request['q'] ) . '"'
            . ' placeholder="' . esc_attr( $labels['placeholder'] ) . '" autocomplete="off" aria-controls="' . esc_attr( $dom_id ) . '-results"></label>';

        $html .= QueryFilterSet::controls( $profile['filters'], $request['filters'], DirectorySearchRequest::PARAM_FILTER, 'hds', QueryFilterSet::scope( 'directory', $profile ) );

        if ( count( $profile['sorts'] ) > 1 ) {
            $html .= '<label class="hds-field"><span class="hds-label">' . esc_html( $labels['sort'] ) . '</span><select class="hds-select" name="' . esc_attr( DirectorySearchRequest::PARAM_SORT ) . '" data-hds-default="' . esc_attr( (string) $profile['default_sort'] ) . '">';
            foreach ( $profile['sorts'] as $key => $sort ) {
                $html .= '<option value="' . esc_attr( $key ) . '"' . ( $key === $request['sort'] ? ' selected' : '' ) . '>' . esc_html( $sort['label'] ) . '</option>';
            }
            $html .= '</select></label>';
        }

        $html .= '<button class="hds-submit" type="submit">' . esc_html( $labels['submit'] ) . '</button></form>';
        $html .= '<p class="hds-summary" role="status" aria-live="polite">' . esc_html( $payload['summary'] ) . '</p>';
        $html .= '<div class="hds-results" id="' . esc_attr( $dom_id ) . '-results">' . $payload['html'] . '</div>';
        $html .= '</div>';

        // Visitor text is echoed back; make it inert to a later do_shortcode() pass (for example Elementor's the_content).
        return $this->assets() . PublicComponent::inert( $html );
    }

    /**
     * One results fragment plus metadata; shared by the shortcode and REST endpoint.
     *
     * Only default views (no search text, no filters, an in-range page) are
     * cached, so visitors cannot grow the cache with arbitrary parameters.
     * Pagination and summary are rebuilt per request from the cached result.
     *
     * @param array<string,mixed> $profile
     * @param array{q:string,page:int,sort:string,filters:array<string,string>} $request
     * @return array{html:string,summary:string,total:int,page:int,pages:int}
     */
    public function results( array $profile, array $request, string $base ): array {
        $cache_key = '';
        if ( $profile['cache_ttl'] > 0 && '' === $request['q'] && [] === $request['filters'] && function_exists( 'get_transient' ) ) {
            $cache_key = 'hds_' . md5( (string) wp_json_encode( [ $profile['id'], $profile['cache_version'], $request['sort'], $request['page'] ] ) );
            $cached = get_transient( $cache_key );
            if ( is_array( $cached ) && isset( $cached['result'], $cached['items'] ) && is_array( $cached['result'] ) ) {
                return $this->payload( $profile, $request, $cached['result'], (string) $cached['items'], $base );
            }
        }

        $result = ( new DirectorySearchQuery() )->run( $profile, $request );
        $data   = [];
        if ( [] !== $result['ids'] && null !== $profile['prepare'] ) {
            $data = (array) call_user_func( $profile['prepare'], $result['ids'], $request );
        }

        if ( [] === $result['ids'] ) {
            $items = '<p class="hds-empty">' . esc_html( $profile['labels']['empty'] ) . '</p>';
        } else {
            $items = '<ol class="hds-list" start="' . esc_attr( (string) ( ( $result['page'] - 1 ) * $result['per_page'] + 1 ) ) . '">';
            foreach ( $result['ids'] as $id ) {
                $items .= '<li class="hds-item">' . (string) call_user_func( $profile['render_item'], $id, (array) ( $data[ $id ] ?? [] ), $request ) . '</li>';
            }
            $items .= '</ol>';
        }

        if ( '' !== $cache_key && $result['page'] === $request['page'] ) {
            set_transient( $cache_key, [ 'result' => $result, 'items' => $items ], $profile['cache_ttl'] );
        }

        return $this->payload( $profile, $request, $result, $items, $base );
    }

    public function endpoint( string $profile_id ): string {
        return PublicComponent::endpoint( 'directory/' . $profile_id );
    }

    /**
     * Normalizes a URL or request URI to a same-site root-relative path plus the
     * page's own query arguments (directory parameters removed).
     */
    public static function sanitize_base( string $url ): string {
        return PublicComponent::sanitize_base( $url, [ DirectorySearchRequest::PARAM_QUERY, DirectorySearchRequest::PARAM_PAGE, DirectorySearchRequest::PARAM_SORT, DirectorySearchRequest::PARAM_FILTER, self::PARAM_DIRECTORY, self::PARAM_DIRECTORY_LEGACY ] );
    }

    /** @return array{html:string,summary:string,total:int,page:int,pages:int} */
    private function payload( array $profile, array $request, array $result, string $items, string $base ): array {
        return [
            'html'    => $items . ( [] !== $result['ids'] ? $this->pagination( $profile, $request, $result, $base ) : '' ),
            'summary' => $this->summary( $profile, $request, $result ),
            'total'   => (int) $result['total'],
            'page'    => (int) $result['page'],
            'pages'   => (int) $result['pages'],
        ];
    }

    private function summary( array $profile, array $request, array $result ): string {
        $labels = $profile['labels'];
        $text   = sprintf( 1 === $result['total'] ? $labels['results_one'] : $labels['results_many'], $result['total'] );
        $length = function_exists( 'mb_strlen' ) ? mb_strlen( $request['q'], 'UTF-8' ) : strlen( $request['q'] );
        if ( $result['searched'] ) {
            $text .= sprintf( $labels['results_for'], $request['q'] );
        } elseif ( '' !== $request['q'] && $length < (int) $profile['min_chars'] ) {
            $text .= ' · ' . sprintf( $labels['min_chars'], $profile['min_chars'] );
        }

        return $text;
    }

    private function pagination( array $profile, array $request, array $result, string $base ): string {
        if ( $result['pages'] <= 1 ) {
            return '';
        }

        $current = (int) $result['page'];
        $pages   = (int) $result['pages'];
        $window  = array_unique( array_filter( [ 1, $current - 2, $current - 1, $current, $current + 1, $current + 2, $pages ], static fn( int $page ): bool => $page >= 1 && $page <= $pages ) );
        sort( $window );

        $html = '<nav class="hds-pagination" aria-label="' . esc_attr( $profile['labels']['pagination'] ) . '">';
        if ( $current > 1 ) {
            $html .= $this->page_link( $profile, $request, $base, $current - 1, $profile['labels']['previous'], 'prev' );
        }
        $previous = 0;
        foreach ( $window as $page ) {
            if ( $previous && $page > $previous + 1 ) {
                $html .= '<span class="hds-gap" aria-hidden="true">…</span>';
            }
            $html .= $page === $current
                ? '<span class="hds-page" aria-current="page">' . esc_html( (string) $page ) . '</span>'
                : $this->page_link( $profile, $request, $base, $page, (string) $page, '' );
            $previous = $page;
        }
        if ( $current < $pages ) {
            $html .= $this->page_link( $profile, $request, $base, $current + 1, $profile['labels']['next'], 'next' );
        }

        return $html . '</nav>';
    }

    private function page_link( array $profile, array $request, string $base, int $page, string $label, string $rel ): string {
        $url = PublicComponent::url( $base, array_merge( [ self::PARAM_DIRECTORY => $profile['id'] ], DirectorySearchRequest::to_args( $request, $profile, $page ) ) );

        return '<a class="hds-page" href="' . esc_url( $url ) . '" data-hds-page="' . esc_attr( (string) $page ) . '"'
            . ( '' !== $rel ? ' rel="' . esc_attr( $rel ) . '"' : '' ) . '>' . esc_html( $label ) . '</a>';
    }

    private function assets(): string {
        if ( self::$assets_printed ) {
            return '';
        }
        self::$assets_printed = true;

        return '<style id="hexa-directory-search-css">' . self::css() . '</style><script id="hexa-directory-search-js">' . self::js() . '</script>';
    }

    public static function css(): string {
        // :where() keeps only the custom-property defaults at zero specificity; structural rules are class selectors, so hosts scope overrides with the profile class.
        return ':where(.hds){--hds-muted:#6b7280;--hds-border:rgba(127,127,127,.3);--hds-field-bg:transparent;--hds-accent:#2563eb;--hds-accent-fg:#fff;--hds-radius:6px;--hds-gap:12px}'
            . '.hds-form{display:grid;grid-template-columns:minmax(220px,2fr) repeat(auto-fit,minmax(150px,1fr));gap:var(--hds-gap);align-items:end}'
            . '.hds-field{display:flex;flex-direction:column;gap:6px;min-width:0;margin:0}'
            . '.hds-label{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--hds-muted)}'
            . '.hds-input,.hds-select{box-sizing:border-box;width:100%;min-height:44px;padding:0 12px;border:1px solid var(--hds-border);border-radius:var(--hds-radius);background:var(--hds-field-bg);color:inherit;font:inherit}'
            . '.hds-toggle{display:flex;align-items:center;gap:8px;min-height:44px;margin:0;cursor:pointer}'
            . '.hds-toggle input{width:18px;height:18px;accent-color:var(--hds-accent)}'
            . '.hds-range-inputs{display:flex;align-items:center;gap:6px}.hds-range-inputs .hds-input{min-width:0}'
            . '.hds-submit{min-height:44px;padding:0 20px;border:0;border-radius:var(--hds-radius);background:var(--hds-accent);color:var(--hds-accent-fg);font:inherit;font-weight:700;cursor:pointer}'
            . '.hds-summary{margin:18px 0 12px;color:var(--hds-muted);font-size:14px}'
            . '.hds-list{list-style:none;margin:0;padding:0;display:grid;gap:var(--hds-gap)}'
            . '.hds-item{margin:0}'
            . '.hds-results{transition:opacity .15s}.hds-results.is-loading{opacity:.5}'
            . '.hds-pagination{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-top:24px}'
            . '.hds-page{display:inline-flex;align-items:center;justify-content:center;min-width:44px;min-height:44px;padding:0 12px;border:1px solid var(--hds-border);border-radius:var(--hds-radius);color:inherit;text-decoration:none}'
            . '.hds-page[aria-current=page]{background:var(--hds-accent);border-color:var(--hds-accent);color:var(--hds-accent-fg)}'
            . '.hds-gap{padding:0 4px;color:var(--hds-muted)}'
            . '.hds-empty{margin:0;padding:32px;border:1px dashed var(--hds-border);border-radius:var(--hds-radius);text-align:center;color:var(--hds-muted)}'
            . '.hds :focus-visible{outline:2px solid var(--hds-accent);outline-offset:2px}'
            . '@media(max-width:700px){.hds-form{grid-template-columns:1fr 1fr}.hds-field--q,.hds-submit{grid-column:1/-1}}'
            . '@media(prefers-reduced-motion:reduce){.hds-results{transition:none}}';
    }

    public static function js(): string {
        return <<<'JS'
(function(){
function init(root){
if(root.getAttribute('data-hds-ready'))return;root.setAttribute('data-hds-ready','1');
var form=root.querySelector('.hds-form'),results=root.querySelector('.hds-results'),summary=root.querySelector('.hds-summary');
if(!form||!results||!window.fetch||!window.URLSearchParams)return;
var input=form.querySelector('[name="dq"]'),endpoint=root.getAttribute('data-hds-endpoint'),base=root.getAttribute('data-hds-base')||location.pathname;
try{var eu=new URL(endpoint,location.href);if(eu.origin!==location.origin||eu.href.indexOf('hexa-plugin-core/v1/directory/')<0)return;}catch(e){return;}
var nonce=root.getAttribute('data-hds-nonce'),min=parseInt(root.getAttribute('data-hds-min')||'2',10),timer=null,controller=null,lastKey=null,seq=0;
var reduced=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
function params(page){var p=new URLSearchParams();Array.prototype.forEach.call(form.elements,function(el){if(!el.name||el.disabled)return;if((el.type==='checkbox'||el.type==='radio')&&!el.checked)return;var v=(el.value||'').trim();if(v!=='')p.append(el.name,v);});if(page>1)p.set('dpage',String(page));return p;}
function pageUrl(p){var q=base.indexOf('?'),path=q>-1?base.slice(0,q):base,own=new URLSearchParams(q>-1?base.slice(q+1):'');p.forEach(function(v,k){if(!own.has(k))own.append(k,v);});var s=own.toString();return path+(s?'?'+s:'');}
function run(page,push,typed){var q=input?(input.value||'').trim():'';
if(typed&&q&&q.length<min){if(controller)controller.abort();seq++;lastKey=null;root.removeAttribute('aria-busy');results.classList.remove('is-loading');summary.textContent=root.getAttribute('data-hds-min-label')||'';return;}
var p=params(page),key=p.toString();if(key===lastKey&&!push)return;lastKey=key;var id=++seq;
if(controller)controller.abort();controller=window.AbortController?new AbortController():null;
root.setAttribute('aria-busy','true');results.classList.add('is-loading');
var rp=new URLSearchParams(key);rp.set('base',base);var headers={'Accept':'application/json'};if(nonce)headers['X-WP-Nonce']=nonce;
fetch(endpoint+(endpoint.indexOf('?')>-1?'&':'?')+rp.toString(),{credentials:nonce?'same-origin':'omit',headers:headers,signal:controller?controller.signal:undefined}).then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json();}).then(function(d){if(id!==seq)return;results.innerHTML=d.html||'';summary.textContent=d.summary||'';
if(push!==null&&window.history&&history.replaceState){var u=pageUrl(p);if(push){history.pushState({hds:root.id},'',u);}else{history.replaceState({hds:root.id},'',u);}}
if(push){root.scrollIntoView({behavior:reduced?'auto':'smooth',block:'start'});}}).catch(function(e){if(id!==seq||(e&&e.name==='AbortError'))return;lastKey=null;summary.textContent=root.getAttribute('data-hds-error-label')||'';}).then(function(){if(id!==seq)return;root.removeAttribute('aria-busy');results.classList.remove('is-loading');});}
form.addEventListener('submit',function(e){e.preventDefault();window.clearTimeout(timer);run(1,false,false);});
if(input){input.addEventListener('input',function(){window.clearTimeout(timer);timer=window.setTimeout(function(){run(1,false,true);},300);});}
form.addEventListener('change',function(e){if(e.target&&e.target!==input){window.clearTimeout(timer);run(1,false,false);}});
results.addEventListener('click',function(e){var a=e.target&&e.target.closest?e.target.closest('a[data-hds-page]'):null;if(!a)return;e.preventDefault();run(parseInt(a.getAttribute('data-hds-page'),10)||1,true,false);});
window.addEventListener('popstate',function(){var sp=new URLSearchParams(location.search),owner=sp.get('hds')||sp.get('dir');if(owner&&('hds-'+owner)!==root.id){sp=new URLSearchParams();}Array.prototype.forEach.call(form.elements,function(el){if(!el.name||el.type==='hidden')return;if(el.type==='checkbox'){el.checked=sp.get(el.name)===el.value;}else if(el.tagName==='SELECT'||el.type==='search'||el.type==='text'||el.type==='date'){el.value=sp.get(el.name)||el.getAttribute('data-hds-default')||'';}});lastKey=null;run(parseInt(sp.get('dpage')||'1',10),null,false);});
}
function boot(){Array.prototype.forEach.call(document.querySelectorAll('.hds[data-hds-endpoint]'),init);}
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',boot);}else{boot();}
})();
JS;
    }
}
