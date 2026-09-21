<?php

namespace Hexa\PluginCore\CorePackageUpdates;

use Hexa\PluginCore\WpAdminComponents\CoreUi;

final class CorePackagePanelRenderer {
    private CorePackageConfig $config;

    public function __construct( CorePackageConfig $config ) {
        $this->config = $config;
    }

    public function render(): void {
        CoreUi::render_assets();

        $status      = ( new CorePackageStatus( $this->config ) )->get();
        $controller  = new CorePackageAjaxController( $this->config );
        $actions     = $controller->actions();
        $nonce       = wp_create_nonce( $this->config->nonce_action() );
        $dom         = 'hexa-plugin-core-package-' . md5( $this->config->core_root() );
        $needs_class = $status['update_available'] ? 'danger' : 'success';
        $needs_text  = $status['update_available'] ? 'Outdated' : 'Current';
        ?>
        <div id="<?php echo esc_attr( $dom ); ?>" class="hpc-ui hexa-plugin-core-package-updater">
            <style>
                #<?php echo esc_attr( $dom ); ?> .hexa-core-kv{display:grid;gap:8px;grid-template-columns:150px minmax(0,1fr);margin:0}
                #<?php echo esc_attr( $dom ); ?> .hexa-core-kv dt{color:var(--hpc-muted);font-size:12px;font-weight:800;text-transform:uppercase}
                #<?php echo esc_attr( $dom ); ?> .hexa-core-kv dd{margin:0;min-width:0}
                #<?php echo esc_attr( $dom ); ?> .hexa-core-version{align-items:center;display:grid;gap:14px;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr)}
                #<?php echo esc_attr( $dom ); ?> .hexa-core-version-side{background:#f8fafc;border:1px solid var(--hpc-line);border-radius:8px;padding:16px;text-align:center}
                #<?php echo esc_attr( $dom ); ?> .hexa-core-version-num{display:block;font-size:32px;font-weight:900;line-height:1.1}
                #<?php echo esc_attr( $dom ); ?> .hexa-core-status{color:var(--hpc-muted);font-size:13px;margin-top:10px}
                #<?php echo esc_attr( $dom ); ?> .hexa-core-log{border:1px solid var(--hpc-line);border-radius:8px;margin-top:12px;overflow:hidden}
                #<?php echo esc_attr( $dom ); ?> .hexa-core-log-head{align-items:center;background:#111827;color:#f8fafc;display:flex;font-weight:800;gap:10px;justify-content:space-between;padding:10px 12px}
                #<?php echo esc_attr( $dom ); ?> .hexa-core-log-body{background:#0f1720;color:#dbe7f3;list-style:none;margin:0;max-height:260px;overflow:auto;padding:0}
                #<?php echo esc_attr( $dom ); ?> .hexa-core-log-row{border-top:1px solid #263244;display:grid;gap:8px;grid-template-columns:24px 1fr auto;margin:0;padding:8px 12px}
                #<?php echo esc_attr( $dom ); ?> .hexa-core-log-row.is-error{color:#ffb4c0}
                #<?php echo esc_attr( $dom ); ?> .hexa-core-log-row.is-warn{color:#ffe099}
                #<?php echo esc_attr( $dom ); ?> .hexa-core-log-row.is-done{color:#9ee6b2}
                #<?php echo esc_attr( $dom ); ?> .hexa-core-log-foot{background:#111827;color:#f8fafc;display:none;padding:10px 12px}
                #<?php echo esc_attr( $dom ); ?> .hexa-core-spin{animation:hexaCoreUpdaterSpin .7s linear infinite;border:2px solid rgba(49,87,213,.25);border-radius:50%;border-top-color:#3157d5;display:inline-block;height:14px;width:14px}
                @keyframes hexaCoreUpdaterSpin{to{transform:rotate(360deg)}}
                @media(max-width:900px){#<?php echo esc_attr( $dom ); ?> .hpc-grid.two,#<?php echo esc_attr( $dom ); ?> .hexa-core-version,#<?php echo esc_attr( $dom ); ?> .hexa-core-kv{grid-template-columns:1fr}}
            </style>

            <details class="hpc-section hexa-core-parent" open data-hpc-persist-key="<?php echo esc_attr( $dom . '-parent' ); ?>">
                <summary>
                    <span><?php echo esc_html( $this->config->package_name() ); ?> Git Updater</span>
                    <span class="hpc-pill <?php echo esc_attr( $needs_class ); ?>" data-role="badge"><?php echo esc_html( $needs_text ); ?></span>
                </summary>
                <div class="hpc-section-body hpc-stack">
                    <div class="hpc-grid two">
                        <article class="hpc-card">
                            <h3>Git Report</h3>
                            <dl class="hexa-core-kv">
                                <dt>Git repo</dt>
                                <dd><code data-role="git-repo"><?php echo esc_html( $status['github_repo'] ); ?></code></dd>
                                <dt>Git URL</dt>
                                <dd><a class="hpc-external" href="<?php echo esc_url( $status['github_url'] ); ?>" target="_blank" rel="noopener" data-role="git-url"><?php echo esc_html( $status['github_url'] ); ?></a></dd>
                                <dt>Git branch</dt>
                                <dd><code data-role="git-branch"><?php echo esc_html( $status['github_branch'] ); ?></code></dd>
                                <dt>Git version</dt>
                                <dd><strong data-role="git-version"><?php echo esc_html( $status['latest_version'] ); ?></strong></dd>
                                <dt>Installed path</dt>
                                <dd><code><?php echo esc_html( $status['core_root'] ); ?></code></dd>
                            </dl>
                        </article>

                        <article class="hpc-card">
                            <h3>Version Compare</h3>
                            <div class="hexa-core-version">
                                <div class="hexa-core-version-side">
                                    <span class="hpc-small">Current vendored core</span>
                                    <span class="hexa-core-version-num" data-role="current-version"><?php echo esc_html( $status['current_version'] ); ?></span>
                                </div>
                                <span aria-hidden="true">&rarr;</span>
                                <div class="hexa-core-version-side">
                                    <span class="hpc-small">Latest in Git</span>
                                    <span class="hexa-core-version-num" data-role="latest-version"><?php echo esc_html( $status['latest_version'] ); ?></span>
                                </div>
                            </div>
                            <p class="hpc-small" data-role="comparison"><?php echo esc_html( $status['update_available'] ? 'Vendored core is behind the Git repository.' : 'Vendored core matches the Git repository.' ); ?></p>
                        </article>
                    </div>

                    <article class="hpc-card">
                        <h3>Update Actions</h3>
                        <p>Check GitHub, compare the vendored core against the latest Git version, update this plugin copy, or download a normalized core ZIP.</p>
                        <div class="hpc-actions">
                            <button type="button" class="hpc-button secondary" data-role="force-check">Check for Updates</button>
                            <button type="button" class="hpc-button" data-role="update-core" <?php disabled( ! $status['update_available'] ); ?>>Pull Core from GitHub</button>
                            <button type="button" class="hpc-button secondary" data-role="download-core">Download <?php echo esc_html( $this->config->proper_folder_name() ); ?>.zip</button>
                        </div>
                        <div class="hexa-core-status" data-role="status"></div>
                        <div class="hexa-core-status" data-role="download-status"></div>
                    </article>

                    <article class="hpc-card">
                        <h3>Activity Log</h3>
                        <div class="hexa-core-log" data-role="update-log">
                            <div class="hexa-core-log-head"><span>Core Update Activity</span><span class="hexa-core-spin" data-role="log-spin" style="display:none"></span></div>
                            <ul class="hexa-core-log-body" data-role="log-body"><li class="hexa-core-log-row is-done"><span></span><span>No core update has run in this panel yet.</span><span></span></li></ul>
                            <div class="hexa-core-log-foot" data-role="log-foot"></div>
                        </div>
                    </article>
                </div>
            </details>

            <script>
            (function($){
                var root = $('#<?php echo esc_js( $dom ); ?>');
                var nonce = '<?php echo esc_js( $nonce ); ?>';
                var actions = <?php echo wp_json_encode( $actions ); ?>;
                var poll = null;

                if (window.hexaPluginCoreInitPersistentDetails) {
                    window.hexaPluginCoreInitPersistentDetails(root.get(0));
                }

                function post(action, data, timeout) {
                    return $.ajax({
                        url: window.ajaxurl,
                        method: 'POST',
                        dataType: 'json',
                        timeout: timeout || 30000,
                        data: $.extend({ action: action, <?php echo esc_js( $this->config->nonce_param() ); ?>: nonce }, data || {})
                    });
                }

                function cmp(a,b){var pa=String(a||'').split('.').map(Number),pb=String(b||'').split('.').map(Number);for(var i=0;i<Math.max(pa.length,pb.length);i++){var x=pa[i]||0,y=pb[i]||0;if(x>y)return 1;if(x<y)return-1;}return 0;}
                function sync(data){
                    data = data || {};
                    var current = data.current_version || root.find('[data-role=current-version]').text();
                    var latest = data.latest_version || data.git_version || 'Unknown';
                    var unknown = !latest || latest === 'Unknown';
                    var needs = !unknown && cmp(latest, current) > 0;
                    root.find('[data-role=current-version]').text(current);
                    root.find('[data-role=latest-version]').text(latest);
                    root.find('[data-role=git-version]').text(latest);
                    if (data.github_repo) root.find('[data-role=git-repo]').text(data.github_repo);
                    if (data.github_branch) root.find('[data-role=git-branch]').text(data.github_branch);
                    if (data.github_url) root.find('[data-role=git-url]').attr('href', data.github_url).text(data.github_url);
                    root.find('[data-role=badge]').removeClass('success danger warning').addClass(unknown ? 'warning' : (needs ? 'danger' : 'success')).text(unknown ? 'Unknown' : (needs ? 'Outdated' : 'Current'));
                    root.find('[data-role=update-core]').prop('disabled', !needs);
                    root.find('[data-role=comparison]').text(unknown ? 'Git version could not be read.' : (needs ? 'Vendored core is behind the Git repository.' : 'Vendored core matches the Git repository.'));
                }
                function time(t){var d=t?new Date(t*1000):new Date(),p=function(n){return(n<10?'0':'')+n;};return p(d.getHours())+':'+p(d.getMinutes())+':'+p(d.getSeconds());}
                function icon(s){if(s==='done')return '✓'; if(s==='warn')return '▲'; if(s==='error')return '✕'; return '<span class="hexa-core-spin"></span>';}
                function renderLog(data){
                    if(!data || !Array.isArray(data.steps))return;
                    var body=root.find('[data-role=log-body]');
                    for(var i=0;i<data.steps.length;i++){
                        var step=data.steps[i]||{};
                        var status=['done','warn','error','running'].indexOf(step.status)>=0?step.status:'running';
                        var row=body.children().eq(i);
                        if(!row.length){
                            body.append('<li class="hexa-core-log-row"><span></span><span></span><span></span></li>');
                            row=body.children().last();
                        }
                        row.attr('class','hexa-core-log-row is-'+status);
                        row.children().eq(0).html(icon(status));
                        row.children().eq(1).text(step.message||'');
                        row.children().eq(2).text(time(step.t));
                    }
                    body.children().slice(data.steps.length).remove();
                    if(data.state==='done'||data.state==='error'){
                        root.find('[data-role=log-spin]').hide();
                        root.find('[data-role=log-foot]').text(data.message || '').show();
                        if(poll){clearInterval(poll);poll=null;}
                    }
                }
                function pollLog(){post(actions.get_update_progress,{},8000).done(function(resp){if(resp&&resp.success)renderLog(resp.data);});}

                root.on('click','[data-role=force-check]',function(){
                    var btn=$(this), status=root.find('[data-role=status]');
                    btn.prop('disabled',true).text('Checking...');
                    status.text('Checking Hexa WordPress Plugin Core Git repository.');
                    post(actions.force_check).done(function(resp){
                        if(resp&&resp.success){sync(resp.data);status.text('Core check complete. Git version: '+(resp.data.latest_version || resp.data.git_version || 'Unknown')+'.');}
                        else{status.text(resp&&resp.data?resp.data:'Core check failed.');}
                    }).always(function(){btn.prop('disabled',false).text('Check for Updates');});
                });
                root.on('click','[data-role=update-core]',function(){
                    if(!window.confirm('This will update the vendored Hexa WordPress Plugin Core files in this plugin. Continue?'))return;
                    var btn=$(this), status=root.find('[data-role=status]');
                    btn.prop('disabled',true).text('Pulling...');
                    status.text('Starting Hexa Core update from GitHub.');
                    root.find('[data-role=log-body]').empty();
                    root.find('[data-role=log-foot]').hide().text('');
                    root.find('[data-role=log-spin]').show();
                    pollLog();
                    poll=setInterval(pollLog,700);
                    post(actions.update_core,{},120000).done(function(resp){
                        pollLog();
                        if(resp&&resp.success){sync({current_version:resp.data.new_version,latest_version:resp.data.new_version});status.html('Updated core to v'+resp.data.new_version+'. <a href="'+window.location.href+'">Reload this page</a>');btn.text('Current').prop('disabled',true);}
                        else{status.text(resp&&resp.data?resp.data:'Core update failed.');btn.prop('disabled',false).text('Pull Core from GitHub');}
                    }).fail(function(){status.text('Connection error during core update. Run Check for Updates to confirm result.');btn.prop('disabled',false).text('Pull Core from GitHub');});
                });
                root.on('click','[data-role=download-core]',function(){
                    var btn=$(this), status=root.find('[data-role=download-status]');
                    btn.prop('disabled',true).text('Preparing...');
                    status.text('Preparing normalized Hexa Core ZIP.');
                    post(actions.download_core_zip,{},60000).done(function(resp){
                        if(resp&&resp.success){status.html('<a href="'+resp.data.url+'" target="_blank" rel="noopener">'+resp.data.filename+' ready</a>');window.location.href=resp.data.url;}
                        else{status.text(resp&&resp.data?resp.data:'Download failed.');}
                    }).always(function(){btn.prop('disabled',false).text('Download <?php echo esc_js( $this->config->proper_folder_name() ); ?>.zip');});
                });
            })(jQuery);
            </script>
        </div>
        <?php
    }
}
