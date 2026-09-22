<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Content;

use Hexa\JpnTools\Events\EventDates;

final class AcfFields
{
    public function __construct(private ?EventDates $dates = null)
    {
        $this->dates ??= new EventDates();
    }

    public function register(): void
    {
        add_action('acf/init', [$this, 'registerFields'], 5);
        add_filter('acf/format_value/name=start_date', [$this, 'formatStartDate'], 20, 2);
    }

    public function formatStartDate(mixed $value, mixed $postId): mixed
    {
        if (!is_numeric($postId) || get_post_meta((int) $postId, 'start_date_precision', true) !== 'date') {
            return $value;
        }

        $timestamp = (int) get_post_meta((int) $postId, 'start_date_timestamp', true);
        return $timestamp > 0 ? $this->dates->formatTimestamp($timestamp, 'F j, Y') : $value;
    }

    public function registerFields(): void
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key' => 'group_6768f6933c3ea',
            'title' => 'Event',
            'fields' => [
                $this->dateField('field_6768f693e478f', 'Start Date', 'start_date'),
                $this->dateField('field_6768f6eada5b2', 'End Date', 'end_date'),
                ['key' => 'field_676deb7cee578', 'label' => 'Link', 'name' => 'link', 'type' => 'text'],
                ['key' => 'field_676decfb3645d', 'label' => 'Additional Photos', 'name' => 'additional_photos', 'type' => 'gallery', 'return_format' => 'array', 'preview_size' => 'medium', 'library' => 'all'],
                ['key' => 'field_67bf95c75e6a6', 'label' => 'Featured Event', 'name' => 'featured_event', 'type' => 'true_false', 'ui' => 1],
                ['key' => 'field_hexa_jpn_kids_event', 'label' => 'Kids Event', 'name' => 'kids_event', 'type' => 'true_false', 'ui' => 1],
                ['key' => 'field_hexa_jpn_event_area', 'label' => 'Area', 'name' => 'area', 'type' => 'taxonomy', 'taxonomy' => 'area', 'field_type' => 'select', 'return_format' => 'id', 'add_term' => 1, 'save_terms' => 1, 'load_terms' => 1, 'allow_null' => 1],
                ['key' => 'field_hexa_jpn_event_location', 'label' => 'Location', 'name' => 'location', 'type' => 'text'],
                ['key' => 'field_hexa_jpn_event_location_address', 'label' => 'Location Address', 'name' => 'location_address', 'type' => 'text'],
                ['key' => 'field_67c0cff435406', 'label' => 'Additional Information', 'name' => 'additional_information', 'type' => 'wysiwyg', 'tabs' => 'all', 'toolbar' => 'full', 'media_upload' => 1],
                $this->derivedField('field_676cd86b3f599', 'Start Date Timestamp', 'start_date_timestamp'),
                $this->derivedField('field_676cd8753f59a', 'End Date Timestamp', 'end_date_timestamp'),
                $this->derivedField('field_67d0f024d5775', 'Start Date Display', 'start_date_display'),
                $this->derivedField('field_67d0f032d5776', 'End Date Display', 'end_date_display'),
            ],
            'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'event']]],
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'active' => true,
            'show_in_rest' => 0,
        ]);

        acf_add_local_field_group([
            'key' => 'group_jpn_event_host_link',
            'title' => 'Event - Host Link (JPN)',
            'fields' => [[
                'key' => 'field_jpn_event_host',
                'label' => 'Event Host',
                'name' => 'event_host',
                'type' => 'user',
                'instructions' => 'WordPress user responsible for this event.',
                'role' => ['host'],
                'allow_null' => 1,
                'multiple' => 0,
                'return_format' => 'id',
            ]],
            'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'event']]],
            'menu_order' => 5,
            'position' => 'side',
            'style' => 'default',
            'label_placement' => 'top',
            'active' => true,
            'show_in_rest' => 0,
        ]);

        acf_add_local_field_group([
            'key' => 'group_jpn_host_meta',
            'title' => 'Host - JPN Meta',
            'fields' => [
                ['key' => 'field_jpn_host_code', 'label' => 'JPN Host Code', 'name' => 'jpn_host_code', 'type' => 'text', 'instructions' => 'Managed by Code.Hexa.', 'readonly' => 1, 'wrapper' => ['width' => '50']],
                ['key' => 'field_jpn_auto_approve', 'label' => 'Auto Approve Submissions', 'name' => 'jpn_auto_approve', 'type' => 'true_false', 'default_value' => 1, 'ui' => 1, 'wrapper' => ['width' => '50']],
                ['key' => 'field_jpn_host_website', 'label' => 'Website', 'name' => 'website', 'type' => 'url'],
            ],
            'location' => [[['param' => 'user_role', 'operator' => '==', 'value' => 'host']]],
            'menu_order' => 5,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'active' => true,
            'show_in_rest' => 0,
        ]);
    }

    private function dateField(string $key, string $label, string $name): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'name' => $name,
            'type' => 'date_time_picker',
            'display_format' => 'F j, Y g:i a',
            'return_format' => 'F j, Y g:i a',
            'first_day' => 0,
        ];
    }

    private function derivedField(string $key, string $label, string $name): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'name' => $name,
            'type' => 'text',
            'readonly' => 1,
            'instructions' => 'Auto-generated from the event date fields.',
            'wrapper' => ['class' => 'jpn-derived-event-field'],
        ];
    }
}
