<?php

namespace Hexa\PluginCore\PluginChecks;

use Hexa\PluginCore\WpAdminComponents\CoreUi;
use Hexa\PluginCore\WpAdminComponents\DynamicButton;

final class PluginChecksRenderer {
    /**
     * @param array<int,PluginCheckDefinition|array<string,mixed>> $definitions
     * @param array<string,mixed> $args
     */
    public function render( array $definitions, array $args = [] ): string {
        $args = $this->args( $args );

        ob_start();
        CoreUi::render_assets();
        DynamicButton::render_assets();
        ?>
        <div class="hpc-ui hpc-plugin-checks" data-hpc-plugin-checks data-ajax-url="<?php echo esc_url( $args['ajax_url'] ); ?>" data-status-action="<?php echo esc_attr( $args['actions']['status'] ); ?>" data-refresh-action="<?php echo esc_attr( $args['actions']['refresh'] ); ?>" data-install-action="<?php echo esc_attr( $args['actions']['install_activate'] ); ?>" data-activate-action="<?php echo esc_attr( $args['actions']['activate'] ); ?>" data-nonce-field="<?php echo esc_attr( $args['nonce_field'] ); ?>" data-nonce="<?php echo esc_attr( $args['nonce'] ); ?>">
            <?php echo $this->assets(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <section class="hpc-plugin-checks-hero">
                <div>
                    <p class="hpc-plugin-checks-kicker"><?php echo esc_html( (string) $args['eyebrow'] ); ?></p>
                    <h2><?php echo esc_html( (string) $args['title'] ); ?></h2>
                    <p><?php echo esc_html( (string) $args['description'] ); ?></p>
                </div>
                <div class="hpc-plugin-checks-hero-actions">
                    <?php echo $this->dynamic_button( [ 'label' => 'Refresh checks', 'working_label' => 'Refreshing...', 'success_label' => 'Refreshed', 'class' => 'hpc-button secondary', 'attrs' => [ 'data-plugin-checks-refresh' => true ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo $this->dynamic_button( [ 'label' => 'Install and activate missing', 'working_label' => 'Processing...', 'success_label' => 'Processed', 'class' => 'hpc-button', 'attrs' => [ 'data-plugin-checks-install-all' => true ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php if ( function_exists( 'admin_url' ) ) : ?>
                        <?php echo CoreUi::external_link( admin_url( 'plugins.php' ), 'Open plugins', 'hpc-button secondary' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endif; ?>
                </div>
            </section>
            <div data-plugin-checks-content>
                <?php echo $this->render_content( $definitions, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <section class="hpc-plugin-checks-log" aria-live="polite">
                <div class="hpc-plugin-checks-log-head">
                    <strong>Activity log</strong>
                    <button type="button" class="hpc-button secondary" data-plugin-checks-clear-log>Clear</button>
                </div>
                <pre data-plugin-checks-log>Ready.</pre>
            </section>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array<int,PluginCheckDefinition|array<string,mixed>> $definitions
     * @param array<string,mixed> $args
     */
    public function render_content( array $definitions, array $args = [] ): string {
        $args     = $this->args( $args );
        $items    = PluginCheckService::normalize_definitions( $definitions );
        $statuses = PluginCheckService::statuses( $items );
        $summary  = PluginCheckService::summary( $statuses );
        $status_by_id = [];
        foreach ( $statuses as $status ) {
            $status_by_id[ (string) $status['id'] ] = $status;
        }

        ob_start();
        ?>
        <section class="hpc-plugin-checks-summary">
            <span><?php echo $this->status_icon( true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo (int) $summary['ready']; ?> ready</span>
            <span><?php echo $this->status_icon( false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo (int) $summary['missing']; ?> missing</span>
            <span><?php echo $this->status_icon( false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo (int) $summary['inactive']; ?> inactive</span>
            <span><span class="hpc-plugin-check-fa hpc-plugin-check-fa-warning" aria-hidden="true"></span> <?php echo (int) $summary['outdated']; ?> outdated</span>
            <span><?php echo (int) $summary['total']; ?> configured</span>
        </section>
        <section class="hpc-plugin-checks-list">
            <?php foreach ( $items as $definition ) : ?>
                <?php echo $this->render_row( $definition, $status_by_id[ $definition->id ] ?? PluginCheckService::status( $definition ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endforeach; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array<string,mixed> $status
     */
    private function render_row( PluginCheckDefinition $definition, array $status ): string {
        $tone = ! empty( $status['ok'] ) ? 'is-ready' : 'needs-attention';

        ob_start();
        ?>
        <article class="hpc-plugin-check-row <?php echo esc_attr( $tone ); ?>" data-plugin-check-card data-plugin-id="<?php echo esc_attr( $definition->id ); ?>" data-plugin-installed="<?php echo ! empty( $status['installed'] ) ? '1' : '0'; ?>" data-plugin-active="<?php echo ! empty( $status['active'] ) ? '1' : '0'; ?>" data-plugin-installable="<?php echo ! empty( $status['installable'] ) ? '1' : '0'; ?>">
            <div class="hpc-plugin-check-plugin">
                <h3><?php echo esc_html( $definition->name ); ?></h3>
                <div class="hpc-plugin-check-list" aria-label="<?php echo esc_attr( $definition->name . ' checklist' ); ?>">
                    <?php echo $this->check_item( 'Installed', ! empty( $status['installed'] ), ! empty( $definition->checks['installed'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo $this->check_item( 'Active', ! empty( $status['active'] ), ! empty( $definition->checks['active'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo $this->check_item( 'Up to date', ! empty( $status['up_to_date'] ), ! empty( $definition->checks['up_to_date'] ) && ! empty( $status['installed'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <p class="hpc-plugin-check-path"><?php echo esc_html( (string) $status['plugin_file'] ?: $definition->plugin_file ?: $definition->slug ); ?></p>
                <p class="hpc-plugin-check-source">Source: <?php echo esc_html( $this->source_label( $definition->source ) ); ?></p>
            </div>
            <div class="hpc-plugin-check-version">
                <?php if ( ! empty( $status['version'] ) ) : ?>
                    <strong><?php echo esc_html( (string) $status['version'] ); ?></strong>
                    <span>Current version</span>
                <?php else : ?>
                    <strong>Not installed</strong>
                    <span>No local version</span>
                <?php endif; ?>
                <?php if ( ! empty( $status['update_available'] ) ) : ?>
                    <em>Update: <?php echo esc_html( (string) ( $status['new_version'] ?: 'available' ) ); ?></em>
                <?php endif; ?>
            </div>
            <div class="hpc-plugin-check-actions">
                <?php echo $this->actions_html( $definition, $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <?php if ( '' !== $definition->notes ) : ?>
                <p class="hpc-plugin-check-notes"><?php echo wp_kses_post( $definition->notes ); ?></p>
            <?php endif; ?>
        </article>
        <?php
        return (string) ob_get_clean();
    }

    private function check_item( string $label, bool $passed, bool $checked ): string {
        if ( ! $checked ) {
            return '<span class="hpc-plugin-check-item is-muted">• ' . esc_html( $label ) . ': not checked</span>';
        }

        return '<span class="hpc-plugin-check-item ' . ( $passed ? 'is-pass' : 'is-fail' ) . '">' . $this->status_icon( $passed ) . ' ' . esc_html( $label ) . '</span>';
    }

    /**
     * @param array<string,mixed> $status
     */
    private function actions_html( PluginCheckDefinition $definition, array $status ): string {
        if ( empty( $status['installed'] ) ) {
            if ( ! empty( $status['installable'] ) ) {
                return $this->dynamic_button(
                    [
                        'label'         => 'Install and activate',
                        'working_label' => 'Installing...',
                        'success_label' => 'Installed',
                        'error_label'   => 'Failed',
                        'class'         => 'hpc-button',
                        'attrs'         => [
                            'data-plugin-check-action' => 'install_activate',
                            'data-plugin-id'           => $definition->id,
                        ],
                    ]
                );
            }

            if ( ! empty( $status['download_url'] ) ) {
                return CoreUi::external_link( (string) $status['download_url'], (string) $status['download_label'], 'hpc-button secondary' );
            }

            return '<span class="hpc-plugin-check-muted">Manual install required.</span>';
        }

        if ( empty( $status['active'] ) && ! empty( $definition->checks['active'] ) ) {
            return $this->dynamic_button(
                [
                    'label'         => 'Activate',
                    'working_label' => 'Activating...',
                    'success_label' => 'Activated',
                    'error_label'   => 'Failed',
                    'class'         => 'hpc-button secondary',
                    'attrs'         => [
                        'data-plugin-check-action' => 'activate',
                        'data-plugin-id'           => $definition->id,
                    ],
                ]
            );
        }

        if ( ! empty( $status['update_available'] ) && function_exists( 'admin_url' ) ) {
            return CoreUi::external_link( admin_url( 'update-core.php' ), 'Open updates', 'hpc-button secondary' );
        }

        return '<span class="hpc-plugin-check-ready">' . $this->status_icon( true ) . ' Ready</span>';
    }

    /**
     * @param array<string,mixed> $args
     */
    private function dynamic_button( array $args ): string {
        $args['render_assets'] = false;

        return DynamicButton::render( $args );
    }

    private function status_icon( bool $passed ): string {
        return '<span class="hpc-plugin-check-fa ' . ( $passed ? 'hpc-plugin-check-fa-check' : 'hpc-plugin-check-fa-xmark' ) . '" aria-hidden="true"></span>';
    }

    private function source_label( string $source ): string {
        return match ( $source ) {
            'wordpress_org' => 'WordPress.org',
            'github' => 'GitHub',
            'pro' => 'Pro/manual',
            default => 'Manual',
        };
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    private function args( array $args ): array {
        $action_prefix = isset( $args['action_prefix'] ) ? trim( (string) $args['action_prefix'], '_' ) : 'hexa_plugin_checks';
        $actions       = isset( $args['actions'] ) && is_array( $args['actions'] ) ? $args['actions'] : [];

        $normalized = array_merge(
            [
                'title'       => 'Plugin Checks',
                'eyebrow'     => 'Required plugins',
                'description' => 'Check installation, activation, and update status for the plugins this feature depends on.',
                'ajax_url'    => function_exists( 'admin_url' ) ? admin_url( 'admin-ajax.php' ) : '',
                'nonce'       => '',
                'nonce_field' => 'nonce',
            ],
            $args
        );
        $normalized['actions'] = [
            'status'           => $actions['status'] ?? $action_prefix . '_status',
            'refresh'          => $actions['refresh'] ?? $action_prefix . '_refresh',
            'install_activate' => $actions['install_activate'] ?? $action_prefix . '_install_activate',
            'activate'         => $actions['activate'] ?? $action_prefix . '_activate',
        ];

        return $normalized;
    }

    private function assets(): string {
        static $done = false;
        if ( $done ) {
            return '';
        }
        $done = true;

        return <<<'HTML'
<style>
.hpc-plugin-checks{display:grid;gap:16px}
.hpc-plugin-checks-hero{align-items:flex-start;background:#fff;border:1px solid var(--hpc-line);border-radius:8px;display:grid;gap:16px;grid-template-columns:minmax(0,1fr) auto;padding:18px}
.hpc-plugin-checks-kicker{color:var(--hpc-blue)!important;font-size:12px!important;font-weight:900!important;letter-spacing:.08em;margin:0 0 6px!important;text-transform:uppercase}
.hpc-plugin-checks-hero h2{font-size:24px;line-height:1.2;margin:0 0 8px}
.hpc-plugin-checks-hero p{max-width:780px}
.hpc-plugin-checks-hero-actions{align-items:center;display:flex;flex-wrap:wrap;gap:10px;justify-content:flex-end}
.hpc-plugin-checks-summary{align-items:center;background:#fff;border:1px solid var(--hpc-line);border-radius:8px;display:flex;flex-wrap:wrap;font-size:13px;font-weight:800;gap:14px;padding:12px 14px}
.hpc-plugin-checks-list{background:#fff;border:1px solid var(--hpc-line);border-radius:8px;display:grid;overflow:hidden}
.hpc-plugin-check-row{align-items:center;background:#fff;border-left:5px solid var(--hpc-green);border-bottom:1px solid #edf1f6;display:grid;gap:16px;grid-template-columns:minmax(0,1fr) minmax(140px,.22fr) auto;padding:14px 16px}
.hpc-plugin-check-row:last-child{border-bottom:0}
.hpc-plugin-check-row.needs-attention{border-left-color:var(--hpc-amber)}
.hpc-plugin-check-plugin h3{font-size:16px;margin:0 0 7px}
.hpc-plugin-check-path{color:var(--hpc-muted)!important;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;font-size:12px!important;margin:0!important;word-break:break-all}
.hpc-plugin-check-source{color:var(--hpc-muted)!important;font-size:12px!important;margin:5px 0 0!important}
.hpc-plugin-check-list{align-items:center;display:flex;flex-wrap:wrap;gap:7px 12px;margin:0 0 7px}
.hpc-plugin-check-item{font-size:13px;font-weight:800;line-height:1.25;white-space:nowrap}
.hpc-plugin-check-item.is-pass{color:var(--hpc-green)}
.hpc-plugin-check-item.is-fail{color:var(--hpc-red)}
.hpc-plugin-check-item.is-muted{color:var(--hpc-muted)}
.hpc-plugin-check-fa{display:inline-flex;font-family:"Font Awesome 6 Free","Font Awesome 5 Free","FontAwesome",Arial,sans-serif;font-size:13px;font-weight:900;line-height:1;margin-right:2px}
.hpc-plugin-check-fa-check{color:var(--hpc-green)}
.hpc-plugin-check-fa-check:before{content:"\f00c"}
.hpc-plugin-check-fa-xmark{color:var(--hpc-red)}
.hpc-plugin-check-fa-xmark:before{content:"\f00d"}
.hpc-plugin-check-fa-warning{color:var(--hpc-amber)}
.hpc-plugin-check-fa-warning:before{content:"!"}
.hpc-plugin-check-version{display:grid;gap:2px}
.hpc-plugin-check-version strong{color:var(--hpc-ink);font-size:13px}
.hpc-plugin-check-version span{color:var(--hpc-muted);font-size:12px}
.hpc-plugin-check-version em{color:var(--hpc-amber);font-size:12px;font-style:normal;font-weight:800}
.hpc-plugin-check-notes{background:#f8fafc;border:1px solid #e4e8ef;border-radius:8px;color:#3f4d63;grid-column:1 / -1;margin:0!important;padding:9px 10px}
.hpc-plugin-check-actions{align-items:center;display:flex;flex-wrap:wrap;gap:10px;justify-content:flex-end}
.hpc-plugin-check-ready{color:var(--hpc-green);display:inline-flex;font-weight:900;line-height:1;padding:8px 0}
.hpc-plugin-check-muted{color:var(--hpc-muted);font-style:italic}
.hpc-plugin-checks-log{background:#111827;border-radius:8px;color:#dbe7f3;overflow:hidden}
.hpc-plugin-checks-log-head{align-items:center;border-bottom:1px solid #263244;display:flex;justify-content:space-between;padding:10px 12px}
.hpc-plugin-checks-log-head strong{color:#fff}
.hpc-plugin-checks-log-head .hpc-button{padding:7px 10px}
.hpc-plugin-checks-log pre{background:transparent;color:#dbe7f3;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;margin:0;max-height:240px;overflow:auto;padding:12px;white-space:pre-wrap}
@media(max-width:1100px){.hpc-plugin-check-row{grid-template-columns:1fr}.hpc-plugin-check-actions{justify-content:flex-start}}
@media(max-width:980px){.hpc-plugin-checks-hero{grid-template-columns:1fr}.hpc-plugin-checks-hero-actions{justify-content:flex-start}}
</style>
<script>
(function(){
  if(window.HexaPluginChecksReady)return; window.HexaPluginChecksReady=true;
  function log(root,message){
    var box=root.querySelector('[data-plugin-checks-log]');
    if(!box)return;
    var stamp=new Date().toLocaleTimeString();
    var current=(box.textContent||'').trim();
    if(current==='Ready.') current='';
    box.textContent=(current?current+"\n":"")+"["+stamp+"] "+message;
    box.scrollTop=box.scrollHeight;
  }
  function body(root,action,extra){
    var p=new URLSearchParams();
    p.set('action',action);
    p.set(root.dataset.nonceField||'nonce',root.dataset.nonce||'');
    Object.keys(extra||{}).forEach(function(k){p.set(k,extra[k]);});
    return p;
  }
  function post(root,action,extra){
    return fetch(root.dataset.ajaxUrl||window.ajaxurl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body(root,action,extra).toString()}).then(function(r){return r.json();});
  }
  function replaceContent(root,payload){
    var target=root.querySelector('[data-plugin-checks-content]');
    if(target&&payload&&payload.content_html)target.innerHTML=payload.content_html;
    if(payload&&payload.log){payload.log.forEach(function(line){log(root,line);});}
  }
  function refresh(root,button){
    if(button&&window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.start(button);
    return post(root,root.dataset.statusAction,{}).then(function(res){
      if(!res||!res.success)throw new Error((res&&res.data&&res.data.message)||'Status refresh failed');
      replaceContent(root,res.data);
      if(button&&window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.success(button,'Refreshed');
      return res.data;
    }).catch(function(err){
      log(root,'ERROR: '+(err&&err.message?err.message:'Status refresh failed'));
      if(button&&window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.error(button,'Failed');
      throw err;
    });
  }
  document.addEventListener('click',function(event){
    var root=event.target.closest('[data-hpc-plugin-checks]');
    if(!root)return;
    var clear=event.target.closest('[data-plugin-checks-clear-log]');
    if(clear){var box=root.querySelector('[data-plugin-checks-log]'); if(box)box.textContent='Ready.'; return;}
    var refreshBtn=event.target.closest('[data-plugin-checks-refresh]');
    if(refreshBtn){
      if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.start(refreshBtn);
      post(root,root.dataset.refreshAction,{}).then(function(res){
        if(!res||!res.success)throw new Error((res&&res.data&&res.data.message)||'Refresh failed');
        replaceContent(root,res.data);
        if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.success(refreshBtn,'Refreshed');
      }).catch(function(err){log(root,'ERROR: '+(err&&err.message?err.message:'Refresh failed')); if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.error(refreshBtn,'Failed');});
      return;
    }
    var allBtn=event.target.closest('[data-plugin-checks-install-all]');
    if(allBtn){
      var actions=Array.prototype.slice.call(root.querySelectorAll('[data-plugin-check-action="install_activate"],[data-plugin-check-action="activate"]')).map(function(btn){return {id:btn.dataset.pluginId,mode:btn.dataset.pluginCheckAction};});
      if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.start(allBtn);
      log(root,'Starting one-click plugin processing for '+actions.length+' plugin(s).');
      actions.reduce(function(p,item){
        return p.then(function(){
          var action=item.mode==='activate'?root.dataset.activateAction:root.dataset.installAction;
          log(root,'Processing '+item.id+'.');
          return post(root,action,{plugin_id:item.id}).then(function(res){
            if(!res||!res.success)throw new Error((res&&res.data&&res.data.message)||('Failed: '+item.id));
            if(res.data&&res.data.log)res.data.log.forEach(function(line){log(root,line);});
          });
        });
      },Promise.resolve()).then(function(){return refresh(root,null);}).then(function(){if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.success(allBtn,'Processed');}).catch(function(err){log(root,'ERROR: '+(err&&err.message?err.message:'One-click processing failed')); if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.error(allBtn,'Failed');});
      return;
    }
    var actionBtn=event.target.closest('[data-plugin-check-action]');
    if(actionBtn){
      var actionName=actionBtn.dataset.pluginCheckAction==='activate'?root.dataset.activateAction:root.dataset.installAction;
      if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.start(actionBtn);
      log(root,'Processing '+(actionBtn.dataset.pluginId||'plugin')+'.');
      post(root,actionName,{plugin_id:actionBtn.dataset.pluginId||''}).then(function(res){
        if(!res||!res.success)throw new Error((res&&res.data&&res.data.message)||'Plugin action failed');
        replaceContent(root,res.data);
        if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.success(actionBtn,'Done');
      }).catch(function(err){log(root,'ERROR: '+(err&&err.message?err.message:'Plugin action failed')); if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.error(actionBtn,'Failed');});
    }
  });
})();
</script>
HTML;
    }
}
