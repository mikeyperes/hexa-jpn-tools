<?php

namespace Hexa\PluginCore\SiteStructure;

final class SiteStructureRenderer {
    private PageStructureManager $manager;

    /**
     * @var array<string,mixed>
     */
    private array $config;

    /**
     * @param array<string,mixed> $config
     */
    public function __construct( PageStructureManager $manager, array $config = [] ) {
        $this->manager = $manager;
        $this->config  = array_merge(
            [
                'instance_id'           => 'hpc-site-structure',
                'nonce'                 => '',
                'card_class'            => 'hpc-card',
                'table_class'           => 'hpc-table',
                'enable_templates'      => false,
                'enable_template_editors' => false,
                'template_editor_media_buttons' => false,
                'template_editor_rows' => 8,
                'apply_template_action' => '',
                'show_pages'            => true,
                'show_menus'            => true,
                'show_page_details'     => false,
                'lazy_page_workspace'   => false,
                'actions'               => [],
                'labels'                => [],
            ],
            $config
        );
    }

    public function render(): string {
        $instance_id = sanitize_html_class( (string) $this->config['instance_id'] );
        $labels      = $this->labels();

        ob_start();
        ?>
        <div id="<?php echo esc_attr( $instance_id ); ?>" class="hpc-site-structure" data-hpc-site-structure>
            <?php if ( ! empty( $this->config['show_pages'] ) ) : ?>
                <?php echo $this->render_pages_card( $labels ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
            <?php if ( ! empty( $this->config['show_menus'] ) ) : ?>
                <?php echo $this->render_menu_card( $labels ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
            <?php echo SiteStructureScriptRenderer::render( $instance_id, $this->script_payload() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @return array<string,string>
     */
    private function labels(): array {
        return array_merge(
            [
                'pages_title'       => 'Critical Pages',
                'pages_heading'     => 'Required Website Pages',
                'pages_description' => 'Assign existing WordPress pages or create the required page structure with parent-child hierarchy.',
                'menus_title'       => 'Navigation Menus',
                'menus_heading'     => 'Menu Blueprint Manager',
                'menus_description' => 'Create WordPress menus, attach groups of assigned pages, or attach a single assigned page beneath a specific menu item.',
            ],
            is_array( $this->config['labels'] ) ? $this->config['labels'] : []
        );
    }

    /**
     * @param array<string,string> $labels
     */
    private function render_pages_card( array $labels ): string {
        $pages = $this->manager->pages();
        $all_pages = $this->manager->all_pages();

        ob_start();
        ?>
        <div class="<?php echo esc_attr( (string) $this->config['card_class'] ); ?> hpc-site-pages-card">
            <div class="sfpf-card-header hpc-card-header">
                <span class="dashicons dashicons-admin-page" style="color:#8b5cf6;"></span>
                <h3><?php echo esc_html( $labels['pages_title'] ); ?></h3>
            </div>

            <h4 style="margin:0 0 8px;font-size:15px;"><?php echo esc_html( $labels['pages_heading'] ); ?></h4>
            <p style="color:#666;margin:0 0 20px;"><?php echo esc_html( $labels['pages_description'] ); ?></p>

            <table class="<?php echo esc_attr( (string) $this->config['table_class'] ); ?> hpc-critical-pages-table">
                <thead>
                    <tr>
                        <th style="width:25%;">Page</th>
                        <th style="width:30%;">Assigned WordPress Page</th>
                        <th style="width:15%;">Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $pages as $page_key => $page_data ) : ?>
                        <?php echo $this->render_page_row( (string) $page_key, $page_data, $all_pages, null, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ( ! empty( $this->config['lazy_page_workspace'] ) ) : ?>
                <?php echo $this->render_page_workspace(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string,mixed> $page_data
     * @param \WP_Post[] $all_pages
     */
    private function render_page_row( string $page_key, array $page_data, array $all_pages, ?string $parent_key = null, bool $top_level = false ): string {
        $page_id = $this->manager->assigned_page_id( $page_key );
        $page    = $page_id > 0 && function_exists( 'get_post' ) ? get_post( $page_id ) : null;
        $is_set  = $this->manager->is_assigned_page_set( $page_key );
        $title   = (string) ( $page_data['title'] ?? $page_key );
        $slug    = (string) ( $page_data['slug'] ?? $page_key );

        ob_start();
        ?>
        <tr class="hpc-site-page-row" data-page-key="<?php echo esc_attr( $page_key ); ?>" data-page-title="<?php echo esc_attr( $title ); ?>" data-page-slug="<?php echo esc_attr( $slug ); ?>" data-parent-key="<?php echo esc_attr( (string) $parent_key ); ?>" style="<?php echo $top_level ? 'background:#f9fafb;' : ''; ?>">
            <td style="<?php echo $parent_key ? 'padding-left:30px;' : ''; ?>">
                <?php if ( $parent_key ) : ?>
                    <span style="color:#9ca3af;margin-right:8px;">&#9492;&#9472;</span>
                <?php endif; ?>
                <strong><?php echo esc_html( $title ); ?></strong>
                <div style="margin-top:3px;<?php echo $parent_key ? 'margin-left:22px;' : ''; ?>">
                    <code><?php echo esc_html( $slug ); ?></code>
                </div>
            </td>
            <td>
                <select class="hpc-site-page-select" data-page="<?php echo esc_attr( $page_key ); ?>" data-parent="<?php echo esc_attr( (string) $parent_key ); ?>" style="width:100%;max-width:280px;">
                    <option value="">- Select Page -</option>
                    <?php foreach ( $all_pages as $candidate ) : ?>
                        <option value="<?php echo esc_attr( (string) $candidate->ID ); ?>" <?php selected( $page_id, $candidate->ID ); ?>>
                            <?php echo esc_html( $candidate->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td class="hpc-site-page-status"><?php echo $this->status_badge( $is_set, $is_set ? 'Set' : 'Not Set' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
            <td class="hpc-site-page-actions"><?php echo $this->page_actions( $page_id, $page_key, $is_set, $page_data, $parent_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
        </tr>
        <?php if ( empty( $this->config['lazy_page_workspace'] ) ) : ?>
            <?php echo $this->render_page_detail_row( $page_key, $page_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php echo $this->render_template_row( $page_key, $page_data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php endif; ?>
        <?php foreach ( (array) ( $page_data['children'] ?? [] ) as $child_key => $child_data ) : ?>
            <?php echo $this->render_page_row( (string) $child_key, is_array( $child_data ) ? $child_data : [], $all_pages, $page_key, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php endforeach; ?>
        <?php

        return (string) ob_get_clean();
    }

    private function render_page_detail_row( string $page_key, int $page_id ): string {
        if ( empty( $this->config['show_page_details'] ) ) {
            return '';
        }

        $detail_html = '';
        if ( $page_id > 0 ) {
            $payload = $this->manager->page_payload( $page_id );
            $detail_html = (string) ( $payload['detail_html'] ?? '' );
        }

        ob_start();
        ?>
        <tr class="hpc-site-page-detail-row" data-page-key="<?php echo esc_attr( $page_key ); ?>" style="<?php echo '' === $detail_html ? 'display:none;' : ''; ?>">
            <td colspan="4" style="padding:0 16px 16px;">
                <div class="hpc-page-detail-wrap" aria-live="polite"><?php echo $detail_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            </td>
        </tr>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string,mixed> $page_data
     */
    private function render_template_row( string $page_key, array $page_data ): string {
        if ( empty( $this->config['enable_templates'] ) || empty( $this->config['enable_template_editors'] ) || empty( $page_data['template'] ) ) {
            return '';
        }

        $raw_editor_id = sanitize_key( (string) $this->config['instance_id'] . '_' . $page_key . '_template' );
        $editor_id = preg_replace( '/[^a-z0-9_]/', '_', $raw_editor_id ) ?: 'hpc_page_template';
        $template = $this->manager->template_content( $page_key );
        $rows = max( 4, (int) $this->config['template_editor_rows'] );

        ob_start();
        ?>
        <tr class="hpc-site-template-row" data-page-key="<?php echo esc_attr( $page_key ); ?>">
            <td colspan="4" style="padding:0 16px 16px;">
                <details class="hpc-template-panel" style="border:1px solid #e5e7eb;border-radius:8px;background:#fff;margin:0;">
                    <summary style="cursor:pointer;padding:12px 14px;font-weight:700;display:flex;align-items:center;justify-content:space-between;gap:12px;">
                        <span>Starter/template text</span>
                        <small style="color:#64748b;font-weight:500;">Saved template is used when creating or applying this page.</small>
                    </summary>
                    <div class="hpc-template-editor" data-editor-id="<?php echo esc_attr( $editor_id ); ?>" style="border-top:1px solid #e5e7eb;padding:14px;">
                        <?php if ( function_exists( 'wp_editor' ) ) : ?>
                            <?php wp_editor( $template, $editor_id, [ 'textarea_name' => $editor_id, 'textarea_rows' => $rows, 'media_buttons' => ! empty( $this->config['template_editor_media_buttons'] ), 'teeny' => false, 'quicktags' => true, 'tinymce' => true ] ); ?>
                        <?php else : ?>
                            <textarea id="<?php echo esc_attr( $editor_id ); ?>" rows="<?php echo esc_attr( (string) $rows ); ?>" style="width:100%;"><?php echo esc_textarea( $template ); ?></textarea>
                        <?php endif; ?>
                        <p style="display:flex;align-items:center;gap:8px;margin:12px 0 0;">
                            <button type="button" class="button button-secondary hpc-save-page-template" data-page-key="<?php echo esc_attr( $page_key ); ?>">Save Template</button>
                            <span class="hpc-template-status" style="font-size:13px;color:#64748b;"></span>
                        </p>
                    </div>
                </details>
            </td>
        </tr>
        <?php

        return (string) ob_get_clean();
    }

    private function render_page_workspace(): string {
        $editor_id = sanitize_key( (string) $this->config['instance_id'] . '_page_workspace_editor' );
        $rows      = max( 4, (int) $this->config['template_editor_rows'] );

        ob_start();
        ?>
        <section class="hpc-page-workspace" data-hpc-page-workspace hidden style="border:1px solid #dcdcde;margin-top:14px;background:#fff;">
            <header style="align-items:center;background:#f6f7f7;border-bottom:1px solid #dcdcde;display:flex;gap:12px;justify-content:space-between;padding:12px 14px;">
                <div><strong data-hpc-workspace-title>Page tools</strong><div data-hpc-workspace-meta style="color:#646970;font-size:12px;margin-top:3px;"></div></div>
                <button type="button" class="button-link hpc-close-page-workspace" aria-label="Close page tools" title="Close page tools" style="font-size:22px;line-height:1;">&times;</button>
            </header>
            <div data-hpc-workspace-loading style="align-items:center;display:none;gap:8px;padding:14px;"><span class="spinner is-active"></span><span>Loading page tools...</span></div>
            <div data-hpc-workspace-body style="display:grid;gap:14px;padding:14px;">
                <?php if ( ! empty( $this->config['show_page_details'] ) ) : ?>
                    <div class="hpc-page-workspace-detail" data-hpc-workspace-detail></div>
                <?php endif; ?>
                <?php if ( ! empty( $this->config['enable_templates'] ) && ! empty( $this->config['enable_template_editors'] ) ) : ?>
                    <div class="hpc-page-workspace-editor" data-editor-id="<?php echo esc_attr( $editor_id ); ?>">
                        <h4 style="margin:0 0 8px;">Starter/template text</h4>
                        <?php if ( function_exists( 'wp_editor' ) ) : ?>
                            <?php wp_editor( '', $editor_id, [ 'textarea_name' => $editor_id, 'textarea_rows' => $rows, 'media_buttons' => ! empty( $this->config['template_editor_media_buttons'] ), 'teeny' => false, 'quicktags' => true, 'tinymce' => true ] ); ?>
                        <?php else : ?>
                            <textarea id="<?php echo esc_attr( $editor_id ); ?>" rows="<?php echo esc_attr( (string) $rows ); ?>" style="width:100%;"></textarea>
                        <?php endif; ?>
                        <div style="align-items:center;display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;">
                            <button type="button" class="button button-secondary hpc-save-workspace-template">Save Template</button>
                            <button type="button" class="button hpc-apply-workspace-template">Apply Template</button>
                            <span class="hpc-workspace-status" style="font-size:13px;color:#64748b;"></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string,mixed> $page_data
     */
    private function page_actions( int $page_id, string $page_key, bool $is_set, array $page_data, ?string $parent_key ): string {
        if ( $is_set ) {
            $html = '<a href="' . esc_url( get_edit_post_link( $page_id ) ) . '" target="_blank" rel="noopener" class="button button-small">Edit</a> ';
            $html .= '<a href="' . esc_url( get_permalink( $page_id ) ) . '" target="_blank" rel="noopener" class="button button-small">View</a> ';

            $actions = is_array( $this->config['actions'] ) ? $this->config['actions'] : [];
            if ( empty( $this->config['lazy_page_workspace'] ) && ! empty( $this->config['enable_templates'] ) && ( ! empty( $this->config['apply_template_action'] ) || ! empty( $actions['apply_template'] ) ) ) {
                $html .= '<button type="button" class="button button-small hpc-apply-page-template" data-page-id="' . esc_attr( (string) $page_id ) . '" data-page-key="' . esc_attr( $page_key ) . '">Apply Template</button> ';
            }

            if ( ! empty( $this->config['lazy_page_workspace'] ) ) {
                $html .= '<button type="button" class="button button-small hpc-open-page-workspace" data-page-key="' . esc_attr( $page_key ) . '" data-page-id="' . esc_attr( (string) $page_id ) . '">Manage</button> ';
            }

            $html .= '<button type="button" class="button button-small hpc-delete-page" data-page="' . esc_attr( $page_key ) . '" data-page-id="' . esc_attr( (string) $page_id ) . '" style="color:#dc2626;border-color:#fca5a5;">Delete</button>';

            return $html;
        }

        $parent_exists = true;
        if ( null !== $parent_key && '' !== $parent_key ) {
            $parent_exists = $this->manager->is_assigned_to_published_page( $parent_key );
        }

        if ( ! $parent_exists ) {
            return '<span style="color:#9ca3af;font-size:12px;">Create parent page first</span>';
        }

        $title = (string) ( $page_data['title'] ?? $page_key );
        $slug  = (string) ( $page_data['slug'] ?? $page_key );

        $html = '<button type="button" class="button button-small button-primary hpc-create-page" data-page="' . esc_attr( $page_key ) . '" data-title="' . esc_attr( $title ) . '" data-slug="' . esc_attr( $slug ) . '"';
        if ( null !== $parent_key && '' !== $parent_key ) {
            $html .= ' data-parent="' . esc_attr( $parent_key ) . '"';
        }
        $html .= '>+ Create</button>';

        if ( ! empty( $this->config['lazy_page_workspace'] ) ) {
            $html .= ' <button type="button" class="button button-small hpc-open-page-workspace" data-page-key="' . esc_attr( $page_key ) . '" data-page-id="0">Manage</button>';
        }

        return $html;
    }

    /**
     * @param array<string,string> $labels
     */
    private function render_menu_card( array $labels ): string {
        $structures = $this->manager->menu_structures();
        $flat_pages = $this->manager->flat_pages();
        $nav_menus  = function_exists( 'wp_get_nav_menus' ) ? wp_get_nav_menus() : [];
        $inventory  = [];

        foreach ( $nav_menus as $menu ) {
            $items = function_exists( 'wp_get_nav_menu_items' ) ? ( wp_get_nav_menu_items( $menu->term_id ) ?: [] ) : [];
            $inventory[] = [
                'menu'  => $menu,
                'items' => $this->manager->menu_item_labels( $items ),
            ];
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr( (string) $this->config['card_class'] ); ?> hpc-site-menu-card" id="hpc-site-menu-manager">
            <div class="sfpf-card-header hpc-card-header">
                <span class="dashicons dashicons-menu" style="color:#059669;"></span>
                <h3><?php echo esc_html( $labels['menus_title'] ); ?></h3>
            </div>

            <h4 style="margin:0 0 8px;font-size:15px;"><?php echo esc_html( $labels['menus_heading'] ); ?></h4>
            <p style="color:#666;margin:0 0 15px;"><?php echo esc_html( $labels['menus_description'] ); ?></p>

            <div style="display:grid;grid-template-columns:minmax(260px,1fr) auto;gap:10px;align-items:end;margin-bottom:18px;max-width:760px;">
                <label style="display:block;">
                    <span style="display:block;font-weight:600;margin-bottom:4px;">Create new menu</span>
                    <input type="text" class="regular-text hpc-new-menu-name" placeholder="Header, Footer, Sub-Footer" style="width:100%;max-width:none;">
                </label>
                <button type="button" class="button button-primary hpc-create-navigation-menu">Create Menu</button>
                <span class="hpc-create-menu-status" style="grid-column:1 / -1;font-size:13px;color:#666;"></span>
            </div>

            <div style="border:1px solid #e5e7eb;border-radius:8px;padding:14px;margin-bottom:18px;background:#fff;">
                <h4 style="margin:0 0 10px;font-size:15px;">Create Menu Item</h4>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;align-items:end;">
                    <label>
                        <span style="display:block;font-weight:600;margin-bottom:4px;">Menu</span>
                        <select class="hpc-custom-item-menu" style="width:100%;" <?php disabled( empty( $nav_menus ) ); ?>>
                            <?php echo $this->menu_options( $nav_menus, 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </select>
                    </label>
                    <label>
                        <span style="display:block;font-weight:600;margin-bottom:4px;">Parent item</span>
                        <select class="hpc-custom-item-parent" style="width:100%;" <?php disabled( empty( $nav_menus ) ); ?>>
                            <?php echo $this->menu_item_options( $inventory ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </select>
                    </label>
                    <label>
                        <span style="display:block;font-weight:600;margin-bottom:4px;">Item label</span>
                        <input type="text" class="regular-text hpc-custom-item-title" placeholder="Advertise" style="width:100%;max-width:none;" <?php disabled( empty( $nav_menus ) ); ?>>
                    </label>
                    <label>
                        <span style="display:block;font-weight:600;margin-bottom:4px;">URL</span>
                        <input type="text" class="regular-text hpc-custom-item-url" placeholder="/advertise/ or https://example.com" style="width:100%;max-width:none;" <?php disabled( empty( $nav_menus ) ); ?>>
                    </label>
                    <button type="button" class="button button-primary hpc-create-menu-item" <?php disabled( empty( $nav_menus ) ); ?>>Create Menu Item</button>
                </div>
                <span class="hpc-create-menu-item-status" style="display:block;margin-top:8px;font-size:13px;color:#666;"></span>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;margin-bottom:18px;">
                <?php foreach ( $structures as $structure_key => $structure ) : ?>
                    <?php $guessed_menu_id = $this->manager->guess_menu_id_for_structure( (string) $structure_key, $nav_menus ); ?>
                    <div class="hpc-menu-structure-card" data-structure="<?php echo esc_attr( (string) $structure_key ); ?>" style="border:1px solid #e5e7eb;border-radius:8px;padding:14px;background:#f9fafb;">
                        <h4 style="margin:0 0 4px;font-size:15px;"><?php echo esc_html( (string) ( $structure['title'] ?? $structure_key ) ); ?></h4>
                        <p style="margin:0 0 10px;color:#666;font-size:13px;"><?php echo esc_html( (string) ( $structure['description'] ?? '' ) ); ?></p>

                        <label style="display:block;margin-bottom:8px;">
                            <span style="display:block;font-weight:600;margin-bottom:4px;">WordPress menu</span>
                            <select class="hpc-structure-menu" style="width:100%;" <?php disabled( empty( $nav_menus ) ); ?>>
                                <?php echo $this->menu_options( $nav_menus, $guessed_menu_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </select>
                        </label>

                        <label style="display:block;margin-bottom:10px;">
                            <span style="display:block;font-weight:600;margin-bottom:4px;">Attach under menu item</span>
                            <select class="hpc-structure-parent" style="width:100%;" <?php disabled( empty( $nav_menus ) ); ?>>
                                <?php echo $this->menu_item_options( $inventory ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </select>
                        </label>

                        <div style="font-size:12px;color:#4b5563;margin-bottom:10px;">
                            <?php foreach ( (array) ( $structure['page_keys'] ?? [] ) as $page_key ) : ?>
                                <?php $page_key = (string) $page_key; $page_data = $flat_pages[ $page_key ] ?? null; $assigned_id = $this->manager->assigned_page_id( $page_key ); ?>
                                <?php if ( $page_data ) : ?>
                                    <span style="display:inline-block;margin:0 4px 4px 0;padding:2px 7px;border-radius:999px;background:<?php echo $assigned_id ? '#dcfce7' : '#f3f4f6'; ?>;color:<?php echo $assigned_id ? '#166534' : '#6b7280'; ?>;">
                                        <?php echo esc_html( (string) $page_data['title'] ); ?><?php echo $assigned_id ? '' : ' (not set)'; ?>
                                    </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>

                        <button type="button" class="button button-secondary hpc-attach-menu-structure" <?php disabled( empty( $nav_menus ) ); ?>>Attach <?php echo esc_html( (string) ( $structure['title'] ?? $structure_key ) ); ?></button>
                        <span class="hpc-structure-status" style="display:block;margin-top:8px;font-size:13px;color:#666;"></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="border:1px solid #e5e7eb;border-radius:8px;padding:14px;margin-bottom:18px;background:#fff;">
                <h4 style="margin:0 0 10px;font-size:15px;">Attach Assigned Page To Menu Item</h4>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;align-items:end;">
                    <label>
                        <span style="display:block;font-weight:600;margin-bottom:4px;">Menu</span>
                        <select class="hpc-attach-menu" style="width:100%;" <?php disabled( empty( $nav_menus ) ); ?>>
                            <?php echo $this->menu_options( $nav_menus, 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </select>
                    </label>
                    <label>
                        <span style="display:block;font-weight:600;margin-bottom:4px;">Parent menu item</span>
                        <select class="hpc-attach-parent-item" style="width:100%;" <?php disabled( empty( $nav_menus ) ); ?>>
                            <?php echo $this->menu_item_options( $inventory ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </select>
                    </label>
                    <label>
                        <span style="display:block;font-weight:600;margin-bottom:4px;">Assigned page</span>
                        <select class="hpc-attach-page-key" style="width:100%;" <?php disabled( empty( $nav_menus ) ); ?>>
                            <?php foreach ( $flat_pages as $page_key => $page_data ) : ?>
                                <?php $assigned_id = $this->manager->assigned_page_id( (string) $page_key ); ?>
                                <option value="<?php echo esc_attr( (string) $page_key ); ?>" <?php disabled( ! $assigned_id ); ?>>
                                    <?php echo esc_html( (string) $page_data['title'] . ( $assigned_id ? '' : ' (not set)' ) ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="button" class="button button-primary hpc-attach-page-to-menu-item" <?php disabled( empty( $nav_menus ) ); ?>>Attach Page</button>
                </div>
                <span class="hpc-attach-page-status" style="display:block;margin-top:8px;font-size:13px;color:#666;"></span>
            </div>

            <h4 style="margin:0 0 10px;font-size:15px;">WordPress Menu Items</h4>
            <div class="hpc-menu-inventory-wrap">
            <?php if ( empty( $nav_menus ) ) : ?>
                <p class="hpc-menu-inventory-empty" style="color:#666;margin:0;">No WordPress menus exist yet. Create the required menus above.</p>
            <?php else : ?>
                <table class="<?php echo esc_attr( (string) $this->config['table_class'] ); ?> hpc-menu-inventory-table">
                    <thead>
                        <tr>
                            <th style="width:24%;">Menu</th>
                            <th>Items</th>
                            <th style="width:20%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $inventory as $row ) : ?>
                            <?php $menu = $row['menu']; ?>
                            <tr>
                                <td><strong><?php echo esc_html( $menu->name ); ?></strong><div><code><?php echo esc_html( $menu->slug ); ?></code></div></td>
                                <td>
                                    <?php if ( empty( $row['items'] ) ) : ?>
                                        <span style="color:#6b7280;">No items</span>
                                    <?php else : ?>
                                        <?php foreach ( $row['items'] as $item ) : ?>
                                            <div style="margin-bottom:3px;"><code>#<?php echo esc_html( (string) $item['id'] ); ?></code> <?php echo esc_html( (string) $item['label'] ); ?></div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="button button-small" href="<?php echo esc_url( admin_url( 'nav-menus.php?menu=' . (int) $menu->term_id ) ); ?>" target="_blank" rel="noopener">Edit</a>
                                    <button type="button" class="button button-small hpc-delete-navigation-menu" data-menu-id="<?php echo esc_attr( (string) $menu->term_id ); ?>" data-menu-name="<?php echo esc_attr( $menu->name ); ?>">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<int,object> $menus
     */
    private function menu_options( array $menus, int $selected ): string {
        if ( empty( $menus ) ) {
            return '<option value="">No menus found</option>';
        }

        $html = '';
        foreach ( $menus as $menu ) {
            $html .= '<option value="' . esc_attr( (string) $menu->term_id ) . '" ' . selected( $selected, (int) $menu->term_id, false ) . '>' . esc_html( $menu->name ) . '</option>';
        }

        return $html;
    }

    /**
     * @param array<int,array<string,mixed>> $inventory
     */
    private function menu_item_options( array $inventory ): string {
        $html = '<option value="0" data-menu-id="0">Top level</option>';
        foreach ( $inventory as $row ) {
            $menu = $row['menu'];
            foreach ( (array) $row['items'] as $item ) {
                $html .= '<option value="' . esc_attr( (string) $item['id'] ) . '" data-menu-id="' . esc_attr( (string) $menu->term_id ) . '">' . esc_html( $menu->name . ': ' . $item['label'] ) . '</option>';
            }
        }

        return $html;
    }

    private function status_badge( bool $status, string $text ): string {
        $bg    = $status ? '#dcfce7' : '#fee2e2';
        $color = $status ? '#166534' : '#991b1b';
        $icon  = $status ? '&#10003;' : '&times;';

        return '<span style="display:inline-block;background:' . esc_attr( $bg ) . ';color:' . esc_attr( $color ) . ';padding:4px 10px;border-radius:4px;font-size:12px;font-weight:500;">' . $icon . ' ' . esc_html( $text ) . '</span>';
    }

    /**
     * @return array<string,mixed>
     */
    private function script_payload(): array {
        $actions = array_merge(
            [
                'assign_page'              => '',
                'create_page'              => '',
                'delete_page'              => '',
                'create_navigation_menu'   => '',
                'delete_navigation_menu'   => '',
                'create_menu_item'         => '',
                'attach_page_to_menu_item' => '',
                'attach_menu_structure'    => '',
                'menu_inventory'           => '',
                'save_template'            => '',
                'apply_template'           => '',
                'page_details'             => '',
                'page_workspace'           => '',
                'update_page_slug'         => '',
            ],
            is_array( $this->config['actions'] ) ? $this->config['actions'] : []
        );

        $payload = [
            'nonce'               => (string) $this->config['nonce'],
            'actions'             => $actions,
            'enableTemplates'     => ! empty( $this->config['enable_templates'] ),
            'lazyPageWorkspace'   => ! empty( $this->config['lazy_page_workspace'] ),
            'applyTemplateAction' => (string) $this->config['apply_template_action'],
            'adminPostBase'       => function_exists( 'admin_url' ) ? admin_url( 'post.php?post=' ) : '',
            'tableClass'          => (string) $this->config['table_class'],
        ];
        return $payload;
    }
}
