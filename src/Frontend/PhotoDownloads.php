<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Frontend;

use Hexa\JpnTools\Events\EventDates;
use Hexa\JpnTools\Events\EventQueries;
use Hexa\JpnTools\Rest\EventController;
use WP_REST_Request;
use WP_REST_Response;
use ZipArchive;

/**
 * Every upcoming event photo in one go: a "Save all photos" button (the phone's share sheet saves
 * them to the camera roll) and one ZIP with the same photos, named by date and title.
 */
final class PhotoDownloads
{
    private const FOLDER = 'hexa-jpn-photos';

    public function __construct(private EventQueries $queries, private EventDates $dates)
    {
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            register_rest_route(EventController::NAMESPACE, '/photos.zip', [
                'methods' => 'GET',
                'callback' => [$this, 'zip'],
                'permission_callback' => '__return_true',
                'args' => ['days' => ['default' => 14, 'sanitize_callback' => 'absint']],
            ]);
        });
    }

    /**
     * The next $days days' event photos (full size), in date order, each with its file name.
     *
     * @return array<int, array{url:string, path:string, name:string}>
     */
    public function files(int $days): array
    {
        $files = [];
        foreach ($this->queries->nextDays($days) as $event) {
            $attachmentId = (int) get_post_thumbnail_id($event->ID);
            $url = $attachmentId > 0 ? (string) wp_get_attachment_image_url($attachmentId, 'full') : '';
            $path = $attachmentId > 0 ? (string) get_attached_file($attachmentId) : '';
            if ($url === '' || $path === '' || !is_readable($path)) {
                continue;
            }
            $start = (int) get_post_meta($event->ID, 'start_date_timestamp', true);
            $title = trim(preg_replace('/[\\\\\/:*?"<>|]+/', '-', wp_strip_all_tags(get_the_title($event->ID))) ?? '');
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'jpg';
            $name = trim(sprintf(
                '%02d %s %s',
                count($files) + 1,
                $start > 0 ? $this->dates->formatTimestamp($start, 'D n-j') : '',
                function_exists('mb_substr') ? mb_substr($title, 0, 60) : substr($title, 0, 60)
            ));
            $files[] = ['url' => $url, 'path' => $path, 'name' => preg_replace('/\s+/', ' ', $name) . '.' . $extension];
        }

        return $files;
    }

    /** The public ZIP link for the next $days days. */
    public function zipUrl(int $days): string
    {
        return add_query_arg('days', max(1, $days), rest_url(EventController::NAMESPACE . '/photos.zip'));
    }

    /**
     * Build (or reuse) the ZIP of the current photos and send the visitor to it. The file name
     * changes whenever the event photos change, so a phone never gets yesterday's ZIP from a cache.
     */
    public function zip(WP_REST_Request $request): WP_REST_Response
    {
        $days = max(1, min(31, (int) $request->get_param('days')));
        $files = $this->files($days);
        if ($files === [] || !class_exists(ZipArchive::class)) {
            return new WP_REST_Response(['message' => __('No event photos to download for this period.', 'hexa-jpn-tools')], 404);
        }

        $uploads = wp_upload_dir();
        $folder = trailingslashit($uploads['basedir']) . self::FOLDER;
        wp_mkdir_p($folder);
        $fingerprint = md5((string) wp_json_encode(array_map(
            static fn (array $file): array => [$file['name'], $file['path'], (int) @filemtime($file['path'])],
            $files
        )));
        $name = sprintf('jpnmiami-event-photos-%dd-%s-%s.zip', $days, wp_date('Y-m-d'), substr($fingerprint, 0, 10));
        $target = $folder . '/' . $name;

        if (!is_file($target)) {
            $temporary = $target . '.tmp';
            $zip = new ZipArchive();
            if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return new WP_REST_Response(['message' => __('The photo ZIP could not be created.', 'hexa-jpn-tools')], 500);
            }
            foreach ($files as $file) {
                $zip->addFile($file['path'], $file['name']);
                $zip->setCompressionName($file['name'], ZipArchive::CM_STORE);
            }
            $zip->close();
            rename($temporary, $target);
            // Earlier ZIPs for the same window are superseded by this one.
            foreach ((array) glob($folder . '/jpnmiami-event-photos-' . $days . 'd-*.zip') as $old) {
                if ($old !== $target) {
                    @unlink($old);
                }
            }
        }

        $response = new WP_REST_Response(null, 302);
        $response->header('Location', trailingslashit($uploads['baseurl']) . self::FOLDER . '/' . $name);
        $response->header('Cache-Control', 'no-store');

        return $response;
    }

    /** The "Save all photos" button and ZIP link shown above the photo grid. */
    public function toolbar(int $days): string
    {
        $files = $this->files($days);
        if ($files === []) {
            return '';
        }
        $count = count($files);
        wp_enqueue_script('hexa-jpn-photo-downloads', HEXA_JPN_TOOLS_PLUGIN_URL . 'assets/photo-downloads.js', [], HEXA_JPN_TOOLS_VERSION, true);
        $list = array_map(static fn (array $file): array => ['url' => $file['url'], 'name' => $file['name']], $files);

        return '<div class="jpn-photos-download" data-files="' . esc_attr((string) wp_json_encode($list)) . '"'
            . ' data-preparing="' . esc_attr(sprintf(__('Preparing %d photos…', 'hexa-jpn-tools'), $count)) . '">'
            . '<button type="button" class="jpn-photos-download__save" hidden>' . esc_html(sprintf(__('📥 Save all %d photos to your phone', 'hexa-jpn-tools'), $count)) . '</button>'
            . '<a class="jpn-photos-download__zip" href="' . esc_url($this->zipUrl($days)) . '">' . esc_html(sprintf(__('Download all %d photos (ZIP)', 'hexa-jpn-tools'), $count)) . '</a>'
            . '</div>';
    }
}
