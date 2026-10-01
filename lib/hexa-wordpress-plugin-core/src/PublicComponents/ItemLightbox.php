<?php

namespace Hexa\PluginCore\PublicComponents;

/**
 * The in-page dialog behind `link_behavior => 'lightbox'` (see ItemLink).
 *
 * Components register a profile resolver once; Core then serves
 * `GET /wp-json/hexa-plugin-core/v1/lightbox/{component}/{profile}/{post}`,
 * which returns the dialog body for one published, non-password post of a
 * type the profile allows, under the profile's own visibility. One small
 * script opens any `a[data-hlb]` on the page in a native <dialog> (focus
 * trap, Escape, backdrop click, browser Back closes it) and copies the
 * `--hlb-*` custom properties from the clicked component, so a page builder
 * styles the dialog where it styles the component.
 */
final class ItemLightbox {
    /** @var array<string,callable> component => fn( string $profile_id ): ?array */
    private static array $components = [];

    private static bool $hooked = false;

    private static bool $assets_printed = false;

    /** @param callable $resolve fn( string $profile_id ): ?array, the component's normalized profile. */
    public static function component( string $name, callable $resolve ): void {
        self::$components[ $name ] = $resolve;
        if ( ! self::$hooked && function_exists( 'add_action' ) ) {
            self::$hooked = true;
            add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
        }
    }

    public static function register_routes(): void {
        register_rest_route(
            PublicComponent::REST_NAMESPACE,
            '/lightbox/(?P<component>[a-z]+)/(?P<profile>[a-z0-9_\-]+)/(?P<id>\d+)',
            [
                'methods'             => 'GET',
                'callback'            => [ self::class, 'rest_item' ],
                'permission_callback' => [ self::class, 'rest_permission' ],
            ]
        );
    }

    /** @param \WP_REST_Request $request */
    public static function rest_permission( $request ): bool {
        $profile = self::profile( (string) $request['component'], (string) $request['profile'] );

        return null !== $profile && PublicComponent::can_view( $profile['public'] );
    }

    /** @param \WP_REST_Request $request */
    public static function rest_item( $request ) {
        $profile = self::profile( (string) $request['component'], (string) $request['profile'] );
        $id      = (int) $request['id'];
        if ( null === $profile || 'lightbox' !== $profile['link_behavior']['mode'] || ! self::eligible( $profile['link_behavior'], $id ) ) {
            return new \WP_Error( 'hexa_lightbox_not_found', 'This item cannot be shown here.', [ 'status' => 404 ] );
        }

        return PublicComponent::rest_response( self::item( $profile['link_behavior'], $id ), $profile['public'] );
    }

    /** The named component's normalized profile, or null for an unknown component or profile. */
    private static function profile( string $component, string $profile_id ): ?array {
        $resolve = self::$components[ $component ] ?? null;
        $profile = null !== $resolve ? call_user_func( $resolve, $profile_id ) : null;

        return is_array( $profile ) && isset( $profile['link_behavior'], $profile['public'] ) ? $profile : null;
    }

    /** Whether the dialog may show this post: published, not password-protected, of an allowed type. */
    public static function eligible( array $link, int $post_id ): bool {
        if ( $post_id <= 0 || [] === $link['post_types'] || ! function_exists( 'get_post' ) ) {
            return false;
        }
        $post = get_post( $post_id );

        return $post instanceof \WP_Post && 'publish' === $post->post_status && '' === (string) $post->post_password
            && in_array( $post->post_type, $link['post_types'], true );
    }

    /**
     * Dialog payload for one eligible post. The post is the global post while the host
     * renders, so template tags and post-aware shortcodes read it.
     *
     * @return array{html:string,title:string,url:string}
     */
    public static function item( array $link, int $post_id ): array {
        global $post;
        $previous = $post;
        $post     = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        setup_postdata( $post );
        try {
            $html = null !== $link['render'] ? (string) call_user_func( $link['render'], $post_id ) : self::preview( $post_id );
        } finally {
            $post = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
            if ( $previous instanceof \WP_Post ) {
                setup_postdata( $previous );
            }
        }

        return [
            'html'  => $html,
            'title' => html_entity_decode( wp_strip_all_tags( (string) get_the_title( $post_id ) ), ENT_QUOTES, 'UTF-8' ),
            'url'   => (string) get_permalink( $post_id ),
        ];
    }

    /** Default dialog body: featured image, title, and a short excerpt. */
    private static function preview( int $post_id ): string {
        $image   = has_post_thumbnail( $post_id ) ? (string) get_the_post_thumbnail( $post_id, 'large', [ 'class' => 'hlb-image', 'loading' => 'lazy' ] ) : '';
        $excerpt = wp_trim_words( wp_strip_all_tags( (string) get_the_excerpt( $post_id ) ), 60 );

        return $image . '<h2 class="hlb-title">' . esc_html( html_entity_decode( (string) get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ) ) . '</h2>'
            . ( '' !== $excerpt ? '<p class="hlb-excerpt">' . esc_html( $excerpt ) . '</p>' : '' );
    }

    /** The dialog's style and script, printed once per page, plus the host's assets for its render markup. */
    public static function assets( array $link ): string {
        if ( 'lightbox' !== $link['mode'] ) {
            return '';
        }
        if ( null !== $link['assets'] ) {
            call_user_func( $link['assets'] );
        }
        if ( self::$assets_printed ) {
            return '';
        }
        self::$assets_printed = true;

        return '<style id="hexa-lightbox-css">' . self::css() . '</style><script id="hexa-lightbox-js">' . self::js() . '</script>';
    }

    public static function css(): string {
        return '.hlb{--hlb-bg:#fff;--hlb-fg:#111827;--hlb-muted:#4b5563;--hlb-border:rgba(127,127,127,.28);--hlb-accent:#2563eb;--hlb-radius:12px;--hlb-width:720px;'
            . 'box-sizing:border-box;width:min(var(--hlb-width),calc(100vw - 32px));max-width:none;max-height:calc(100vh - 48px);max-height:calc(100dvh - 48px);margin:auto;padding:0;'
            . 'border:1px solid var(--hlb-border);border-radius:var(--hlb-radius);background:var(--hlb-bg);color:var(--hlb-fg);box-shadow:0 30px 80px rgba(0,0,0,.45);overflow:hidden}'
            . '.hlb[open]{display:flex;flex-direction:column}.hlb::backdrop{background:var(--hlb-backdrop,rgba(0,0,0,.72));backdrop-filter:blur(3px)}'
            . '.hlb-bar{display:flex;flex:none;align-items:center;gap:12px;padding:10px 10px 10px 20px;border-bottom:1px solid var(--hlb-border)}'
            . '.hlb-open{margin-right:auto;color:var(--hlb-accent);font-size:13px;font-weight:600;letter-spacing:.04em;text-decoration:none}.hlb-open:hover{text-decoration:underline}.hlb-open[hidden]{display:none}'
            . '.hlb-close{flex:none;display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;margin-left:auto;padding:0;border:1px solid var(--hlb-border);border-radius:999px;background:transparent;color:inherit;font:inherit;font-size:22px;line-height:1;cursor:pointer}'
            // Without the full-page link the bar holds only the close button: a slim strip without a divider.
            . '.hlb.is-bare .hlb-bar{padding:8px 8px 0;border:0}.hlb.is-bare .hlb-body{padding-top:4px}'
            . '.hlb-close:hover{border-color:var(--hlb-accent);color:var(--hlb-accent)}.hlb :focus-visible{outline:2px solid var(--hlb-accent);outline-offset:2px}'
            . '.hlb-body{flex:1 1 auto;min-height:0;overflow:auto;padding:20px;overscroll-behavior:contain;-webkit-overflow-scrolling:touch}.hlb-body.is-loading{opacity:.7}'
            . '.hlb-msg{margin:0;color:var(--hlb-muted)}.hlb-msg a{color:var(--hlb-accent)}'
            . '.hlb-image{display:block;width:100%;height:auto;max-height:50vh;margin:0 0 16px;object-fit:contain;border-radius:calc(var(--hlb-radius) - 4px)}'
            . '.hlb-title{margin:0 0 10px;color:inherit;font-size:22px;line-height:1.2}.hlb-excerpt{margin:0;color:var(--hlb-muted)}'
            . 'html.hlb-lock{overflow:hidden}'
            . '@media(max-width:600px){.hlb{width:100vw;max-height:92vh;max-height:92dvh;margin:auto 0 0;border-width:1px 0 0;border-radius:var(--hlb-radius) var(--hlb-radius) 0 0}.hlb-body{padding:16px}}'
            . '@media(prefers-reduced-motion:no-preference){.hlb[open]{animation:hlbIn .2s ease-out}}@keyframes hlbIn{from{opacity:0;transform:translateY(16px)}}';
    }

    public static function js(): string {
        return <<<'JS'
(function(){
if(window.hexaLightbox)return;window.hexaLightbox=1;
var TOKENS=['bg','fg','muted','border','accent','radius','width','backdrop'],cache={},dlg,body,full,close,trigger=null,seq=0,pushed=false;
function esc(s){return String(s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
function allowed(u){try{var x=new URL(u,location.href);return x.origin===location.origin&&x.href.indexOf('hexa-plugin-core/v1/lightbox/')>-1;}catch(e){return false;}}
function build(){dlg=document.createElement('dialog');dlg.className='hlb';
dlg.innerHTML='<div class="hlb-bar"><a class="hlb-open" href="#"></a><button type="button" class="hlb-close"><span aria-hidden="true">×</span></button></div><div class="hlb-body"></div>';
document.body.appendChild(dlg);body=dlg.querySelector('.hlb-body');full=dlg.querySelector('.hlb-open');close=dlg.querySelector('.hlb-close');
close.addEventListener('click',hide);
dlg.addEventListener('click',function(e){if(e.target===dlg)hide();});
dlg.addEventListener('cancel',function(e){e.preventDefault();hide();});
dlg.addEventListener('close',function(){document.documentElement.classList.remove('hlb-lock');body.innerHTML='';if(trigger&&trigger.isConnected&&trigger.focus)trigger.focus();});}
function hide(){if(!dlg||!dlg.open)return;if(pushed&&history.state&&history.state.hlb){pushed=false;history.back();return;}pushed=false;dlg.close();}
window.addEventListener('popstate',function(){if(dlg&&dlg.open){pushed=false;dlg.close();}});
function show(a){if(!dlg)build();
var root=a.closest('[data-hlb-labels]')||a,l={},cs=getComputedStyle(root),url=a.getAttribute('data-hlb'),href=a.href,n=++seq;
try{l=JSON.parse(root.getAttribute('data-hlb-labels')||'{}')||{};}catch(e){}
TOKENS.forEach(function(t){var v=cs.getPropertyValue('--hlb-'+t).trim();if(v){dlg.style.setProperty('--hlb-'+t,v);}else{dlg.style.removeProperty('--hlb-'+t);}});
close.setAttribute('aria-label',l.close||'Close');full.href=href;full.textContent=l.open||'';full.hidden=l.page===false||!l.open;dlg.classList.toggle('is-bare',full.hidden);
dlg.setAttribute('aria-label',(a.getAttribute('title')||a.textContent||'').trim().slice(0,120));
body.classList.add('is-loading');body.innerHTML='<p class="hlb-msg" role="status">'+esc(l.loading||'')+'</p>';
trigger=a;if(!dlg.open){dlg.showModal();document.documentElement.classList.add('hlb-lock');if(window.history&&history.pushState){history.pushState({hlb:1},'');pushed=true;}}
var nonce=root.getAttribute('data-hlb-nonce'),h={'Accept':'application/json'};if(nonce)h['X-WP-Nonce']=nonce;
if(!cache[url]){cache[url]=fetch(url,{credentials:nonce?'same-origin':'omit',headers:h}).then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json();});}
cache[url].then(function(d){if(n!==seq||!dlg.open)return;body.classList.remove('is-loading');body.innerHTML=d.html||'';body.scrollTop=0;if(d.title)dlg.setAttribute('aria-label',d.title);close.focus();})
.catch(function(){delete cache[url];if(n!==seq||!dlg.open)return;body.classList.remove('is-loading');body.innerHTML='<p class="hlb-msg">'+esc(l.error||'')+' <a href="'+esc(href)+'">'+esc(l.open||href)+'</a></p>';});}
document.addEventListener('click',function(e){var a=e.target&&e.target.closest?e.target.closest('a[data-hlb]'):null;
if(!a||e.defaultPrevented||e.button||e.metaKey||e.ctrlKey||e.shiftKey||e.altKey||!window.fetch||!window.HTMLDialogElement||!allowed(a.getAttribute('data-hlb')))return;
e.preventDefault();show(a);});
})();
JS;
    }
}
