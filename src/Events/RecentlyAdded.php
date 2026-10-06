<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Events;

/**
 * The one "recently added" rule: an event first published on jpnmiami.com
 * within the last HOURS hours (its WordPress publish time, so later edits do
 * not restart the window). Every listing asks this class, and every HTML
 * marker carries its expiry so assets/recently-added.js removes it from a
 * cached page once the window has passed.
 */
final class RecentlyAdded
{
    public const HOURS = 24;
    public const LABEL = '🆕 NEW · Recently added';
    public const SHORT_LABEL = '🆕 NEW';

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function enqueue(): void
    {
        wp_enqueue_style('hexa-jpn-recently-added', HEXA_JPN_TOOLS_PLUGIN_URL . 'assets/recently-added.css', [], HEXA_JPN_TOOLS_VERSION);
        wp_enqueue_script('hexa-jpn-recently-added', HEXA_JPN_TOOLS_PLUGIN_URL . 'assets/recently-added.js', [], HEXA_JPN_TOOLS_VERSION, true);
    }

    public static function windowSeconds(): int
    {
        return max(1, (int) apply_filters('hexa_jpn_recent_hours', self::HOURS)) * HOUR_IN_SECONDS;
    }

    /** Unix time the marker expires, or 0 when the event is not recently added. */
    public static function until(int $eventId, ?int $now = null): int
    {
        $post = $eventId > 0 ? get_post($eventId) : null;
        if (!$post || $post->post_type !== 'event' || $post->post_status !== 'publish') {
            return 0;
        }

        $publishedAt = (int) get_post_time('U', true, $post);
        $until = $publishedAt + self::windowSeconds();

        return $publishedAt > 0 && $until > ($now ?? time()) ? $until : 0;
    }

    public static function is(int $eventId, ?int $now = null): bool
    {
        return self::until($eventId, $now) > 0;
    }

    /** Self-expiring marker element; empty when the event is not recently added. */
    public static function marker(int $eventId, string $class = 'jpn-new', bool $short = false): string
    {
        $until = self::until($eventId);

        return $until > 0
            ? '<span class="' . esc_attr($class) . '" data-jpn-new-until="' . esc_attr((string) $until) . '">' . esc_html($short ? self::SHORT_LABEL : self::LABEL) . '</span>'
            : '';
    }
}
