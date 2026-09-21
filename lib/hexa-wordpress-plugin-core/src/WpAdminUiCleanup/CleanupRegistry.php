<?php

namespace Hexa\PluginCore\WpAdminUiCleanup;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

final class CleanupRegistry implements ModuleInterface {
    private string $option_prefix;
    private string $ajax_action;
    private string $nonce_action;
    private string $nonce_field;
    private string $capability;
    private string $root_id;
    private array $sections;
    private array $options;
    private bool $callbacks_applied = false;

    public function __construct( array $config = [] ) {
        $this->option_prefix = (string) ( $config["option_prefix"] ?? "hpc_ui_cleanup_" );
        $this->ajax_action = $this->clean_key( (string) ( $config["ajax_action"] ?? "hpc_ui_cleanup_toggle" ) );
        $this->nonce_action = (string) ( $config["nonce_action"] ?? $this->ajax_action );
        $this->nonce_field = $this->clean_key( (string) ( $config["nonce_field"] ?? "nonce" ) );
        $this->capability = (string) ( $config["capability"] ?? "manage_options" );
        $this->root_id = $this->clean_key( (string) ( $config["root_id"] ?? "hpc-ui-cleanup" ) );
        $this->sections = $this->normalize_sections( $config["sections"] ?? [] );
        $this->options = $this->normalize_options( $config["options"] ?? [] );
    }

    public function register(): void {
        if ( "" !== $this->ajax_action ) {
            ( new CleanupAjaxController( $this ) )->register();
        }
        add_action( "admin_init", [ $this, "apply_active_callbacks" ], 1 );
        add_action( "admin_head", [ $this, "render_admin_screen_cleanup" ], 999 );
    }

    public function render(): void {
        ( new CleanupRenderer( $this ) )->render();
    }

    public function apply_active_callbacks(): void {
        if ( $this->callbacks_applied ) return;
        $this->callbacks_applied = true;
        foreach ( $this->options as $option ) {
            if ( ! $this->is_enabled( $option->key ) ) continue;
            if ( "footer_filter" === $option->mode ) $this->register_footer_filters( $option );
            if ( is_callable( $option->callback ) ) call_user_func( $option->callback, $option, $this );
        }
    }

    public function render_admin_screen_cleanup(): void {
        if ( ! function_exists( "is_admin" ) || ! is_admin() ) return;
        global $pagenow;
        $page = is_string( $pagenow ?? null ) ? $pagenow : "";
        $active = $this->active_options_for_page( $page );
        if ( [] === $active ) return;
        $css_selectors = [];
        $collapse_selectors = [];
        $js_headers = [];
        $js_input_ids = [];
        foreach ( $active as $option ) {
            if ( in_array( $option->mode, [ "css_hide", "postbox_hide" ], true ) ) $css_selectors = array_merge( $css_selectors, $option->selectors );
            if ( "postbox_collapse" === $option->mode ) $collapse_selectors = array_merge( $collapse_selectors, $option->selectors );
            $js_headers = array_merge( $js_headers, $option->js_headers );
            $js_input_ids = array_merge( $js_input_ids, $option->js_input_ids );
        }
        $css_selectors = array_values( array_unique( array_filter( $css_selectors ) ) );
        $collapse_selectors = array_values( array_unique( array_filter( $collapse_selectors ) ) );
        $js_headers = array_values( array_unique( array_filter( $js_headers ) ) );
        $js_input_ids = array_values( array_unique( array_filter( $js_input_ids ) ) );
        if ( [] !== $css_selectors ) {
            echo "<style id=" . esc_attr( $this->root_id . "-screen-css" ) . ">" . PHP_EOL;
            echo implode( "," . PHP_EOL, array_map( [ $this, "safe_css_selector" ], $css_selectors ) ) . "{display:none!important;}" . PHP_EOL;
            echo "</style>" . PHP_EOL;
        }
        if ( [] === $collapse_selectors && [] === $js_headers && [] === $js_input_ids ) return;
        $collapse_json = function_exists( "wp_json_encode" ) ? wp_json_encode( $collapse_selectors ) : json_encode( $collapse_selectors );
        $headers_json = function_exists( "wp_json_encode" ) ? wp_json_encode( $js_headers ) : json_encode( $js_headers );
        $inputs_json = function_exists( "wp_json_encode" ) ? wp_json_encode( $js_input_ids ) : json_encode( $js_input_ids );
        ?>
        <script id="<?php echo esc_attr( $this->root_id . "-screen-js" ); ?>">
        (function(){
            var collapseSelectors = <?php echo $collapse_json ?: "[]"; ?>;
            var headers = <?php echo $headers_json ?: "[]"; ?>;
            var inputs = <?php echo $inputs_json ?: "[]"; ?>;
            function hideElement(element){ if (element) element.style.setProperty("display", "none", "important"); }
            function collapsePostbox(selector){
                document.querySelectorAll(selector).forEach(function(node){
                    var box = node.classList && node.classList.contains("postbox") ? node : node.closest(".postbox");
                    if (!box) return;
                    box.classList.add("closed");
                    var inside = box.querySelector(":scope > .inside");
                    if (inside) inside.style.display = "none";
                });
            }
            function run(){
                collapseSelectors.forEach(collapsePostbox);
                headers.forEach(function(text){
                    document.querySelectorAll("h2").forEach(function(header){
                        if ((header.textContent || "").trim() !== text) return;
                        hideElement(header);
                        var next = header.nextElementSibling;
                        if (next && next.classList.contains("form-table")) hideElement(next);
                        var row = header.closest("tr");
                        if (row) hideElement(row);
                    });
                });
                inputs.forEach(function(id){
                    var field = document.getElementById(id);
                    if (!field) return;
                    var row = field.closest("tr");
                    hideElement(row || field);
                });
            }
            if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", run); else run();
            window.setTimeout(run, 300);
            window.setTimeout(run, 1000);
            if (window.MutationObserver) new MutationObserver(run).observe(document.documentElement, { childList: true, subtree: true });
        })();
        </script>
        <?php
    }

    public function is_enabled( string $key ): bool {
        $option = $this->option( $key );
        if ( ! $option ) return false;
        $missing = "__hpc_missing__";
        $value = function_exists( "get_option" ) ? get_option( $this->option_prefix . $key, $missing ) : $missing;
        if ( $missing === $value ) return $option->default;
        if ( is_bool( $value ) ) return $value;
        if ( is_numeric( $value ) ) return 1 === (int) $value;
        return in_array( strtolower( (string) $value ), [ "1", "true", "yes", "on" ], true );
    }

    public function update_enabled( string $key, bool $enabled ): bool {
        if ( ! $this->option( $key ) || ! function_exists( "update_option" ) ) return false;
        update_option( $this->option_prefix . $key, $enabled ? "1" : "0" );
        return true;
    }

    public function option( string $key ): ?CleanupOptionDefinition { return $this->options[ $this->clean_key( $key ) ] ?? null; }
    public function options(): array { return $this->options; }
    public function sections(): array { return $this->sections; }
    public function ajax_action(): string { return $this->ajax_action; }
    public function nonce_action(): string { return $this->nonce_action; }
    public function nonce_field(): string { return $this->nonce_field; }
    public function capability(): string { return $this->capability; }
    public function root_id(): string { return $this->root_id; }
    public function option_prefix(): string { return $this->option_prefix; }

    public function active_options_for_page( string $pagenow ): array {
        return array_values( array_filter( $this->options, fn( CleanupOptionDefinition $option ): bool => $this->is_enabled( $option->key ) && $option->applies_to_admin_page( $pagenow ) ) );
    }

    private function register_footer_filters( CleanupOptionDefinition $option ): void {
        $patterns = $option->footer_patterns;
        if ( [] !== $patterns ) {
            add_filter( "admin_footer_text", static function ( mixed $text ) use ( $patterns ): string {
                $text = is_string( $text ) ? $text : "";
                foreach ( $patterns as $pattern ) if ( false !== stripos( $text, $pattern ) ) return "";
                return $text;
            }, PHP_INT_MAX );
        }
        if ( $option->hide_update_footer ) add_filter( "update_footer", "__return_empty_string", PHP_INT_MAX );
    }

    private function normalize_sections( mixed $sections ): array {
        $normalized = [];
        if ( ! is_array( $sections ) ) return $normalized;
        foreach ( $sections as $key => $section ) {
            $id = $this->clean_key( (string) $key );
            if ( "" === $id ) continue;
            if ( is_string( $section ) ) $section = [ "title" => $section ];
            $section = is_array( $section ) ? $section : [];
            $normalized[ $id ] = [ "title" => (string) ( $section["title"] ?? $id ), "description" => (string) ( $section["description"] ?? "" ), "icon" => (string) ( $section["icon"] ?? "" ) ];
        }
        return $normalized;
    }

    private function normalize_options( mixed $options ): array {
        $normalized = [];
        if ( ! is_array( $options ) ) return $normalized;
        foreach ( $options as $key => $config ) {
            $config = is_array( $config ) ? $config : [];
            if ( ! isset( $config["key"] ) ) $config["key"] = is_string( $key ) ? $key : "";
            $option = CleanupOptionDefinition::from_array( $config );
            if ( "" !== $option->key ) $normalized[ $option->key ] = $option;
        }
        return $normalized;
    }

    private function clean_key( string $value ): string {
        if ( function_exists( "sanitize_key" ) ) return sanitize_key( $value );
        return preg_replace( "/[^a-z0-9_\-]/", "", strtolower( $value ) ) ?: "";
    }

    private function safe_css_selector( string $selector ): string {
        return preg_replace( "/[^#\.\[\]\(\)\*\=\^\$\~\|\:\,\s>\+\"\'a-zA-Z0-9_\-]/", "", $selector ) ?: "";
    }
}
