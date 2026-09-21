<?php

namespace Hexa\PluginCore\ContentCleanup;

use Hexa\PluginCore\WpAdminComponents\CoreUi;
use Hexa\PluginCore\WpAdminComponents\DynamicButton;

final class ContentCleanupRenderer {
    private ContentCleanupConfig $config;

    public function __construct( ContentCleanupConfig|array $config ) {
        $this->config = is_array( $config ) ? new ContentCleanupConfig( $config ) : $config;
    }

    public function render(): void {
        CoreUi::render_assets();
        DynamicButton::render_assets();

        $root_id  = $this->config->root_id();
        $defaults = $this->config->default_criteria();
        $nonce    = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( $this->config->nonce_action() ) : '';
        $show_filters = $this->config->show_filters();
        ?>
        <div id="<?php echo esc_attr( $root_id ); ?>" class="hpc-ui hpc-content-cleanup" data-hpc-content-cleanup data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce-field="<?php echo esc_attr( $this->config->nonce_field() ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-scan-action="<?php echo esc_attr( $this->config->scan_action() ); ?>" data-trash-action="<?php echo esc_attr( $this->config->trash_action() ); ?>" data-delete-action="<?php echo esc_attr( $this->config->delete_action() ); ?>" data-empty-message="<?php echo esc_attr( (string) $this->config->get( 'empty_message' ) ); ?>" data-default-criteria="<?php echo esc_attr( wp_json_encode( $defaults ) ); ?>" data-count-label="<?php echo esc_attr( $this->config->count_label() ); ?>" data-auto-scan="<?php echo esc_attr( $this->config->auto_scan() ? '1' : '0' ); ?>">
            <style>
                #<?php echo esc_attr( $root_id ); ?>{max-width:100%;overflow:hidden}
                #<?php echo esc_attr( $root_id ); ?> .hpc-section,#<?php echo esc_attr( $root_id ); ?> .hpc-section-body{max-width:100%;overflow:hidden}
                #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-section-description{color:#3f4d63;font-size:13px;line-height:1.55;margin:0 0 14px}
                #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-description-text{color:var(--hpc-muted);font-size:12px;line-height:1.45;margin:0}
                #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-filters{display:grid;gap:12px;grid-template-columns:repeat(6,minmax(0,1fr));margin-bottom:14px}
                #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-filter-wide{grid-column:span 2}
                #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-table-wrap{background:#fff;border:1px solid var(--hpc-line);border-radius:8px;max-width:100%;overflow:hidden}
                #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-table{border-collapse:collapse;table-layout:fixed;width:100%}
                #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-table th,#<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-table td{border-bottom:1px solid var(--hpc-line);overflow-wrap:anywhere;padding:12px;text-align:left;vertical-align:middle;word-break:normal}
                #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-table th{background:#f8fafc;color:#314056;font-size:12px;text-transform:uppercase}
                #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-title{font-weight:800;line-height:1.35}
                #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-slug{color:var(--hpc-muted);font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;font-size:12px;margin-top:4px;word-break:break-all}
                #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-row.is-working{opacity:.58}
                #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-actions{align-items:flex-start;display:flex;flex-direction:column;gap:8px}
                #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-actions .hpc-button{padding:8px 10px}
                #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-muted{color:var(--hpc-muted);font-size:12px}
                #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-count{align-items:center;display:flex;gap:8px}
                #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-flags{align-items:center;display:flex;flex-wrap:wrap;gap:6px}
                #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-flag{border-radius:999px;display:inline-flex;font-size:12px;font-weight:800;line-height:1;padding:7px 9px}
                #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-flag.warning{background:#fff7e0;border:1px solid #f5df9c;color:#9a6700}
                #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-flag.danger{background:#fff0f2;border:1px solid #ffd0d8;color:#b42336}
                #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-flag.success{background:#eaf8ef;border:1px solid #ccefd7;color:#16803c}
                #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-flag.dark,#<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-flag.info{background:#eef2ff;border:1px solid #dbe4ff;color:#2944ad}
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
                #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-body{max-height:360px;max-width:100%;overflow:auto}
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
                @media(max-width:1100px){#<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-filters{grid-template-columns:repeat(2,minmax(0,1fr))}#<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-filter-wide{grid-column:span 2}}
                @media(max-width:700px){#<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-filters{grid-template-columns:1fr}#<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-filter-wide{grid-column:auto}#<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-row{grid-template-columns:1fr}}
            </style>

            <?php ob_start(); ?>
                <?php echo $this->description_card(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php if ( $show_filters ) : ?>
                    <?php
                    echo CoreUi::collapsible(
                        [
                            'title'       => 'Detection Filters',
                            'open'        => true,
                            'persist_key' => $root_id . '-filters',
                            'body_html'   => $this->filters_html( $defaults ),
                        ]
                    ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                <?php else : ?>
                    <div class="hpc-actions" style="margin:0 0 14px;">
                        <?php echo DynamicButton::render( [ 'label' => 'Refresh Report', 'working_label' => 'Scanning...', 'success_label' => 'Updated', 'class' => 'hpc-button secondary', 'attrs' => [ 'data-cleanup-scan' => true ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php endif; ?>

                <div class="hpc-cleanup-table-wrap">
                    <table class="hpc-cleanup-table">
                        <thead>
                            <tr>
                                <th>Title / Slug</th>
                                <th>Flag</th>
                                <th>Status</th>
                                <th>Date Published</th>
                                <th>Date Modified</th>
                                <th>Edit</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody data-cleanup-results>
                            <tr><td colspan="7" class="hpc-cleanup-muted">Press Scan Pages to run the cleanup report.</td></tr>
                        </tbody>
                    </table>
                </div>

                <details class="hpc-cleanup-log" data-cleanup-log>
                    <summary class="hpc-cleanup-log-head">
                        <div>
                            <h3 class="hpc-cleanup-log-title">Activity Log</h3>
                            <span class="hpc-cleanup-log-pill">Hexa Core Log Type 1</span>
                        </div>
                        <span class="hpc-cleanup-log-controls">
                            <span class="hpc-cleanup-log-chevron" aria-hidden="true"><svg viewBox="0 0 512 512" focusable="false"><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"></path></svg></span>
                            <button type="button" class="hpc-button secondary" data-cleanup-clear-log>Clear</button>
                        </span>
                    </summary>
                    <div class="hpc-cleanup-log-body" data-cleanup-log-body></div>
                </details>
            <?php
            $section_body = (string) ob_get_clean();
            echo CoreUi::collapsible(
                [
                    'title'       => (string) $this->config->get( 'title' ),
                    'open'        => true,
                    'persist_key' => $root_id . '-section',
                    'meta_html'   => '<span class="hpc-cleanup-count">' . CoreUi::pill( $this->config->count_label() . ': 0', 'dark' ) . '</span>',
                    'body_html'   => $section_body,
                ]
            ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>

            <script>
            (function(){
                var root = document.getElementById('<?php echo esc_js( $root_id ); ?>');
                if (!root || root.dataset.cleanupReady === '1') return;
                root.dataset.cleanupReady = '1';
                var form = root.querySelector('[data-cleanup-filters]');
                var tbody = root.querySelector('[data-cleanup-results]');
                var countPill = root.querySelector('.hpc-cleanup-count .hpc-pill');
                var logBody = root.querySelector('[data-cleanup-log-body]');
                function text(value){ return value === null || value === undefined ? '' : String(value); }
                function esc(value){ var div = document.createElement('div'); div.textContent = text(value); return div.innerHTML; }
                function now(){ var date = new Date(); return date.toTimeString().slice(0,8); }
                function dynamicStart(button, label){ if (window.HexaWpCoreDynamicButton) window.HexaWpCoreDynamicButton.start(button, label); else if (button) button.disabled = true; }
                function dynamicSuccess(button, label){ if (window.HexaWpCoreDynamicButton) window.HexaWpCoreDynamicButton.success(button, label || 'Done'); else if (button) button.disabled = false; }
                function dynamicError(button, label){ if (window.HexaWpCoreDynamicButton) window.HexaWpCoreDynamicButton.error(button, label || 'Failed'); else if (button) button.disabled = false; }
                function addLog(entry){
                    if (!logBody) return;
                    entry = entry || {};
                    var row = document.createElement('div');
                    var level = text(entry.level || 'info').toLowerCase();
                    var context = entry.context && Object.keys(entry.context).length ? JSON.stringify(entry.context, null, 2) : '';
                    row.className = 'hpc-cleanup-log-row';
                    row.innerHTML = '<div class="hpc-cleanup-log-time">' + esc(entry.time || now()) + '</div><div><span class="hpc-cleanup-log-level ' + esc(level) + '">' + esc(level) + '</span></div><div><div class="hpc-cleanup-log-message">' + esc(entry.message || '') + '</div>' + (context ? '<div class="hpc-cleanup-log-context">' + esc(context) + '</div>' : '') + '</div>';
                    logBody.appendChild(row);
                    logBody.scrollTop = logBody.scrollHeight;
                }
                function addLogs(logs){ (logs || []).forEach(addLog); }
                function criteria(){
                    if (!form) {
                        try { return JSON.parse(root.dataset.defaultCriteria || '{}') || {}; } catch(e) { return {}; }
                    }
                    var data = new FormData(form);
                    return {
                        post_type: data.get('post_type') || '',
                        status: data.get('status') || '',
                        published_before_days: data.get('published_before_days') || '0',
                        modified_before_days: data.get('modified_before_days') || '0',
                        search: data.get('search') || '',
                        limit: data.get('limit') || '50'
                    };
                }
                function post(action, payload){
                    var body = new URLSearchParams();
                    body.set('action', action);
                    body.set(root.dataset.nonceField || 'nonce', root.dataset.nonce || '');
                    Object.keys(payload || {}).forEach(function(key){ body.set(key, payload[key]); });
                    return fetch(root.dataset.ajaxUrl || window.ajaxurl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                        body: body.toString()
                    }).then(function(response){ return response.json(); }).then(function(payload){
                        if (!payload || !payload.success) {
                            var message = payload && payload.data && (payload.data.message || payload.data.error) ? (payload.data.message || payload.data.error) : 'AJAX request failed.';
                            throw new Error(message);
                        }
                        return payload.data || {};
                    });
                }
                function setCount(count){ if (countPill) countPill.textContent = (root.dataset.countLabel || 'Detected') + ': ' + count; }
                function flagsHtml(row) {
                    var flags = row.flags || [];
                    if (!flags.length) return '<span class="hpc-cleanup-muted">No flag</span>';
                    return '<div class="hpc-cleanup-flags">' + flags.map(function(flag){
                        var title = flag.description ? ' title="' + esc(flag.description) + '"' : '';
                        return '<span class="hpc-cleanup-flag ' + esc(flag.tone || 'warning') + '"' + title + '>' + esc(flag.label || 'Flag') + '</span>';
                    }).join('') + '</div>';
                }
                function rowHtml(row){
                    var disabled = row.protected ? ' disabled title="' + esc(row.protected_reason || 'Protected item') + '"' : '';
                    var protectedPill = row.protected ? ' <span class="hpc-pill warning">Protected</span>' : '';
                    var edit = row.edit_url ? '<a class="hpc-button secondary hpc-external" href="' + esc(row.edit_url) + '" target="_blank" rel="noopener noreferrer">Edit</a>' : '<span class="hpc-cleanup-muted">No edit link</span>';
                    return '<tr class="hpc-cleanup-row" data-post-id="' + esc(row.id) + '">'
                        + '<td><div class="hpc-cleanup-title">' + esc(row.title) + protectedPill + '</div><div class="hpc-cleanup-slug">' + esc(row.slug) + '</div></td>'
                        + '<td>' + flagsHtml(row) + '</td>'
                        + '<td>' + esc(row.status) + '</td>'
                        + '<td>' + esc(row.published_label) + '</td>'
                        + '<td>' + esc(row.modified_label) + '</td>'
                        + '<td>' + edit + '</td>'
                        + '<td><div class="hpc-cleanup-actions"><button type="button" class="hpc-button danger" data-cleanup-trash data-post-id="' + esc(row.id) + '"' + disabled + '>Move to Trash</button><button type="button" class="hpc-button danger" data-cleanup-delete data-post-id="' + esc(row.id) + '"' + disabled + '>Delete Permanently</button></div></td>'
                        + '</tr>';
                }
                function renderRows(rows){
                    rows = rows || [];
                    setCount(rows.length);
                    if (!tbody) return;
                    if (!rows.length) {
                        tbody.innerHTML = '<tr><td colspan="7" class="hpc-cleanup-muted">' + esc(root.dataset.emptyMessage || 'No matching items found.') + '</td></tr>';
                        return;
                    }
                    tbody.innerHTML = rows.map(rowHtml).join('');
                }
                function scan(button){
                    dynamicStart(button, 'Scanning...');
                    addLog({level:'info', message:'Starting content cleanup report request.', context: criteria()});
                    post(root.dataset.scanAction || '', criteria()).then(function(data){
                        addLogs(data.log);
                        renderRows(data.rows || []);
                        dynamicSuccess(button, 'Scanned');
                    }).catch(function(error){
                        addLog({level:'error', message:error.message || 'Scan failed.'});
                        dynamicError(button, 'Failed');
                    });
                }
                function removeRow(postId){
                    var row = root.querySelector('.hpc-cleanup-row[data-post-id="' + postId + '"]');
                    if (row) row.remove();
                    var remaining = root.querySelectorAll('.hpc-cleanup-row').length;
                    setCount(remaining);
                    if (!remaining && tbody) {
                        tbody.innerHTML = '<tr><td colspan="7" class="hpc-cleanup-muted">' + esc(root.dataset.emptyMessage || 'No matching items found.') + '</td></tr>';
                    }
                }
                root.addEventListener('click', function(event){
                    var scanButton = event.target.closest('[data-cleanup-scan]');
                    if (scanButton) {
                        event.preventDefault();
                        scan(scanButton);
                        return;
                    }
                    var clearButton = event.target.closest('[data-cleanup-clear-log]');
                    if (clearButton) {
                        event.preventDefault();
                        event.stopPropagation();
                        if (logBody) logBody.innerHTML = '';
                        addLog({level:'info', message:'Activity log cleared.'});
                        return;
                    }
                    var trashButton = event.target.closest('[data-cleanup-trash]');
                    if (trashButton) {
                        event.preventDefault();
                        var trashId = trashButton.getAttribute('data-post-id') || '';
                        dynamicStart(trashButton, 'Moving...');
                        addLog({level:'warning', message:'Sending move-to-trash AJAX request.', context:{post_id: trashId}});
                        post(root.dataset.trashAction || '', {post_id: trashId}).then(function(data){
                            addLogs(data.log);
                            removeRow(trashId);
                            dynamicSuccess(trashButton, 'Moved');
                        }).catch(function(error){
                            addLog({level:'error', message:error.message || 'Trash failed.', context:{post_id: trashId}});
                            dynamicError(trashButton, 'Failed');
                        });
                        return;
                    }
                    var deleteButton = event.target.closest('[data-cleanup-delete]');
                    if (deleteButton) {
                        event.preventDefault();
                        var deleteId = deleteButton.getAttribute('data-post-id') || '';
                        if (!window.confirm('Permanently delete this item? This cannot be undone.')) return;
                        dynamicStart(deleteButton, 'Deleting...');
                        addLog({level:'warning', message:'Sending permanent-delete AJAX request.', context:{post_id: deleteId}});
                        post(root.dataset.deleteAction || '', {post_id: deleteId}).then(function(data){
                            addLogs(data.log);
                            removeRow(deleteId);
                            dynamicSuccess(deleteButton, 'Deleted');
                        }).catch(function(error){
                            addLog({level:'error', message:error.message || 'Delete failed.', context:{post_id: deleteId}});
                            dynamicError(deleteButton, 'Failed');
                        });
                    }
                });
                if (form) {
                    form.addEventListener('submit', function(event){
                        event.preventDefault();
                        scan(root.querySelector('[data-cleanup-scan]'));
                    });
                }
                if (root.dataset.autoScan === '1') {
                    addLog({level:'info', message:'Cleanup UI loaded. Auto-running the content cleanup report.'});
                    scan(root.querySelector('[data-cleanup-scan]'));
                } else {
                    addLog({level:'info', message:'Cleanup UI loaded. Waiting for manual scan.'});
                }
            })();
            </script>
        </div>
        <?php
    }

    private function filters_html( array $defaults ): string {
        ob_start();
        ?>
        <form class="hpc-cleanup-filters" data-cleanup-filters>
            <label class="hpc-field">
                <span>Content Type</span>
                <?php echo $this->select_html( 'post_type', $this->config->post_types(), (string) $defaults['post_type'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </label>
            <label class="hpc-field">
                <span>Status</span>
                <?php echo $this->select_html( 'status', $this->config->statuses(), (string) $defaults['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </label>
            <label class="hpc-field">
                <span>Published Older Than</span>
                <input type="number" min="0" max="5000" name="published_before_days" value="<?php echo esc_attr( (string) $defaults['published_before_days'] ); ?>">
                <div class="hpc-small">Days. Use 0 to ignore published date.</div>
            </label>
            <label class="hpc-field">
                <span>Modified Older Than</span>
                <input type="number" min="0" max="5000" name="modified_before_days" value="<?php echo esc_attr( (string) $defaults['modified_before_days'] ); ?>">
                <div class="hpc-small">Days. Use 0 to ignore modified date.</div>
            </label>
            <label class="hpc-field hpc-cleanup-filter-wide">
                <span>Search Title / Content</span>
                <input type="search" name="search" value="" placeholder="Optional keyword">
            </label>
            <label class="hpc-field">
                <span>Limit</span>
                <input type="number" min="1" max="<?php echo esc_attr( (string) $this->config->max_limit() ); ?>" name="limit" value="<?php echo esc_attr( (string) $defaults['limit'] ); ?>">
            </label>
            <div class="hpc-actions" style="align-self:end;">
                <?php echo DynamicButton::render( [ 'label' => 'Detect Old Pages', 'working_label' => 'Scanning...', 'success_label' => 'Scanned', 'class' => 'hpc-button', 'attrs' => [ 'data-cleanup-scan' => true ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </form>
        <?php
        return (string) ob_get_clean();
    }

    private function description_card(): string {
        $description = trim( (string) $this->config->get( 'description' ) );
        if ( '' === $description ) {
            $description = 'Review the generated cleanup report before using any destructive action.';
        }

        $body = '<p class="hpc-cleanup-description-text">' . esc_html( $description ) . '</p>';

        return $this->detail_card(
            [
                'title'       => 'Description',
                'open'        => false,
                'variant'     => 'subtle',
                'persist_key' => $this->config->root_id() . '-description',
                'body_html'   => $body,
            ]
        );
    }

    private function rules_card(): string {
        $rules = $this->config->detection_rules();

        if ( [] === $rules ) {
            $body = '<p class="hpc-cleanup-rule-empty">No fixed detection rules are configured. This scan uses the visible filters above to build the WordPress content query.</p>';
        } else {
            $items = '';
            foreach ( $rules as $rule ) {
                $items .= $this->rule_item_html( $rule );
            }
            $body = '<div class="hpc-cleanup-rule-list">' . $items . '</div>';
        }

        return $this->detail_card(
            [
                'title'       => 'Detection Rules',
                'open'        => false,
                'variant'     => 'subtle',
                'persist_key' => $this->config->root_id() . '-rules',
                'meta_html'   => CoreUi::pill( count( $rules ) . ' rule' . ( 1 === count( $rules ) ? '' : 's' ), 'dark' ),
                'body_html'   => $body,
            ]
        );
    }

    private function rule_item_html( array $rule ): string {
        $terms      = array_map( static fn( string $term ): string => '"' . $term . '"', (array) ( $rule['terms'] ?? [] ) );
        $fields     = array_map( [ $this, 'field_label' ], (array) ( $rule['fields'] ?? [] ) );
        $match      = 'contains' === (string) ( $rule['match'] ?? 'word' ) ? 'contains match' : 'whole-word match';
        $tone       = (string) ( $rule['tone'] ?? 'warning' );
        $tone_label = [
            'warning' => 'Yellow flag',
            'danger'  => 'Red flag',
            'success' => 'Green flag',
            'dark'    => 'Dark flag',
            'info'    => 'Info flag',
        ][ $tone ] ?? 'Flag';
        $description = trim( (string) ( $rule['description'] ?? '' ) );
        if ( '' === $description ) {
            $description = 'Flags content when the configured terms match the selected fields.';
        }

        $exclude_parts = [];
        if ( ! empty( $rule['exclude_option_ids'] ) ) {
            $exclude_parts[] = 'WordPress option IDs: ' . implode( ', ', array_map( static fn( mixed $value ): string => (string) $value, (array) $rule['exclude_option_ids'] ) );
        }
        if ( ! empty( $rule['exclude_post_ids'] ) ) {
            $exclude_parts[] = 'Post IDs: ' . implode( ', ', array_map( 'absint', (array) $rule['exclude_post_ids'] ) );
        }

        $meta = [
            'Terms: ' . esc_html( implode( ', ', $terms ) ),
            'Fields checked: ' . esc_html( implode( ', ', $fields ) ),
            'Match type: ' . esc_html( $match ),
        ];
        if ( [] !== $exclude_parts ) {
            $meta[] = 'Exclusions: ' . esc_html( implode( '; ', $exclude_parts ) );
        }

        return '<article class="hpc-cleanup-rule-item">'
            . '<div class="hpc-cleanup-rule-head"><strong>' . esc_html( (string) ( $rule['label'] ?? 'Rule' ) ) . '</strong>' . CoreUi::pill( $tone_label, $tone ) . '</div>'
            . '<p class="hpc-cleanup-rule-body">' . esc_html( $description ) . '</p>'
            . '<div class="hpc-cleanup-rule-meta"><span>' . implode( '</span><span>', $meta ) . '</span></div>'
            . '</article>';
    }

    private function field_label( string $field ): string {
        return [
            'title'   => 'Title',
            'slug'    => 'Slug',
            'content' => 'Content',
            'excerpt' => 'Excerpt',
        ][ $field ] ?? ucwords( str_replace( '_', ' ', $field ) );
    }

    private function select_html( string $name, array $options, string $selected ): string {
        $html = '<select name="' . esc_attr( $name ) . '">';
        foreach ( $options as $value => $label ) {
            $html .= '<option value="' . esc_attr( (string) $value ) . '"' . selected( $selected, (string) $value, false ) . '>' . esc_html( (string) $label ) . '</option>';
        }
        $html .= '</select>';

        return $html;
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
