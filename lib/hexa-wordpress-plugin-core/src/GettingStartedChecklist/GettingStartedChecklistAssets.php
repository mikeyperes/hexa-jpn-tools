<?php

namespace Hexa\PluginCore\GettingStartedChecklist;
final class GettingStartedChecklistAssets {
    public static function render( string $root_id ): string {
        ob_start();
        ?>
        <style>
            #<?php echo esc_attr( $root_id ); ?>{max-width:100%;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-description{color:#3f4d63;font-size:13px;line-height:1.55;margin:0 0 14px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-actions{align-items:center;display:flex;flex-wrap:wrap;gap:10px;margin:0 0 16px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-template-picker{align-items:flex-end;background:#f8fbff;border:1px solid #dce7f4;border-radius:8px;display:flex;flex-wrap:wrap;gap:10px;margin:0 0 14px;padding:12px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-template-picker label{display:grid;gap:5px;min-width:min(280px,100%)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-template-picker label span{color:#243044;font-size:12px;font-weight:900;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-template-picker select{background:#fff;border:1px solid #cbd6e2;border-radius:6px;box-shadow:none;color:#111827;font-size:13px;min-height:34px;padding:5px 30px 5px 10px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-template-status{background:#eaf8ef;border:1px solid #ccefd7;border-radius:999px;color:var(--hpc-green);font-size:11px;font-weight:900;line-height:1;padding:7px 9px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-template-status[data-state="selected"]{background:#eef2ff;border-color:#c7d4ff;color:var(--hpc-blue)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-template-picker small{color:var(--hpc-muted);display:block;flex-basis:100%;font-size:11px;line-height:1.35}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-list{background:#fff;border:1px solid var(--hpc-line);border-radius:8px;display:block;margin:0 0 16px;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-step{background:#fff;border:0;border-top:1px solid var(--hpc-line);border-radius:0;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-step:first-child{border-top:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-step summary{cursor:pointer;list-style:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-step summary::-webkit-details-marker{display:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row{align-items:flex-start;display:grid;gap:12px;grid-template-columns:34px minmax(0,1fr) auto;padding:14px 16px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-step-row{background:#fff}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-step-parent[open]>.hpc-gsc-step-row{background:#fbfcfe}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-subtasks{border-top:1px solid #edf1f6;display:grid;gap:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-subtask-row{border-top:1px solid #edf1f6;margin-left:34px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-subtask-row:first-child{border-top:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row-action{align-items:center;display:flex;gap:10px;justify-content:flex-end}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row-action,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-actions{position:relative}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row-action[data-disabled-reason]:hover::after,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row-action[data-disabled-reason]:focus-within::after,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-actions[data-disabled-reason]:hover::after,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-actions[data-disabled-reason]:focus-within::after{background:#111827;border:1px solid #263241;border-radius:6px;box-shadow:0 10px 24px rgba(15,23,42,.24);color:#f8fafc;content:attr(data-disabled-reason);font-size:12px;font-weight:700;line-height:1.35;max-width:min(360px,calc(100vw - 40px));padding:8px 10px;pointer-events:none;position:absolute;right:0;text-align:left;top:calc(100% + 8px);white-space:normal;width:max-content;z-index:50}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row-action[data-disabled-reason]:hover::before,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row-action[data-disabled-reason]:focus-within::before,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-actions[data-disabled-reason]:hover::before,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-actions[data-disabled-reason]:focus-within::before{border:7px solid transparent;border-bottom-color:#111827;content:"";pointer-events:none;position:absolute;right:20px;top:calc(100% - 5px);z-index:51}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-section-toggle{align-items:center;background:#eef2ff;border:1px solid #dbe4ff;border-radius:999px;color:var(--hpc-blue);display:inline-flex;height:28px;justify-content:center;transition:background .18s,border-color .18s,color .18s;width:28px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-section-toggle svg{display:block;fill:currentColor;height:12px;transform:rotate(180deg);transition:transform .18s;width:12px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-step:not([open]) .hpc-gsc-section-toggle svg{transform:rotate(0deg)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-step summary:hover .hpc-gsc-section-toggle{background:#e4ebff;border-color:#c7d4ff}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-step summary:focus-visible{box-shadow:inset 0 0 0 2px var(--hpc-blue);outline:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-main{min-width:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-title-line{align-items:center;display:flex;flex-wrap:wrap;gap:8px;margin:0 0 5px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-title-line strong{font-size:14px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-main p{color:var(--hpc-muted);font-size:12px;line-height:1.45;margin:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report{display:grid;gap:8px;margin-top:10px;max-width:100%;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report[hidden]{display:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-card{background:#f8fafc;border:1px solid #dce5ef;border-radius:7px;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-head{border-bottom:1px solid #dce5ef;padding:9px 11px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-head strong{display:block;font-size:12px;margin:0 0 3px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-head span{color:var(--hpc-muted);display:block;font-size:11px;line-height:1.35}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-explain{background:#fff;border-bottom:1px solid #dce5ef;display:grid;gap:8px;padding:11px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-doc{color:#334155;font-size:12px;line-height:1.45;margin:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-summary-list{display:grid;gap:7px;margin:0;padding:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-summary-list li{background:#f8fafc;border:1px solid #e4ebf3;border-radius:6px;color:#243044;display:grid;font-size:12px;gap:3px;line-height:1.4;list-style:none;margin:0;padding:8px 9px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-summary-list strong{color:#111827;font-size:11px;font-weight:900;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-preview-grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));padding:11px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-preview-card{background:#fff;border:1px solid #dce5ef;border-radius:8px;color:#243044;display:grid;gap:8px;padding:10px;text-decoration:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-preview-card:hover{border-color:var(--hpc-blue);box-shadow:0 6px 16px rgba(30,64,175,.10)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-preview-frame{align-items:center;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:7px;display:flex;height:132px;justify-content:center;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-preview-frame img{display:block;height:auto;image-rendering:auto;max-height:112px;max-width:112px;object-fit:contain;width:auto}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-preview-label{color:#1f2937;font-size:12px;font-weight:900;line-height:1.25}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-preview-meta,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-preview-url{color:var(--hpc-muted);font-size:11px;line-height:1.35;overflow-wrap:anywhere}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-scroll{max-width:100%;overflow:auto}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-table{border-collapse:collapse;font-size:11px;min-width:100%;table-layout:auto;width:100%}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-table th,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-table td{border-bottom:1px solid #e7eef6;padding:7px 9px;text-align:left;vertical-align:top}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-table th{background:#eef4fb;color:#334155;font-size:10px;font-weight:900;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-table td{color:#243044;overflow-wrap:anywhere;word-break:break-word}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-table tr:last-child td{border-bottom:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-table a{color:var(--hpc-blue);font-weight:800;text-decoration:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-inputs{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));margin-top:10px;max-width:760px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-input-field{display:grid;gap:5px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-input-field span{align-items:center;color:#243044;display:flex;font-size:12px;font-weight:800;gap:7px;line-height:1.2}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-input-field em{background:#fff4dd;border:1px solid #ffdca8;border-radius:999px;color:#8a5200;font-size:10px;font-style:normal;font-weight:900;line-height:1;padding:4px 7px;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-input-field input{background:#fff;border:1px solid #cbd6e2;border-radius:6px;box-shadow:none;color:#111827;font-size:13px;line-height:1.4;min-height:34px;padding:6px 10px;width:100%}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-input-field input:focus{border-color:var(--hpc-blue);box-shadow:0 0 0 1px var(--hpc-blue);outline:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-input-field input[aria-invalid="true"]{border-color:var(--hpc-red);box-shadow:0 0 0 1px var(--hpc-red)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-input-field small{color:var(--hpc-muted);font-size:11px;line-height:1.35}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-input-field .hpc-gsc-input-error{color:var(--hpc-red);font-weight:700;min-height:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-state{background:#eef2f7;border:1px solid #d7e0ea;border-radius:999px;color:#475569;font-size:11px;font-weight:800;line-height:1;padding:5px 8px;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-type{background:#fff;border:1px solid #d8e1ec;border-radius:5px;color:#536173;font-size:11px;font-weight:800;line-height:1;padding:5px 7px;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-safety{border:1px solid;border-radius:999px;font-size:10px;font-weight:900;line-height:1;padding:5px 7px;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-safety.destructive{background:#fff0f2;border-color:#ffd0d8;color:var(--hpc-red)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-safety.individual{background:#fff8e6;border-color:#f3d59a;color:#805000}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-summary{color:var(--hpc-muted);font-size:12px;font-weight:800}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="running"] .hpc-gsc-state{background:#eef2ff;border-color:#c7d4ff;color:var(--hpc-blue)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="success"] .hpc-gsc-state{background:#eaf8ef;border-color:#ccefd7;color:var(--hpc-green)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="failed"] .hpc-gsc-state{background:#fff0f2;border-color:#ffd0d8;color:var(--hpc-red)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-status-icon{align-items:center;background:#eef2f7;border:1px solid #d7e0ea;border-radius:999px;color:#64748b;display:inline-flex;height:30px;justify-content:center;margin-top:1px;width:30px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-status-icon svg{display:block;fill:currentColor;height:14px;width:14px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-icon-check,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-icon-x,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-icon-spinner{display:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-icon-pending{background:currentColor;border-radius:999px;display:block;height:8px;width:8px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="running"] .hpc-gsc-status-icon{background:#eef2ff;border-color:#c7d4ff;color:var(--hpc-blue)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="success"] .hpc-gsc-status-icon{background:#eaf8ef;border-color:#ccefd7;color:var(--hpc-green)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="failed"] .hpc-gsc-status-icon{background:#fff0f2;border-color:#ffd0d8;color:var(--hpc-red)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="running"] .hpc-gsc-icon-spinner{animation:hpc-gsc-spin .72s linear infinite;border:3px solid currentColor;border-right-color:transparent;border-radius:999px;display:block;height:17px;width:17px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="success"] .hpc-gsc-icon-check,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="failed"] .hpc-gsc-icon-x{display:block}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="running"] .hpc-gsc-icon-pending,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="success"] .hpc-gsc-icon-pending,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="failed"] .hpc-gsc-icon-pending{display:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log{background:#0f1720;border:1px solid #263241;border-radius:8px;color:#dbe7f3;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log summary{cursor:pointer;list-style:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log summary::-webkit-details-marker{display:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-head{align-items:center;background:#111c2a;display:flex;gap:12px;justify-content:space-between;padding:14px 16px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log[open] .hpc-gsc-log-head{border-bottom:1px solid #263241}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-head h3{color:#f8fafc;font-size:15px;margin:0 0 4px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-head span{color:#9fb1c6;font-size:12px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-controls{align-items:center;display:inline-flex;gap:10px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-chevron{align-items:center;background:#1f2f44;border:1px solid #34465d;border-radius:999px;color:#cbd5e1;display:inline-flex;height:28px;justify-content:center;width:28px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-chevron svg{fill:currentColor;height:12px;transform:rotate(0deg);transition:transform .18s;width:12px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log[open] .hpc-gsc-log-chevron svg{transform:rotate(180deg)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-body{max-height:360px;overflow:auto}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-row{border-top:1px solid #1f2f44;display:grid;gap:10px;grid-template-columns:minmax(70px,92px) minmax(58px,82px) minmax(0,1fr);padding:12px 16px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-row:first-child{border-top:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-time{color:#8ca1b8;font-size:12px;white-space:nowrap}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-level{border-radius:5px;font-size:11px;font-weight:900;letter-spacing:.04em;padding:3px 7px;text-align:center;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-level.info{background:#16324f;color:#9bd0ff}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-level.success{background:#14391f;color:#9cf0b0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-level.warning{background:#493813;color:#ffd37a}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-level.error{background:#4c1720;color:#ff9cac}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-message{font-size:13px;font-weight:650;margin-bottom:4px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-context{color:#9fb1c6;font-size:12px;overflow-wrap:anywhere;white-space:pre-wrap;word-break:break-word}
            @keyframes hpc-gsc-spin{to{transform:rotate(360deg)}}
            @media(max-width:760px){#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row{grid-template-columns:34px minmax(0,1fr)}#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row-action{grid-column:2;justify-content:flex-start}#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-subtask-row{margin-left:0}#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-row{grid-template-columns:1fr}}
        </style>
        <script>
        (function(){
            var root = document.getElementById('<?php echo esc_js( $root_id ); ?>');
            if (!root || root.dataset.gscReady === '1') return;
            root.dataset.gscReady = '1';
            var logBody = null;
            function text(value){ return value === null || value === undefined ? '' : String(value); }
            function esc(value){ var div = document.createElement('div'); div.textContent = text(value); return div.innerHTML; }
            function css(value){ if (window.CSS && CSS.escape) return CSS.escape(text(value)); return text(value).replace(/[^a-zA-Z0-9_-]/g, '\\$&'); }
            function now(){ var date = new Date(); return date.toTimeString().slice(0,8); }
            function getLogBody(){
                if (!logBody || !root.contains(logBody)) {
                    logBody = root.querySelector('[data-gsc-log-body]');
                }
                return logBody;
            }
            function actionContainer(button){ return button ? (button.closest('.hpc-gsc-row-action') || button.closest('.hpc-gsc-actions')) : null; }
            function setDisabledReason(button, reason){
                if (!button) return;
                reason = text(reason).trim();
                var container = actionContainer(button);
                if (reason) {
                    button.setAttribute('title', reason);
                    button.setAttribute('aria-disabled', 'true');
                    button.dataset.disabledReason = reason;
                    if (container) {
                        container.dataset.disabledReason = reason;
                        container.setAttribute('title', reason);
                    }
                } else {
                    button.removeAttribute('title');
                    button.removeAttribute('aria-disabled');
                    delete button.dataset.disabledReason;
                    if (container) {
                        delete container.dataset.disabledReason;
                        container.removeAttribute('title');
                    }
                }
            }
            function dynamicStart(button, label){ setDisabledReason(button, ''); if (window.HexaWpCoreDynamicButton) window.HexaWpCoreDynamicButton.start(button, label); else if (button) button.disabled = true; }
            function dynamicSuccess(button, label){ setDisabledReason(button, ''); if (window.HexaWpCoreDynamicButton) window.HexaWpCoreDynamicButton.success(button, label || 'Done'); else if (button) button.disabled = false; }
            function dynamicError(button, label){ setDisabledReason(button, ''); if (window.HexaWpCoreDynamicButton) window.HexaWpCoreDynamicButton.error(button, label || 'Failed'); else if (button) button.disabled = false; }
            function dynamicReset(button){ setDisabledReason(button, ''); if (window.HexaWpCoreDynamicButton) window.HexaWpCoreDynamicButton.reset(button); else if (button) button.disabled = false; }
            function logRow(entry){
                entry = entry || {};
                var context = entry.context && Object.keys(entry.context).length ? JSON.stringify(entry.context, null, 2) : '';
                return '<div class="hpc-gsc-log-row"><div class="hpc-gsc-log-time">' + esc(entry.time || now()) + '</div><div><span class="hpc-gsc-log-level ' + esc((entry.level || 'info').toLowerCase()) + '">' + esc(entry.level || 'info') + '</span></div><div><div class="hpc-gsc-log-message">' + esc(entry.message || '') + '</div>' + (context ? '<div class="hpc-gsc-log-context">' + esc(context) + '</div>' : '') + '</div></div>';
            }
            function addLog(entry){
                var target = getLogBody();
                if (!target) return;
                target.insertAdjacentHTML('beforeend', logRow(entry));
                target.scrollTop = target.scrollHeight;
            }
            function addLogs(logs){ (logs || []).forEach(addLog); }
            function clearLog(){
                var target = getLogBody();
                if (!target) return;
                target.innerHTML = logRow({time:'Ready', level:'info', message:'Checklist runner is ready.', context:{}});
            }
            function reportTarget(row){ return row ? row.querySelector('[data-gsc-report]') : null; }
            function clearReport(row){ var target = reportTarget(row); if (!target) return; target.hidden = true; target.innerHTML = ''; }
            function normalizeReports(payload){
                payload = payload || {};
                var reports = [];
                if (Array.isArray(payload.reports)) reports = payload.reports;
                else if (payload.report) reports = Array.isArray(payload.report) ? payload.report : [payload.report];
                return reports.filter(function(report){ return report && typeof report === 'object'; });
            }
            function reportColumns(report){
                if (Array.isArray(report.columns) && report.columns.length) return report.columns.map(function(col){ return typeof col === 'string' ? {key:col,label:col} : col; }).filter(function(col){ return col && col.key; });
                if (report.type === 'deleted_files') return [{key:'file',label:'File'},{key:'location',label:'Location'},{key:'size',label:'Size'}];
                if (report.type === 'wp_config_changes') return [{key:'setting',label:'Setting'},{key:'before',label:'Before Action'},{key:'after',label:'Requested Value'},{key:'actual',label:'Verified After'},{key:'meaning',label:'What Changed'},{key:'file',label:'File'}];
                if (report.type === 'deleted_posts') return [{key:'title',label:'Title'},{key:'id',label:'ID'},{key:'permalink',label:'Permalink'},{key:'media',label:'Deleted Media'}];
                var first = Array.isArray(report.items) && report.items.length ? report.items[0] : {};
                return Object.keys(first).map(function(key){ return {key:key,label:key.replace(/_/g,' ')}; });
            }
            function valueHtml(value, key){
                if (Array.isArray(value)) return value.map(function(item){ return valueHtml(item, key); }).join('<br>');
                if (value && typeof value === 'object') return '<code>' + esc(JSON.stringify(value)) + '</code>';
                value = text(value);
                if ((/url|link|permalink/i.test(key || '') || /^https?:\/\//i.test(value)) && /^https?:\/\//i.test(value)) return '<a href="' + esc(value).replace(/"/g,'&quot;') + '" target="_blank" rel="noopener noreferrer">' + esc(value) + '</a>';
                return esc(value);
            }
            function reportExplainHtml(report){
                var meta = report && report.meta && typeof report.meta === 'object' ? report.meta : {};
                var documentation = text(meta.documentation || meta.explanation || '').trim();
                var items = Array.isArray(meta.summary_items) ? meta.summary_items : [];
                items = items.filter(function(item){ return item && (text(item.label || '').trim() || text(item.value || '').trim()); });
                if (!documentation && !items.length) return '';
                var html = '<div class="hpc-gsc-report-explain">';
                if (documentation) html += '<p class="hpc-gsc-report-doc">' + esc(documentation) + '</p>';
                if (items.length) {
                    html += '<ul class="hpc-gsc-report-summary-list">' + items.map(function(item){
                        var label = text(item.label || '').trim();
                        var value = text(item.value || '').trim();
                        return '<li>' + (label ? '<strong>' + esc(label) + '</strong>' : '') + '<span>' + esc(value) + '</span></li>';
                    }).join('') + '</ul>';
                }
                html += '</div>';
                return html;
            }
            function reportPreviewHtml(report){
                var meta = report && report.meta && typeof report.meta === 'object' ? report.meta : {};
                var assets = Array.isArray(report.preview_assets) ? report.preview_assets : (Array.isArray(meta.preview_assets) ? meta.preview_assets : []);
                assets = assets.filter(function(asset){ return asset && typeof asset === 'object' && text(asset.url || asset.link || '').trim(); });
                if (!assets.length) return '';
                return '<div class="hpc-gsc-preview-grid">' + assets.map(function(asset){
                    var url = text(asset.url || asset.link || '').trim();
                    var previewUrl = text(asset.preview_url || asset.preview || url).trim();
                    var label = text(asset.label || asset.asset || 'Generated asset').trim();
                    var metaText = text(asset.meta || asset.format || '').trim();
                    var safeUrl = esc(url).replace(/"/g,'&quot;');
                    var safePreview = esc(previewUrl).replace(/"/g,'&quot;');
                    var safeLabel = esc(label).replace(/"/g,'&quot;');
                    return '<a class="hpc-gsc-preview-card" href="' + safeUrl + '" target="_blank" rel="noopener noreferrer">'
                        + '<span class="hpc-gsc-preview-frame"><img src="' + safePreview + '" alt="' + safeLabel + '" loading="lazy"></span>'
                        + '<span class="hpc-gsc-preview-label">' + esc(label) + '</span>'
                        + (metaText ? '<span class="hpc-gsc-preview-meta">' + esc(metaText) + '</span>' : '')
                        + '<span class="hpc-gsc-preview-url">' + esc(url) + '</span>'
                        + '</a>';
                }).join('') + '</div>';
            }
            function reportHtml(report){
                var columns = reportColumns(report), items = Array.isArray(report.items) ? report.items : [];
                var html = '<div class="hpc-gsc-report-card"><div class="hpc-gsc-report-head"><strong>' + esc(report.title || 'Checklist Report') + '</strong>' + (report.summary ? '<span>' + esc(report.summary) + '</span>' : '') + '</div>';
                html += reportExplainHtml(report);
                html += reportPreviewHtml(report);
                if (items.length && columns.length) {
                    html += '<div class="hpc-gsc-report-scroll"><table class="hpc-gsc-report-table"><thead><tr>' + columns.map(function(col){ return '<th>' + esc(col.label || col.key) + '</th>'; }).join('') + '</tr></thead><tbody>';
                    html += items.map(function(item){ return '<tr>' + columns.map(function(col){ return '<td>' + valueHtml(item[col.key], col.key) + '</td>'; }).join('') + '</tr>'; }).join('');
                    html += '</tbody></table></div>';
                }
                html += '</div>';
                return html;
            }
            function renderReports(row, payload){
                var target = reportTarget(row), reports = normalizeReports(payload);
                if (!target) return;
                if (!reports.length) { clearReport(row); return; }
                target.innerHTML = reports.map(reportHtml).join('');
                target.hidden = false;
            }
            function setRowState(row, state, message){
                if (!row) return;
                row.dataset.status = state || 'pending';
                var label = row.querySelector('[data-gsc-state]');
                if (label) label.textContent = message || ({pending:'Pending', running:'Running', success:'Complete', failed:'Failed'}[state] || 'Pending');
            }
            function resetRows(){
                root.querySelectorAll('[data-gsc-item]').forEach(function(row){ setRowState(row, 'pending', 'Pending'); clearReport(row); });
                root.querySelectorAll('[data-hpc-dynamic-button]').forEach(dynamicReset);
                refreshInputState();
            }
            function stateRow(item){
                if (!item) return null;
                var selector = '[data-gsc-item][data-step-id="' + css(item.step_id || '') + '"][data-subtask-id="' + css(item.subtask_id || '') + '"]';
                return root.querySelector(selector);
            }
            function applyState(state){
                state = state || {};
                Object.keys(state.items || {}).forEach(function(key){
                    var item = state.items[key] || {}, row = stateRow(item);
                    if (row) setRowState(row, item.status || 'pending', item.message || '');
                });
                var summary = state.summary || {}, target = root.querySelector('[data-gsc-summary]');
                if (target) target.textContent = summary.total ? ((summary.success || 0) + '/' + summary.total + ' complete' + ((summary.failed || 0) ? ', ' + summary.failed + ' failed' : '')) : 'Not run';
            }
            function templateSelect(){ return root.querySelector('[data-gsc-template-select]'); }
            function templateList(){ return root.querySelector('[data-gsc-list]'); }
            function templateSource(templateId){ return root.querySelector('template[data-gsc-template-source="' + css(templateId) + '"]'); }
            function templateLoadButton(){ return root.querySelector('[data-gsc-load-template]'); }
            function currentTemplateId(){ return root.dataset.currentTemplateId || root.dataset.defaultTemplateId || 'default'; }
            function updateTemplateStatus(){
                var select = templateSelect();
                var status = root.querySelector('[data-gsc-template-status]');
                var description = root.querySelector('[data-gsc-template-description]');
                var option = select ? select.options[select.selectedIndex] : null;
                var isLoaded = option && option.value === currentTemplateId();
                if (status && option) {
                    status.textContent = option.textContent + (isLoaded ? ' loaded' : ' selected — click Load Template');
                    status.dataset.state = isLoaded ? 'loaded' : 'selected';
                }
                if (description && option) description.textContent = option.dataset.description || '';
            }
            function loadTemplate(templateId){
                var source = templateSource(templateId), list = templateList();
                if (!source || !list) {
                    addLog({level:'error', message:'Checklist template could not be loaded.', context:{template_id:templateId}});
                    return false;
                }
                list.innerHTML = source.innerHTML;
                    root.dataset.currentTemplateId = templateId;
                    resetRows();
                clearLog();
                updateTemplateStatus();
                    addLog({level:'info', message:'Checklist template loaded.', context:{template_id:templateId, step_count:list.querySelectorAll('[data-gsc-step-row]').length}});
                    loadStatus();
                return true;
            }
            function rowInputs(row){
                return row ? Array.prototype.slice.call(row.querySelectorAll('[data-gsc-input]')) : [];
            }
            function rowInputMessages(row, showErrors){
                var messages = [];
                rowInputs(row).forEach(function(input){
                    var value = text(input.value).trim();
                    var type = input.dataset.inputType || input.type || 'text';
                    var label = input.dataset.inputLabel || input.dataset.inputId || 'Input';
                    var required = input.required;
                    var message = '';
                    if (required && !value) {
                        message = label + ' is required.';
                    } else if (value && type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                        message = label + ' must be a valid email address.';
                    } else if (value && type === 'number' && !Number.isFinite(Number(value))) {
                        message = label + ' must be a number.';
                    } else if (value && type === 'number' && input.min !== '' && Number(value) < Number(input.min)) {
                        message = label + ' must be at least ' + input.min + '.';
                    } else if (value && type === 'number' && input.max !== '' && Number(value) > Number(input.max)) {
                        message = label + ' must be no more than ' + input.max + '.';
                    } else if (type === 'confirmation' && input.dataset.confirmText) {
                        var expected = text(input.dataset.confirmText);
                        var actual = value;
                        var matches = input.dataset.caseSensitive === '0' ? actual.toLowerCase() === expected.toLowerCase() : actual === expected;
                        if (!matches) message = label + ' must match the required confirmation text.';
                    } else if (value && input.pattern) {
                        try {
                            var pattern = new RegExp('^(?:' + input.pattern + ')$');
                            if (!pattern.test(value)) message = label + ' is invalid.';
                        } catch (error) {}
                    }
                    input.setAttribute('aria-invalid', message ? 'true' : 'false');
                    var errorNode = input.closest('.hpc-gsc-input-field')?.querySelector('[data-gsc-input-error]');
                    if (errorNode) errorNode.textContent = showErrors ? message : '';
                    if (message) messages.push(message);
                });
                return messages;
            }
            function validateRowInputs(row, showErrors){
                return rowInputMessages(row, showErrors).length === 0;
            }
            function disabledReason(messages){
                messages = (messages || []).filter(Boolean);
                if (!messages.length) return '';
                return 'Can not run yet: ' + messages.join(' ');
            }
            function setButtonBlocked(button, messages){
                if (!button) return;
                if (button.classList.contains('is-loading')) return;
                var reason = disabledReason(messages);
                button.disabled = !!reason;
                setDisabledReason(button, reason);
            }
            function collectRowInputs(row){
                var values = {};
                rowInputs(row).forEach(function(input){
                    values[input.dataset.inputId || input.name || input.id] = text(input.value).trim();
                });
                return values;
            }
            function refreshInputState(){
                root.querySelectorAll('[data-gsc-item]').forEach(function(row){
                    var rowMessages = rowInputMessages(row, false);
                    var rowButton = row.querySelector('[data-gsc-run-item]');
                    setButtonBlocked(rowButton, rowMessages);
                });
                root.querySelectorAll('[data-gsc-step-row]').forEach(function(stepRow){
                    var stepButton = stepRow.querySelector('[data-gsc-run-step]');
                    setButtonBlocked(stepButton, rowInputMessages(stepRow, false));
                });
                var runAllButton = root.querySelector('[data-gsc-run-all]');
                setButtonBlocked(runAllButton, []);
            }
            function postItem(stepId, subtaskId, inputs){
                var body = new URLSearchParams();
                body.set('action', root.dataset.runAction || '');
                body.set(root.dataset.nonceField || 'nonce', root.dataset.nonce || '');
                body.set('step_id', stepId || '');
                body.set('subtask_id', subtaskId || '');
                body.set('template_id', currentTemplateId());
                Object.keys(inputs || {}).forEach(function(key){
                    body.set('inputs[' + key + ']', inputs[key]);
                });
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
            function postAction(action, payload){
                var body = new URLSearchParams();
                body.set('action', action || '');
                body.set(root.dataset.nonceField || 'nonce', root.dataset.nonce || '');
                Object.keys(payload || {}).forEach(function(key){
                    var value = payload[key];
                    if (Array.isArray(value)) {
                        value.forEach(function(item){ body.append(key + '[]', item); });
                    } else {
                        body.set(key, value);
                    }
                });
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
            function loadStatus(){
                if (root.dataset.persistenceEnabled !== '1' || !root.dataset.statusAction) return Promise.resolve({});
                return postAction(root.dataset.statusAction, {template_id:currentTemplateId()}).then(function(state){ applyState(state); return state; }).catch(function(error){
                    addLog({level:'warning', message:'Saved checklist state could not be loaded.', context:{error:error.message || ''}});
                    return {};
                });
            }
            function resetStatus(){
                resetRows();
                clearLog();
                if (root.dataset.persistenceEnabled !== '1' || !root.dataset.resetAction) { applyState({items:{},summary:{}}); return Promise.resolve({}); }
                return postAction(root.dataset.resetAction, {template_id:currentTemplateId()}).then(function(state){ applyState(state); return state; });
            }
            function rowMutates(row){ if (!row) return true; if (row.dataset.mutating === '1') return true; if (row.dataset.mutating === '0') return false; return row.dataset.destructive === '1' || (row.dataset.requestType || '') !== 'status_check'; }
            function runOutcome(success, row, mutatingFailure){ success = !!success; return {success:success, mutatingFailure:success ? false : (typeof mutatingFailure === 'boolean' ? mutatingFailure : rowMutates(row))}; }
            function normalizeRunOutcome(result, row){ if (result && typeof result === 'object' && Object.prototype.hasOwnProperty.call(result, 'success')) { var failureMutates = Object.prototype.hasOwnProperty.call(result, 'mutatingFailure') ? !!result.mutatingFailure : (Object.prototype.hasOwnProperty.call(result, 'mutating') ? !!result.mutating : rowMutates(row)); return runOutcome(result.success !== false, row, failureMutates); } return runOutcome(result !== false, row); }
            var extensionApi = {
                root: root,
                text: text,
                escapeHtml: esc,
                cssSelectorValue: css,
                currentTemplateId: currentTemplateId,
                collectRowInputs: collectRowInputs,
                validateRowInputs: validateRowInputs,
                setRowState: setRowState,
                reportTarget: reportTarget,
                clearReport: clearReport,
                renderReports: renderReports,
                addLog: addLog,
                addLogs: addLogs,
                refreshInputState: refreshInputState,
                postAction: postAction, rowMutates: rowMutates, normalizeRunOutcome: normalizeRunOutcome,
                runItem: runItem, runStep: runStep, runAll: runAll
            };
            root.hexaChecklistApi = extensionApi;
            root.dispatchEvent(new CustomEvent('hexa:checklist:ready', {detail:{api:extensionApi}}));
            function runExtension(row, scope){
                var detail = {
                    api: extensionApi,
                    row: row,
                    scope: scope,
                    stepId: row ? row.dataset.stepId || '' : '',
                    subtaskId: row ? row.dataset.subtaskId || '' : '',
                    handled: false,
                    promise: null
                };
                root.dispatchEvent(new CustomEvent('hexa:checklist:run', {detail:detail}));
                if (!detail.handled) return null;
                return Promise.resolve(detail.promise).then(function(result){ return normalizeRunOutcome(result, row); }).catch(function(error){
                    setRowState(row, 'failed', error && error.message ? error.message : 'Failed');
                    addLog({level:'error', message:error && error.message ? error.message : 'Checklist workflow extension failed.', context:{step_id:detail.stepId, subtask_id:detail.subtaskId}});
                    return runOutcome(false, row);
                });
            }
            function runItem(row){
                var stepId = row ? row.dataset.stepId : '';
                var subtaskId = row ? row.dataset.subtaskId : '';
                if (!validateRowInputs(row, true)) {
                    setRowState(row, 'failed', 'Needs Input');
                    addLog({level:'error', message:'Required input missing or invalid.', context:{step_id:stepId, subtask_id:subtaskId}});
                    refreshInputState();
                    return Promise.resolve(runOutcome(false, row));
                }
                var extension = runExtension(row, 'item');
                if (extension) return extension;
                setRowState(row, 'running', 'Running');
                clearReport(row);
                return postItem(stepId, subtaskId, collectRowInputs(row)).then(function(data){
                    addLogs(data.logs);
                    renderReports(row, data.data || data);
                    if (data.state) applyState(data.state);
                    if (data.success) {
                        setRowState(row, 'success', data.message || 'Complete');
                        return runOutcome(true, row);
                    }
                    setRowState(row, 'failed', data.message || 'Failed');
                    return runOutcome(false, row, typeof data.mutating === 'boolean' ? data.mutating : rowMutates(row));
                }).catch(function(error){
                    setRowState(row, 'failed', error.message || 'Failed');
                    addLog({level:'error', message:error.message || 'Checklist item failed.', context:{step_id:stepId, subtask_id:subtaskId}});
                    return runOutcome(false, row);
                });
            }
            function runParentAction(stepRow){
                var stepId = stepRow ? stepRow.dataset.stepId : '';
                if (!validateRowInputs(stepRow, true)) {
                    addLog({level:'error', message:'Required parent-step input missing or invalid.', context:{step_id:stepId}});
                    return Promise.resolve(runOutcome(false, stepRow));
                }
                return postItem(stepId, '', collectRowInputs(stepRow)).then(function(data){
                    addLogs(data.logs);
                    if (data.state) applyState(data.state);
                    if (!data.success) {
                        addLog({level:'error', message:data.message || 'Parent step action failed.', context:{step_id:stepId}});
                    }
                    return runOutcome(!!data.success, stepRow, typeof data.mutating === 'boolean' ? data.mutating : rowMutates(stepRow));
                }).catch(function(error){
                    addLog({level:'error', message:error.message || 'Parent step action failed.', context:{step_id:stepId}});
                    return runOutcome(false, stepRow);
                });
            }
            async function runStep(stepRow, batchMode){
                if (!stepRow) return runOutcome(false, stepRow);
                var stepId = stepRow.dataset.stepId || '';
                if (batchMode && stepRow.dataset.batchEnabled === '0') {
                    setRowState(stepRow, 'pending', 'Individual only');
                    addLog({level:'warning', message:'Skipped individual-only checklist step during batch run.', context:{step_id:stepId, destructive:stepRow.dataset.destructive === '1'}});
                    return runOutcome(true, stepRow);
                }
                var card = root.querySelector('[data-gsc-step-card][data-step-id="' + css(stepId) + '"]');
                var subtasks = card ? Array.prototype.slice.call(card.querySelectorAll('[data-gsc-subtask-row]')) : [];
                if (!validateRowInputs(stepRow, true)) {
                    setRowState(stepRow, 'failed', 'Needs Input');
                    addLog({level:'error', message:'Required parent-step input missing or invalid.', context:{step_id:stepId}});
                    refreshInputState();
                    return runOutcome(false, stepRow);
                }
                var extension = runExtension(stepRow, 'step');
                if (extension) return extension;
                if (!subtasks.length) {
                    return runItem(stepRow);
                }
                setRowState(stepRow, 'running', 'Running subtasks');
                addLog({level:'info', message:'Starting parent step subtasks.', context:{step_id:stepId, subtask_count:subtasks.length}});
                var allOk = true;
                if (stepRow.dataset.hasAction === '1') {
                    var parentOutcome = await runParentAction(stepRow);
                    if (!parentOutcome.success) {
                        allOk = false;
                        if (parentOutcome.mutatingFailure) {
                            setRowState(stepRow, 'failed', 'Parent action failed'); addLog({level:'error', message:'Batch stopped after a mutating parent action failed.', context:{step_id:stepId}});
                            return parentOutcome;
                        }
                    }
                }
                for (var i = 0; i < subtasks.length; i++) {
                    if (batchMode && subtasks[i].dataset.batchEnabled === '0') {
                        setRowState(subtasks[i], 'pending', 'Individual only');
                        addLog({level:'warning', message:'Skipped individual-only checklist subtask during batch run.', context:{step_id:stepId, subtask_id:subtasks[i].dataset.subtaskId || '', destructive:subtasks[i].dataset.destructive === '1'}});
                        continue;
                    }
                    var itemOutcome = await runItem(subtasks[i]);
                    if (!itemOutcome.success) {
                        allOk = false;
                        if (itemOutcome.mutatingFailure) {
                            setRowState(stepRow, 'failed', 'Mutating subtask failed'); addLog({level:'error', message:'Batch stopped after a mutating subtask failed.', context:{step_id:stepId, subtask_id:subtasks[i].dataset.subtaskId || ''}});
                            return itemOutcome;
                        }
                    }
                }
                setRowState(stepRow, allOk ? 'success' : 'failed', allOk ? 'All subtasks complete' : 'Subtask failed');
                addLog({level:allOk ? 'success' : 'error', message:allOk ? 'Parent step completed.' : 'Parent step completed with failures.', context:{step_id:stepId}});
                return runOutcome(allOk, stepRow, false);
            }
            async function runAll(button){
                resetRows();
                clearLog();
                dynamicStart(button, 'Running...');
                addLog({level:'info', message:'Starting full getting started checklist run.', context:{steps:root.querySelectorAll('[data-gsc-step-row]').length}});
                var rows = Array.prototype.slice.call(root.querySelectorAll('[data-gsc-step-row]'));
                var allOk = true, stopped = false;
                for (var i = 0; i < rows.length; i++) {
                    var stepOutcome = await runStep(rows[i], true); if (!stepOutcome.success) allOk = false;
                    if (stepOutcome.mutatingFailure) { stopped = true; addLog({level:'error', message:'Checklist batch halted; remaining items were not run after a mutating failure.', context:{step_id:rows[i].dataset.stepId || ''}}); break; }
                }
                addLog({level:allOk ? 'success' : 'error', message:allOk ? 'Checklist run finished successfully.' : (stopped ? 'Checklist run stopped after a mutating failure.' : 'Checklist status scan finished with failures.'), context:{stopped:stopped}});
                if (allOk) dynamicSuccess(button, 'Checklist Finished'); else dynamicError(button, 'Checklist Failed');
            }
            root.addEventListener('click', function(event){
                var clear = event.target.closest('[data-gsc-clear-log]');
                if (clear) {
                    event.preventDefault();
                    event.stopPropagation();
                    clearLog();
                    return;
                }
                var reset = event.target.closest('[data-gsc-reset]');
                if (reset) {
                    event.preventDefault();
                    resetStatus().catch(function(error){ addLog({level:'error', message:error.message || 'Checklist state reset failed.', context:{}}); });
                    return;
                }
                var loadTemplateButton = event.target.closest('[data-gsc-load-template]');
                if (loadTemplateButton) {
                    event.preventDefault();
                    var select = templateSelect();
                    dynamicStart(loadTemplateButton, 'Loading...');
                    if (loadTemplate(select ? select.value : root.dataset.defaultTemplateId || 'default')) {
                        dynamicSuccess(loadTemplateButton, 'Template Loaded');
                    } else {
                        dynamicError(loadTemplateButton, 'Load Failed');
                    }
                    return;
                }
                var runAllButton = event.target.closest('[data-gsc-run-all]');
                if (runAllButton) {
                    event.preventDefault();
                    runAll(runAllButton); return;
                }
                var runStepButton = event.target.closest('[data-gsc-run-step]');
                if (runStepButton) {
                    event.preventDefault();
                    event.stopPropagation();
                    dynamicStart(runStepButton, 'Running...');
                    var stepRow = root.querySelector('[data-gsc-step-row][data-step-id="' + css(runStepButton.dataset.stepId || '') + '"]');
                    if (!validateRowInputs(stepRow, true)) {
                        setRowState(stepRow, 'failed', 'Needs Input');
                        addLog({level:'error', message:'Required parent-step input missing or invalid.', context:{step_id:runStepButton.dataset.stepId || ''}});
                        refreshInputState();
                        dynamicError(runStepButton, 'Needs Input');
                        return;
                    }
                    runStep(stepRow, false).then(function(outcome){ if (outcome.success) dynamicSuccess(runStepButton, 'Done'); else dynamicError(runStepButton, 'Failed'); });
                    return;
                }
                var runItemButton = event.target.closest('[data-gsc-run-item]');
                if (runItemButton) {
                    event.preventDefault();
                    event.stopPropagation();
                    dynamicStart(runItemButton, 'Running...');
                    var selector = '[data-gsc-subtask-row][data-step-id="' + css(runItemButton.dataset.stepId || '') + '"][data-subtask-id="' + css(runItemButton.dataset.subtaskId || '') + '"]';
                    var row = root.querySelector(selector);
                    if (!validateRowInputs(row, true)) {
                        setRowState(row, 'failed', 'Needs Input');
                        addLog({level:'error', message:'Required input missing or invalid.', context:{step_id:runItemButton.dataset.stepId || '', subtask_id:runItemButton.dataset.subtaskId || ''}});
                        refreshInputState();
                        dynamicError(runItemButton, 'Needs Input');
                        return;
                    }
                    runItem(row).then(function(outcome){ if (outcome.success) dynamicSuccess(runItemButton, 'Done'); else dynamicError(runItemButton, 'Failed'); });
                }
            });
            root.addEventListener('input', function(event){
                if (!event.target.closest('[data-gsc-input]')) return;
                refreshInputState();
            });
            root.addEventListener('change', function(event){
                if (!event.target.closest('[data-gsc-template-select]')) return;
                dynamicReset(templateLoadButton());
                updateTemplateStatus();
            });
            root.addEventListener('click', function(event){
                if (event.target.closest('[data-gsc-input]')) {
                    event.stopPropagation();
                }
            }, true);
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", function(){ updateTemplateStatus(); refreshInputState(); loadStatus(); }, {once:true});
            } else {
                window.setTimeout(function(){ updateTemplateStatus(); refreshInputState(); loadStatus(); }, 0);
            }
        })();
        </script>
        <?php
        return (string) ob_get_clean();
    }
}
