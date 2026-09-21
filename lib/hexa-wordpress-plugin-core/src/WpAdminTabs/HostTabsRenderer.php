<?php

namespace Hexa\PluginCore\WpAdminTabs;

use Hexa\PluginCore\WpAdminComponents\CoreUi;

final class HostTabsRenderer {
    public function render( array $args ): void {
        CoreUi::render_assets();

        $tabs      = isset( $args["tabs"] ) && is_array( $args["tabs"] ) ? $args["tabs"] : [];
        $active    = isset( $args["active"] ) ? sanitize_key( (string) $args["active"] ) : "";
        $page_url  = isset( $args["page_url"] ) ? (string) $args["page_url"] : "";
        $ajax_url  = isset( $args["ajax_url"] ) ? (string) $args["ajax_url"] : ( function_exists( "admin_url" ) ? admin_url( "admin-ajax.php" ) : "" );
        $action    = isset( $args["ajax_action"] ) ? sanitize_key( (string) $args["ajax_action"] ) : "";
        $nonce     = isset( $args["nonce"] ) ? (string) $args["nonce"] : "";
        $nonce_key = isset( $args["nonce_field"] ) ? sanitize_key( (string) $args["nonce_field"] ) : "nonce";
        $root_id   = isset( $args["root_id"] ) ? sanitize_html_class( (string) $args["root_id"] ) : "hpc-host-tabs";
        $panel_id  = isset( $args["panel_id"] ) ? sanitize_html_class( (string) $args["panel_id"] ) : "hpc-host-tab-panel";
        $label     = isset( $args["label"] ) ? (string) $args["label"] : "Plugin sections";
        $callback  = $args["render_callback"] ?? null;
        $layout    = isset( $args["layout"] ) ? sanitize_key( (string) $args["layout"] ) : "bar";
        $groups    = isset( $args["groups"] ) && is_array( $args["groups"] ) ? $args["groups"] : [];
        $sidebar_identity = isset( $args["sidebar_identity"] ) && is_array( $args["sidebar_identity"] ) ? $args["sidebar_identity"] : [];

        if ( "" === $root_id ) {
            $root_id = "hpc-host-tabs";
        }
        if ( "" === $panel_id ) {
            $panel_id = "hpc-host-tab-panel";
        }

        if ( "" === $active || ! array_key_exists( $active, $tabs ) ) {
            $keys   = array_keys( $tabs );
            $active = isset( $keys[0] ) ? (string) $keys[0] : "";
        }

        $sidebar             = ( "sidebar" === $layout && [] !== $groups );
        $sidebar_collapsible = $sidebar && ! empty( $args["sidebar_collapsible"] );
        $sidebar_collapsed   = $sidebar_collapsible && ! empty( $args["sidebar_collapsed"] );
        $sidebar_persist     = $sidebar_collapsible
            && ( ! array_key_exists( "sidebar_persist", $args ) || (bool) $args["sidebar_persist"] );
        $rail_id             = $root_id . "-rail";
        $rail_navigation_id  = $root_id . "-rail-navigation";
        $sidebar_storage_key = "hpc-host-sidebar-" . $root_id;

        $panel_html = "";
        if ( is_callable( $callback ) && "" !== $active ) {
            ob_start();
            call_user_func( $callback, $active );
            $panel_html = (string) ob_get_clean();
        }

        $shell_class = "hpc-ui hpc-host-tabs-shell " . ( $sidebar ? "is-sidebar" : "is-bar" );
        if ( $sidebar_collapsible ) {
            $shell_class .= " has-collapsible-sidebar";
        }
        if ( $sidebar_collapsed ) {
            $shell_class .= " is-sidebar-collapsed";
        }

        $status_label  = "" !== $active ? "Loaded " . $this->tab_label( $tabs[ $active ] ?? $active ) . "." : "";
        $active_tab_id = "" !== $active ? $this->tab_dom_id( $root_id, $active ) : "";
        $toggle_label  = $sidebar_collapsed ? "Expand navigation" : "Collapse navigation";
        $toggle_icon   = $sidebar_collapsed ? "dashicons-arrow-right-alt2" : "dashicons-arrow-left-alt2";
        $sidebar_identity_html = $sidebar ? $this->sidebar_identity_html( $sidebar_identity ) : "";
        ?>
        <div id="<?php echo esc_attr( $root_id ); ?>" class="<?php echo esc_attr( $shell_class ); ?>" data-hpc-tab-root data-ajax-url="<?php echo esc_url( $ajax_url ); ?>" data-ajax-action="<?php echo esc_attr( $action ); ?>" data-nonce-field="<?php echo esc_attr( $nonce_key ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-page-url="<?php echo esc_url( $page_url ); ?>" data-panel-id="<?php echo esc_attr( $panel_id ); ?>" data-active-tab="<?php echo esc_attr( $active ); ?>" data-sidebar-collapsible="<?php echo $sidebar_collapsible ? "1" : "0"; ?>" data-sidebar-collapsed="<?php echo $sidebar_collapsed ? "1" : "0"; ?>" data-sidebar-persist="<?php echo $sidebar_persist ? "1" : "0"; ?>" data-sidebar-storage-key="<?php echo esc_attr( $sidebar_storage_key ); ?>">
            <?php if ( $sidebar ) : ?>
                <aside id="<?php echo esc_attr( $rail_id ); ?>" class="hpc-host-rail" aria-label="<?php echo esc_attr( $label ); ?>">
                    <?php if ( "" !== $sidebar_identity_html || $sidebar_collapsible ) : ?>
                        <div class="hpc-host-rail-header">
                            <?php echo $sidebar_identity_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php if ( $sidebar_collapsible ) : ?>
                                <div class="hpc-host-rail-tools">
                                    <button type="button" class="hpc-host-sidebar-toggle" data-hpc-sidebar-toggle aria-controls="<?php echo esc_attr( $rail_navigation_id ); ?>" aria-expanded="<?php echo $sidebar_collapsed ? "false" : "true"; ?>" aria-label="<?php echo esc_attr( $toggle_label ); ?>" title="<?php echo esc_attr( $toggle_label ); ?>">
                                        <span class="dashicons <?php echo esc_attr( $toggle_icon ); ?>" aria-hidden="true"></span>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div id="<?php echo esc_attr( $rail_navigation_id ); ?>" class="hpc-host-rail-navigation" data-hpc-sidebar-navigation<?php echo $sidebar_collapsed ? " hidden" : ""; ?>>
                        <?php echo $this->grouped_nav_html( $groups, $tabs, $page_url, $active, $root_id, $panel_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </aside>
                <main class="hpc-host-main">
                    <div class="hpc-host-tab-status" aria-live="polite"><span class="spinner"></span><span data-hpc-tab-message><?php echo esc_html( $status_label ); ?></span></div>
                    <section id="<?php echo esc_attr( $panel_id ); ?>" class="hpc-host-tab-panel" data-hpc-tab-panel data-active-tab="<?php echo esc_attr( $active ); ?>" role="tabpanel"<?php echo "" !== $active_tab_id ? ' aria-labelledby="' . esc_attr( $active_tab_id ) . '"' : ""; ?> aria-live="polite"><?php echo $panel_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></section>
                </main>
            <?php else : ?>
                <nav class="hpc-host-tabs" role="tablist" aria-label="<?php echo esc_attr( $label ); ?>">
                    <?php foreach ( $tabs as $id => $tab ) {
                        echo $this->tab_link_html( (string) $id, $tab, $page_url, $active, $root_id, $panel_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    } ?>
                </nav>
                <div class="hpc-host-tab-status" aria-live="polite"><span class="spinner"></span><span data-hpc-tab-message><?php echo esc_html( $status_label ); ?></span></div>
                <section id="<?php echo esc_attr( $panel_id ); ?>" class="hpc-host-tab-panel" data-hpc-tab-panel data-active-tab="<?php echo esc_attr( $active ); ?>" role="tabpanel"<?php echo "" !== $active_tab_id ? ' aria-labelledby="' . esc_attr( $active_tab_id ) . '"' : ""; ?> aria-live="polite"><?php echo $panel_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></section>
            <?php endif; ?>
        </div>
        <script>
        (function(){
            var root = document.getElementById("<?php echo esc_js( $root_id ); ?>");
            if (!root || root.dataset.hpcTabsReady === "1") return;
            root.dataset.hpcTabsReady = "1";
            var panel = document.getElementById(root.dataset.panelId || "");
            var status = root.querySelector(".hpc-host-tab-status");
            var spinner = status ? status.querySelector(".spinner") : null;
            var message = status ? status.querySelector("[data-hpc-tab-message]") : null;
            var sidebarToggle = root.querySelector("[data-hpc-sidebar-toggle]");
            var sidebarNavigation = root.querySelector("[data-hpc-sidebar-navigation]");
            function tabs(){ return Array.prototype.slice.call(root.querySelectorAll("[data-hpc-host-tab]")); }
            function setMessage(text){ if (message) message.textContent = text || ""; }
            function setLoading(on){ if (!panel) return; panel.classList.toggle("is-loading", !!on); panel.setAttribute("aria-busy", on ? "true" : "false"); if (spinner) spinner.classList.toggle("is-active", !!on); }
            function eventName(name){ return "hexa-core-host-tab-" + name; }
            function dispatch(name, detail){ detail = detail || {}; detail.root = root; detail.panel = panel; root.dispatchEvent(new CustomEvent(eventName(name), { bubbles: true, detail: detail })); document.dispatchEvent(new CustomEvent(eventName(name), { detail: detail })); }
            function setActive(tab){
                tabs().forEach(function(item){
                    var on = item.getAttribute("data-hpc-host-tab") === tab;
                    item.classList.toggle("active", on);
                    item.setAttribute("aria-selected", on ? "true" : "false");
                });
                root.dataset.activeTab = tab;
                if (panel) {
                    panel.setAttribute("data-active-tab", tab);
                    panel.setAttribute("aria-labelledby", root.id + "-tab-" + tab);
                }
            }
            function setSidebarCollapsed(collapsed, persist, announce){
                if (root.dataset.sidebarCollapsible !== "1") return;
                collapsed = !!collapsed;
                root.classList.toggle("is-sidebar-collapsed", collapsed);
                root.dataset.sidebarCollapsed = collapsed ? "1" : "0";
                if (sidebarNavigation) sidebarNavigation.hidden = collapsed;
                if (sidebarToggle) {
                    var label = collapsed ? "Expand navigation" : "Collapse navigation";
                    var icon = sidebarToggle.querySelector(".dashicons");
                    sidebarToggle.setAttribute("aria-expanded", collapsed ? "false" : "true");
                    sidebarToggle.setAttribute("aria-label", label);
                    sidebarToggle.setAttribute("title", label);
                    if (icon) {
                        icon.classList.toggle("dashicons-arrow-left-alt2", !collapsed);
                        icon.classList.toggle("dashicons-arrow-right-alt2", collapsed);
                    }
                }
                if (persist && root.dataset.sidebarPersist === "1") {
                    try {
                        if (window.localStorage) window.localStorage.setItem(root.dataset.sidebarStorageKey || "", collapsed ? "1" : "0");
                    } catch (e) {}
                }
                if (announce) dispatch("sidebar-state", { collapsed: collapsed });
            }
            function restoreSidebar(){
                if (root.dataset.sidebarCollapsible !== "1") return;
                var collapsed = root.dataset.sidebarCollapsed === "1";
                if (root.dataset.sidebarPersist === "1") {
                    try {
                        var stored = window.localStorage ? window.localStorage.getItem(root.dataset.sidebarStorageKey || "") : null;
                        if (stored === "1") collapsed = true;
                        if (stored === "0") collapsed = false;
                    } catch (e) {}
                }
                setSidebarCollapsed(collapsed, false, false);
            }
            function tabUrl(tab){ var base = root.dataset.pageUrl || window.location.href; try { var u = new URL(base, window.location.origin); u.searchParams.set("tab", tab); return u.toString(); } catch(e) { return base + (base.indexOf("?") === -1 ? "?" : "&") + "tab=" + encodeURIComponent(tab); } }
            function runScripts(container){
                if (!container || !container.querySelectorAll) return;
                Array.prototype.slice.call(container.querySelectorAll("script")).forEach(function(source){
                    var script = document.createElement("script");
                    Array.prototype.slice.call(source.attributes || []).forEach(function(attr){ script.setAttribute(attr.name, attr.value); });
                    script.text = source.textContent || "";
                    if (source.parentNode) source.parentNode.replaceChild(script, source);
                    else document.head.appendChild(script).parentNode.removeChild(script);
                });
            }
            function load(tab, href, push){
                if (!tab || !panel || panel.dataset.loading === "1") return;
                panel.dataset.loading = "1";
                setLoading(true);
                setMessage("Loading...");
                dispatch("before-load", { tab: tab });
                var body = new URLSearchParams();
                body.set("action", root.dataset.ajaxAction || "");
                body.set(root.dataset.nonceField || "nonce", root.dataset.nonce || "");
                body.set("tab", tab);
                fetch(root.dataset.ajaxUrl || ajaxurl, { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" }, body: body.toString() })
                    .then(function(response){ return response.json(); })
                    .then(function(payload){
                        if (!payload || !payload.success || !payload.data) throw new Error("Tab response failed.");
                        panel.innerHTML = payload.data.html || "";
                        runScripts(panel);
                        setActive(payload.data.tab || tab);
                        setMessage("Loaded " + (payload.data.label || payload.data.tab || tab) + ".");
                        if (panel.scrollIntoView) { var r = panel.getBoundingClientRect(); if (r.top < 0) panel.scrollIntoView({ block: "start" }); }
                        if (push !== false && window.history && history.pushState) history.pushState({ hpcHostTab: payload.data.tab || tab }, "", href || tabUrl(payload.data.tab || tab));
                        dispatch("loaded", { tab: payload.data.tab || tab, label: payload.data.label || "", html: payload.data.html || "" });
                    })
                    .catch(function(){ setMessage("Error loading tab."); dispatch("error", { tab: tab }); })
                    .finally(function(){ delete panel.dataset.loading; setLoading(false); });
            }
            restoreSidebar();
            if (sidebarToggle) {
                sidebarToggle.addEventListener("click", function(){
                    setSidebarCollapsed(root.dataset.sidebarCollapsed !== "1", true, true);
                });
            }
            tabs().forEach(function(tab){ tab.addEventListener("click", function(event){ var id = tab.getAttribute("data-hpc-host-tab"); if (!id) return; event.preventDefault(); if (id === root.dataset.activeTab) return; load(id, tab.getAttribute("href"), true); }); });
            window.addEventListener("popstate", function(){ var params = new URLSearchParams(window.location.search); var tab = params.get("tab") || root.dataset.activeTab || ""; load(tab, tabUrl(tab), false); });
        })();
        </script>

        <?php
    }

    /**
     * @param array<string,mixed> $identity
     */
    private function sidebar_identity_html( array $identity ): string {
        $plugin_name     = trim( (string) ( $identity["plugin_name"] ?? "" ) );
        $current_version = trim( (string) ( $identity["current_version"] ?? "" ) );
        $github_version  = trim( (string) ( $identity["github_version"] ?? "" ) );
        $github_url      = trim( (string) ( $identity["github_url"] ?? "" ) );
        $core_name       = trim( (string) ( $identity["core_name"] ?? "" ) );
        $core_version    = trim( (string) ( $identity["core_version"] ?? "" ) );
        $core_github_url = trim( (string) ( $identity["core_github_url"] ?? "" ) );

        if ( "" === $plugin_name && "" === $current_version && "" === $github_version && "" === $core_name && "" === $core_version ) {
            return "";
        }

        $version_parts = [];
        if ( "" !== $current_version ) {
            $version_parts[] = "<span>Current " . esc_html( $current_version ) . "</span>";
        }
        if ( "" !== $github_version ) {
            $github_label = "GitHub " . esc_html( $github_version );
            $version_parts[] = "" !== $github_url
                ? "<a href=\"" . esc_url( $github_url ) . "\" target=\"_blank\" rel=\"noopener noreferrer\">" . $github_label . "</a>"
                : "<span>" . $github_label . "</span>";
        }

        $core_label = trim( $core_name . ( "" !== $core_version ? " " . $core_version : "" ) );
        $core_html  = "";
        if ( "" !== $core_label ) {
            $core_html = "" !== $core_github_url
                ? "<a class=\"hpc-host-rail-core\" href=\"" . esc_url( $core_github_url ) . "\" target=\"_blank\" rel=\"noopener noreferrer\">" . esc_html( $core_label ) . "</a>"
                : "<span class=\"hpc-host-rail-core\">" . esc_html( $core_label ) . "</span>";
        }

        $html = "<div class=\"hpc-host-rail-identity\">";
        if ( "" !== $plugin_name ) {
            $html .= "<strong class=\"hpc-host-rail-plugin-name\">" . esc_html( $plugin_name ) . "</strong>";
        }
        if ( [] !== $version_parts ) {
            $html .= "<div class=\"hpc-host-rail-versions\">" . implode( "<span class=\"hpc-host-rail-version-separator\" aria-hidden=\"true\"> - </span>", $version_parts ) . "</div>";
        }
        $html .= $core_html . "</div>";

        return $html;
    }

    /**
     * @param array<int,array<string,mixed>> $groups
     * @param array<string,mixed>            $tabs
     */
    private function grouped_nav_html( array $groups, array $tabs, string $page_url, string $active, string $root_id, string $panel_id ): string {
        $html     = "";
        $rendered = [];

        foreach ( $groups as $group ) {
            if ( ! is_array( $group ) ) {
                continue;
            }

            $group_label = isset( $group["label"] ) ? (string) $group["label"] : "";
            $group_ids   = isset( $group["tabs"] ) && is_array( $group["tabs"] ) ? $group["tabs"] : [];
            $links       = "";

            foreach ( $group_ids as $id ) {
                $id = sanitize_key( (string) $id );
                if ( "" === $id || ! array_key_exists( $id, $tabs ) || isset( $rendered[ $id ] ) ) {
                    continue;
                }
                $rendered[ $id ] = true;
                $links          .= $this->tab_link_html( $id, $tabs[ $id ], $page_url, $active, $root_id, $panel_id );
            }

            if ( "" === $links ) {
                continue;
            }

            $nav_label = "" !== $group_label ? $group_label : "Plugin sections";
            $html     .= '<div class="hpc-host-rail-group">'
                . ( "" !== $group_label ? '<p class="hpc-host-rail-title">' . esc_html( $group_label ) . '</p>' : "" )
                . '<nav class="hpc-host-tabs" role="tablist" aria-label="' . esc_attr( $nav_label ) . '">' . $links . '</nav>'
                . '</div>';
        }

        // Any tab not assigned to a group is still shown so nothing silently disappears.
        $leftover = "";
        foreach ( $tabs as $id => $tab ) {
            $id = sanitize_key( (string) $id );
            if ( "" === $id || isset( $rendered[ $id ] ) ) {
                continue;
            }
            $leftover .= $this->tab_link_html( $id, $tab, $page_url, $active, $root_id, $panel_id );
        }

        if ( "" !== $leftover ) {
            $html .= '<div class="hpc-host-rail-group"><p class="hpc-host-rail-title">More</p><nav class="hpc-host-tabs" role="tablist" aria-label="More">' . $leftover . '</nav></div>';
        }

        return $html;
    }

    private function tab_link_html( string $id, mixed $tab, string $page_url, string $active, string $root_id, string $panel_id ): string {
        $id     = sanitize_key( $id );
        $text   = $this->tab_label( $tab );
        $url    = $this->tab_url( $page_url, $id );
        $is_on  = $id === $active;
        $dom_id = $this->tab_dom_id( $root_id, $id );

        return '<a id="' . esc_attr( $dom_id ) . '" class="hpc-host-tab' . ( $is_on ? " active" : "" ) . '" href="' . esc_url( $url ) . '" data-hpc-host-tab="' . esc_attr( $id ) . '" role="tab" aria-controls="' . esc_attr( $panel_id ) . '" aria-selected="' . ( $is_on ? "true" : "false" ) . '">' . esc_html( $text ) . '</a>';
    }

    private function tab_dom_id( string $root_id, string $tab_id ): string {
        return sanitize_html_class( $root_id . "-tab-" . sanitize_key( $tab_id ) );
    }

    private function tab_label( mixed $tab ): string {
        if ( $tab instanceof TabDefinition ) {
            return $tab->label . ( $tab->deprecated ? " (Deprecated)" : "" );
        }

        if ( is_array( $tab ) && isset( $tab["label"] ) ) {
            return (string) $tab["label"];
        }

        return (string) $tab;
    }

    private function tab_url( string $page_url, string $tab ): string {
        if ( function_exists( "add_query_arg" ) ) {
            return add_query_arg( "tab", $tab, $page_url );
        }

        return $page_url . ( str_contains( $page_url, "?" ) ? "&" : "?" ) . "tab=" . rawurlencode( $tab );
    }
}
