<?php

namespace Hexa\PluginCore\PluginUpdates;

use Hexa\PluginCore\WpAdminComponents\CoreUi;

final class UpdaterPanelRenderer {
    private UpdaterConfig $config;

    public function __construct( UpdaterConfig $config ) {
        $this->config = $config;
    }

    public function render(): void {
        CoreUi::render_assets();

        $status      = ( new PluginUpdateStatus( $this->config ) )->get();
        $controller  = new UpdaterAjaxController( $this->config );
        $actions     = $controller->actions();
        $nonce       = wp_create_nonce( $this->config->nonce_action() );
        $dom         = 'hexa-updater-' . preg_replace( '/[^a-z0-9_-]+/', '-', strtolower( $this->config->plugin_slug() ) );
        $needs_class = $status['update_available'] ? 'danger' : 'success';
        $needs_text  = $status['update_available'] ? 'Outdated' : 'Current';
        ?>
        <div id="<?php echo esc_attr( $dom ); ?>" class="hpc-ui hexa-plugin-core-updater">
            <style>
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-kv{display:grid;gap:8px;grid-template-columns:150px minmax(0,1fr);margin:0}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-kv dt{color:var(--hpc-muted);font-size:12px;font-weight:800;text-transform:uppercase}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-kv dd{margin:0;min-width:0}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-version{align-items:center;display:grid;gap:14px;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr)}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-version-side{background:#f8fafc;border:1px solid var(--hpc-line);border-radius:8px;padding:16px;text-align:center}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-version-num{display:block;font-size:32px;font-weight:900;line-height:1.1}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-status{color:var(--hpc-muted);font-size:13px;margin-top:10px}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-log{border:1px solid var(--hpc-line);border-radius:8px;margin-top:12px;overflow:hidden}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-log-head{align-items:center;background:#111827;color:#f8fafc;display:flex;font-weight:800;gap:10px;justify-content:space-between;padding:10px 12px}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-log-body{background:#0f1720;color:#dbe7f3;list-style:none;margin:0;max-height:260px;overflow:auto;padding:0}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-log-row{border-top:1px solid #263244;display:grid;gap:8px;grid-template-columns:24px 1fr auto;margin:0;padding:8px 12px}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-log-row.is-error{color:#ffb4c0}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-log-row.is-warn{color:#ffe099}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-log-row.is-done{color:#9ee6b2}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-log-foot{background:#111827;color:#f8fafc;display:none;padding:10px 12px}
                #<?php echo esc_attr( $dom ); ?> .hexa-updater-spin{animation:hexaUpdaterSpin .7s linear infinite;border:2px solid rgba(49,87,213,.25);border-radius:50%;border-top-color:#3157d5;display:inline-block;height:14px;width:14px}
                @keyframes hexaUpdaterSpin{to{transform:rotate(360deg)}}
                @media(max-width:900px){#<?php echo esc_attr( $dom ); ?> .hpc-grid.two,#<?php echo esc_attr( $dom ); ?> .hexa-updater-version,#<?php echo esc_attr( $dom ); ?> .hexa-updater-kv{grid-template-columns:1fr}}
            </style>

            <details class="hpc-section hexa-updater-parent" open data-hpc-persist-key="<?php echo esc_attr( $dom . '-parent' ); ?>">
                <summary>
                    <span><?php echo esc_html( $this->config->plugin_name() ); ?> Git Updater</span>
                    <span class="hpc-pill <?php echo esc_attr( $needs_class ); ?>" data-role="badge"><?php echo esc_html( $needs_text ); ?></span>
                </summary>
                <div class="hpc-section-body hpc-stack">
                    <div class="hpc-grid two">
                        <article class="hpc-card">
                            <h3>Git Report</h3>
                            <dl class="hexa-updater-kv">
                                <dt>Git repo</dt>
                                <dd><code data-role="git-repo"><?php echo esc_html( $status['github_repo'] ); ?></code></dd>
                                <dt>Git URL</dt>
                                <dd><a class="hpc-external" href="<?php echo esc_url( $status['github_url'] ); ?>" target="_blank" rel="noopener" data-role="git-url"><?php echo esc_html( $status['github_url'] ); ?></a></dd>
                                <dt>Git branch</dt>
                                <dd><code data-role="git-branch"><?php echo esc_html( $status['github_branch'] ); ?></code></dd>
                                <dt>Git version</dt>
                                <dd><strong data-role="git-version"><?php echo esc_html( $status['latest_version'] ); ?></strong></dd>
                            </dl>
                        </article>

                        <article class="hpc-card">
                            <h3>Version Compare</h3>
                            <div class="hexa-updater-version">
                                <div class="hexa-updater-version-side">
                                    <span class="hpc-small">Current plugin</span>
                                    <span class="hexa-updater-version-num" data-role="current-version"><?php echo esc_html( $status['current_version'] ); ?></span>
                                </div>
                                <span aria-hidden="true">&rarr;</span>
                                <div class="hexa-updater-version-side">
                                    <span class="hpc-small">Latest in Git</span>
                                    <span class="hexa-updater-version-num" data-role="latest-version"><?php echo esc_html( $status['latest_version'] ); ?></span>
                                </div>
                            </div>
                            <p class="hpc-small" data-role="comparison"><?php echo esc_html( $status['update_available'] ? 'Current version is behind the Git repository.' : 'Current version matches the Git repository.' ); ?></p>
                        </article>
                    </div>

                    <article class="hpc-card">
                        <h3>Update Actions</h3>
                        <p>Check GitHub, compare the installed version against the latest Git version, pull the latest build, or download a normalized plugin ZIP.</p>
                        <div class="hpc-actions">
                            <button type="button" class="hpc-button secondary" data-role="force-check">Check for Updates</button>
                            <button type="button" class="hpc-button" data-role="direct-update" <?php disabled( ! $status['update_available'] ); ?>>Pull Latest from GitHub</button>
                            <button type="button" class="hpc-button secondary" data-role="download-current">Download <?php echo esc_html( $this->config->proper_folder_name() ); ?>.zip</button>
                            <a href="<?php echo esc_url( admin_url( 'update-core.php?force-check=1' ) ); ?>" class="hpc-button secondary hpc-external" target="_blank" rel="noopener">WP Update Page</a>
                        </div>
                        <div class="hexa-updater-status" data-role="update-status"></div>
                        <div class="hexa-updater-status" data-role="download-status"></div>
                    </article>

                    <article class="hpc-card">
                        <h3>Activity Log</h3>
                        <div class="hexa-updater-log" data-role="update-log">
                            <div class="hexa-updater-log-head"><span>GitHub Update Activity</span><span class="hexa-updater-spin" data-role="log-spin" style="display:none"></span></div>
                            <ul class="hexa-updater-log-body" data-role="log-body"><li class="hexa-updater-log-row is-done"><span></span><span>No update has run in this panel yet.</span><span></span></li></ul>
                            <div class="hexa-updater-log-foot" data-role="log-foot"></div>
                        </div>
                    </article>

                    <article class="hpc-card">
                        <h3>Version History</h3>
                        <p>Load recent GitHub commits and download a normalized ZIP for a selected commit.</p>
                        <div class="hpc-actions">
                            <select data-role="version-select"><option value="">Load versions first</option></select>
                            <button type="button" class="hpc-button secondary" data-role="load-versions">Load Versions</button>
                            <button type="button" class="hpc-button secondary" data-role="download-version" disabled>Download Selected Version</button>
                        </div>
                        <div class="hexa-updater-status" data-role="version-status"></div>
                    </article>
                </div>
            </details>

            <script>
            (function($){
                var root = $('#<?php echo esc_js( $dom ); ?>');
                var nonce = '<?php echo esc_js( $nonce ); ?>';
                var actions = <?php echo wp_json_encode( $actions ); ?>;
                var versionData = {};
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
                    var stale = !unknown && cmp(latest,current)>0;
                    root.find('[data-role=current-version]').text(current);
                    root.find('[data-role=latest-version]').text(latest);
                    root.find('[data-role=git-version]').text(latest);
                    if (data.github_repo) root.find('[data-role=git-repo]').text(data.github_repo);
                    if (data.github_branch) root.find('[data-role=git-branch]').text(data.github_branch);
                    if (data.github_url) root.find('[data-role=git-url]').attr('href', data.github_url).text(data.github_url);
                    root.find('[data-role=badge]').removeClass('success danger warning').addClass(unknown ? 'warning' : (stale ? 'danger' : 'success')).text(unknown ? 'Unknown' : (stale ? 'Outdated' : 'Current'));
                    root.find('[data-role=direct-update]').prop('disabled', !stale);
                    root.find('[data-role=comparison]').text(unknown ? 'Git version could not be read.' : (stale ? 'Current version is behind the Git repository.' : 'Current version matches the Git repository.'));
                }
                function time(t){var d=t?new Date(t*1000):new Date(),p=function(n){return(n<10?'0':'')+n;};return p(d.getHours())+':'+p(d.getMinutes())+':'+p(d.getSeconds());}
                function icon(s){if(s==='done')return '✓'; if(s==='warn')return '▲'; if(s==='error')return '✕'; return '<span class="hexa-updater-spin"></span>';}
                function renderLog(data){
                    if(!data || !Array.isArray(data.steps))return;
                    var body=root.find('[data-role=log-body]');
                    for(var i=0;i<data.steps.length;i++){
                        var step=data.steps[i]||{};
                        var status=['done','warn','error','running'].indexOf(step.status)>=0?step.status:'running';
                        var row=body.children().eq(i);
                        if(!row.length){
                            body.append('<li class="hexa-updater-log-row"><span></span><span></span><span></span></li>');
                            row=body.children().last();
                        }
                        row.attr('class','hexa-updater-log-row is-'+status);
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
                    var btn=$(this), status=root.find('[data-role=update-status]');
                    btn.prop('disabled',true).text('Checking...');
                    status.text('Checking GitHub repository.');
                    post(actions.force_update_check).done(function(resp){
                        if(resp&&resp.success){sync(resp.data);status.text('Check complete. Git version: '+(resp.data.latest_version || resp.data.git_version || 'Unknown')+'.');}
                        else{status.text(resp&&resp.data?resp.data:'Check failed.');}
                    }).always(function(){btn.prop('disabled',false).text('Check for Updates');});
                });
                root.on('click','[data-role=direct-update]',function(){
                    if(!window.confirm('This will download the latest build from GitHub and replace this plugin in place. Continue?'))return;
                    var btn=$(this), status=root.find('[data-role=update-status]');
                    btn.prop('disabled',true).text('Pulling...');
                    status.text('Starting GitHub update.');
                    root.find('[data-role=log-body]').empty();
                    root.find('[data-role=log-foot]').hide().text('');
                    root.find('[data-role=log-spin]').show();
                    pollLog();
                    poll=setInterval(pollLog,700);
                    post(actions.direct_update_plugin,{},200000).done(function(resp){
                        pollLog();
                        if(resp&&resp.success){sync({current_version:resp.data.new_version,latest_version:resp.data.new_version});status.html('Updated to v'+resp.data.new_version+'. <a href="'+window.location.href+'">Reload this page</a>');btn.text('Current').prop('disabled',true);}
                        else{status.text(resp&&resp.data?resp.data:'Update failed.');btn.prop('disabled',false).text('Pull Latest from GitHub');}
                    }).fail(function(){status.text('Connection error during update. Use Check for Updates to confirm result.');btn.prop('disabled',false).text('Pull Latest from GitHub');});
                });
                root.on('click','[data-role=download-current]',function(){
                    var btn=$(this), status=root.find('[data-role=download-status]');
                    btn.prop('disabled',true).text('Preparing...');
                    status.text('Preparing normalized plugin ZIP.');
                    post(actions.download_plugin_zip,{},60000).done(function(resp){
                        if(resp&&resp.success){status.html('<a href="'+resp.data.url+'" target="_blank" rel="noopener">'+resp.data.filename+' ready</a>');window.location.href=resp.data.url;}
                        else{status.text(resp&&resp.data?resp.data:'Download failed.');}
                    }).always(function(){btn.prop('disabled',false).text('Download <?php echo esc_js( $this->config->proper_folder_name() ); ?>.zip');});
                });
                root.on('click','[data-role=load-versions]',function(){
                    var btn=$(this), select=root.find('[data-role=version-select]'), status=root.find('[data-role=version-status]');
                    btn.prop('disabled',true).text('Loading...');
                    post(actions.load_github_versions,{},60000).done(function(resp){
                        if(resp&&resp.success){select.empty();versionData={};select.append('<option value="">Select Version ('+resp.data.count+' commits)</option>');$.each(resp.data.versions,function(i,v){versionData[v.name]=v.sha;select.append('<option value="'+v.name+'">'+v.name+'</option>');});root.find('[data-role=download-version]').prop('disabled',false);status.text('Loaded '+resp.data.count+' commits.');}
                        else{status.text(resp&&resp.data?resp.data:'Load failed.');}
                    }).always(function(){btn.prop('disabled',false).text('Load Versions');});
                });
                root.on('click','[data-role=download-version]',function(){
                    var btn=$(this), select=root.find('[data-role=version-select]'), status=root.find('[data-role=version-status]'), version=select.val(), sha=versionData[version]||'';
                    if(!version){status.text('Select a version first.');return;}
                    btn.prop('disabled',true).text('Preparing...');
                    post(actions.download_specific_version,{version:version,sha:sha},60000).done(function(resp){
                        if(resp&&resp.success){status.html('<a href="'+resp.data.url+'" target="_blank" rel="noopener">'+resp.data.filename+' ready</a>');window.location.href=resp.data.url;}
                        else{status.text(resp&&resp.data?resp.data:'Download failed.');}
                    }).always(function(){btn.prop('disabled',false).text('Download Selected Version');});
                });
            })(jQuery);
            </script>
        </div>
        <?php
    }
}
