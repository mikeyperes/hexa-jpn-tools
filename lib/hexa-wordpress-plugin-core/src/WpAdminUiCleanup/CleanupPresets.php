<?php

namespace Hexa\PluginCore\WpAdminUiCleanup;

/**
 * Shared cleanup option definitions, so every host plugin offers the same
 * editor-screen toggles with the same behavior instead of copying them.
 *
 * Each method returns a config array for CleanupRegistry "options"; a host may
 * override any key (label, section, default, post_types) with array_merge().
 */
final class CleanupPresets {
    /** True when new posts get comments closed (Settings → Discussion). */
    public static function comments_closed(): bool {
        return function_exists( "get_option" ) && "closed" === get_option( "default_comment_status", "open" );
    }

    /** The core "Comments" box on post editor screens; removed automatically while comments are closed. */
    public static function comments_meta_box( array $overrides = [] ): array {
        return array_merge(
            [
                "label"        => "Comments Box",
                "description"  => "Removes the Comments box from post editor screens. Turns on automatically while comments are disabled.",
                "mode"         => "meta_box_remove",
                "meta_boxes"   => [ "commentsdiv" ],
                "auto_enabled" => [ self::class, "comments_closed" ],
                "auto_reason"  => "On automatically because comments are disabled on this site.",
                "section"      => "editor",
            ],
            $overrides
        );
    }

    /** The FIFU (Featured Image from URL) box on post editor screens. */
    public static function fifu_meta_box( array $overrides = [] ): array {
        return array_merge(
            [
                "label"       => "FIFU Box",
                "description" => "Removes the FIFU (Featured Image from URL) box from post editor screens.",
                "mode"        => "meta_box_remove",
                "meta_boxes"  => [ "featuredMediaMetaBox" ],
                "section"     => "editor",
            ],
            $overrides
        );
    }
}
