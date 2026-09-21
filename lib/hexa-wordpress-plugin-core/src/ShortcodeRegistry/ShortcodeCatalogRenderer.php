<?php

namespace Hexa\PluginCore\ShortcodeRegistry;

use Hexa\PluginCore\SmartSearch\SmartSearchRenderer;
use Hexa\PluginCore\WpAdminComponents\CoreUi;

final class ShortcodeCatalogRenderer {
    /**
     * @param array<string,mixed> $args
     */
    public function render_page( array $args ): string {
        $catalog  = isset( $args['catalog'] ) && is_array( $args['catalog'] ) ? $args['catalog'] : [];
        $context  = isset( $args['context'] ) && is_array( $args['context'] ) ? $args['context'] : [];
        $title    = isset( $args['title'] ) ? (string) $args['title'] : 'Shortcodes';
        $intro    = isset( $args['intro'] ) ? (string) $args['intro'] : '';
        $settings = $this->settings( $args );

        CoreUi::render_assets();

        ob_start();
        ?>
        <div class="smpi-panel smpi-sc">
            <?php $this->render_styles(); ?>
            <h2><?php echo esc_html( $title ); ?></h2>
            <?php if ( '' !== $intro ) : ?>
                <p class="smpi-sc-intro"><?php echo esc_html( $intro ); ?></p>
            <?php endif; ?>
            <?php echo $this->render_search_bar( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <div id="<?php echo esc_attr( $settings['values_id'] ); ?>" data-user="<?php echo esc_attr( (string) ( $context['user_id'] ?? 0 ) ); ?>" data-post="<?php echo esc_attr( (string) ( $context['post_id'] ?? 0 ) ); ?>">
                <?php echo $this->render_catalog( $catalog, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <p class="smpi-sc-noresults"><?php echo esc_html( $settings['empty_text'] ); ?></p>
        </div>
        <?php echo $this->render_script( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array<int,array<string,mixed>> $catalog
     * @param array<string,mixed> $args
     */
    public function render_catalog( array $catalog, array $args = [] ): string {
        $context = isset( $args['context'] ) && is_array( $args['context'] ) ? $args['context'] : [];
        $layers  = isset( $args['layers'] ) && is_array( $args['layers'] )
            ? $args['layers']
            : [
                'Author layer'      => 'author',
                'Post layer'        => 'post',
                'Publication layer' => 'publication',
                'Other plugins'     => 'external',
                'External layer'    => 'external',
            ];

        $out = '';
        foreach ( $catalog as $group ) {
            if ( ! is_array( $group ) ) {
                continue;
            }

            $items  = isset( $group['items'] ) && is_array( $group['items'] ) ? $group['items'] : [];
            $mode   = isset( $group['live'] ) ? (string) $group['live'] : 'none';
            $layer  = isset( $group['layer'] ) ? (string) $group['layer'] : '';
            $lmod   = isset( $layers[ $layer ] ) ? (string) $layers[ $layer ] : sanitize_key( $layer );
            $rows   = '';
            $count  = count( $items );

            foreach ( $items as $item ) {
                if ( is_array( $item ) ) {
                    $rows .= $this->render_item( $item, $group, $mode, $context, $args );
                }
            }

            $reg    = isset( $group['register_file'] ) ? (string) $group['register_file'] : '';
            $access = isset( $group['access'] ) ? (string) $group['access'] : '';
            $key    = isset( $group['key'] ) ? (string) $group['key'] : sanitize_key( (string) ( $group['title'] ?? 'shortcodes' ) );
            $title  = isset( $group['title'] ) ? (string) $group['title'] : 'Shortcodes';

            $out .= '<details class="smpi-sc-card" data-ctx="' . esc_attr( $key ) . '" open>';
            $out .= '<summary class="smpi-sc-card-head"><span class="smpi-sc-chev" aria-hidden="true"></span><span class="smpi-sc-layer smpi-sc-layer--' . esc_attr( $lmod ) . '">' . esc_html( $layer ) . '</span><span class="smpi-sc-card-title">' . esc_html( $title ) . '</span><span class="smpi-sc-count">' . (int) $count . '</span></summary>';
            $out .= '<div class="smpi-sc-card-body">';
            if ( ! empty( $group['blurb'] ) ) {
                $out .= '<p class="smpi-sc-card-blurb">' . esc_html( (string) $group['blurb'] ) . '</p>';
            }
            $out .= $this->requirement_html( (string) ( $group['requires'] ?? '' ), (string) ( $group['requires_key'] ?? '' ), 'smpi-sc-card-req', $args );
            if ( '' !== $reg || '' !== $access ) {
                $out .= '<p class="smpi-sc-card-meta"><b>Registered in</b> <code>' . esc_html( $reg ) . '</code> &nbsp; <b>Used</b> ' . esc_html( $access ) . '</p>';
            }
            $out .= '<div class="smpi-sc-rows">' . $rows . '</div>';
            $out .= '</div></details>';
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    private function settings( array $args ): array {
        return [
            'values_id'          => isset( $args['values_id'] ) ? (string) $args['values_id'] : 'smpi-shortcode-user-values',
            'ajax_object'        => isset( $args['ajax_object'] ) ? (string) $args['ajax_object'] : 'smpiAdmin',
            'ajax_action'        => isset( $args['ajax_action'] ) ? (string) $args['ajax_action'] : '',
            'filter_search_id'   => isset( $args['filter_search_id'] ) ? (string) $args['filter_search_id'] : 'smpi-sc-filter-search',
            'author_search_id'   => isset( $args['author_search_id'] ) ? (string) $args['author_search_id'] : 'smpi-sc-author-search',
            'post_search_id'     => isset( $args['post_search_id'] ) ? (string) $args['post_search_id'] : 'smpi-sc-post-search',
            'filter_source'      => isset( $args['filter_source'] ) ? (string) $args['filter_source'] : 'smpi_shortcodes',
            'author_source'      => isset( $args['author_source'] ) ? (string) $args['author_source'] : 'users',
            'post_source'        => isset( $args['post_source'] ) ? (string) $args['post_source'] : 'posts',
            'post_type'          => isset( $args['post_type'] ) ? (string) $args['post_type'] : 'post',
            'show_author_search' => array_key_exists( 'show_author_search', $args ) ? (bool) $args['show_author_search'] : true,
            'show_post_search'   => array_key_exists( 'show_post_search', $args ) ? (bool) $args['show_post_search'] : true,
            'filter_placeholder' => isset( $args['filter_placeholder'] ) ? (string) $args['filter_placeholder'] : 'Search shortcodes by tag or description...',
            'author_placeholder' => isset( $args['author_placeholder'] ) ? (string) $args['author_placeholder'] : 'Preview author...',
            'post_placeholder'   => isset( $args['post_placeholder'] ) ? (string) $args['post_placeholder'] : 'Sample post...',
            'empty_text'         => isset( $args['empty_text'] ) ? (string) $args['empty_text'] : 'No shortcodes match that search.',
        ];
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function render_search_bar( array $settings ): string {
        ob_start();
        ?>
        <div class="smpi-sc-top">
            <div class="smpi-sc-search smpi-sc-search--filter"><?php ( new SmartSearchRenderer() )->render( [ 'id' => $settings['filter_search_id'], 'source' => $settings['filter_source'], 'min_chars' => 2, 'limit' => 12, 'label' => 'Search shortcodes', 'placeholder' => $settings['filter_placeholder'] ] ); ?></div>
            <?php if ( ! empty( $settings['show_author_search'] ) ) : ?>
                <div class="smpi-sc-search smpi-sc-search--author"><?php ( new SmartSearchRenderer() )->render( [ 'id' => $settings['author_search_id'], 'source' => $settings['author_source'], 'min_chars' => 2, 'label' => 'Preview author', 'placeholder' => $settings['author_placeholder'] ] ); ?></div>
            <?php endif; ?>
            <?php if ( ! empty( $settings['show_post_search'] ) ) : ?>
                <div class="smpi-sc-search smpi-sc-search--post"><?php ( new SmartSearchRenderer() )->render( [ 'id' => $settings['post_search_id'], 'source' => $settings['post_source'], 'post_type' => $settings['post_type'], 'min_chars' => 2, 'label' => 'Sample post', 'placeholder' => $settings['post_placeholder'] ] ); ?></div>
            <?php endif; ?>
            <span class="spinner smpi-sc-spin"></span>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,mixed> $group
     * @param array<string,mixed> $context
     * @param array<string,mixed> $args
     */
    private function render_item( array $item, array $group, string $mode, array $context, array $args ): string {
        $tag        = isset( $item['tag'] ) ? (string) $item['tag'] : '';
        $code       = isset( $item['code'] ) ? (string) $item['code'] : '[' . $tag . ']';
        $desc       = isset( $item['desc'] ) ? (string) $item['desc'] : (string) ( $item['description'] ?? '' );
        $type       = isset( $item['type'] ) ? (string) $item['type'] : '';
        $params     = isset( $item['params'] ) ? (string) $item['params'] : '';
        $detail     = isset( $item['detail'] ) && is_array( $item['detail'] ) ? $item['detail'] : [];
        $source     = isset( $detail['source'] ) ? (string) $detail['source'] : '';
        $variations = isset( $item['variations'] ) && is_array( $item['variations'] ) ? $item['variations'] : [];
        $var_live   = ! empty( $item['variations_live'] );
        $aliases    = isset( $item['aliases'] ) && is_array( $item['aliases'] ) ? $item['aliases'] : [];
        $legacy     = isset( $item['deprecated_versions'] ) && is_array( $item['deprecated_versions'] ) ? $item['deprecated_versions'] : [];
        $deprecated = ! empty( $item['deprecated'] );
        $instead    = isset( $item['use_instead'] ) ? (string) $item['use_instead'] : '';
        $requires   = isset( $item['requires'] ) ? (string) $item['requires'] : '';
        $attrs      = isset( $item['attrs_enum'] ) && is_array( $item['attrs_enum'] ) ? $item['attrs_enum'] : [];
        $has_params = '' !== $params && 'none' !== $params;
        $edit_url   = $this->edit_url( (string) ( $detail['edit'] ?? 'none' ), $context, $args );
        $filter     = strtolower( $tag . ' ' . $desc . ' ' . $source . ' ' . $params . ' ' . $type . ( $deprecated ? ' deprecated legacy' : '' ) . ( '' !== $requires ? ' requires' : '' ) );

        $row  = '<div class="smpi-sc-row' . ( $deprecated ? ' is-deprecated' : '' ) . '" data-filter="' . esc_attr( $filter ) . '">';
        $row .= '<p class="smpi-sc-desc">' . esc_html( $desc );
        if ( $deprecated && '' !== $instead ) {
            $row .= ' <span class="smpi-sc-use">Use ' . esc_html( $instead ) . ' instead.</span>';
        }
        $row .= '</p>';
        $row .= $this->requirement_html( $requires, (string) ( $item['requires_key'] ?? '' ), 'smpi-sc-req', $args );
        $row .= '<div class="smpi-sc-block"><div class="smpi-sc-line"><code class="smpi-sc-tag">' . esc_html( $code ) . '</code>';
        $row .= '<button type="button" class="smpi-sc-copy" data-copy="' . esc_attr( $code ) . '" aria-label="Copy shortcode">Copy</button>';
        if ( $deprecated ) {
            $row .= '<span class="smpi-sc-dep">Deprecated</span>';
        }
        $row .= '<span class="smpi-sc-actions">';
        if ( '' !== $edit_url ) {
            $row .= '<a class="smpi-sc-edit" href="' . esc_url( $edit_url ) . '" target="_blank" rel="noopener" title="Edit the underlying data">Edit</a>';
        }
        $row .= '<button type="button" class="smpi-sc-det">Details</button></span></div>';
        if ( $has_params ) {
            $row .= '<div class="smpi-sc-attrs"><span class="smpi-sc-k">Accepts</span>' . esc_html( $params ) . '</div>';
        }
        if ( $var_live && ! empty( $variations ) ) {
            $row .= '<div class="smpi-sc-vars">';
            foreach ( $variations as $variation ) {
                if ( ! is_array( $variation ) || empty( $variation['code'] ) ) {
                    continue;
                }
                $vc   = (string) $variation['code'];
                $row .= '<div class="smpi-sc-var"><code class="smpi-sc-vtag">' . esc_html( $vc ) . '</code><button type="button" class="smpi-sc-copy smpi-sc-copy--mini" data-copy="' . esc_attr( $vc ) . '">Copy</button><span class="smpi-sc-vlabel">' . esc_html( (string) ( $variation['label'] ?? '' ) ) . '</span><span class="smpi-sc-vout">' . $this->run_shortcode( $vc, $mode, $context, $args ) . '</span></div>';
            }
            $row .= '</div>';
        } elseif ( 'none' !== $mode ) {
            $row .= '<div class="smpi-sc-out"><span class="smpi-sc-k">Output</span>' . $this->run_shortcode( $code, $mode, $context, $args ) . '</div>';
        }
        $row .= '</div>';

        if ( ! empty( $attrs ) ) {
            $row .= '<details class="smpi-sc-attrs-card"><summary class="smpi-sc-attrs-head"><span class="smpi-sc-chev2" aria-hidden="true"></span>All field values<span class="smpi-sc-attrs-count">' . count( $attrs ) . '</span></summary><div class="smpi-sc-attrs-body">';
            foreach ( $attrs as $attr ) {
                if ( ! is_array( $attr ) || empty( $attr['field'] ) ) {
                    continue;
                }
                $field = (string) $attr['field'];
                $label = isset( $attr['label'] ) ? (string) $attr['label'] : $field;
                $acode = '[' . $tag . ' field="' . $field . '"]';
                $row  .= '<div class="smpi-sc-attr"><code class="smpi-sc-attr-tag">field=&quot;' . esc_html( $field ) . '&quot;</code><button type="button" class="smpi-sc-copy smpi-sc-copy--mini" data-copy="' . esc_attr( $acode ) . '">Copy</button><span class="smpi-sc-attr-label">' . esc_html( $label ) . '</span><span class="smpi-sc-attr-out">' . $this->run_shortcode( $acode, $mode, $context, $args ) . '</span></div>';
            }
            $row .= '</div></details>';
        }

        if ( ! $var_live && ! empty( $variations ) ) {
            $chips = '';
            foreach ( $variations as $variation ) {
                if ( ! is_array( $variation ) || empty( $variation['code'] ) ) {
                    continue;
                }
                $vc     = (string) $variation['code'];
                $vlabel = (string) ( $variation['label'] ?? $vc );
                $chips .= '<span class="smpi-sc-vchip" data-copy="' . esc_attr( $vc ) . '" title="' . esc_attr( $vc . ' - ' . $vlabel ) . '">' . esc_html( $vlabel ) . '</span>';
            }
            $row .= '<div class="smpi-sc-vars-line"><span class="smpi-sc-k">Variations</span>' . $chips . '</div>';
        }

        if ( ! empty( $aliases ) ) {
            $row .= '<div class="smpi-sc-alias"><span class="smpi-sc-k">Aliases</span>' . esc_html( implode( '    ', array_map( 'strval', $aliases ) ) ) . '</div>';
        }
        if ( ! empty( $legacy ) ) {
            $row .= '<div class="smpi-sc-alias"><span class="smpi-sc-k">Deprecated</span>' . esc_html( implode( '    ', array_map( 'strval', $legacy ) ) ) . '</div>';
        }

        $row .= '<div class="smpi-sc-detail">';
        if ( '' !== $source ) {
            $row .= '<div class="r"><b>Source</b> ' . esc_html( $source ) . '</div>';
        }
        if ( '' !== $requires ) {
            $row .= '<div class="r"><b>Requires</b> ' . esc_html( $requires ) . '</div>';
        }
        if ( $deprecated ) {
            $row .= '<div class="r"><b>Status</b> Deprecated' . ( '' !== $instead ? ' - use ' . esc_html( $instead ) . ' instead' : '' ) . '</div>';
        }
        $row .= '<div class="r"><b>Field</b> ' . esc_html( isset( $detail['field'] ) ? (string) $detail['field'] : '-' ) . ' &nbsp;&nbsp; <b>Group</b> ' . esc_html( isset( $detail['group'] ) ? (string) $detail['group'] : '-' ) . '</div>';
        $row .= '<div class="r"><b>Type</b> ' . esc_html( isset( $detail['type'] ) ? (string) $detail['type'] : '-' ) . ' &nbsp;&nbsp; <b>Plugin</b> ' . esc_html( isset( $detail['plugin'] ) ? (string) $detail['plugin'] : '-' ) . '</div>';
        $row .= '<div class="r"><b>Source file</b> <code>' . esc_html( isset( $detail['file'] ) ? (string) $detail['file'] : '-' ) . '</code></div>';
        if ( '' !== $edit_url ) {
            $row .= '<div class="r"><b>Edit page</b> <a href="' . esc_url( $edit_url ) . '" target="_blank" rel="noopener">' . esc_html( $edit_url ) . '</a></div>';
        }
        $row .= '</div>';
        $row .= '</div>';

        return $row;
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $args
     */
    private function run_shortcode( string $code, string $mode, array $context, array $args ): string {
        if ( isset( $args['run_callback'] ) && is_callable( $args['run_callback'] ) ) {
            return (string) call_user_func( $args['run_callback'], $code, $mode, $context );
        }

        if ( 'none' === $mode || ! function_exists( 'do_shortcode' ) ) {
            return '<span class="smpi-muted">External provider shortcode. Not executed here.</span>';
        }

        $run = $code;
        if ( 'author' === $mode && ! empty( $context['user_id'] ) ) {
            $run = preg_replace( '/\]$/', ' user_id="' . (int) $context['user_id'] . '"]', $code );
        } elseif ( 'post' === $mode && ! empty( $context['post_id'] ) ) {
            $run = preg_replace( '/\]$/', ' post_id="' . (int) $context['post_id'] . '"]', $code );
        }

        return do_shortcode( (string) $run );
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $args
     */
    private function edit_url( string $target, array $context, array $args ): string {
        if ( isset( $args['edit_url_callback'] ) && is_callable( $args['edit_url_callback'] ) ) {
            return (string) call_user_func( $args['edit_url_callback'], $target, $context );
        }

        return '';
    }

    /**
     * @param array<string,mixed> $args
     */
    private function requirement_html( string $text, string $key, string $class, array $args ): string {
        if ( '' === $text ) {
            return '';
        }

        $chip = '';
        if ( '' !== $key && isset( $args['requirement_state_callback'] ) && is_callable( $args['requirement_state_callback'] ) ) {
            $state = call_user_func( $args['requirement_state_callback'], $key );
            if ( is_array( $state ) ) {
                $enabled = ! empty( $state['enabled'] );
                $label   = isset( $state['label'] ) ? (string) $state['label'] : ( $enabled ? 'Enabled' : 'Disabled' );
                $chip    = ' <span class="smpi-sc-req-state ' . ( $enabled ? 'is-on' : 'is-off' ) . '">' . esc_html( $label ) . '</span>';
            } elseif ( is_bool( $state ) ) {
                $chip = ' <span class="smpi-sc-req-state ' . ( $state ? 'is-on' : 'is-off' ) . '">' . ( $state ? 'Enabled' : 'Disabled' ) . '</span>';
            }
        }

        return '<p class="' . esc_attr( $class ) . '"><span class="smpi-sc-req-badge">Requires</span>' . esc_html( $text ) . $chip . '</p>';
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function render_script( array $settings ): string {
        ob_start();
        ?>
        <script>
        (function(){
            if (window.smpiScBound) return; window.smpiScBound = true;
            var $ = window.jQuery; if (!$) return;
            var valuesId = <?php echo wp_json_encode( $settings['values_id'] ); ?>;
            var filterId = <?php echo wp_json_encode( $settings['filter_search_id'] ); ?>;
            var authorId = <?php echo wp_json_encode( $settings['author_search_id'] ); ?>;
            var postId = <?php echo wp_json_encode( $settings['post_search_id'] ); ?>;
            var ajaxObjectName = <?php echo wp_json_encode( $settings['ajax_object'] ); ?>;
            var ajaxAction = <?php echo wp_json_encode( $settings['ajax_action'] ); ?>;
            function filterValue(){ var el = document.querySelector("#" + filterId + " .hpc-smart-search-input"); return el ? (el.value || "") : ""; }
            function applyFilter(q){
                q = (q || "").toLowerCase().trim(); var total = 0;
                $(".smpi-sc-card").each(function(){
                    var vis = 0, card = this;
                    $(this).find(".smpi-sc-row").each(function(){
                        var ok = !q || (this.getAttribute("data-filter") || "").indexOf(q) !== -1;
                        this.style.display = ok ? "" : "none"; if (ok) vis++;
                    });
                    card.style.display = vis ? "" : "none";
                    if (q && vis) { card.setAttribute("open", ""); }
                    total += vis;
                });
                $(".smpi-sc-noresults").css("display", total ? "none" : "block");
            }
            function refresh(){
                if (!ajaxAction) return;
                var adminObject = window[ajaxObjectName] || {};
                var box = $("#" + valuesId);
                var uid = box.attr("data-user") || 0, pid = box.attr("data-post") || 0;
                $(".smpi-sc-spin").addClass("is-active");
                $.post(adminObject.ajaxUrl, { action: ajaxAction, nonce: adminObject.nonce, user_id: uid, post_id: pid })
                    .done(function(x){ if (x && x.success) { box.html(x.data.html || ""); applyFilter(filterValue()); } })
                    .always(function(){ $(".smpi-sc-spin").removeClass("is-active"); });
            }
            function copyText(v, b){
                var done = function(ok){ b.classList.remove("is-ok","is-err"); b.classList.add(ok ? "is-ok" : "is-err"); b.textContent = ok ? "✓" : "✗"; setTimeout(function(){ b.classList.remove("is-ok","is-err"); b.textContent = "Copy"; }, 1100); };
                if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(v).then(function(){ done(true); }, function(){ done(false); }); }
                else { try { var ta = document.createElement("textarea"); ta.value = v; document.body.appendChild(ta); ta.select(); var ok = document.execCommand("copy"); document.body.removeChild(ta); done(ok); } catch (e) { done(false); } }
            }
            document.addEventListener("hexa-search-selected", function(ev){
                var d = ev.detail || {}, cid = d.component_id, item = d.item || {};
                var box = document.getElementById(valuesId);
                if (!box) return;
                if (cid === authorId) { box.setAttribute("data-user", item.id || item.value || 0); refresh(); }
                else if (cid === postId) { box.setAttribute("data-post", item.id || item.value || 0); refresh(); }
                else if (cid === filterId) { applyFilter(String(item.value || "")); }
            });
            $(document).on("input", "#" + filterId + " .hpc-smart-search-input", function(){ applyFilter(this.value); });
            $(document).on("click", ".smpi-sc-det", function(){ $(this).closest(".smpi-sc-row").toggleClass("is-open"); });
            $(document).on("click", ".smpi-sc-copy", function(e){ e.preventDefault(); e.stopPropagation(); copyText(this.getAttribute("data-copy") || "", this); });
            $(document).on("click", ".smpi-sc-vchip", function(){ var v = this.getAttribute("data-copy") || this.textContent; if (navigator.clipboard) navigator.clipboard.writeText(v); var c = this, o = c.style.color; c.style.color = "#15803d"; setTimeout(function(){ c.style.color = o; }, 700); });
        })();
        </script>
        <?php
        return (string) ob_get_clean();
    }

    private function render_styles(): void {
        static $rendered = false;
        if ( $rendered ) {
            return;
        }
        $rendered = true;
        ?>
        <style>
            .smpi-sc{max-width:1020px}
            .smpi-sc h2{margin:0 0 4px}
            .smpi-sc-intro{margin:0 0 14px;color:#667085;font-size:13px;line-height:1.55;max-width:80ch}
            .smpi-sc-top{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0 0 18px;padding:9px 11px;background:#fff;border:1px solid #e3e5e9;border-radius:11px;box-shadow:0 4px 14px rgba(16,24,40,.06);position:sticky;top:34px;z-index:30}
            .smpi-sc-search{position:relative}
            .smpi-sc-search--filter{flex:1 1 300px;min-width:230px}
            .smpi-sc-search--author,.smpi-sc-search--post{flex:0 1 210px;min-width:160px}
            .smpi-sc-top .hpc-smart-search{margin:0}
            .smpi-sc-top .hpc-field{margin:0;display:block}
            .smpi-sc-top .hpc-field>span{display:none}
            .smpi-sc-top .hpc-smart-search-input{width:100%;box-sizing:border-box;font-size:13px;padding:8px 12px;border:1px solid #cfd3da;border-radius:8px;background:#fff;margin:0;line-height:1.3}
            .smpi-sc-top .hpc-smart-search-input:focus{border-color:#2563eb;outline:none;box-shadow:0 0 0 3px rgba(37,99,235,.12)}
            .smpi-sc-top .hpc-smart-search-status{display:none}
            .smpi-sc-top .hpc-smart-search-selected{display:none !important}
            .smpi-sc-top .hpc-smart-search-results{position:absolute;top:100%;left:0;right:0;z-index:40;background:#fff;border:1px solid #dcdfe4;border-radius:9px;box-shadow:0 12px 30px rgba(16,24,40,.16);margin-top:5px;max-height:330px;overflow:auto;padding:4px}
            .smpi-sc-top .hpc-smart-search-result{display:block;width:100%;text-align:left;border:0;background:none;border-radius:7px;padding:6px 10px;cursor:pointer;font-size:12.5px;height:auto;line-height:1.4}
            .smpi-sc-top .hpc-smart-search-result.active,.smpi-sc-top .hpc-smart-search-result:hover{background:#eff4ff}
            .smpi-sc-top .hpc-smart-search-result strong{display:block;color:#1f2937;font-weight:600;font-family:Menlo,Consolas,monospace;font-size:12px}
            .smpi-sc-top .hpc-smart-search-result span{display:block;color:#98a2b3;font-size:11px}
            .smpi-sc-top .hpc-smart-search-result em{display:none}
            .smpi-sc-spin{float:none;margin:0}
            .smpi-sc-card{border:1px solid #e3e5e9;border-radius:14px;background:#fff;margin:0 0 16px;overflow:hidden;box-shadow:0 1px 3px rgba(16,24,40,.04)}
            .smpi-sc-card>summary.smpi-sc-card-head{list-style:none;cursor:pointer;display:flex;align-items:center;gap:10px;padding:13px 18px;background:#f8f9fb;user-select:none}
            .smpi-sc-card[open]>summary.smpi-sc-card-head{border-bottom:1px solid #e7e9ee}
            .smpi-sc-card>summary::-webkit-details-marker{display:none}
            .smpi-sc-card>summary:hover{background:#f2f4f7}
            .smpi-sc-chev{width:8px;height:8px;border-right:2px solid #98a2b3;border-bottom:2px solid #98a2b3;transform:rotate(-45deg);transition:transform .15s;flex:0 0 auto}
            .smpi-sc-card[open]>summary .smpi-sc-chev{transform:rotate(45deg)}
            .smpi-sc-card-title{font-size:14px;font-weight:700;color:#101828}
            .smpi-sc-card-body{padding:0}
            .smpi-sc-card-blurb{margin:0;padding:11px 18px 0;font-size:12px;color:#667085;line-height:1.5;max-width:84ch}
            .smpi-sc-card-meta{margin:5px 0 0;padding:0 18px;font-size:10.5px;color:#98a2b3}
            .smpi-sc-card-meta code{background:#eef0f3;border-radius:4px;padding:1px 6px}
            .smpi-sc-count{font-size:11px;font-weight:700;color:#667085;background:#eceef1;border-radius:999px;padding:2px 9px}
            .smpi-sc-layer{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;border-radius:6px;padding:3px 9px;white-space:nowrap}
            .smpi-sc-layer--author{background:#dbeafe;color:#1e40af}
            .smpi-sc-layer--post{background:#dcfce7;color:#15803d}
            .smpi-sc-layer--publication{background:#ede9fe;color:#6d28d9}
            .smpi-sc-layer--external{background:#e5e7eb;color:#475467}
            .smpi-sc-rows{padding:6px 18px 16px}
            .smpi-sc-row{padding:14px 0}
            .smpi-sc-row + .smpi-sc-row{border-top:1px solid #eef0f3}
            .smpi-sc-desc{margin:0 0 10px;font-size:14px;color:#1f2937;line-height:1.5}
            .smpi-sc-use{color:#dc2626;font-weight:600;font-size:13px}
            .smpi-sc-block{background:#fafbfc;border:1px solid #eaecf0;border-radius:10px;padding:12px 14px}
            .smpi-sc-line{display:flex;align-items:center;gap:9px;flex-wrap:wrap}
            .smpi-sc-tag{font-family:Menlo,Consolas,monospace;font-size:13px;font-weight:700;color:#334155;background:#fff;border:1px solid #d5d9e0;border-radius:7px;padding:3px 10px;white-space:nowrap}
            .smpi-sc-row.is-deprecated .smpi-sc-tag{color:#b42318;background:#fef3f2;border-color:#fdab9e}
            .smpi-sc-dep{font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#fff;background:#dc2626;border-radius:5px;padding:3px 8px;white-space:nowrap}
            .smpi-sc-copy{font-size:11px;font-weight:600;color:#1d4ed8;background:#fff;border:1px solid #c7d2e4;border-radius:6px;padding:3px 10px;cursor:pointer;min-width:52px;text-align:center}
            .smpi-sc-copy:hover{background:#eff4ff;border-color:#1d4ed8}
            .smpi-sc-copy.is-ok{color:#15803d;border-color:#86c69b;background:#f0fdf4}
            .smpi-sc-copy.is-err{color:#dc2626;border-color:#f3b4ae;background:#fef2f2}
            .smpi-sc-actions{display:flex;align-items:center;gap:14px;margin-left:auto}
            .smpi-sc-edit,.smpi-sc-det{font-size:11px;color:#98a2b3;text-decoration:none;background:none;border:0;cursor:pointer;padding:0}
            .smpi-sc-edit:hover,.smpi-sc-det:hover{color:#1d4ed8}
            .smpi-sc-k{font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:#98a2b3;margin-right:9px;font-family:-apple-system,sans-serif}
            .smpi-sc-attrs{margin:10px 0 0;font-size:11.5px;color:#475467;font-family:Menlo,Consolas,monospace}
            .smpi-sc-out{margin:9px 0 0;font-size:10.5px;color:#667085;line-height:1.5;word-break:break-word}
            .smpi-sc-out code,.smpi-sc-vout code{background:#fff;border:1px solid #eaecf0;border-radius:4px;padding:1px 5px;color:#3c434a}
            .smpi-sc-vars{margin:10px 0 0;border-top:1px solid #eaecf0;padding-top:8px}
            .smpi-sc-var{display:flex;align-items:center;gap:9px;flex-wrap:wrap;padding:4px 0}
            .smpi-sc-vtag{font-family:Menlo,Consolas,monospace;font-size:11px;font-weight:600;color:#475467;white-space:nowrap;flex:0 0 auto;min-width:165px}
            .smpi-sc-vlabel{font-size:11px;color:#98a2b3;flex:0 0 auto;min-width:130px}
            .smpi-sc-vout{font-size:11px;color:#475467;flex:1 1 150px;word-break:break-word}
            .smpi-sc-copy--mini{min-width:0;font-size:10px;padding:1px 8px}
            .smpi-sc-vars-line{margin:10px 0 0;font-size:11px;color:#98a2b3;line-height:2}
            .smpi-sc-vchip{font-family:Menlo,Consolas,monospace;font-size:10.5px;color:#98a2b3;cursor:pointer;margin-right:14px;border-bottom:1px dashed #d0d5dd}
            .smpi-sc-vchip:hover{color:#1d4ed8;border-bottom-color:#1d4ed8}
            .smpi-sc-alias{margin:10px 0 0;font-size:10.5px;color:#98a2b3;font-family:Menlo,Consolas,monospace;word-break:break-word}
            .smpi-sc-detail{display:none;margin:10px 0 0;padding:11px 13px;background:#f8f9fb;border:1px solid #edeff2;border-radius:9px;font-size:11px;color:#5a616b}
            .smpi-sc-row.is-open .smpi-sc-detail{display:block}
            .smpi-sc-detail .r{margin:0 0 5px;word-break:break-word}
            .smpi-sc-detail .r:last-child{margin-bottom:0}
            .smpi-sc-detail b{color:#344054;font-weight:600}
            .smpi-sc-detail code{background:#eceef1;border-radius:4px;padding:1px 5px}
            .smpi-sc-detail a{color:#1d4ed8}
            .smpi-sc-req{margin:0 0 10px;font-size:12px;color:#9a3412;background:#fff7ed;border:1px solid #fdd9af;border-radius:8px;padding:6px 11px;line-height:1.45}
            .smpi-sc-card-req{margin:8px 18px 0;font-size:11.5px;color:#9a3412;background:#fff7ed;border:1px solid #fdd9af;border-radius:8px;padding:6px 11px;line-height:1.45}
            .smpi-sc-req-badge{font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#fff;background:#ea801c;border-radius:4px;padding:2px 6px;margin-right:8px}
            .smpi-sc-req-state{font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;border-radius:4px;padding:1px 7px}
            .smpi-sc-req-state.is-on{background:#dcfce7;color:#15803d}
            .smpi-sc-req-state.is-off{background:#fee2e2;color:#b42318}
            .smpi-sc-attrs-card{margin:10px 0 0;border:1px solid #e6e9ed;border-radius:9px;background:#fff;overflow:hidden}
            .smpi-sc-attrs-head{list-style:none;cursor:pointer;display:flex;align-items:center;gap:8px;padding:8px 12px;background:#f8f9fb;font-size:11.5px;font-weight:700;color:#475467;user-select:none}
            .smpi-sc-attrs-card>summary::-webkit-details-marker{display:none}
            .smpi-sc-attrs-head:hover{background:#f2f4f7}
            .smpi-sc-chev2{width:7px;height:7px;border-right:2px solid #98a2b3;border-bottom:2px solid #98a2b3;transform:rotate(-45deg);transition:transform .15s;flex:0 0 auto}
            .smpi-sc-attrs-card[open]>summary .smpi-sc-chev2{transform:rotate(45deg)}
            .smpi-sc-attrs-count{font-size:10px;font-weight:700;color:#667085;background:#eceef1;border-radius:999px;padding:1px 8px;margin-left:auto}
            .smpi-sc-attrs-body{padding:2px 0 6px}
            .smpi-sc-attr{display:flex;align-items:center;gap:9px;flex-wrap:wrap;padding:5px 12px;border-top:1px solid #f2f4f7}
            .smpi-sc-attr-tag{font-family:Menlo,Consolas,monospace;font-size:10.5px;font-weight:600;color:#475467;white-space:nowrap;flex:0 0 auto;min-width:175px}
            .smpi-sc-attr-label{font-size:11px;color:#98a2b3;flex:0 0 auto;min-width:150px}
            .smpi-sc-attr-out{font-size:10.5px;color:#475467;flex:1 1 140px;word-break:break-word}
            .smpi-sc-attr-out code{background:#f6f7f9;border:1px solid #eaecf0;border-radius:4px;padding:1px 5px}
            .smpi-sc-noresults{display:none;padding:16px;color:#98a2b3}
        </style>
        <?php
    }
}
