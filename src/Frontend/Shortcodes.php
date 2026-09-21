<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Frontend;

use Hexa\JpnTools\Events\EventDates;
use Hexa\JpnTools\Events\EventQueries;

final class Shortcodes
{
    public function __construct(private EventQueries $queries, private EventDates $dates)
    {
    }

    public function register(): void
    {
        add_shortcode('events-photos', [$this, 'photos']);
        add_shortcode('jpn_upcoming_event_banner', [$this, 'banner']);
        add_shortcode('jpn_event_venue', [$this, 'venue']);
        add_shortcode('jpn_event_time_range', [$this, 'timeRange']);
    }

    public function photos(array|string $attributes = []): string
    {
        $attributes = shortcode_atts(['days' => 7], $attributes, 'events-photos');
        $events = $this->queries->nextDays((int) $attributes['days']);
        if ($events === []) {
            return '<p>' . esc_html__('No events found for this period.', 'hexa-jpn-tools') . '</p>';
        }

        $this->enqueueStyle();
        $output = '<div class="events-photos">';
        foreach ($events as $event) {
            $image = get_the_post_thumbnail($event->ID, 'medium_large');
            if ($image === '') {
                continue;
            }
            $link = trim((string) get_post_meta($event->ID, 'link', true));
            $class = 'event-photo' . ($link !== '' ? ' clickable' : '');
            $output .= '<div class="' . esc_attr($class) . '">';
            if ($link !== '') {
                $output .= '<a href="' . esc_url($this->normalizeUrl($link, get_permalink($event->ID))) . '" target="_blank" rel="noopener">'
                    . $image . '</a><div class="click-overlay">' . esc_html__('Click to register', 'hexa-jpn-tools') . '</div>';
            } else {
                $output .= $image;
            }
            $output .= '</div>';
        }
        return $output . '</div>';
    }

    public function banner(): string
    {
        $events = $this->queries->upcoming(1, true);
        if ($events === []) {
            $events = $this->queries->upcoming(1);
        }
        if ($events === []) {
            return '';
        }

        $this->enqueueStyle();
        $event = $events[0];
        $title = get_the_title($event->ID);
        $venue = $this->eventVenue($event->ID, $title);
        $timestamp = (int) get_post_meta($event->ID, 'start_date_timestamp', true);
        $date = $timestamp > 0 ? strtoupper($this->dates->formatTimestamp($timestamp, 'D M j')) : '';
        $link = $this->normalizeUrl((string) get_post_meta($event->ID, 'link', true), get_permalink($event->ID));
        $label = get_post_meta($event->ID, 'featured_event', true) === '1' ? 'FEATURED' : 'UPCOMING';

        return '<a class="jpn-upcoming-event-banner" href="' . esc_url($link) . '" target="_blank" rel="noopener">'
            . '<span class="jpn-event-banner-main"><span class="jpn-event-banner-label">' . esc_html($label) . '</span><span class="jpn-event-banner-title">' . esc_html($title) . '</span></span>'
            . '<span class="jpn-event-banner-divider" aria-hidden="true"></span>'
            . '<span class="jpn-event-banner-meta"><span class="jpn-event-banner-label">VENUE</span><span class="jpn-event-banner-value">' . esc_html($venue) . '</span></span>'
            . '<span class="jpn-event-banner-divider" aria-hidden="true"></span>'
            . '<span class="jpn-event-banner-meta jpn-event-banner-date"><span class="jpn-event-banner-label">DATE</span><span class="jpn-event-banner-value">' . esc_html($date) . '</span></span>'
            . '<span class="jpn-event-banner-button">RSVP <span aria-hidden="true">→</span></span></a>';
    }

    public function venue(): string
    {
        $eventId = (int) get_the_ID();
        return $eventId > 0 ? esc_html($this->eventVenue($eventId, get_the_title($eventId))) : '';
    }

    public function timeRange(): string
    {
        $eventId = (int) get_the_ID();
        $start = (int) get_post_meta($eventId, 'start_date_timestamp', true);
        if ($eventId <= 0 || $start <= 0) {
            return '';
        }
        $value = $this->dates->formatTimestamp($start, 'g:i A');
        $end = (int) get_post_meta($eventId, 'end_date_timestamp', true);
        if ($end > $start) {
            $value .= ' - ' . $this->dates->formatTimestamp($end, 'g:i A');
        }
        return esc_html($value);
    }

    private function eventVenue(int $eventId, string $title): string
    {
        $location = trim((string) get_post_meta($eventId, 'location', true));
        if ($location !== '') {
            return $location;
        }
        $areaId = (int) get_post_meta($eventId, 'area', true);
        if ($areaId > 0) {
            $term = get_term($areaId, 'area');
            if ($term && !is_wp_error($term)) {
                return $term->name;
            }
        }
        if (str_contains($title, ' - ')) {
            $parts = array_map('trim', explode(' - ', $title));
            $last = (string) end($parts);
            if ($last !== '') {
                return $last;
            }
        }
        return __('Event Details', 'hexa-jpn-tools');
    }

    private function normalizeUrl(string $url, string $fallback): string
    {
        $url = trim($url);
        if ($url === '') {
            return $fallback;
        }
        return preg_match('#^https?://#i', $url) ? $url : 'https://' . ltrim($url, '/');
    }

    private function enqueueStyle(): void
    {
        wp_enqueue_style('hexa-jpn-frontend', HEXA_JPN_TOOLS_PLUGIN_URL . 'assets/frontend.css', [], HEXA_JPN_TOOLS_VERSION);
    }
}
