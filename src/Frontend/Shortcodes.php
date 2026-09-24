<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Frontend;

use Hexa\JpnTools\Events\EventDates;
use Hexa\JpnTools\Events\EventQueries;

final class Shortcodes
{
    public function __construct(private EventQueries $queries, private EventDates $dates, private ?PhotoDownloads $downloads = null)
    {
    }

    public function register(): void
    {
        add_shortcode('events-photos', [$this, 'photos']);
        add_shortcode('jpn_upcoming_event_banner', [$this, 'banner']);
        add_shortcode('jpn_event_venue', [$this, 'venue']);
        add_shortcode('jpn_event_time_range', [$this, 'timeRange']);
        add_shortcode('jpn_event_photos', [$this, 'eventPhotos']);
        add_shortcode('jpn_event_badges', [$this, 'badges']);
        add_shortcode('jpn_event_facts', [$this, 'facts']);
        add_shortcode('jpn_event_actions', [$this, 'actions']);
    }

    /** Featured / Kids badges for the current event card; empty when neither flag is set. */
    public function badges(): string
    {
        $eventId = $this->currentEventId();
        if ($eventId === 0) {
            return '';
        }

        $badges = array_filter([
            'featured' => $this->flag($eventId, 'featured_event') ? '★ ' . __('Featured', 'hexa-jpn-tools') : '',
            'kids' => $this->flag($eventId, 'kids_event') ? __('Kids', 'hexa-jpn-tools') : '',
        ]);
        if ($badges === []) {
            return '';
        }

        $this->enqueueStyle();
        $html = '';
        foreach ($badges as $type => $label) {
            $html .= '<span class="jpn-badge jpn-badge--' . esc_attr($type) . '">' . esc_html($label) . '</span>';
        }
        return '<div class="jpn-badges">' . $html . '</div>';
    }

    /** Labelled When / Where / Host / Who facts for the current event card; empty facts are omitted. */
    public function facts(): string
    {
        $eventId = $this->currentEventId();
        if ($eventId === 0) {
            return '';
        }

        $when = '';
        $start = (int) get_post_meta($eventId, 'start_date_timestamp', true);
        if ($start > 0) {
            $parts = $this->dates->when(
                $start,
                (int) get_post_meta($eventId, 'end_date_timestamp', true),
                get_post_meta($eventId, 'start_date_precision', true) === 'date'
            );
            $when = esc_html($parts['date']) . ($parts['time'] !== '' ? '<br><span>' . esc_html($parts['time']) . '</span>' : '');
        }

        $host = get_userdata((int) get_post_meta($eventId, 'event_host', true));
        $facts = array_filter([
            __('When', 'hexa-jpn-tools') => $when,
            __('Where', 'hexa-jpn-tools') => esc_html($this->eventWhere($eventId)),
            __('Host', 'hexa-jpn-tools') => $host ? esc_html($host->display_name) : '',
            __('Who', 'hexa-jpn-tools') => esc_html($this->flag($eventId, 'kids_event') ? __('Kids & families', 'hexa-jpn-tools') : __('All ages', 'hexa-jpn-tools')),
        ], static fn(string $value): bool => $value !== '');

        $this->enqueueStyle();
        $html = '';
        foreach ($facts as $label => $value) {
            $html .= '<div><dt>' . esc_html((string) $label) . '</dt><dd>' . $value . '</dd></div>';
        }
        return '<dl class="jpn-facts">' . $html . '</dl>';
    }

    /** RSVP (the event's registration link, when set) and Details (the event page) buttons. */
    public function actions(): string
    {
        $eventId = $this->currentEventId();
        if ($eventId === 0) {
            return '';
        }

        $this->enqueueStyle();
        $link = trim((string) get_post_meta($eventId, 'link', true));
        $html = $link !== ''
            ? '<a class="jpn-btn jpn-btn--primary" href="' . esc_url($this->normalizeUrl($link, '')) . '" target="_blank" rel="noopener">' . esc_html__('RSVP', 'hexa-jpn-tools') . ' <span aria-hidden="true">↗</span></a>'
            : '';
        $html .= '<a class="jpn-btn jpn-btn--ghost" href="' . esc_url((string) get_permalink($eventId)) . '">' . esc_html__('Details', 'hexa-jpn-tools') . ' <span aria-hidden="true">→</span></a>';
        return '<div class="jpn-card-actions">' . $html . '</div>';
    }

    /**
     * Additional Photos gallery for the current event; renders nothing when the
     * ACF gallery is empty, so templates need no visibility plugin.
     */
    public function eventPhotos(array|string $attributes = []): string
    {
        $attributes = shortcode_atts(['title' => __('Additional Photos', 'hexa-jpn-tools')], $attributes, 'jpn_event_photos');
        $eventId = $this->currentEventId();
        if ($eventId === 0) {
            return '';
        }

        $ids = get_post_meta($eventId, 'additional_photos', true);
        $ids = array_values(array_filter(array_map('intval', is_array($ids) ? $ids : (array) maybe_unserialize($ids))));
        if ($ids === []) {
            return '';
        }

        $this->enqueueStyle();
        $items = '';
        foreach ($ids as $attachmentId) {
            $full = wp_get_attachment_image_url($attachmentId, 'full');
            $thumb = wp_get_attachment_image_url($attachmentId, 'medium_large');
            if (!$full || !$thumb) {
                continue;
            }
            $alt = trim((string) get_post_meta($attachmentId, '_wp_attachment_image_alt', true));
            $items .= '<a class="jpn-event-photos__item" href="' . esc_url($full) . '" data-elementor-open-lightbox="yes"'
                . ' data-elementor-lightbox-slideshow="jpn-event-' . esc_attr((string) $eventId) . '">'
                . '<img src="' . esc_url($thumb) . '" alt="' . esc_attr($alt) . '" loading="lazy"></a>';
        }
        if ($items === '') {
            return '';
        }

        return '<section class="jpn-event-photos"><p class="jpn-event-photos__title">' . esc_html((string) $attributes['title']) . '</p>'
            . '<div class="jpn-event-photos__grid">' . $items . '</div></section>';
    }

    public function photos(array|string $attributes = []): string
    {
        $attributes = shortcode_atts(['days' => 7, 'download' => 'no'], $attributes, 'events-photos');
        $events = $this->queries->nextDays((int) $attributes['days']);
        if ($events === []) {
            return '<p>' . esc_html__('No events found for this period.', 'hexa-jpn-tools') . '</p>';
        }

        $this->enqueueStyle();
        // download="yes": a "Save all photos" button and a ZIP link above the photos.
        $toolbar = $this->downloads !== null && in_array(strtolower((string) $attributes['download']), ['yes', '1', 'true'], true)
            ? $this->downloads->toolbar((int) $attributes['days'])
            : '';
        $output = $toolbar . '<div class="events-photos">';
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
        if ($eventId <= 0 || $start <= 0 || get_post_meta($eventId, 'start_date_precision', true) === 'date') {
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
        $area = $this->areaName($eventId);
        if ($area !== '') {
            return $area;
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

    /** Where the event happens: the Code.Hexa "where" label, else its area. */
    private function eventWhere(int $eventId): string
    {
        $where = trim((string) get_post_meta($eventId, 'jpn_event_where_label', true));
        return $where !== '' ? $where : $this->areaName($eventId);
    }

    private function areaName(int $eventId): string
    {
        $areaId = (int) get_post_meta($eventId, 'area', true);
        $term = $areaId > 0 ? get_term($areaId, 'area') : null;
        return $term && !is_wp_error($term) ? html_entity_decode((string) $term->name, ENT_QUOTES, 'UTF-8') : '';
    }

    private function currentEventId(): int
    {
        $eventId = (int) get_the_ID();
        return $eventId > 0 && get_post_type($eventId) === 'event' ? $eventId : 0;
    }

    private function flag(int $eventId, string $key): bool
    {
        return (string) get_post_meta($eventId, $key, true) === '1';
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
