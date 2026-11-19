<?php

namespace Hexa\PluginCore\SchemaTools;

/**
 * A plugin's own "Schema" section: every schema structure the plugin
 * declared, the pages it covers, its mode, and a live preview of what it
 * prints on a real page of the site, with validator links.
 */
final class SchemaStructuresPanel {
    public static function render( string $owner ): void {
        $owner = sanitize_key( $owner );
        $data  = SchemaStructures::owner( $owner );
        $rows  = SchemaStructuresModule::rows( $owner );
        $id    = 'hexa-ssp-' . sanitize_html_class( $owner );
        ?>
        <section class="hexa-ssp" id="<?php echo esc_attr( $id ); ?>" data-owner="<?php echo esc_attr( $owner ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( SchemaStructuresModule::NONCE ) ); ?>">
            <h3>Schema</h3>
            <p>The structured data <?php echo esc_html( (string) ( $data['plugin'] ?? 'this plugin' ) ); ?> adds to each page. Every structure is on from the start; choose another mode or turn one off here. All plugins print into one schema block per page.</p>
            <?php if ( [] === $rows ) : ?>
                <p>This plugin has no schema structures.</p>
            <?php endif; ?>
            <?php foreach ( $rows as $row ) : ?>
                <div class="hexa-ssp-row" data-id="<?php echo esc_attr( $row['id'] ); ?>">
                    <h4><?php echo esc_html( $row['label'] ); ?></h4>
                    <?php if ( '' !== $row['where'] ) : ?>
                        <p class="hexa-ssp-where"><?php echo esc_html( $row['where'] ); ?></p>
                    <?php endif; ?>
                    <label class="hexa-ssp-mode">
                        <span>Mode</span>
                        <select data-role="mode">
                            <?php foreach ( $row['modes'] as $mode => $meta ) : ?>
                                <option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $row['mode'], $mode ); ?>><?php echo esc_html( $meta['label'] . ( '' !== $meta['types'] ? ' (' . $meta['types'] . ')' : '' ) . ( $mode === $row['default'] ? ' - default' : '' ) ); ?></option>
                            <?php endforeach; ?>
                            <option value="<?php echo esc_attr( SchemaStructures::OFF ); ?>" <?php selected( $row['mode'], SchemaStructures::OFF ); ?>>Off</option>
                        </select>
                    </label>
                    <p class="hexa-ssp-actions">
                        <button type="button" class="button" data-role="preview">Preview</button>
                        <?php if ( '' !== $row['sample_url'] ) : ?>
                            <a class="button" href="<?php echo esc_url( $row['sample_url'] ); ?>" target="_blank" rel="noopener noreferrer">Open page</a>
                            <a class="button" href="<?php echo esc_url( SchemaGraph::validator_url( $row['sample_url'] ) ); ?>" target="_blank" rel="noopener noreferrer">Schema validator</a>
                            <a class="button" href="<?php echo esc_url( 'https://search.google.com/test/rich-results?url=' . rawurlencode( $row['sample_url'] ) ); ?>" target="_blank" rel="noopener noreferrer">Google rich results</a>
                        <?php endif; ?>
                    </p>
                    <div class="hexa-ssp-preview" data-role="output" hidden></div>
                </div>
            <?php endforeach; ?>
            <p class="hexa-ssp-notice" data-role="notice" role="status" aria-live="polite"></p>
        </section>
        <style>
            .hexa-ssp{max-width:900px}
            .hexa-ssp-row{padding:14px 16px;border:1px solid #dcdcde;border-radius:8px;background:#fff;margin:0 0 10px}
            .hexa-ssp-row h4{margin:0 0 4px;font-size:14px}
            .hexa-ssp-where{margin:0 0 10px;color:#50575e}
            .hexa-ssp-mode{display:block;margin:0 0 10px}
            .hexa-ssp-mode span{display:block;font-weight:600;margin:0 0 4px}
            .hexa-ssp-mode select{max-width:100%}
            .hexa-ssp-actions{display:flex;flex-wrap:wrap;gap:6px;margin:0}
            .hexa-ssp-preview{margin-top:10px}
            .hexa-ssp-preview pre{background:#1e1e1e;color:#d4d4d4;padding:14px;border-radius:6px;max-height:420px;overflow:auto;white-space:pre-wrap;word-break:break-word;font-size:12px}
            .hexa-ssp-preview .bad{color:#b32d2e}
            .hexa-ssp-notice.bad{color:#b32d2e}
        </style>
        <script>
        (function(){
            var root=document.getElementById(<?php echo wp_json_encode( $id ); ?>); if(!root||root.dataset.ready)return; root.dataset.ready='1';
            var owner=root.dataset.owner, nonce=root.dataset.nonce, notice=root.querySelector('[data-role=notice]');
            function esc(v){var d=document.createElement('div');d.textContent=v==null?'':String(v);return d.innerHTML;}
            function post(action,f){var b=new URLSearchParams();b.set('action',action);b.set('nonce',nonce);b.set('owner',owner);Object.keys(f||{}).forEach(function(k){b.set(k,f[k]);});
                return fetch(ajaxurl,{method:'POST',credentials:'same-origin',body:b}).then(function(r){return r.json();}).then(function(j){if(!j||!j.success)throw new Error((j&&j.data&&j.data.message)||'The request failed.');return j.data;});}
            function say(t,bad){notice.textContent=t||'';notice.className='hexa-ssp-notice'+(bad?' bad':'');}
            root.addEventListener('change',function(e){var s=e.target.closest('select[data-role=mode]');if(!s)return;var id=s.closest('[data-id]').dataset.id;s.disabled=true;
                post(<?php echo wp_json_encode( SchemaStructuresModule::PREFIX . '_save' ); ?>,{id:id,mode:s.value}).then(function(d){say(d.message||'Saved.');}).catch(function(x){say(x.message,true);}).finally(function(){s.disabled=false;});});
            root.addEventListener('click',function(e){var b=e.target.closest('button[data-role=preview]');if(!b)return;var row=b.closest('[data-id]'),out=row.querySelector('[data-role=output]');b.disabled=true;out.hidden=false;out.innerHTML='<p>Building…</p>';
                post(<?php echo wp_json_encode( SchemaStructuresModule::PREFIX . '_preview' ); ?>,{id:row.dataset.id}).then(function(d){
                    var h='<p'+(d.ok?'':' class="bad"')+'>'+esc(d.message)+(d.url?' <a href="'+esc(d.url)+'" target="_blank" rel="noopener noreferrer">'+esc(d.url)+'</a>':'')+'</p>';
                    if(d.issues&&d.issues.length)h+='<p class="bad">'+d.issues.map(function(i){return esc(i.path+': '+i.code);}).join('<br>')+'</p>';
                    if(d.ok)h+='<pre>'+esc(JSON.stringify(d.document,null,2))+'</pre>';
                    out.innerHTML=h;}).catch(function(x){out.innerHTML='<p class="bad">'+esc(x.message)+'</p>';}).finally(function(){b.disabled=false;});});
        })();
        </script>
        <?php
    }
}
