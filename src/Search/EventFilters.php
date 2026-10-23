<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Search;

use Hexa\JpnTools\Events\EventDates;
use Hexa\JpnTools\Events\EventRelations;
use Hexa\PluginCore\QueryFilter\QuerySql;
use Hexa\PluginCore\Search\Search;
use Hexa\PluginCore\Search\SearchBox;
use Hexa\PluginCore\Search\SearchContext;
use Hexa\PluginCore\Search\SearchModule;
use Hexa\PluginCore\Search\SearchRegistry;

/**
 * Quick filters for the upcoming events list, on Hexa Core's one search:
 * `[jpn_event_filters]` above the Elementor loop with the Query ID
 * `jpn_home_upcoming_events` narrows that loop in place without a page
 * reload. Kids events are hidden until a visitor switches on "Show kids
 * events"; visitors also search, pick Today and/or Tomorrow, and pick one or
 * more locations. Location
 * counts cover the same upcoming events the loop lists, and locations with
 * none are hidden. `style="none"` drops the JPN look.
 */
final class EventFilters
{
    public const PROFILE = 'jpn_events';
    public const SHORTCODE = 'jpn_event_filters';
    public const LOOP_QUERY_ID = 'jpn_home_upcoming_events';

    /** Marks the queries behind the filter counts so they cover upcoming events only. */
    private const UPCOMING_ARG = 'jpn_upcoming_events';

    private bool $styled = false;

    public function __construct(private EventRelations $relations, private SiteSearch $search)
    {
    }

    public function register(): void
    {
        if (!class_exists(SearchRegistry::class) || !class_exists(SearchBox::class)) {
            return;
        }

        add_action(SearchRegistry::REGISTER_ACTION, [$this, 'registerProfile']);
        add_filter(SearchModule::TARGET_FILTER, [$this, 'markLoop'], 10, 2);
        add_action('elementor/query/' . self::LOOP_QUERY_ID, [$this, 'hideKidsByDefault'], 20);
        add_action('pre_get_posts', [$this, 'limitToUpcoming'], 20);
        add_shortcode(self::SHORTCODE, [$this, 'render']);
    }

    public function registerProfile(): void
    {
        SearchRegistry::register(self::PROFILE, [
            'search' => [$this->search, 'eventSettings'],
            'post_types' => ['event'],
            'context' => false,
            'filters' => [
                'when' => [
                    'type' => 'callback',
                    'control' => 'checkboxes',
                    'match' => 'any',
                    'options' => ['today' => __('Today', 'hexa-jpn-tools'), 'tomorrow' => __('Tomorrow', 'hexa-jpn-tools')],
                    'apply' => [$this, 'eventsOnDay'],
                    'label' => __('When', 'hexa-jpn-tools'),
                ],
                // Lifts the default exclusion below; it adds no condition of its own.
                'kids' => [
                    'type' => 'callback',
                    'control' => 'toggle',
                    'apply' => static fn (): ?array => null,
                    'label' => __('Show kids events', 'hexa-jpn-tools'),
                ],
                'location' => [
                    'type' => 'taxonomy',
                    'taxonomy' => 'area',
                    'options' => 'terms',
                    'control' => 'checkboxes',
                    'match' => 'any',
                    'hide_empty' => true,
                    'label' => __('Locations', 'hexa-jpn-tools'),
                ],
            ],
            'labels' => [
                'search' => __('Search upcoming events', 'hexa-jpn-tools'),
                'placeholder' => __('Search upcoming events…', 'hexa-jpn-tools'),
                'results_one' => __('%d upcoming event', 'hexa-jpn-tools'),
                'results_many' => __('%d upcoming events', 'hexa-jpn-tools'),
                'empty' => __('No upcoming events match. Try another location or clear the filters.', 'hexa-jpn-tools'),
                'clear' => __('Clear', 'hexa-jpn-tools'),
            ],
            'class' => 'jpn-event-filters',
        ]);
    }

    /** The upcoming events loop is the list these filters narrow. @param object|null $widget */
    public function markLoop($target, $widget = null): bool
    {
        if ($target || !is_object($widget) || !method_exists($widget, 'get_settings')) {
            return (bool) $target;
        }

        return in_array(self::LOOP_QUERY_ID, [(string) $widget->get_settings('post_query_query_id'), (string) $widget->get_settings('posts_query_id')], true);
    }

    /** @param \WP_Query $query */
    public function limitToUpcoming($query): void
    {
        if ($query instanceof \WP_Query && $query->get(self::UPCOMING_ARG)) {
            $this->relations->configureUpcomingQuery($query);
            $this->hideKidsByDefault($query);
        }
    }

    /**
     * Published events taking place on the local day `today` or `tomorrow`:
     * starting that day, or started earlier and still running.
     *
     * @return int[]|null
     */
    public function eventsOnDay($value): ?array
    {
        global $wpdb;
        $offset = ['today' => 0, 'tomorrow' => 1][is_string($value) ? $value : ''] ?? null;
        if ($offset === null) {
            return null;
        }
        $start = (new \DateTimeImmutable('@' . EventDates::startOfToday()))->setTimezone(wp_timezone())->modify('+' . $offset . ' day');
        $from = $start->getTimestamp();
        $to = $start->modify('+1 day')->getTimestamp();

        return array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p"
            . " INNER JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = 'start_date_timestamp'"
            . " LEFT JOIN {$wpdb->postmeta} e ON e.post_id = p.ID AND e.meta_key = 'end_date_timestamp' AND e.meta_value <> ''"
            . " WHERE p.post_type = 'event' AND p.post_status = 'publish'"
            . ' AND CAST(s.meta_value AS SIGNED) < %d AND GREATEST(CAST(s.meta_value AS SIGNED), COALESCE(CAST(e.meta_value AS SIGNED), 0)) >= %d',
            $to,
            $from
        )));
    }

    /** The loop and its counts leave kids events out unless "Show kids events" is on. @param \WP_Query $query */
    public function hideKidsByDefault($query): void
    {
        global $wpdb;
        $state = Search::state(self::PROFILE);
        if (!$query instanceof \WP_Query || ($state['values']['kids'] ?? '') === '1') {
            return;
        }

        QuerySql::attach($query, $wpdb->prepare(
            'NOT EXISTS (SELECT 1 FROM ' . $wpdb->postmeta . ' jpn_kids WHERE jpn_kids.post_id = ' . $wpdb->posts . '.ID AND jpn_kids.meta_key = %s AND jpn_kids.meta_value = %s)',
            'kids_event',
            '1'
        ));
    }

    /** @param array<string,mixed>|string $attributes */
    public function render($attributes = []): string
    {
        $attributes = is_array($attributes) ? $attributes : [];
        $attributes['style'] = ($attributes['style'] ?? '') === 'none' ? 'none' : 'default-1';
        $branded = $attributes['style'] !== 'none';

        $upcoming = static fn (array $args): array => $args + [self::UPCOMING_ARG => 1];
        add_filter(SearchContext::ARGS_FILTER, $upcoming);
        try {
            $html = SearchBox::render(self::PROFILE, $attributes);
        } finally {
            remove_filter(SearchContext::ARGS_FILTER, $upcoming);
        }

        return $html !== '' && $branded ? $this->style() . $html : $html;
    }

    /** The JPN look, printed once with the first filter bar. */
    private function style(): string
    {
        if ($this->styled) {
            return '';
        }
        $this->styled = true;
        $css = (string) file_get_contents(HEXA_JPN_TOOLS_PLUGIN_DIR . 'assets/event-filters.css');
        // Comments and line breaks out, so the block stays one inert line inside page content.
        $css = trim((string) preg_replace(['#/\*.*?\*/#s', '/\s+/'], ['', ' '], $css));

        return '<style id="jpn-event-filters-css">' . $css . '</style>';
    }
}
