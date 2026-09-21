<?php

namespace Hexa\PluginCore\WpAdminUiCleanup;

use Hexa\PluginCore\WpAdminComponents\CoreUi;

final class CleanupRenderer {
    private CleanupRegistry $registry;

    public function __construct( CleanupRegistry $registry ) {
        $this->registry = $registry;
    }

    public function render(): void {
        CoreUi::render_assets();
        $sections = $this->grouped_options();
        $root_id = $this->registry->root_id();
        $nonce = function_exists( "wp_create_nonce" ) ? wp_create_nonce( $this->registry->nonce_action() ) : "";
        $ajax_url = function_exists( "admin_url" ) ? admin_url( "admin-ajax.php" ) : "";
        ?>
        <div id="<?php echo esc_attr( $root_id ); ?>" class="hpc-ui hpc-ui-cleanup" data-hpc-ui-cleanup-root data-ajax-url="<?php echo esc_url( $ajax_url ); ?>" data-ajax-action="<?php echo esc_attr( $this->registry->ajax_action() ); ?>" data-nonce-field="<?php echo esc_attr( $this->registry->nonce_field() ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
            <style>
                #<?php echo esc_attr( $root_id ); ?> .hpc-ui-cleanup-head{background:#fff;border:1px solid var(--hpc-line);border-radius:8px;margin:0 0 14px;padding:16px}
                #<?php echo esc_attr( $root_id ); ?> .hpc-ui-cleanup-head h2{font-size:20px;margin:0 0 6px}
                #<?php echo esc_attr( $root_id ); ?> .hpc-ui-cleanup-head p{color:var(--hpc-muted);font-size:13px;margin:0}
                #<?php echo esc_attr( $root_id ); ?> .hpc-ui-cleanup-section{background:#fff;border:1px solid var(--hpc-line);border-radius:8px;margin:0 0 14px;overflow:hidden}
                #<?php echo esc_attr( $root_id ); ?> .hpc-ui-cleanup-section-title{align-items:center;background:#f8fbff;border-bottom:1px solid var(--hpc-line);display:flex;gap:10px;padding:14px 16px}
                #<?php echo esc_attr( $root_id ); ?> .hpc-ui-cleanup-section-title h3{font-size:16px;margin:0}
                #<?php echo esc_attr( $root_id ); ?> .hpc-ui-cleanup-section-title p{color:var(--hpc-muted);font-size:12px;margin:3px 0 0}
                #<?php echo esc_attr( $root_id ); ?> .hpc-ui-cleanup-row{align-items:center;border-bottom:1px solid #edf1f6;display:grid;gap:16px;grid-template-columns:minmax(0,1fr) auto;padding:15px 16px}
                #<?php echo esc_attr( $root_id ); ?> .hpc-ui-cleanup-row:last-child{border-bottom:0}
                #<?php echo esc_attr( $root_id ); ?> .hpc-ui-cleanup-label{align-items:center;display:flex;flex-wrap:wrap;font-size:14px;font-weight:800;gap:8px;margin:0 0 4px}
                #<?php echo esc_attr( $root_id ); ?> .hpc-ui-cleanup-description{color:var(--hpc-muted);font-size:12px;line-height:1.45;margin:0}
                #<?php echo esc_attr( $root_id ); ?> .hpc-ui-cleanup-status{background:#e7ecf3;border:1px solid #d4deea;border-radius:999px;color:#334155;font-size:11px;font-weight:800;padding:3px 8px}
                #<?php echo esc_attr( $root_id ); ?> .hpc-ui-cleanup-status.is-on{background:#fff0f2;border-color:#ffd0d8;color:var(--hpc-red)}
                #<?php echo esc_attr( $root_id ); ?> .hpc-ui-cleanup-toggle{display:inline-flex;height:28px;position:relative;width:54px}
                #<?php echo esc_attr( $root_id ); ?> .hpc-ui-cleanup-toggle input{height:0;opacity:0;width:0}
                #<?php echo esc_attr( $root_id ); ?> .hpc-ui-cleanup-slider{background:#a5adb8;border-radius:999px;cursor:pointer;inset:0;position:absolute;transition:.2s}
                #<?php echo esc_attr( $root_id ); ?> .hpc-ui-cleanup-slider:before{background:#fff;border-radius:50%;bottom:4px;box-shadow:0 1px 3px rgba(0,0,0,.25);content:"";height:20px;left:4px;position:absolute;transition:.2s;width:20px}
                #<?php echo esc_attr( $root_id ); ?> .hpc-ui-cleanup-toggle input:checked + .hpc-ui-cleanup-slider{background:var(--hpc-blue)}
                #<?php echo esc_attr( $root_id ); ?> .hpc-ui-cleanup-toggle input:checked + .hpc-ui-cleanup-slider:before{transform:translateX(26px)}
                #<?php echo esc_attr( $root_id ); ?> .hpc-ui-cleanup-toggle input:disabled + .hpc-ui-cleanup-slider{cursor:wait;opacity:.55}
                #<?php echo esc_attr( $root_id ); ?> .hpc-ui-cleanup-save{border-radius:999px;display:inline-flex;font-size:11px;font-weight:800;margin-top:6px;min-height:20px;padding:3px 8px}
                #<?php echo esc_attr( $root_id ); ?> .hpc-ui-cleanup-save:empty{display:none}
                #<?php echo esc_attr( $root_id ); ?> .hpc-ui-cleanup-save.is-saving{background:#eef2f7;color:#475569}
                #<?php echo esc_attr( $root_id ); ?> .hpc-ui-cleanup-save.is-saved{background:#e6f4ea;color:#137333}
                #<?php echo esc_attr( $root_id ); ?> .hpc-ui-cleanup-save.is-error{background:#fce8e6;color:#b32d2e}
            </style>
            <div class="hpc-ui-cleanup-head"><h2>UI Cleanup</h2><p>Hide or collapse noisy WordPress admin elements on the screens where they render. Settings save through AJAX and behavior loads admin-wide.</p></div>
            <?php foreach ( $sections as $section_id => $section ) : ?>
                <section class="hpc-ui-cleanup-section" data-section="<?php echo esc_attr( $section_id ); ?>">
                    <div class="hpc-ui-cleanup-section-title">
                        <?php if ( "" !== $section["icon"] ) : ?><span><?php echo esc_html( $section["icon"] ); ?></span><?php endif; ?>
                        <div><h3><?php echo esc_html( $section["title"] ); ?></h3><?php if ( "" !== $section["description"] ) : ?><p><?php echo esc_html( $section["description"] ); ?></p><?php endif; ?></div>
                    </div>
                    <?php foreach ( $section["options"] as $option ) : $enabled = $this->registry->is_enabled( $option->key ); ?>
                        <div class="hpc-ui-cleanup-row" data-option="<?php echo esc_attr( $option->key ); ?>">
                            <div><div class="hpc-ui-cleanup-label"><span><?php echo esc_html( $option->label ); ?></span><span class="hpc-ui-cleanup-status<?php echo $enabled ? " is-on" : ""; ?>" data-cleanup-status><?php echo esc_html( $enabled ? $option->on_label : $option->off_label ); ?></span></div><?php if ( "" !== $option->description ) : ?><p class="hpc-ui-cleanup-description"><?php echo esc_html( $option->description ); ?></p><?php endif; ?><span class="hpc-ui-cleanup-save" data-cleanup-save aria-live="polite"></span></div>
                            <label class="hpc-ui-cleanup-toggle" aria-label="<?php echo esc_attr( $option->label ); ?>"><input type="checkbox" data-cleanup-toggle data-option="<?php echo esc_attr( $option->key ); ?>" <?php checked( $enabled ); ?>><span class="hpc-ui-cleanup-slider"></span></label>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        </div>
        <script>
        (function(){
            var root = document.getElementById("<?php echo esc_js( $root_id ); ?>");
            if (!root || root.dataset.cleanupReady === "1") return;
            root.dataset.cleanupReady = "1";
            root.addEventListener("change", function(event){
                var input = event.target.closest("[data-cleanup-toggle]");
                if (!input) return;
                var row = input.closest(".hpc-ui-cleanup-row");
                var status = row ? row.querySelector("[data-cleanup-status]") : null;
                var save = row ? row.querySelector("[data-cleanup-save]") : null;
                function saveState(state, message){ if (!save) return; save.className = "hpc-ui-cleanup-save" + (state ? " is-" + state : ""); save.textContent = message || ""; }
                var body = new URLSearchParams();
                body.set("action", root.dataset.ajaxAction || "");
                body.set(root.dataset.nonceField || "nonce", root.dataset.nonce || "");
                body.set("option", input.getAttribute("data-option") || "");
                body.set("enabled", input.checked ? "1" : "0");
                input.disabled = true;
                saveState("saving", "Saving...");
                fetch(root.dataset.ajaxUrl || window.ajaxurl, { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" }, body: body.toString() })
                    .then(function(response){ return response.json(); })
                    .then(function(payload){ if (!payload || !payload.success || !payload.data) throw new Error((payload && payload.data && (payload.data.message || payload.data.error)) || "Save failed"); if (status) { status.textContent = payload.data.status || (input.checked ? "Hidden" : "Visible"); status.classList.toggle("is-on", !!input.checked); } saveState("saved", "Saved"); })
                    .catch(function(error){ input.checked = !input.checked; saveState("error", "Error: " + (error && error.message ? error.message : "Save failed")); })
                    .finally(function(){ input.disabled = false; });
            });
        })();
        </script>
        <?php
    }

    private function grouped_options(): array {
        $sections = $this->registry->sections();
        foreach ( $this->registry->options() as $option ) {
            if ( ! isset( $sections[ $option->section ] ) ) $sections[ $option->section ] = [ "title" => ucwords( str_replace( [ "_", "-" ], " ", $option->section ) ), "description" => "", "icon" => "" ];
            $sections[ $option->section ]["options"][] = $option;
        }
        return array_filter( $sections, static fn( array $section ): bool => ! empty( $section["options"] ) );
    }
}
