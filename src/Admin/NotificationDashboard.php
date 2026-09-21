<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Admin;

use Hexa\JpnTools\Events\EventDates;
use Hexa\JpnTools\Events\EventQueries;

final class NotificationDashboard
{
    public function __construct(private EventQueries $queries, private EventDates $dates)
    {
    }

    public function register(): void
    {
        add_action('acf/init', [$this, 'registerOptionsPage']);
        add_action('acf/save_post', [$this, 'refreshMessage'], 20);
    }

    public function registerOptionsPage(): void
    {
        if (!function_exists('acf_add_options_page')) {
            return;
        }
        acf_add_options_page([
            'page_title' => __('Notifications Dashboard', 'hexa-jpn-tools'),
            'menu_slug' => 'notifications-dashboard',
            'capability' => 'manage_options',
            'redirect' => false,
        ]);
    }

    public function refreshMessage(mixed $postId): void
    {
        if ($postId !== 'options'
            || sanitize_key((string) ($_GET['page'] ?? '')) !== 'notifications-dashboard'
            || !current_user_can('manage_options')
            || !function_exists('update_field')) {
            return;
        }

        $compiled = $this->compile($this->queries->forPeriod('week'));
        update_field('whatsapp_notification_body', $compiled['body'], 'option');
        update_field('whatsapp_notification_output', $compiled['message'], 'option');
    }

    public function compile(array $events): array
    {
        $header = function_exists('get_field') ? (string) get_field('whatsapp_notification_header', 'option') : '';
        $footer = function_exists('get_field') ? (string) get_field('whatsapp_notification_footer', 'option') : '';
        $parts = [];
        foreach ($events as $event) {
            $parts[] = $this->eventText($event);
        }

        return [
            'body' => implode('', $parts),
            'message' => implode('', self::splitMessages($parts, 1000000, $header, $footer)),
            'telegram' => self::splitMessages($parts, 4000, $header, $footer),
        ];
    }

    public static function splitMessages(array $events, int $limit, string $header, string $footer): array
    {
        $limit = max(1, $limit);
        $full = $header . implode('', array_map('strval', $events)) . $footer;
        if (strlen($full) <= $limit) {
            return [$full];
        }

        // Reserve room for labels such as "Part 12/12:" on multi-part messages.
        $payloadLimit = max(1, $limit - 32);
        $messages = [];
        $current = '';
        foreach (array_merge([$header], array_map('strval', $events), [$footer]) as $piece) {
            $piece = (string) $piece;
            while ($piece !== '') {
                $remaining = $payloadLimit - strlen($current);
                if ($remaining <= 0) {
                    $messages[] = $current;
                    $current = '';
                    continue;
                }

                if (strlen($piece) <= $remaining) {
                    $current .= $piece;
                    break;
                }

                if ($current !== '' && strlen($piece) <= $payloadLimit) {
                    $messages[] = $current;
                    $current = '';
                    continue;
                }

                $chunk = self::utf8ByteChunk($piece, $remaining);
                if ($chunk === '') {
                    $chunk = substr($piece, 0, $remaining);
                }
                $current .= $chunk;
                $piece = substr($piece, strlen($chunk));
                $messages[] = $current;
                $current = '';
            }
        }
        if ($current !== '' || $messages === []) {
            $messages[] = $current;
        }

        $total = count($messages);
        foreach ($messages as $index => $message) {
            $messages[$index] = 'Part ' . ($index + 1) . '/' . $total . ":\n" . $message;
        }
        return $messages;
    }

    private static function utf8ByteChunk(string $value, int $bytes): string
    {
        if ($bytes <= 0) {
            return '';
        }
        if (function_exists('mb_strcut')) {
            return (string) mb_strcut($value, 0, $bytes, 'UTF-8');
        }
        return substr($value, 0, $bytes);
    }

    private function eventText($event): string
    {
        $host = get_userdata((int) $event->post_author);
        $areaId = (int) get_post_meta($event->ID, 'area', true);
        $area = $areaId > 0 ? get_term($areaId, 'area') : null;
        $labels = array_values(array_filter([
            $host ? $host->display_name : '',
            $area && !is_wp_error($area) ? $area->name : '',
        ]));
        $text = '📣 ' . get_the_title($event->ID) . ($labels !== [] ? ' (' . implode(', ', $labels) . ')' : '') . "\n";

        $start = (int) get_post_meta($event->ID, 'start_date_timestamp', true);
        $end = (int) get_post_meta($event->ID, 'end_date_timestamp', true);
        if ($start > 0) {
            $text .= '🗓️ ' . $this->dates->formatTimestamp($start, 'F j, g:i a');
            if ($end > $start) {
                $text .= $this->dates->formatTimestamp($start, 'Y-m-d') === $this->dates->formatTimestamp($end, 'Y-m-d')
                    ? '-' . $this->dates->formatTimestamp($end, 'g:i a')
                    : '-' . $this->dates->formatTimestamp($end, 'F j, g:i a');
            }
            $text .= "\n";
        }
        $location = trim((string) get_post_meta($event->ID, 'location', true));
        if ($location !== '') {
            $text .= '📍 ' . $location . "\n";
        }
        $link = trim((string) get_post_meta($event->ID, 'link', true));
        $text .= 'Learn more: ' . ($link !== '' ? $link : get_permalink($event->ID)) . "\n\n";
        return $text;
    }
}
