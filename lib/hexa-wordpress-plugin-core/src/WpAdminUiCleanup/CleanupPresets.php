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

    /** Rank Math's "Lock Modified Date" switch in the Publish box, through Rank Math's own filter. */
    public static function rankmath_lock_modified_date( array $overrides = [] ): array {
        return array_merge(
            [
                "label"       => "Rank Math Lock Modified Date",
                "description" => "Removes Rank Math's Lock Modified Date switch from the Publish box.",
                "mode"        => "callback",
                "callback"    => [ self::class, "disable_rankmath_lock_modified_date" ],
                "section"     => "editor",
            ],
            $overrides
        );
    }

    public static function disable_rankmath_lock_modified_date(): void {
        add_filter( "rank_math/lock_modified_date", "__return_false", PHP_INT_MAX );
    }

    /** Every LiteSpeed element WordPress shows outside its own settings pages: the editor box, Media Library columns and admin-bar menu. */
    public static function litespeed_ui( array $overrides = [] ): array {
        return array_merge(
            [
                "label"           => "LiteSpeed Editor Box, Columns & Menu",
                "description"     => "Removes the LiteSpeed box from post editor screens, the LiteSpeed columns from the Media Library and the LiteSpeed admin-bar menu.",
                "mode"            => "meta_box_remove",
                "meta_boxes"      => [ "litespeed_meta_boxes" ],
                "columns"         => [ "imgoptm", "lqip" ],
                "column_hooks"    => [ "manage_media_columns" ],
                "admin_bar_nodes" => [ "litespeed-menu" ],
                "section"         => "editor",
            ],
            $overrides
        );
    }
}
