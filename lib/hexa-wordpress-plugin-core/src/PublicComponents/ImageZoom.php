<?php

namespace Hexa\PluginCore\PublicComponents;

/**
 * Hover-zoom preview plus a touch zoom viewer for one image (a flyer, a poster, a product shot).
 *
 * `ImageZoom::html( $attachment_id )` prints the thumbnail wrapped in a link to the original
 * upload. One small script (printed once per page) gives every `a[data-hiz]`:
 *  - mouse: hovering shows the original image large in a centered preview that fades out when
 *    the pointer leaves the thumbnail (or the window loses focus);
 *  - click, tap or Enter: a full-screen viewer with pinch, wheel and double-tap zoom, finger or
 *    mouse panning, swipe-down / Escape / backdrop to close, fading out as it closes.
 * Options: `delay` (ms) holds the hover preview back and shows a small countdown ring on the thumbnail while it waits;
 * `hint` is a one-line explainer shown above the preview (a translatable default is used when `delay` is set; pass
 * an empty string to hide it); `detail` adds a side panel to the preview with a title, label/value facts, a short
 * text and link lines, as if the item had been opened.
 * Without JavaScript the link simply opens the original image. Page-builder lightboxes are opted out. `--hiz-*` custom properties on
 * any ancestor restyle the preview and viewer.
 */
final class ImageZoom {
    private static bool $assets_printed = false;

    /**
     * Thumbnail markup for one attachment.
     *
     * `fit` => 'contain' shows the whole image over a blurred fill of itself instead of cropping it.
     *
     * @param array{size?:string,class?:string,alt?:string,sizes?:string,loading?:string,fit?:string,delay?:int,hint?:string,detail?:array{title?:string,meta?:array<string,string>,text?:string,links?:list<array{label:string,url:string,primary?:bool}>}} $args
     */
    public static function html( int $attachment_id, array $args = [] ): string {
        if ( $attachment_id <= 0 || ! function_exists( 'wp_get_attachment_image_src' ) ) {
            return '';
        }
        $full = wp_get_attachment_image_src( $attachment_id, 'full' );
        if ( ! is_array( $full ) || '' === (string) $full[0] ) {
            return '';
        }
        $alt   = (string) ( $args['alt'] ?? get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
        $image = (string) wp_get_attachment_image( $attachment_id, (string) ( $args['size'] ?? 'large' ), false, array_filter( [
            'class'   => 'hiz__img',
            'loading' => (string) ( $args['loading'] ?? 'lazy' ),
            'sizes'   => $args['sizes'] ?? null,
            'alt'     => $alt,
        ], static fn( $v ): bool => null !== $v ) );

        if ( isset( $args['sizes'] ) ) {
            // An explicit sizes list wins over WordPress's lazy `auto` sizing, which would pick a rendition
            // for the box width and blur a cover/contain-fitted image in a tall box.
            $image = str_replace( 'sizes="auto, ', 'sizes="', $image );
        }
        $delay   = max( 0, min( 5000, (int) ( $args['delay'] ?? 0 ) ) );
        $hint    = array_key_exists( 'hint', $args )
            ? trim( (string) $args['hint'] )
            : ( $delay > 0 ? __( "You're seeing this preview because you hovered over it. Click to open it full screen." ) : '' );
        $detail  = self::detail( is_array( $args['detail'] ?? null ) ? $args['detail'] : [] );
        $contain = 'contain' === ( $args['fit'] ?? 'cover' );
        $style   = $contain ? ' style="--hiz-fill:url(' . esc_url( (string) ( wp_get_attachment_image_src( $attachment_id, 'medium' )[0] ?? $full[0] ) ) . ')"' : '';

        return '<a class="' . esc_attr( trim( 'hiz ' . ( $contain ? 'hiz--contain ' : '' ) . ( $args['class'] ?? '' ) ) ) . '" href="' . esc_url( (string) $full[0] ) . '"' . $style . ' data-hiz data-elementor-open-lightbox="no"'
            . ( $delay > 0 ? ' data-hiz-delay="' . $delay . '"' : '' ) . ( '' !== $hint ? ' data-hiz-hint="' . esc_attr( $hint ) . '"' : '' )
            . ' data-hiz-w="' . (int) $full[1] . '" data-hiz-h="' . (int) $full[2] . '" aria-label="' . esc_attr( '' !== $alt ? $alt : __( 'View full image' ) ) . '">'
            . $image . '</a>' . ( '' !== $detail ? '<template class="hiz-detail">' . $detail . '</template>' : '' ) . self::assets();
    }

    /**
     * The preview side panel: title, facts, text and link lines. Everything is escaped here; empty parts are skipped.
     *
     * @param array{title?:string,meta?:array<string,string>,text?:string,links?:list<array{label:string,url:string,primary?:bool}>} $detail
     */
    public static function detail( array $detail ): string {
        $html  = '';
        $title = trim( (string) ( $detail['title'] ?? '' ) );
        if ( '' !== $title ) {
            $html .= '<h3 class="hiz-d__t">' . esc_html( $title ) . '</h3>';
        }
        $facts = '';
        foreach ( (array) ( $detail['meta'] ?? [] ) as $label => $value ) {
            $value = trim( (string) $value );
            if ( '' !== $value ) {
                $facts .= '<div><dt>' . esc_html( (string) $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
            }
        }
        if ( '' !== $facts ) {
            $html .= '<dl class="hiz-d__m">' . $facts . '</dl>';
        }
        $text = trim( (string) ( $detail['text'] ?? '' ) );
        if ( '' !== $text ) {
            $html .= '<p class="hiz-d__p">' . esc_html( $text ) . '</p>';
        }
        $links = '';
        foreach ( (array) ( $detail['links'] ?? [] ) as $link ) {
            $url   = trim( (string) ( $link['url'] ?? '' ) );
            $label = trim( (string) ( $link['label'] ?? '' ) );
            if ( '' === $url || '' === $label ) {
                continue;
            }
            $shown  = preg_replace( '#^https?://(www\\.)?#i', '', $url );
            $home   = function_exists( 'home_url' ) ? (string) wp_parse_url( home_url(), PHP_URL_HOST ) : '';
            $away   = '' === $home || (string) wp_parse_url( $url, PHP_URL_HOST ) !== $home;
            $links .= '<li' . ( ! empty( $link['primary'] ) ? ' class="is-primary"' : '' ) . '><a href="' . esc_url( $url ) . '"' . ( $away ? ' target="_blank" rel="noopener"' : '' ) . '><b>' . esc_html( $label ) . '</b><span>' . esc_html( rtrim( (string) $shown, '/' ) ) . '</span></a></li>';
        }
        if ( '' !== $links ) {
            $html .= '<ul class="hiz-d__l">' . $links . '</ul>';
        }

        return '' !== $html ? '<div class="hiz-d">' . $html . '</div>' : '';
    }

    /** The preview/viewer style and script, once per page. */
    public static function assets(): string {
        if ( self::$assets_printed ) {
            return '';
        }
        self::$assets_printed = true;

        return '<style id="hexa-image-zoom-css">' . self::css() . '</style><script id="hexa-image-zoom-js">' . self::js() . '</script>';
    }

    public static function css(): string {
        return '.hiz{display:block;position:relative;width:100%;height:100%;overflow:hidden;cursor:zoom-in;-webkit-tap-highlight-color:transparent}'
            . '.hiz .hiz__img{display:block;width:100%;height:100%;max-width:none;object-fit:cover;transition:transform .5s cubic-bezier(.2,.7,.2,1)}.hiz:hover .hiz__img{transform:scale(1.03)}'
            . '.hiz--contain{background:#000}.hiz--contain::before{content:"";position:absolute;inset:-24px;background:var(--hiz-fill) center/cover;filter:blur(18px) brightness(.55);transform:scale(1.1)}.hiz.hiz--contain .hiz__img{position:relative;object-fit:contain}'
            . '.hiz-pop{position:fixed;z-index:99998;top:50%;left:50%;width:max-content;max-width:min(var(--hiz-pop-width,720px),92%);max-height:92vh;max-height:92dvh;pointer-events:none;'
            . 'opacity:0;transform:translate(-50%,calc(-50% + 8px)) scale(.975);transition:opacity .38s cubic-bezier(.16,1,.3,1),transform .5s cubic-bezier(.16,1,.3,1);will-change:opacity,transform;'
            . 'border:1px solid var(--hiz-border,rgba(255,255,255,.14));border-radius:var(--hiz-radius,16px);background:var(--hiz-bg,#000);box-shadow:0 30px 90px rgba(0,0,0,.6);overflow:hidden}'
            . '.hiz-pop.is-in{opacity:1;transform:translate(-50%,-50%) scale(1);pointer-events:auto}.hiz-pop.is-in .hiz-pop__fig{cursor:zoom-in}.hiz-pop.has-detail{max-width:min(var(--hiz-pop-wide,1040px),94%)}'
            . '.hiz-pop__note{display:flex;align-items:center;gap:10px;margin:0;padding:11px 18px 10px;border-bottom:1px solid rgba(255,255,255,.06);background:linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,.01));color:rgba(255,255,255,.92);font:500 12.5px/1.4 inherit;letter-spacing:.01em}'
            . '.hiz-pop__note::before{content:"";flex:none;width:6px;height:6px;border-radius:50%;background:var(--hiz-accent,#fff);box-shadow:0 0 0 3px rgba(255,255,255,.08),0 0 10px var(--hiz-accent,#fff)}.hiz-pop__note[hidden]{display:none}'
            . '.hiz-pop__body{display:flex;align-items:stretch}.hiz-pop__fig{display:flex;flex:0 1 auto;min-width:0;background:var(--hiz-bg,#000)}'
            . '.hiz-pop img{display:block;width:auto;height:auto;max-width:100%;max-height:86vh;max-height:86dvh;object-fit:contain}'
            . '.hiz-pop.has-detail img{max-height:min(80vh,820px)}'
            . '.hiz-pop__side{display:none;flex:0 0 300px;min-width:0;border-left:1px solid var(--hiz-border,rgba(255,255,255,.1));background:var(--hiz-panel,#0c0c0c);color:rgba(255,255,255,.86);overflow:hidden}.hiz-pop.has-detail .hiz-pop__side{display:block}'
            . '.hiz-d{padding:22px 22px 24px}.hiz-d__t{margin:0 0 16px;color:#fff;font-size:20px;font-weight:600;line-height:1.22;letter-spacing:-.005em}'
            . '.hiz-d__m{margin:0 0 16px;display:grid;gap:11px}.hiz-d__m dt{color:var(--hiz-accent,#fff);font-size:10px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;opacity:.9}.hiz-d__m dd{margin:2px 0 0;font-size:13.5px;line-height:1.4}'
            . '.hiz-d__p{margin:0 0 16px;color:rgba(255,255,255,.66);font-size:13px;line-height:1.55;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:6;overflow:hidden}'
            . '.hiz-d__l{margin:0;padding:14px 0 0;list-style:none;display:grid;gap:9px;border-top:1px solid rgba(255,255,255,.1)}.hiz-d__l li{min-width:0}.hiz-d__l a{display:grid;gap:1px;min-width:0;text-decoration:none;color:inherit}.hiz-d__l a:hover span,.hiz-d__l a:focus-visible span{color:rgba(255,255,255,.85)}'
            . '.hiz-d__l b{font-size:12px;font-weight:600;color:#fff}.hiz-d__l .is-primary b{color:var(--hiz-accent,#fff)}.hiz-d__l span{font-size:12px;color:rgba(255,255,255,.5);transition:color .25s;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}'
            . '@media(max-width:760px){.hiz-pop.has-detail .hiz-pop__body{flex-direction:column}.hiz-pop.has-detail img{max-height:48vh}.hiz-pop__side{flex:none;border-left:0;border-top:1px solid var(--hiz-border,rgba(255,255,255,.1))}.hiz-d{padding:16px 18px}.hiz-d__p{-webkit-line-clamp:3}}'
            . '.hiz-timer{position:absolute;top:10px;right:10px;z-index:2;display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;background:rgba(0,0,0,.55);pointer-events:none;animation:hiz-in .18s ease both}'
            . '.hiz-timer svg{position:absolute;inset:0;width:100%;height:100%;transform:rotate(-90deg)}.hiz-timer circle{fill:none;stroke-width:2.2}.hiz-timer__bg{stroke:rgba(255,255,255,.22)}.hiz-timer__fg{stroke:var(--hiz-accent,#fff);stroke-linecap:round}'
            . '.hiz-timer b{position:relative;color:#fff;font:600 10.5px/1 inherit;font-variant-numeric:tabular-nums}@keyframes hiz-in{from{opacity:0}to{opacity:1}}'
            . '.hiz-view{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:var(--hiz-backdrop,rgba(0,0,0,.92));opacity:0;transition:opacity .25s ease;touch-action:none;overscroll-behavior:contain}'
            . '.hiz-view.is-in{opacity:1}.hiz-view img{max-width:100vw;max-height:100vh;max-height:100dvh;object-fit:contain;user-select:none;-webkit-user-drag:none;will-change:transform;transform-origin:50% 50%;cursor:zoom-in}'
            . '.hiz-view.is-zoomed img{cursor:grab}.hiz-view.is-anim img{transition:transform .3s cubic-bezier(.2,.7,.2,1)}'
            . '.hiz-x{position:absolute;top:max(14px,env(safe-area-inset-top));right:14px;z-index:1;width:44px;height:44px;padding:0;border:1px solid rgba(255,255,255,.3);border-radius:999px;background:rgba(0,0,0,.55);color:#fff;font:400 26px/1 sans-serif;cursor:pointer}'
            . 'html.hiz-lock{overflow:hidden}@media(prefers-reduced-motion:reduce){.hiz-pop,.hiz-view,.hiz__img,.hiz-view.is-anim img{transition:none}.hiz-timer{animation:none}}';
    }

    public static function js(): string {
        return <<<'JS'
(function(){
if(window.hexaImageZoom)return;window.hexaImageZoom=1;
var hover=window.matchMedia('(hover:hover) and (pointer:fine)'),pop,popTimer,raf,ring,cur=null,view,img,s=1,tx=0,ty=0,pts={},start=null,lastTap=0,opener=null;
function trig(e){return e.target.closest?e.target.closest('a[data-hiz]'):null;}
// Hover preview: the original image, centered and large, never capturing the pointer. With data-hiz-delay it waits,
// showing a small countdown ring on the thumbnail; a hint line and a detail side panel (template.hiz-detail) are optional.
function stopRing(){cancelAnimationFrame(raf);if(ring){ring.remove();ring=null;}}
function startRing(a,ms){stopRing();ring=document.createElement('span');ring.className='hiz-timer';ring.setAttribute('aria-hidden','true');
ring.innerHTML='<svg viewBox="0 0 36 36"><circle class="hiz-timer__bg" cx="18" cy="18" r="15"/><circle class="hiz-timer__fg" cx="18" cy="18" r="15"/></svg><b></b>';a.appendChild(ring);
var fg=ring.querySelector('.hiz-timer__fg'),n=ring.querySelector('b'),t0=performance.now(),C=2*Math.PI*15;fg.style.strokeDasharray=C;
function f(t){var p=Math.min(1,(t-t0)/ms);fg.style.strokeDashoffset=C*(1-p);n.textContent=(ms*(1-p)/1000).toFixed(1);if(p<1)raf=requestAnimationFrame(f);}raf=requestAnimationFrame(f);}
function ensurePop(){if(pop)return;pop=document.createElement('div');pop.className='hiz-pop';pop.setAttribute('aria-hidden','true');
pop.innerHTML='<p class="hiz-pop__note" hidden></p><div class="hiz-pop__body"><div class="hiz-pop__fig"><img alt=""></div><div class="hiz-pop__side"></div></div>';document.body.appendChild(pop);}
function fill(a){ensurePop();var i=pop.querySelector('img'),note=pop.querySelector('.hiz-pop__note'),side=pop.querySelector('.hiz-pop__side'),t=a.nextElementSibling,h=a.getAttribute('data-hiz-hint')||'',d=t&&t.matches&&t.matches('template.hiz-detail')?t.content.cloneNode(true):null;
if(i.getAttribute('src')!==a.href){i.src=a.href;}note.textContent=h;note.hidden=!h;side.textContent='';if(d)side.appendChild(d);pop.classList.toggle('has-detail',!!d);}
function showPop(a){hidePop();cur=a;var ms=+a.getAttribute('data-hiz-delay')||0;if(ms>0)startRing(a,ms);
popTimer=setTimeout(function(){if(cur!==a)return;stopRing();fill(a);var i=pop.querySelector('img'),go=function(){if(cur===a)requestAnimationFrame(function(){pop.classList.add('is-in');});};if(i.decode){i.decode().then(go,go);}else{go();}},ms>0?ms:90);}
function hidePop(){clearTimeout(popTimer);stopRing();cur=null;if(pop)pop.classList.remove('is-in');}
document.addEventListener('mouseover',function(e){var a=trig(e);if(a&&hover.matches&&a!==cur&&!view)showPop(a);});
function inPop(n){return !!(pop&&n&&pop.contains(n));}
document.addEventListener('mouseout',function(e){var a=trig(e),r=e.relatedTarget;if(a&&a===cur&&!(r&&a.contains(r))&&!(inPop(r)&&pop.classList.contains('is-in')))hidePop();else if(cur&&inPop(e.target)&&!inPop(r)&&!(r&&cur.contains(r)))hidePop();});
document.addEventListener('click',function(e){if(!cur||!pop||!pop.classList.contains('is-in'))return;if(inPop(e.target)){if(e.target.closest('.hiz-pop__fig')){var a=cur;e.preventDefault();open(a);}return;}if(!trig(e))hidePop();});
window.addEventListener('blur',hidePop);
// Full-screen viewer: pinch / wheel / double-tap zoom, pan, swipe down to close.
function apply(anim){view.classList.toggle('is-anim',!!anim);view.classList.toggle('is-zoomed',s>1.01);img.style.transform='translate('+tx+'px,'+ty+'px) scale('+s+')';}
function clamp(){var w=img.offsetWidth*s,h=img.offsetHeight*s,mx=Math.max(0,(w-innerWidth)/2),my=Math.max(0,(h-innerHeight)/2);tx=Math.min(mx,Math.max(-mx,tx));ty=Math.min(my,Math.max(-my,ty));}
function zoomAt(ns,x,y){ns=Math.min(5,Math.max(1,ns));var cx=x-innerWidth/2,cy=y-innerHeight/2;tx=cx-(cx-tx)*ns/s;ty=cy-(cy-ty)*ns/s;s=ns;if(s===1){tx=ty=0;}clamp();}
function open(a){hidePop();opener=a;view=document.createElement('div');view.className='hiz-view';view.setAttribute('role','dialog');view.setAttribute('aria-modal','true');
view.innerHTML='<button type="button" class="hiz-x" aria-label="Close">×</button>';img=new Image();img.alt=(a.querySelector('img')||{}).alt||'';var th=a.querySelector('img');if(th&&th.currentSrc)img.src=th.currentSrc;img.src=a.href;view.appendChild(img);
document.body.appendChild(view);document.documentElement.classList.add('hiz-lock');s=1;tx=ty=0;pts={};apply();
requestAnimationFrame(function(){view.classList.add('is-in');});view.querySelector('.hiz-x').focus({preventScroll:true});
view.querySelector('.hiz-x').addEventListener('click',function(){close();});
view.addEventListener('wheel',function(e){e.preventDefault();zoomAt(s*(e.deltaY<0?1.15:1/1.15),e.clientX,e.clientY);apply();},{passive:false});
view.addEventListener('pointerdown',down);view.addEventListener('pointermove',move);view.addEventListener('pointerup',up);view.addEventListener('pointercancel',up);
history.pushState({hiz:1},'');}
function close(fromPop){if(!view)return;var v=view;view=null;v.style.pointerEvents='none';v.classList.remove('is-in');document.documentElement.classList.remove('hiz-lock');
setTimeout(function(){v.remove();},260);if(fromPop!==true&&history.state&&history.state.hiz)history.back();if(opener)opener.focus({preventScroll:true});}
function mid(){var k=Object.keys(pts),a=pts[k[0]],b=pts[k[1]];return{x:(a.x+b.x)/2,y:(a.y+b.y)/2,d:Math.hypot(a.x-b.x,a.y-b.y)};}
function down(e){if(e.target.closest('.hiz-x'))return;view.setPointerCapture(e.pointerId);pts[e.pointerId]={x:e.clientX,y:e.clientY};var n=Object.keys(pts).length;
start=n===2?{m:mid(),s:s,tx:tx,ty:ty,pinch:1}:{x:e.clientX,y:e.clientY,tx:tx,ty:ty,moved:0,t:Date.now()};}
function move(e){if(!pts[e.pointerId]||!start)return;pts[e.pointerId]={x:e.clientX,y:e.clientY};
if(start.pinch&&Object.keys(pts).length===2){var m=mid();s=start.s;tx=start.tx+(m.x-start.m.x);ty=start.ty+(m.y-start.m.y);zoomAt(start.s*m.d/start.m.d,m.x,m.y);apply();return;}
if(start.pinch)return;var dx=e.clientX-start.x,dy=e.clientY-start.y;if(Math.abs(dx)+Math.abs(dy)>6)start.moved=1;
if(s>1.01){tx=start.tx+dx;ty=start.ty+dy;clamp();apply();}else if(dy>0){ty=dy;img.style.transform='translateY('+dy+'px)';view.style.opacity=String(Math.max(.25,1-dy/400));}}
function up(e){delete pts[e.pointerId];if(!start)return;var st=start;if(Object.keys(pts).length){var k=Object.keys(pts)[0];start={x:pts[k].x,y:pts[k].y,tx:tx,ty:ty,moved:1};return;}start=null;
if(st.pinch){if(s<1.05){s=1;tx=ty=0;}apply(1);return;}
if(s<=1.01&&ty>110){close();return;}if(s<=1.01){ty=0;view.style.opacity='';apply(1);}
if(!st.moved){var now=Date.now();if(now-lastTap<300){zoomAt(s>1.01?1:2.5,e.clientX,e.clientY);apply(1);lastTap=0;}else{lastTap=now;if(e.target===view&&s<=1.01)setTimeout(function(){if(lastTap===now)close();},300);}}}
document.addEventListener('click',function(e){var a=trig(e);if(!a||e.ctrlKey||e.metaKey||e.shiftKey)return;e.preventDefault();e.stopImmediatePropagation();open(a);},true);
document.addEventListener('keydown',function(e){if(view&&e.key==='Escape'){e.preventDefault();close();}});
window.addEventListener('popstate',function(){if(view)close(true);});
})();
JS;
    }
}
