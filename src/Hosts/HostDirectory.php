<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Hosts;

use Hexa\JpnTools\Events\EventDates;
use Hexa\JpnTools\Rest\HostController;
use Hexa\PluginCore\DirectorySearch\DirectorySearchRegistry;

/**
 * JPN host directory: registers the `jpn_hosts` profile with Hexa WP Core's
 * DirectorySearch and supplies JPN-specific data and card markup.
 *
 * Core owns search, filters, sorting mechanics, pagination, and interaction.
 * This class owns host event statistics, the area vocabulary, and the card.
 */
final class HostDirectory
{
    public const PROFILE = 'jpn_hosts';
    private const EVENTS_PER_CARD = 3;

    /** @var array<int,array{total:int,upcoming:int,next_ts:int,last_ts:int}>|null */
    private ?array $stats = null;

    public function __construct(private EventDates $dates)
    {
    }

    public function register(): void
    {
        if (!class_exists(DirectorySearchRegistry::class)) {
            return;
        }

        DirectorySearchRegistry::register(self::PROFILE, [
            'source' => 'users',
            'roles' => ['host'],
            'fields' => ['display_name', 'nicename', 'url'],
            'meta_keys' => ['address', 'instagram_url', 'instagram_handle', 'website', 'description'],
            'term_logic' => 'all',
            'word_matching' => 'prefix',
            'wildcards' => true,
            'min_chars' => 2,
            'per_page' => 12,
            'filters' => [
                'area' => [
                    'type' => 'meta',
                    'meta_key' => 'area',
                    'compare' => 'serialized',
                    'label' => __('Area', 'hexa-jpn-tools'),
                    'all_label' => __('All areas', 'hexa-jpn-tools'),
                    'options' => [$this, 'areaOptions'],
                ],
                'upcoming' => [
                    'type' => 'callback',
                    'control' => 'toggle',
                    'label' => __('Has upcoming events', 'hexa-jpn-tools'),
                    'apply' => [$this, 'hostsWithUpcoming'],
                ],
            ],
            'sorts' => [
                'active' => ['type' => 'callback', 'label' => __('Upcoming first', 'hexa-jpn-tools'), 'callback' => [$this, 'sortActive']],
                'events' => ['type' => 'callback', 'label' => __('Most events', 'hexa-jpn-tools'), 'callback' => [$this, 'sortByEvents']],
                'recent' => ['type' => 'callback', 'label' => __('Recently active', 'hexa-jpn-tools'), 'callback' => [$this, 'sortRecent']],
                'name' => ['type' => 'field', 'field' => 'name', 'label' => __('Name A–Z', 'hexa-jpn-tools')],
            ],
            'default_sort' => 'active',
            'prepare' => [$this, 'prepare'],
            'render_item' => [$this, 'renderCard'],
            'labels' => [
                'search' => __('Search hosts', 'hexa-jpn-tools'),
                'placeholder' => __('Name, venue, neighborhood… (try chab*)', 'hexa-jpn-tools'),
                'empty' => __('No hosts match your search. Try fewer words or clear a filter.', 'hexa-jpn-tools'),
                'results_one' => __('%d host', 'hexa-jpn-tools'),
                'results_many' => __('%d hosts', 'hexa-jpn-tools'),
            ],
            'cache_ttl' => 300,
            'cache_version' => HEXA_JPN_TOOLS_VERSION,
            'class' => 'jpn-hosts-directory',
        ]);
    }

    /** @return array<string,string> term_id => name, for areas actually used by hosts. */
    public function areaOptions(): array
    {
        $terms = get_terms(['taxonomy' => 'area', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC']);
        if (!is_array($terms)) {
            return [];
        }

        $used = $this->usedAreaIds();
        $options = [];
        foreach ($terms as $term) {
            if (isset($used[(int) $term->term_id])) {
                $options[(string) $term->term_id] = (string) $term->name;
            }
        }

        return $options;
    }

    /** @return int[]|null */
    public function hostsWithUpcoming(string $value): ?array
    {
        if ($value !== '1') {
            return null;
        }

        return array_keys(array_filter($this->stats(), static fn (array $row): bool => $row['upcoming'] > 0));
    }

    /** Hosts with upcoming events first (soonest next event), then most recently active, then inactive A–Z (input order). @param int[] $ids @return int[] */
    public function sortActive(array $ids): array
    {
        $stats = $this->stats();
        $position = array_flip($ids);
        usort($ids, static function (int $a, int $b) use ($stats, $position): int {
            $sa = $stats[$a] ?? null;
            $sb = $stats[$b] ?? null;
            $ua = $sa && $sa['upcoming'] > 0;
            $ub = $sb && $sb['upcoming'] > 0;
            if ($ua !== $ub) {
                return $ua ? -1 : 1;
            }
            if ($ua && $ub && $sa['next_ts'] !== $sb['next_ts']) {
                return $sa['next_ts'] <=> $sb['next_ts'];
            }
            $la = $sa['last_ts'] ?? 0;
            $lb = $sb['last_ts'] ?? 0;
            if ($la !== $lb) {
                return $lb <=> $la;
            }

            return $position[$a] <=> $position[$b];
        });

        return $ids;
    }

    /** @param int[] $ids @return int[] */
    public function sortByEvents(array $ids): array
    {
        return $this->sortByMetric($ids, 'total');
    }

    /** @param int[] $ids @return int[] */
    public function sortRecent(array $ids): array
    {
        $stats = $this->stats();
        $position = array_flip($ids);
        usort($ids, static function (int $a, int $b) use ($stats, $position): int {
            $ra = max($stats[$a]['last_ts'] ?? 0, ($stats[$a]['upcoming'] ?? 0) > 0 ? ($stats[$a]['next_ts'] ?? 0) : 0);
            $rb = max($stats[$b]['last_ts'] ?? 0, ($stats[$b]['upcoming'] ?? 0) > 0 ? ($stats[$b]['next_ts'] ?? 0) : 0);

            return $rb <=> $ra ?: $position[$a] <=> $position[$b];
        });

        return $ids;
    }

    /**
     * Batch data for one results page: host profile fields plus up to three
     * upcoming (or, failing that, most recent) events per host.
     *
     * @param int[] $ids
     * @return array<int,array<string,mixed>>
     */
    public function prepare(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        cache_users($ids);
        $stats = $this->stats();
        $events = $this->cardEvents($ids);
        $areaNames = [];
        $data = [];

        foreach ($ids as $id) {
            $summary = HostController::summary($id);
            if ($summary === []) {
                continue;
            }
            $user = get_userdata($id);
            $areaId = (int) ($summary['area_term_id'] ?? 0);
            if ($areaId > 0 && !isset($areaNames[$areaId])) {
                $term = get_term($areaId, 'area');
                $areaNames[$areaId] = $term && !is_wp_error($term) ? (string) $term->name : '';
            }
            $website = (string) ($summary['website'] ?: ($user ? $user->user_url : ''));
            $address = trim((string) preg_replace('/\s+/u', ' ', wp_strip_all_tags((string) get_user_meta($id, 'address', true))));

            $data[$id] = [
                'name' => (string) $summary['display_name'],
                'url' => get_author_posts_url($id, (string) $summary['slug']),
                'area' => $areaId > 0 ? $areaNames[$areaId] : '',
                'address' => mb_substr($address, 0, 90),
                'website' => $website,
                'instagram' => (string) ($summary['instagram_handle'] ?? ''),
                'avatar' => $this->avatarUrl($id),
                'stats' => $stats[$id] ?? ['total' => 0, 'upcoming' => 0, 'next_ts' => 0, 'last_ts' => 0],
                'events' => $events[$id] ?? [],
            ];
        }

        return $data;
    }

    /** @param array<string,mixed> $host */
    public function renderCard(int $id, array $host): string
    {
        if ($host === []) {
            return '';
        }

        $name = (string) $host['name'];
        $url = (string) $host['url'];
        $stats = $host['stats'];
        $upcoming = (int) $stats['upcoming'];

        $media = $host['avatar'] !== ''
            ? '<img src="' . esc_url((string) $host['avatar']) . '" alt="" loading="lazy" width="96" height="96">'
            : '<span class="jpn-host__monogram" aria-hidden="true">' . esc_html($this->initials($name)) . '</span>';

        $meta = [];
        if ($host['area'] !== '') {
            $meta[] = '<span class="jpn-host__chip jpn-host__chip--area">' . esc_html((string) $host['area']) . '</span>';
        }
        if ($host['address'] !== '') {
            $meta[] = '<span class="jpn-host__chip">' . esc_html((string) $host['address']) . '</span>';
        }
        if ($host['website'] !== '') {
            $domain = (string) preg_replace('#^www\.#i', '', (string) wp_parse_url((string) $host['website'], PHP_URL_HOST));
            if ($domain !== '') {
                $meta[] = '<a class="jpn-host__chip jpn-host__chip--link" href="' . esc_url((string) $host['website']) . '" target="_blank" rel="noopener nofollow">' . esc_html($domain) . ' ↗</a>';
            }
        }
        if ($host['instagram'] !== '') {
            $meta[] = '<a class="jpn-host__chip jpn-host__chip--link" href="' . esc_url('https://www.instagram.com/' . rawurlencode((string) $host['instagram']) . '/') . '" target="_blank" rel="noopener nofollow">@' . esc_html((string) $host['instagram']) . '</a>';
        }

        $eventsHtml = '';
        if ($host['events'] !== []) {
            $label = $upcoming > 0 ? __('Upcoming', 'hexa-jpn-tools') : __('Recent events', 'hexa-jpn-tools');
            $eventsHtml = '<div class="jpn-host__events"><p class="jpn-host__events-label">' . esc_html($label) . '</p><ul>';
            foreach ($host['events'] as $event) {
                $eventsHtml .= '<li' . ($event['upcoming'] ? ' class="is-upcoming"' : '') . '><a href="' . esc_url($event['url']) . '">'
                    . '<time datetime="' . esc_attr(gmdate('c', $event['ts'])) . '">' . esc_html($this->dates->formatTimestamp($event['ts'], 'M j')) . '</time>'
                    . '<span>' . esc_html($event['title']) . '</span></a></li>';
            }
            $eventsHtml .= '</ul></div>';
        }

        if ($upcoming > 0 && $stats['next_ts'] > 0) {
            $when = sprintf(__('Next: %s', 'hexa-jpn-tools'), $this->dates->formatTimestamp((int) $stats['next_ts'], 'D, M j'));
        } elseif ($stats['last_ts'] > 0) {
            $when = sprintf(__('Last: %s', 'hexa-jpn-tools'), $this->dates->formatTimestamp((int) $stats['last_ts'], 'M j, Y'));
        } else {
            $when = __('No events yet', 'hexa-jpn-tools');
        }

        return '<article class="jpn-host' . ($upcoming > 0 ? ' has-upcoming' : '') . '">'
            . '<a class="jpn-host__media" href="' . esc_url($url) . '" tabindex="-1" aria-hidden="true">' . $media . '</a>'
            . '<div class="jpn-host__main">'
            . '<h3 class="jpn-host__name"><a href="' . esc_url($url) . '">' . esc_html($name) . '</a></h3>'
            . ($meta !== [] ? '<div class="jpn-host__meta">' . implode('', $meta) . '</div>' : '')
            . $eventsHtml
            . '</div>'
            . '<div class="jpn-host__side">'
            . '<div class="jpn-host__stats">'
            . '<p class="jpn-host__stat"><b>' . esc_html(number_format_i18n((int) $stats['total'])) . '</b><span>' . esc_html(_n('event', 'events', (int) $stats['total'], 'hexa-jpn-tools')) . '</span></p>'
            . '<p class="jpn-host__stat' . ($upcoming > 0 ? ' is-live' : '') . '"><b>' . esc_html(number_format_i18n($upcoming)) . '</b><span>' . esc_html__('upcoming', 'hexa-jpn-tools') . '</span></p>'
            . '</div>'
            . '<p class="jpn-host__when">' . esc_html($when) . '</p>'
            . '<a class="jpn-host__cta" href="' . esc_url($url) . '">' . esc_html__('View host', 'hexa-jpn-tools') . ' <span aria-hidden="true">→</span></a>'
            . '</div>'
            . '</article>';
    }

    /**
     * Event statistics for every host with events, from one aggregate query.
     *
     * @return array<int,array{total:int,upcoming:int,next_ts:int,last_ts:int}>
     */
    private function stats(): array
    {
        if ($this->stats !== null) {
            return $this->stats;
        }

        global $wpdb;
        [$today] = $this->dates->calendarWindow('today');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.post_author AS host_id,
                    COUNT(*) AS total,
                    SUM(CAST(m.meta_value AS SIGNED) >= %d) AS upcoming,
                    MIN(CASE WHEN CAST(m.meta_value AS SIGNED) >= %d THEN CAST(m.meta_value AS SIGNED) END) AS next_ts,
                    MAX(CASE WHEN CAST(m.meta_value AS SIGNED) < %d THEN CAST(m.meta_value AS SIGNED) END) AS last_ts
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'start_date_timestamp'
             WHERE p.post_type = 'event' AND p.post_status = 'publish' AND p.post_author > 0
             GROUP BY p.post_author",
            $today,
            $today,
            $today
        ), ARRAY_A);

        $this->stats = [];
        foreach ((array) $rows as $row) {
            $this->stats[(int) $row['host_id']] = [
                'total' => (int) $row['total'],
                'upcoming' => (int) $row['upcoming'],
                'next_ts' => (int) $row['next_ts'],
                'last_ts' => (int) $row['last_ts'],
            ];
        }

        return $this->stats;
    }

    /**
     * Up to EVENTS_PER_CARD events per host: the soonest upcoming ones, or the
     * most recent past ones when the host has nothing upcoming.
     *
     * @param int[] $ids
     * @return array<int,array<int,array{url:string,title:string,ts:int,upcoming:bool}>>
     */
    private function cardEvents(array $ids): array
    {
        global $wpdb;
        [$today] = $this->dates->calendarWindow('today');
        $in = implode(',', array_map('intval', $ids));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT host_id, ID, ts, is_upcoming FROM (
                SELECT p.post_author AS host_id, p.ID, CAST(m.meta_value AS SIGNED) AS ts,
                       (CAST(m.meta_value AS SIGNED) >= %d) AS is_upcoming,
                       MAX(CAST(m.meta_value AS SIGNED) >= %d) OVER (PARTITION BY p.post_author) AS host_has_upcoming,
                       ROW_NUMBER() OVER (
                           PARTITION BY p.post_author
                           ORDER BY (CAST(m.meta_value AS SIGNED) >= %d) DESC,
                                    CASE WHEN CAST(m.meta_value AS SIGNED) >= %d THEN CAST(m.meta_value AS SIGNED) END ASC,
                                    CAST(m.meta_value AS SIGNED) DESC, p.ID DESC
                       ) AS rn
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'start_date_timestamp'
                WHERE p.post_type = 'event' AND p.post_status = 'publish' AND p.post_author IN ({$in})
            ) ranked WHERE rn <= %d AND (is_upcoming = 1 OR host_has_upcoming = 0) ORDER BY host_id, rn",
            $today,
            $today,
            $today,
            $today,
            self::EVENTS_PER_CARD
        ), ARRAY_A);

        $postIds = array_map(static fn (array $row): int => (int) $row['ID'], (array) $rows);
        if ($postIds !== []) {
            _prime_post_caches($postIds, false, false);
        }

        $events = [];
        foreach ((array) $rows as $row) {
            $postId = (int) $row['ID'];
            $events[(int) $row['host_id']][] = [
                'url' => (string) get_permalink($postId),
                'title' => wp_strip_all_tags(get_the_title($postId)),
                'ts' => (int) $row['ts'],
                'upcoming' => (bool) (int) $row['is_upcoming'],
            ];
        }

        return $events;
    }

    /** @param int[] $ids @return int[] */
    private function sortByMetric(array $ids, string $metric): array
    {
        $stats = $this->stats();
        $position = array_flip($ids);
        usort($ids, static fn (int $a, int $b): int => (($stats[$b][$metric] ?? 0) <=> ($stats[$a][$metric] ?? 0)) ?: $position[$a] <=> $position[$b]);

        return $ids;
    }

    /** @return array<int,true> */
    private function usedAreaIds(): array
    {
        global $wpdb;
        $values = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT um.meta_value FROM {$wpdb->usermeta} um
             INNER JOIN {$wpdb->usermeta} cap ON cap.user_id = um.user_id AND cap.meta_key = %s AND cap.meta_value LIKE %s
             WHERE um.meta_key = 'area' AND um.meta_value <> ''",
            $wpdb->get_blog_prefix() . 'capabilities',
            '%' . $wpdb->esc_like('"host"') . '%'
        ));

        $used = [];
        foreach ((array) $values as $value) {
            foreach ((array) maybe_unserialize($value) as $termId) {
                if (is_numeric($termId) && (int) $termId > 0) {
                    $used[(int) $termId] = true;
                }
            }
        }

        return $used;
    }

    /** Uploaded One User Avatar image, or '' so the card shows a monogram instead of a gray silhouette. */
    private function avatarUrl(int $userId): string
    {
        global $wpdb;
        $attachmentId = (int) get_user_meta($userId, $wpdb->get_blog_prefix() . 'user_avatar', true);
        if ($attachmentId <= 0) {
            return '';
        }
        $url = (string) wp_get_attachment_image_url($attachmentId, 'thumbnail');
        if ($url === '' || preg_match('/no-photo|placeholder|default-avatar/i', $url)) {
            return '';
        }

        return $url;
    }

    private function initials(string $name): string
    {
        $words = preg_split('/[\s&+\-–]+/u', trim((string) preg_replace('/[^\p{L}\p{N}\s&+\-–]/u', '', $name))) ?: [];
        $letters = '';
        foreach ($words as $word) {
            if ($word !== '' && !in_array(mb_strtolower($word), ['the', 'of', 'at', 'and', 'for'], true)) {
                $letters .= mb_strtoupper(mb_substr($word, 0, 1));
            }
            if (mb_strlen($letters) >= 2) {
                break;
            }
        }

        return $letters !== '' ? $letters : '•';
    }
}
