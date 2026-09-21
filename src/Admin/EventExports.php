<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Admin;

use Hexa\JpnTools\Events\EventQueries;
use RuntimeException;
use Throwable;
use ZipArchive;

final class EventExports
{
    private const ACTION = 'hexa_jpn_export_events';
    private const NONCE_ACTION = 'hexa_jpn_export_nonce';

    public function __construct(private EventQueries $queries)
    {
    }

    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_ajax_' . self::ACTION, [$this, 'stream']);
        add_action('wp_ajax_export_todays_events', fn () => $this->legacyResponse('today'));
        add_action('wp_ajax_export_tomorrows_events', fn () => $this->legacyResponse('tomorrow'));
        add_action('wp_ajax_export_weeks_events', fn () => $this->legacyResponse('week'));
    }

    public function enqueue(string $hook): void
    {
        if ($hook !== 'toplevel_page_notifications-dashboard' || !current_user_can('manage_options')) {
            return;
        }

        wp_enqueue_style('hexa-jpn-admin', HEXA_JPN_TOOLS_PLUGIN_URL . 'assets/admin.css', [], HEXA_JPN_TOOLS_VERSION);
        wp_enqueue_script('hexa-jpn-admin', HEXA_JPN_TOOLS_PLUGIN_URL . 'assets/admin.js', [], HEXA_JPN_TOOLS_VERSION, true);
        wp_localize_script('hexa-jpn-admin', 'hexaJpnAdmin', [
            'downloadUrl' => admin_url('admin-ajax.php'),
            'action' => self::ACTION,
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'counts' => [
                'today' => count($this->queries->forPeriod('today')),
                'tomorrow' => count($this->queries->forPeriod('tomorrow')),
                'week' => count($this->queries->forPeriod('week')),
            ],
        ]);
    }

    public function stream(): void
    {
        $this->authorize();
        $period = sanitize_key((string) ($_GET['period'] ?? ''));
        if (!in_array($period, ['today', 'tomorrow', 'week'], true)) {
            wp_die(esc_html__('Invalid export period.', 'hexa-jpn-tools'), '', ['response' => 400]);
        }

        $zipPath = null;
        try {
            $zipPath = $this->createZip($this->queries->forPeriod($period));
            nocache_headers();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="jpn-events-' . $period . '-' . gmdate('Ymd-His') . '.zip"');
            header('Content-Length: ' . (string) filesize($zipPath));
            readfile($zipPath);
        } catch (Throwable $exception) {
            wp_die(esc_html($exception->getMessage()), '', ['response' => 500]);
        } finally {
            if (is_string($zipPath) && is_file($zipPath)) {
                unlink($zipPath);
            }
        }
        exit;
    }

    public function legacyResponse(string $period): void
    {
        $this->authorize();
        wp_send_json_success([
            'zip_url' => add_query_arg([
                'action' => self::ACTION,
                'period' => $period,
                'nonce' => wp_create_nonce(self::NONCE_ACTION),
            ], admin_url('admin-ajax.php')),
        ]);
    }

    private function authorize(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to export events.', 'hexa-jpn-tools'), '', ['response' => 403]);
        }
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
    }

    private function createZip(array $events): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException(__('ZIP support is unavailable.', 'hexa-jpn-tools'));
        }

        $temporary = wp_tempnam('hexa-jpn-events.zip');
        if (!$temporary) {
            throw new RuntimeException(__('Could not create the temporary export.', 'hexa-jpn-tools'));
        }

        $zip = new ZipArchive();
        if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            unlink($temporary);
            throw new RuntimeException(__('Could not open the temporary export.', 'hexa-jpn-tools'));
        }

        foreach (array_slice($events, 0, EventQueries::MAX_EVENTS) as $event) {
            $attachmentId = (int) get_post_thumbnail_id($event->ID);
            $path = $attachmentId > 0 ? get_attached_file($attachmentId) : false;
            if (!is_string($path) || !is_file($path) || !is_readable($path)) {
                continue;
            }
            $zip->addFile($path, $event->ID . '-' . sanitize_file_name(basename($path)));
        }
        if (!$zip->close()) {
            unlink($temporary);
            throw new RuntimeException(__('Could not finalize the temporary export.', 'hexa-jpn-tools'));
        }

        return $temporary;
    }
}
