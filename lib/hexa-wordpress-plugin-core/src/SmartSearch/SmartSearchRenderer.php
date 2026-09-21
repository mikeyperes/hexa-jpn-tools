<?php

namespace Hexa\PluginCore\SmartSearch;

use Hexa\PluginCore\WpAdminComponents\CoreUi;

final class SmartSearchRenderer {
    public function render( array $args = [] ): void {
        CoreUi::render_assets();

        $id          = isset( $args['id'] ) ? sanitize_key( (string) $args['id'] ) : 'hpc-smart-search';
        $label       = isset( $args['label'] ) ? (string) $args['label'] : 'Smart Search';
        $placeholder = isset( $args['placeholder'] ) ? (string) $args['placeholder'] : 'Search posts, pages, or post types...';
        $source      = isset( $args['source'] ) ? sanitize_key( (string) $args['source'] ) : 'posts';
        $post_type   = isset( $args['post_type'] ) ? sanitize_key( (string) $args['post_type'] ) : 'any';
        $min_chars   = isset( $args['min_chars'] ) ? max( 1, (int) $args['min_chars'] ) : 2;
        $limit       = isset( $args['limit'] ) ? min( 50, max( 1, (int) $args['limit'] ) ) : 8;
        $value       = isset( $args['value'] ) ? (string) $args['value'] : '';
        $selected_name = isset( $args['selected_name'] ) ? (string) $args['selected_name'] : '';
        $selected_subtitle = isset( $args['selected_subtitle'] ) ? (string) $args['selected_subtitle'] : '';
        $input_name  = isset( $args['input_name'] ) ? sanitize_key( (string) $args['input_name'] ) : '';
        $class       = isset( $args['class'] ) ? sanitize_html_class( (string) $args['class'] ) : '';
        $ajax_url    = function_exists( 'admin_url' ) ? admin_url( 'admin-ajax.php' ) : '';
        $nonce       = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( 'hexa_plugin_core_smart_search' ) : '';
        ?>
        <div id="<?php echo esc_attr( $id ); ?>" class="hpc-smart-search <?php echo esc_attr( $class ); ?>" data-source="<?php echo esc_attr( $source ); ?>" data-post-type="<?php echo esc_attr( $post_type ); ?>" data-min-chars="<?php echo esc_attr( (string) $min_chars ); ?>" data-limit="<?php echo esc_attr( (string) $limit ); ?>" data-ajax-url="<?php echo esc_url( $ajax_url ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
            <label class="hpc-field">
                <span><?php echo esc_html( $label ); ?></span>
                <input type="search" class="hpc-smart-search-input" placeholder="<?php echo esc_attr( $placeholder ); ?>" autocomplete="off" value="<?php echo esc_attr( $selected_name ); ?>">
            </label>
            <input type="hidden" class="hpc-smart-search-value"<?php echo '' !== $input_name ? ' name="' . esc_attr( $input_name ) . '"' : ''; ?> value="<?php echo esc_attr( $value ); ?>">
            <div class="hpc-smart-search-status"><?php echo '' !== $value ? esc_html( 'Selected: ' . $selected_name ) : 'Type at least ' . esc_html( (string) $min_chars ) . ' characters.'; ?></div>
            <div class="hpc-smart-search-results" hidden></div>
            <div class="hpc-smart-search-selected"<?php echo '' === $value ? ' hidden' : ''; ?>><?php if ( '' !== $value ) : ?><span class="hpc-pill success">Selected</span><strong><?php echo esc_html( $selected_name ); ?></strong><span><?php echo esc_html( $selected_subtitle ); ?></span><?php endif; ?></div>
        </div>
        <script>
        (function(){
            var root = document.getElementById('<?php echo esc_js( $id ); ?>');
            if (!root) return;
            var input = root.querySelector('.hpc-smart-search-input');
            var hidden = root.querySelector('.hpc-smart-search-value');
            var status = root.querySelector('.hpc-smart-search-status');
            var resultsBox = root.querySelector('.hpc-smart-search-results');
            var selectedBox = root.querySelector('.hpc-smart-search-selected');
            var timer = null;
            var controller = null;
            var active = -1;
            var items = [];
            function clearResults(message) {
                items = [];
                active = -1;
                resultsBox.hidden = true;
                resultsBox.innerHTML = '';
                status.textContent = message || '';
            }
            function renderResults() {
                resultsBox.innerHTML = '';
                if (!items.length) {
                    clearResults('No matches.');
                    return;
                }
                items.forEach(function(item, index) {
                    var button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'hpc-smart-search-result' + (index === active ? ' active' : '');
                    button.innerHTML = '<strong></strong><span></span><em></em>';
                    button.querySelector('strong').textContent = item.name || 'Untitled';
                    button.querySelector('span').textContent = item.subtitle || '';
                    button.querySelector('em').textContent = item.id ? '#' + item.id : '';
                    button.addEventListener("mouseenter", function(){ active = index; Array.prototype.slice.call(resultsBox.children).forEach(function(child, childIndex){ child.classList.toggle("active", childIndex === active); }); });
                    button.addEventListener('click', function(){ selectItem(index); });
                    resultsBox.appendChild(button);
                });
                resultsBox.hidden = false;
                status.textContent = items.length + ' result' + (items.length === 1 ? '' : 's');
            }
            function selectItem(index) {
                var item = items[index];
                if (!item) return;
                hidden.value = item.value || item.id || '';
                input.value = item.name || '';
                selectedBox.hidden = false;
                selectedBox.innerHTML = '<span class="hpc-pill success">Selected</span><strong></strong><span></span>';
                selectedBox.querySelector('strong').textContent = item.name || '';
                selectedBox.querySelector('span:last-child').textContent = item.subtitle || '';
                clearResults('Selected: ' + (item.name || 'item'));
                root.dispatchEvent(new CustomEvent('hexa-search-selected', { bubbles: true, detail: { item: item, component_id: root.id } }));
            }
            function search() {
                var query = (input.value || '').trim();
                if (query.length < Number(root.dataset.minChars || 2)) {
                    clearResults('Type at least ' + (root.dataset.minChars || 2) + ' characters.');
                    return;
                }
                if (controller) controller.abort();
                controller = new AbortController();
                status.textContent = 'Searching...';
                var params = new URLSearchParams();
                params.set('action', 'hexa_plugin_core_smart_search');
                params.set('_ajax_nonce', root.dataset.nonce || '');
                params.set('source', root.dataset.source || 'posts');
                params.set('post_type', root.dataset.postType || 'any');
                params.set('limit', root.dataset.limit || '8');
                params.set('q', query);
                fetch((root.dataset.ajaxUrl || '') + '?' + params.toString(), { credentials: 'same-origin', signal: controller.signal })
                    .then(function(response){ return response.json(); })
                    .then(function(payload){
                        var data = payload && payload.data ? payload.data : {};
                        items = Array.isArray(data.results) ? data.results : [];
                        active = items.length ? 0 : -1;
                        renderResults();
                    })
                    .catch(function(error){
                        if (error && error.name === 'AbortError') return;
                        clearResults('Search failed.');
                    });
            }
            input.addEventListener('input', function(){
                window.clearTimeout(timer);
                timer = window.setTimeout(search, 250);
            });
            input.addEventListener('keydown', function(event){
                if (!items.length) return;
                if (event.key === 'ArrowDown') { event.preventDefault(); active = Math.min(items.length - 1, active + 1); renderResults(); }
                if (event.key === 'ArrowUp') { event.preventDefault(); active = Math.max(0, active - 1); renderResults(); }
                if (event.key === 'Enter') { event.preventDefault(); selectItem(active); }
                if (event.key === 'Escape') { clearResults('Search closed.'); }
            });
        })();
        </script>
        <?php
    }
}
