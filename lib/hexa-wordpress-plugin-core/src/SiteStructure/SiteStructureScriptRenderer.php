<?php

namespace Hexa\PluginCore\SiteStructure;

final class SiteStructureScriptRenderer {
    /**
     * @param array<string,mixed> $payload
     */
    public static function render( string $instance_id, array $payload ): string {
        ob_start();
        ?>
        <script>
        jQuery(function($) {
            var $root = $("#<?php echo esc_js( $instance_id ); ?>");
            if (!$root.length) return;
            var cfg = <?php echo wp_json_encode( $payload ); ?>;

            function message(response, fallback) {
                if (!response || typeof response.data === "undefined") return fallback;
                if (typeof response.data === "string") return response.data;
                if (response.data && response.data.message) return response.data.message;
                return fallback;
            }

            function toast(messageText, type) {
                var bgColor = type === "success" ? "#dcfce7" : "#fef2f2";
                var borderColor = type === "success" ? "#16a34a" : "#dc2626";
                var $toast = $('<div style="position:fixed;top:50px;right:20px;z-index:9999;padding:12px 20px;background:' + bgColor + ';border:1px solid ' + borderColor + ';border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.15);"><p style="margin:0;"></p></div>');
                $toast.find("p").text(messageText);
                $("body").append($toast);
                setTimeout(function() { $toast.fadeOut(function() { $(this).remove(); }); }, 3000);
            }

            function statusBadge(success, text) {
                var bg = success ? "#dcfce7" : "#fee2e2";
                var color = success ? "#166534" : "#991b1b";
                var icon = success ? "✓" : "×";
                return '<span style="display:inline-block;background:' + bg + ';color:' + color + ';padding:4px 10px;border-radius:4px;font-size:12px;font-weight:500;">' + icon + ' ' + text + '</span>';
            }

            function post(actionName, data, done, fail) {
                data = data || {};
                if (!cfg.actions[actionName]) {
                    toast("Action is not configured: " + actionName, "error");
                    if (typeof fail === "function") fail();
                    return;
                }
                data.action = cfg.actions[actionName];
                data.nonce = cfg.nonce;
                $.post(ajaxurl, data, done).fail(fail || function() {
                    toast("Request failed.", "error");
                });
            }

            function actionButtons(pageKey, pageId, editUrl, viewUrl) {
                var html = '<a href="' + editUrl + '" target="_blank" rel="noopener" class="button button-small">Edit</a> ';
                html += '<a href="' + viewUrl + '" target="_blank" rel="noopener" class="button button-small">View</a> ';
                if (!cfg.lazyPageWorkspace && cfg.enableTemplates && (cfg.actions.apply_template || cfg.applyTemplateAction)) {
                    html += '<button type="button" class="button button-small hpc-apply-page-template" data-page-id="' + pageId + '" data-page-key="' + pageKey + '">Apply Template</button> ';
                }
                if (cfg.lazyPageWorkspace) {
                    html += '<button type="button" class="button button-small hpc-open-page-workspace" data-page-id="' + pageId + '" data-page-key="' + pageKey + '">Manage</button> ';
                }
                html += '<button type="button" class="button button-small hpc-delete-page" data-page="' + pageKey + '" data-page-id="' + pageId + '" style="color:#dc2626;border-color:#fca5a5;">Delete</button>';
                return html;
            }

            function createButton($row) {
                var pageKey = $row.data("page-key");
                var title = $row.data("page-title");
                var slug = $row.data("page-slug");
                var parentKey = $row.data("parent-key") || "";
                var html = '<button type="button" class="button button-small button-primary hpc-create-page" data-page="' + pageKey + '" data-title="' + title + '" data-slug="' + slug + '"';
                if (parentKey) html += ' data-parent="' + parentKey + '"';
                html += '>+ Create</button>';
                if (cfg.lazyPageWorkspace) html += ' <button type="button" class="button button-small hpc-open-page-workspace" data-page-id="0" data-page-key="' + pageKey + '">Manage</button>';
                return html;
            }

            function filterParentItems($select, menuId) {
                $select.find("option").each(function() {
                    var optionMenuId = String($(this).data("menu-id") || "0");
                    var isTop = optionMenuId === "0";
                    $(this).toggle(isTop || optionMenuId === String(menuId));
                });
                if (!$select.find("option:selected").is(":visible")) {
                    $select.val("0");
                }
            }

            function setStatus($el, text, success) {
                $el.text(text).css("color", success ? "#059669" : "#dc2626");
            }


            function originalText($btn) {
                if (!$btn.data("hpc-original-text")) {
                    $btn.data("hpc-original-text", $btn.text());
                }
                return $btn.data("hpc-original-text");
            }

            function escapeHtml(value) {
                return String(value || "").replace(/[&<>"']/g, function(chr) {
                    return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[chr] || chr;
                });
            }

            function parentOptions(menus) {
                var html = '<option value="0" data-menu-id="0">Top level</option>';
                $.each(menus || [], function(_, row) {
                    var menu = row.menu || {};
                    $.each(row.items || [], function(__, item) {
                        html += '<option value="' + escapeHtml(item.id) + '" data-menu-id="' + escapeHtml(menu.id) + '">' + escapeHtml((menu.name || "") + ': ' + (item.label || item.title || "")) + '</option>';
                    });
                });
                return html;
            }

            function menuOptions(menus) {
                if (!menus || !menus.length) return '<option value="">No menus found</option>';
                var html = "";
                $.each(menus, function(_, row) {
                    var menu = row.menu || {};
                    html += '<option value="' + escapeHtml(menu.id) + '">' + escapeHtml(menu.name || "") + '</option>';
                });
                return html;
            }

            function inventoryTable(menus) {
                if (!menus || !menus.length) {
                    return '<p class="hpc-menu-inventory-empty" style="color:#666;margin:0;">No WordPress menus exist yet. Create the required menus above.</p>';
                }
                var tableClass = escapeHtml(cfg.tableClass || "widefat striped");
                var html = '<table class="' + tableClass + ' hpc-menu-inventory-table"><thead><tr><th style="width:24%;">Menu</th><th>Items</th><th style="width:20%;">Actions</th></tr></thead><tbody>';
                $.each(menus, function(_, row) {
                    var menu = row.menu || {};
                    html += '<tr><td><strong>' + escapeHtml(menu.name || "") + '</strong><div><code>' + escapeHtml(menu.slug || "") + '</code></div></td><td>';
                    if (!row.items || !row.items.length) {
                        html += '<span style="color:#6b7280;">No items</span>';
                    } else {
                        $.each(row.items, function(__, item) {
                            html += '<div style="margin-bottom:3px;"><code>#' + escapeHtml(item.id) + '</code> ' + escapeHtml(item.label || item.title || "") + '</div>';
                        });
                    }
                    html += '</td><td><a class="button button-small" href="' + escapeHtml(menu.edit_url || "#") + '" target="_blank" rel="noopener">Edit</a> ';
                    html += '<button type="button" class="button button-small hpc-delete-navigation-menu" data-menu-id="' + escapeHtml(menu.id) + '" data-menu-name="' + escapeHtml(menu.name || "") + '">Delete</button></td></tr>';
                });
                html += '</tbody></table>';
                return html;
            }

            function renderMenuInventory(data) {
                var menus = data && data.menus ? data.menus : [];
                var hasMenus = menus.length > 0;
                $root.find(".hpc-menu-inventory-wrap").html(inventoryTable(menus));
                $root.find(".hpc-custom-item-menu,.hpc-attach-menu,.hpc-structure-menu").each(function() {
                    var $select = $(this);
                    var oldValue = $select.val();
                    $select.html(menuOptions(menus));
                    if (oldValue && $select.find('option[value="' + oldValue + '"]').length) $select.val(oldValue);
                    $select.prop("disabled", !hasMenus);
                });
                $root.find(".hpc-custom-item-parent,.hpc-attach-parent-item,.hpc-structure-parent").each(function() {
                    var $select = $(this);
                    var oldValue = $select.val() || "0";
                    $select.html(parentOptions(menus));
                    if ($select.find('option[value="' + oldValue + '"]').length) $select.val(oldValue);
                    $select.prop("disabled", !hasMenus);
                });
                $root.find(".hpc-create-menu-item,.hpc-attach-menu-structure,.hpc-attach-page-to-menu-item,.hpc-custom-item-title,.hpc-custom-item-url").prop("disabled", !hasMenus);
                $root.find(".hpc-attach-menu,.hpc-custom-item-menu,.hpc-structure-menu").trigger("change");
            }

            function refreshMenuInventory($status, successText, done) {
                post("menu_inventory", {}, function(response) {
                    if (response.success) {
                        renderMenuInventory(response.data || {});
                        if ($status && $status.length) setStatus($status, successText || message(response, "Menu items updated."), true);
                    } else if ($status && $status.length) {
                        setStatus($status, message(response, "Menu inventory refresh failed."), false);
                    }
                    if (typeof done === "function") done();
                }, function() {
                    if ($status && $status.length) setStatus($status, "Menu inventory refresh failed.", false);
                    if (typeof done === "function") done();
                });
            }

            function editorContent(editorId) {
                if (window.tinyMCE && window.tinyMCE.get(editorId)) {
                    return window.tinyMCE.get(editorId).getContent();
                }

                return $("#" + editorId).val() || "";
            }

            function setEditorContent(editorId, content) {
                content = content || "";
                var $textarea = $("#" + editorId);
                $textarea.val(content);
                if (window.tinyMCE && window.tinyMCE.get(editorId)) {
                    window.tinyMCE.get(editorId).setContent(content);
                }
            }

            function pageWorkspace() {
                return $root.find("[data-hpc-page-workspace]").first();
            }

            function openPageWorkspace(pageKey, pageId) {
                var $workspace = pageWorkspace();
                if (!$workspace.length || !cfg.actions.page_workspace) return;
                var $loading = $workspace.find("[data-hpc-workspace-loading]");
                var $body = $workspace.find("[data-hpc-workspace-body]");
                $workspace.prop("hidden", false).attr("data-page-key", pageKey).attr("data-page-id", pageId || 0);
                $loading.css("display", "flex");
                $body.hide();
                $workspace.find("[data-hpc-workspace-title]").text("Page tools");
                $workspace.find("[data-hpc-workspace-meta]").text("Loading " + pageKey + "...");
                post("page_workspace", {page_key:pageKey, page_id:pageId || 0}, function(response) {
                    $loading.hide();
                    if (!response.success) {
                        $workspace.find("[data-hpc-workspace-meta]").text(message(response, "Page tools failed to load."));
                        return;
                    }
                    var data = response.data || {};
                    var editorId = $workspace.find(".hpc-page-workspace-editor").data("editor-id");
                    $workspace.attr("data-page-id", data.page_id || 0);
                    $workspace.find("[data-hpc-workspace-title]").text(data.title || pageKey);
                    $workspace.find("[data-hpc-workspace-meta]").text((data.assigned ? "Assigned page" : "Unassigned page type") + " | " + (data.slug || pageKey));
                    $workspace.find("[data-hpc-workspace-detail]").html(data.detail_html || '<p style="color:#646970;margin:0;">Assign or create this page to load page details.</p>');
                    if (editorId) setEditorContent(editorId, data.template || "");
                    $workspace.find(".hpc-apply-workspace-template").prop("disabled", !data.assigned);
                    $workspace.find(".hpc-workspace-status").text("");
                    $body.show();
                    if ($workspace[0] && $workspace[0].scrollIntoView) $workspace[0].scrollIntoView({behavior:"smooth", block:"nearest"});
                }, function() {
                    $loading.hide();
                    $workspace.find("[data-hpc-workspace-meta]").text("Page tools request failed.");
                });
            }

            function detailRow($row) {
                return $row.nextAll(".hpc-site-page-detail-row").first();
            }

            function setPageDetail($row, html) {
                var $detail = detailRow($row);
                if (!$detail.length) return;
                html = html || "";
                $detail.find(".hpc-page-detail-wrap").html(html);
                if (html) {
                    $detail.show();
                } else {
                    $detail.hide();
                }
            }

            function updatePageSelectLabel($row, pageId, title) {
                var $select = $row.find(".hpc-site-page-select");
                var $option = $select.find('option[value="' + pageId + '"]');
                if ($option.length && title) $option.text(title);
            }

            function appendMenuOption(menuId, name) {
                menuId = parseInt(menuId, 10) || 0;
                name = name || "";
                if (!menuId || !name) return;
                var selectors = ".hpc-custom-item-menu,.hpc-attach-menu,.hpc-structure-menu";
                $root.find(selectors).each(function() {
                    var $select = $(this);
                    if (!$select.find("option[value=\"" + menuId + "\"]").length) {
                        $select.append($("<option/>").val(menuId).text(name));
                    }
                    $select.prop("disabled", false);
                });
                $root.find(".hpc-custom-item-parent,.hpc-attach-parent-item,.hpc-structure-parent,.hpc-create-menu-item,.hpc-attach-menu-structure,.hpc-custom-item-title,.hpc-custom-item-url").prop("disabled", false);
            }

            $root.find(".hpc-structure-menu").each(function() {
                var $card = $(this).closest(".hpc-menu-structure-card");
                filterParentItems($card.find(".hpc-structure-parent"), $(this).val());
            });

            $root.find(".hpc-attach-menu").on("change", function() {
                filterParentItems($root.find(".hpc-attach-parent-item"), $(this).val());
            }).trigger("change");

            $root.find(".hpc-custom-item-menu").on("change", function() {
                filterParentItems($root.find(".hpc-custom-item-parent"), $(this).val());
            }).trigger("change");

            $root.on("change", ".hpc-structure-menu", function() {
                var $card = $(this).closest(".hpc-menu-structure-card");
                filterParentItems($card.find(".hpc-structure-parent"), $(this).val());
            });

            $root.on("change", ".hpc-site-page-select", function() {
                var $select = $(this);
                var $row = $select.closest(".hpc-site-page-row");
                var pageId = $select.val();
                $select.prop("disabled", true);
                post("assign_page", {
                    page_key: $select.data("page"),
                    page_id: pageId,
                    parent_key: $select.data("parent") || ""
                }, function(response) {
                    $select.prop("disabled", false);
                    if (response.success) {
                        if (pageId) {
                            var data = response.data || {};
                            var editUrl = data.edit_url || (cfg.adminPostBase + pageId + "&action=edit");
                            var viewUrl = data.permalink || "#";
                            $row.find(".hpc-site-page-status").html(statusBadge(true, "Set"));
                            $row.find(".hpc-site-page-actions").html(actionButtons($select.data("page"), pageId, editUrl, viewUrl));
                            setPageDetail($row, data.detail_html || "");
                        } else {
                            $row.find(".hpc-site-page-status").html(statusBadge(false, "Not Set"));
                            $row.find(".hpc-site-page-actions").html(createButton($row));
                            setPageDetail($row, "");
                        }
                        toast("Page assignment saved.", "success");
                    } else {
                        toast(message(response, "Failed to assign page."), "error");
                    }
                }, function() {
                    $select.prop("disabled", false);
                    toast("Page assignment request failed.", "error");
                });
            });

            $root.on("click", ".hpc-create-page", function() {
                var $btn = $(this);
                var $row = $btn.closest(".hpc-site-page-row");
                var pageKey = $btn.data("page");
                var title = $btn.data("title");
                $btn.prop("disabled", true).text("Creating...");
                post("create_page", {
                    page_key: pageKey,
                    title: title,
                    slug: $btn.data("slug"),
                    parent_key: $btn.data("parent") || ""
                }, function(response) {
                    if (response.success) {
                        var data = response.data || {};
                        var pageId = data.page_id;
                        var editUrl = data.edit_url || (cfg.adminPostBase + pageId + "&action=edit");
                        var viewUrl = data.permalink || "#";
                        var $select = $row.find(".hpc-site-page-select");
                        if (!$select.find('option[value="' + pageId + '"]').length) {
                            $select.append($("<option/>").val(pageId).text(data.title || title));
                        }
                        $select.val(pageId);
                        $row.find(".hpc-site-page-status").html(statusBadge(true, "Set"));
                        $row.find(".hpc-site-page-actions").html(actionButtons(pageKey, pageId, editUrl, viewUrl));
                        setPageDetail($row, data.detail_html || "");
                        toast("Page created: " + (data.title || title), "success");
                    } else {
                        toast(message(response, "Failed to create page."), "error");
                        $btn.prop("disabled", false).text("+ Create");
                    }
                }, function() {
                    toast("Page creation request failed.", "error");
                    $btn.prop("disabled", false).text("+ Create");
                });
            });

            $root.on("click", ".hpc-delete-page", function() {
                var $btn = $(this);
                var $row = $btn.closest(".hpc-site-page-row");
                if (!confirm("Delete this managed page? External pages will only be unassigned.")) return;
                $btn.prop("disabled", true).text("Deleting...");
                post("delete_page", {
                    page_key: $btn.data("page"),
                    page_id: $btn.data("page-id")
                }, function(response) {
                    if (response.success) {
                        $row.find(".hpc-site-page-select").val("");
                        $row.find(".hpc-site-page-status").html(statusBadge(false, "Not Set"));
                        $row.find(".hpc-site-page-actions").html(createButton($row));
                        setPageDetail($row, "");
                        toast(message(response, "Page deleted or unassigned."), "success");
                    } else {
                        toast(message(response, "Failed to delete page."), "error");
                        $btn.prop("disabled", false).text("Delete");
                    }
                }, function() {
                    toast("Page deletion request failed.", "error");
                    $btn.prop("disabled", false).text("Delete");
                });
            });

            $root.on("click", ".hpc-create-navigation-menu", function() {
                var $btn = $(this);
                var $status = $root.find(".hpc-create-menu-status");
                var menuName = $root.find(".hpc-new-menu-name").val();
                if (!menuName) {
                    setStatus($status, "Enter a menu name.", false);
                    return;
                }
                originalText($btn);
                $btn.prop("disabled", true).text("Creating...");
                post("create_navigation_menu", { menu_name: menuName }, function(response) {
                    if (response.success) {
                        var data = response.data || {};
                        appendMenuOption(data.menu_id, data.name || menuName);
                        $root.find(".hpc-new-menu-name").val("");
                        refreshMenuInventory($status, "Created menu: " + (data.name || menuName), function() {
                            $btn.prop("disabled", false).text(originalText($btn));
                        });
                    } else {
                        setStatus($status, message(response, "Menu creation failed."), false);
                        $btn.prop("disabled", false).text("Create Menu");
                    }
                }, function() {
                    setStatus($status, "Menu creation request failed.", false);
                    $btn.prop("disabled", false).text("Create Menu");
                });
            });

            $root.on("click", ".hpc-delete-navigation-menu", function() {
                var $btn = $(this);
                if (!confirm("Delete the " + $btn.data("menu-name") + " menu?")) return;
                $btn.prop("disabled", true).text("Deleting...");
                post("delete_navigation_menu", { menu_id: $btn.data("menu-id") }, function(response) {
                    if (response.success) {
                        toast("Menu deleted.", "success");
                        refreshMenuInventory($root.find(".hpc-create-menu-status"), "Menu deleted.");
                    } else {
                        toast(message(response, "Menu deletion failed."), "error");
                        $btn.prop("disabled", false).text("Delete");
                    }
                }, function() {
                    toast("Menu deletion request failed.", "error");
                    $btn.prop("disabled", false).text("Delete");
                });
            });

            $root.on("click", ".hpc-create-menu-item", function() {
                var $btn = $(this);
                var $status = $root.find(".hpc-create-menu-item-status");
                var menuId = $root.find(".hpc-custom-item-menu").val();
                var title = $root.find(".hpc-custom-item-title").val();
                var url = $root.find(".hpc-custom-item-url").val();
                if (!menuId || !title || !url) {
                    setStatus($status, "Select a menu, label, and URL.", false);
                    return;
                }
                originalText($btn);
                $btn.prop("disabled", true).text("Creating...");
                post("create_menu_item", {
                    menu_id: menuId,
                    parent_item_id: $root.find(".hpc-custom-item-parent").val() || "0",
                    title: title,
                    url: url
                }, function(response) {
                    if (response.success) {
                        $root.find(".hpc-custom-item-title,.hpc-custom-item-url").val("");
                        refreshMenuInventory($status, message(response, "Menu item created."), function() {
                            $btn.prop("disabled", false).text(originalText($btn));
                        });
                    } else {
                        setStatus($status, message(response, "Menu item creation failed."), false);
                        $btn.prop("disabled", false).text("Create Menu Item");
                    }
                }, function() {
                    setStatus($status, "Menu item creation request failed.", false);
                    $btn.prop("disabled", false).text("Create Menu Item");
                });
            });

            $root.on("click", ".hpc-attach-menu-structure", function() {
                var $btn = $(this);
                var $card = $btn.closest(".hpc-menu-structure-card");
                var $status = $card.find(".hpc-structure-status");
                var menuId = $card.find(".hpc-structure-menu").val();
                if (!menuId) {
                    setStatus($status, "Select a menu first.", false);
                    return;
                }
                originalText($btn);
                $btn.prop("disabled", true).text("Attaching...");
                post("attach_menu_structure", {
                    menu_id: menuId,
                    parent_item_id: $card.find(".hpc-structure-parent").val() || "0",
                    structure: $card.data("structure")
                }, function(response) {
                    if (response.success) {
                        refreshMenuInventory($status, message(response, "Structure attached."), function() {
                            $btn.prop("disabled", false).text(originalText($btn));
                        });
                    } else {
                        setStatus($status, message(response, "Structure attach failed."), false);
                        $btn.prop("disabled", false).text("Attach " + $card.find("h4").first().text());
                    }
                }, function() {
                    setStatus($status, "Structure attach request failed.", false);
                    $btn.prop("disabled", false).text("Attach " + $card.find("h4").first().text());
                });
            });

            $root.on("click", ".hpc-attach-page-to-menu-item", function() {
                var $btn = $(this);
                var $status = $root.find(".hpc-attach-page-status");
                var menuId = $root.find(".hpc-attach-menu").val();
                var pageKey = $root.find(".hpc-attach-page-key").val();
                if (!menuId || !pageKey) {
                    setStatus($status, "Select a menu and assigned page.", false);
                    return;
                }
                originalText($btn);
                $btn.prop("disabled", true).text("Attaching...");
                post("attach_page_to_menu_item", {
                    menu_id: menuId,
                    parent_item_id: $root.find(".hpc-attach-parent-item").val() || "0",
                    page_key: pageKey
                }, function(response) {
                    if (response.success) {
                        refreshMenuInventory($status, message(response, "Page attached."), function() {
                            $btn.prop("disabled", false).text(originalText($btn));
                        });
                    } else {
                        setStatus($status, message(response, "Page attach failed."), false);
                        $btn.prop("disabled", false).text("Attach Page");
                    }
                }, function() {
                    setStatus($status, "Page attach request failed.", false);
                    $btn.prop("disabled", false).text("Attach Page");
                });
            });

            $root.on("click", ".hpc-save-page-template", function() {
                var $btn = $(this);
                var $wrap = $btn.closest(".hpc-template-editor");
                var $status = $wrap.find(".hpc-template-status");
                var editorId = $wrap.data("editor-id");
                $btn.prop("disabled", true).text("Saving...");
                setStatus($status, "Saving template...", true);
                post("save_template", {
                    page_key: $btn.data("page-key"),
                    template: editorContent(editorId)
                }, function(response) {
                    if (response.success) {
                        setStatus($status, message(response, "Template saved."), true);
                    } else {
                        setStatus($status, message(response, "Template save failed."), false);
                    }
                    $btn.prop("disabled", false).text("Save Template");
                }, function() {
                    setStatus($status, "Template save request failed.", false);
                    $btn.prop("disabled", false).text("Save Template");
                });
            });

            $root.on("click", ".hpc-open-page-workspace", function() {
                var $btn = $(this);
                openPageWorkspace(String($btn.data("page-key") || ""), parseInt($btn.data("page-id"), 10) || 0);
            });

            $root.on("click", ".hpc-close-page-workspace", function() {
                pageWorkspace().prop("hidden", true).removeAttr("data-page-key data-page-id");
            });

            $root.on("click", ".hpc-save-workspace-template", function() {
                var $btn = $(this);
                var $workspace = pageWorkspace();
                var $status = $workspace.find(".hpc-workspace-status");
                var editorId = $workspace.find(".hpc-page-workspace-editor").data("editor-id");
                $btn.prop("disabled", true).text("Saving...");
                setStatus($status, "Saving template...", true);
                post("save_template", {
                    page_key: $workspace.attr("data-page-key") || "",
                    template: editorContent(editorId)
                }, function(response) {
                    setStatus($status, message(response, response.success ? "Template saved." : "Template save failed."), !!response.success);
                    $btn.prop("disabled", false).text("Save Template");
                }, function() {
                    setStatus($status, "Template save request failed.", false);
                    $btn.prop("disabled", false).text("Save Template");
                });
            });

            $root.on("click", ".hpc-apply-workspace-template", function() {
                var $btn = $(this);
                var $workspace = pageWorkspace();
                var pageId = parseInt($workspace.attr("data-page-id"), 10) || 0;
                var pageKey = $workspace.attr("data-page-key") || "";
                if (!pageId || !pageKey) return;
                $btn.prop("disabled", true).text("Applying...");
                postTemplate(pageId, pageKey, "false", $btn);
            });

            $root.on("click", ".hpc-apply-page-template", function(e) {
                e.preventDefault();
                if (!cfg.enableTemplates || (!cfg.actions.apply_template && !cfg.applyTemplateAction)) return;
                var $btn = $(this);
                var pageId = $btn.data("page-id");
                var pageKey = $btn.data("page-key");
                $btn.prop("disabled", true).text("Applying...");
                postTemplate(pageId, pageKey, "false", $btn);
            });

            function postTemplate(pageId, pageKey, force, $btn) {
                var action = cfg.actions.apply_template || cfg.applyTemplateAction;
                $.post(ajaxurl, {
                    action: action,
                    nonce: cfg.nonce,
                    page_id: pageId,
                    page_key: pageKey,
                    force: force
                }, function(response) {
                    if (response.success) {
                        toast("Template applied.", "success");
                        $btn.prop("disabled", false).text("Apply Template");
                    } else if (response.data && response.data.code === "has_content" && force !== "true") {
                        if (confirm("Page already has content. Overwrite with default template?")) {
                            postTemplate(pageId, pageKey, "true", $btn);
                        } else {
                            $btn.prop("disabled", false).text("Apply Template");
                        }
                    } else {
                        toast(message(response, "Template apply failed."), "error");
                        $btn.prop("disabled", false).text("Apply Template");
                    }
                }).fail(function() {
                    toast("Template request failed.", "error");
                    $btn.prop("disabled", false).text("Apply Template");
                });
            }

            $root.on("click", ".hpc-save-page-slug", function() {
                var $btn = $(this);
                var $detail = $btn.closest(".hpc-page-detail");
                var $detailRow = $btn.closest(".hpc-site-page-detail-row");
                var $workspace = $btn.closest("[data-hpc-page-workspace]");
                var $row = $workspace.length ? $root.find('.hpc-site-page-row[data-page-key="' + ($workspace.attr("data-page-key") || "") + '"]').first() : $detailRow.prevAll(".hpc-site-page-row").first();
                var $status = $detail.find(".hpc-page-slug-status");
                var pageId = $detail.data("page-id") || $btn.data("page-id");
                var slug = $detail.find(".hpc-page-slug-input").val();
                $btn.prop("disabled", true).text("Saving...");
                post("update_page_slug", {
                    page_key: $row.data("page-key"),
                    page_id: pageId,
                    slug: slug
                }, function(response) {
                    if (response.success) {
                        var data = response.data || {};
                        if ($workspace.length) {
                            $workspace.find("[data-hpc-workspace-detail]").html(data.detail_html || "");
                            $workspace.find("[data-hpc-workspace-meta]").text("Assigned page | " + (data.slug || slug));
                        } else {
                            setPageDetail($row, data.detail_html || "");
                        }
                        updatePageSelectLabel($row, pageId, data.title || "");
                        toast(message(response, "Slug updated."), "success");
                    } else {
                        setStatus($status, message(response, "Slug update failed."), false);
                        $btn.prop("disabled", false).text("Save Slug");
                    }
                }, function() {
                    setStatus($status, "Slug update request failed.", false);
                    $btn.prop("disabled", false).text("Save Slug");
                });
            });
        });
        </script>
        <?php

        return (string) ob_get_clean();
    }
}
