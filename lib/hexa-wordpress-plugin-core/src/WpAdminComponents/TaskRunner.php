<?php

namespace Hexa\PluginCore\WpAdminComponents;

/**
 * Shared admin pieces for long jobs that run as a series of AJAX steps:
 * a page header, KPI tiles, a segmented panel switcher, toolbars with filter
 * chips and a bulk-selection bar, data tables, soft badges, a slim progress
 * bar with Stop, and a light activity log, plus the window.HexaWpCoreTaskRunner
 * client that posts each step, runs queues one item at a time, repeats batched
 * steps until they report done, and stops between steps.
 */
final class TaskRunner {
    private const STATES = [ 'pending', 'running', 'done', 'error', 'skipped' ];

    /**
     * @param array{title:string,text?:string,actions_html?:string} $args
     */
    public static function header( array $args ): string {
        self::render_assets();

        return '<header class="hpc-runner-head"><div><h2>' . esc_html( $args['title'] ) . '</h2>'
            . ( isset( $args['text'] ) ? '<p>' . esc_html( $args['text'] ) . '</p>' : '' ) . '</div>'
            . '<div class="hpc-runner-head-actions">' . ( $args['actions_html'] ?? '' ) . '</div></header>';
    }

    /**
     * @param array<int,array{key:string,label:string,value?:string,hint?:string}> $cards
     */
    public static function stats( array $cards ): string {
        self::render_assets();
        $html = '';
        foreach ( $cards as $card ) {
            $key   = sanitize_key( (string) ( $card['key'] ?? '' ) );
            $html .= '<div class="hpc-runner-kpi" data-hpc-kpi="' . esc_attr( $key ) . '">'
                . '<span class="hpc-runner-kpi-label">' . esc_html( (string) ( $card['label'] ?? '' ) ) . '</span>'
                . '<strong class="hpc-runner-kpi-value" data-hpc-stat="' . esc_attr( $key ) . '">' . esc_html( (string) ( $card['value'] ?? "\u{2013}" ) ) . '</strong>'
                . '<span class="hpc-runner-kpi-hint" data-hpc-stat-hint="' . esc_attr( $key ) . '">' . esc_html( (string) ( $card['hint'] ?? '' ) ) . '</span>'
                . '</div>';
        }

        return '<div class="hpc-runner-kpis">' . $html . '</div>';
    }

    /**
     * Segmented switcher. Panels are elements with data-hpc-runner-panel="<id>".
     *
     * @param array<string,string> $segments id => label
     */
    public static function segments( array $segments ): string {
        self::render_assets();
        $html  = '';
        $first = true;
        foreach ( $segments as $id => $label ) {
            $html .= '<button type="button" role="tab" class="hpc-runner-segment' . ( $first ? ' is-active' : '' ) . '" aria-selected="' . ( $first ? 'true' : 'false' ) . '" data-hpc-runner-segment="' . esc_attr( $id ) . '">'
                . esc_html( $label ) . '<span class="hpc-runner-segment-count" data-hpc-segment-count="' . esc_attr( $id ) . '"></span></button>';
            $first = false;
        }

        return '<nav class="hpc-runner-segments" role="tablist">' . $html . '</nav>';
    }

    /**
     * @param array{title?:string,text?:string,stop?:bool} $args
     */
    public static function progress( array $args = [] ): string {
        self::render_assets();

        return '<div class="hpc-runner-progress" data-hpc-runner-progress hidden>'
            . '<div class="hpc-runner-progress-row"><div class="hpc-runner-progress-copy"><strong data-hpc-runner-progress-title>' . esc_html( (string) ( $args['title'] ?? 'Working' ) ) . '</strong>'
            . '<span data-hpc-runner-progress-text>' . esc_html( (string) ( $args['text'] ?? '' ) ) . '</span></div>'
            . '<span class="hpc-runner-progress-pct" data-hpc-runner-progress-pct></span>'
            . ( ! empty( $args['stop'] ) ? '<button type="button" class="hpc-runner-link" data-hpc-runner-stop>Stop</button>' : '' )
            . '</div><div class="hpc-runner-meter"><span data-hpc-runner-progress-bar></span></div></div>';
    }

    /**
     * @param array{title?:string,open?:bool} $args
     */
    public static function log( array $args = [] ): string {
        self::render_assets();

        return '<details class="hpc-runner-log"' . ( ! empty( $args['open'] ) ? ' open' : '' ) . '>'
            . '<summary><span>' . esc_html( (string) ( $args['title'] ?? 'Activity' ) ) . '</span><span class="hpc-runner-log-count" data-hpc-runner-log-count></span>'
            . '<button type="button" class="hpc-runner-link" data-hpc-runner-clear-log>Clear</button></summary>'
            . '<div class="hpc-runner-log-body" data-hpc-runner-log></div>'
            . '</details>';
    }

    public static function state( string $status, string $label = '' ): string {
        self::render_assets();
        $status = in_array( $status, self::STATES, true ) ? $status : 'pending';

        return '<span class="hpc-runner-status is-' . esc_attr( $status ) . '"><i></i>' . esc_html( '' !== $label ? $label : ucfirst( $status ) ) . '</span>';
    }

    public static function render_assets(): void {
        static $rendered = false;
        if ( $rendered ) {
            return;
        }
        $rendered = true;
        CoreUi::render_assets();
        DynamicButton::render_assets();
        ?>
        <style>
            .hpc-runner-app{--r-ink:#0f172a;--r-text:#334155;--r-muted:#64748b;--r-faint:#94a3b8;--r-line:#e2e8f0;--r-soft:#f8fafc;--r-accent:var(--hpc-blue,#2563eb);--r-green:#15803d;--r-amber:#b45309;--r-red:#b91c1c;color:var(--r-text);font-size:13px;max-width:1240px}
            .hpc-runner-app *{box-sizing:border-box}
            .hpc-runner-card{background:#fff;border:1px solid var(--r-line);border-radius:14px;box-shadow:0 1px 2px rgba(15,23,42,.04);margin:0 0 16px}
            .hpc-runner-head{align-items:flex-start;display:flex;flex-wrap:wrap;gap:16px;justify-content:space-between;margin:4px 0 20px}
            .hpc-runner-head h2{color:var(--r-ink);font-size:22px;font-weight:700;letter-spacing:-.01em;line-height:1.2;margin:0 0 6px;padding:0}
            .hpc-runner-head p{color:var(--r-muted);font-size:13px;line-height:1.55;margin:0;max-width:640px}
            .hpc-runner-head-actions{display:flex;flex-wrap:wrap;gap:8px}
            .hpc-runner-kpis{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin:0 0 16px}
            .hpc-runner-kpi{background:#fff;border:1px solid var(--r-line);border-radius:14px;box-shadow:0 1px 2px rgba(15,23,42,.04);display:grid;gap:6px;padding:16px 18px;position:relative}
            .hpc-runner-kpi:before{background:var(--r-line);border-radius:14px 0 0 14px;bottom:-1px;content:"";left:-1px;position:absolute;top:-1px;width:4px}
            .hpc-runner-kpi[data-tone="good"]:before{background:var(--r-green)}
            .hpc-runner-kpi[data-tone="warn"]:before{background:#f59e0b}
            .hpc-runner-kpi[data-tone="bad"]:before{background:var(--r-red)}
            .hpc-runner-kpi[data-tone="info"]:before{background:var(--r-accent)}
            .hpc-runner-kpi-label{color:var(--r-muted);font-size:12px;font-weight:600}
            .hpc-runner-kpi-value{color:var(--r-ink);font-size:26px;font-variant-numeric:tabular-nums;font-weight:700;letter-spacing:-.02em;line-height:1.1}
            .hpc-runner-kpi-hint{color:var(--r-faint);font-size:12px;min-height:16px}
            .hpc-runner-segments{background:#f1f5f9;border-radius:12px;display:inline-flex;flex-wrap:wrap;gap:2px;margin:0 0 16px;padding:4px}
            .hpc-runner-segment{align-items:center;background:transparent;border:0;border-radius:9px;color:var(--r-muted);cursor:pointer;display:inline-flex;font-size:13px;font-weight:600;gap:8px;padding:8px 16px}
            .hpc-runner-segment:hover{color:var(--r-ink)}
            .hpc-runner-segment.is-active{background:#fff;box-shadow:0 1px 3px rgba(15,23,42,.1);color:var(--r-ink)}
            .hpc-runner-segment-count{background:#e2e8f0;border-radius:999px;color:var(--r-text);font-size:11px;font-weight:700;padding:1px 7px}
            .hpc-runner-segment-count:empty{display:none}
            .hpc-runner-segment.is-active .hpc-runner-segment-count{background:var(--r-accent);color:#fff}
            [data-hpc-runner-panel][hidden]{display:none!important}
            .hpc-runner-toolbar{align-items:center;border-bottom:1px solid var(--r-line);display:flex;flex-wrap:wrap;gap:10px;justify-content:space-between;padding:14px 18px}
            .hpc-runner-toolbar-group{align-items:center;display:flex;flex-wrap:wrap;gap:8px}
            .hpc-runner-search{background:#fff;border:1px solid var(--r-line)!important;border-radius:9px!important;box-shadow:none!important;font-size:13px;min-height:36px;padding:0 12px!important;width:260px}
            .hpc-runner-search:focus{border-color:var(--r-accent)!important;box-shadow:0 0 0 3px rgba(37,99,235,.12)!important;outline:0}
            .hpc-runner-chip{align-items:center;background:#fff;border:1px solid var(--r-line);border-radius:999px;color:var(--r-text);cursor:pointer;display:inline-flex;font-size:12px;font-weight:600;gap:6px;padding:6px 12px}
            .hpc-runner-chip:hover{border-color:#cbd5e1}
            .hpc-runner-chip span{color:var(--r-faint);font-variant-numeric:tabular-nums}
            .hpc-runner-chip.is-active{background:var(--r-ink);border-color:var(--r-ink);color:#fff}
            .hpc-runner-chip.is-active span{color:#cbd5e1}
            .hpc-runner-bulkbar{align-items:center;background:var(--r-soft);border-bottom:1px solid var(--r-line);display:flex;flex-wrap:wrap;gap:8px;padding:10px 18px}
            .hpc-runner-bulkbar-label{color:var(--r-muted);font-size:12px;font-weight:600;margin-right:6px}
            .hpc-runner-app .hpc-button{border-radius:9px;font-size:13px;font-weight:600;min-height:36px;padding:9px 14px}
            .hpc-runner-app .hpc-button.secondary{background:#fff;border-color:var(--r-line);color:var(--r-ink)}
            .hpc-runner-app .hpc-button.secondary:hover{border-color:#cbd5e1;background:var(--r-soft)}
            .hpc-runner-app .hpc-button.small{font-size:12px;min-height:30px;padding:6px 11px}
            .hpc-runner-app .hpc-button[disabled]{opacity:.45}
            .hpc-runner-link{background:none;border:0;color:var(--r-accent);cursor:pointer;font-size:12px;font-weight:600;padding:4px 6px}
            .hpc-runner-link:hover{text-decoration:underline}
            .hpc-runner-table-wrap{max-width:100%;overflow-x:auto}
            .hpc-runner-app table.hpc-runner-table{border-collapse:separate;border-spacing:0;max-width:none;min-width:720px;width:100%}
            .hpc-runner-table th,.hpc-runner-table td{border-bottom:1px solid #f1f5f9;font-size:13px;line-height:1.45;padding:12px 14px;text-align:left;vertical-align:middle}
            .hpc-runner-table thead th{background:#fff;border-bottom:1px solid var(--r-line);color:var(--r-muted);font-size:11px;font-weight:700;letter-spacing:.04em;position:sticky;text-transform:uppercase;top:0;white-space:nowrap;z-index:1}
            .hpc-runner-table tbody tr:hover td{background:#fafcff}
            .hpc-runner-table tbody tr:last-child td{border-bottom:0}
            .hpc-runner-table td.num,.hpc-runner-table th.num{font-variant-numeric:tabular-nums;text-align:right;white-space:nowrap}
            .hpc-runner-table .hpc-runner-col-check{padding-right:0;width:36px}
            .hpc-runner-muted{color:var(--r-faint)}
            .hpc-runner-mono{color:var(--r-ink);font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12.5px;overflow-wrap:anywhere}
            .hpc-runner-sub{color:var(--r-muted);display:block;font-size:12px;margin-top:3px}
            .hpc-runner-empty{color:var(--r-muted);padding:36px 18px;text-align:center}
            .hpc-runner-app input[type=checkbox].hpc-runner-check{accent-color:var(--r-accent);border-radius:5px;height:16px;margin:0;width:16px}
            .hpc-runner-badges{align-items:center;display:flex;flex-wrap:wrap;gap:6px}
            .hpc-runner-app details.hpc-runner-more>summary{color:var(--r-accent);cursor:pointer;font-size:12px;font-weight:600;list-style:none;margin-top:4px}
            .hpc-runner-app details.hpc-runner-more>summary::-webkit-details-marker{display:none}
            .hpc-runner-app details.hpc-runner-more>div{color:var(--r-muted);font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;line-height:1.7;margin-top:6px}
            .hpc-runner-badge{align-items:center;background:#f1f5f9;border-radius:999px;color:var(--r-text);display:inline-flex;font-size:11.5px;font-weight:600;gap:5px;line-height:1;padding:4px 9px;white-space:nowrap}
            .hpc-runner-badge.is-green{background:#ecfdf3;color:var(--r-green)}
            .hpc-runner-badge.is-amber{background:#fffbeb;color:var(--r-amber)}
            .hpc-runner-badge.is-red{background:#fef2f2;color:var(--r-red)}
            .hpc-runner-badge.is-blue{background:#eff6ff;color:#1d4ed8}
            .hpc-runner-status{align-items:center;color:var(--r-muted);display:inline-flex;font-size:12.5px;font-weight:600;gap:7px;white-space:nowrap}
            .hpc-runner-status i{background:#cbd5e1;border-radius:999px;display:inline-block;height:8px;width:8px}
            .hpc-runner-status.is-running{color:var(--r-accent)}
            .hpc-runner-status.is-running i{animation:hpc-runner-spin .8s linear infinite;background:transparent;border:2px solid var(--r-accent);border-right-color:transparent;height:12px;width:12px}
            .hpc-runner-status.is-done{color:var(--r-green)}.hpc-runner-status.is-done i{background:#22c55e}
            .hpc-runner-status.is-error{color:var(--r-red)}.hpc-runner-status.is-error i{background:#ef4444}
            .hpc-runner-status.is-skipped i{background:#e2e8f0}
            .hpc-runner-row-running td,.hpc-runner-row-running{background:#f8fbff!important}
            .hpc-runner-progress{background:#fff;border:1px solid var(--r-line);border-radius:14px;box-shadow:0 1px 2px rgba(15,23,42,.04);margin:0 0 16px;padding:14px 18px}
            .hpc-runner-progress[hidden]{display:none}
            .hpc-runner-progress-row{align-items:center;display:flex;gap:12px;margin:0 0 10px}
            .hpc-runner-progress-copy{flex:1;min-width:0}
            .hpc-runner-progress-copy strong{color:var(--r-ink);display:block;font-size:13px}
            .hpc-runner-progress-copy span{color:var(--r-muted);display:block;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
            .hpc-runner-progress-pct{color:var(--r-ink);font-variant-numeric:tabular-nums;font-weight:700}
            .hpc-runner-meter{background:#eef2f7;border-radius:999px;height:6px;overflow:hidden}
            .hpc-runner-meter span{background:linear-gradient(90deg,var(--r-accent),#60a5fa);border-radius:999px;display:block;height:100%;transition:width .3s ease;width:0}
            .hpc-runner-log{background:#fff;border:1px solid var(--r-line);border-radius:14px;margin:0 0 16px;overflow:hidden}
            .hpc-runner-log summary{align-items:center;cursor:pointer;display:flex;font-size:13px;font-weight:700;gap:8px;list-style:none;padding:12px 18px}
            .hpc-runner-log summary::-webkit-details-marker{display:none}
            .hpc-runner-log summary>span:first-child{color:var(--r-ink)}
            .hpc-runner-log summary [data-hpc-runner-clear-log]{margin-left:auto}
            .hpc-runner-log-count{color:var(--r-faint);font-weight:600}
            .hpc-runner-log-body{border-top:1px solid var(--r-line);font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;max-height:260px;overflow:auto}
            .hpc-runner-log-row{align-items:baseline;border-bottom:1px solid #f1f5f9;display:grid;gap:12px;grid-template-columns:64px 10px minmax(0,1fr);padding:7px 18px}
            .hpc-runner-log-time{color:var(--r-faint)}
            .hpc-runner-log-dot{background:#93c5fd;border-radius:999px;height:7px;width:7px}
            .hpc-runner-log-dot.success{background:#22c55e}.hpc-runner-log-dot.warning{background:#f59e0b}.hpc-runner-log-dot.error{background:#ef4444}
            .hpc-runner-log-message{color:var(--r-text);white-space:pre-wrap;word-break:break-word}
            @keyframes hpc-runner-spin{to{transform:rotate(360deg)}}
            @media(max-width:782px){.hpc-runner-search{width:100%}.hpc-runner-kpi-value{font-size:22px}.hpc-runner-segments{display:flex}.hpc-runner-segment{flex:1;justify-content:center;padding:8px 10px}}
        </style>
        <script>
        (function(){
            if(window.HexaWpCoreTaskRunner)return;
            function text(v){return v===null||v===undefined?'':String(v)}
            function esc(v){var d=document.createElement('div');d.textContent=text(v);return d.innerHTML}
            function now(){return new Date().toTimeString().slice(0,8)}
            function bytes(n){n=Number(n)||0;var u=['B','KB','MB','GB','TB'],i=0;while(n>=1024&&i<u.length-1){n/=1024;i++}return (i?n.toFixed(n<10?1:0):n)+' '+u[i]}
            function number(n){return (Number(n)||0).toLocaleString()}
            function state(status,label){status=['pending','running','done','error','skipped'].indexOf(status)>=0?status:'pending';return '<span class="hpc-runner-status is-'+status+'"><i></i>'+esc(label||status.charAt(0).toUpperCase()+status.slice(1))+'</span>'}
            function badge(label,tone){return '<span class="hpc-runner-badge'+(tone?' is-'+esc(tone):'')+'">'+esc(label)+'</span>'}
            function append(body,key,value){if(Array.isArray(value)){value.forEach(function(v){body.append(key+'[]',v)});return}if(value&&typeof value==='object'){Object.keys(value).forEach(function(k){append(body,key+'['+k+']',value[k])});return}body.append(key,value===undefined||value===null?'':value)}
            function create(root,opts){
                root=typeof root==='string'?document.querySelector(root):root;opts=opts||{};
                var ds=root?root.dataset:{},cancelled=false,logged=0;
                var ui={progress:root.querySelector('[data-hpc-runner-progress]'),title:root.querySelector('[data-hpc-runner-progress-title]'),text:root.querySelector('[data-hpc-runner-progress-text]'),pct:root.querySelector('[data-hpc-runner-progress-pct]'),bar:root.querySelector('[data-hpc-runner-progress-bar]'),stop:root.querySelector('[data-hpc-runner-stop]'),log:root.querySelector('[data-hpc-runner-log]'),logCount:root.querySelector('[data-hpc-runner-log-count]')};
                var runner={
                    busy:false,esc:esc,bytes:bytes,number:number,state:state,badge:badge,
                    post:function(action,payload){
                        var body=new URLSearchParams();body.set('action',action||'');body.set(opts.nonceField||ds.nonceField||'nonce',opts.nonce||ds.nonce||'');
                        Object.keys(payload||{}).forEach(function(k){append(body,k,payload[k])});
                        return fetch(opts.ajaxUrl||ds.ajaxUrl||window.ajaxurl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()})
                            .then(function(r){return r.text().then(function(t){try{return JSON.parse(t)}catch(e){throw new Error('Server returned an invalid response (HTTP '+r.status+').')}})})
                            .then(function(p){if(!p||!p.success){var d=p&&p.data||{};throw new Error(d.message||d.error||'Request failed.')}return p.data||{}});
                    },
                    progress:function(done,total,title,msg){if(!ui.progress)return;ui.progress.hidden=false;var pct=total?Math.min(100,Math.round(done/total*100)):0;if(ui.title)ui.title.textContent=title||'';if(ui.text)ui.text.textContent=msg||'';if(ui.pct)ui.pct.textContent=pct+'%';if(ui.bar)ui.bar.style.width=pct+'%'},
                    hideProgress:function(){if(ui.progress)ui.progress.hidden=true},
                    log:function(level,message,time){if(!ui.log)return;level=text(level||'info').toLowerCase();var row=document.createElement('div');row.className='hpc-runner-log-row';row.innerHTML='<span class="hpc-runner-log-time">'+esc(time||now())+'</span><span class="hpc-runner-log-dot '+esc(level)+'"></span><span class="hpc-runner-log-message">'+esc(message)+'</span>';ui.log.appendChild(row);ui.log.scrollTop=ui.log.scrollHeight;logged++;if(ui.logCount)ui.logCount.textContent=logged},
                    logs:function(entries){(entries||[]).forEach(function(e){runner.log(e.level,e.message,e.time)})},
                    clearLog:function(){if(ui.log)ui.log.innerHTML='';logged=0;if(ui.logCount)ui.logCount.textContent=''},
                    setRow:function(row,status,label,cell){if(!row)return;['pending','running','done','error','skipped'].forEach(function(s){row.classList.remove('hpc-runner-row-'+s)});row.classList.add('hpc-runner-row-'+status);var c=row.querySelector(cell||'[data-hpc-runner-state]');if(c)c.innerHTML=state(status,label)},
                    segmentCount:function(id,value){var e=root.querySelector('[data-hpc-segment-count="'+id+'"]');if(e)e.textContent=value===''||value===null||value===undefined?'':value},
                    showSegment:function(id){root.querySelectorAll('[data-hpc-runner-segment]').forEach(function(b){var on=b.getAttribute('data-hpc-runner-segment')===id;b.classList.toggle('is-active',on);b.setAttribute('aria-selected',on?'true':'false')});root.querySelectorAll('[data-hpc-runner-panel]').forEach(function(p){p.hidden=p.getAttribute('data-hpc-runner-panel')!==id});try{localStorage.setItem('hpc-runner-segment-'+root.id,id)}catch(e){}},
                    cancel:function(){cancelled=true;if(ui.stop)ui.stop.disabled=true},
                    cancelled:function(){return cancelled},
                    begin:function(){cancelled=false;runner.busy=true;if(ui.stop){ui.stop.hidden=false;ui.stop.disabled=false}},
                    end:function(){runner.busy=false;if(ui.stop)ui.stop.hidden=true},
                    /* Run worker(item,index) for each item, one at a time (or hooks.concurrency at once for read-only work). Stops early after cancel(). */
                    queue:async function(items,worker,hooks){hooks=hooks||{};var out={done:0,failed:0,cancelled:false},next=0,finished=0,lanes=Math.max(1,Math.min(hooks.concurrency||1,items.length));async function lane(){while(next<items.length){if(cancelled){out.cancelled=true;return}var i=next++;try{await worker(items[i],i);out.done++}catch(e){out.failed++;runner.log('error',e.message||'Step failed.');if(hooks.onError)hooks.onError(items[i],e)}finished++;if(hooks.onProgress)hooks.onProgress(finished,items.length)}}var pool=[];for(var l=0;l<lanes;l++)pool.push(lane());await Promise.all(pool);if(cancelled&&out.done+out.failed<items.length)out.cancelled=true;return out},
                    /* Repeat one batched action until the server returns done:true. */
                    loop:async function(action,payload,hooks){hooks=hooks||{};var max=hooks.maxSteps||500,data={};for(var i=0;i<max;i++){if(cancelled){data.cancelled=true;return data}data=await runner.post(action,payload);if(hooks.onStep)hooks.onStep(data,i);if(data.done)return data}throw new Error('Stopped after '+max+' batches.')}
                };
                if(root){
                    root.addEventListener('click',function(e){
                        if(e.target.closest('[data-hpc-runner-clear-log]')){e.preventDefault();runner.clearLog();return}
                        if(e.target.closest('[data-hpc-runner-stop]')){runner.cancel();runner.log('warning','Stopping after the current step.');return}
                        var seg=e.target.closest('[data-hpc-runner-segment]');if(seg&&root.contains(seg))runner.showSegment(seg.getAttribute('data-hpc-runner-segment'));
                    });
                    var saved=null;try{saved=localStorage.getItem('hpc-runner-segment-'+root.id)}catch(e){}
                    var first=root.querySelector('[data-hpc-runner-segment]');
                    if(first)runner.showSegment(saved&&root.querySelector('[data-hpc-runner-segment="'+saved+'"]')?saved:first.getAttribute('data-hpc-runner-segment'));
                    if(ui.stop)ui.stop.hidden=true;
                }
                return runner;
            }
            window.HexaWpCoreTaskRunner={create:create,esc:esc,bytes:bytes,number:number,state:state,badge:badge};
        })();
        </script>
        <?php
    }
}
