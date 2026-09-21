<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Events;

use WP_Query;

final class EventRelations
{
    private const MAX_RELATION_ROWS = 1000;
    private const LEGACY_BLOCK = '~\s*<!--\s*jpn-related-events:start\s*-->.*?<!--\s*jpn-related-events:end\s*-->\s*~is';

    public function __construct(private EventQueries $queries)
    {
    }

    public function register(): void
    {
        add_action('elementor/query/jpn_home_upcoming_events', [$this, 'configureUpcomingQuery']);
        add_filter('the_content', [$this, 'renderRelatedEvents'], 25);
    }

    public function configureUpcomingQuery($query): void
    {
        if (!$query instanceof WP_Query) {
            return;
        }

        $query->set('post_type', 'event');
        $query->set('post_status', 'publish');
        $query->set('meta_key', 'start_date_timestamp');
        $query->set('orderby', 'meta_value_num');
        $query->set('order', 'ASC');
        $query->set('ignore_sticky_posts', true);
        $query->set('meta_query', [[
            'key' => 'start_date_timestamp',
            'value' => time(),
            'compare' => '>=',
            'type' => 'NUMERIC',
        ]]);

        $excluded = $this->relatedUpcomingExclusions();
        if ($excluded !== []) {
            $query->set('post__not_in', array_values(array_unique(array_merge(
                array_map('intval', (array) $query->get('post__not_in')),
                $excluded
            ))));
        }
    }

    public function relatedUpcomingExclusions(): array
    {
        global $wpdb;

        static $excluded = null;
        if (is_array($excluded)) {
            return $excluded;
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ('related_events','related_event_links') ORDER BY meta_id DESC LIMIT %d",
                self::MAX_RELATION_ROWS
            ),
            ARRAY_A
        );

        $graph = [];
        foreach ((array) $rows as $row) {
            $eventId = (int) ($row['post_id'] ?? 0);
            if ($eventId <= 0) {
                continue;
            }
            $graph[$eventId] ??= [];
            foreach (self::parseRelatedIds($row['meta_value'] ?? '') as $relatedId) {
                $graph[$eventId][] = $relatedId;
                $graph[$relatedId] ??= [];
                $graph[$relatedId][] = $eventId;
            }
        }

        if ($graph === []) {
            return $excluded = [];
        }

        $ids = array_slice(array_map('intval', array_keys($graph)), 0, self::MAX_RELATION_ROWS);
        _prime_post_caches($ids, true, false);
        $timestamps = [];
        $eligible = [];
        $now = time();
        foreach ($ids as $id) {
            $post = get_post($id);
            $timestamp = (int) get_post_meta($id, 'start_date_timestamp', true);
            $timestamps[$id] = $timestamp;
            if ($post && $post->post_type === 'event' && $post->post_status === 'publish' && $timestamp >= $now) {
                $eligible[] = $id;
            }
        }

        return $excluded = self::excludedIdsFromGraph($graph, $timestamps, $eligible);
    }

    public static function excludedIdsFromGraph(array $graph, array $timestamps, array $eligibleIds): array
    {
        $eligible = array_fill_keys(array_map('intval', $eligibleIds), true);
        $visited = [];
        $excluded = [];

        foreach (array_keys($graph) as $startId) {
            $startId = (int) $startId;
            if (isset($visited[$startId])) {
                continue;
            }

            $stack = [$startId];
            $component = [];
            while ($stack !== []) {
                $id = (int) array_pop($stack);
                if (isset($visited[$id])) {
                    continue;
                }
                $visited[$id] = true;
                if (isset($eligible[$id])) {
                    $component[] = $id;
                }
                foreach ((array) ($graph[$id] ?? []) as $neighbor) {
                    if (!isset($visited[(int) $neighbor])) {
                        $stack[] = (int) $neighbor;
                    }
                }
            }

            if (count($component) < 2) {
                continue;
            }

            usort($component, static function (int $left, int $right) use ($timestamps): int {
                $leftTime = (int) ($timestamps[$left] ?? PHP_INT_MAX);
                $rightTime = (int) ($timestamps[$right] ?? PHP_INT_MAX);
                return $leftTime === $rightTime ? $left <=> $right : $leftTime <=> $rightTime;
            });
            array_shift($component);
            array_push($excluded, ...$component);
        }

        return array_values(array_unique(array_map('intval', $excluded)));
    }

    public static function parseRelatedIds(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode(wp_unslash($raw), true);
            $raw = is_array($decoded) ? $decoded : maybe_unserialize($raw);
        }
        if (!is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $item) {
            if (is_array($item)) {
                $item = $item['post_id'] ?? $item['id'] ?? null;
            }
            if (is_numeric($item) && (int) $item > 0) {
                $ids[] = (int) $item;
            }
        }

        return array_values(array_unique($ids));
    }

    public static function stripLegacyBlock(string $content): string
    {
        return trim((string) preg_replace(self::LEGACY_BLOCK, '', $content));
    }

    public function renderRelatedEvents(string $content): string
    {
        $postId = (int) get_the_ID();
        if ($postId <= 0 || get_post_type($postId) !== 'event') {
            return $content;
        }

        $content = self::stripLegacyBlock($content);
        $items = $this->relatedItems($postId);
        if ($items === []) {
            return $content;
        }

        wp_enqueue_style('hexa-jpn-frontend', HEXA_JPN_TOOLS_PLUGIN_URL . 'assets/frontend.css', [], HEXA_JPN_TOOLS_VERSION);
        $html = '<section class="jpn-related-events"><h2>' . esc_html__('Related events', 'hexa-jpn-tools') . '</h2><ul>';
        foreach ($items as $item) {
            $html .= '<li><a href="' . esc_url($item['permalink']) . '">' . esc_html($item['title']) . '</a></li>';
        }
        $html .= '</ul></section>';

        return rtrim($content) . "\n\n" . $html;
    }

    public function relatedItems(int $postId): array
    {
        $ids = self::parseRelatedIds(get_post_meta($postId, 'related_events', true));
        if ($ids === []) {
            $ids = self::parseRelatedIds(get_post_meta($postId, 'related_event_links', true));
        }

        $items = [];
        foreach (array_slice($ids, 0, 50) as $id) {
            $post = get_post($id);
            if (!$post || $post->post_type !== 'event' || $post->post_status !== 'publish') {
                continue;
            }
            $items[] = [
                'post_id' => $id,
                'title' => get_the_title($id),
                'permalink' => get_permalink($id),
            ];
        }

        return $items;
    }
}
