<?php

namespace Hexa\PluginCore\EntitySources;

use Hexa\PluginCore\SmartSearch\SmartSearchRenderer;
use Hexa\PluginCore\WpAdminComponents\CoreUi;

final class PrimaryEntityRenderer {
    public function render( PrimaryEntityManager $manager, array $args = [] ): string {
        $args = array_replace( (array) $manager->config( 'render_args', [] ), $args );
        ob_start();
        CoreUi::render_assets();
        $assets = (string) ob_get_clean();
        $settings = $manager->settings();
        $entity = $manager->resolve();
        $sources = $manager->sources();
        $site_type = $manager->site_type();
        $allow_empty_site_type = (bool) $manager->config( 'allow_empty_site_type', false );
        $site_type_placeholder = (string) $manager->config( 'site_type_placeholder', 'Select website type' );
        $selected_source = isset( $sources[ $settings['source'] ] ) ? (string) $settings['source'] : ( 1 === count( $sources ) ? (string) array_key_first( $sources ) : '' );
        $nonce = wp_create_nonce( (string) $manager->config( 'nonce_action', 'hexa_primary_entity' ) );
        $type_map = [];
        foreach ( $manager->site_types() as $value => $label ) {
            $type_map[ $value ] = [ 'value' => $manager->entity_type_for_site_type( $value ), 'label' => $manager->entity_type_label( $value ) ];
        }
        if ( $allow_empty_site_type ) {
            $type_map[''] = [ 'value' => 'auto', 'label' => 'Not set' ];
        }

        ob_start();
        ?>
        <?php echo $assets; ?>
        <?php echo $this->assets(); ?>
        <div class="hpc-ui hpc-primary-entity" data-hpc-primary-entity data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-action="<?php echo esc_attr( (string) $manager->config( 'ajax_action', 'hexa_save_primary_entity' ) ); ?>" data-nonce-field="<?php echo esc_attr( (string) $manager->config( 'nonce_field', 'nonce' ) ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-type-map="<?php echo esc_attr( wp_json_encode( $type_map ) ); ?>">
            <section class="hpc-card hpc-primary-settings">
                <h3><?php echo esc_html( (string) ( $args['title'] ?? 'Website & Primary Entity' ) ); ?></h3>
                <p>Classify the website and optionally bind one WordPress author as its canonical identity. HWS remains fully functional when no author is assigned.</p>

                <div class="hpc-primary-entity-fields">
                    <label class="hpc-field"><span>Website type</span><select class="hpc-primary-site-type"><?php if ( $allow_empty_site_type ) : ?><option value="" <?php selected( $site_type, '' ); ?> disabled><?php echo esc_html( $site_type_placeholder ); ?></option><?php endif; ?><?php foreach ( $manager->site_types() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $site_type, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select><small><?php echo '' === $site_type ? 'Select a website type to derive its semantic entity type.' : 'Determines the read-only semantic entity type.'; ?></small></label>
                    <div class="hpc-field"><span>Entity type</span><output class="hpc-primary-derived-type" data-value="<?php echo esc_attr( $manager->entity_type_for_site_type( $site_type ) ); ?>"><?php echo esc_html( $manager->entity_type_label( $site_type ) ); ?></output><small>Derived automatically from the website type; it is not a separate setting.</small></div>
                    <div class="hpc-field"><span>Primary author</span><?php echo CoreUi::toggle( 'primary_entity_enabled', ! empty( $settings['enabled'] ), 'Use a primary WordPress author on this site', [ 'class' => 'hpc-primary-enabled' ] ); ?><small>Optional. Enable this only on sites that need a canonical author identity.</small></div>
                    <?php if ( 1 === count( $sources ) ) : ?>
                        <?php $source = reset( $sources ); ?>
                        <div class="hpc-field"><span>Source</span><input type="hidden" class="hpc-primary-source" value="<?php echo esc_attr( (string) $source['id'] ); ?>"><output class="hpc-primary-source-readonly"><?php echo esc_html( (string) $source['label'] ); ?></output><small><?php echo esc_html( (string) $source['description'] ); ?></small></div>
                    <?php else : ?>
                        <label class="hpc-field"><span>Source</span><select class="hpc-primary-source"><option value="">Select a source</option><?php foreach ( $sources as $source ) : ?><option value="<?php echo esc_attr( $source['id'] ); ?>" <?php selected( $selected_source, $source['id'] ); ?>><?php echo esc_html( $source['label'] ); ?></option><?php endforeach; ?></select></label>
                    <?php endif; ?>
                </div>

                <div class="hpc-primary-searches">
                    <?php foreach ( $sources as $source ) : ?>
                        <?php $selected = $settings['source'] === $source['id'] ? $entity : null; ?>
                        <div class="hpc-primary-source-search" data-source-id="<?php echo esc_attr( $source['id'] ); ?>" <?php echo $selected_source === $source['id'] ? '' : 'hidden'; ?>>
                            <?php
                            ob_start();
                            ( new SmartSearchRenderer() )->render(
                                [
                                    'id' => 'hpc-primary-search-' . $source['id'], 'label' => 'Select ' . $source['label'],
                                    'placeholder' => 'Search ' . strtolower( $source['label'] ) . '...',
                                    'source' => 'user' === $source['kind'] ? 'users' : 'posts', 'post_type' => $source['post_type'] ?: 'any',
                                    'value' => $selected['id'] ?? '', 'selected_name' => $selected['name'] ?? '', 'selected_subtitle' => $selected['subtitle'] ?? '',
                                    'class' => 'hpc-primary-object-search',
                                ]
                            );
                            echo ob_get_clean();
                            ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="hpc-primary-status" aria-live="polite" aria-atomic="true"></div>
            </section>
            <div class="hpc-primary-preview-slot" data-hpc-primary-preview><?php echo $this->render_entity_preview( $entity, $manager, $args ); ?></div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed>|null $entity */
    public function render_entity_preview( ?array $entity, PrimaryEntityManager $manager, array $args = [] ): string {
        $args = array_replace( (array) $manager->config( 'render_args', [] ), $args );
        if ( ! $entity ) {
            return '<section class="hpc-primary-empty" role="status"><strong>No primary author assigned</strong><p>Assigning an author is optional. Website classification and unrelated features continue to work without one.</p></section>';
        }
        $show_field_inventory = ! array_key_exists( 'show_field_inventory', $args ) || ! empty( $args['show_field_inventory'] );
        ob_start();
        ?>
        <section class="hpc-card hpc-primary-preview">
            <div class="hpc-primary-preview-top">
                <div class="hpc-actions"><?php echo CoreUi::pill( ucfirst( (string) $entity['entity_type'] ), 'success' ); ?><?php echo CoreUi::pill( 'WordPress ' . ucfirst( (string) $entity['kind'] ), 'dark' ); ?><?php echo CoreUi::pill( 'ID ' . (int) $entity['id'], 'dark' ); ?></div>
                <div class="hpc-actions"><?php if ( $entity['edit_url'] ) : ?><?php echo CoreUi::external_link( $entity['edit_url'], 'Edit author' ); ?><?php endif; ?><?php if ( $entity['view_url'] ) : ?><?php echo CoreUi::external_link( $entity['view_url'], 'View author archive' ); ?><?php endif; ?></div>
            </div>

            <?php echo ( new EntityProfileCardRenderer() )->render( $entity ); ?>

            <?php if ( ! empty( $entity['settings']['migrated_from'] ) ) : ?><p class="hpc-small">Migrated from: <?php echo esc_html( $entity['settings']['migrated_from'] ); ?></p><?php endif; ?>
            <?php if ( $show_field_inventory ) : ?><?php echo ( new EntityFieldInventoryRenderer() )->render( $entity, [ 'standalone' => false ] ); ?><?php endif; ?>
            <?php if ( ! empty( $args['consumers'] ) ) : ?><?php echo $this->consumer_status( (array) $args['consumers'], $entity ); ?><?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    /** @param array<int,array<string,mixed>> $consumers @param array<string,mixed> $entity */
    private function consumer_status( array $consumers, array $entity ): string {
        $html = '<div class="hpc-primary-consumers"><h4>Plugin consumers</h4><div class="hpc-toggle-list">';
        foreach ( $consumers as $consumer ) {
            $active = isset( $consumer['active'] ) && is_callable( $consumer['active'] ) ? (bool) call_user_func( $consumer['active'], $entity ) : (bool) ( $consumer['active'] ?? false );
            $html .= '<div class="hpc-toggle-row">' . CoreUi::pill( $active ? 'Connected' : 'Not active', $active ? 'success' : 'warning' ) . '<div><strong>' . esc_html( (string) ( $consumer['label'] ?? 'Plugin' ) ) . '</strong><p>' . esc_html( (string) ( $consumer['description'] ?? '' ) ) . '</p></div></div>';
        }
        return $html . '</div></div>';
    }

    private function assets(): string {
        static $done = false;
        if ( $done ) return '';
        $done = true;
        return <<<'HTML'
<style>
.hpc-primary-entity{display:grid;gap:14px;max-width:100%;min-width:0}.hpc-primary-entity *{min-width:0}.hpc-primary-entity-fields{display:grid;gap:12px 16px;grid-template-columns:repeat(2,minmax(0,1fr));margin-top:16px}.hpc-primary-entity .hpc-field{margin:0}.hpc-primary-entity .hpc-field small{color:var(--hpc-muted);display:block;font-size:11px;line-height:1.4;margin-top:6px}.hpc-primary-derived-type,.hpc-primary-source-readonly{align-items:center;background:#f5f7fa;border:1px solid #cfd8e3;border-radius:6px;color:var(--hpc-ink);display:flex;font-size:14px;font-weight:750;min-height:40px;padding:8px 10px;width:100%}.hpc-primary-searches{margin-top:14px;max-width:100%}.hpc-primary-preview-slot{max-width:100%;min-width:0}.hpc-primary-preview-slot[aria-busy="true"]{opacity:.58}.hpc-primary-empty{background:#f7f9fc;border:1px dashed #c8d2df;border-radius:7px;color:var(--hpc-ink);padding:14px 16px}.hpc-primary-empty strong{display:block;font-size:13px}.hpc-primary-empty p{color:var(--hpc-muted);font-size:12px;margin:4px 0 0}.hpc-primary-preview-top{align-items:flex-start;border-bottom:1px solid var(--hpc-line);display:flex;flex-wrap:wrap;gap:12px;justify-content:space-between;margin-bottom:18px;padding-bottom:14px}.hpc-entity-profile-head{align-items:center;display:flex;gap:14px}.hpc-entity-profile-head img{border:1px solid var(--hpc-line);border-radius:8px;height:96px;object-fit:cover;width:96px}.hpc-entity-profile-head h3{font-size:21px;margin:0 0 5px}.hpc-entity-profile-head p{color:var(--hpc-ink);font-size:13px;margin:0 0 3px}.hpc-entity-profile-head span{color:var(--hpc-muted);font-size:12px}.hpc-entity-profile-section{border-top:1px solid #e7ebf1;margin-top:16px;padding-top:15px}.hpc-entity-profile-section h4,.hpc-primary-field-groups>h4,.hpc-primary-consumers>h4{font-size:13px;margin:0 0 10px}.hpc-entity-profile-details{display:grid;gap:0 18px;grid-template-columns:repeat(2,minmax(0,1fr));margin:0}.hpc-entity-profile-details>div{border-bottom:1px solid #edf1f6;display:grid;gap:8px;grid-template-columns:118px minmax(0,1fr);padding:8px 0}.hpc-entity-profile-details dt{color:var(--hpc-muted);font-size:11px;font-weight:750}.hpc-entity-profile-details dd{font-size:12px;margin:0;overflow-wrap:anywhere}.hpc-entity-profile-details .is-empty{color:var(--hpc-muted);font-style:italic}.hpc-entity-socials{margin:0}.hpc-entity-socials>div{border-bottom:1px solid #edf1f6;display:grid;gap:12px;grid-template-columns:118px minmax(0,1fr);padding:8px 0}.hpc-entity-socials dt{color:var(--hpc-muted);font-size:11px;font-weight:750}.hpc-entity-socials dd{font-size:12px;margin:0;overflow-wrap:anywhere}.hpc-entity-socials a{color:var(--hpc-blue);text-decoration:underline;text-underline-offset:2px}.hpc-entity-socials a:hover{color:#1d35bd}.hpc-entity-photos{display:grid;gap:10px;grid-template-columns:repeat(auto-fill,minmax(112px,1fr))}.hpc-entity-photos figure{margin:0}.hpc-entity-photos img{aspect-ratio:1/1;border:1px solid var(--hpc-line);border-radius:7px;display:block;height:auto;object-fit:cover;width:100%}.hpc-entity-photos figcaption{color:var(--hpc-muted);font-size:10px;margin-top:4px}.hpc-entity-biography{white-space:pre-line}.hpc-primary-field-groups,.hpc-primary-consumers{border-top:1px solid var(--hpc-line);margin-top:18px;padding-top:16px}.hpc-primary-field-table{display:grid;grid-template-columns:minmax(130px,.75fr) minmax(80px,.35fr) minmax(0,1.5fr);overflow-wrap:anywhere;width:100%}.hpc-primary-field-table>div{border-bottom:1px solid #e7ebf1;padding:9px}.hpc-primary-field-table .head{background:#f5f7fa;font-size:11px;font-weight:800;text-transform:uppercase}.hpc-primary-field-table small{color:var(--hpc-muted);display:block;margin-top:3px}.hpc-primary-field-table .empty{color:var(--hpc-muted);font-style:italic}.hpc-primary-entity.is-saving{pointer-events:none}.hpc-primary-status{color:var(--hpc-muted);font-size:12px;min-height:18px;margin-top:10px}.hpc-primary-status.is-error{color:#b42318}@media(max-width:760px){.hpc-primary-entity-fields,.hpc-entity-profile-details{grid-template-columns:1fr}.hpc-entity-profile-details>div{grid-template-columns:105px minmax(0,1fr)}.hpc-entity-socials>div{grid-template-columns:90px minmax(0,1fr)}.hpc-primary-field-table{display:block}.hpc-primary-field-table .head{display:none}.hpc-primary-field-table>div{border-bottom:0;padding:5px 0}.hpc-primary-field-table>div:nth-child(3n){border-bottom:1px solid #e7ebf1;margin-bottom:8px;padding-bottom:10px}.hpc-entity-profile-head{align-items:flex-start}.hpc-entity-profile-head img{height:76px;width:76px}}
</style>
<script>
(function(){
if(window.hexaPrimaryEntityReady)return;window.hexaPrimaryEntityReady=true;
function sourceValue(root){var field=root.querySelector('.hpc-primary-source');return field?field.value||'':'';}
function updateDerivedType(root){var select=root.querySelector('.hpc-primary-site-type');var output=root.querySelector('.hpc-primary-derived-type');if(!select||!output)return;var map={};try{map=JSON.parse(root.dataset.typeMap||'{}');}catch(e){}var item=map[select.value]||{value:'auto',label:'Not set'};output.value=item.label||'Not set';output.textContent=item.label||'Not set';output.dataset.value=item.value||'auto';}
function save(root,context){
    var source=sourceValue(root);var search=root.querySelector('.hpc-primary-source-search[data-source-id="'+CSS.escape(source)+'"]');var value=search?search.querySelector('.hpc-smart-search-value'):null;var objectId=value?value.value:'';var status=root.querySelector('.hpc-primary-status');var slot=root.querySelector('[data-hpc-primary-preview]');var body=new URLSearchParams();
    body.set('action',root.dataset.action||'');body.set(root.dataset.nonceField||'nonce',root.dataset.nonce||'');body.set('site_type',root.querySelector('.hpc-primary-site-type').value||'');body.set('enabled',root.querySelector('.hpc-primary-enabled input').checked?'1':'0');body.set('source',source);body.set('object_id',objectId);body.set('entity_type',(root.querySelector('.hpc-primary-derived-type')||{}).dataset?.value||'auto');
    if(root._hpcPrimarySaveController)root._hpcPrimarySaveController.abort();root._hpcPrimarySaveController=new AbortController();var controller=root._hpcPrimarySaveController;root.classList.add('is-saving');if(slot)slot.setAttribute('aria-busy','true');if(status){status.classList.remove('is-error');status.textContent='Saving...';}
    return fetch(root.dataset.ajaxUrl||window.ajaxurl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString(),signal:controller.signal}).then(function(response){return response.json()}).then(function(payload){if(!payload||!payload.success)throw new Error(payload&&payload.data&&payload.data.message?payload.data.message:'Save failed');if(payload.data&&typeof payload.data.preview_html==='string'&&slot){slot.innerHTML=payload.data.preview_html;if(window.hexaPluginCoreInitPersistentDetails)window.hexaPluginCoreInitPersistentDetails(slot);}if(status)status.textContent=context==='selection'?'Primary author saved.':(payload.data.message||'Saved.');root.dispatchEvent(new CustomEvent('hexa-primary-entity-saved',{bubbles:true,detail:payload.data||{}}));}).catch(function(error){if(error&&error.name==='AbortError')return;if(status){status.classList.add('is-error');status.textContent=error&&error.message?error.message:'Unable to save.';}}).finally(function(){if(root._hpcPrimarySaveController!==controller)return;root._hpcPrimarySaveController=null;root.classList.remove('is-saving');if(slot)slot.removeAttribute('aria-busy');});
}
document.addEventListener('change',function(event){var root=event.target.closest('[data-hpc-primary-entity]');if(!root)return;var siteType=event.target.closest('.hpc-primary-site-type');if(siteType){updateDerivedType(root);save(root,'settings');return;}var source=event.target.closest('.hpc-primary-source');if(source){root.querySelectorAll('.hpc-primary-source-search').forEach(function(item){item.hidden=item.dataset.sourceId!==source.value;});save(root,'settings');return;}if(event.target.closest('.hpc-primary-enabled input'))save(root,'settings');});
document.addEventListener('hexa-search-selected',function(event){var root=event.target.closest('[data-hpc-primary-entity]');if(!root)return;var toggle=root.querySelector('.hpc-primary-enabled input');if(toggle)toggle.checked=true;save(root,'selection');});
document.querySelectorAll('[data-hpc-primary-entity]').forEach(updateDerivedType);
})();
</script>
HTML;
    }
}
