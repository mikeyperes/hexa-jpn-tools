<?php

namespace Hexa\PluginCore\DatabaseCleanup;

use Hexa\PluginCore\WpAdminComponents\CoreUi;
use Hexa\PluginCore\WpAdminComponents\DynamicButton;

final class DatabaseCleanupRenderer {
    /**
     * @param array<string,mixed> $config
     */
    public function __construct( private array $config ) {}

    public function render(): void {
        CoreUi::render_assets();
        DynamicButton::render_assets();

        $root_id = (string) ( $this->config['root_id'] ?? 'hpc-database-cleanup' );
        $nonce   = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( (string) ( $this->config['nonce_action'] ?? '' ) ) : '';
        ?>
        <div id="<?php echo esc_attr( $root_id ); ?>" class="hpc-ui hpc-db-cleanup" data-hpc-db-cleanup data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-nonce-field="<?php echo esc_attr( (string) ( $this->config['nonce_field'] ?? 'nonce' ) ); ?>" data-status-action="<?php echo esc_attr( (string) ( $this->config['status_action'] ?? '' ) ); ?>" data-start-action="<?php echo esc_attr( (string) ( $this->config['start_action'] ?? '' ) ); ?>" data-cleanup-action="<?php echo esc_attr( (string) ( $this->config['cleanup_action'] ?? '' ) ); ?>" data-table-action="<?php echo esc_attr( (string) ( $this->config['table_action'] ?? '' ) ); ?>" data-finish-action="<?php echo esc_attr( (string) ( $this->config['finish_action'] ?? '' ) ); ?>">
            <?php $this->styles( $root_id ); ?>
            <?php
            ob_start();
            ?>
                <p class="hpc-db-description"><?php echo esc_html( (string) ( $this->config['description'] ?? 'Run WP-Optimize database cleanup and optimize tables with row-by-row AJAX reporting.' ) ); ?></p>

                <div class="hpc-db-status-grid" data-db-status>
                    <div class="hpc-db-status-card">
                        <strong>WP-Optimize</strong>
                        <span data-db-plugin-state>Checking...</span>
                    </div>
                    <div class="hpc-db-status-card">
                        <strong>Run State</strong>
                        <span data-db-run-state>Ready</span>
                    </div>
                    <div class="hpc-db-status-card">
                        <strong>Tables</strong>
                        <span data-db-table-count>Not scanned</span>
                    </div>
                </div>

                <div class="hpc-actions hpc-db-actions">
                    <?php echo DynamicButton::render( [ 'label' => 'Refresh Status', 'working_label' => 'Checking...', 'success_label' => 'Status Updated', 'class' => 'hpc-button secondary', 'attrs' => [ 'data-db-refresh' => true ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo DynamicButton::render( [ 'label' => 'Run Database Cleanup', 'working_label' => 'Running...', 'success_label' => 'Cleanup Finished', 'error_label' => 'Cleanup Failed', 'class' => 'hpc-button', 'attrs' => [ 'data-db-run' => true ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>

                <div class="hpc-db-progress" data-db-progress hidden>
                    <div class="hpc-db-progress-head">
                        <div>
                            <strong data-db-progress-title>Preparing cleanup run</strong>
                            <span data-db-progress-text>Waiting for the first AJAX response.</span>
                        </div>
                        <div class="hpc-db-progress-meter"><span data-db-progress-bar></span></div>
                    </div>
                </div>

                <details class="hpc-db-subsection" open>
                    <summary>Cleanup Tasks <span data-db-task-summary>0 queued</span></summary>
                    <div class="hpc-db-table-wrap">
                        <table class="hpc-db-table hpc-db-cleanup-tasks">
                            <thead><tr><th>Task</th><th>Status</th><th>Result</th></tr></thead>
                            <tbody data-db-tasks><tr><td colspan="3" class="hpc-db-muted">Press Run Database Cleanup to load WP-Optimize cleanup tasks.</td></tr></tbody>
                        </table>
                    </div>
                </details>

                <details class="hpc-db-subsection" open>
                    <summary>Table Optimization <span data-db-table-summary>0 queued</span></summary>
                    <div class="hpc-db-table-wrap">
                        <table class="hpc-db-table hpc-db-tables">
                            <thead><tr><th>Table</th><th>Engine</th><th>Rows</th><th>Size</th><th>Overhead Before</th><th>Overhead After</th><th>Status</th></tr></thead>
                            <tbody data-db-tables><tr><td colspan="7" class="hpc-db-muted">Press Run Database Cleanup to scan tables.</td></tr></tbody>
                        </table>
                    </div>
                </details>

                <details class="hpc-db-log">
                    <summary>
                        <span>Database Cleanup Log</span>
                        <button type="button" class="hpc-button secondary" data-db-clear-log>Clear</button>
                    </summary>
                    <div class="hpc-db-log-body" data-db-log></div>
                </details>
            <?php
            $body = (string) ob_get_clean();

            echo CoreUi::collapsible(
                [
                    'title'       => (string) ( $this->config['title'] ?? 'Database Cleanup' ),
                    'open'        => true,
                    'persist_key' => $root_id . '-section',
                    'meta_html'   => CoreUi::pill( 'WP-Optimize', 'dark' ) . CoreUi::pill( 'AJAX', 'blue' ),
                    'body_html'   => $body,
                ]
            ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
            <?php $this->script( $root_id ); ?>
        </div>
        <?php
    }

    private function styles( string $root_id ): void {
        ?>
        <style>
            #<?php echo esc_attr( $root_id ); ?>{margin:0 0 14px;max-width:100%;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-description{color:#3f4d63;font-size:13px;line-height:1.55;margin:0 0 14px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-status-grid{display:grid;gap:10px;grid-template-columns:repeat(3,minmax(0,1fr));margin:0 0 14px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-status-card{background:#fbfcfe;border:1px solid var(--hpc-line);border-radius:8px;padding:12px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-status-card strong{display:block;font-size:12px;margin:0 0 6px;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-status-card span{color:#3f4d63;font-size:13px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-actions{margin:0 0 14px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-progress{background:#f8fbff;border:1px solid #cfe0ff;border-radius:8px;margin:0 0 14px;padding:12px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-progress-head{display:grid;gap:10px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-progress-head strong{display:block;font-size:13px;margin:0 0 4px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-progress-head span{color:#3f4d63;font-size:12px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-progress-meter{background:#d9e5ff;border-radius:999px;height:10px;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-progress-meter span{background:var(--hpc-blue);display:block;height:100%;transition:width .2s;width:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-subsection{border:1px solid var(--hpc-line);border-radius:8px;margin:0 0 12px;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-subsection summary{align-items:center;background:#f8fafc;cursor:pointer;display:flex;font-size:13px;font-weight:800;justify-content:space-between;list-style:none;padding:12px 14px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-subsection summary::-webkit-details-marker{display:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-subsection summary span{color:var(--hpc-muted);font-size:12px;font-weight:700}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-table-wrap{max-width:100%;overflow-x:auto;overflow-y:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-table{border-collapse:collapse;table-layout:fixed;width:100%}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-table th,#<?php echo esc_attr( $root_id ); ?> .hpc-db-table td{border-top:1px solid var(--hpc-line);font-size:12px;line-height:1.45;overflow-wrap:anywhere;padding:10px;text-align:left;vertical-align:top;word-break:normal}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-cleanup-tasks{min-width:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-cleanup-tasks th:nth-child(1),#<?php echo esc_attr( $root_id ); ?> .hpc-db-cleanup-tasks td:nth-child(1){width:32%}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-cleanup-tasks th:nth-child(2),#<?php echo esc_attr( $root_id ); ?> .hpc-db-cleanup-tasks td:nth-child(2){white-space:nowrap;width:150px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-cleanup-tasks th:nth-child(3),#<?php echo esc_attr( $root_id ); ?> .hpc-db-cleanup-tasks td:nth-child(3){width:auto}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-tables{min-width:980px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-table th{background:#fff;color:#314056;font-weight:800;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-table td{background:#fff}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-result{color:#3f4d63}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-muted{color:var(--hpc-muted)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-table-name{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;font-weight:700;word-break:break-all}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-state{align-items:center;display:inline-flex;gap:7px;font-weight:800;white-space:nowrap}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-state-icon{align-items:center;border-radius:999px;display:inline-flex;height:22px;justify-content:center;width:22px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-state-icon.pending{background:#eef2f7;color:#65758b}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-state-icon.running{animation:hpc-db-spin .8s linear infinite;border:3px solid #3157d5;border-right-color:transparent}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-state-icon.done{background:#eaf8ef;color:#16803c}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-state-icon.error{background:#fff0f2;color:#b42336}
            #<?php echo esc_attr( $root_id ); ?> tr.is-running td{background:#f8fbff}
            #<?php echo esc_attr( $root_id ); ?> tr.is-done td{background:#fbfffc}
            #<?php echo esc_attr( $root_id ); ?> tr.is-error td{background:#fff7f8}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-log{background:#0f1720;border:1px solid #263241;border-radius:8px;color:#dbe7f3;margin-top:14px;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-log summary{align-items:center;background:#111c2a;cursor:pointer;display:flex;font-size:13px;font-weight:800;justify-content:space-between;list-style:none;padding:12px 14px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-log summary::-webkit-details-marker{display:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-log-body{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;max-height:280px;overflow:auto}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-log-row{border-top:1px solid #263241;display:grid;gap:10px;grid-template-columns:80px 74px minmax(0,1fr);padding:10px 14px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-log-time{color:#8ca1b8}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-log-level{border-radius:5px;font-size:11px;font-weight:900;padding:3px 7px;text-align:center;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-log-level.info{background:#16324f;color:#9bd0ff}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-log-level.success{background:#14391f;color:#9cf0b0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-log-level.warning{background:#493813;color:#ffd37a}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-log-level.error{background:#4c1720;color:#ff9cac}
            #<?php echo esc_attr( $root_id ); ?> .hpc-db-log-message{white-space:pre-wrap;word-break:break-word}
            @keyframes hpc-db-spin{to{transform:rotate(360deg)}}
            @media(max-width:900px){#<?php echo esc_attr( $root_id ); ?> .hpc-db-status-grid{grid-template-columns:1fr}#<?php echo esc_attr( $root_id ); ?> .hpc-db-log-row{grid-template-columns:1fr}}
        </style>
        <?php
    }

    private function script( string $root_id ): void {
        ?>
        <script>
        (function(){
            var root=document.getElementById('<?php echo esc_js( $root_id ); ?>'); if(!root||root.dataset.dbReady==='1')return; root.dataset.dbReady='1';
            var els={
                plugin:root.querySelector('[data-db-plugin-state]'),
                runState:root.querySelector('[data-db-run-state]'),
                tableCount:root.querySelector('[data-db-table-count]'),
                tasks:root.querySelector('[data-db-tasks]'),
                tables:root.querySelector('[data-db-tables]'),
                taskSummary:root.querySelector('[data-db-task-summary]'),
                tableSummary:root.querySelector('[data-db-table-summary]'),
                progress:root.querySelector('[data-db-progress]'),
                progressTitle:root.querySelector('[data-db-progress-title]'),
                progressText:root.querySelector('[data-db-progress-text]'),
                progressBar:root.querySelector('[data-db-progress-bar]'),
                log:root.querySelector('[data-db-log]')
            };
            function text(v){return v===null||v===undefined?'':String(v)}
            function esc(v){var d=document.createElement('div');d.textContent=text(v);return d.innerHTML}
            function now(){return new Date().toTimeString().slice(0,8)}
            function dynStart(b,l){if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.start(b,l);else if(b)b.disabled=true}
            function dynOk(b,l){if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.success(b,l||'Done');else if(b)b.disabled=false}
            function dynFail(b,l){if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.error(b,l||'Failed');else if(b)b.disabled=false}
            function setRunState(v){if(els.runState)els.runState.textContent=v}
            function setProgress(done,total,title,msg){if(els.progress)els.progress.hidden=false;if(els.progressTitle)els.progressTitle.textContent=title||'Running cleanup';if(els.progressText)els.progressText.textContent=msg||'';if(els.progressBar)els.progressBar.style.width=(total?Math.round((done/total)*100):0)+'%'}
            function addLog(entry){entry=entry||{};if(!els.log)return;var level=text(entry.level||'info').toLowerCase(),row=document.createElement('div');row.className='hpc-db-log-row';row.innerHTML='<div class="hpc-db-log-time">'+esc(entry.time||now())+'</div><div><span class="hpc-db-log-level '+esc(level)+'">'+esc(level)+'</span></div><div class="hpc-db-log-message">'+esc(entry.message||'')+'</div>';els.log.appendChild(row);els.log.scrollTop=els.log.scrollHeight}
            function addLogs(logs){(logs||[]).forEach(addLog)}
            function post(action,payload){var body=new URLSearchParams();body.set('action',action||'');body.set(root.dataset.nonceField||'nonce',root.dataset.nonce||'');Object.keys(payload||{}).forEach(function(k){body.set(k,payload[k])});return fetch(root.dataset.ajaxUrl||window.ajaxurl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()}).then(function(r){return r.json()}).then(function(p){if(!p||!p.success){var m=p&&p.data&&(p.data.message||p.data.error)?(p.data.message||p.data.error):'AJAX request failed.';throw new Error(m)}return p.data||{}})}
            function stateHtml(status,label){var cls=status==='running'?'running':status==='done'?'done':status==='error'?'error':'pending',icon=status==='done'?'✓':status==='error'?'×':status==='running'?'':'-';return '<span class="hpc-db-state"><span class="hpc-db-state-icon '+cls+'">'+esc(icon)+'</span><span>'+esc(label||status)+'</span></span>'}
            function taskRow(t){return '<tr data-db-task-id="'+esc(t.id)+'"><td><strong>'+esc(t.label||t.id)+'</strong></td><td data-db-task-status>'+stateHtml('pending','Pending')+'</td><td data-db-task-result class="hpc-db-result hpc-db-muted">Queued</td></tr>'}
            function tableRow(t){return '<tr data-db-table="'+esc(t.name)+'"><td><span class="hpc-db-table-name">'+esc(t.name)+'</span></td><td>'+esc(t.engine||'')+'</td><td>'+esc(t.rows||0)+'</td><td>'+esc(t.size_label||'')+'</td><td data-db-overhead-before>'+esc(t.overhead_label||'')+'</td><td data-db-overhead-after class="hpc-db-muted">Pending</td><td data-db-table-status>'+stateHtml('pending','Pending')+'</td></tr>'}
            function setTask(id,status,label,result){var row=root.querySelector('[data-db-task-id="'+CSS.escape(id)+'"]');if(!row)return;row.classList.remove('is-running','is-done','is-error');if(status==='running')row.classList.add('is-running');if(status==='done')row.classList.add('is-done');if(status==='error')row.classList.add('is-error');var s=row.querySelector('[data-db-task-status]'),r=row.querySelector('[data-db-task-result]');if(s)s.innerHTML=stateHtml(status,label);if(r)r.textContent=result||''}
            function setTable(name,status,label,result,after){var row=root.querySelector('[data-db-table="'+CSS.escape(name)+'"]');if(!row)return;row.classList.remove('is-running','is-done','is-error');if(status==='running')row.classList.add('is-running');if(status==='done')row.classList.add('is-done');if(status==='error')row.classList.add('is-error');var s=row.querySelector('[data-db-table-status]'),a=row.querySelector('[data-db-overhead-after]');if(s)s.innerHTML=stateHtml(status,label);if(a)a.textContent=after||result||''}
            function renderStart(data){var tasks=data.cleanup_tasks||[],tables=data.tables||[];if(els.tasks)els.tasks.innerHTML=tasks.length?tasks.map(taskRow).join(''):'<tr><td colspan="3" class="hpc-db-muted">No cleanup tasks were available from WP-Optimize.</td></tr>';if(els.tables)els.tables.innerHTML=tables.length?tables.map(tableRow).join(''):'<tr><td colspan="7" class="hpc-db-muted">No tables returned by WP-Optimize.</td></tr>';if(els.taskSummary)els.taskSummary.textContent=tasks.length+' queued';if(els.tableSummary)els.tableSummary.textContent=tables.length+' queued';if(els.tableCount)els.tableCount.textContent=tables.length+' tables';addLogs(data.log);return {tasks:tasks,tables:tables,sessionId:data.session_id,total:tasks.length+tables.length+1}}
            function refresh(button){dynStart(button,'Checking...');post(root.dataset.statusAction,{}).then(function(data){var p=data.plugin||{},label=p.installed?(p.active?'Active':'Installed, inactive'):'Not installed';if(els.plugin)els.plugin.textContent=label+(p.version?' v'+p.version:'');setRunState('Ready');dynOk(button,'Status Updated')}).catch(function(error){setRunState('Status check failed');addLog({level:'error',message:error.message||'Status check failed.'});dynFail(button,'Failed')})}
            function run(button){if(els.log)els.log.innerHTML='';dynStart(button,'Starting...');setRunState('Starting');setProgress(0,1,'Starting cleanup','Checking WP-Optimize and scanning tables.');post(root.dataset.startAction,{}).then(async function(data){var run=renderStart(data),done=0,total=run.total||1;for(var i=0;i<run.tasks.length;i++){var task=run.tasks[i];setTask(task.id,'running','Running','Working...');setProgress(done,total,'Cleanup tasks','Running '+(task.label||task.id));try{var result=await post(root.dataset.cleanupAction,{session_id:run.sessionId,task_id:task.id});addLogs(result.log);setTask(task.id,'done','Done',result.message||'Completed')}catch(error){addLog({level:'error',message:error.message||'Cleanup task failed.'});setTask(task.id,'error','Failed',error.message||'Failed')}done++;setProgress(done,total,'Cleanup tasks',done+' of '+total+' steps complete.')}for(var j=0;j<run.tables.length;j++){var table=run.tables[j];setTable(table.name,'running','Running','Working...');setProgress(done,total,'Table optimization','Optimizing '+table.name);try{var out=await post(root.dataset.tableAction,{session_id:run.sessionId,table:table.name});addLogs(out.log);var after=out.after&&out.after.overhead_label?out.after.overhead_label:'Done';setTable(table.name,'done','Done',out.message||'Optimized',after)}catch(error){addLog({level:'error',message:error.message||'Table optimization failed.'});setTable(table.name,'error','Failed',error.message||'Failed','Failed')}done++;setProgress(done,total,'Table optimization',done+' of '+total+' steps complete.')}setProgress(done,total,'Finishing cleanup','Disabling WP-Optimize after the cleanup run.');try{var finish=await post(root.dataset.finishAction,{session_id:run.sessionId});addLogs(finish.log)}catch(error){addLog({level:'error',message:error.message||'Finish step failed.'})}done++;setProgress(done,total,'Cleanup complete','Database cleanup and table optimization finished.');setRunState('Finished');dynOk(button,'Cleanup Finished',false)}).catch(function(error){setRunState('Failed');addLog({level:'error',message:error.message||'Cleanup failed.'});dynFail(button,'Cleanup Failed',false)})}
            root.addEventListener('click',function(event){var r=event.target.closest('[data-db-refresh]');if(r){event.preventDefault();refresh(r);return}var runBtn=event.target.closest('[data-db-run]');if(runBtn){event.preventDefault();run(runBtn);return}var clear=event.target.closest('[data-db-clear-log]');if(clear){event.preventDefault();event.stopPropagation();if(els.log)els.log.innerHTML='';addLog({level:'info',message:'Database cleanup log cleared.'})}});
            refresh(root.querySelector('[data-db-refresh]'));
        })();
        </script>
        <?php
    }
}
