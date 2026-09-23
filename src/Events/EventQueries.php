<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Events;

use WP_Post;
use WP_Query;

final class EventQueries
{
    public const MAX_EVENTS = 200;

    public function __construct(private EventDates $dates)
    {
    }

    /** @return WP_Post[] */
    public function forPeriod(string $period, ?string $areaSlug = null, int $limit = self::MAX_EVENTS): array
    {
        [$start, $end] = $this->dates->calendarWindow($period);

        return $this->between($start, $end, $areaSlug, $limit);
    }

    /** @return WP_Post[] */
    public function nextDays(int $days, ?string $areaSlug = null, int $limit = self::MAX_EVENTS): array
    {
        $days = max(1, min(31, $days));
        [$start] = $this->dates->calendarWindow('today');
        $end = (new \DateTimeImmutable('@' . (string) $start))
            ->setTimezone($this->dates->timezone())
            ->modify('+' . $days . ' days')
            ->getTimestamp();

        return $this->between($start, $end, $areaSlug, $limit);
    }

    /**
     * Published events overlapping [$start, $end), soonest first.
     *
     * An event without an end timestamp counts as ending when it starts. One
     * indexed join per timestamp replaces the former nested meta_query, which
     * multiplied postmeta rows and could run for minutes.
     *
     * @return WP_Post[]
     */
    public function between(int $start, int $end, ?string $areaSlug = null, int $limit = self::MAX_EVENTS): array
    {
        global $wpdb;

        $areaSql = '';
        if ($areaSlug !== null && trim($areaSlug) !== '') {
            $term = get_term_by('slug', sanitize_title($areaSlug), 'area');
            if (!$term || is_wp_error($term)) {
                return [];
            }
            $areaSql = $wpdb->prepare(
                " AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} area WHERE area.post_id = p.ID AND area.meta_key = 'area' AND area.meta_value = %s)",
                (string) $term->term_id
            );
        }

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = 'start_date_timestamp'
             LEFT JOIN {$wpdb->postmeta} e ON e.post_id = p.ID AND e.meta_key = 'end_date_timestamp'
             WHERE p.post_type = 'event' AND p.post_status = 'publish'
               AND CAST(s.meta_value AS SIGNED) < %d
               AND CAST(COALESCE(NULLIF(e.meta_value, ''), s.meta_value) AS SIGNED) >= %d{$areaSql}
             GROUP BY p.ID
             ORDER BY MIN(CAST(s.meta_value AS SIGNED)) ASC, p.ID ASC
             LIMIT %d",
            $end,
            $start,
            max(1, min(self::MAX_EVENTS, $limit))
        ));

        $ids = array_map('intval', (array) $ids);
        if ($ids === []) {
            return [];
        }
        _prime_post_caches($ids, false, true);

        return array_values(array_filter(array_map('get_post', $ids), static fn ($post): bool => $post instanceof WP_Post));
    }

    public function upcoming(int $limit = 1, bool $featuredOnly = false): array
    {
        $metaQuery = [[
            'key' => 'start_date_timestamp',
            'value' => time(),
            'compare' => '>=',
            'type' => 'NUMERIC',
        ]];
        if ($featuredOnly) {
            $metaQuery[] = ['key' => 'featured_event', 'value' => '1', 'compare' => '='];
        }

        $query = new WP_Query([
            'post_type' => 'event',
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(20, $limit)),
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
            'meta_key' => 'start_date_timestamp',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
            'meta_query' => $metaQuery,
        ]);

        return $query->posts;
    }
}
