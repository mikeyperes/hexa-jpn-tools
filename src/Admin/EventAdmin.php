<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Admin;

use Hexa\JpnTools\Events\EventDates;
use Throwable;
use WP_Post;

final class EventAdmin
{
    private static bool $updatingDates = false;

    public function __construct(private EventDates $dates)
    {
    }

    public function register(): void
    {
        add_action('acf/save_post', [$this, 'saveDerivedDates'], 20);
        add_action('save_post_event', [$this, 'saveDerivedDatesFromPost'], 100, 3);
        add_action('save_post_event', [$this, 'setDefaultImage'], 110, 3);
        add_action('pre_get_posts', [$this, 'includeEventsOnAuthorArchives']);
        add_action('add_meta_boxes_event', [$this, 'replaceAuthorBox'], 100);
        add_action('add_meta_boxes_event', [$this, 'removeRankMathBox'], 100);
        add_action('post_submitbox_misc_actions', [$this, 'renderFeaturedImageUrl']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('acf/prepare_field/name=start_date_timestamp', [$this, 'lockDerivedField']);
        add_filter('acf/prepare_field/name=end_date_timestamp', [$this, 'lockDerivedField']);
        add_filter('acf/prepare_field/name=start_date_display', [$this, 'lockDerivedField']);
        add_filter('acf/prepare_field/name=end_date_display', [$this, 'lockDerivedField']);

        if (!is_readable(WPMU_PLUGIN_DIR . '/jpn-event-admin-thumbnails.php')) {
            add_filter('manage_event_posts_columns', [$this, 'eventColumns']);
            add_action('manage_event_posts_custom_column', [$this, 'eventColumnValue'], 10, 2);
        }

        add_filter('manage_profile_posts_columns', [$this, 'profileColumns']);
        add_filter('manage_edit-profile_columns', [$this, 'profileColumns']);
        add_action('manage_profile_posts_custom_column', [$this, 'profileColumnValue'], 10, 2);
    }

    public function saveDerivedDates(mixed $postId): void
    {
        if (!is_numeric($postId) || get_post_type((int) $postId) !== 'event') {
            return;
        }
        $this->updateDerivedDates((int) $postId);
    }

    public function saveDerivedDatesFromPost(int $postId, WP_Post $post, bool $update): void
    {
        $this->updateDerivedDates($postId);
    }

    public function updateDerivedDates(int $postId): void
    {
        if (self::$updatingDates || wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }
        if (is_user_logged_in() && !current_user_can('edit_post', $postId)) {
            return;
        }

        self::$updatingDates = true;
        try {
            $this->updateOneDate($postId, 'start_date', 'start_date_timestamp', 'start_date_display');
            $this->updateOneDate($postId, 'end_date', 'end_date_timestamp', 'end_date_display');
        } finally {
            self::$updatingDates = false;
        }
    }

    public function setDefaultImage(int $postId, WP_Post $post, bool $update): void
    {
        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId) || has_post_thumbnail($postId)) {
            return;
        }
        $attachmentId = (int) get_option('hexa_jpn_default_featured_image_id', 1354);
        if ($attachmentId > 0 && get_post_type($attachmentId) === 'attachment') {
            set_post_thumbnail($postId, $attachmentId);
        }
    }

    public function includeEventsOnAuthorArchives($query): void
    {
        if (is_admin() || !$query->is_main_query() || !$query->is_author()) {
            return;
        }
        $query->set('post_type', ['event']);
    }

    public function replaceAuthorBox(): void
    {
        remove_meta_box('authordiv', 'event', 'normal');
        add_meta_box('authordiv', __('Host', 'hexa-jpn-tools'), 'post_author_meta_box', 'event', 'normal', 'default');
    }

    public function removeRankMathBox(): void
    {
        remove_meta_box('rank_math_metabox', 'event', 'normal');
    }

    public function renderFeaturedImageUrl(): void
    {
        $post = get_post();
        if (!$post || $post->post_type !== 'event') {
            return;
        }
        $url = get_the_post_thumbnail_url($post->ID, 'full');
        if (!$url) {
            return;
        }
        echo '<div class="misc-pub-section jpn-featured-image-url"><strong>'
            . esc_html__('Featured image:', 'hexa-jpn-tools') . '</strong> <a href="'
            . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html__('Open original', 'hexa-jpn-tools')
            . '</a></div>';
    }

    public function enqueueAssets(string $hook): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || !in_array($screen->id, ['event', 'edit-event', 'profile', 'user-edit'], true)) {
            return;
        }
        wp_enqueue_style('hexa-jpn-admin', HEXA_JPN_TOOLS_PLUGIN_URL . 'assets/admin.css', [], HEXA_JPN_TOOLS_VERSION);
    }

    public function lockDerivedField(array $field): array
    {
        $field['readonly'] = 1;
        $field['wrapper'] = is_array($field['wrapper'] ?? null) ? $field['wrapper'] : [];
        $field['wrapper']['class'] = trim((string) ($field['wrapper']['class'] ?? '') . ' jpn-derived-event-field');
        return $field;
    }

    public function eventColumns(array $columns): array
    {
        $updated = [];
        foreach ($columns as $key => $label) {
            $updated[$key] = $label;
            if ($key === 'cb') {
                $updated['event_thumbnail'] = __('Image', 'hexa-jpn-tools');
            }
        }
        return $updated;
    }

    public function eventColumnValue(string $column, int $postId): void
    {
        if ($column !== 'event_thumbnail') {
            return;
        }
        echo has_post_thumbnail($postId)
            ? get_the_post_thumbnail($postId, [56, 56], ['class' => 'jpn-admin-thumbnail'])
            : '&mdash;';
    }

    public function profileColumns(array $columns): array
    {
        $updated = [];
        foreach ($columns as $key => $label) {
            $updated[$key] = $label;
            if ($key === 'cb') {
                $updated['featured_image'] = __('Featured Image', 'hexa-jpn-tools');
            }
        }
        return $updated;
    }

    public function profileColumnValue(string $column, int $postId): void
    {
        if ($column !== 'featured_image' || get_post_type($postId) !== 'profile') {
            return;
        }
        echo has_post_thumbnail($postId)
            ? get_the_post_thumbnail($postId, [50, 50], ['class' => 'jpn-admin-thumbnail'])
            : esc_html__('No Featured Image', 'hexa-jpn-tools');
    }

    private function updateOneDate(int $postId, string $source, string $timestamp, string $display): void
    {
        $raw = trim((string) get_post_meta($postId, $source, true));
        $precisionKey = $source . '_precision';
        if ($raw === '') {
            update_post_meta($postId, $timestamp, '');
            update_post_meta($postId, $display, '');
            update_post_meta($postId, $precisionKey, '');
            return;
        }

        try {
            $normalized = $this->dates->normalize($raw);
            $precision = (string) get_post_meta($postId, $precisionKey, true);
            if ($precision !== 'date' || !str_ends_with($normalized['storage'], ' 00:00:00')) {
                $precision = $normalized['precision'];
            }
            update_post_meta($postId, $timestamp, (string) $normalized['timestamp']);
            update_post_meta(
                $postId,
                $display,
                $precision === 'date' ? $this->dates->formatTimestamp((int) $normalized['timestamp'], 'F j') : (string) $normalized['display']
            );
            update_post_meta($postId, $precisionKey, $precision);
        } catch (Throwable $exception) {
            update_post_meta($postId, $timestamp, '');
            update_post_meta($postId, $display, '');
            update_post_meta($postId, $precisionKey, '');
            error_log(sprintf('Hexa JPN Tools: invalid %s for event %d.', $source, $postId));
        }
    }
}
