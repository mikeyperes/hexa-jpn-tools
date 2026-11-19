<?php
namespace Hexa\PluginCore\PublicComponents;

/** Styles survive discarded shortcode expansions and WordPress deduplicates each handle. */
final class InlineStyles {
    private static array $handles = [];
    public static function enqueue(string $handle, string $css): void {
        if ($css === '') return;
        if (!wp_style_is($handle, 'registered')) {
            wp_register_style($handle, false, [], null);
            wp_add_inline_style($handle, $css);
        }
        wp_enqueue_style($handle);
        if (!isset(self::$handles[$handle])) {
            self::$handles[$handle] = true;
            // A shortcode rendered after wp_head still needs its style printed.
            add_action('wp_footer', [self::class, 'print_pending'], 5);
            add_action('admin_footer', [self::class, 'print_pending'], 5);
        }
    }
    public static function print_pending(): void {
        wp_print_styles(array_keys(self::$handles));
    }
}
