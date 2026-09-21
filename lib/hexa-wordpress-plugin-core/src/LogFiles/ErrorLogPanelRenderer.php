<?php

namespace Hexa\PluginCore\LogFiles;

use Hexa\PluginCore\WpAdminComponents\CoreUi;

final class ErrorLogPanelRenderer {
    private ErrorLogReader $reader;

    public function __construct( ?ErrorLogReader $reader = null ) {
        $this->reader = $reader ?: new ErrorLogReader();
    }

    /**
     * @param ErrorLogSource[] $sources
     */
    public function render( array $sources, array $args = [] ): void {
        CoreUi::render_assets();

        $id          = isset( $args['id'] ) ? sanitize_key( (string) $args['id'] ) : 'hexa-error-log-panel';
        $title       = isset( $args['title'] ) ? (string) $args['title'] : 'Error Logs';
        $fatal_limit = isset( $args['fatal_limit'] ) ? (int) $args['fatal_limit'] : 100;
        $tail_lines  = isset( $args['tail_lines'] ) ? (int) $args['tail_lines'] : 150;
        ?>
        <section id="<?php echo esc_attr( $id ); ?>" class="hpc-ui hpc-log-panel">
            <style>
                #<?php echo esc_attr( $id ); ?> .hpc-log-tabs{border-bottom:1px solid #d9e0ea;display:flex;flex-wrap:wrap;gap:8px;margin:12px 0;padding-bottom:12px}
                #<?php echo esc_attr( $id ); ?> .hpc-log-tab{background:#fff;border:1px solid #cfd8e3;border-radius:6px;color:#253650;cursor:pointer;font-weight:700;padding:8px 11px}
                #<?php echo esc_attr( $id ); ?> .hpc-log-tab.active{background:#3157d5;border-color:#3157d5;color:#fff}
                #<?php echo esc_attr( $id ); ?> .hpc-log-tools{align-items:center;display:flex;gap:10px;margin:0 0 12px}
                #<?php echo esc_attr( $id ); ?> .hpc-log-search{border:1px solid #9aa7b8;border-radius:6px;max-width:440px;padding:8px 10px;width:100%}
                #<?php echo esc_attr( $id ); ?> .hpc-log-viewer{background:#0f1720;border:1px solid #263241;border-radius:8px;color:#dbe7f3;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;font-size:12px;line-height:1.55;max-height:430px;overflow:auto;padding:14px;white-space:pre-wrap}
                #<?php echo esc_attr( $id ); ?> .hpc-log-pane{display:none}
                #<?php echo esc_attr( $id ); ?> .hpc-log-pane.active{display:block}
                #<?php echo esc_attr( $id ); ?> .hpc-log-line.level-fatal{color:#ff9cac;font-weight:800}
                #<?php echo esc_attr( $id ); ?> .hpc-log-line.level-warning{color:#ffd37a}
                #<?php echo esc_attr( $id ); ?> .hpc-log-line.level-notice{color:#9bd0ff}
                #<?php echo esc_attr( $id ); ?> mark{background:#ffe08a;border-radius:2px;color:#111827;padding:1px 2px}
                #<?php echo esc_attr( $id ); ?> .hpc-log-source-badge{background:#24364c;border-radius:4px;color:#dbe7f3;display:inline-block;font-size:11px;font-weight:800;margin-right:8px;padding:2px 6px}
            </style>
            <div class="hpc-shell">
                <div class="hpc-actions" style="justify-content:space-between;margin-bottom:12px;">
                    <div>
                        <h2 style="font-size:20px;margin:0 0 6px;"><?php echo esc_html( $title ); ?> <?php echo CoreUi::tooltip( 'Reusable Hexa core error-log viewer. HWS currently provides delete actions and refresh behavior.' ); ?></h2>
                        <div class="hpc-actions">
                            <?php foreach ( $sources as $source ) : ?>
                                <?php if ( $source instanceof ErrorLogSource ) : ?>
                                    <?php echo CoreUi::pill( $source->label . ': ' . $source->size_label(), $source->exists() ? ( $source->size() > 0 ? 'warning' : 'success' ) : '' ); ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="hpc-actions">
                        <?php foreach ( $sources as $source ) : ?>
                            <?php if ( $source instanceof ErrorLogSource && $source->deletable && $source->delete_button_id ) : ?>
                                <button type="button" class="hpc-button danger" id="<?php echo esc_attr( $source->delete_button_id ); ?>">Delete <?php echo esc_html( $source->label ); ?></button>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="hpc-log-tabs">
                    <button type="button" class="hpc-log-tab active" data-log-pane="fatal">Fatal and syntax</button>
                    <?php foreach ( $sources as $source ) : ?>
                        <?php if ( $source instanceof ErrorLogSource ) : ?>
                            <button type="button" class="hpc-log-tab" data-log-pane="<?php echo esc_attr( $source->id ); ?>"><?php echo esc_html( $source->label ); ?></button>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <div class="hpc-log-tools">
                    <input type="search" class="hpc-log-search" placeholder="Search visible log...">
                    <span class="hpc-pill" data-role="search-count">Search inactive</span>
                </div>

                <div class="hpc-log-pane active" data-pane="fatal">
                    <div class="hpc-log-viewer">
                        <?php
                        $fatal_entries = $this->reader->fatal_syntax_entries( $sources, $fatal_limit );
                        if ( [] === $fatal_entries ) {
                            echo '<span class="hpc-log-line level-info">No fatal or syntax errors found.</span>';
                        } else {
                            foreach ( $fatal_entries as $entry ) {
                                echo '<span class="hpc-log-source-badge">' . esc_html( $entry['source'] ) . '</span><span class="hpc-log-line level-fatal">' . esc_html( $entry['line'] ) . '</span>' . "\n";
                            }
                        }
                        ?>
                    </div>
                </div>

                <?php foreach ( $sources as $source ) : ?>
                    <?php if ( $source instanceof ErrorLogSource ) : ?>
                        <div class="hpc-log-pane" data-pane="<?php echo esc_attr( $source->id ); ?>">
                            <div class="hpc-log-viewer"><?php echo $this->reader->highlighted_html( $this->reader->tail( $source, $tail_lines ) ); ?></div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <script>
            (function(){
                var root = document.getElementById('<?php echo esc_js( $id ); ?>');
                if (!root) return;
                var tabs = root.querySelectorAll('.hpc-log-tab');
                var panes = root.querySelectorAll('.hpc-log-pane');
                var search = root.querySelector('.hpc-log-search');
                var count = root.querySelector('[data-role="search-count"]');
                var originals = new Map();
                root.querySelectorAll('.hpc-log-viewer').forEach(function(viewer){ originals.set(viewer, viewer.innerHTML); });
                function activeViewer(){ var pane = root.querySelector('.hpc-log-pane.active'); return pane ? pane.querySelector('.hpc-log-viewer') : null; }
                function runSearch(){
                    var viewer = activeViewer();
                    if (!viewer) return;
                    viewer.innerHTML = originals.get(viewer) || viewer.innerHTML;
                    var q = (search.value || '').trim();
                    if (q.length < 2) { count.textContent = 'Search inactive'; return; }
                    var escaped = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                    var re = new RegExp('(' + escaped + ')', 'gi');
                    var matches = (viewer.innerHTML.match(re) || []).length;
                    viewer.innerHTML = viewer.innerHTML.replace(re, '<mark>$1</mark>');
                    count.textContent = matches ? matches + ' matches' : 'No matches';
                }
                tabs.forEach(function(tab){
                    tab.addEventListener('click', function(){
                        tabs.forEach(function(t){ t.classList.remove('active'); });
                        panes.forEach(function(p){ p.classList.remove('active'); });
                        tab.classList.add('active');
                        var pane = root.querySelector('[data-pane="' + tab.getAttribute('data-log-pane') + '"]');
                        if (pane) pane.classList.add('active');
                        runSearch();
                    });
                });
                if (search) search.addEventListener('input', runSearch);
            })();
            </script>
        </section>
        <?php
    }
}
