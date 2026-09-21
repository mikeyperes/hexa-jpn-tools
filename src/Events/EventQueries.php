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

    /** @return WP_Post[] */
    public function between(int $start, int $end, ?string $areaSlug = null, int $limit = self::MAX_EVENTS): array
    {
        $metaQuery = [
            'relation' => 'AND',
            [
                'key' => 'start_date_timestamp',
                'value' => $end,
                'compare' => '<',
                'type' => 'NUMERIC',
            ],
            [
                'relation' => 'OR',
                [
                    'key' => 'end_date_timestamp',
                    'value' => $start,
                    'compare' => '>=',
                    'type' => 'NUMERIC',
                ],
                [
                    'relation' => 'AND',
                    ['key' => 'end_date_timestamp', 'compare' => 'NOT EXISTS'],
                    ['key' => 'start_date_timestamp', 'value' => $start, 'compare' => '>=', 'type' => 'NUMERIC'],
                ],
                [
                    'relation' => 'AND',
                    ['key' => 'end_date_timestamp', 'value' => '', 'compare' => '='],
                    ['key' => 'start_date_timestamp', 'value' => $start, 'compare' => '>=', 'type' => 'NUMERIC'],
                ],
            ],
        ];

        if ($areaSlug !== null && trim($areaSlug) !== '') {
            $term = get_term_by('slug', sanitize_title($areaSlug), 'area');
            if (!$term || is_wp_error($term)) {
                return [];
            }
            $metaQuery[] = ['key' => 'area', 'value' => (int) $term->term_id, 'compare' => '=', 'type' => 'NUMERIC'];
        }

        $query = new WP_Query([
            'post_type' => 'event',
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(self::MAX_EVENTS, $limit)),
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
            'meta_key' => 'start_date_timestamp',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
            'meta_query' => $metaQuery,
        ]);

        return array_values(array_filter($query->posts, static fn ($post): bool => $post instanceof WP_Post));
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
