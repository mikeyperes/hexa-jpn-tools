<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Events;

/** Maps JPN area events to Core's generic, paginated location-detail entries. */
final class AreaMapDetails
{
    public function __construct(private EventDates $dates)
    {
    }

    public function items(int $hostId, array $host, array $item, array $request): array
    {
        $area = maybe_unserialize(get_user_meta($hostId, 'area', true));
        $areaId = (int) (is_array($area) ? reset($area) : $area);
        $term = $areaId > 0 ? get_term($areaId, 'area') : null;
        if (!$term || is_wp_error($term)) {
            $areaId = 0;
        }
        $hours = (int) $request['hours'];
        $from = time();
        $to = $hours > 0 ? $from + $hours * 3600 : PHP_INT_MAX;
        $page = (new EventQueries($this->dates))->pageBetween($from, $to, $areaId, $hostId, (int) $request['per_page'], (int) $request['page']);

        $hostIds = [];
        foreach ($page['posts'] as $post) {
            $hostIds[] = (int) get_post_meta($post->ID, 'event_host', true) ?: (int) $post->post_author;
        }
        cache_users(array_values(array_unique(array_filter($hostIds))));
        $entries = [];
        foreach ($page['posts'] as $post) {
            $id = (int) $post->ID;
            $start = (int) get_post_meta($id, 'start_date_timestamp', true);
            $end = (int) get_post_meta($id, 'end_date_timestamp', true);
            $dateOnly = get_post_meta($id, 'start_date_precision', true) === 'date';
            $when = $this->dates->when($start, $end, $dateOnly);
            $eventHostId = (int) get_post_meta($id, 'event_host', true) ?: (int) $post->post_author;
            $eventHost = $eventHostId > 0 ? get_userdata($eventHostId) : false;
            $address = trim((string) (get_post_meta($id, 'location_address', true) ?: get_post_meta($id, 'address', true)));
            if ($address === '' && $eventHostId > 0) {
                $address = trim((string) get_user_meta($eventHostId, 'address', true));
            }
            $location = trim((string) get_post_meta($id, 'location', true));
            $imageId = (int) get_post_thumbnail_id($id);
            if ($imageId <= 0) {
                $additional = get_post_meta($id, 'additional_photos', true);
                $first = is_array($additional) ? reset($additional) : null;
                $imageId = (int) (is_array($first) ? ($first['ID'] ?? 0) : $first);
            }
            $imageUrl = $imageId > 0 ? (string) wp_get_attachment_image_url($imageId, 'medium_large') : '';
            $url = (string) get_permalink($id);
            $actions = [['label' => __('Details', 'hexa-jpn-tools'), 'url' => $url]];
            $registration = trim((string) get_post_meta($id, 'link', true));
            if ($registration !== '') {
                if (!preg_match('#^https?://#i', $registration)) {
                    $registration = 'https://' . ltrim($registration, '/');
                }
                $actions[] = ['label' => __('RSVP', 'hexa-jpn-tools'), 'url' => $registration, 'external' => true];
            }
            $description = (string) $post->post_excerpt ?: (string) get_post_meta($id, 'additional_information', true);
            if (trim($description) === '') {
                $description = (string) $post->post_content;
            }
            $hostName = $eventHost ? html_entity_decode((string) $eventHost->display_name, ENT_QUOTES, 'UTF-8') : '';
            $now = time();
            $tags = [];
            if ($start > 0 && $start <= $now && ($end <= 0 || $end >= $now)) {
                $tags[] = __('Happening now', 'hexa-jpn-tools');
            }
            if (get_post_meta($id, 'featured_event', true) === '1') {
                $tags[] = __('Featured', 'hexa-jpn-tools');
            }
            if (get_post_meta($id, 'kids_event', true) === '1') {
                $tags[] = __('Kids & families', 'hexa-jpn-tools');
            }
            $multiDay = str_contains($when['date'], '–');
            $entries[] = [
                'id' => $id,
                'title' => html_entity_decode(get_the_title($id), ENT_QUOTES, 'UTF-8'),
                'url' => $url,
                'image' => ['url' => $imageUrl, 'alt' => $imageId > 0 ? (string) get_post_meta($imageId, '_wp_attachment_image_alt', true) : ''],
                'badge' => [
                    'top' => $this->dates->formatTimestamp($start, 'M'),
                    'main' => $this->dates->formatTimestamp($start, 'j'),
                    'bottom' => $this->dates->formatTimestamp($start, 'D'),
                ],
                // Row line: multi-day range or start time, then the host.
                'meta' => [$multiDay ? $when['date'] : ($when['time'] !== '' ? $when['time'] : __('All day', 'hexa-jpn-tools')), $hostName],
                'tags' => $tags,
                'description' => wp_trim_words(wp_strip_all_tags(strip_shortcodes($description)), 35),
                'facts' => [
                    __('When', 'hexa-jpn-tools') => $when['date'] . ($when['time'] !== '' ? ' · ' . $when['time'] : '') . ' · ' . $this->dates->formatTimestamp($start, 'Y'),
                    __('Host', 'hexa-jpn-tools') => $hostName,
                    __('Where', 'hexa-jpn-tools') => $location,
                    __('Address', 'hexa-jpn-tools') => $address,
                ],
                'actions' => $actions,
            ];
        }

        $areaName = $areaId > 0 ? html_entity_decode((string) $term->name, ENT_QUOTES, 'UTF-8') : $item['title'];
        $summary = $areaId > 0
            ? sprintf(__('Upcoming and ongoing events across %s.', 'hexa-jpn-tools'), $areaName)
            : __('This location has no assigned area. Showing its own upcoming and ongoing events.', 'hexa-jpn-tools');
        if ($hours > 0) {
            $summary .= ' ' . sprintf(__('Within the next %d hours.', 'hexa-jpn-tools'), $hours);
        }

        return ['title' => $areaName, 'summary' => $summary, 'entries' => $entries, 'total' => $page['total'], 'page' => $page['page']];
    }
}
