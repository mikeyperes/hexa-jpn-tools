<?php

declare(strict_types=1);

$hooks = [];
$context = ['admin' => false, 'ajax' => false, 'cron' => false, 'rest' => true];

function add_filter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
    global $hooks;
    $hooks[$hook][$priority][] = $callback;
    return true;
}

function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
    return add_filter($hook, $callback, $priority, $acceptedArgs);
}

function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
{
    return $value;
}

function sanitize_key(string $value): string
{
    return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower($value));
}

function wp_unslash(mixed $value): mixed
{
    return $value;
}

function is_admin(): bool
{
    global $context;
    return $context['admin'];
}

function wp_doing_ajax(): bool
{
    global $context;
    return $context['ajax'];
}

function wp_doing_cron(): bool
{
    global $context;
    return $context['cron'];
}

function wp_is_serving_rest_request(): bool
{
    global $context;
    return $context['rest'];
}

function is_user_logged_in(): bool
{
    return false;
}

final class ElementorAdapterQuery
{
    /** @param array<string,mixed> $vars */
    public function __construct(public array $vars, private bool $main = false)
    {
    }

    public function get(string $key): mixed
    {
        return $this->vars[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->vars[$key] = $value;
    }

    public function is_main_query(): bool
    {
        return $this->main;
    }
}

final class ElementorAdapterWidget
{
    /** @var array<string,array<string,string>> */
    public array $attributes = [];

    public function __construct(private string $name, private string $queryId)
    {
    }

    public function get_name(): string
    {
        return $this->name;
    }

    /** @return array<string,string> */
    public function get_settings_for_display(): array
    {
        return ['search_query_query_id' => $this->queryId];
    }

    public function add_render_attribute(string $group, string $name, string $value): void
    {
        $this->attributes[$group][$name] = $value;
    }
}

final class ElementorAdapterDatabase
{
    public string $posts = 'wp_posts';
    public string $postmeta = 'wp_postmeta';
    public string $terms = 'wp_terms';
    public string $term_taxonomy = 'wp_term_taxonomy';
    public string $term_relationships = 'wp_term_relationships';
    public string $users = 'wp_users';

    public function esc_like(string $value): string
    {
        return addcslashes($value, '_%\\');
    }

    public function prepare(string $sql, mixed ...$values): string
    {
        $index = 0;
        return (string) preg_replace_callback('/%[sd]/', static function (array $match) use (&$index, $values): string {
            $value = $values[$index++] ?? '';
            return $match[0] === '%d' ? (string) (int) $value : "'" . str_replace("'", "''", (string) $value) . "'";
        }, $sql);
    }
}

$root = dirname(__DIR__);
require $root . '/src/QueryFilter/NaturalTimeWindow.php';
require $root . '/src/SearchQuery/SearchQueryConfiguration.php';
require $root . '/src/SearchQuery/SearchTermParser.php';
require $root . '/src/SearchQuery/SearchMatchSql.php';
require $root . '/src/SearchQuery/MetaConstraintSql.php';
require $root . '/src/SearchQuery/SearchQueryEngine.php';
require $root . '/src/SearchQuery/ElementorSearchAdapter.php';

use Hexa\PluginCore\SearchQuery\ElementorSearchAdapter;

$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$providerCalls = 0;
$configured = 0;
$settings = [
    'enabled' => true,
    'scope' => 'all',
    'term_logic' => 'all',
    'word_matching' => 'prefix',
    'post_types' => ['event'],
    'fields' => ['title'],
    'custom_fields' => ['location'],
    'user_reference_fields' => ['event_host'],
    'results_per_page' => 12,
];

$adapter = new ElementorSearchAdapter(
    static function () use (&$providerCalls, $settings): array {
        ++$providerCalls;
        return $settings;
    },
    'jpn_search_upcoming',
    static function ($query) use (&$configured): void {
        ++$configured;
        $query->set('post_type', ['event', 'page']);
        $query->set('meta_key', 'start_date_timestamp');
    },
    24
);
$adapter->register();

$expect(isset($hooks['elementor/query/jpn_search_upcoming'][20][0]), 'The adapter binds only its exact registered Elementor Query ID.');
$expect(!isset($hooks['elementor/query/jpn_search_all']), 'The adapter does not register unrelated Query IDs.');

$query = new ElementorAdapterQuery(['s' => 'Miami Chabad', 'posts_per_page' => -1, 'suppress_filters' => false]);
$widget = new ElementorAdapterWidget('search', 'jpn_search_upcoming');
$registeredQueryCallback = $hooks['elementor/query/jpn_search_upcoming'][20][0] ?? null;
if (is_callable($registeredQueryCallback)) {
    $registeredQueryCallback($query, $widget);
}

$expect(!$query->is_main_query(), 'The focused fixture exercises Elementor live search as a non-main REST query.');
$expect($providerCalls === 1 && $configured === 1, 'The registered Query ID callback reaches the Core profile once from Elementor pre_get_posts.');
$expect($query->get('post_type') === ['event'], 'A configurator cannot escape the profile post-type allowlist.');
$expect($query->get('post_status') === 'publish' && $query->get('has_password') === false, 'Elementor live results are public, published, and non-password content.');
$expect($query->get('posts_per_page') === 12, 'The configured bounded page size replaces an unbounded Elementor value.');

$searchFilter = $hooks['posts_search'][999][0] ?? null;
$GLOBALS['wpdb'] = new ElementorAdapterDatabase();
$sql = is_callable($searchFilter) ? $searchFilter('ORIGINAL', $query) : '';
$expect(str_contains($sql, "post_title REGEXP '(^|[^[:alnum:]_])Miami'"), 'The exact query state prepared in pre_get_posts persists to Core bounded prefix matching.');
$expect(str_contains($sql, 'hexa_sq_ur') && str_contains($sql, 'wp_users'), 'Organizer display names use the generic user-reference source.');

$markCallback = $hooks['elementor/frontend/widget/before_render'][10][0] ?? null;
if (is_callable($markCallback)) {
    $markCallback($widget);
}
$expect(($widget->attributes['_wrapper']['data-hexa-search-query-id'] ?? '') === 'jpn_search_upcoming', 'Only the verified widget is marked for scoped Core request-state behavior.');

$unrelated = new ElementorAdapterQuery(['s' => 'Miami', 'posts_per_page' => 6]);
$adapter->prepare_elementor_query($unrelated, new ElementorAdapterWidget('loop-grid', 'jpn_search_upcoming'));
$adapter->prepare_elementor_query($unrelated, new ElementorAdapterWidget('search', 'another_query'));
$expect($providerCalls === 1 && $unrelated->get('hexa_search_query_active') === null, 'Unrelated widgets and Query IDs remain untouched before profile discovery.');

$context['admin'] = true;
$admin = new ElementorAdapterQuery(['s' => 'Miami', 'posts_per_page' => 6]);
$adapter->prepare_elementor_query($admin, $widget);
$expect($providerCalls === 1 && $admin->get('hexa_search_query_active') === null, 'Ordinary admin queries remain untouched.');

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, 'FAIL: ' . $failure . "\n");
    }
    exit(1);
}

echo "PASS: Elementor live Search uses exact trusted query scoping and bounded public Core matching.\n";
