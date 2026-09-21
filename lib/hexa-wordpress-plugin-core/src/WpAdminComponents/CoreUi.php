<?php

namespace Hexa\PluginCore\WpAdminComponents;

final class CoreUi {
    public static function render_assets(): void {
        static $rendered = false;

        if ( $rendered ) {
            return;
        }

        $rendered = true;
        ?>
        <style>
            .hpc-ui{--hpc-bg:#f5f7fb;--hpc-ink:#172033;--hpc-muted:#65758b;--hpc-panel:#fff;--hpc-line:#d9e0ea;--hpc-blue:#3157d5;--hpc-green:#16803c;--hpc-red:#b42336;--hpc-amber:#9a6700;--hpc-dark:#111827;--hpc-radius:8px;color:var(--hpc-ink)}
            .hpc-ui *{box-sizing:border-box}
            .hpc-shell{background:var(--hpc-bg);border:1px solid var(--hpc-line);border-radius:8px;padding:18px}
            .hpc-hero{background:linear-gradient(135deg,#101827,#1a2740);border-radius:8px;color:#f8fafc;display:grid;gap:16px;grid-template-columns:minmax(0,1fr) auto;margin:0 0 18px;padding:22px}
            .hpc-hero h2{color:#fff;font-size:26px;line-height:1.15;margin:0 0 8px}
            .hpc-hero p{color:#d7e1ef;font-size:14px;line-height:1.6;margin:0}
            .hpc-grid{display:grid;gap:14px;grid-template-columns:repeat(3,minmax(0,1fr))}
            .hpc-grid.two{grid-template-columns:repeat(2,minmax(0,1fr))}
            .hpc-stack{display:grid;gap:14px}
            .hpc-card,.hpc-subcard{background:var(--hpc-panel);border:1px solid var(--hpc-line);border-radius:8px;padding:16px}
            .hpc-card h3,.hpc-subcard h4{font-size:15px;margin:0 0 8px}
            .hpc-card p,.hpc-subcard p{color:#3f4d63;font-size:13px;line-height:1.55;margin:0 0 10px}
            .hpc-subcard{background:#fbfcfe}
            .hpc-detail-card{background:#fbfcfe;border:1px solid var(--hpc-line);border-radius:8px;margin:0 0 14px;overflow:hidden}
            .hpc-detail-card summary{align-items:center;cursor:pointer;display:flex;font-size:13px;font-weight:800;gap:10px;justify-content:space-between;list-style:none;padding:12px 14px}
            .hpc-detail-card summary::-webkit-details-marker{display:none}
            .hpc-detail-card-title{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
            .hpc-detail-card-side{align-items:center;display:inline-flex;flex:0 0 auto;gap:8px;margin-left:auto}
            .hpc-detail-card-toggle{align-items:center;background:#eef2ff;border:1px solid #dbe4ff;border-radius:999px;color:var(--hpc-blue);display:inline-flex;height:24px;justify-content:center;width:24px}
            .hpc-detail-card-toggle svg{display:block;fill:currentColor;height:10px;transform:rotate(180deg);transition:transform .18s;width:10px}
            .hpc-detail-card:not([open]) .hpc-detail-card-toggle svg{transform:rotate(0deg)}
            .hpc-detail-card-body{border-top:1px solid var(--hpc-line);padding:14px}
            .hpc-detail-card.subtle{background:transparent;border:0;border-top:1px solid #edf1f6;border-radius:0;margin:-2px 0 8px}
            .hpc-detail-card.subtle summary{color:var(--hpc-muted);font-size:12px;font-weight:700;padding:7px 2px}
            .hpc-detail-card.subtle .hpc-detail-card-body{border-top:0;color:var(--hpc-muted);padding:0 2px 8px 18px}
            .hpc-detail-card.subtle .hpc-detail-card-toggle{background:transparent;border:0;color:#8a98aa;height:18px;width:18px}
            .hpc-detail-card.subtle .hpc-detail-card-toggle svg{height:9px;width:9px}
            .hpc-detail-card.subtle .hpc-pill{font-size:11px;padding:3px 7px}
            .hpc-pill{align-items:center;background:#eef2ff;border:1px solid #dbe4ff;border-radius:999px;color:#2944ad;display:inline-flex;font-size:12px;font-weight:700;gap:6px;line-height:1;padding:7px 10px}
            .hpc-pill.success{background:#eaf8ef;border-color:#ccefd7;color:var(--hpc-green)}
            .hpc-pill.warning{background:#fff7e0;border-color:#f5df9c;color:var(--hpc-amber)}
            .hpc-pill.danger{background:#fff0f2;border-color:#ffd0d8;color:var(--hpc-red)}
            .hpc-pill.dark{background:#202b3d;border-color:#3a4a62;color:#dbe7f3}
            .hpc-actions{align-items:center;display:flex;flex-wrap:wrap;gap:10px}
            .hpc-button{background:var(--hpc-blue);border:1px solid var(--hpc-blue);border-radius:6px;color:#fff;cursor:pointer;display:inline-flex;font-weight:700;line-height:1;padding:10px 13px;text-decoration:none}
            .hpc-button.secondary{background:#fff;color:var(--hpc-blue)}
            .hpc-button.danger{background:var(--hpc-red);border-color:var(--hpc-red)}
            .hpc-core-tabs{align-items:center;border-bottom:1px solid var(--hpc-line);display:flex;flex-wrap:wrap;gap:8px;margin:0 0 16px;padding-bottom:12px}
            .hpc-core-tab{background:#fff;border:1px solid #cfd8e3;border-radius:6px;color:#253650;cursor:pointer;font-weight:800;padding:9px 12px}
            .hpc-core-tab.active{background:var(--hpc-blue);border-color:var(--hpc-blue);color:#fff}
            .hpc-core-pane{display:none}
            .hpc-core-pane.active{display:block}
            .hpc-host-tabs-shell{margin:16px 0 0;max-width:100%;min-width:0}
            .hpc-host-tabs-shell.is-sidebar{--hpc-host-sidebar-width:214px;align-items:start;display:grid;gap:16px;grid-template-columns:var(--hpc-host-sidebar-width) minmax(0,1fr)}
            .hpc-host-tabs-shell.is-sidebar.is-sidebar-collapsed{grid-template-columns:44px minmax(0,1fr)}
            .hpc-host-rail{align-self:start;background:#fff;border:1px solid var(--hpc-line);border-radius:var(--hpc-radius);max-height:none;max-width:100%;overflow:visible;padding:7px;position:static}
            .hpc-host-rail-header{border-bottom:1px solid #eef1f6;margin:0 2px 6px;min-width:0;padding:8px 9px 11px;position:relative}
            .hpc-host-rail-identity{margin:0;min-width:0;overflow-wrap:anywhere;padding:0;width:auto}
            .hpc-host-rail-plugin-name{color:var(--hpc-ink);display:block;font-size:14px;line-height:1.35;margin:0 0 4px;padding-right:38px}
            .hpc-host-rail-versions{align-items:baseline;color:var(--hpc-muted);display:flex;flex-wrap:wrap;font-size:11px;gap:0 2px;line-height:1.45;min-width:0}
            .hpc-host-rail-versions a,.hpc-host-rail-core{color:var(--hpc-blue);text-decoration:none}
            .hpc-host-rail-versions a:hover,.hpc-host-rail-core:hover{text-decoration:underline}
            .hpc-host-rail-version-separator{color:#9aa6b6}
            .hpc-host-rail-core{display:block;font-size:11px;line-height:1.45;margin-top:3px;overflow-wrap:anywhere}
            .hpc-host-tabs-shell.is-sidebar-collapsed .hpc-host-rail-identity{display:none}
            .hpc-host-rail-tools{display:flex;justify-content:flex-end;padding:0;position:absolute;right:5px;top:7px}
            .hpc-host-sidebar-toggle{align-items:center;background:#fff;border:1px solid #cfd8e3;border-radius:6px;color:#31405a;cursor:pointer;display:inline-flex;height:32px;justify-content:center;padding:0;transition:background .15s,border-color .15s,color .15s;width:32px}
            .hpc-host-sidebar-toggle:hover{background:#eef3fc;border-color:#aebbd0;color:var(--hpc-blue)}
            .hpc-host-sidebar-toggle:focus-visible{box-shadow:0 0 0 2px #fff,0 0 0 4px var(--hpc-blue);outline:0}
            .hpc-host-sidebar-toggle .dashicons{font-size:18px;height:18px;line-height:18px;width:18px}
            .hpc-host-rail-navigation{min-width:0}
            .hpc-host-rail-navigation[hidden]{display:none}
            .hpc-host-tabs-shell.is-sidebar-collapsed .hpc-host-rail{padding:5px}
            .hpc-host-tabs-shell.is-sidebar-collapsed .hpc-host-rail-header{border-bottom:0;display:flex;justify-content:center;margin:0;padding:0}
            .hpc-host-tabs-shell.is-sidebar-collapsed .hpc-host-rail-tools{justify-content:center;padding:0;position:static}
            .hpc-host-rail-group{padding:4px 2px 8px}
            .hpc-host-rail-group+.hpc-host-rail-group{border-top:1px solid #eef1f6;margin-top:2px;padding-top:8px}
            .hpc-host-rail-title{color:#8492a6;font-size:10.5px;font-weight:800;letter-spacing:.07em;margin:0;padding:6px 10px 4px;text-transform:uppercase}
            .hpc-host-tabs{background:#fff;border:1px solid var(--hpc-line);border-radius:var(--hpc-radius);display:flex;flex-wrap:wrap;gap:6px;margin:0 0 14px;max-width:100%;padding:6px}
            .hpc-host-rail .hpc-host-tabs{background:none;border:0;border-radius:0;display:block;gap:0;margin:0;padding:0}
            .hpc-host-tab{align-items:center;border:1px solid transparent;border-radius:6px;color:#31405a;cursor:pointer;display:inline-flex;font-size:13px;font-weight:700;gap:7px;line-height:1.25;max-width:100%;padding:8px 12px;text-decoration:none;transition:background .15s,border-color .15s,color .15s;white-space:normal}
            .hpc-host-tab:hover{background:#eef3fc;color:var(--hpc-blue)}
            .hpc-host-tab:focus-visible{box-shadow:0 0 0 2px #fff,0 0 0 4px var(--hpc-blue);outline:0}
            .hpc-host-tab.active,.hpc-host-tab.active:hover{background:var(--hpc-blue);border-color:var(--hpc-blue);color:#fff}
            .hpc-host-rail .hpc-host-tab{display:block;font-weight:600;margin:1px 0;padding:8px 11px}
            .hpc-host-main{max-width:100%;min-width:0}
            .hpc-host-tab-status{border:0;clip:rect(0,0,0,0);height:1px;margin:-1px;overflow:hidden;padding:0;position:absolute;white-space:nowrap;width:1px}
            .hpc-host-tab-panel{background:#fff;border:1px solid var(--hpc-line);border-radius:var(--hpc-radius);max-width:100%;min-height:220px;min-width:0;padding:22px 24px;position:relative}
            .hpc-host-tab-panel.is-loading{opacity:.6;pointer-events:none}
            .hpc-host-tab-panel.is-loading:after{animation:hpc-host-load 1s linear infinite;background:linear-gradient(90deg,transparent,var(--hpc-blue),transparent);background-size:200% 100%;border-radius:var(--hpc-radius) var(--hpc-radius) 0 0;content:"";height:3px;left:0;position:absolute;right:0;top:0}
            @keyframes hpc-host-load{0%{background-position:200% 0}100%{background-position:-200% 0}}
            .hpc-section{background:#fff;border:1px solid var(--hpc-line);border-radius:8px;margin:0 0 14px;overflow:hidden;transition:border-color .18s,box-shadow .18s}
            .hpc-section summary{align-items:center;cursor:pointer;display:flex;font-size:15px;font-weight:800;gap:12px;justify-content:space-between;list-style:none;padding:15px 16px;transition:background .18s}
            .hpc-section[open]{border-color:#c6d3e4;box-shadow:0 0 0 1px rgba(49,87,213,.06)}
            .hpc-section[open]>summary{background:#f1f5fb}
            .hpc-section summary::-webkit-details-marker{display:none}
            .hpc-section-title{min-width:0;overflow-wrap:anywhere;white-space:normal}
            .hpc-section-summary-side{align-items:center;display:inline-flex;flex:0 0 auto;gap:10px;margin-left:auto}
            .hpc-section-toggle{align-items:center;background:#eef2ff;border:1px solid #dbe4ff;border-radius:999px;color:var(--hpc-blue);display:inline-flex;height:28px;justify-content:center;transition:background .18s,border-color .18s,color .18s;width:28px}
            .hpc-section-toggle svg{display:block;fill:currentColor;height:12px;transform:rotate(180deg);transition:transform .18s;width:12px}
            .hpc-section:not([open]) .hpc-section-toggle svg{transform:rotate(0deg)}
            .hpc-section summary:hover .hpc-section-toggle{background:#e4ebff;border-color:#c7d4ff}
            .hpc-section summary:focus-visible{box-shadow:inset 0 0 0 2px var(--hpc-blue);outline:0}
            .hpc-section-body{border-top:1px solid var(--hpc-line);padding:16px}
            .hpc-callout{background:#f8fbff;border:1px solid #cfe0ff;border-left:4px solid var(--hpc-blue);border-radius:8px;color:#253650;padding:12px 14px}
            .hpc-field{display:block;margin:0 0 12px}
            .hpc-field span{color:#314056;display:block;font-size:12px;font-weight:800;margin:0 0 6px;text-transform:uppercase}
            .hpc-field input,.hpc-field select,.hpc-field textarea{background:#fff;border:1px solid #a9b4c3;border-radius:6px;color:#172033;font-size:14px;min-height:40px;padding:8px 10px;width:100%}
            .hpc-field select{-webkit-appearance:none;appearance:none;background-color:#fff;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3E%3Cpath fill='%23314056' d='M5.2 7.3a1 1 0 0 1 1.4 0L10 10.7l3.4-3.4a1 1 0 1 1 1.4 1.4l-4.1 4.1a1 1 0 0 1-1.4 0L5.2 8.7a1 1 0 0 1 0-1.4Z'/%3E%3C/svg%3E");background-position:right 11px center;background-repeat:no-repeat;background-size:18px 18px;cursor:pointer;padding-right:38px}
            .hpc-field select:focus{border-color:var(--hpc-blue);box-shadow:0 0 0 2px rgba(49,87,213,.14);outline:0}
            .hpc-small{color:var(--hpc-muted);font-size:12px;line-height:1.45;margin:8px 0 0}
            .hpc-steps{margin:8px 0 0 20px}
            .hpc-steps li{margin:5px 0}
            .hpc-credential-demo{display:grid;gap:12px}
            .hpc-credential-head{align-items:flex-start;display:flex;gap:12px;justify-content:space-between}
            .hpc-credential-head h4{font-size:15px;margin:0 0 6px}
            .hpc-credential-head p{color:var(--hpc-muted);font-size:13px;margin:0}
            .hpc-collection-filter{align-items:center;background:#f8fafc;border:1px solid var(--hpc-line);border-radius:8px;display:grid;gap:8px 14px;grid-template-columns:minmax(240px,520px) auto;margin:18px 0;padding:12px 14px}
            .hpc-collection-filter-field{min-width:0;position:relative}
            .hpc-collection-filter-icon{color:#718096;font-size:18px;height:18px;left:14px;pointer-events:none;position:absolute;top:50%;transform:translateY(-50%);width:18px;z-index:1}
            .hpc-collection-filter-field .hpc-collection-filter-input{background:#fff;border:1px solid #cbd5e1;border-radius:6px;box-sizing:border-box;font-size:13px;height:40px;margin:0;padding:0 44px 0 46px;width:100%}
            .hpc-collection-filter-input:focus{border-color:var(--hpc-blue);box-shadow:0 0 0 2px rgba(49,87,213,.14);outline:0}
            .hpc-collection-filter-clear{align-items:center;background:transparent;border:0;border-radius:5px;color:#64748b;cursor:pointer;display:flex;height:30px;justify-content:center;padding:0;position:absolute;right:5px;top:50%;transform:translateY(-50%);width:30px}
            .hpc-collection-filter-clear:hover{background:#eef2f7;color:#172033}
            .hpc-collection-filter-clear:focus-visible{box-shadow:0 0 0 2px var(--hpc-blue);outline:0}
            .hpc-collection-filter-clear[hidden]{display:none}
            .hpc-collection-filter-status{color:var(--hpc-muted);font-size:12px;justify-self:end;white-space:nowrap}
            .hpc-collection-filter-status strong{color:var(--hpc-ink)}
            .hpc-collection-filter-empty{color:var(--hpc-muted);font-size:13px;grid-column:1/-1;margin:0;padding:3px 0}
            .hpc-ui [data-hpc-filter-hidden="1"]{display:none!important}
            @media(max-width:680px){.hpc-collection-filter{grid-template-columns:minmax(0,1fr)}.hpc-collection-filter-status{justify-self:start}}
            .hpc-smart-search{position:relative}
            .hpc-smart-search-results[hidden],.hpc-smart-search-selected[hidden]{display:none!important}
            .hpc-smart-search-status{color:var(--hpc-muted);font-size:12px;margin-top:6px}
            .hpc-smart-search-results{background:#fff;border:1px solid #d3dce8;border-radius:8px;box-shadow:0 12px 30px rgba(15,23,42,.12);left:0;margin-top:6px;max-height:260px;overflow:auto;position:absolute;right:0;z-index:30}
            .hpc-smart-search-result{background:#fff;border:0;border-bottom:1px solid #edf1f6;color:#172033;cursor:pointer;display:grid;gap:2px;grid-template-columns:minmax(0,1fr) auto;padding:10px 12px;text-align:left;width:100%}
            .hpc-smart-search-result:hover,.hpc-smart-search-result.active{background:#eef4ff}
            .hpc-smart-search-result strong{font-size:13px;grid-column:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
            .hpc-smart-search-result span{color:var(--hpc-muted);font-size:12px;grid-column:1}
            .hpc-smart-search-result em{color:#8a98aa;font-size:11px;font-style:normal;grid-column:2;grid-row:1 / span 2}
            .hpc-smart-search-selected{align-items:center;background:#eefbf3;border:1px solid #c7eed5;border-radius:8px;display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;padding:8px 10px}
            .hpc-smart-search-selected strong{font-size:13px}
            .hpc-smart-search-selected span:last-child{color:var(--hpc-muted);font-size:12px}
            .hpc-tooltip{align-items:center;background:#edf2f7;border:1px solid #cfdae7;border-radius:999px;color:#334155;cursor:help;display:inline-flex;font-size:11px;font-weight:800;height:20px;justify-content:center;position:relative;width:20px}
            .hpc-tooltip:focus{outline:2px solid var(--hpc-blue);outline-offset:2px}
            .hpc-tooltip[data-tooltip]:hover:after,.hpc-tooltip[data-tooltip]:focus:after{background:#111827;border-radius:6px;bottom:calc(100% + 8px);color:#fff;content:attr(data-tooltip);font-size:12px;font-weight:500;left:50%;line-height:1.4;max-width:280px;min-width:180px;padding:8px 10px;position:absolute;transform:translateX(-50%);white-space:normal;z-index:20}
            .hpc-toggle-list{display:grid;gap:10px;margin:10px 0}
            .hpc-toggle-row{align-items:flex-start;background:#fff;border:1px solid var(--hpc-line);border-radius:8px;display:flex;gap:10px;padding:10px 12px}
            .hpc-toggle{align-items:center;cursor:pointer;display:inline-flex;gap:9px;font-weight:800;line-height:1.3}
            .hpc-toggle input{clip:rect(0 0 0 0);height:1px;margin:-1px;opacity:0;overflow:hidden;position:absolute;width:1px}
            .hpc-toggle-ui{background:#cbd5e1;border-radius:999px;display:inline-block;flex:0 0 auto;height:22px;position:relative;width:40px}
            .hpc-toggle-ui:before{background:#fff;border-radius:999px;content:"";height:16px;left:3px;position:absolute;top:3px;transition:.18s;width:16px}
            .hpc-toggle input:checked+.hpc-toggle-ui{background:var(--hpc-blue)}
            .hpc-toggle input:checked+.hpc-toggle-ui:before{transform:translateX(18px)}
            .hpc-toggle input:focus+.hpc-toggle-ui{outline:2px solid var(--hpc-blue);outline-offset:2px}
            .hpc-toggle-label{align-items:center;display:inline-flex;gap:6px}
            .hpc-inline-details{border:0;margin:6px 0 0 0}
            .hpc-inline-details summary{align-items:center;color:var(--hpc-muted);cursor:pointer;display:inline-flex;font-size:12px;font-weight:800;gap:5px;list-style:none}
            .hpc-inline-details summary::-webkit-details-marker{display:none}
            .hpc-inline-details summary:before{content:"+";font-weight:900;line-height:1}
            .hpc-inline-details[open] summary:before{content:"-"}
            .hpc-inline-details-body{color:#3f4d63;font-size:12px;line-height:1.5;margin:6px 0 0;padding-left:15px}
            .hpc-actions.hpc-actions-bottom{border-top:1px solid var(--hpc-line);margin-top:12px;padding-top:12px}
            .hpc-external:after{content:"\2197";display:inline-block;font-size:.8em;font-weight:900;line-height:1;margin-left:5px;text-decoration:none;transform:translateY(-1px)}
            .hpc-path,.hpc-code{background:#eef0f2;border-radius:5px;color:#2f3a4a;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;padding:2px 5px;word-break:break-all}
            .hpc-readme{background:#0f1720;border-radius:8px;color:#dbe7f3;max-height:380px;overflow:auto;padding:14px;white-space:pre-wrap}
            .hpc-list{margin:0 0 0 20px}
            .hpc-list li{margin:6px 0}
            @media(max-width:1000px){.hpc-hero,.hpc-grid,.hpc-grid.two{grid-template-columns:1fr}}
            @media(max-width:960px){
                .hpc-host-tabs-shell.is-sidebar,.hpc-host-tabs-shell.is-sidebar.is-sidebar-collapsed{grid-template-columns:minmax(0,1fr)}
                .hpc-host-rail{max-height:none;position:static;width:100%}
                .hpc-host-tabs-shell.is-sidebar-collapsed .hpc-host-rail{width:44px}
                .hpc-host-rail-navigation{max-width:100%;width:100%}
                .hpc-host-rail-group{border-top:0!important;margin:0;padding:0 0 8px;width:100%}
                .hpc-host-rail-title{padding:6px 4px 2px;width:100%}
                .hpc-host-rail .hpc-host-tabs{display:flex;flex-wrap:wrap;gap:6px;max-width:100%}
                .hpc-host-rail .hpc-host-tab{display:inline-flex;margin:0;max-width:100%}
                .hpc-host-tab-panel{padding:18px 16px}
            }
        </style>
        <script>
        (function(){
            if (window.hexaPluginCoreUiReady) return;
            window.hexaPluginCoreUiReady = true;
            var detailsQueryParam = 'hpc_open';
            function detailsQueryState() {
                var state = { specified: false, keys: [] };
                try {
                    var params = new URLSearchParams(window.location.search || '');
                    state.specified = params.has(detailsQueryParam);
                    params.getAll(detailsQueryParam).forEach(function(value) {
                        String(value || '').split(',').forEach(function(key) {
                            key = key.trim();
                            if (!key || key === 'none' || state.keys.indexOf(key) !== -1) return;
                            state.keys.push(key);
                        });
                    });
                } catch (e) {}
                return state;
            }
            function detailsIn(scope) {
                var selector = 'details[data-hpc-persist-key],details[data-hpc-query-key]';
                var items = [];
                if (scope && scope.matches && scope.matches(selector)) items.push(scope);
                if (scope && scope.querySelectorAll) items = items.concat(Array.prototype.slice.call(scope.querySelectorAll(selector)));
                return items;
            }
            function setRestoredOpen(item, open) {
                if (item.open === open) return;
                item.dataset.hpcStateRestoring = '1';
                item.open = open;
                window.setTimeout(function() { delete item.dataset.hpcStateRestoring; }, 0);
            }
            function initPersistentDetails(scope) {
                scope = scope || document;
                var query = detailsQueryState();
                var items = detailsIn(scope);
                items.forEach(function(item) {
                    if (item.dataset.hpcPersistKey && item.dataset.hpcPersistentReady !== '1') {
                        item.dataset.hpcPersistentReady = '1';
                        try {
                            var stored = window.localStorage ? window.localStorage.getItem('hpc-details-' + item.dataset.hpcPersistKey) : null;
                            if (stored === '1') setRestoredOpen(item, true);
                            if (stored === '0') setRestoredOpen(item, false);
                        } catch (e) {}
                    }
                    if (item.dataset.hpcQueryKey && item.dataset.hpcQueryReady !== '1') {
                        item.dataset.hpcQueryReady = '1';
                        if (query.specified) setRestoredOpen(item, query.keys.indexOf(item.dataset.hpcQueryKey) !== -1);
                    }
                });
            }
            function updateDetailsQuery() {
                if (!window.history || !history.replaceState || typeof URL !== 'function') return;
                try {
                    var keys = [];
                    Array.prototype.slice.call(document.querySelectorAll('details[data-hpc-query-key]')).forEach(function(item) {
                        var key = item.dataset.hpcQueryKey || '';
                        if (item.open && key && keys.indexOf(key) === -1) keys.push(key);
                    });
                    var url = new URL(window.location.href);
                    url.searchParams.delete(detailsQueryParam);
                    if (keys.length) url.searchParams.set(detailsQueryParam, keys.join(','));
                    else url.searchParams.set(detailsQueryParam, 'none');
                    history.replaceState(history.state || null, '', url.toString());
                } catch (e) {}
            }
            window.hexaPluginCoreInitPersistentDetails = initPersistentDetails;
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() { initPersistentDetails(document); });
            } else {
                initPersistentDetails(document);
            }
            document.addEventListener('hexa-core-host-tab-loaded', function(event) {
                initPersistentDetails(event.detail && event.detail.panel ? event.detail.panel : document);
            });
            document.addEventListener('toggle', function(event) {
                var details = event.target;
                if (!details || !details.matches || !details.matches('details[data-hpc-persist-key],details[data-hpc-query-key]')) return;
                if (details.dataset.hpcStateRestoring === '1') return;
                if (details.dataset.hpcPersistKey && details.dataset.hpcPersistentReady !== '1') return;
                if (details.dataset.hpcQueryKey && details.dataset.hpcQueryReady !== '1') return;
                if (details.dataset.hpcPersistKey) {
                    try {
                        if (window.localStorage) window.localStorage.setItem('hpc-details-' + details.dataset.hpcPersistKey, details.open ? '1' : '0');
                    } catch (e) {}
                }
                if (details.dataset.hpcQueryKey) updateDetailsQuery();
            }, true);
            document.addEventListener('click', function(event) {
                var trigger = event.target.closest('[data-hpc-copy]');
                if (!trigger || !navigator.clipboard) return;
                navigator.clipboard.writeText(trigger.getAttribute('data-hpc-copy') || '').then(function(){
                    var old = trigger.textContent;
                    trigger.textContent = 'Copied';
                    window.setTimeout(function(){ trigger.textContent = old; }, 1200);
                });
            });
            function initCollectionFilters(scope) {
                scope = scope || document;
                var filters = [];
                if (scope.matches && scope.matches('[data-hpc-collection-filter]')) filters.push(scope);
                if (scope.querySelectorAll) filters = filters.concat(Array.prototype.slice.call(scope.querySelectorAll('[data-hpc-collection-filter]')));
                filters.forEach(function(filter) {
                    if (filter.dataset.hpcCollectionFilterReady === '1') return;
                    var target = document.getElementById(filter.dataset.targetId || '');
                    var input = filter.querySelector('[data-hpc-filter-input]');
                    var clear = filter.querySelector('[data-hpc-filter-clear]');
                    var status = filter.querySelector('[data-hpc-filter-status]');
                    var empty = filter.querySelector('[data-hpc-filter-empty]');
                    var itemSelector = filter.dataset.itemSelector || '[data-hpc-filter-item]';
                    var textSelector = filter.dataset.textSelector || '';
                    var groupSelector = filter.dataset.groupSelector || '';
                    if (!target || !input || !status) return;
                    filter.dataset.hpcCollectionFilterReady = '1';

                    function selected(selector, root) {
                        if (!selector || !root || !root.querySelectorAll) return [];
                        try {
                            return Array.prototype.slice.call(root.querySelectorAll(selector));
                        } catch (e) {
                            return [];
                        }
                    }
                    function applyFilter() {
                        var query = (input.value || '').trim().toLocaleLowerCase();
                        var items = selected(itemSelector, target);
                        var visible = 0;
                        items.forEach(function(item) {
                            var text = item.getAttribute('data-hpc-filter-text') || '';
                            if (!text && textSelector) {
                                text = selected(textSelector, item).map(function(node) { return node.textContent || ''; }).join(' ');
                            }
                            if (!text) text = item.textContent || '';
                            var matches = !query || text.toLocaleLowerCase().indexOf(query) !== -1;
                            item.hidden = !matches;
                            if (matches) item.removeAttribute('data-hpc-filter-hidden');
                            else item.setAttribute('data-hpc-filter-hidden', '1');
                            if (matches) visible++;
                        });
                        selected(groupSelector, target).forEach(function(group) {
                            var groupItems = items.filter(function(item) { return group.contains(item); });
                            var groupHidden = !!query && groupItems.length > 0 && groupItems.every(function(item) { return item.hidden; });
                            group.hidden = groupHidden;
                            if (groupHidden) group.setAttribute('data-hpc-filter-hidden', '1');
                            else group.removeAttribute('data-hpc-filter-hidden');
                        });
                        var singular = filter.dataset.itemLabelSingular || 'item';
                        var plural = filter.dataset.itemLabelPlural || 'items';
                        status.textContent = visible + ' of ' + items.length + ' ' + (items.length === 1 ? singular : plural);
                        if (clear) clear.hidden = !query;
                        if (empty) empty.hidden = !query || visible !== 0;
                    }

                    input.addEventListener('input', applyFilter);
                    input.addEventListener('keydown', function(event) {
                        if (event.key !== 'Escape' || !input.value) return;
                        event.preventDefault();
                        input.value = '';
                        applyFilter();
                    });
                    if (clear) {
                        clear.addEventListener('click', function() {
                            input.value = '';
                            applyFilter();
                            input.focus();
                        });
                    }
                    if (window.MutationObserver) {
                        new MutationObserver(function() { applyFilter(); }).observe(target, {childList:true, subtree:true});
                    }
                    applyFilter();
                });
            }
            window.hexaPluginCoreInitCollectionFilters = initCollectionFilters;
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", function() { initCollectionFilters(document); });
            } else {
                initCollectionFilters(document);
            }
            document.addEventListener('hexa-core-host-tab-loaded', function(event) {
                initCollectionFilters(event.detail && event.detail.panel ? event.detail.panel : document);
            });
        })();
        </script>
        <?php
    }

    public static function tooltip( string $text, string $label = '?' ): string {
        return '<span class="hpc-tooltip" tabindex="0" data-tooltip="' . esc_attr( $text ) . '">' . esc_html( $label ) . '</span>';
    }

    public static function toggle( string $name, bool $checked, string $label, array $args = [] ): string {
        $id           = isset( $args['id'] ) ? sanitize_key( (string) $args['id'] ) : sanitize_key( $name . '-' . md5( $label ) );
        $value        = isset( $args['value'] ) ? (string) $args['value'] : '1';
        $class_names  = preg_split( '/\s+/', trim( 'hpc-toggle ' . (string) ( $args['class'] ?? '' ) ) ) ?: [];
        $class_names  = array_values( array_filter( array_map( 'sanitize_html_class', $class_names ) ) );
        $class        = implode( ' ', $class_names );
        $input_names  = preg_split( '/\s+/', trim( (string) ( $args['input_class'] ?? '' ) ) ) ?: [];
        $input_names  = array_values( array_filter( array_map( 'sanitize_html_class', $input_names ) ) );
        $input_class  = [] !== $input_names ? ' class="' . esc_attr( implode( ' ', $input_names ) ) . '"' : '';
        $disabled     = ! empty( $args['disabled'] ) ? ' disabled' : '';
        $tooltip      = '' !== (string) ( $args['tooltip'] ?? '' ) ? ' ' . self::tooltip( (string) $args['tooltip'] ) : '';
        $checked_attr = $checked ? ' checked' : '';
        $data_attrs   = '';

        foreach ( (array) ( $args['data'] ?? [] ) as $data_key => $data_value ) {
            if ( ! is_scalar( $data_value ) ) {
                continue;
            }
            $data_key = sanitize_key( str_replace( '_', '-', (string) $data_key ) );
            if ( '' === $data_key ) {
                continue;
            }
            $data_attrs .= ' data-' . $data_key . '="' . esc_attr( (string) $data_value ) . '"';
        }

        return '<label class="' . esc_attr( $class ) . '" for="' . esc_attr( $id ) . '">'
            . '<input id="' . esc_attr( $id ) . '" type="checkbox" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $input_class . $data_attrs . $checked_attr . $disabled . '>'
            . '<span class="hpc-toggle-ui" aria-hidden="true"></span>'
            . '<span class="hpc-toggle-label">' . esc_html( $label ) . $tooltip . '</span>'
            . '</label>';
    }
    public static function inline_details( string $summary, string $body_html, bool $open = false ): string {
        return '<details class="hpc-inline-details"' . ( $open ? ' open' : '' ) . '><summary>' . esc_html( $summary ) . '</summary><div class="hpc-inline-details-body">' . wp_kses_post( $body_html ) . '</div></details>';
    }

    public static function external_link( string $url, string $label, string $class = 'hpc-button secondary' ): string {
        return '<a class="' . esc_attr( trim( $class . ' hpc-external' ) ) . '" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $label ) . '</a>';
    }

    public static function pill( string $text, string $tone = '' ): string {
        $class = trim( 'hpc-pill ' . sanitize_html_class( $tone ) );

        return '<span class="' . esc_attr( $class ) . '">' . esc_html( $text ) . '</span>';
    }

    public static function card( array $args ): string {
        $title = isset( $args['title'] ) ? (string) $args['title'] : '';
        $body  = isset( $args['body_html'] ) ? (string) $args['body_html'] : '';
        $meta  = isset( $args['meta_html'] ) ? (string) $args['meta_html'] : '';

        return '<article class="hpc-card">'
            . ( '' !== $title ? '<h3>' . esc_html( $title ) . '</h3>' : '' )
            . $body
            . $meta
            . '</article>';
    }

    public static function subcard( array $args ): string {
        $title = isset( $args['title'] ) ? (string) $args['title'] : '';
        $body  = isset( $args['body_html'] ) ? (string) $args['body_html'] : '';

        return '<article class="hpc-subcard">'
            . ( '' !== $title ? '<h4>' . esc_html( $title ) . '</h4>' : '' )
            . $body
            . '</article>';
    }

    public static function detail_card( array $args ): string {
        $title       = isset( $args['title'] ) ? (string) $args['title'] : '';
        $body        = isset( $args['body_html'] ) ? (string) $args['body_html'] : '';
        $open        = ! empty( $args['open'] ) ? ' open' : '';
        $meta        = isset( $args['meta_html'] ) ? (string) $args['meta_html'] : '';
        $persist_key = isset( $args['persist_key'] ) ? (string) $args['persist_key'] : '';
        $persist     = '' !== $persist_key ? ' data-hpc-persist-key="' . esc_attr( $persist_key ) . '"' : '';
        $classes     = [ 'hpc-detail-card' ];

        foreach ( [ $args['variant'] ?? '', $args['class'] ?? '' ] as $class_string ) {
            foreach ( preg_split( '/\s+/', (string) $class_string ) ?: [] as $class_name ) {
                $class_name = sanitize_html_class( $class_name );
                if ( '' !== $class_name ) {
                    $classes[] = $class_name;
                }
            }
        }

        $toggle = '<span class="hpc-detail-card-toggle" aria-hidden="true"><svg viewBox="0 0 512 512" focusable="false"><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"></path></svg></span>';

        return '<details class="' . esc_attr( implode( ' ', array_unique( $classes ) ) ) . '"' . $open . $persist . '><summary><span class="hpc-detail-card-title">' . esc_html( $title ) . '</span><span class="hpc-detail-card-side">' . $meta . $toggle . '</span></summary><div class="hpc-detail-card-body">' . $body . '</div></details>';
    }

    public static function collapsible( array $args ): string {
        $title = isset( $args['title'] ) ? (string) $args['title'] : '';
        $body  = isset( $args['body_html'] ) ? (string) $args['body_html'] : '';
        $open        = ! empty( $args['open'] ) ? ' open' : '';
        $meta        = isset( $args['meta_html'] ) ? (string) $args['meta_html'] : '';
        $persist_key = isset( $args['persist_key'] ) ? (string) $args['persist_key'] : '';
        $persist     = '' !== $persist_key ? ' data-hpc-persist-key="' . esc_attr( $persist_key ) . '"' : '';
        $query_state = ! array_key_exists( 'query_state', $args ) || ! empty( $args['query_state'] );
        $query_seed  = isset( $args['query_key'] ) ? (string) $args['query_key'] : $title;
        $query_key   = $query_state ? self::normalize_query_key( $query_seed ) : '';
        $query       = '' !== $query_key ? ' data-hpc-query-key="' . esc_attr( $query_key ) . '"' : '';
        $classes     = [ 'hpc-section' ];
        foreach ( preg_split( '/\\s+/', (string) ( $args['class'] ?? '' ) ) ?: [] as $class_name ) {
            $class_name = sanitize_html_class( $class_name );
            if ( '' !== $class_name ) {
                $classes[] = $class_name;
            }
        }
        $toggle = '<span class="hpc-section-toggle" aria-hidden="true"><svg viewBox="0 0 512 512" focusable="false"><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"></path></svg></span>';

        return '<details class="' . esc_attr( implode( ' ', array_unique( $classes ) ) ) . '"' . $open . $persist . $query . '><summary><span class="hpc-section-title">' . esc_html( $title ) . '</span><span class="hpc-section-summary-side">' . $meta . $toggle . '</span></summary><div class="hpc-section-body">' . $body . '</div></details>';
    }

    private static function normalize_query_key( string $value ): string {
        $raw = trim( $value );
        if ( '' === $raw ) {
            return '';
        }
        $key = strtolower( $raw );
        $key = preg_replace( '/[^a-z0-9]+/', '-', $key ) ?: '';
        $key = trim( $key, '-' );
        if ( '' === $key ) {
            return 'section-' . substr( md5( $raw ), 0, 12 );
        }
        return rtrim( substr( $key, 0, 80 ), '-' );
    }

    public static function collection_filter( array $args ): string {
        $target_id      = sanitize_html_class( (string) ( $args['target_id'] ?? '' ) );
        $item_selector  = trim( (string) ( $args['item_selector'] ?? '[data-hpc-filter-item]' ) );
        $text_selector  = trim( (string) ( $args['text_selector'] ?? '' ) );
        $group_selector = trim( (string) ( $args['group_selector'] ?? '' ) );

        if ( '' === $target_id || '' === $item_selector ) {
            return '';
        }

        $id            = sanitize_html_class( (string) ( $args['id'] ?? $target_id . '-filter' ) );
        $label         = (string) ( $args['label'] ?? 'Search items' );
        $placeholder   = (string) ( $args['placeholder'] ?? $label );
        $singular      = (string) ( $args['item_label_singular'] ?? 'item' );
        $plural        = (string) ( $args['item_label_plural'] ?? $singular . 's' );
        $empty_message = (string) ( $args['empty_message'] ?? 'No matching items.' );

        return '<div class="hpc-collection-filter" data-hpc-collection-filter data-target-id="' . esc_attr( $target_id ) . '" data-item-selector="' . esc_attr( $item_selector ) . '" data-text-selector="' . esc_attr( $text_selector ) . '" data-group-selector="' . esc_attr( $group_selector ) . '" data-item-label-singular="' . esc_attr( $singular ) . '" data-item-label-plural="' . esc_attr( $plural ) . '">'
            . '<label class="screen-reader-text" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>'
            . '<div class="hpc-collection-filter-field">'
            . '<span class="dashicons dashicons-search hpc-collection-filter-icon" aria-hidden="true"></span>'
            . '<input id="' . esc_attr( $id ) . '" class="hpc-collection-filter-input" type="search" autocomplete="off" placeholder="' . esc_attr( $placeholder ) . '" aria-controls="' . esc_attr( $target_id ) . '" data-hpc-filter-input>'
            . '<button type="button" class="hpc-collection-filter-clear" aria-label="' . esc_attr( 'Clear ' . strtolower( $label ) ) . '" title="' . esc_attr( 'Clear ' . strtolower( $label ) ) . '" data-hpc-filter-clear hidden><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>'
            . '</div>'
            . '<span class="hpc-collection-filter-status" role="status" aria-live="polite" data-hpc-filter-status></span>'
            . '<p class="hpc-collection-filter-empty" data-hpc-filter-empty hidden>' . esc_html( $empty_message ) . '</p>'
            . '</div>';
    }

    public static function copy_button( string $value, string $label = 'Copy' ): string {
        return '<button type="button" class="hpc-button secondary" data-hpc-copy="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</button>';
    }
}
