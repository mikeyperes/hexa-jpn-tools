<?php

namespace Hexa\PluginCore\FieldStructures;

use Hexa\PluginCore\WpAdminComponents\CoreUi;

final class AcfFieldGroupRenderer {
    public function render( AcfFieldGroupRegistry $registry, array $args = [] ): string {
        ob_start();
        CoreUi::render_assets();
        $assets = (string) ob_get_clean();
        $title = (string) ( $args['title'] ?? 'Additional ACF Structures' );
        $description = (string) ( $args['description'] ?? 'Optional field groups that target users, settings, or other objects outside the custom post types above.' );
        $persist = sanitize_key( (string) ( $args['persist_prefix'] ?? $registry->store()->option_name() ) );
        ob_start();
        ?>
        <?php echo $assets . $this->assets(); ?>
        <div class="hpc-ui hpc-acf-registry" data-hpc-acf-registry data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-action="<?php echo esc_attr( (string) $registry->config( 'ajax_action' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( (string) $registry->config( 'nonce_action' ) ) ); ?>" data-nonce-field="<?php echo esc_attr( (string) $registry->config( 'nonce_field', 'nonce' ) ); ?>">
            <section class="hpc-card hpc-acf-registry-intro"><h3><?php echo esc_html( $title ); ?></h3><p><?php echo esc_html( $description ); ?></p></section>
            <div class="hpc-stack">
                <?php foreach ( $registry->resolved_definitions() as $definition ) : ?>
                    <?php echo $this->card( $definition, $persist ); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $definition */
    private function card( array $definition, string $persist ): string {
        $enabled = ! empty( $definition['enabled'] );
        $available = ! empty( $definition['available'] );
        $registered = $available && function_exists( 'acf_get_field_group' ) && '' !== $definition['group_key'] && (bool) acf_get_field_group( $definition['group_key'] );
        $meta = CoreUi::pill( $enabled ? 'Enabled' : 'Disabled', $enabled ? 'success' : 'warning' )
            . CoreUi::pill( $registered ? 'Registered' : ( $available ? 'Not registered' : 'Superseded' ), $registered ? 'success' : ( $available && $enabled ? 'danger' : 'dark' ) );
        $details = '';
        if ( $definition['fields'] ) {
            $details .= '<p><strong>Fields</strong></p><ul class="hpc-list"><li>' . implode( '</li><li>', array_map( 'esc_html', $definition['fields'] ) ) . '</li></ul>';
        }
        if ( $definition['dependencies'] ) {
            $details .= '<p><strong>Dependencies:</strong> ' . esc_html( implode( ', ', $definition['dependencies'] ) ) . '</p>';
        }
        ob_start();
        ?>
        <div class="hpc-acf-group" data-field-group-id="<?php echo esc_attr( $definition['id'] ); ?>">
            <p><?php echo esc_html( $definition['description'] ); ?></p>
            <dl class="hpc-acf-facts"><div><dt>ACF group</dt><dd><span class="hpc-code"><?php echo esc_html( $definition['group_key'] ); ?></span></dd></div><div><dt>Location</dt><dd><?php echo esc_html( $definition['location'] ); ?></dd></div></dl>
            <?php if ( ! $available ) : ?><p class="hpc-small">A dedicated owner plugin has replaced this compatibility structure.</p><?php endif; ?>
            <?php echo CoreUi::inline_details( 'Detailed field breakdown', $details ?: '<p>No field summary supplied.</p>' ); ?>
            <div class="hpc-actions hpc-actions-bottom">
                <?php echo CoreUi::toggle( 'acf_group_' . $definition['id'], $enabled, 'Enable this ACF structure', [ 'class' => 'hpc-acf-group-toggle', 'disabled' => ! $available ] ); ?>
                <span class="hpc-acf-group-status" aria-live="polite"></span>
            </div>
        </div>
        <?php
        return CoreUi::collapsible( [ 'title' => $definition['label'], 'body_html' => (string) ob_get_clean(), 'meta_html' => $meta, 'open' => false, 'persist_key' => $persist . '-acf-' . $definition['id'], 'query_state' => false ] );
    }

    private function assets(): string {
        static $done = false;
        if ( $done ) return '';
        $done = true;
        return <<<'HTML'
<style>.hpc-acf-registry-intro{margin-bottom:14px}.hpc-acf-registry-intro h3{font-size:20px}.hpc-acf-facts{display:grid;gap:10px;grid-template-columns:repeat(2,minmax(0,1fr));margin:12px 0}.hpc-acf-facts div{background:#f8fafc;border:1px solid #e3e8f0;border-radius:8px;padding:9px 10px}.hpc-acf-facts dt{color:#65758b;font-size:11px;font-weight:800;text-transform:uppercase}.hpc-acf-facts dd{margin:4px 0 0;overflow-wrap:anywhere}.hpc-acf-group.is-saving{opacity:.7;pointer-events:none}.hpc-acf-group-status{color:var(--hpc-muted);font-size:13px}@media(max-width:700px){.hpc-acf-facts{grid-template-columns:1fr}}</style>
<script>(function(){if(window.hexaAcfRegistryReady)return;window.hexaAcfRegistryReady=true;document.addEventListener('change',function(event){var input=event.target.closest('.hpc-acf-group-toggle input');if(!input)return;var group=input.closest('.hpc-acf-group');var root=input.closest('[data-hpc-acf-registry]');if(!group||!root)return;var status=group.querySelector('.hpc-acf-group-status');var body=new URLSearchParams();body.set('action',root.dataset.action||'');body.set(root.dataset.nonceField||'nonce',root.dataset.nonce||'');body.set('field_group_id',group.dataset.fieldGroupId||'');body.set('enabled',input.checked?'1':'0');group.classList.add('is-saving');if(status)status.textContent='Saving...';fetch(root.dataset.ajaxUrl||window.ajaxurl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()}).then(function(response){return response.json()}).then(function(payload){if(!payload||!payload.success)throw new Error(payload&&payload.data&&payload.data.message?payload.data.message:'Save failed');if(status)status.textContent=payload.data.message||'Saved.'}).catch(function(error){input.checked=!input.checked;if(status)status.textContent=error.message||'Unable to save.'}).finally(function(){group.classList.remove('is-saving')})})})();</script>
HTML;
    }
}
