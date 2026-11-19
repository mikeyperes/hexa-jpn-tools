<?php
namespace Hexa\PluginCore\Sitemaps;

/**
 * A plugin's own "Sitemaps" section: only the sitemaps that plugin declared,
 * each with its recommendation, Rank Math state, live address and a change
 * button where they differ. Uses the shared sitemap engine's AJAX actions.
 */
final class SitemapsPanel {
    public static function render(string $slug): void {
        $p=SitemapProviders::all()[$slug]??null;
        $id='hexa-sp-'.sanitize_html_class($slug);
        ?>
        <section class="hexa-sp" id="<?php echo esc_attr($id); ?>" data-owner="<?php echo esc_attr($slug); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('hexa_sitemaps')); ?>">
            <h3>Sitemaps</h3>
            <p>The sitemaps <?php echo esc_html($p['plugin']??'this plugin'); ?> adds to Rank Math, and whether each one is on. HWS Base Tools shows these too, read-only.</p>
            <div data-role="list"><p>Loading…</p></div>
            <p class="hexa-sp-notice" data-role="notice" role="status" aria-live="polite"></p>
        </section>
        <style>
            .hexa-sp{max-width:900px}
            .hexa-sp-row{display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px;padding:12px 14px;border:1px solid #dcdcde;border-radius:8px;background:#fff;margin:0 0 8px}
            .hexa-sp-row.attention{border-color:#dba617}
            .hexa-sp-label{flex:1 1 240px;font-weight:600}
            .hexa-sp-label small{display:block;font-weight:400;color:#50575e}
            .hexa-sp-label a span{margin-left:3px}
            .hexa-sp-pill{display:inline-block;padding:2px 8px;border-radius:999px;background:#f0f0f1;color:#3c434a;font-size:12px}
            .hexa-sp-pill.ok{background:#e7f6ec;color:#00753a}
            .hexa-sp-why{flex-basis:100%;color:#50575e;font-size:12px}
            .hexa-sp-notice.bad{color:#b32d2e}
        </style>
        <script>
        (function(){
            var root=document.getElementById(<?php echo wp_json_encode($id); ?>); if(!root||root.dataset.ready)return; root.dataset.ready='1';
            var owner=root.dataset.owner, nonce=root.dataset.nonce, list=root.querySelector('[data-role=list]'), notice=root.querySelector('[data-role=notice]');
            function esc(v){var d=document.createElement('div');d.textContent=v==null?'':String(v);return d.innerHTML;}
            function post(action,f){var b=new URLSearchParams();b.set('action',action);b.set('nonce',nonce);Object.keys(f||{}).forEach(function(k){b.set(k,f[k]);});
                return fetch(ajaxurl,{method:'POST',credentials:'same-origin',body:b}).then(function(r){return r.json();}).then(function(j){if(!j||!j.success)throw new Error((j&&j.data&&j.data.message)||'The request failed.');return j.data;});}
            function say(t,bad){notice.textContent=t||'';notice.className='hexa-sp-notice'+(bad?' bad':'');}
            function row(r){
                var writable=r.setting!==''||r.kind==='module', differs=writable&&r.recommended!==r.current;
                var h='<div class="hexa-sp-row'+(differs?' attention':'')+'" data-id="'+esc(r.id)+'"><span class="hexa-sp-label">'+esc(r.label);
                if(r.url)h+='<small><a href="'+esc(r.url)+'" target="_blank" rel="noopener noreferrer">'+esc(r.url)+'<span aria-hidden="true">↗</span></a></small>';
                h+='</span><span class="hexa-sp-pill '+(r.recommended?'ok':'')+'">'+(r.recommended?'Recommended':'Not recommended')+'</span>';
                h+='<span class="hexa-sp-pill '+(r.current?'ok':'')+'">'+(r.current?'On in Rank Math':'Off in Rank Math')+'</span>';
                if(differs)h+='<button type="button" class="button button-primary" data-change="'+(r.recommended?1:0)+'">'+(r.recommended?'Turn on':'Turn off')+'</button>';
                else if(writable)h+='<button type="button" class="button" data-change="'+(r.current?0:1)+'">'+(r.current?'Turn off':'Turn on')+'</button>';
                if(r.why)h+='<span class="hexa-sp-why">'+esc(r.why)+'</span>';
                return h+'</div>';
            }
            function load(){return post('hexa_sitemaps_lookup',{}).then(function(d){
                var rows=(d.rows||[]).filter(function(r){return r.owner===owner;});
                list.innerHTML=!d.rank_math_active?'<p>Rank Math is not active, so there are no sitemaps.</p>':(rows.length?rows.map(row).join(''):'<p>None of this plugin\'s content types exist on this site yet.</p>');
            }).catch(function(e){list.innerHTML='<p>'+esc(e.message)+'</p>';});}
            list.addEventListener('click',function(e){var b=e.target.closest('button[data-change]');if(!b)return;b.disabled=true;
                post('hexa_sitemaps_change',{id:b.closest('[data-id]').dataset.id,on:b.dataset.change}).then(function(r){say(r.message||'Changed.',r.ok===false);return load();}).catch(function(x){b.disabled=false;say(x.message,true);});});
            load();
        })();
        </script>
        <?php
    }
}
