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
     * @param array{size?:string,class?:string,alt?:string,sizes?:string,loading?:string,fit?:string} $args
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
        $contain = 'contain' === ( $args['fit'] ?? 'cover' );
        $style   = $contain ? ' style="--hiz-fill:url(' . esc_url( (string) ( wp_get_attachment_image_src( $attachment_id, 'medium' )[0] ?? $full[0] ) ) . ')"' : '';

        return '<a class="' . esc_attr( trim( 'hiz ' . ( $contain ? 'hiz--contain ' : '' ) . ( $args['class'] ?? '' ) ) ) . '" href="' . esc_url( (string) $full[0] ) . '"' . $style . ' data-hiz data-elementor-open-lightbox="no"'
            . ' data-hiz-w="' . (int) $full[1] . '" data-hiz-h="' . (int) $full[2] . '" aria-label="' . esc_attr( '' !== $alt ? $alt : __( 'View full image' ) ) . '">'
            . $image . '</a>' . self::assets();
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
            . '.hiz-pop{position:fixed;z-index:99998;top:50%;left:50%;max-width:min(var(--hiz-pop-width,720px),92vw);max-height:92vh;max-height:92dvh;pointer-events:none;'
            . 'opacity:0;transform:translate(-50%,-50%) scale(.96);transition:opacity .22s ease,transform .28s cubic-bezier(.2,.7,.2,1);'
            . 'border:1px solid var(--hiz-border,rgba(255,255,255,.14));border-radius:var(--hiz-radius,10px);background:var(--hiz-bg,#000);box-shadow:0 30px 90px rgba(0,0,0,.6);overflow:hidden}'
            . '.hiz-pop.is-in{opacity:1;transform:translate(-50%,-50%) scale(1)}.hiz-pop img{display:block;width:auto;height:auto;max-width:inherit;max-height:92vh;max-height:92dvh;object-fit:contain}'
            . '.hiz-view{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:var(--hiz-backdrop,rgba(0,0,0,.92));opacity:0;transition:opacity .25s ease;touch-action:none;overscroll-behavior:contain}'
            . '.hiz-view.is-in{opacity:1}.hiz-view img{max-width:100vw;max-height:100vh;max-height:100dvh;object-fit:contain;user-select:none;-webkit-user-drag:none;will-change:transform;transform-origin:50% 50%;cursor:zoom-in}'
            . '.hiz-view.is-zoomed img{cursor:grab}.hiz-view.is-anim img{transition:transform .3s cubic-bezier(.2,.7,.2,1)}'
            . '.hiz-x{position:absolute;top:max(14px,env(safe-area-inset-top));right:14px;z-index:1;width:44px;height:44px;padding:0;border:1px solid rgba(255,255,255,.3);border-radius:999px;background:rgba(0,0,0,.55);color:#fff;font:400 26px/1 sans-serif;cursor:pointer}'
            . 'html.hiz-lock{overflow:hidden}@media(prefers-reduced-motion:reduce){.hiz-pop,.hiz-view,.hiz__img,.hiz-view.is-anim img{transition:none}}';
    }

    public static function js(): string {
        return <<<'JS'
(function(){
if(window.hexaImageZoom)return;window.hexaImageZoom=1;
var hover=window.matchMedia('(hover:hover) and (pointer:fine)'),pop,popTimer,cur=null,view,img,s=1,tx=0,ty=0,pts={},start=null,lastTap=0,opener=null;
function trig(e){return e.target.closest?e.target.closest('a[data-hiz]'):null;}
// Hover preview: the original image, centered and large, never capturing the pointer.
function showPop(a){clearTimeout(popTimer);if(!pop){pop=document.createElement('div');pop.className='hiz-pop';pop.setAttribute('aria-hidden','true');pop.appendChild(document.createElement('img'));document.body.appendChild(pop);}
cur=a;popTimer=setTimeout(function(){if(cur!==a)return;var i=pop.firstChild;if(i.getAttribute('src')!==a.href){i.src=a.href;}requestAnimationFrame(function(){pop.classList.add('is-in');});},90);}
function hidePop(){clearTimeout(popTimer);cur=null;if(pop)pop.classList.remove('is-in');}
document.addEventListener('mouseover',function(e){var a=trig(e);if(a&&hover.matches&&a!==cur&&!view)showPop(a);});
document.addEventListener('mouseout',function(e){var a=trig(e);if(a&&a===cur&&!(e.relatedTarget&&a.contains(e.relatedTarget)))hidePop();});
window.addEventListener('blur',hidePop);window.addEventListener('scroll',hidePop,{passive:true});
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
