<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Search;

use Hexa\JpnTools\Events\EventDates;
use Hexa\PluginCore\SearchQuery\ElementorPublicTextIndex;
use Hexa\PluginCore\SearchQuery\ElementorSearchAdapter;

/** JPN profiles for Elementor Pro's native live Search widget. */
final class SiteSearch
{
    public const UPCOMING_QUERY_ID = 'jpn_search_upcoming';
    public const ALL_QUERY_ID = 'jpn_search_all';

    /** @var ElementorSearchAdapter[] */
    private array $adapters = [];

    private ?ElementorPublicTextIndex $publicTextIndex = null;

    public function register(): void
    {
        if (!class_exists(ElementorSearchAdapter::class)) {
            return;
        }

        $publicPostTypes = $this->publicPostTypes();
        if (class_exists(ElementorPublicTextIndex::class)) {
            // Resolve public post types when indexing runs, after JPN's event
            // and service types have been registered on init.
            $this->publicTextIndex = new ElementorPublicTextIndex();
            $this->publicTextIndex->register();
        }

        $this->adapters = [
            new ElementorSearchAdapter(
                [$this, 'upcomingSettings'],
                self::UPCOMING_QUERY_ID,
                [$this, 'configureUpcomingQuery'],
                24
            ),
            new ElementorSearchAdapter(
                [$this, 'allSettings'],
                self::ALL_QUERY_ID,
                null,
                24
            ),
        ];

        foreach ($this->adapters as $adapter) {
            $adapter->register();
        }

        if (defined('WP_CLI') && WP_CLI && $this->publicTextIndex !== null) {
            \WP_CLI::add_command('hexa-jpn search-index rebuild', [$this, 'cliRebuildPublicTextIndex']);
        }
    }

    /** @return array<string,mixed> */
    public function upcomingSettings(): array
    {
        return $this->settings(['event']);
    }

    /** @return array<string,mixed> */
    public function allSettings(): array
    {
        return $this->settings($this->publicPostTypes());
    }

    /**
     * Ongoing timed events remain until their end time. Date-only events stay
     * through their final local day. Future events are ordered soonest first.
     *
     * @param object $query WP_Query-compatible Elementor query.
     */
    public function configureUpcomingQuery($query): void
    {
        $now = time();
        $today = EventDates::startOfToday($now);

        $query->set('post_type', ['event']);
        $query->set('meta_key', 'start_date_timestamp');
        $query->set('meta_query', [
            'relation' => 'OR',
            [
                'key' => 'start_date_timestamp',
                'value' => $now,
                'compare' => '>=',
                'type' => 'NUMERIC',
            ],
            [
                'key' => 'end_date_timestamp',
                'value' => $now,
                'compare' => '>=',
                'type' => 'NUMERIC',
            ],
            [
                'relation' => 'AND',
                [
                    'key' => 'start_date_precision',
                    'value' => 'date',
                    'compare' => '=',
                ],
                [
                    'key' => 'start_date_timestamp',
                    'value' => $today,
                    'compare' => '>=',
                    'type' => 'NUMERIC',
                ],
            ],
            [
                'relation' => 'AND',
                [
                    'key' => 'start_date_precision',
                    'value' => 'date',
                    'compare' => '=',
                ],
                [
                    'key' => 'end_date_timestamp',
                    'value' => $today,
                    'compare' => '>=',
                    'type' => 'NUMERIC',
                ],
            ],
        ]);
        $query->set('orderby', ['meta_value_num' => 'ASC', 'ID' => 'ASC']);
        $query->set('order', 'ASC');
    }

    /** @param string[] $postTypes @return array<string,mixed> */
    private function settings(array $postTypes): array
    {
        $customFields = [
            'location',
            'location_address',
            'additional_information',
            'jpn_event_where_label',
            'jpn_event_venue_label',
        ];
        if ($this->publicTextIndex !== null) {
            $customFields[] = ElementorPublicTextIndex::META_KEY;
        }

        return [
            'enabled' => true,
            'scope' => 'all',
            'term_logic' => 'all',
            'word_matching' => 'prefix',
            'post_types' => $postTypes,
            'fields' => ['title', 'content', 'excerpt', 'slug'],
            'taxonomies' => $this->publicTaxonomies(),
            'authors' => true,
            'custom_fields' => $customFields,
            'user_reference_fields' => ['event_host'],
            'results_per_page' => 12,
            'orderby' => 'relevance',
        ];
    }

    /** @return string[] */
    private function publicPostTypes(): array
    {
        $objects = get_post_types(['public' => true], 'objects');
        $types = [];
        foreach (is_array($objects) ? $objects : [] as $name => $object) {
            $name = sanitize_key((string) $name);
            if ($name === '' || $name === 'attachment' || !is_object($object) || !empty($object->exclude_from_search)) {
                continue;
            }
            $types[] = $name;
        }

        return $types !== [] ? array_values(array_unique($types)) : ['post', 'page', 'event', 'service'];
    }

    /** @return string[] */
    private function publicTaxonomies(): array
    {
        $objects = get_taxonomies(['public' => true], 'objects');
        $taxonomies = [];
        foreach (is_array($objects) ? $objects : [] as $name => $object) {
            $name = sanitize_key((string) $name);
            if ($name !== '' && $name !== 'post_format' && is_object($object)) {
                $taxonomies[] = $name;
            }
        }

        return array_values(array_unique($taxonomies));
    }

    /**
     * Rebuild the bounded public Elementor text index without exposing content.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Return post IDs and before/after hashes without writing post meta.
     *
     * [--page=<number>]
     * : Process only one page of the selection.
     *
     * [--per-page=<number>]
     * : Batch size from 1 to 200. Default 100.
     *
     * @param string[] $args
     * @param array<string,mixed> $assocArgs
     */
    public function cliRebuildPublicTextIndex(array $args, array $assocArgs): void
    {
        if ($this->publicTextIndex === null) {
            \WP_CLI::error('The public Elementor text index is unavailable.');
        }

        $dryRun = array_key_exists('dry-run', $assocArgs);
        $perPage = max(1, min(200, (int) ($assocArgs['per-page'] ?? 100)));
        $requestedPage = max(0, (int) ($assocArgs['page'] ?? 0));
        $page = $requestedPage > 0 ? $requestedPage : 1;
        $report = [
            'success' => true,
            'dry_run' => $dryRun,
            'meta_key' => ElementorPublicTextIndex::META_KEY,
            'post_types' => $this->publicPostTypes(),
            'selected_total' => 0,
            'processed' => 0,
            'indexed' => 0,
            'removed' => 0,
            'would_index' => 0,
            'would_remove' => 0,
            'unchanged' => 0,
            'unavailable' => 0,
            'failed' => 0,
            'items' => [],
        ];

        do {
            $batch = $this->publicTextIndex->rebuild($page, $perPage, $dryRun);
            $report['selected_total'] = (int) $batch['total'];
            foreach (['indexed', 'removed', 'would_index', 'would_remove', 'unchanged', 'unavailable', 'failed'] as $status) {
                $report[$status] += (int) ($batch[$status] ?? 0);
            }
            $items = is_array($batch['items'] ?? null) ? $batch['items'] : [];
            $report['processed'] += count($items);
            $report['items'] = array_merge($report['items'], $items);
            $nextPage = $requestedPage > 0 ? null : ($batch['next_page'] ?? null);
            $page = is_numeric($nextPage) ? (int) $nextPage : 0;
        } while ($page > 0);

        $report['success'] = $report['failed'] === 0 && $report['unavailable'] === 0;
        \WP_CLI::line((string) wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if (!$report['success']) {
            \WP_CLI::halt(1);
        }
    }
}
