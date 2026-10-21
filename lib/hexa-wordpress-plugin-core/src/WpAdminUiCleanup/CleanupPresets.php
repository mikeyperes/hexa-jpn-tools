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

    /**
     * Admin notices for clients: before notices print, removes every notice callback
     * from plugins outside ClientFacingPlugins (SEO, builders, security, donation
     * banners). WordPress's own notices and our plugins' customer notices stay.
     */
    public static function client_notices( array $overrides = [] ): array {
        return array_merge(
            [
                "label"       => "Plugin Notices for Non-Administrators",
                "description" => "Users below administrator only see notices from WordPress and the client-facing plugins (Hexa, HWS, ACF, avatars). Other plugins' notices, such as Rank Math's or donation banners, never reach them.",
                "mode"        => "callback",
                "on_label"    => "Removed",
                "audience"    => "non_admins",
                "callback"    => [ self::class, "apply_client_notices" ],
                "section"     => "wordpress",
            ],
            $overrides
        );
    }

    public static function apply_client_notices(): void {
        add_action( "in_admin_header", static function (): void {
            foreach ( [ "admin_notices", "all_admin_notices", "user_admin_notices", "network_admin_notices" ] as $hook ) {
                ClientFacingPlugins::strip( $hook );
            }
        }, PHP_INT_MAX );
    }

    /**
     * The profile page for clients: staff-only sections removed at the source.
     * Plugin sections outside ClientFacingPlugins (Rank Math SEO, Elementor AI) are
     * unhooked, including their contact fields and footer scripts on Profile, Application Passwords are turned off through WordPress's own filter,
     * the color picker through its own hook, and the remaining Personal Options rows,
     * which WordPress prints without a hook, are hidden by WordPress's row classes.
     */
    public static function client_profile( array $overrides = [] ): array {
        return array_merge(
            [
                "label"       => "Profile Page for Non-Administrators",
                "description" => "Users below administrator see only their own details on Profile: no Personal Options, Application Passwords, or sections from staff plugins such as Rank Math SEO or Elementor AI.",
                "mode"        => "css_hide",
                "on_label"    => "Removed",
                "audience"    => "non_admins",
                "admin_pages" => [ "profile.php", "user-edit.php" ],
                "selectors"   => [
                    "#your-profile h2:has(+ .form-table .user-infinite-scrolling-wrap)",
                    "#your-profile .form-table:has(.user-infinite-scrolling-wrap)",
                ],
                "callback"    => [ self::class, "apply_client_profile" ],
                "section"     => "wordpress",
            ],
            $overrides
        );
    }

    public static function apply_client_profile(): void {
        add_filter( "wp_is_application_passwords_available_for_user", "__return_false", PHP_INT_MAX );
        foreach ( [ "load-profile.php", "load-user-edit.php" ] as $screen ) {
            add_action( $screen, static function (): void {
                remove_action( "admin_color_scheme_picker", "admin_color_scheme_picker" );
                foreach ( [ "personal_options", "profile_personal_options", "show_user_profile", "edit_user_profile", "user_contactmethods", "admin_footer" ] as $hook ) {
                    ClientFacingPlugins::strip( $hook );
                }
            }, PHP_INT_MAX );
        }
    }
}
