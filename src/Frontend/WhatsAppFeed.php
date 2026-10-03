<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Frontend;

use Hexa\JpnTools\Events\EventDates;
use Hexa\JpnTools\Events\EventQueries;
use WP_Post;

/** Live, bounded event data for the native Elementor WhatsApp phone mockup. */
final class WhatsAppFeed
{
    private const DAYS = 14;
    private const FEED_LIMIT = 10;

    public function __construct(private EventQueries $queries, private EventDates $dates)
    {
    }

    public function register(): void
    {
        add_shortcode('jpn_whatsapp_feed', [$this, 'render']);
    }

    public function render(): string
    {
        $upcoming = $this->queries->nextDays(self::DAYS, null, EventQueries::MAX_EVENTS);
        $events = array_map(
            fn (WP_Post $event): array => $this->eventData($event),
            array_slice($upcoming, 0, self::FEED_LIMIT)
        );

        wp_enqueue_script(
            'hexa-jpn-whatsapp-feed',
            HEXA_JPN_TOOLS_PLUGIN_URL . 'assets/whatsapp-feed.js',
            [],
            HEXA_JPN_TOOLS_VERSION,
            true
        );

        $payload = wp_json_encode([
            'events' => $events,
            'total_count' => count($upcoming),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return '<div class="jpn-whatsapp-feed" data-jpn-whatsapp-feed></div>'
            . '<script type="application/json" class="jpn-whatsapp-feed-data">' . ($payload ?: '{}') . '</script>';
    }

    /** @return array{title:string,host:string,date:string,url:string,image:string} */
    private function eventData(WP_Post $event): array
    {
        $eventId = (int) $event->ID;
        $timestamp = (int) get_post_meta($eventId, 'start_date_timestamp', true);
        $date = '';
        if ($timestamp > 0) {
            $when = $this->dates->when(
                $timestamp,
                (int) get_post_meta($eventId, 'end_date_timestamp', true),
                get_post_meta($eventId, 'start_date_precision', true) === 'date'
            );
            $date = $when['date'] . ($when['time'] !== '' ? ' · ' . $when['time'] : '');
        }

        $hostId = (int) get_post_meta($eventId, 'event_host', true) ?: (int) $event->post_author;
        $host = $hostId > 0 ? get_userdata($hostId) : false;
        $hostName = $host ? trim((string) $host->display_name) : '';
        $area = $this->areaName($eventId);
        if ($hostName !== '' && $area !== '' && stripos($hostName, $area) === false) {
            $hostName .= ', ' . $area;
        }
        if ($hostName === '') {
            $hostName = trim((string) get_post_meta($eventId, 'jpn_event_where_label', true));
        }
        if ($hostName === '') {
            $hostName = trim((string) get_post_meta($eventId, 'location', true)) ?: $area;
        }

        $title = html_entity_decode(
            wp_strip_all_tags((string) get_post_field('post_title', $eventId, 'raw')),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        return [
            'title' => trim($title),
            'host' => $hostName,
            'date' => $date,
            'url' => esc_url_raw((string) get_permalink($eventId), ['http', 'https']),
            'image' => esc_url_raw((string) get_the_post_thumbnail_url($eventId, 'medium_large'), ['http', 'https']),
        ];
    }

    private function areaName(int $eventId): string
    {
        $areaId = (int) get_post_meta($eventId, 'area', true);
        $term = $areaId > 0 ? get_term($areaId, 'area') : null;

        return $term && !is_wp_error($term)
            ? html_entity_decode((string) $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : '';
    }
}
