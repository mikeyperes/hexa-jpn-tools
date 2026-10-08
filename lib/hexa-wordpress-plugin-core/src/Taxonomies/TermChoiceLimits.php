<?php

namespace Hexa\PluginCore\Taxonomies;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

/**
 * Limits how many terms of a taxonomy a user may give a post, for users who
 * lack a capability (staff keep full control). A limit of 1 on a hierarchical
 * taxonomy shows radio buttons; other limits stop the picker at the limit. The
 * limit is also enforced on save, so the editor UI cannot be bypassed.
 *
 * Rules: [ "taxonomy" => "category", "max" => 1, "capability" => "edit_others_posts", "post_types" => [ "post" ] ]
 * A rule with "max" 0 is ignored.
 */
final class TermChoiceLimits implements ModuleInterface {
    /** @var array<int,array{taxonomy:string,max:int,capability:string,post_types:array<int,string>}> */
    private array $rules = [];
    private bool $saving = false;

    public function __construct( array $rules ) {
        foreach ( $rules as $rule ) {
            $taxonomy = sanitize_key( (string) ( $rule["taxonomy"] ?? "" ) );
            $max = max( 0, (int) ( $rule["max"] ?? 0 ) );
            if ( "" === $taxonomy || 0 === $max ) continue;
            $this->rules[] = [
                "taxonomy" => $taxonomy,
                "max" => $max,
                "capability" => (string) ( $rule["capability"] ?? "manage_options" ),
                "post_types" => array_values( array_filter( array_map( "strval", (array) ( $rule["post_types"] ?? [] ) ) ) ),
            ];
        }
    }

    public function register(): void {
        if ( [] === $this->rules ) return;
        add_action( "save_post", [ $this, "enforce" ], 99, 2 );
        add_action( "admin_footer-post.php", [ $this, "editor_script" ] );
        add_action( "admin_footer-post-new.php", [ $this, "editor_script" ] );
    }

    /** @return array<int,array{taxonomy:string,max:int,capability:string,post_types:array<int,string>}> */
    public function rules_for( string $post_type ): array {
        return array_values( array_filter( $this->rules, static fn( array $rule ): bool =>
            ( [] === $rule["post_types"] || in_array( $post_type, $rule["post_types"], true ) )
            && ! current_user_can( $rule["capability"] )
        ) );
    }

    public function enforce( int $post_id, \WP_Post $post ): void {
        if ( $this->saving || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
        foreach ( $this->rules_for( $post->post_type ) as $rule ) {
            $ids = wp_get_object_terms( $post_id, $rule["taxonomy"], [ "fields" => "ids", "orderby" => "term_order" ] );
            if ( is_wp_error( $ids ) || count( $ids ) <= $rule["max"] ) continue;
            $this->saving = true;
            wp_set_object_terms( $post_id, array_slice( array_map( "intval", $ids ), 0, $rule["max"] ), $rule["taxonomy"], false );
            $this->saving = false;
        }
    }

    public function editor_script(): void {
        $screen = function_exists( "get_current_screen" ) ? get_current_screen() : null;
        $rules = $screen ? $this->rules_for( (string) $screen->post_type ) : [];
        if ( [] === $rules ) return;
        $payload = array_map( static fn( array $rule ): array => [
            "taxonomy" => $rule["taxonomy"],
            "max" => $rule["max"],
            "hierarchical" => is_taxonomy_hierarchical( $rule["taxonomy"] ),
        ], $rules );
        ?>
        <script id="hpc-term-choice-limits">
        (function(){
            var rules = <?php echo wp_json_encode( $payload ); ?>;
            function single(tax){
                var lists = document.querySelectorAll("#" + tax + "checklist, #" + tax + "checklist-pop");
                lists.forEach(function(list){
                    list.querySelectorAll('input[type="checkbox"]').forEach(function(box){ box.type = "radio"; });
                });
                document.addEventListener("change", function(event){
                    var input = event.target;
                    if (!input.matches || !input.matches("#" + tax + "checklist input, #" + tax + "checklist-pop input")) return;
                    document.querySelectorAll("#" + tax + "checklist input, #" + tax + "checklist-pop input").forEach(function(other){
                        if (other !== input && other.value === input.value) other.checked = input.checked;
                        else if (other !== input && input.checked) other.checked = false;
                    });
                });
            }
            function limitTags(tax, max){
                var box = document.getElementById("tagsdiv-" + tax);
                if (!box) return;
                var field = box.querySelector(".newtag"), add = box.querySelector(".tagadd"), note = document.createElement("p");
                note.className = "howto hpc-term-limit-note";
                box.querySelector(".inside").appendChild(note);
                function count(){ return box.querySelectorAll(".tagchecklist > li").length; }
                function update(){
                    var full = count() >= max;
                    var text = full ? "Limit reached: up to " + max + " tags." : "Up to " + max + " tags.";
                    if (field) field.disabled = full;
                    if (add) add.disabled = full;
                    if (note.textContent !== text) note.textContent = text;
                }
                var list = box.querySelector(".tagchecklist");
                if (list) new MutationObserver(update).observe(list, { childList: true });
                update();
            }
            rules.forEach(function(rule){
                if (rule.hierarchical && rule.max === 1) single(rule.taxonomy);
                else if (!rule.hierarchical) limitTags(rule.taxonomy, rule.max);
            });
        })();
        </script>
        <?php
    }
}
