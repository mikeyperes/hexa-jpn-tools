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
                'show_default_content'  => true,
                'facts_used'            => null,
                'facts_edit_url'        => '',
                'shortcode_reference'   => [],
                'shortcode_reference_title' => 'Shortcodes for page content',
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
                'main_pages_title'  => 'Main pages',
                'main_pages_note'   => 'Pages at the top level of the site',
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
     * The page list, in sections: one section per page that has pages under it
     * (that page first, then its pages), then one section for the other pages.
     *
     * @param array<string,string> $labels
     */
    private function render_pages_card( array $labels ): string {
        $pages     = self::nest_by_parent( $this->manager->pages() );
        $all_pages = $this->manager->all_pages();

        $sections = [];
        $others   = [];
        foreach ( $pages as $page_key => $page_data ) {
            $page_data = is_array( $page_data ) ? $page_data : [];
            if ( ! empty( $page_data['children'] ) ) {
                $sections[ (string) $page_key ] = $page_data;
            } else {
                $others[ (string) $page_key ] = $page_data;
            }
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr( (string) $this->config['card_class'] ); ?> hpc-site-pages-card">
            <?php echo self::page_list_styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <div class="hpc-sp-intro">
                <h3><?php echo esc_html( $labels['pages_title'] ); ?></h3>
                <p><?php echo esc_html( $labels['pages_description'] ); ?></p>
            </div>
            <?php echo self::shortcode_reference_html( (array) $this->config['shortcode_reference'], (string) $this->config['shortcode_reference_title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php foreach ( $sections as $page_key => $page_data ) : ?>
                <?php $title = (string) ( $page_data['title'] ?? $page_key ); ?>
                <section class="hpc-sp-section-group">
                    <h4 class="hpc-sp-group-title"><?php echo esc_html( $title ); ?> <span><?php echo esc_html( 'The ' . $title . ' page and the pages under it' ); ?></span></h4>
                    <div class="hpc-sp-list">
                        <?php echo $this->render_page_row( $page_key, $page_data, $all_pages, null, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </section>
            <?php endforeach; ?>
            <?php if ( ! empty( $others ) ) : ?>
                <section class="hpc-sp-section-group">
                    <h4 class="hpc-sp-group-title"><?php echo esc_html( $labels['main_pages_title'] ); ?> <span><?php echo esc_html( $labels['main_pages_note'] ); ?></span></h4>
                    <div class="hpc-sp-list">
                        <?php foreach ( $others as $page_key => $page_data ) : ?>
                            <?php echo $this->render_page_row( $page_key, $page_data, $all_pages, null, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            <?php if ( ! empty( $this->config['lazy_page_workspace'] ) ) : ?>
                <?php echo $this->render_page_workspace(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Puts each page listed with a 'parent' key under that parent's 'children',
     * so the list shows the same hierarchy the site has.
     *
     * @param array<string,array<string,mixed>> $pages
     * @return array<string,array<string,mixed>>
     */
    private static function nest_by_parent( array $pages ): array {
        $moved = false;
        foreach ( $pages as $key => $data ) {
            $parent = is_array( $data ) ? (string) ( $data['parent'] ?? '' ) : '';
            if ( '' !== $parent && $parent !== $key && isset( $pages[ $parent ] ) ) {
                $pages[ $parent ]['children'] = array_merge( (array) ( $pages[ $parent ]['children'] ?? [] ), [ $key => $data ] );
                unset( $pages[ $key ] );
                $moved = true;
            }
        }
        return $moved ? self::nest_by_parent( $pages ) : $pages;
    }

    /** Where a page that does not exist yet will be: under its parent's address. */
    private function planned_address( string $slug, ?string $parent_key ): string {
        $parent_id = null !== $parent_key && '' !== $parent_key ? $this->manager->assigned_page_id( $parent_key ) : 0;
        $base      = $parent_id > 0 && function_exists( 'get_permalink' ) ? rtrim( (string) wp_make_link_relative( (string) get_permalink( $parent_id ) ), '/' ) : '';
        return $base . '/' . $slug . '/';
    }

    /**
     * One page row: an on/off switch, the page name and address, its status, and one
     * main button (Edit page or Create page). "Page details" opens everything else:
     * the linked page and its actions, the details it uses, its default content and
     * what goes into it. Pages under it follow as rows of their own, not indented.
     *
     * @param array<string,mixed> $page_data
     * @param \WP_Post[] $all_pages
     */
    private function render_page_row( string $page_key, array $page_data, array $all_pages, ?string $parent_key = null, bool $top_level = false, string $parent_title = '' ): string {
        $page_id = $this->manager->assigned_page_id( $page_key );
        $is_set  = $this->manager->is_assigned_page_set( $page_key );
        $enabled = $this->manager->is_enabled( $page_key );
        $reason  = $this->manager->unavailable_reason( $page_key );
        $custom  = $this->manager->is_custom_design( $page_key );
        $title   = (string) ( $page_data['title'] ?? $page_key );
        $slug    = (string) ( $page_data['slug'] ?? $page_key );
        $url     = $page_id > 0 && function_exists( 'get_permalink' ) ? (string) get_permalink( $page_id ) : '';
        $address = '' !== $url ? (string) wp_make_link_relative( $url ) : $this->planned_address( $slug, $parent_key );
        $facts   = $this->facts_used( $page_key );
        $missing = array_keys( array_filter( $facts, static fn( $value ): bool => '' === (string) $value ) );
        $body_id = sanitize_html_class( (string) $this->config['instance_id'] . '-' . $page_key . '-details' );
        $toggle  = ! empty( ( is_array( $this->config['actions'] ) ? $this->config['actions'] : [] )['toggle_page'] );

        ob_start();
        ?>
        <div class="hpc-sp hpc-site-page-row<?php echo $enabled ? '' : ' is-off'; ?><?php echo '' !== $reason ? ' is-unavailable' : ''; ?>" data-page-key="<?php echo esc_attr( $page_key ); ?>" data-page-title="<?php echo esc_attr( $title ); ?>" data-page-slug="<?php echo esc_attr( $slug ); ?>" data-parent-key="<?php echo esc_attr( (string) $parent_key ); ?>" data-linked="<?php echo $is_set ? '1' : '0'; ?>" data-custom="<?php echo $custom ? '1' : '0'; ?>">
            <div class="hpc-sp-head">
                <?php if ( $toggle ) : ?>
                    <label class="hpc-sp-switch" title="<?php echo esc_attr( $enabled ? 'On: part of this site' : 'Off: not part of this site' ); ?>"><input type="checkbox" class="hpc-sp-enable" data-page="<?php echo esc_attr( $page_key ); ?>" <?php checked( $enabled ); ?> <?php disabled( '' !== $reason ); ?> aria-label="<?php echo esc_attr( 'Use the ' . $title . ' page on this site' ); ?>"><span aria-hidden="true"></span></label>
                <?php endif; ?>
                <div class="hpc-sp-name">
                    <strong><?php echo esc_html( $title ); ?></strong><?php echo $custom ? ' <span class="hpc-sp-status hpc-sp-status--design" title="Gets its own design in the site redesign; the default text is not used.">Custom design</span>' : ''; ?>
                    <div class="hpc-sp-path"><?php echo '' !== $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $address ) . '</a>' : esc_html( $address ); ?><?php echo '' !== $parent_title ? '<span class="hpc-sp-under">Under ' . esc_html( $parent_title ) . '</span>' : ''; ?></div>
                    <button type="button" class="hpc-sp-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $body_id ); ?>">Page details</button>
                </div>
                <div class="hpc-sp-state">
                    <span class="hpc-site-page-status"><?php echo '' !== $reason ? '<span class="hpc-sp-status hpc-sp-status--muted">Not available</span>' : self::page_pill( $is_set, $enabled ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <?php if ( '' !== $reason ) : ?>
                        <span class="hpc-sp-disclaimer"><?php echo esc_html( $reason ); ?><?php echo '' !== $this->manager->site_type_edit_url() ? ' <a href="' . esc_url( $this->manager->site_type_edit_url() ) . '">Change what this website represents</a>' : ''; ?></span>
                    <?php else : ?>
                        <span class="hpc-sp-disclaimer hpc-sp-off-note"<?php echo $enabled ? ' hidden' : ''; ?>>Off: this page is not part of the site and will not be created. Switch it on to use it.</span>
                    <?php endif; ?>
                    <?php if ( ! empty( $missing ) && '' === $reason ) : ?>
                        <span class="hpc-sp-missing-line" title="<?php echo esc_attr( implode( ', ', $missing ) ); ?>"><?php echo esc_html( 'Missing: ' . implode( ', ', $missing ) ); ?></span>
                    <?php endif; ?>
                </div>
                <div class="hpc-sp-primary"><?php echo $this->primary_action( $page_id, $page_key, $is_set, $page_data, $parent_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            </div>
            <div class="hpc-sp-body" id="<?php echo esc_attr( $body_id ); ?>" hidden>
                <div class="hpc-sp-part">
                    <h5>Linked page</h5>
                    <p class="hpc-sp-note">The WordPress page used for <?php echo esc_html( $title ); ?>. Pick another page to use it instead.</p>
                    <div class="hpc-sp-linkrow">
                        <select class="hpc-site-page-select" data-page="<?php echo esc_attr( $page_key ); ?>" data-parent="<?php echo esc_attr( (string) $parent_key ); ?>" aria-label="<?php echo esc_attr( 'Page used for ' . $title ); ?>">
                            <option value="">Not linked</option>
                            <?php foreach ( $all_pages as $candidate ) : ?>
                                <option value="<?php echo esc_attr( (string) $candidate->ID ); ?>" <?php selected( $page_id, $candidate->ID ); ?>><?php echo esc_html( $candidate->post_title ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="hpc-site-page-actions"><?php echo $is_set ? $this->page_actions( $page_id, $page_key, $is_set, $page_data, $parent_key ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    </div>
                    <?php echo $this->page_detail_block( $page_key, $page_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <?php if ( $custom ) : ?>
                    <div class="hpc-sp-part"><h5>Custom design</h5><p class="hpc-sp-note">This page gets its own design in the site redesign. The plugin finds the page, links it and keeps it under its parent page; Create page makes it empty, and the default text is never put into it.</p></div>
                <?php else : ?>
                    <?php echo $this->render_page_more( $page_key, $page_id, $page_data, $facts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endif; ?>
            </div>
        </div>
        <?php foreach ( (array) ( $page_data['children'] ?? [] ) as $child_key => $child_data ) : ?>
            <?php echo $this->render_page_row( (string) $child_key, is_array( $child_data ) ? $child_data : [], $all_pages, $page_key, false, $title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php endforeach; ?>
        <?php

        return (string) ob_get_clean();
    }

    /** The row's one main button: Edit page when the page exists, else Create page. */
    private function primary_action( int $page_id, string $page_key, bool $is_set, array $page_data, ?string $parent_key ): string {
        if ( $is_set ) {
            return '<a href="' . esc_url( (string) get_edit_post_link( $page_id ) ) . '" target="_blank" rel="noopener" class="button hpc-sp-newtab" title="Opens in a new tab">Edit page <span aria-hidden="true">&rarr;</span></a>';
        }

        return $this->page_actions( $page_id, $page_key, $is_set, $page_data, $parent_key );
    }

    /** Status, author and last change of the linked page (from the plugin's page_detail_renderer). */
    private function page_detail_block( string $page_key, int $page_id ): string {
        if ( empty( $this->config['show_page_details'] ) || ! empty( $this->config['lazy_page_workspace'] ) ) {
            return '';
        }
        $detail_html = $page_id > 0 ? (string) ( $this->manager->page_payload( $page_id )['detail_html'] ?? '' ) : '';

        return '<div class="hpc-site-page-detail-row" data-page-key="' . esc_attr( $page_key ) . '"' . ( '' === $detail_html ? ' style="display:none;"' : '' ) . '><div class="hpc-page-detail-wrap" aria-live="polite">' . $detail_html . '</div></div>';
    }

    /**
     * The rest of Page details: the details the page uses, the default content
     * as it reads on the site, and what goes into the page.
     *
     * @param array<string,mixed> $page_data
     */
    private function render_page_more( string $page_key, int $page_id, array $page_data, array $facts = [] ): string {
        $sections = '';

        if ( ! empty( $facts ) ) {
            $items = '';
            foreach ( $facts as $label => $value ) {
                $value  = (string) $value;
                $items .= '<li><span>' . esc_html( (string) $label ) . '</span>' . ( '' === $value ? '<strong class="hpc-sp-missing">Not set</strong>' : '<strong>' . esc_html( $value ) . '</strong>' ) . '</li>';
            }
            $edit      = (string) $this->config['facts_edit_url'];
            $sections .= '<div class="hpc-sp-part"><h5>Details this page uses</h5><p class="hpc-sp-note">Filled in automatically. A detail that is not set is left out of the page.' . ( '' !== $edit ? ' <a href="' . esc_url( $edit ) . '">Edit these details</a>' : '' ) . '</p><ul class="hpc-sp-facts">' . $items . '</ul></div>';
        }

        if ( ! empty( $this->config['show_default_content'] ) ) {
            $template = $this->manager->default_template( $page_key );
            if ( '' !== trim( $template ) ) {
                $html      = function_exists( 'do_shortcode' ) ? do_shortcode( $template ) : $template;
                // The page's own <style> blocks are kept so the preview looks as it does on the site; scripts are dropped.
                preg_match_all( '#<style\b[^>]*>.*?</style>#is', $html, $styles );
                $html      = (string) preg_replace( '#<(style|script)\b[^>]*>.*?</\1>#is', '', $html );
                $sections .= '<div class="hpc-sp-part"><h5>Default page content</h5><p class="hpc-sp-note">How the page reads on the site, filled in from the site\'s details.</p><div class="hpc-sp-preview">' . implode( '', $styles[0] ) . wp_kses_post( $html ) . '</div></div>';
            }
        }

        if ( empty( $this->config['lazy_page_workspace'] ) && ! empty( $this->config['enable_templates'] ) && ! empty( $this->config['enable_template_editors'] ) && ! empty( $page_data['template'] ) ) {
            $raw_editor_id = sanitize_key( (string) $this->config['instance_id'] . '_' . $page_key . '_template' );
            $editor_id     = preg_replace( '/[^a-z0-9_]/', '_', $raw_editor_id ) ?: 'hpc_page_template';
            $rows          = max( 4, (int) $this->config['template_editor_rows'] );
            $template      = $this->manager->template_content( $page_key );
            ob_start();
            ?>
            <div class="hpc-sp-part hpc-template-editor" data-editor-id="<?php echo esc_attr( $editor_id ); ?>">
                <h5>What goes into the page</h5>
                <p class="hpc-sp-note">Create page and Apply template put this into the page. <?php echo false !== strpos( $template, '[' ) && ! empty( $this->config['show_default_content'] ) ? 'A line in [square brackets] prints the Default page content above on the site, filled in from the site\'s details, so it stays up to date. ' : ''; ?>Replace it with your own text to use different wording on this site.</p>
                <textarea id="<?php echo esc_attr( $editor_id ); ?>" class="large-text code" rows="<?php echo esc_attr( (string) min( $rows, 4 ) ); ?>"><?php echo esc_textarea( $template ); ?></textarea>
                <p class="hpc-sp-save"><button type="button" class="button hpc-save-page-template" data-page-key="<?php echo esc_attr( $page_key ); ?>">Save</button> <span class="hpc-template-status"></span></p>
            </div>
            <?php
            $sections .= (string) ob_get_clean();
        }

        return $sections;
    }

    /** The details a page's content uses (label => value, '' when not set), from the 'facts_used' callback. */
    private function facts_used( string $page_key ): array {
        $callback = $this->config['facts_used'] ?? null;
        return is_callable( $callback ) ? (array) call_user_func( $callback, $page_key ) : [];
    }

    /**
     * The shortcodes page content can use, each with what it does, shown above the page list.
     *
     * @param array<int,array{code?:string,description?:string}> $items
     */
    private static function shortcode_reference_html( array $items, string $title ): string {
        if ( empty( $items ) ) {
            return '';
        }
        return '<details class="hpc-sp-shortcodes"><summary>' . esc_html( $title ) . '</summary>' . self::shortcode_items_html( $items ) . '</details>';
    }

    /**
     * Reference rows: a 'heading' (with optional 'note') starts a group; a row has 'code',
     * 'description' and, for a detail, its current 'value'.
     *
     * @param array<int,array<string,string>> $items
     */
    public static function shortcode_items_html( array $items ): string {
        // Group rows under their headings; a group whose rows carry a 'value' is shown as
        // Detail | Current value | Shortcode, any other group as Shortcode | What it does.
        $groups = [];
        $index  = -1;
        foreach ( $items as $item ) {
            if ( isset( $item['heading'] ) || $index < 0 ) {
                $groups[] = [ 'heading' => (string) ( $item['heading'] ?? '' ), 'note' => (string) ( $item['note'] ?? '' ), 'rows' => [] ];
                $index++;
                if ( isset( $item['heading'] ) ) {
                    continue;
                }
            }
            $groups[ $index ]['rows'][] = $item;
        }

        $html = self::shortcode_reference_styles() . '<div class="hpc-scref">';
        foreach ( $groups as $group ) {
            if ( empty( $group['rows'] ) ) {
                continue;
            }
            $values = array_key_exists( 'value', $group['rows'][0] );
            $html  .= '<section class="hpc-scref-group">';
            $html  .= '' !== $group['heading'] ? '<h5>' . esc_html( $group['heading'] ) . '</h5>' : '';
            $html  .= '' !== $group['note'] ? '<p class="hpc-scref-note">' . esc_html( $group['note'] ) . '</p>' : '';
            $html  .= '<div class="hpc-scref-table' . ( $values ? ' hpc-scref-table--values' : '' ) . '">';
            $html  .= $values
                ? '<div class="hpc-scref-row hpc-scref-head"><span>Detail</span><span>Current value</span><span>Shortcode</span></div>'
                : '<div class="hpc-scref-row hpc-scref-head"><span>Shortcode</span><span>What it does</span></div>';
            foreach ( $group['rows'] as $row ) {
                $code = (string) ( $row['code'] ?? '' );
                $chip = '<span class="hpc-scref-code"><code>' . esc_html( $code ) . '</code><button type="button" class="button-link hpc-scref-copy" data-code="' . esc_attr( $code ) . '">Copy</button></span>';
                if ( $values ) {
                    $value = (string) ( $row['value'] ?? '' );
                    $html .= '<div class="hpc-scref-row"><span class="hpc-scref-label">' . esc_html( (string) ( $row['description'] ?? '' ) ) . '</span>'
                        . ( '' === $value ? '<span class="hpc-scref-value is-empty">Not set</span>' : '<span class="hpc-scref-value" title="' . esc_attr( $value ) . '">' . esc_html( $value ) . '</span>' )
                        . $chip . '</div>';
                } else {
                    $html .= '<div class="hpc-scref-row">' . $chip . '<span class="hpc-scref-desc">' . esc_html( (string) ( $row['description'] ?? '' ) ) . '</span></div>';
                }
            }
            $html .= '</div></section>';
        }

        return $html . '</div>';
    }

    /** Styles and the Copy button for shortcode_items_html(), printed once per page. */
    private static function shortcode_reference_styles(): string {
        static $printed = false;
        if ( $printed ) {
            return '';
        }
        $printed = true;

        return '<style>
            .hpc-scref{display:grid;gap:22px}
            .hpc-scref-group h5{margin:0 0 4px;font-size:13px;color:#1d2327}
            .hpc-scref-note{margin:0 0 10px;color:#646970;font-size:12px;max-width:760px}
            .hpc-scref-table{border:1px solid #dcdcde;border-radius:6px;overflow:hidden}
            .hpc-scref-row{display:grid;grid-template-columns:minmax(260px,380px) minmax(0,1fr);gap:16px;align-items:center;padding:8px 14px;border-top:1px solid #f0f0f1;font-size:13px}
            .hpc-scref-table--values .hpc-scref-row{grid-template-columns:180px minmax(0,1fr) minmax(280px,360px)}
            .hpc-scref-row:first-child{border-top:0}
            .hpc-scref-head{background:#f6f7f7;color:#50575e;font-size:11px;font-weight:600;letter-spacing:.03em;text-transform:uppercase;padding-top:6px;padding-bottom:6px}
            .hpc-scref-label{color:#1d2327;font-weight:600}
            .hpc-scref-value{color:#1d2327;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
            .hpc-scref-value.is-empty{color:#8a4b0f;font-style:italic}
            .hpc-scref-desc{color:#50575e}
            .hpc-scref-code{display:flex;align-items:center;gap:8px;min-width:0}
            .hpc-scref-code code{flex:0 1 auto;min-width:0;padding:2px 6px;background:#f0f0f1;border-radius:4px;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
            .hpc-scref-copy{flex:0 0 auto;font-size:12px}
            @media (max-width:960px){.hpc-scref-row,.hpc-scref-table--values .hpc-scref-row{grid-template-columns:1fr;gap:4px}.hpc-scref-head{display:none}}
        </style>
        <script>
        document.addEventListener("click", function (event) {
            var button = event.target.closest ? event.target.closest(".hpc-scref-copy") : null;
            if (!button) { return; }
            var done = function () { var text = button.textContent; button.textContent = "Copied"; setTimeout(function () { button.textContent = text; }, 1200); };
            if (navigator.clipboard) { navigator.clipboard.writeText(button.getAttribute("data-code")).then(done); return; }
            var area = document.createElement("textarea"); area.value = button.getAttribute("data-code"); document.body.appendChild(area); area.select(); document.execCommand("copy"); area.remove(); done();
        });
        </script>';
    }

    private static function page_list_styles(): string {
        static $printed = false;
        if ( $printed ) {
            return '';
        }
        $printed = true;

        return '<style>
            .hpc-sp-intro h3{margin:0 0 4px;font-size:16px}
            .hpc-sp-intro p{margin:0 0 20px;color:#50575e;max-width:760px}
            .hpc-sp-section-group{margin:0 0 24px}
            .hpc-sp-group-title{margin:0 0 8px;font-size:14px}
            .hpc-sp-group-title span{margin-left:6px;font-weight:400;font-size:12px;color:#646970}
            .hpc-sp-list{border:1px solid #dcdcde;border-radius:8px;background:#fff;overflow:hidden}
            .hpc-sp{border-top:1px solid #f0f0f1;padding:14px 18px}
            .hpc-sp:first-child{border-top:0}
            .hpc-sp-head{display:grid;grid-template-columns:auto minmax(0,1fr) 260px 130px;gap:18px;align-items:center}
            .hpc-sp-name strong{font-size:14px;color:#1d2327}
            .hpc-sp-path{margin-top:2px;color:#646970;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
            .hpc-sp-path a{color:#646970;text-decoration:none}
            .hpc-sp-path a:hover{color:#2271b1;text-decoration:underline}
            .hpc-sp-under{margin-left:8px;padding-left:8px;border-left:1px solid #dcdcde}
            .hpc-sp-toggle{margin:4px 0 0;padding:0;border:0;background:none;color:#2271b1;font-size:12px;font-weight:600;cursor:pointer}
            .hpc-sp-toggle::before{content:"";display:inline-block;margin-right:6px;border-style:solid;border-width:4px 0 4px 6px;border-color:transparent transparent transparent currentColor;transition:transform .15s}
            .hpc-sp-toggle[aria-expanded="true"]::before{transform:rotate(90deg)}
            .hpc-sp-state{display:flex;flex-direction:column;align-items:flex-start;gap:4px;min-width:0}
            .hpc-sp-missing-line{max-width:100%;color:#8a4b0f;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
            .hpc-sp-primary{text-align:right}
            .hpc-sp-primary .button{min-width:110px;text-align:center}
            .hpc-sp-newtab span{margin-left:4px}
            .hpc-sp.is-off .hpc-sp-name,.hpc-sp.is-off .hpc-sp-status,.hpc-sp.is-off .hpc-sp-missing-line{opacity:.55}
            .hpc-sp.is-off{background:#fafafa}
            .hpc-sp-disclaimer{max-width:100%;color:#50575e;font-size:12px;line-height:1.4;white-space:normal}
            .hpc-sp-disclaimer[hidden]{display:none}
            .hpc-sp.is-unavailable .hpc-sp-switch{cursor:not-allowed;opacity:.5}
            .hpc-sp.is-off .hpc-sp-primary{visibility:hidden}
            .hpc-sp-switch{position:relative;display:inline-block;width:36px;height:20px;cursor:pointer}
            .hpc-sp-switch input{position:absolute;opacity:0;width:0;height:0;margin:0;border:0;padding:0;-webkit-appearance:none;appearance:none;pointer-events:none}
            .hpc-sp-switch span{position:absolute;inset:0;border-radius:20px;background:#c3c4c7;transition:background .15s}
            .hpc-sp-switch span::after{content:"";position:absolute;top:2px;left:2px;width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.25);transition:transform .15s}
            .hpc-sp-switch input:checked + span{background:#2271b1}
            .hpc-sp-switch input:checked + span::after{transform:translateX(16px)}
            .hpc-sp-switch input:focus-visible + span{outline:2px solid #2271b1;outline-offset:2px}
            .hpc-sp-status{display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;line-height:18px}
            .hpc-sp-status--on{background:#e7f6ec;color:#16632d}
            .hpc-sp-status--off{background:#fcf0e3;color:#8a4b0f}
            .hpc-sp-status--muted{background:#f0f0f1;color:#50575e}
            .hpc-sp-status--design{background:#eef2ff;color:#3730a3;margin-left:6px;vertical-align:1px}
            .hpc-sp-body[hidden]{display:none}
            .hpc-sp-body{display:grid;gap:0;margin:14px 0 0;border:1px solid #dcdcde;border-radius:6px;overflow:hidden}
            .hpc-sp-linkrow{display:flex;flex-wrap:wrap;align-items:center;gap:8px}
            .hpc-sp-linkrow select{min-width:280px}
            .hpc-site-page-actions{display:inline-flex;gap:6px;flex-wrap:wrap}
            .hpc-site-page-actions .hpc-delete-page{color:#b32d2e;border-color:#e6b6b6}
            .hpc-page-detail-wrap{margin-top:10px}
            .hpc-sp-facts{display:grid;gap:6px;margin:0;max-width:900px}
            .hpc-sp-facts li{display:grid;grid-template-columns:170px minmax(0,1fr);gap:12px;margin:0;font-size:13px}
            .hpc-sp-facts li span{color:#646970}
            .hpc-sp-facts strong{font-weight:600;word-break:break-word}
            .hpc-sp-missing{color:#8a4b0f}
            .hpc-sp-shortcodes{margin:0 0 24px;padding:12px 16px;background:#fff;border:1px solid #dcdcde;border-radius:8px}
            .hpc-sp-shortcodes > summary{cursor:pointer;font-size:14px;font-weight:600}
            .hpc-sp-shortcodes[open] > summary{margin-bottom:12px}
            .hpc-sp-part{padding:14px 16px;background:#f6f7f7;border-top:1px solid #dcdcde}
            .hpc-sp-part:first-child{border-top:0}
            .hpc-sp-part h5{margin:0 0 4px;font-size:13px;color:#1d2327}
            .hpc-sp-note{margin:0 0 10px;color:#646970;font-size:12px}
            .hpc-sp-preview{max-height:320px;overflow:auto;padding:14px 16px;background:#fff;border:1px solid #dcdcde;border-radius:6px}
            .hpc-sp-preview > :first-child{margin-top:0}
            .hpc-sp-preview > :last-child{margin-bottom:0}
            .hpc-template-editor textarea{display:block;width:100%;font-family:Menlo,Consolas,monospace;font-size:12px}
            .hpc-sp-save{display:flex;align-items:center;gap:8px;margin:10px 0 0}
            .hpc-template-status{font-size:12px;color:#646970}
            .hpc-page-detail-wrap .hws-pages-detail{display:flex;flex-wrap:wrap;gap:6px 20px;font-size:13px}
            @media (max-width:960px){.hpc-sp-head{grid-template-columns:auto minmax(0,1fr)}.hpc-sp-state,.hpc-sp-primary{grid-column:2}.hpc-sp-primary{text-align:left}}
        </style>';
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
            $html = '<a href="' . esc_url( get_permalink( $page_id ) ) . '" target="_blank" rel="noopener" class="button hpc-sp-newtab" title="Opens in a new tab">View <span aria-hidden="true">&rarr;</span></a> ';

            $actions = is_array( $this->config['actions'] ) ? $this->config['actions'] : [];
            if ( ! $this->manager->is_custom_design( $page_key ) && empty( $this->config['lazy_page_workspace'] ) && ! empty( $this->config['enable_templates'] ) && ( ! empty( $this->config['apply_template_action'] ) || ! empty( $actions['apply_template'] ) ) ) {
                $html .= '<button type="button" class="button hpc-apply-page-template" data-page-id="' . esc_attr( (string) $page_id ) . '" data-page-key="' . esc_attr( $page_key ) . '">Apply template</button> ';
            }

            if ( ! empty( $this->config['lazy_page_workspace'] ) ) {
                $html .= '<button type="button" class="button button-small hpc-open-page-workspace" data-page-key="' . esc_attr( $page_key ) . '" data-page-id="' . esc_attr( (string) $page_id ) . '">Manage</button> ';
            }

            $html .= '<button type="button" class="button hpc-delete-page" data-page="' . esc_attr( $page_key ) . '" data-page-id="' . esc_attr( (string) $page_id ) . '">Delete</button>';

            return $html;
        }

        $parent_exists = true;
        if ( null !== $parent_key && '' !== $parent_key ) {
            $parent_exists = $this->manager->is_assigned_to_published_page( $parent_key );
        }

        if ( ! $parent_exists ) {
            return '<span class="hpc-sp-note">Create the parent page first</span>';
        }

        $title = (string) ( $page_data['title'] ?? $page_key );
        $slug  = (string) ( $page_data['slug'] ?? $page_key );

        $html = '<button type="button" class="button button-primary hpc-create-page" data-page="' . esc_attr( $page_key ) . '" data-title="' . esc_attr( $title ) . '" data-slug="' . esc_attr( $slug ) . '"';
        if ( null !== $parent_key && '' !== $parent_key ) {
            $html .= ' data-parent="' . esc_attr( $parent_key ) . '"';
        }
        $html .= '>Create page</button>';

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

    private static function page_pill( bool $is_set, bool $enabled = true ): string {
        if ( ! $enabled ) {
            return '<span class="hpc-sp-status hpc-sp-status--muted">Off</span>';
        }
        return '<span class="hpc-sp-status hpc-sp-status--' . ( $is_set ? 'on' : 'off' ) . '">' . ( $is_set ? 'Published' : 'Not created' ) . '</span>';
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
                'toggle_page'              => '',
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
