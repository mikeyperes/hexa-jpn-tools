<?php

namespace Hexa\PluginCore\FieldStructures;

use Hexa\PluginCore\WpAdminComponents\CoreUi;

final class FieldStructureRenderer {
    public function render( array $definitions, array $args = [] ): string {
        CoreUi::render_assets();
        $manager = new FieldStructureManager();
        $items   = $manager->normalizeDefinitions( $definitions );
        $summary = $manager->summarize( $items );
        $ajax_url    = isset( $args["ajax_url"] ) ? (string) $args["ajax_url"] : ( function_exists( "admin_url" ) ? admin_url( "admin-ajax.php" ) : "" );
        $save_action = isset( $args["save_action"] ) ? sanitize_key( (string) $args["save_action"] ) : "";
        $nonce       = isset( $args["nonce"] ) ? (string) $args["nonce"] : "";
        $nonce_field = isset( $args["nonce_field"] ) ? sanitize_key( (string) $args["nonce_field"] ) : "nonce";
        $title       = isset( $args["title"] ) ? (string) $args["title"] : "Field Structures";
        $description = isset( $args["description"] ) ? (string) $args["description"] : "Reusable ACF, custom post type, taxonomy, and option-backed structure controls.";
        ob_start();
        ?>
        <div class="hpc-ui hpc-field-structures" data-hpc-field-structures data-ajax-url="<?php echo esc_url( $ajax_url ); ?>" data-save-action="<?php echo esc_attr( $save_action ); ?>" data-nonce-field="<?php echo esc_attr( $nonce_field ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
            <?php echo $this->style_once(); ?>
            <section class="hpc-card hpc-field-structure-intro">
                <div><h3><?php echo esc_html( $title ); ?></h3><p><?php echo esc_html( $description ); ?></p></div>
                <div class="hpc-field-structure-summary">
                    <?php echo CoreUi::pill( (string) $summary["total"] . " total", "dark" ); ?>
                    <?php echo CoreUi::pill( (string) $summary["enabled"] . " enabled", "success" ); ?>
                    <?php echo CoreUi::pill( (string) $summary["registered"] . " registered", $summary["warnings"] ? "warning" : "success" ); ?>
                </div>
            </section>
            <div class="hpc-field-structure-list">
                <?php foreach ( $items as $item ) : ?>
                    <?php echo $this->render_item( $item, "" !== $save_action ); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function render_item( array $item, bool $can_save ): string {
        $enabled    = ! empty( $item["enabled"] );
        $registered = ! empty( $item["registered"] );
        $type_label = $this->type_label( (string) $item["type"] );
        ob_start();
        ?>
        <article class="hpc-field-structure-row" data-field-structure-id="<?php echo esc_attr( (string) $item["id"] ); ?>">
            <div class="hpc-field-structure-main">
                <div class="hpc-field-structure-titleline">
                    <?php echo CoreUi::pill( $type_label, "dark" ); ?>
                    <h4><?php echo esc_html( (string) $item["label"] ); ?></h4>
                    <?php echo CoreUi::pill( $enabled ? "Enabled" : "Disabled", $enabled ? "success" : "warning" ); ?>
                    <?php echo CoreUi::pill( $registered ? "Registered" : "Not registered", $registered ? "success" : ( $enabled ? "danger" : "warning" ) ); ?>
                </div>
                <?php if ( "" !== (string) $item["description"] ) : ?><p><?php echo esc_html( (string) $item["description"] ); ?></p><?php endif; ?>
                <dl class="hpc-field-structure-meta">
                    <?php if ( "" !== (string) $item["setting_key"] ) : ?><div><dt>Setting</dt><dd><span class="hpc-code"><?php echo esc_html( (string) $item["setting_key"] ); ?></span></dd></div><?php endif; ?>
                    <?php if ( "" !== (string) $item["acf_group_key"] ) : ?><div><dt>ACF group</dt><dd><span class="hpc-code"><?php echo esc_html( (string) $item["acf_group_key"] ); ?></span></dd></div><?php endif; ?>
                    <?php if ( "" !== (string) $item["object_name"] ) : ?><div><dt>Object</dt><dd><span class="hpc-code"><?php echo esc_html( (string) $item["object_name"] ); ?></span></dd></div><?php endif; ?>
                    <?php if ( "" !== (string) $item["location"] ) : ?><div><dt>Location</dt><dd><?php echo esc_html( (string) $item["location"] ); ?></dd></div><?php endif; ?>
                </dl>
            </div>
            <div class="hpc-field-structure-side">
                <?php if ( "" !== (string) $item["setting_key"] ) : ?>
                    <label class="hpc-field-switch"><input type="checkbox" class="hpc-field-structure-toggle" data-setting-key="<?php echo esc_attr( (string) $item["setting_key"] ); ?>" <?php checked( $enabled ); ?> <?php disabled( ! $can_save ); ?>><span></span><strong><?php echo $enabled ? esc_html__( "Active", "hexa-plugin-core" ) : esc_html__( "Inactive", "hexa-plugin-core" ); ?></strong></label>
                    <div class="hpc-field-structure-save-status" aria-live="polite"></div>
                <?php else : ?><?php echo CoreUi::pill( "Always on", "dark" ); ?><?php endif; ?>
            </div>
            <div class="hpc-field-structure-details">
                <?php echo $this->detail_block( "Fields", (array) $item["fields"] ); ?>
                <?php echo $this->detail_block( "Dependencies", (array) $item["dependencies"] ); ?>
                <?php if ( "" !== (string) $item["instructions"] ) : ?><?php echo CoreUi::subcard( [ "title" => "Use instructions", "body_html" => "<p>" . esc_html( (string) $item["instructions"] ) . "</p>" ] ); ?><?php endif; ?>
                <?php if ( "" !== (string) $item["code_example"] ) : ?><?php echo CoreUi::subcard( [ "title" => "Code example", "body_html" => "<pre class=\"hpc-readme\">" . esc_html( (string) $item["code_example"] ) . "</pre>" ] ); ?><?php endif; ?>
                <?php echo CoreUi::subcard( [ "title" => "Test report", "body_html" => "<p>" . esc_html( (string) $item["test_report"] ) . "</p>" ] ); ?>
                <?php if ( "" !== (string) $item["activity"] ) : ?><?php echo CoreUi::subcard( [ "title" => "Activity", "body_html" => "<p>" . esc_html( (string) $item["activity"] ) . "</p>" ] ); ?><?php endif; ?>
            </div>
        </article>
        <?php
        return (string) ob_get_clean();
    }

    private function detail_block( string $title, array $items ): string {
        if ( empty( $items ) ) {
            return "";
        }
        $html = "<ul class=\"hpc-list\">";
        foreach ( $items as $item ) {
            $html .= "<li>" . esc_html( (string) $item ) . "</li>";
        }
        return CoreUi::subcard( [ "title" => $title, "body_html" => $html . "</ul>" ] );
    }

    private function type_label( string $type ): string {
        return match ( $type ) {
            "acf" => "ACF",
            "cpt" => "CPT",
            "taxonomy" => "Taxonomy",
            "option" => "Option",
            default => ucwords( str_replace( [ "-", "_" ], " ", $type ) ),
        };
    }

    private function style_once(): string {
        static $rendered = false;
        if ( $rendered ) {
            return "";
        }
        $rendered = true;
        return <<<HTML
<style>.hpc-field-structure-intro{align-items:flex-start;display:flex;gap:18px;justify-content:space-between;margin:0 0 14px}.hpc-field-structure-intro h3{font-size:20px}.hpc-field-structure-summary{align-items:center;display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end}.hpc-field-structure-list{display:grid;gap:14px}.hpc-field-structure-row{background:#fff;border:1px solid var(--hpc-line);border-radius:8px;display:grid;gap:14px;grid-template-columns:minmax(0,1fr) auto;padding:16px}.hpc-field-structure-titleline{align-items:center;display:flex;flex-wrap:wrap;gap:8px;margin:0 0 8px}.hpc-field-structure-titleline h4{font-size:17px;margin:0}.hpc-field-structure-main p{color:#3f4d63;line-height:1.55;margin:0 0 12px}.hpc-field-structure-meta{display:grid;gap:8px;grid-template-columns:repeat(2,minmax(0,1fr));margin:12px 0 0}.hpc-field-structure-meta div{background:#f8fafc;border:1px solid #e3e8f0;border-radius:8px;padding:9px 10px}.hpc-field-structure-meta dt{color:#65758b;font-size:11px;font-weight:800;letter-spacing:.05em;margin:0 0 4px;text-transform:uppercase}.hpc-field-structure-meta dd{margin:0}.hpc-field-structure-side{min-width:170px}.hpc-field-switch{align-items:center;display:inline-flex;gap:10px}.hpc-field-switch input{opacity:0;position:absolute}.hpc-field-switch span{background:#cbd5e1;border-radius:999px;display:inline-block;height:24px;position:relative;width:44px}.hpc-field-switch span:before{background:#fff;border-radius:999px;content:"";height:18px;left:3px;position:absolute;top:3px;transition:.18s;width:18px}.hpc-field-switch input:checked+span{background:var(--hpc-green)}.hpc-field-switch input:checked+span:before{transform:translateX(20px)}.hpc-field-structure-save-status{color:var(--hpc-muted);font-size:12px;margin-top:8px}.hpc-field-structure-details{display:grid;gap:12px;grid-column:1 / -1;grid-template-columns:repeat(2,minmax(0,1fr))}.hpc-field-structure-row.is-saving{opacity:.72}.hpc-field-structure-row.is-error{border-color:#ffd0d8}@media(max-width:900px){.hpc-field-structure-intro,.hpc-field-structure-row{grid-template-columns:1fr}.hpc-field-structure-details,.hpc-field-structure-meta{grid-template-columns:1fr}}</style>
<script>(function(){if(window.hexaFieldStructuresReady)return;window.hexaFieldStructuresReady=true;document.addEventListener("change",function(event){var input=event.target.closest(".hpc-field-structure-toggle");if(!input)return;var root=input.closest("[data-hpc-field-structures]");var row=input.closest(".hpc-field-structure-row");var status=row?row.querySelector(".hpc-field-structure-save-status"):null;var key=input.getAttribute("data-setting-key")||"";if(!root||!key||!root.dataset.saveAction)return;var body=new URLSearchParams();body.set("action",root.dataset.saveAction||"");body.set(root.dataset.nonceField||"nonce",root.dataset.nonce||"");body.set(key,input.checked?"1":"0");if(row)row.classList.add("is-saving");if(status)status.textContent="Saving...";fetch(root.dataset.ajaxUrl||window.ajaxurl,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:body.toString()}).then(function(response){return response.json()}).then(function(payload){if(!payload||!payload.success)throw new Error("Save failed");if(status)status.textContent="Saved.";if(row)row.classList.remove("is-error")}).catch(function(){input.checked=!input.checked;if(status)status.textContent="Error saving.";if(row)row.classList.add("is-error")}).finally(function(){if(row)row.classList.remove("is-saving")})})})();</script>
HTML;
    }
}
