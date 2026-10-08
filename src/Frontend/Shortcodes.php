<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Frontend;

use Hexa\JpnTools\Events\EventDates;
use Hexa\JpnTools\Events\EventQueries;
use Hexa\JpnTools\Events\RecentlyAdded;
use Hexa\PluginCore\PublicComponents\ImageZoom;

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
        add_shortcode('jpn_event_recent', [$this, 'recent']);
        add_shortcode('jpn_event_facts', [$this, 'facts']);
        add_shortcode('jpn_event_actions', [$this, 'actions']);
        add_shortcode('jpn_event_field', [$this, 'eventField']);
        add_shortcode('jpn_event_date_stack', [$this, 'dateStack']);
        add_shortcode('jpn_event_place', [$this, 'place']);
        add_shortcode('jpn_event_flyer', [$this, 'flyer']);
    }

    /** Scalar current-event value for native Elementor Loop Item controls. */
    public function eventField(array|string $attributes = []): string
    {
        $attributes = shortcode_atts(['key' => ''], $attributes, 'jpn_event_field');
        $eventId = $this->currentEventId();
        if ($eventId === 0) {
            return '';
        }

        $key = sanitize_key((string) $attributes['key']);
        $start = (int) get_post_meta($eventId, 'start_date_timestamp', true);
        $area = $this->areaName($eventId);
        $hostId = (int) get_post_meta($eventId, 'event_host', true) ?: (int) get_post_field('post_author', $eventId);
        $address = trim((string) get_post_meta($eventId, 'location_address', true));
        if ($address === '') {
            $address = trim((string) get_post_meta($eventId, 'location', true));
        }
        if ($address === '' && $hostId > 0) {
            $address = trim((string) get_user_meta($hostId, 'address', true));
        }

        $value = match ($key) {
            'dow' => $start > 0 ? $this->dates->formatTimestamp($start, 'D') : '',
            'day' => $start > 0 ? $this->dates->formatTimestamp($start, 'j') : '',
            'month' => $start > 0 ? $this->dates->formatTimestamp($start, 'M') : '',
            'relative' => $this->relativeDate($start),
            'date_long' => $start > 0 ? $this->dates->formatTimestamp($start, 'l, F j') : '',
            'time_range' => html_entity_decode($this->timeRange(), ENT_QUOTES, 'UTF-8'),
            'where' => $this->eventWhere($eventId),
            'address' => $address,
            'audience' => $this->flag($eventId, 'kids_event') ? __('Kids & families', 'hexa-jpn-tools') : __('All ages', 'hexa-jpn-tools'),
            'rsvp_url' => $this->eventRsvpUrl($eventId),
            'area' => $area,
            'featured_label' => $this->flag($eventId, 'featured_event') ? __('Featured', 'hexa-jpn-tools') : '',
            'kids_label' => $this->flag($eventId, 'kids_event') ? __('Kids', 'hexa-jpn-tools') : '',
            'recent_label' => RecentlyAdded::is($eventId) ? RecentlyAdded::LABEL : '',
            default => '',
        };

        return $key === 'rsvp_url' ? esc_url($value) : esc_html($value);
    }

    /** Recently added / Featured / Kids badges for the current event card; empty when none applies. */
    public function badges(): string
    {
        $eventId = $this->currentEventId();
        if ($eventId === 0) {
            return '';
        }

        $recent = RecentlyAdded::marker($eventId, 'jpn-badge jpn-badge--recent');
        $badges = array_filter([
            'featured' => $this->flag($eventId, 'featured_event') ? '★ ' . __('Featured', 'hexa-jpn-tools') : '',
            'kids' => $this->flag($eventId, 'kids_event') ? __('Kids', 'hexa-jpn-tools') : '',
        ]);
        if ($badges === [] && $recent === '') {
            return '';
        }

        $this->enqueueStyle();
        $html = $recent;
        foreach ($badges as $type => $label) {
            $html .= '<span class="jpn-badge jpn-badge--' . esc_attr($type) . '">' . esc_html($label) . '</span>';
        }
        return '<div class="jpn-badges">' . $html . '</div>';
    }

    /** Recently added marker alone, for templates that already show Featured / Kids themselves. */
    public function recent(): string
    {
        $eventId = $this->currentEventId();

        return $eventId > 0 ? RecentlyAdded::marker($eventId, 'jpn-new jpn-event-new') : '';
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

    /** Timeline date stack: weekday, day number, month, then the start time under a rule (omitted for date-only events). */
    public function dateStack(): string
    {
        $eventId = $this->currentEventId();
        $start = $eventId > 0 ? (int) get_post_meta($eventId, 'start_date_timestamp', true) : 0;
        if ($start <= 0) {
            return '';
        }

        $this->enqueueStyle();
        $time = '';
        if (get_post_meta($eventId, 'start_date_precision', true) !== 'date') {
            $time = '<span class="jpn-ds__time"><b>' . esc_html($this->dates->formatTimestamp($start, 'g:i')) . '</b><small>'
                . esc_html($this->dates->formatTimestamp($start, 'A')) . '</small></span>';
        }
        return '<time class="jpn-ds" datetime="' . esc_attr(gmdate('c', $start)) . '">'
            . '<span class="jpn-ds__wd">' . esc_html($this->dates->formatTimestamp($start, 'D')) . '</span>'
            . '<b class="jpn-ds__day">' . esc_html($this->dates->formatTimestamp($start, 'j')) . '</b>'
            . '<span class="jpn-ds__mo">' . esc_html($this->dates->formatTimestamp($start, 'M')) . '</span>'
            . $time . '</time>';
    }

    /** The event flyer filling its container: hover previews it large, click or tap opens the zoom viewer. */
    public function flyer(): string
    {
        $eventId = $this->currentEventId();

        return $eventId > 0 ? ImageZoom::html((int) get_post_thumbnail_id($eventId), [
            'class' => 'jpn-flyer', 'sizes' => '(max-width: 767px) 100vw, 640px', 'alt' => get_the_title($eventId),
        ]) : '';
    }

    /** Location (where label or area, with the venue under it when it differs) and Hosted by, each on its own row. */
    public function place(): string
    {
        $eventId = $this->currentEventId();
        if ($eventId === 0) {
            return '';
        }

        $where = $this->eventWhere($eventId);
        $venue = trim((string) get_post_meta($eventId, 'jpn_event_venue_label', true));
        if (strcasecmp($venue, $where) === 0) {
            $venue = '';
        }
        $host = get_userdata((int) get_post_meta($eventId, 'event_host', true));
        $rows = '';
        if ($where !== '' || $venue !== '') {
            $rows .= '<div class="jpn-place__row jpn-place__row--loc"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg><div>'
                . '<span class="jpn-place__lbl">' . esc_html__('Location', 'hexa-jpn-tools') . '</span>'
                . '<b>' . esc_html($where !== '' ? $where : $venue) . '</b>'
                . ($where !== '' && $venue !== '' ? '<span class="jpn-place__venue">' . esc_html($venue) . '</span>' : '') . '</div></div>';
        }
        if ($host) {
            $rows .= '<div class="jpn-place__row jpn-place__row--host"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/></svg><div>'
                . '<span class="jpn-place__lbl">' . esc_html__('Hosted by', 'hexa-jpn-tools') . '</span>'
                . '<b>' . esc_html(html_entity_decode((string) $host->display_name, ENT_QUOTES, 'UTF-8')) . '</b></div></div>';
        }
        if ($rows === '') {
            return '';
        }

        $this->enqueueStyle();
        return '<div class="jpn-place">' . $rows . '</div>';
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
            $image = get_the_post_thumbnail($event->ID, 'large');
            if ($image === '') {
                continue;
            }
            $link = trim((string) get_post_meta($event->ID, 'link', true));
            $class = 'event-photo' . ($link !== '' ? ' clickable' : '');
            $output .= '<div class="' . esc_attr($class) . '">' . RecentlyAdded::marker($event->ID, 'jpn-new jpn-new--overlay');
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
        $label = RecentlyAdded::is($event->ID) ? RecentlyAdded::SHORT_LABEL : (get_post_meta($event->ID, 'featured_event', true) === '1' ? 'FEATURED' : 'UPCOMING');

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

    private function eventRsvpUrl(int $eventId): string
    {
        $link = trim((string) get_post_meta($eventId, 'link', true));
        return $link !== '' ? $this->normalizeUrl($link, '') : '';
    }

    private function relativeDate(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '';
        }

        $today = EventDates::startOfToday();
        $eventDay = EventDates::startOfToday($timestamp);
        if ($eventDay < $today) {
            return '';
        }

        $days = (int) round(($eventDay - $today) / DAY_IN_SECONDS);
        return match ($days) {
            0 => __('Today', 'hexa-jpn-tools'),
            1 => __('Tomorrow', 'hexa-jpn-tools'),
            default => sprintf(__('In %d days', 'hexa-jpn-tools'), $days),
        };
    }

    private function enqueueStyle(): void
    {
        wp_enqueue_style('hexa-jpn-frontend', HEXA_JPN_TOOLS_PLUGIN_URL . 'assets/frontend.css', [], HEXA_JPN_TOOLS_VERSION);
    }
}
