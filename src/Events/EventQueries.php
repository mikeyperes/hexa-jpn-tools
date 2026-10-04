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

    /**
     * Complete paginated ongoing/upcoming area results for a selected map location.
     * Area scope spans all hosts; missing area falls back to the selected host.
     * Two indexed date joins match between(), with a count and bounded page.
     * Date-only endings include their stored last local day; timed endings do not.
     *
     * @return array{posts:WP_Post[],total:int,page:int}
     */
    public function pageBetween(int $from, int $to, int $areaId, int $hostId, int $perPage, int $page): array
    {
        global $wpdb;

        $perPage = max(1, min(50, $perPage));
        if ($areaId <= 0 && $hostId <= 0) {
            return ['posts' => [], 'total' => 0, 'page' => 1];
        }
        $scope = $areaId > 0
            ? $wpdb->prepare(
                "EXISTS (SELECT 1 FROM {$wpdb->term_relationships} rel
                    INNER JOIN {$wpdb->term_taxonomy} tax ON tax.term_taxonomy_id = rel.term_taxonomy_id
                    WHERE rel.object_id = p.ID AND tax.taxonomy = 'area' AND tax.term_id = %d)",
                $areaId
            )
            : $wpdb->prepare(
                "(EXISTS (SELECT 1 FROM {$wpdb->postmeta} host WHERE host.post_id = p.ID
                    AND host.meta_key = 'event_host' AND host.meta_value = %s)
                    OR (p.post_author = %d AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} host
                        WHERE host.post_id = p.ID AND host.meta_key = 'event_host' AND CAST(host.meta_value AS UNSIGNED) > 0)))",
                (string) $hostId,
                $hostId
            );
        $base = $wpdb->prepare(
            "FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = 'start_date_timestamp'
             LEFT JOIN {$wpdb->postmeta} e ON e.post_id = p.ID AND e.meta_key = 'end_date_timestamp'
             WHERE p.post_type = 'event' AND p.post_status = 'publish' AND p.post_password = ''
               AND CAST(s.meta_value AS SIGNED) > 0 AND CAST(s.meta_value AS SIGNED) < %d
               AND GREATEST(CAST(s.meta_value AS SIGNED), CAST(COALESCE(NULLIF(e.meta_value, ''), s.meta_value) AS SIGNED)) >=
                   CASE WHEN EXISTS (SELECT 1 FROM {$wpdb->postmeta} precision_meta
                       WHERE precision_meta.post_id = p.ID AND precision_meta.meta_key = 'start_date_precision' AND precision_meta.meta_value = 'date')
                       THEN %d ELSE %d END
               AND {$scope}",
            $to,
            EventDates::startOfToday($from),
            $from
        );
        $total = (int) $wpdb->get_var("SELECT COUNT(DISTINCT p.ID) {$base}");
        $page = max(1, min(max(1, (int) ceil($total / $perPage)), $page));
        if ($total === 0) {
            return ['posts' => [], 'total' => 0, 'page' => 1];
        }
        $ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID {$base} GROUP BY p.ID ORDER BY MIN(CAST(s.meta_value AS SIGNED)) ASC, p.ID ASC LIMIT %d OFFSET %d",
            $perPage,
            ($page - 1) * $perPage
        )));
        _prime_post_caches($ids, true, true);
        $posts = array_values(array_filter(array_map('get_post', $ids), static fn ($post): bool => $post instanceof WP_Post));

        return ['posts' => $posts, 'total' => $total, 'page' => $page];
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
