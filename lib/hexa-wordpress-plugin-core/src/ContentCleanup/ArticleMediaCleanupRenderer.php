<?php

namespace Hexa\PluginCore\ContentCleanup;

use Hexa\PluginCore\WpAdminComponents\CoreUi;
use Hexa\PluginCore\WpAdminComponents\DynamicButton;

final class ArticleMediaCleanupRenderer {
    private ArticleMediaCleanupConfig $config;

    public function __construct( ArticleMediaCleanupConfig|array $config ) {
        $this->config = is_array( $config ) ? new ArticleMediaCleanupConfig( $config ) : $config;
    }

    public function render(): void {
        CoreUi::render_assets();
        DynamicButton::render_assets();

        $root_id  = $this->config->root_id();
        $defaults = $this->config->default_criteria();
        $nonce    = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( $this->config->nonce_action() ) : '';
        ?>
        <div id="<?php echo esc_attr( $root_id ); ?>" class="hpc-ui hpc-cleanup-module hpc-article-media-cleanup" data-hpc-article-media-cleanup data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce-field="<?php echo esc_attr( $this->config->nonce_field() ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-scan-action="<?php echo esc_attr( $this->config->scan_action() ); ?>" data-delete-action="<?php echo esc_attr( $this->config->delete_action() ); ?>" data-batch-delete-action="<?php echo esc_attr( $this->config->batch_delete_action() ); ?>" data-empty-message="<?php echo esc_attr( (string) $this->config->get( 'empty_message' ) ); ?>" data-auto-scan="<?php echo esc_attr( $this->config->auto_scan() ? '1' : '0' ); ?>">
            <?php $this->styles( $root_id ); ?>
            <?php ob_start(); ?>
                <p class="hpc-cleanup-section-description"><?php echo esc_html( (string) $this->config->get( 'description' ) ); ?></p>
                <?php echo $this->primary_batch_html( $defaults ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php
                echo CoreUi::collapsible(
                    [
                        'title'       => 'Advanced Filters & Preview',
                        'open'        => true,
                        'persist_key' => $root_id . '-filters',
                        'body_html'   => $this->filters_html( $defaults ) . $this->preview_table_html(),
                    ]
                ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
                <?php $this->log_html(); ?>
            <?php
            $section_body = (string) ob_get_clean();
            echo CoreUi::collapsible(
                [
                    'title'       => (string) $this->config->get( 'title' ),
                    'open'        => true,
                    'persist_key' => $root_id . '-section',
                    'meta_html'   => '<span class="hpc-cleanup-count">' . CoreUi::pill( 'Articles: 0', 'dark' ) . '</span>',
                    'body_html'   => $section_body,
                ]
            ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
            <?php $this->script( $root_id ); ?>
        </div>
        <?php
    }

    private function primary_batch_html( array $defaults ): string {
        $keep_recent = max( 1, (int) ( $defaults['keep_recent'] ?? 25 ) );

        ob_start();
        ?>
        <section class="hpc-article-primary-batch" aria-label="Article batch deletion">
            <article class="hpc-article-batch-card">
                <div>
                    <h4>Delete All Matching Posts</h4>
                    <p>Deletes every post matched by the current advanced filters. Defaults to published posts with no search filter.</p>
                </div>
                <?php echo CoreUi::toggle( 'delete_media_all_matching', false, 'Delete associated media', [ 'tooltip' => 'Deletes detected featured images plus inline/gallery image attachments after each post is deleted.' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php echo DynamicButton::render( [ 'label' => 'Delete All Matching Posts', 'working_label' => 'Deleting...', 'success_label' => 'Deleted', 'class' => 'hpc-button danger', 'attrs' => [ 'data-article-delete-batch' => true, 'data-batch-mode' => 'all_matching' ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </article>
            <article class="hpc-article-batch-card">
                <div>
                    <h4>Delete Matching Except Latest X</h4>
                    <p>Preserves the newest matching posts, then deletes the rest.</p>
                </div>
                <label class="hpc-field hpc-article-keep-field">
                    <span>Keep Latest Posts</span>
                    <input type="number" min="1" max="5000" value="<?php echo esc_attr( (string) $keep_recent ); ?>" data-batch-keep-recent>
                </label>
                <?php echo CoreUi::toggle( 'delete_media_except_latest', false, 'Delete associated media', [ 'tooltip' => 'Deletes detected featured images plus inline/gallery image attachments after each deleted post.' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php echo DynamicButton::render( [ 'label' => 'Delete Matching Except Latest X', 'working_label' => 'Deleting...', 'success_label' => 'Deleted', 'class' => 'hpc-button danger', 'attrs' => [ 'data-article-delete-batch' => true, 'data-batch-mode' => 'all_except_keep_recent' ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </article>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function filters_html( array $defaults ): string {
        ob_start();
        ?>
        <form class="hpc-article-filters" data-article-filters>
            <label class="hpc-field"><span>Content Type</span><?php echo $this->select_html( 'post_type', $this->config->post_types(), (string) $defaults['post_type'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
            <label class="hpc-field"><span>Status</span><?php echo $this->select_html( 'status', $this->config->statuses(), (string) $defaults['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
            <label class="hpc-field hpc-article-filter-wide"><span>Search Title / Content</span><input type="search" name="search" value="" placeholder="Optional keyword"></label>
            <label class="hpc-field"><span>Preview Limit</span><input type="number" min="1" max="<?php echo esc_attr( (string) $this->config->max_limit() ); ?>" name="limit" value="<?php echo esc_attr( (string) $defaults['limit'] ); ?>"><div class="hpc-small">Only affects the preview table.</div></label>
            <label class="hpc-field"><span>Batch Size</span><input type="number" min="1" max="<?php echo esc_attr( (string) $this->config->max_batch_size() ); ?>" name="batch_size" value="<?php echo esc_attr( (string) $this->config->default_batch_size() ); ?>"><div class="hpc-small">Posts deleted per AJAX request.</div></label>
            <label class="hpc-field hpc-article-toggle"><span>Filtered Row Media Cleanup</span><?php echo CoreUi::toggle( 'delete_media_filtered', false, 'Delete associated media', [ 'tooltip' => 'Applies only to Delete Selected and individual row deletes from the preview table.' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><div class="hpc-small">Applies to selected rows and row deletes only.</div></label>
            <div class="hpc-actions hpc-article-actions">
                <?php echo DynamicButton::render( [ 'label' => 'Scan Articles', 'working_label' => 'Scanning...', 'success_label' => 'Scanned', 'class' => 'hpc-button secondary', 'attrs' => [ 'data-article-scan' => true ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php echo DynamicButton::render( [ 'label' => 'Delete Selected', 'working_label' => 'Deleting...', 'success_label' => 'Deleted', 'class' => 'hpc-button danger', 'attrs' => [ 'data-article-delete-selected' => true ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </form>
        <?php
        return (string) ob_get_clean();
    }

    private function preview_table_html(): string {
        ob_start();
        ?>
        <div class="hpc-cleanup-table-wrap">
            <table class="hpc-cleanup-table">
                <colgroup>
                    <col class="hpc-col-select">
                    <col class="hpc-col-article">
                    <col class="hpc-col-cleanup">
                    <col class="hpc-col-status">
                    <col class="hpc-col-published">
                    <col class="hpc-col-author">
                    <col class="hpc-col-media">
                    <col class="hpc-col-edit">
                    <col class="hpc-col-action">
                </colgroup>
                <thead><tr><th><label><input type="checkbox" data-article-select-all> Select</label></th><th>Article</th><th>Cleanup</th><th>Status</th><th>Published</th><th>Author</th><th>Associated Media</th><th>Edit</th><th>Row Action</th></tr></thead>
                <tbody data-article-results><tr><td colspan="9" class="hpc-cleanup-muted">Open filters and press Scan Articles to preview matching posts.</td></tr></tbody>
            </table>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function select_html( string $name, array $options, string $selected ): string {
        $html = '<select name="' . esc_attr( $name ) . '">';
        foreach ( $options as $value => $label ) {
            $html .= '<option value="' . esc_attr( (string) $value ) . '"' . selected( $selected, (string) $value, false ) . '>' . esc_html( (string) $label ) . '</option>';
        }
        $html .= '</select>';

        return $html;
    }

    private function styles( string $root_id ): void {
        ?>
        <style>
            #<?php echo esc_attr( $root_id ); ?>{margin-top:14px;max-width:100%;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-section,#<?php echo esc_attr( $root_id ); ?> .hpc-section-body{max-width:100%;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-section-description{color:#3f4d63;font-size:13px;line-height:1.55;margin:0 0 14px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-article-primary-batch{display:grid;gap:14px;grid-template-columns:repeat(2,minmax(0,1fr));margin:0 0 14px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-article-batch-card{align-content:start;background:#fff;border:1px solid var(--hpc-line);border-radius:8px;display:grid;gap:12px;padding:16px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-article-batch-card h4{font-size:15px;margin:0 0 5px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-article-batch-card p{color:var(--hpc-muted);font-size:12px;line-height:1.45;margin:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-article-keep-field{margin:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-article-filters{display:grid;gap:12px;grid-template-columns:repeat(6,minmax(0,1fr));margin-bottom:4px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-article-filter-wide{grid-column:span 2}
            #<?php echo esc_attr( $root_id ); ?> .hpc-article-toggle{grid-column:span 2}
            #<?php echo esc_attr( $root_id ); ?> .hpc-article-actions{align-self:end;grid-column:span 2}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-table-wrap{background:#fff;border:1px solid var(--hpc-line);border-radius:8px;max-width:100%;overflow:auto}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-table{border-collapse:collapse;min-width:1180px;table-layout:fixed;width:100%}
            #<?php echo esc_attr( $root_id ); ?> .hpc-col-select{width:72px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-col-article{width:22%}
            #<?php echo esc_attr( $root_id ); ?> .hpc-col-cleanup{width:118px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-col-status{width:90px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-col-published{width:120px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-col-author{width:120px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-col-media{width:24%}
            #<?php echo esc_attr( $root_id ); ?> .hpc-col-edit{width:80px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-col-action{width:118px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-table th,#<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-table td{border-bottom:1px solid var(--hpc-line);overflow-wrap:anywhere;padding:12px;text-align:left;vertical-align:middle;word-break:normal}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-table th{background:#f8fafc;color:#314056;font-size:12px;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-loading-row{align-items:center;color:var(--hpc-muted);display:flex;gap:8px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-loading-row .spinner{float:none;margin:0;visibility:visible}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-title{font-weight:800;line-height:1.35}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-slug{color:var(--hpc-muted);font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;font-size:12px;margin-top:4px;word-break:break-all}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-muted{color:var(--hpc-muted);font-size:12px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-row.is-working{background:#fffdf5}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-row.is-deleted{background:#f6fff8}
            #<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-row.is-failed{background:#fff7f7}
            #<?php echo esc_attr( $root_id ); ?> .hpc-status-badge{align-items:center;border:1px solid #d8e0ea;border-radius:999px;color:#475569;display:inline-flex;font-size:12px;font-weight:800;gap:7px;line-height:1;padding:6px 9px;white-space:nowrap}
            #<?php echo esc_attr( $root_id ); ?> .hpc-status-badge .hpc-status-icon{align-items:center;background:#e9eef5;border-radius:999px;color:#64748b;display:inline-flex;font-size:11px;font-weight:900;height:18px;justify-content:center;width:18px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-status-badge .spinner{float:none;height:16px;margin:0;visibility:visible;width:16px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-status-pending{background:#f8fafc}
            #<?php echo esc_attr( $root_id ); ?> .hpc-status-deleting{background:#fff8e8;border-color:#f4c46b;color:#8a5200}
            #<?php echo esc_attr( $root_id ); ?> .hpc-status-deleted{background:#e9f8ee;border-color:#bde8cb;color:#137333}
            #<?php echo esc_attr( $root_id ); ?> .hpc-status-deleted .hpc-status-icon{background:#137333;color:#fff}
            #<?php echo esc_attr( $root_id ); ?> .hpc-status-failed{background:#fdecec;border-color:#f3b2b2;color:#b42318}
            #<?php echo esc_attr( $root_id ); ?> .hpc-status-failed .hpc-status-icon{background:#b42318;color:#fff}
            #<?php echo esc_attr( $root_id ); ?> .hpc-status-skipped,#<?php echo esc_attr( $root_id ); ?> .hpc-status-preserved{background:#eef4ff;border-color:#c9d8ff;color:#294996}
            #<?php echo esc_attr( $root_id ); ?> .hpc-media-list{display:grid;gap:5px;max-width:100%}
            #<?php echo esc_attr( $root_id ); ?> .hpc-media-item{align-items:start;background:#f8fafc;border:1px solid #e0e6ef;border-radius:6px;display:grid;font-size:12px;gap:7px;grid-template-columns:auto minmax(0,1fr);padding:7px 8px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-media-title{font-weight:800;line-height:1.25}
            #<?php echo esc_attr( $root_id ); ?> .hpc-media-meta{color:var(--hpc-muted);font-size:11px;line-height:1.35;margin-top:2px}
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
            @media(max-width:1100px){#<?php echo esc_attr( $root_id ); ?> .hpc-article-primary-batch{grid-template-columns:1fr}#<?php echo esc_attr( $root_id ); ?> .hpc-article-filters{grid-template-columns:repeat(2,minmax(0,1fr))}#<?php echo esc_attr( $root_id ); ?> .hpc-article-filter-wide,#<?php echo esc_attr( $root_id ); ?> .hpc-article-toggle,#<?php echo esc_attr( $root_id ); ?> .hpc-article-actions{grid-column:span 2}}
            @media(max-width:700px){#<?php echo esc_attr( $root_id ); ?> .hpc-article-filters{grid-template-columns:1fr}#<?php echo esc_attr( $root_id ); ?> .hpc-article-filter-wide,#<?php echo esc_attr( $root_id ); ?> .hpc-article-toggle,#<?php echo esc_attr( $root_id ); ?> .hpc-article-actions{grid-column:auto}#<?php echo esc_attr( $root_id ); ?> .hpc-cleanup-log-row{grid-template-columns:1fr}}
        </style>
        <?php
    }

    private function log_html(): void {
        ?>
        <details class="hpc-cleanup-log">
            <summary class="hpc-cleanup-log-head">
                <div><h3 class="hpc-cleanup-log-title">Article Cleanup Activity Log</h3><span class="hpc-cleanup-log-pill">Hexa Core Log Type 1</span></div>
                <span class="hpc-cleanup-log-controls">
                    <span class="hpc-cleanup-log-chevron" aria-hidden="true"><svg viewBox="0 0 512 512" focusable="false"><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"></path></svg></span>
                    <button type="button" class="hpc-button secondary" data-article-clear-log>Clear</button>
                </span>
            </summary>
            <div class="hpc-cleanup-log-body" data-article-log-body></div>
        </details>
        <?php
    }

    private function script( string $root_id ): void {
        ?>
        <script>
        (function(){
            var root=document.getElementById('<?php echo esc_js( $root_id ); ?>'); if(!root||root.dataset.articleReady==='1')return; root.dataset.articleReady='1';
            var form=root.querySelector('[data-article-filters]'), tbody=root.querySelector('[data-article-results]'), countPill=root.querySelector('.hpc-cleanup-count .hpc-pill'), logBody=root.querySelector('[data-article-log-body]');
            function text(v){return v===null||v===undefined?'':String(v)}
            function esc(v){var d=document.createElement('div');d.textContent=text(v);return d.innerHTML}
            function attr(v){return text(v).replace(/\\/g,'\\\\').replace(/"/g,'\\"')}
            function safeStatus(v){var s=text(v||'pending').toLowerCase().replace(/[^a-z_-]/g,'');return s||'pending'}
            function now(){return new Date().toTimeString().slice(0,8)}
            function dynStart(b,l){if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.start(b,l);else if(b)b.disabled=true}
            function dynOk(b,l){if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.success(b,l||'Done');else if(b)b.disabled=false}
            function dynFail(b,l){if(window.HexaWpCoreDynamicButton)window.HexaWpCoreDynamicButton.error(b,l||'Failed');else if(b)b.disabled=false}
            function addLog(e){if(!logBody)return;e=e||{};var level=text(e.level||'info').toLowerCase(),row=document.createElement('div');row.className='hpc-cleanup-log-row';row.innerHTML='<div class="hpc-cleanup-log-time">'+esc(e.time||now())+'</div><div><span class="hpc-cleanup-log-level '+esc(level)+'">'+esc(level)+'</span></div><div><div class="hpc-cleanup-log-message">'+esc(e.message||'')+'</div></div>';logBody.appendChild(row);logBody.scrollTop=logBody.scrollHeight}
            function addLogs(logs){(logs||[]).forEach(addLog)}
            function setCount(n){if(countPill)countPill.textContent='Articles: '+n}
            function statusBadge(status,label){status=safeStatus(status);label=label||({pending:'Pending',deleting:'Deleting',deleted:'Deleted',failed:'Failed',skipped:'Kept',preserved:'Kept'}[status]||status);var icon='<span class="hpc-status-icon">•</span>';if(status==='deleting')icon='<span class="spinner is-active"></span>';if(status==='deleted')icon='<span class="hpc-status-icon">✓</span>';if(status==='failed')icon='<span class="hpc-status-icon">×</span>';if(status==='skipped'||status==='preserved')icon='<span class="hpc-status-icon">–</span>';return '<span class="hpc-status-badge hpc-status-'+esc(status)+'">'+icon+'<span>'+esc(label)+'</span></span>'}
            function criteria(){var data=new FormData(form);return{post_type:data.get('post_type')||'',status:data.get('status')||'',keep_recent:'0',search:data.get('search')||'',limit:data.get('limit')||'50'}}
            function batchSize(){var input=form?form.querySelector('input[name="batch_size"]'):null;var value=parseInt(input&&input.value?input.value:'50',10);return isNaN(value)||value<1?'50':String(value)}
            function deleteMediaEnabled(){var input=form?form.querySelector('input[name="delete_media_filtered"]'):null;return !!(input&&input.checked)}
            function batchDeleteMediaEnabled(mode){var input=root.querySelector('input[name="'+(mode==='all_except_keep_recent'?'delete_media_except_latest':'delete_media_all_matching')+'"]');return !!(input&&input.checked)}
            function batchKeepRecent(){var input=root.querySelector('[data-batch-keep-recent]');var value=parseInt(input&&input.value?input.value:'0',10);return isNaN(value)||value<1?1:value}
            function post(action,payload){var body=new URLSearchParams();body.set('action',action);body.set(root.dataset.nonceField||'nonce',root.dataset.nonce||'');Object.keys(payload||{}).forEach(function(k){var value=payload[k];if(Array.isArray(value)){value.forEach(function(item){body.append(k+'[]',item)})}else{body.set(k,value)}});return fetch(root.dataset.ajaxUrl||window.ajaxurl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()}).then(function(r){return r.json()}).then(function(p){if(!p||!p.success){var m=p&&p.data&&(p.data.message||p.data.error)?(p.data.message||p.data.error):'AJAX request failed.';throw new Error(m)}return p.data||{}})}
            function mediaHtml(row){var media=row.media||[];if(!media.length)return '<span class="hpc-cleanup-muted">No associated media</span>';return '<div class="hpc-media-list">'+media.map(function(item){return '<div class="hpc-media-item hpc-media-status-pending" data-media-id="'+esc(item.id)+'">'+statusBadge('pending','Pending')+'<div><div class="hpc-media-title">#'+esc(item.id)+' '+esc(item.title)+'</div><div class="hpc-media-meta">'+esc(item.source)+'</div></div></div>'}).join('')+'</div>'}
            function rowHtml(row){var edit=row.edit_url?'<a class="hpc-button secondary hpc-external" href="'+esc(row.edit_url)+'" target="_blank" rel="noopener noreferrer">Edit</a>':'<span class="hpc-cleanup-muted">No edit link</span>';return '<tr class="hpc-cleanup-row" data-post-id="'+esc(row.id)+'" data-cleanup-status="pending"><td><input type="checkbox" data-article-select value="'+esc(row.id)+'"></td><td><div class="hpc-cleanup-title">'+esc(row.title)+'</div><div class="hpc-cleanup-slug">'+esc(row.slug)+'</div></td><td data-article-status-cell>'+statusBadge('pending','Pending')+'</td><td>'+esc(row.status)+'</td><td>'+esc(row.published_label)+'</td><td>'+esc(row.author)+'</td><td data-article-media-cell>'+mediaHtml(row)+'</td><td>'+edit+'</td><td><button type="button" class="hpc-button danger" data-article-delete data-post-id="'+esc(row.id)+'">Delete Post</button></td></tr>'}
            function renderRows(rows){rows=rows||[];setCount(rows.length);var all=root.querySelector('[data-article-select-all]');if(all)all.checked=false;if(!tbody)return;if(!rows.length){tbody.innerHTML='<tr><td colspan="9" class="hpc-cleanup-muted">'+esc(root.dataset.emptyMessage||'No matching articles found.')+'</td></tr>';return}tbody.innerHTML=rows.map(rowHtml).join('')}
            function rowFor(postId){return root.querySelector('.hpc-cleanup-row[data-post-id="'+attr(postId)+'"]')}
            function setRowStatus(postId,status,label){status=safeStatus(status);var row=rowFor(postId);if(!row)return;row.dataset.cleanupStatus=status;row.classList.remove('is-working','is-deleted','is-failed');if(status==='deleting')row.classList.add('is-working');if(status==='deleted')row.classList.add('is-deleted');if(status==='failed')row.classList.add('is-failed');var cell=row.querySelector('[data-article-status-cell]');if(cell)cell.innerHTML=statusBadge(status,label)}
            function setRowMediaState(row,status,label){status=safeStatus(status);if(!row)return;row.querySelectorAll('.hpc-media-item').forEach(function(item){item.className=item.className.replace(/\bhpc-media-status-[a-z_-]+\b/g,'').trim()+' hpc-media-status-'+status;var badge=item.querySelector('.hpc-status-badge');if(badge)badge.outerHTML=statusBadge(status,label)})}
            function applyMediaStatuses(postId,mediaStatuses){var row=rowFor(postId);if(!row)return;(mediaStatuses||[]).forEach(function(item){var media=row.querySelector('.hpc-media-item[data-media-id="'+attr(item.id)+'"]');if(!media)return;var status=safeStatus(item.status||'skipped'),label=status==='deleted'?'Deleted':(status==='failed'?'Failed':'Kept');media.className=media.className.replace(/\bhpc-media-status-[a-z_-]+\b/g,'').trim()+' hpc-media-status-'+status;var badge=media.querySelector('.hpc-status-badge');if(badge)badge.outerHTML=statusBadge(status,label);var meta=media.querySelector('.hpc-media-meta');if(meta&&item.message)meta.textContent=text(item.source||'')+' — '+text(item.message)})}
            function disableDeletedRow(row){if(!row)return;var box=row.querySelector('[data-article-select]');if(box){box.checked=false;box.disabled=true}var button=row.querySelector('[data-article-delete]');if(button)button.disabled=true}
            function applyPostResult(result){result=result||{};var id=result.id||0,status=text(result.status||'deleted').toLowerCase();setRowStatus(id,status,status==='failed'?'Failed':'Deleted');applyMediaStatuses(id,result.media_status||[]);var row=rowFor(id);if(status==='deleted')disableDeletedRow(row)}
            function markPreservedRows(keep){var rows=Array.from(root.querySelectorAll('.hpc-cleanup-row'));rows.forEach(function(row,index){if(index<keep&&row.dataset.cleanupStatus==='pending'){setRowStatus(row.dataset.postId,'preserved','Kept');setRowMediaState(row,'preserved','Kept')}})}
            function markBatchRowsDeleting(mode,keep,size,deleteMedia){var rows=Array.from(root.querySelectorAll('.hpc-cleanup-row')).filter(function(row){return row.dataset.cleanupStatus==='pending'});if(mode==='all_except_keep_recent')markPreservedRows(keep);rows=Array.from(root.querySelectorAll('.hpc-cleanup-row')).filter(function(row){return row.dataset.cleanupStatus==='pending'});rows.slice(0,size).forEach(function(row){setRowStatus(row.dataset.postId,'deleting','Deleting');setRowMediaState(row,deleteMedia?'deleting':'skipped',deleteMedia?'Deleting':'Kept')})}
            function applyPreservedIds(ids){(ids||[]).forEach(function(id){var row=rowFor(id);if(row&&row.dataset.cleanupStatus==='pending'){setRowStatus(id,'preserved','Kept');setRowMediaState(row,'preserved','Kept')}})}
            function scan(button){dynStart(button,'Scanning...');addLog({level:'info',message:'Scanning article cleanup candidates.'});return post(root.dataset.scanAction||'',criteria()).then(function(data){addLogs(data.log);renderRows(data.rows||[]);dynOk(button,'Scanned');return data}).catch(function(error){addLog({level:'error',message:error.message||'Scan failed.'});dynFail(button,'Failed');throw error})}
            function deleteOne(postId,button){var row=rowFor(postId),media=deleteMediaEnabled();if(row){setRowStatus(postId,'deleting','Deleting');setRowMediaState(row,media?'deleting':'skipped',media?'Deleting':'Kept')}dynStart(button,'Deleting...');addLog({level:'warning',message:'Deleting post #'+postId+'.'});return post(root.dataset.deleteAction||'',{post_id:postId,delete_media:media?'1':'0'}).then(function(data){addLogs(data.log);dynOk(button,'Deleted');applyPostResult(data);return data}).catch(function(error){setRowStatus(postId,'failed','Failed');addLog({level:'error',message:error.message||'Delete failed.'});dynFail(button,'Failed');throw error})}
            function ensureRows(button){if(root.querySelector('.hpc-cleanup-row'))return Promise.resolve();return scan(root.querySelector('[data-article-scan]')||button)}
            function deleteBatch(mode,button){var c=criteria(),keep=mode==='all_except_keep_recent'?batchKeepRecent():0,media=batchDeleteMediaEnabled(mode),label=mode==='all_except_keep_recent'?'Delete matching posts except the latest '+keep:'Delete all matching posts',promptText=label+' using the current advanced filters. Preview Limit will be ignored.';c.keep_recent=String(keep);if(media)promptText+=' Associated featured/inline media will also be deleted.';promptText+='\n\nType DELETE to confirm.';if(window.prompt(promptText)!=='DELETE'){addLog({level:'warning',message:'Batch delete cancelled before AJAX request.'});return}dynStart(button,'Preparing...');ensureRows(button).then(function(){dynStart(button,'Deleting...');var totals={deleted:0,failed:0,media:0,batches:0},exclude=[];function step(){totals.batches++;var payload=Object.assign({},c,{batch_mode:mode,batch_size:batchSize(),delete_media:media?'1':'0',exclude_ids:exclude}),size=parseInt(payload.batch_size,10)||1;markBatchRowsDeleting(mode,keep,size,media);addLog({level:'warning',message:'Deleting batch '+totals.batches+'.'});return post(root.dataset.batchDeleteAction||'',payload).then(function(data){addLogs(data.log);applyPreservedIds(data.preserved_ids||[]);(data.post_results||[]).forEach(applyPostResult);exclude=data.exclude_ids||exclude;totals.deleted+=parseInt(data.deleted_count||0,10)||0;totals.failed+=parseInt(data.failed_count||0,10)||0;totals.media+=parseInt(data.deleted_media_count||0,10)||0;if(data.has_more){dynStart(button,'Deleted '+totals.deleted+'...');return step()}addLog({level:'success',message:'Batch delete finished: '+totals.deleted+' post(s), '+totals.media+' media item(s), '+totals.failed+' failed.'});dynOk(button,'Deleted');return data}).catch(function(error){root.querySelectorAll('.hpc-cleanup-row[data-cleanup-status="deleting"]').forEach(function(row){setRowStatus(row.dataset.postId,'failed','Failed')});addLog({level:'error',message:error.message||'Batch delete failed.'});dynFail(button,'Failed');throw error})}return step()}).catch(function(){})}
            root.addEventListener('click',function(event){var scanButton=event.target.closest('[data-article-scan]');if(scanButton){event.preventDefault();scan(scanButton).catch(function(){});return}var clear=event.target.closest('[data-article-clear-log]');if(clear){event.preventDefault();event.stopPropagation();if(logBody)logBody.innerHTML='';addLog({level:'info',message:'Article cleanup activity log cleared.'});return}var selectAll=event.target.closest('[data-article-select-all]');if(selectAll){root.querySelectorAll('[data-article-select]:not(:disabled)').forEach(function(box){box.checked=selectAll.checked});return}var batch=event.target.closest('[data-article-delete-batch]');if(batch){event.preventDefault();deleteBatch(batch.getAttribute('data-batch-mode')||'all_matching',batch);return}var rowDelete=event.target.closest('[data-article-delete]');if(rowDelete){event.preventDefault();var id=rowDelete.getAttribute('data-post-id')||'';var msg='Permanently delete this post?';if(deleteMediaEnabled())msg+=' Associated featured/inline media will also be deleted.';if(!window.confirm(msg))return;deleteOne(id,rowDelete).catch(function(){});return}var bulk=event.target.closest('[data-article-delete-selected]');if(bulk){event.preventDefault();var ids=Array.from(root.querySelectorAll('[data-article-select]:checked:not(:disabled)')).map(function(box){return box.value});if(!ids.length){addLog({level:'warning',message:'No articles selected for deletion.'});dynFail(bulk,'Select rows');return}var msg='Permanently delete '+ids.length+' selected post(s)?';if(deleteMediaEnabled())msg+=' Associated featured/inline media will also be deleted.';if(!window.confirm(msg))return;dynStart(bulk,'Deleting...');var chain=Promise.resolve();ids.forEach(function(id){chain=chain.then(function(){var b=root.querySelector('.hpc-cleanup-row[data-post-id="'+id+'"] [data-article-delete]')||bulk;return deleteOne(id,b).catch(function(){})})});chain.then(function(){dynOk(bulk,'Deleted')})}});
            if(form){form.addEventListener('submit',function(event){event.preventDefault();scan(root.querySelector('[data-article-scan]')).catch(function(){})})}
            if(root.dataset.autoScan==='1'){addLog({level:'info',message:'Article cleanup UI loaded. Auto-running article scan.'});scan(root.querySelector('[data-article-scan]')).catch(function(){})}else{addLog({level:'info',message:'Article cleanup UI loaded. Waiting for manual scan.'})}
        })();
        </script>
        <?php
    }
}
