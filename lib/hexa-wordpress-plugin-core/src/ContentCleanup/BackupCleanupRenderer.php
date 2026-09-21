<?php

namespace Hexa\PluginCore\ContentCleanup;

use Hexa\PluginCore\WpAdminComponents\CoreUi;
use Hexa\PluginCore\WpAdminComponents\DynamicButton;

final class BackupCleanupRenderer {
    private BackupCleanupConfig $config;

    public function __construct( BackupCleanupConfig|array $config ) {
        $this->config = is_array( $config ) ? new BackupCleanupConfig( $config ) : $config;
    }

    public function render(): void {
        CoreUi::render_assets();
        DynamicButton::render_assets();

        $root_id = $this->config->root_id();
        $nonce   = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( $this->config->nonce_action() ) : '';
        ?>
        <div id="<?php echo esc_attr( $root_id ); ?>" class="hpc-ui hpc-cleanup-module hpc-backup-cleanup" data-hpc-backup-cleanup data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce-field="<?php echo esc_attr( $this->config->nonce_field() ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-scan-action="<?php echo esc_attr( $this->config->scan_action() ); ?>" data-delete-action="<?php echo esc_attr( $this->config->delete_action() ); ?>" data-empty-message="<?php echo esc_attr( (string) $this->config->get( 'empty_message' ) ); ?>" data-auto-scan="<?php echo esc_attr( $this->config->auto_scan() ? '1' : '0' ); ?>">
            <?php $this->styles( $root_id ); ?>
            <?php ob_start(); ?>
                <?php echo $this->description_card(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php echo $this->locations_card(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <div class="hpc-actions" style="margin:0 0 14px;">
                    <?php echo DynamicButton::render( [ 'label' => 'Scan Backup Files', 'working_label' => 'Scanning...', 'success_label' => 'Scanned', 'class' => 'hpc-button secondary', 'attrs' => [ 'data-backup-scan' => true ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <div class="hpc-cleanup-table-wrap">
                    <table class="hpc-cleanup-table">
                        <thead><tr><th>Backup File</th><th>Source</th><th>Size</th><th>Modified</th><th>Age</th><th>Writable</th><th>Action</th></tr></thead>
                        <tbody data-backup-results><tr><td colspan="7" class="hpc-cleanup-muted">Press Scan Backup Files to inspect configured locations.</td></tr></tbody>
                    </table>
                </div>
                <?php $this->log_html(); ?>
            <?php
            $section_body = (string) ob_get_clean();
            echo CoreUi::collapsible(
                [
                    'title'       => (string) $this->config->get( 'title' ),
                    'open'        => true,
                    'persist_key' => $root_id . '-section',
                    'meta_html'   => '<span class="hpc-cleanup-count">' . CoreUi::pill( 'Detected: 0', 'dark' ) . '</span>',
                    'body_html'   => $section_body,
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
            #<?php echo esc_attr( $root_id ); ?>{margin-top:14px;max-width:100%;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-section,#<?php echo esc_attr( $root_id ); ?> .hpc-section-body{max-width:100%;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-section-description{color:#3f4d63;font-size:13px;line-height:1.55;margin:0 0 14px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-description-text{color:var(--hpc-muted);font-size:12px;line-height:1.45;margin:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-backup-location-list{display:grid;gap:4px;margin:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-backup-location{background:transparent;border:0;border-top:1px solid #edf1f6;border-radius:0;padding:7px 0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-backup-location:first-child{border-top:0;padding-top:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-backup-location-head{align-items:center;display:flex;flex-wrap:wrap;gap:6px;margin:0 0 4px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-backup-location-head strong{font-size:12px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-backup-location-meta{color:var(--hpc-muted);display:grid;font-size:11px;gap:3px;line-height:1.4;margin:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-backup-location-path{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;overflow-wrap:anywhere}
            #<?php echo esc_attr( $root_id ); ?> .hpc-backup-location-dirs{margin:4px 0 0 18px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-backup-location-dirs li{margin:3px 0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-backup-location-empty{color:var(--hpc-muted);font-size:13px;margin:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-table-wrap{background:#fff;border:1px solid var(--hpc-line);border-radius:8px;max-width:100%;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-table{border-collapse:collapse;table-layout:fixed;width:100%}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-table th,#<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-table td{border-bottom:1px solid var(--hpc-line);overflow-wrap:anywhere;padding:12px;text-align:left;vertical-align:middle;word-break:normal}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-table th{background:#f8fafc;color:#314056;font-size:12px;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-loading-row{align-items:center;color:var(--hpc-muted);display:flex;gap:8px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-loading-row .spinner{float:none;margin:0;visibility:visible}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-title{font-weight:800;line-height:1.35}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-slug{color:var(--hpc-muted);font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;font-size:12px;margin-top:4px;word-break:break-all}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-muted{color:var(--hpc-muted);font-size:12px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-row.is-working{opacity:.58}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log{border-radius:8px;overflow:hidden;border:1px solid #263241;background:#0f1720;color:#dbe7f3;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;margin-top:16px;max-width:100%}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log summary{list-style:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log summary::-webkit-details-marker{display:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-head{align-items:center;background:#111c2a;cursor:pointer;display:flex;gap:12px;justify-content:space-between;padding:14px 16px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log[open] .hpc-cleanup-log-head{border-bottom:1px solid #263241}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-title{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:15px;font-weight:800;margin:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-pill{background:#1f2f44;border:1px solid #34465d;border-radius:999px;color:#b9c7d8;font-size:12px;padding:4px 9px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-controls{align-items:center;display:inline-flex;gap:10px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-chevron{align-items:center;background:#1f2f44;border:1px solid #34465d;border-radius:999px;color:#cbd5e1;display:inline-flex;height:28px;justify-content:center;width:28px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-chevron svg{fill:currentColor;height:12px;transform:rotate(0deg);transition:transform .18s;width:12px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log[open] .hpc-cleanup-log-chevron svg{transform:rotate(180deg)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-body{max-height:300px;max-width:100%;overflow:auto}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-row{border-top:1px solid #1f2f44;display:grid;gap:10px;grid-template-columns:minmax(70px,92px) minmax(58px,82px) minmax(0,1fr);padding:12px 16px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-row>*{min-width:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-time{color:#8ca1b8;font-size:12px;white-space:nowrap}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-level{border-radius:5px;font-size:11px;font-weight:900;letter-spacing:.04em;padding:3px 7px;text-align:center;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-level.info{background:#16324f;color:#9bd0ff}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-level.success{background:#14391f;color:#9cf0b0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-level.warning{background:#493813;color:#ffd37a}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-level.error{background:#4c1720;color:#ff9cac}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-message{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:13px;font-weight:650;margin-bottom:4px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-context{color:#9fb1c6;font-size:12px;overflow-wrap:anywhere;white-space:pre-wrap;word-break:break-word}
            @media(max-width:700px){#<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-row{grid-template-columns:1fr}}
        </style>
        <?php
    }

    private function log_html(): void {
        ?>
        <details class="hpc-cleanup-log">
            <summary class="hpc-cleanup-log-head">
                <div><h3 class="hpc-cleanup-log-title">Backup Activity Log</h3><span class="hpc-cleanup-log-pill">Hexa Core Log Type 1</span></div>
                <span class="hpc-cleanup-log-controls">
                    <span class="hpc-cleanup-log-chevron" aria-hidden="true"><svg viewBox="0 0 512 512" focusable="false"><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"></path></svg></span>
                    <button type="button" class="hpc-button secondary" data-backup-clear-log>Clear</button>
                </span>
            </summary>
            <div class="hpc-cleanup-log-body" data-backup-log-body></div>
        </details>
        <?php
    }

    private function description_card(): string {
        $description = trim( (string) $this->config->get( 'description' ) );
        if ( '' === $description ) {
            $description = 'Scan configured backup locations and delete selected files through guarded AJAX actions.';
        }

        return $this->detail_card(
            [
                'title'       => 'Description',
                'open'        => false,
                'variant'     => 'subtle',
                'persist_key' => $this->config->root_id() . '-description',
                'body_html'   => '<p class="hpc-cleanup-description-text">' . esc_html( $description ) . '</p>',
            ]
        );
    }

    private function locations_card(): string {
        $locations = $this->config->locations();

        if ( [] === $locations ) {
            $body = '<p class="hpc-backup-location-empty">No backup locations are configured for this cleanup service.</p>';
        } else {
            $items = '';
            foreach ( $locations as $location ) {
                $items .= $this->location_item_html( $location );
            }
            $body = '<div class="hpc-backup-location-list">' . $items . '</div>';
        }

        return $this->detail_card(
            [
                'title'       => 'Scan Locations',
                'open'        => false,
                'variant'     => 'subtle',
                'persist_key' => $this->config->root_id() . '-locations',
                'meta_html'   => CoreUi::pill( count( $locations ) . ' configured', 'dark' ),
                'body_html'   => $body,
            ]
        );
    }

    private function location_item_html( array $location ): string {
        $path       = (string) ( $location['path'] ?? '' );
        $extensions = array_values( array_map( static fn( mixed $extension ): string => (string) $extension, (array) ( $location['extensions'] ?? [] ) ) );
        $resolved   = $this->resolved_directories( $path );
        $exists     = [] !== array_filter( $resolved, 'is_dir' );
        $readable   = [] !== array_filter( $resolved, 'is_readable' );
        $state      = $readable ? CoreUi::pill( 'Readable', 'success' ) : ( $exists ? CoreUi::pill( 'Not readable', 'danger' ) : CoreUi::pill( 'No directory found', 'warning' ) );

        $dirs_html = '<span class="hpc-backup-location-empty">No matching directories currently exist.</span>';
        if ( [] !== $resolved ) {
            $dirs = '';
            foreach ( $resolved as $directory ) {
                $label = is_dir( $directory ) ? ( is_readable( $directory ) ? 'readable' : 'not readable' ) : 'missing';
                $dirs .= '<li><span class="hpc-backup-location-path">' . esc_html( $directory ) . '</span> <span class="hpc-cleanup-muted">(' . esc_html( $label ) . ')</span></li>';
            }
            $dirs_html = '<ul class="hpc-backup-location-dirs">' . $dirs . '</ul>';
        }

        return '<article class="hpc-backup-location">'
            . '<div class="hpc-backup-location-head"><strong>' . esc_html( (string) ( $location['name'] ?? $location['id'] ?? 'Backup location' ) ) . '</strong>' . $state . '</div>'
            . '<div class="hpc-backup-location-meta">'
            . '<span><strong>Configured path:</strong> <span class="hpc-backup-location-path">' . esc_html( $path ) . '</span></span>'
            . '<span><strong>Allowed extensions:</strong> ' . esc_html( implode( ', ', $extensions ) ) . '</span>'
            . '<span><strong>Resolved directories scanned:</strong></span>' . $dirs_html
            . '</div>'
            . '</article>';
    }

    private function resolved_directories( string $path ): array {
        if ( '' === $path ) {
            return [];
        }

        if ( false !== strpos( $path, '*' ) ) {
            $matches = glob( $path, GLOB_ONLYDIR );
            return is_array( $matches ) ? array_values( $matches ) : [];
        }

        return [ $path ];
    }

    private function script( string $root_id ): void {
        ?>
        <script>
        (function(){
            var root=document.getElementById('<?php echo esc_js( $root_id ); ?>'); if(!root||root.dataset.backupReady==='1')return; root.dataset.backupReady='1';
            var tbody=root.querySelector('[data-backup-results]'), countPill=root.querySelector('.hpc-cleanup-count .hpc-pill'), logBody=root.querySelector('[data-backup-log-body]');
            function text(v){return v===null||v===undefined?'':String(v)} function esc(v){var d=document.createElement('div');d.textContent=text(v);return d.innerHTML} function now(){return new Date().toTimeString().slice(0,8)}
            function dynStart(b,l){if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.start(b,l);else if(b)b.disabled=true} function dynOk(b,l){if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.success(b,l||'Done');else if(b)b.disabled=false} function dynFail(b,l){if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.error(b,l||'Failed');else if(b)b.disabled=false}
            function addLog(e){if(!logBody)return;e=e||{};var level=text(e.level||'info').toLowerCase(),ctx=e.context&&Object.keys(e.context).length?JSON.stringify(e.context,null,2):'',row=document.createElement('div');row.className='hpc-cleanup-log-row';row.innerHTML='<div class="hpc-cleanup-log-time">'+esc(e.time||now())+'</div><div><span class="hpc-cleanup-log-level '+esc(level)+'">'+esc(level)+'</span></div><div><div class="hpc-cleanup-log-message">'+esc(e.message||'')+'</div>'+(ctx?'<div class="hpc-cleanup-log-context">'+esc(ctx)+'</div>':'')+'</div>';logBody.appendChild(row);logBody.scrollTop=logBody.scrollHeight}
            function addLogs(logs){(logs||[]).forEach(addLog)} function setCount(n){if(countPill)countPill.textContent='Detected: '+n}
            function post(action,payload){var body=new URLSearchParams();body.set('action',action);body.set(root.dataset.nonceField||'nonce',root.dataset.nonce||'');Object.keys(payload||{}).forEach(function(k){body.set(k,payload[k])});return fetch(root.dataset.ajaxUrl||window.ajaxurl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()}).then(function(r){return r.json()}).then(function(p){if(!p||!p.success){var m=p&&p.data&&(p.data.message||p.data.error)?(p.data.message||p.data.error):'AJAX request failed.';throw new Error(m)}return p.data||{}})}
            function rowHtml(row){var disabled=row.writable?'':' disabled title="File is not writable"';return '<tr class="hpc-cleanup-row" data-file-id="'+esc(row.id)+'"><td><div class="hpc-cleanup-title">'+esc(row.file)+'</div><div class="hpc-cleanup-slug">'+esc(row.path)+'</div></td><td>'+esc(row.source)+'</td><td>'+esc(row.size_label)+'</td><td>'+esc(row.modified_label)+'</td><td>'+esc(row.age_days===null?'Unknown':row.age_days+' days')+'</td><td>'+(row.writable?'<span class="hpc-pill success">Writable</span>':'<span class="hpc-pill danger">Locked</span>')+'</td><td><button type="button" class="hpc-button danger" data-backup-delete data-file-id="'+esc(row.id)+'"'+disabled+'>Delete</button></td></tr>'}
            function renderRows(rows){rows=rows||[];setCount(rows.length);if(!tbody)return;if(!rows.length){tbody.innerHTML='<tr><td colspan="7" class="hpc-cleanup-muted">No results found.</td></tr>';return}tbody.innerHTML=rows.map(rowHtml).join('')}
            function renderScanning(){if(tbody)tbody.innerHTML='<tr><td colspan="7"><div class="hpc-cleanup-loading-row"><span class="spinner is-active"></span><span>Scanning backup folders and file patterns...</span></div></td></tr>'}
            function openFreshManualLog(){if(!logBody)return;logBody.innerHTML='';var details=logBody.closest('details');if(details)details.open=true}
            function scan(button,showLog){if(showLog)openFreshManualLog();dynStart(button,'Scanning...');renderScanning();addLog({level:'info',message:'Starting backup file scan.'});post(root.dataset.scanAction||'',{}).then(function(data){addLogs(data.log);renderRows(data.rows||[]);if(!(data.rows||[]).length)addLog({level:'warning',message:'No results found after scanning configured backup locations.'});dynOk(button,'Scanned')}).catch(function(error){addLog({level:'error',message:error.message||'Scan failed.'});dynFail(button,'Failed')})}
            root.addEventListener('click',function(event){var scanButton=event.target.closest('[data-backup-scan]');if(scanButton){event.preventDefault();scan(scanButton,true);return}var clearButton=event.target.closest('[data-backup-clear-log]');if(clearButton){event.preventDefault();event.stopPropagation();if(logBody)logBody.innerHTML='';addLog({level:'info',message:'Backup activity log cleared.'});return}var del=event.target.closest('[data-backup-delete]');if(del){event.preventDefault();var id=del.getAttribute('data-file-id')||'';if(!window.confirm('Delete this backup file? This cannot be undone.'))return;var row=root.querySelector('.hpc-cleanup-row[data-file-id="'+id+'"]');if(row)row.classList.add('is-working');dynStart(del,'Deleting...');addLog({level:'warning',message:'Sending backup delete AJAX request.',context:{file_id:id}});post(root.dataset.deleteAction||'',{file_id:id}).then(function(data){addLogs(data.log);if(row)row.remove();setCount(root.querySelectorAll('.hpc-cleanup-row').length);if(!root.querySelector('.hpc-cleanup-row')&&tbody)tbody.innerHTML='<tr><td colspan="7" class="hpc-cleanup-muted">'+esc(root.dataset.emptyMessage||'No backup files detected.')+'</td></tr>';dynOk(del,'Deleted')}).catch(function(error){if(row)row.classList.remove('is-working');addLog({level:'error',message:error.message||'Delete failed.',context:{file_id:id}});dynFail(del,'Failed')})}});
            if(root.dataset.autoScan==='1'){addLog({level:'info',message:'Backup cleanup UI loaded. Auto-running backup scan.'});scan(root.querySelector('[data-backup-scan]'),false)}else{addLog({level:'info',message:'Backup cleanup UI loaded. Waiting for manual scan.'})}
        })();
        </script>
        <?php
    }

    private function detail_card( array $args ): string {
        if ( method_exists( CoreUi::class, 'detail_card' ) ) {
            return CoreUi::detail_card( $args );
        }

        return CoreUi::collapsible(
            [
                'title'       => (string) ( $args['title'] ?? '' ),
                'open'        => ! empty( $args['open'] ),
                'persist_key' => (string) ( $args['persist_key'] ?? '' ),
                'meta_html'   => (string) ( $args['meta_html'] ?? '' ),
                'body_html'   => (string) ( $args['body_html'] ?? '' ),
            ]
        );
    }
}
