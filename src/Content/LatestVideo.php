<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Content;

use Hexa\PluginCore\Fields\Field;
use Hexa\PluginCore\Fields\FieldGroups;
use Hexa\PluginCore\Fields\Hooks;
use Hexa\PluginCore\Fields\OptionsPages;
use Hexa\PluginCore\PublicComponents\RelativeTime;

/**
 * The latest events video (the campaign's WhatsApp slideshow export) on the site.
 *
 * The campaign's video step publishes each new export with
 * `wp hexa-jpn video publish <file.mp4> --caption="…"`: the file goes into the
 * Media Library, the "Latest Events Video" option fields record it and when it
 * was made, the previous published export is deleted, and cached pages showing
 * it are purged. `[jpn_latest_video]` renders the player with a live
 * "Last updated X ago" line above it. Design lives in Elementor.
 */
final class LatestVideo
{
    public const SHORTCODE = 'jpn_latest_video';
    public const PAGE = 'jpn-latest-video';
    public const CACHE_TAG = 'jpn_latest_video';
    private const OWNED_META = '_jpn_latest_video_export';

    public function register(): void
    {
        Hooks::on('init', [$this, 'registerFields']);
        add_shortcode(self::SHORTCODE, [$this, 'render']);

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('hexa-jpn video publish', [$this, 'cliPublish']);
            \WP_CLI::add_command('hexa-jpn video status', [$this, 'cliStatus']);
        }
    }

    public function registerFields(): void
    {
        OptionsPages::add_sub([
            'page_title' => __('Latest Events Video', 'hexa-jpn-tools'),
            'menu_title' => __('Latest Video', 'hexa-jpn-tools'),
            'menu_slug' => self::PAGE,
            'parent_slug' => 'notifications-dashboard',
            'capability' => 'manage_options',
        ]);

        FieldGroups::add([
            'key' => 'group_jpn_latest_video',
            'title' => __('Latest Events Video', 'hexa-jpn-tools'),
            'fields' => [
                ['key' => 'field_jpn_latest_video', 'label' => 'Video', 'name' => 'jpn_latest_video', 'type' => 'file', 'return_format' => 'id', 'mime_types' => 'mp4', 'instructions' => 'Updated automatically by the JPN campaign video step (wp hexa-jpn video publish).'],
                ['key' => 'field_jpn_latest_video_updated', 'label' => 'Updated (Unix time)', 'name' => 'jpn_latest_video_updated', 'type' => 'number', 'readonly' => 1, 'wrapper' => ['width' => '50']],
                ['key' => 'field_jpn_latest_video_caption', 'label' => 'Caption', 'name' => 'jpn_latest_video_caption', 'type' => 'text', 'instructions' => 'For example the event window and count.', 'wrapper' => ['width' => '50']],
            ],
            'location' => [[['param' => 'options_page', 'operator' => '==', 'value' => self::PAGE]]],
        ]);
    }

    /**
     * Stores one exported MP4 as the latest events video.
     *
     * @return array{attachment:int,url:string,updated:int,replaced:int}
     * @throws \RuntimeException When the file cannot be stored.
     */
    public function publish(string $file, string $caption = '', ?int $updated = null): array
    {
        if (!is_readable($file) || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'mp4') {
            throw new \RuntimeException("Not a readable .mp4 file: {$file}");
        }
        $updated = $updated ?? (int) filemtime($file);

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $upload = wp_upload_bits('jpn-events-video-' . gmdate('Ymd-His', $updated) . '.mp4', null, (string) file_get_contents($file));
        if (!empty($upload['error'])) {
            throw new \RuntimeException('Upload failed: ' . $upload['error']);
        }

        $title = sprintf('JPN events video %s', wp_date('M j, Y g:i a', $updated));
        $id = wp_insert_attachment(['post_mime_type' => 'video/mp4', 'post_title' => $title, 'post_status' => 'inherit'], $upload['file'], 0, true);
        if (is_wp_error($id)) {
            @unlink($upload['file']);
            throw new \RuntimeException('Attachment failed: ' . $id->get_error_message());
        }
        wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $upload['file']));
        update_post_meta($id, self::OWNED_META, 1);

        $previous = (int) Field::get('jpn_latest_video', 'option', false);
        Field::update('jpn_latest_video', $id, 'option');
        Field::update('jpn_latest_video_updated', $updated, 'option');
        Field::update('jpn_latest_video_caption', $caption, 'option');

        // Only exports this publisher created are removed; a video chosen by hand stays in the library.
        if ($previous > 0 && $previous !== $id && get_post_meta($previous, self::OWNED_META, true)) {
            wp_delete_attachment($previous, true);
        }
        do_action('litespeed_purge', self::CACHE_TAG); // No-op without LiteSpeed Cache.

        return ['attachment' => (int) $id, 'url' => (string) wp_get_attachment_url($id), 'updated' => $updated, 'replaced' => $previous];
    }

    /** `[jpn_latest_video]`: the player with "Last updated X ago" above it, or nothing when no video is set. */
    public function render(): string
    {
        $id = (int) Field::get('jpn_latest_video', 'option', false);
        $url = $id > 0 ? (string) wp_get_attachment_url($id) : '';
        if ($url === '') {
            return '';
        }

        do_action('litespeed_tag_add', self::CACHE_TAG);
        $updated = (int) Field::get('jpn_latest_video_updated', 'option');
        $caption = trim((string) Field::get('jpn_latest_video_caption', 'option'));
        $meta = (array) wp_get_attachment_metadata($id);
        $size = !empty($meta['width']) && !empty($meta['height']) ? ' width="' . (int) $meta['width'] . '" height="' . (int) $meta['height'] . '"' : '';

        return '<figure class="jpn-latest-video">'
            . '<figcaption class="jpn-latest-video__meta"><span class="jpn-latest-video__dot" aria-hidden="true"></span>'
            . ($updated > 0 ? esc_html__('Last updated', 'hexa-jpn-tools') . ' ' . RelativeTime::html($updated, 'jpn-latest-video__time') : '')
            . ($caption !== '' ? '<span class="jpn-latest-video__caption">' . esc_html($caption) . '</span>' : '')
            . '</figcaption>'
            . '<video class="jpn-latest-video__player" src="' . esc_url($url) . '#t=0.1" controls playsinline preload="metadata"' . $size . '></video>'
            . '</figure>';
    }

    /**
     * Publishes an exported MP4 as the site's latest events video.
     *
     * ## OPTIONS
     *
     * <file>
     * : Path to the .mp4 made by images-export.
     *
     * [--caption=<text>]
     * : Short line shown after the age, for example "Sep 28 – Oct 11 · 35 events".
     *
     * [--updated=<unix>]
     * : When the video was made. Defaults to the file's modification time.
     *
     * @param string[] $args
     * @param array<string,string> $assoc
     */
    public function cliPublish(array $args, array $assoc): void
    {
        try {
            $result = $this->publish((string) $args[0], (string) ($assoc['caption'] ?? ''), isset($assoc['updated']) ? (int) $assoc['updated'] : null);
        } catch (\RuntimeException $exception) {
            \WP_CLI::error($exception->getMessage());
        }
        \WP_CLI::line((string) wp_json_encode($result));
        \WP_CLI::success('Latest events video updated.');
    }

    /** Shows the current latest events video. */
    public function cliStatus(): void
    {
        $id = (int) Field::get('jpn_latest_video', 'option', false);
        $updated = (int) Field::get('jpn_latest_video_updated', 'option');
        \WP_CLI::line((string) wp_json_encode([
            'attachment' => $id,
            'url' => $id > 0 ? (string) wp_get_attachment_url($id) : '',
            'updated' => $updated,
            'age' => $updated > 0 ? RelativeTime::text($updated, time()) : '',
            'caption' => (string) Field::get('jpn_latest_video_caption', 'option'),
        ]));
    }
}
