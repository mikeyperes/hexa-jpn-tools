<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Hosts;

use Hexa\JpnTools\Events\EventDates;
use Hexa\JpnTools\Events\EventLightbox;
use Hexa\JpnTools\Events\AreaMapDetails;
use Hexa\PluginCore\Map\MapRegistry;

/**
 * JPN host map: registers the `jpn_hosts` profile with Hexa WP Core's Map.
 *
 * Core owns geocoding, the map, pins, clustering, the area filter, the right
 * selection sidebar, rich entry markup, and caching; brand colors are set in
 * Elementor. This class owns the host profile and opts into AreaMapDetails,
 * which supplies upcoming/ongoing event data across the selected area.
 * Event photos and titles open Core's lightbox; Details opens the event page.
 */
final class HostMap
{
    public const PROFILE = 'jpn_hosts';

    public function __construct(private HostDirectory $directory, private EventDates $dates, private EventLightbox $lightbox)
    {
    }

    public function register(): void
    {
        if (!class_exists(MapRegistry::class)) {
            return;
        }

        MapRegistry::register(self::PROFILE, $this->lightbox->profile() + [
            'source' => 'users',
            'roles' => ['host'],
            'address' => 'address',
            'geocoders' => ['census', 'nominatim'],
            'country' => 'us',
            'group' => ['meta' => 'area', 'taxonomy' => 'area'],
            'prepare' => [$this->directory, 'prepare'],
            'highlight' => static fn (int $id, array $host): bool => (int) ($host['stats']['upcoming'] ?? 0) > 0,
            // Start of the host's next event: drives the "24 hours / 48 hours / 1 week / 2 weeks" chips.
            'next' => static fn (int $id, array $host): int => (int) ($host['stats']['upcoming'] ?? 0) > 0 ? (int) ($host['stats']['next_ts'] ?? 0) : 0,
            'card' => [$this, 'card'],
            'selection' => 'sidebar',
            'details' => [new AreaMapDetails($this->dates), 'items'],
            'details_per_page' => 10,
            'related_post_types' => ['event'],
            'view' => ['center' => [26.2, -80.19], 'zoom' => 8.4, 'fit_zoom' => 13],
            'labels' => [
                'region' => __('Map of JPN hosts', 'hexa-jpn-tools'),
                'loading' => __('Loading map…', 'hexa-jpn-tools'),
                'all' => __('All', 'hexa-jpn-tools'),
                'filter' => __('Filter hosts by area', 'hexa-jpn-tools'),
                'when' => __('Filter hosts by their next event', 'hexa-jpn-tools'),
                'when_all' => __('Any time', 'hexa-jpn-tools'),
                'when_prefix' => __('Events in the next', 'hexa-jpn-tools'),
                'more' => __('More areas…', 'hexa-jpn-tools'),
                'count_one' => __('%d host', 'hexa-jpn-tools'),
                'count_many' => __('%d hosts', 'hexa-jpn-tools'),
                'list' => __('List of all hosts on the map', 'hexa-jpn-tools'),
                'cta' => __('View host', 'hexa-jpn-tools'),
                'details' => __('Events at this location', 'hexa-jpn-tools'),
                'details_open' => __('Show area events', 'hexa-jpn-tools'),
                'details_loading' => __('Loading area events…', 'hexa-jpn-tools'),
                'details_error' => __('The area events could not load. Please try again.', 'hexa-jpn-tools'),
                'details_empty' => __('No upcoming or ongoing events match this area and date window.', 'hexa-jpn-tools'),
                'details_page' => __('Page %1$d of %2$d · %3$d events', 'hexa-jpn-tools'),
            ] + $this->lightbox->labels(),
            'cache_version' => HEXA_JPN_TOOLS_VERSION,
            'class' => 'jpn-hosts-map',
        ]);
    }

    /**
     * Card data for one host: address, event totals, and up to three upcoming
     * (or most recent) events.
     *
     * @param array<string,mixed> $host HostDirectory::prepare() row.
     * @return array<string,mixed>
     */
    public function card(int $id, array $host): array
    {
        $stats = (array) ($host['stats'] ?? []);
        $total = (int) ($stats['total'] ?? 0);
        $upcoming = (int) ($stats['upcoming'] ?? 0);
        $meta = [(string) ($host['address'] ?? '')];
        $meta[] = $total > 0
            ? sprintf(
                /* translators: 1: total events, 2: upcoming events */
                __('%1$s · %2$s upcoming', 'hexa-jpn-tools'),
                sprintf(_n('%s event', '%s events', $total, 'hexa-jpn-tools'), number_format_i18n($total)),
                number_format_i18n($upcoming)
            )
            : __('No events yet', 'hexa-jpn-tools');

        $list = [];
        foreach ((array) ($host['events'] ?? []) as $event) {
            $list[] = [
                'label' => $this->dates->formatTimestamp((int) $event['ts'], 'M j'),
                'text' => (string) $event['title'],
                'url' => (string) $event['url'],
                'id' => (int) ($event['id'] ?? 0),
            ];
        }

        return [
            'meta' => $meta,
            'list_label' => $list === [] ? '' : ($upcoming > 0 ? __('Upcoming', 'hexa-jpn-tools') : __('Recent events', 'hexa-jpn-tools')),
            'list' => $list,
        ];
    }
}
