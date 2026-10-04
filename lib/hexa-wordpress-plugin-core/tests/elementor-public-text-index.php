<?php

declare(strict_types=1);

$hooks = [];
$storedMeta = [209 => ['_hexa_elementor_public_text' => 'Old public index'], 210 => ['_hexa_elementor_public_text' => 'Stale protected index']];
$writes = [];
$currentUserId = 7;
$posts = [
    209 => (object) ['ID' => 209, 'post_type' => 'page', 'post_status' => 'publish', 'post_password' => ''],
    210 => (object) ['ID' => 210, 'post_type' => 'page', 'post_status' => 'publish', 'post_password' => 'secret'],
    1331 => (object) ['ID' => 1331, 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_password' => ''],
];

function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
    global $hooks;
    $hooks[$hook][$priority][] = $callback;
    return true;
}

function get_post(int $postId): ?object
{
    global $posts;
    return $posts[$postId] ?? null;
}

function get_post_type(int $postId): string
{
    $post = get_post($postId);
    return $post ? (string) $post->post_type : '';
}

function get_post_type_object(string $postType): ?object
{
    return match ($postType) {
        'page' => (object) ['public' => true, 'exclude_from_search' => false],
        'elementor_library' => (object) ['public' => false, 'exclude_from_search' => true],
        default => null,
    };
}

function get_post_types(array $args = [], string $output = 'names'): array
{
    return $output === 'objects'
        ? ['page' => get_post_type_object('page')]
        : ['page'];
}

function get_post_meta(int $postId, string $key, bool $single = false): mixed
{
    global $storedMeta;
    return $storedMeta[$postId][$key] ?? '';
}

function update_post_meta(int $postId, string $key, mixed $value): bool
{
    global $storedMeta, $writes;
    $storedMeta[$postId][$key] = (string) $value;
    $writes[] = ['update', $postId, $key];
    return true;
}

function delete_post_meta(int $postId, string $key): bool
{
    global $storedMeta, $writes;
    unset($storedMeta[$postId][$key]);
    $writes[] = ['delete', $postId, $key];
    return true;
}

function wp_strip_all_tags(string $text, bool $removeBreaks = false): string
{
    $text = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $text);
    $text = strip_tags($text);
    return $removeBreaks ? (string) preg_replace('/[\r\n\t ]+/', ' ', $text) : $text;
}

function get_bloginfo(string $show): string
{
    return $show === 'charset' ? 'UTF-8' : '';
}

function get_current_user_id(): int
{
    global $currentUserId;
    return $currentUserId;
}

function wp_set_current_user(int $userId): object
{
    global $currentUserId;
    $currentUserId = $userId;
    return (object) ['ID' => $userId];
}

function wp_is_post_revision(int $postId): bool
{
    return false;
}

function wp_is_post_autosave(int $postId): bool
{
    return false;
}

final class WP_Query
{
    /** @var int[] */
    public array $posts;
    public int $found_posts;
    public int $max_num_pages;

    /** @param array<string,mixed> $args */
    public function __construct(array $args)
    {
        if (in_array('elementor_library', (array) ($args['post_type'] ?? []), true)) {
            $this->posts = [209, 1331];
        } else {
            $this->posts = [209];
        }
        $this->found_posts = count($this->posts);
        $this->max_num_pages = 1;
        $GLOBALS['lastQueryArgs'] = $args;
    }
}

final class PublicTextDocument
{
    public function __construct(private int $id)
    {
    }

    public function is_built_with_elementor(): bool
    {
        return true;
    }

    /** @return array<int,array<string,mixed>> */
    public function get_elements_data(): array
    {
        return $this->id === 209
            ? [[
                'elType' => 'widget',
                'widgetType' => 'template',
                'settings' => ['template_id' => '1331', 'private_recipient' => 'hidden@example.com'],
                'elements' => [],
            ]]
            : [];
    }
}

final class PublicTextDocuments
{
    public function get(int $postId): PublicTextDocument
    {
        return new PublicTextDocument($postId);
    }
}

final class PublicTextFrontend
{
    public int $seenUserId = -1;
    public bool $sawGlobalPost = true;

    public function get_builder_content_for_display(int $postId, bool $withCss = false): string
    {
        $this->seenUserId = get_current_user_id();
        $this->sawGlobalPost = isset($GLOBALS['post']);

        return '<style>.private{content:"recipient@example.com"}</style>'
            . '<script>window.recipient="recipient@example.com";</script>'
            . '<h2>Featured &amp; Upcoming Events</h2>'
            . '<p>Public reusable template text.</p>'
            . '<form data-recipient="recipient@example.com"><label>Email</label></form>';
    }
}

eval('namespace Elementor; final class Plugin { public static $instance; }');
$frontend = new PublicTextFrontend();
\Elementor\Plugin::$instance = (object) [
    'documents' => new PublicTextDocuments(),
    'frontend' => $frontend,
];

$GLOBALS['post'] = (object) ['ID' => 999];

$root = dirname(__DIR__);
require $root . '/src/SearchQuery/ElementorPublicTextIndex.php';

use Hexa\PluginCore\SearchQuery\ElementorPublicTextIndex;

$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$index = new ElementorPublicTextIndex(['page']);
$index->register();

$expect(isset($hooks['elementor/editor/after_save'][20][0]), 'Elementor saves queue a public-text refresh.');
$expect(isset($hooks['updated_post_meta'][20][0]) && isset($hooks['save_post'][100][0]), 'Structured document and publication changes queue refreshes.');

$writesBeforeDryRun = count($writes);
$dryRun = $index->rebuild(1, 100, true);
$item = $dryRun['items'][0] ?? [];
$expect(count($writes) === $writesBeforeDryRun, 'Dry-run reports the exact selection without a post-meta write.');
$expect(($dryRun['would_index'] ?? 0) === 1 && ($item['post_id'] ?? 0) === 209, 'Dry-run selects the published public Elementor page.');
$expect(!empty($item['before_hash']) && !empty($item['after_hash']) && !isset($item['text']), 'Dry-run exposes only bound hashes and counts, never indexed text.');
$expect(($GLOBALS['lastQueryArgs']['post_status'] ?? '') === 'publish' && ($GLOBALS['lastQueryArgs']['has_password'] ?? null) === false, 'Rebuild selection is published and non-password content only.');

$result = $index->sync_post(209);
$indexed = (string) ($storedMeta[209][ElementorPublicTextIndex::META_KEY] ?? '');
$expect(($result['status'] ?? '') === 'indexed' && str_contains($indexed, 'Featured & Upcoming Events'), 'Supported frontend rendering indexes reusable-template public text.');
$expect(str_contains($indexed, 'Public reusable template text.') && str_contains($indexed, 'Email'), 'Visible public text remains searchable.');
$expect(!str_contains($indexed, 'recipient@example.com') && !str_contains($indexed, '.private'), 'Scripts, styles, attributes, and private form settings never enter the index.');
$expect($frontend->seenUserId === 0 && !$frontend->sawGlobalPost, 'Extraction renders in an anonymous context outside the prior post.');
$expect(get_current_user_id() === 7 && (($GLOBALS['post']->ID ?? 0) === 999), 'Extraction restores the prior user and post context.');

$protected = $index->sync_post(210);
$expect(($protected['status'] ?? '') === 'removed' && !isset($storedMeta[210][ElementorPublicTextIndex::META_KEY]), 'Password-protected content cannot retain a public-text index.');

$storedMeta[209][ElementorPublicTextIndex::META_KEY] = 'Old public index';
$index->queue_elementor_save(1331);
$index->flush_queue();
$expect(str_contains((string) ($storedMeta[209][ElementorPublicTextIndex::META_KEY] ?? ''), 'Featured & Upcoming Events'), 'Saving a reusable template refreshes its dependent public page.');

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, 'FAIL: ' . $failure . "\n");
    }
    exit(1);
}

echo "PASS: Elementor public text is anonymously rendered, safely indexed, dry-run bound, and refreshed through reusable templates.\n";
