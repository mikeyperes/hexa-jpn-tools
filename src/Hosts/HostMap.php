<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Hosts;

use Hexa\JpnTools\Events\EventDates;
use Hexa\PluginCore\Map\MapRegistry;

/**
 * JPN host map: registers the `jpn_hosts` profile with Hexa WP Core's Map.
 *
 * Core owns geocoding, the map, pins, clustering, the area filter, card
 * markup, and caching; brand colors are set in Elementor. This class owns
 * which hosts appear, where their address and area are stored, and what a
 * host's card says, reusing the host directory's batch data.
 */
final class HostMap
{
    public const PROFILE = 'jpn_hosts';

    public function __construct(private HostDirectory $directory, private EventDates $dates)
    {
    }

    public function register(): void
    {
        if (!class_exists(MapRegistry::class)) {
            return;
        }

        MapRegistry::register(self::PROFILE, [
            'source' => 'users',
            'roles' => ['host'],
            'address' => 'address',
            'geocoders' => ['census', 'nominatim'],
            'country' => 'us',
            'group' => ['meta' => 'area', 'taxonomy' => 'area'],
            'prepare' => [$this->directory, 'prepare'],
            'highlight' => static fn (int $id, array $host): bool => (int) ($host['stats']['upcoming'] ?? 0) > 0,
            'card' => [$this, 'card'],
            'view' => ['center' => [26.2, -80.19], 'zoom' => 8.4, 'fit_zoom' => 13],
            'labels' => [
                'region' => __('Map of JPN hosts', 'hexa-jpn-tools'),
                'loading' => __('Loading map…', 'hexa-jpn-tools'),
                'all' => __('All', 'hexa-jpn-tools'),
                'filter' => __('Filter hosts by area', 'hexa-jpn-tools'),
                'more' => __('More areas…', 'hexa-jpn-tools'),
                'count_one' => __('%d host', 'hexa-jpn-tools'),
                'count_many' => __('%d hosts', 'hexa-jpn-tools'),
                'list' => __('List of all hosts on the map', 'hexa-jpn-tools'),
                'cta' => __('View host', 'hexa-jpn-tools'),
            ],
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
            ];
        }

        return [
            'meta' => $meta,
            'list_label' => $list === [] ? '' : ($upcoming > 0 ? __('Upcoming', 'hexa-jpn-tools') : __('Recent events', 'hexa-jpn-tools')),
            'list' => $list,
        ];
    }
}
