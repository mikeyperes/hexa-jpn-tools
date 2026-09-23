<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Events;

use Hexa\PluginCore\Calendar\CalendarRegistry;

/**
 * JPN event calendar: registers the `jpn_events` profile with Hexa WP Core's
 * Calendar and supplies JPN-specific item data and markup.
 *
 * Core owns the month grid, query, filters, navigation, caching, and
 * interaction. This class owns which event fields drive the calendar, the
 * JPN filter vocabulary, and how one event reads inside a day.
 */
final class EventCalendar
{
    public const PROFILE = 'jpn_events';

    public function register(): void
    {
        if (!class_exists(CalendarRegistry::class)) {
            return;
        }

        CalendarRegistry::register(self::PROFILE, [
            'post_types' => ['event'],
            'start' => ['meta' => 'start_date_timestamp', 'format' => 'timestamp'],
            // Date-only events store their last day at local midnight; Core's end_midnight 'auto' keeps that day for all-day events.
            'end' => 'end_date_timestamp',
            'timezone' => EventDates::TIMEZONE,
            'week_start' => 0,
            'months_back' => 12,
            'months_ahead' => 12,
            'max_per_day' => 3,
            'max_span_days' => 7,
            'time_format' => 'g:ia',
            'link' => 'permalink',
            'filters' => [
                'area' => [
                    'type' => 'taxonomy',
                    'taxonomy' => 'area',
                    'label' => __('Area', 'hexa-jpn-tools'),
                    'all_label' => __('All areas', 'hexa-jpn-tools'),
                    'options' => 'terms',
                ],
                'dates' => [
                    'type' => 'date_range',
                    'meta_key' => 'start_date_timestamp',
                    'end_meta_key' => 'end_date_timestamp',
                    'format' => 'timestamp',
                    'timezone' => EventDates::TIMEZONE,
                    'label' => __('Dates', 'hexa-jpn-tools'),
                    'from_label' => __('From', 'hexa-jpn-tools'),
                    'to_label' => __('To', 'hexa-jpn-tools'),
                ],
                'kids' => [
                    'type' => 'meta',
                    'meta_key' => 'kids_event',
                    'control' => 'toggle',
                    'label' => __('Kids events', 'hexa-jpn-tools'),
                ],
                'featured' => [
                    'type' => 'meta',
                    'meta_key' => 'featured_event',
                    'control' => 'toggle',
                    'label' => __('Featured', 'hexa-jpn-tools'),
                ],
            ],
            'prepare' => [$this, 'prepare'],
            'item_class' => [$this, 'itemClass'],
            'render_item' => [$this, 'renderItem'],
            'labels' => [
                'count_one' => __('%d event', 'hexa-jpn-tools'),
                'count_many' => __('%d events', 'hexa-jpn-tools'),
                'count_none' => __('No events', 'hexa-jpn-tools'),
                'empty' => __('No events are scheduled in %s.', 'hexa-jpn-tools'),
                'more' => __('+%d more', 'hexa-jpn-tools'),
                'continues' => __('Continues', 'hexa-jpn-tools'),
                'until' => __('Until %s', 'hexa-jpn-tools'),
                'reset' => __('Clear filters', 'hexa-jpn-tools'),
            ],
            'cache_ttl' => 300,
            'cache_version' => HEXA_JPN_TOOLS_VERSION,
            'class' => 'jpn-calendar',
        ]);
    }

    /**
     * Area name and flags for exactly the events in the visible month: one
     * meta query and one term query.
     *
     * @param int[] $ids
     * @return array<int,array{area:string,featured:bool,kids:bool}>
     */
    public function prepare(array $ids): array
    {
        global $wpdb;

        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        $data = array_fill_keys($ids, ['area' => '', 'featured' => false, 'kids' => false]);
        $list = implode(',', $ids);
        $rows = (array) $wpdb->get_results(
            "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
             WHERE post_id IN ({$list}) AND meta_key IN ('featured_event', 'kids_event')",
            ARRAY_A
        );
        foreach ($rows as $row) {
            $key = $row['meta_key'] === 'featured_event' ? 'featured' : 'kids';
            $data[(int) $row['post_id']][$key] = $data[(int) $row['post_id']][$key] || (string) $row['meta_value'] === '1';
        }

        $terms = wp_get_object_terms($ids, 'area', ['fields' => 'all_with_object_id', 'orderby' => 'name']);
        if (is_array($terms)) {
            foreach ($terms as $term) {
                $objectId = (int) $term->object_id;
                if (isset($data[$objectId]) && $data[$objectId]['area'] === '') {
                    $data[$objectId]['area'] = html_entity_decode((string) $term->name, ENT_QUOTES, 'UTF-8');
                }
            }
        }

        return $data;
    }

    /** @param array<string,mixed> $item @param array<string,mixed> $data */
    public function itemClass(array $item, array $data): string
    {
        return trim((!empty($data['featured']) ? 'is-featured ' : '') . (!empty($data['kids']) ? 'is-kids' : ''));
    }

    /**
     * Inner markup of one event inside a day; Core wraps it in the event link.
     *
     * @param array<string,mixed> $item
     * @param array<string,mixed> $data
     */
    public function renderItem(array $item, array $data): string
    {
        $html = (string) $item['when'] !== '' ? '<span class="hcal-time">' . esc_html((string) $item['when']) . '</span>' : '';
        $html .= '<span class="hcal-name">'
            . (!empty($data['featured']) ? '<span class="jpn-cal-star" role="img" aria-label="' . esc_attr__('Featured', 'hexa-jpn-tools') . '">★</span> ' : '')
            . esc_html(html_entity_decode((string) $item['title'], ENT_QUOTES, 'UTF-8'))
            . '</span>';

        $details = array_filter([
            (string) ($data['area'] ?? ''),
            !empty($data['kids']) ? __('Kids', 'hexa-jpn-tools') : '',
        ]);
        if ($details !== []) {
            $html .= '<span class="jpn-cal-meta">' . esc_html(implode(' · ', $details)) . '</span>';
        }

        return $html;
    }
}
