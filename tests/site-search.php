<?php

declare(strict_types=1);

namespace {
    function sanitize_key(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower($value));
    }

    function get_post_types(array $args, string $output): array
    {
        return [
            'post' => (object) ['exclude_from_search' => false],
            'page' => (object) ['exclude_from_search' => false],
            'event' => (object) ['exclude_from_search' => false],
            'service' => (object) ['exclude_from_search' => false],
            'attachment' => (object) ['exclude_from_search' => false],
            'private_note' => (object) ['exclude_from_search' => true],
        ];
    }

    function get_taxonomies(array $args, string $output): array
    {
        return [
            'category' => (object) [],
            'area' => (object) [],
            'post_format' => (object) [],
        ];
    }
}

namespace Hexa\PluginCore\SearchQuery {
    final class ElementorPublicTextIndex
    {
        public const META_KEY = '_hexa_elementor_public_text';
        public static int $registrations = 0;

        public function register(): void
        {
            self::$registrations++;
        }
    }

    final class ElementorSearchAdapter
    {
        /** @var self[] */
        public static array $instances = [];
        public bool $registered = false;

        public function __construct(
            public $settingsProvider,
            public string $queryId,
            public $configurator = null,
            public int $maximum = 50
        ) {
            self::$instances[] = $this;
        }

        public function register(): void
        {
            $this->registered = true;
        }
    }
}

namespace {
    require_once dirname(__DIR__) . '/src/Events/EventDates.php';
    require_once dirname(__DIR__) . '/src/Search/SiteSearch.php';

    use Hexa\JpnTools\Search\SiteSearch;
    use Hexa\PluginCore\SearchQuery\ElementorPublicTextIndex;
    use Hexa\PluginCore\SearchQuery\ElementorSearchAdapter;

    final class SearchFixtureQuery
    {
        /** @var array<string,mixed> */
        public array $vars = [];

        public function set(string $key, mixed $value): void
        {
            $this->vars[$key] = $value;
        }
    }

    /** @param array<string|int,mixed> $node @param array<string,mixed> $meta */
    function matches_search_constraints(array $node, array $meta): bool
    {
        if (isset($node['relation'])) {
            $matches = [];
            foreach ($node as $key => $child) {
                if (is_int($key) && is_array($child)) {
                    $matches[] = matches_search_constraints($child, $meta);
                }
            }
            return strtoupper((string) $node['relation']) === 'AND'
                ? !in_array(false, $matches, true)
                : in_array(true, $matches, true);
        }

        $key = (string) ($node['key'] ?? '');
        if ($key === '' || !array_key_exists($key, $meta)) {
            return false;
        }
        $actual = ($node['type'] ?? '') === 'NUMERIC' ? (int) $meta[$key] : (string) $meta[$key];
        $expected = ($node['type'] ?? '') === 'NUMERIC' ? (int) $node['value'] : (string) $node['value'];

        return match ($node['compare'] ?? '=') {
            '>=' => $actual >= $expected,
            '=' => $actual === $expected,
            default => false,
        };
    }

    $assertions = 0;
    $failures = [];
    $expect = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
        $assertions++;
        if (!$condition) {
            $failures[] = $message;
            fwrite(STDERR, "FAIL: {$message}\n");
        }
    };

    $search = new SiteSearch();
    $search->register();

    $expect(ElementorPublicTextIndex::$registrations === 1, 'the public Elementor text index is registered once');
    $expect(count(ElementorSearchAdapter::$instances) === 2, 'the site registers exactly the upcoming and all-content adapters');
    $adapters = [];
    foreach (ElementorSearchAdapter::$instances as $adapter) {
        $adapters[$adapter->queryId] = $adapter;
        $expect($adapter->registered, $adapter->queryId . ' is registered');
        $expect($adapter->maximum === 24, $adapter->queryId . ' retains the bounded result ceiling');
    }
    $expect(isset($adapters[SiteSearch::UPCOMING_QUERY_ID], $adapters[SiteSearch::ALL_QUERY_ID]), 'both documented Query IDs are wired');

    $upcoming = $search->upcomingSettings();
    $all = $search->allSettings();
    $expect($upcoming['post_types'] === ['event'], 'the upcoming scope is event-only');
    $expect(array_diff(['post', 'page', 'event', 'service'], $all['post_types']) === [], 'all-content covers every public searchable JPN type');
    $expect(!in_array('attachment', $all['post_types'], true) && !in_array('private_note', $all['post_types'], true), 'attachments and excluded content stay out of all-content search');
    $expect(array_diff(['title', 'content', 'excerpt', 'slug'], $all['fields']) === [], 'public titles, body text, excerpts, and slugs are searchable');
    $expect(array_diff(['location', 'location_address', 'additional_information', ElementorPublicTextIndex::META_KEY], $all['custom_fields']) === [], 'locations, event details, and rendered Elementor text are searchable');
    $expect($all['user_reference_fields'] === ['event_host'] && $all['authors'] === true, 'event hosts and public authors are searchable');
    $expect($all['taxonomies'] === ['category', 'area'], 'public topics and areas are searchable without post-format noise');
    $expect($all['results_per_page'] === 12 && $all['orderby'] === 'relevance', 'all-content keeps bounded relevance ordering');

    $query = new SearchFixtureQuery();
    $configured = $search->configureUpcomingQuery($query);
    $constraints = $configured['meta_constraints'];
    $now = (int) $constraints[0]['value'];
    $today = (int) $constraints[2][1]['value'];
    $beforeToday = $today - 1;
    $futureDay = $now + 86400;

    $expect($query->vars['post_type'] === ['event'], 'upcoming configuration cannot escape the event type');
    $expect($query->vars['meta_key'] === 'start_date_timestamp', 'upcoming ordering uses the canonical start timestamp');
    $expect($query->vars['orderby'] === ['meta_value_num' => 'ASC', 'ID' => 'ASC'] && $query->vars['order'] === 'ASC', 'upcoming events are stable and soonest-first');
    $expect(matches_search_constraints($constraints, ['start_date_timestamp' => $now + 60]), 'a future timed event is upcoming');
    $expect(matches_search_constraints($constraints, ['start_date_timestamp' => $now - 3600, 'end_date_timestamp' => $now + 3600]), 'an ongoing timed event remains upcoming through its end time');
    $expect(!matches_search_constraints($constraints, ['start_date_timestamp' => $now - 7200, 'end_date_timestamp' => $now - 60]), 'an ended timed event is excluded');
    $expect(matches_search_constraints($constraints, ['start_date_precision' => 'date', 'start_date_timestamp' => $today]), 'a date-only event remains upcoming throughout its local start day');
    $expect(matches_search_constraints($constraints, ['start_date_precision' => 'date', 'start_date_timestamp' => $beforeToday, 'end_date_timestamp' => $today]), 'a multi-day date-only event remains upcoming through its final local day');
    $expect(!matches_search_constraints($constraints, ['start_date_precision' => 'date', 'start_date_timestamp' => $beforeToday, 'end_date_timestamp' => $beforeToday]), 'a date-only event whose final local day passed is excluded');
    $expect(matches_search_constraints($constraints, ['start_date_precision' => 'date', 'start_date_timestamp' => $futureDay]), 'a future date-only event is upcoming');

    $plugin = (string) file_get_contents(dirname(__DIR__) . '/src/Plugin.php');
    $expect(str_contains($plugin, 'use Hexa\\JpnTools\\Search\\SiteSearch;'), 'the plugin bootstrap imports the site-search owner');
    $expect(str_contains($plugin, '(new SiteSearch())->register();'), 'the plugin bootstrap starts the live-search profiles');

    if ($failures !== []) {
        fwrite(STDERR, sprintf("%d/%d search assertions failed.\n", count($failures), $assertions));
        exit(1);
    }

    fwrite(STDOUT, sprintf("PASS: %d JPN search wiring, source, and date-boundary assertions.\n", $assertions));
}
