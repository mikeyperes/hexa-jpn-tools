<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Rest;

use Hexa\JpnTools\Events\EventDates;
use Hexa\JpnTools\Events\EventRelations;
use Throwable;
use WP_Error;

final class EventWriter
{
    private const ACF_KEYS = [
        'start_date' => 'field_6768f693e478f',
        'end_date' => 'field_6768f6eada5b2',
        'link' => 'field_676deb7cee578',
        'additional_photos' => 'field_676decfb3645d',
        'featured_event' => 'field_67bf95c75e6a6',
        'kids_event' => 'field_hexa_jpn_kids_event',
        'area' => 'field_hexa_jpn_event_area',
        'location' => 'field_hexa_jpn_event_location',
        'location_address' => 'field_hexa_jpn_event_location_address',
        'additional_information' => 'field_67c0cff435406',
        'start_date_timestamp' => 'field_676cd86b3f599',
        'end_date_timestamp' => 'field_676cd8753f59a',
        'start_date_display' => 'field_67d0f024d5775',
        'end_date_display' => 'field_67d0f032d5776',
        'event_host' => 'field_jpn_event_host',
    ];

    public function __construct(
        private EventBindings $bindings,
        private EventDates $dates,
        private EventRelations $relations
    ) {
    }

    public function write(int $postId, array $payload, bool $created): array
    {
        $effects = array_fill_keys(['content', 'event_fields', 'taxonomy', 'host', 'media', 'relations', 'status'], 'preserved');
        $errors = [];
        $publicBefore = get_post_status($postId) === 'publish';
        $fields = is_array($payload['fields'] ?? null) ? $payload['fields'] : [];

        $this->applyContent($postId, $fields, $effects, $errors);
        $this->applyEventFields($postId, $fields, $payload, $effects, $errors);
        $this->applyArea($postId, $fields, $effects, $errors);
        $this->applyHost($postId, $fields, $effects, $errors);
        $this->applyMedia($postId, $fields, $payload, $effects, $errors);
        $this->applyRelations($postId, $fields, $effects, $errors);

        $requestedStatus = $payload['status'] ?? null;
        if ($errors === [] && in_array($requestedStatus, ['draft', 'publish'], true)) {
            $postType = get_post_type_object('event');
            $publishCapability = $postType && isset($postType->cap->publish_posts)
                ? (string) $postType->cap->publish_posts
                : 'publish_posts';
            if ($requestedStatus === 'publish' && !current_user_can($publishCapability)) {
                $errors[] = ['code' => 'cannot_publish_event', 'message' => 'The authenticated user cannot publish this event.'];
                $effects['status'] = 'failed';
            } else {
                $result = wp_update_post(['ID' => $postId, 'post_status' => $requestedStatus], true);
                if ($result instanceof WP_Error) {
                    $errors[] = ['code' => 'status_update_failed', 'message' => $result->get_error_message()];
                    $effects['status'] = 'failed';
                } else {
                    $effects['status'] = get_post_status($postId) === $requestedStatus ? 'applied' : 'failed';
                    if ($effects['status'] === 'failed') {
                        $errors[] = ['code' => 'status_verification_failed', 'message' => 'The requested event status was not observed.'];
                    }
                }
            }
        }

        $applied = $created || in_array('applied', $effects, true);
        $result = $errors === [] ? ($created ? 'created' : 'updated') : ($applied ? 'partial' : 'failed');
        $publicAfter = get_post_status($postId) === 'publish';
        $publicChanged = $publicBefore !== $publicAfter
            || ($publicBefore && in_array('applied', $effects, true));

        return [
            'success' => $errors === [],
            'result' => $result,
            'post_id' => $postId,
            'effects' => $effects,
            'error' => $errors === [] ? null : ['code' => 'event_write_incomplete', 'message' => 'One or more event effects failed.', 'details' => $errors],
            'public_state_changed' => $publicChanged,
        ];
    }

    private function applyContent(int $postId, array $fields, array &$effects, array &$errors): void
    {
        $postUpdate = ['ID' => $postId];
        if (array_key_exists('title', $fields)) {
            $postUpdate['post_title'] = sanitize_text_field((string) $fields['title']);
        }
        if (array_key_exists('description', $fields) || array_key_exists('content', $fields)) {
            $content = (string) ($fields['description'] ?? $fields['content'] ?? '');
            $postUpdate['post_content'] = EventRelations::stripLegacyBlock(wp_kses_post($content));
        }
        if (count($postUpdate) === 1) {
            return;
        }
        $result = wp_update_post($postUpdate, true);
        if ($result instanceof WP_Error) {
            $effects['content'] = 'failed';
            $errors[] = ['code' => 'content_update_failed', 'message' => $result->get_error_message()];
            return;
        }
        $effects['content'] = 'applied';
    }

    private function applyEventFields(int $postId, array $fields, array $payload, array &$effects, array &$errors): void
    {
        $changed = false;
        foreach (['start_date' => ['start_at', 'start_date'], 'end_date' => ['end_at', 'end_date']] as $stored => $aliases) {
            $input = null;
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $fields)) {
                    $input = $alias;
                    break;
                }
            }
            if ($input === null) {
                continue;
            }
            try {
                $value = trim((string) $fields[$input]);
                if ($value === '') {
                    $this->setMeta($postId, $stored, '');
                    $this->setMeta($postId, $stored . '_timestamp', '');
                    $this->setMeta($postId, $stored . '_display', '');
                } else {
                    $normalized = $this->dates->normalize($value);
                    $this->setMeta($postId, $stored, $normalized['storage']);
                    $this->setMeta($postId, $stored . '_timestamp', (string) $normalized['timestamp']);
                    $this->setMeta($postId, $stored . '_display', $normalized['display']);
                }
                $changed = true;
            } catch (Throwable $exception) {
                $errors[] = ['code' => 'invalid_' . $stored, 'message' => 'The ' . str_replace('_', ' ', $stored) . ' is invalid.'];
            }
        }

        $simple = [
            'external_url' => 'link', 'link' => 'link', 'location_name' => 'location',
            'location_address' => 'location_address', 'address' => 'location_address',
            'additional_information' => 'additional_information',
        ];
        foreach ($simple as $input => $stored) {
            if (array_key_exists($input, $fields)) {
                $value = $stored === 'additional_information' ? wp_kses_post((string) $fields[$input]) : sanitize_text_field((string) $fields[$input]);
                $this->setMeta($postId, $stored, $value);
                if ($stored === 'location_address') {
                    $this->setMeta($postId, 'address', $value);
                    $this->setMeta($postId, 'jpn_event_where_label', $value);
                }
                $changed = true;
            }
        }

        foreach (['featured_event', 'kids_event'] as $boolean) {
            if (array_key_exists($boolean, $fields)) {
                $this->setMeta($postId, $boolean, rest_sanitize_boolean($fields[$boolean]) ? '1' : '0');
                $changed = true;
            }
        }
        if (array_key_exists('host_name', $fields)) {
            $this->setMeta($postId, 'jpn_event_venue_label', sanitize_text_field((string) $fields['host_name']));
            $changed = true;
        } elseif (array_key_exists('location_name', $fields)) {
            $this->setMeta($postId, 'jpn_event_venue_label', sanitize_text_field((string) $fields['location_name']));
            $changed = true;
        }

        $submissionId = (int) ($payload['code_submission_id'] ?? 0);
        if ($submissionId > 0) {
            $this->setMeta($postId, '_hexa_jpn_code_submission_id', (string) $submissionId);
            $this->setMeta($postId, 'jpn_code_submission_id', (string) $submissionId);
            $this->setMeta($postId, 'code_submission_id', (string) $submissionId);
            $changed = true;
        }

        if ($changed) {
            $effects['event_fields'] = 'applied';
        }
    }

    private function applyArea(int $postId, array $fields, array &$effects, array &$errors): void
    {
        if (!array_key_exists('area', $fields)) {
            return;
        }
        $area = $fields['area'];
        if ($area === null || $area === '') {
            wp_set_object_terms($postId, [], 'area');
            $this->setMeta($postId, 'area', '');
            $this->setMeta($postId, 'jpn_event_area_label', '');
            $effects['taxonomy'] = 'applied';
            return;
        }

        $term = null;
        if (is_numeric($area)) {
            $term = get_term((int) $area, 'area');
        } else {
            $needle = trim((string) $area);
            $term = get_term_by('slug', sanitize_title($needle), 'area') ?: get_term_by('name', $needle, 'area');
        }
        if (!$term || $term instanceof WP_Error || $term->taxonomy !== 'area') {
            $effects['taxonomy'] = 'failed';
            $errors[] = ['code' => 'invalid_area', 'message' => 'The selected area does not exist.'];
            return;
        }

        $set = wp_set_object_terms($postId, [(int) $term->term_id], 'area', false);
        if ($set instanceof WP_Error) {
            $effects['taxonomy'] = 'failed';
            $errors[] = ['code' => 'area_update_failed', 'message' => $set->get_error_message()];
            return;
        }
        $this->setMeta($postId, 'area', (string) $term->term_id);
        $this->setMeta($postId, 'jpn_event_area_label', (string) $term->name);
        $effects['taxonomy'] = 'applied';
    }

    private function applyHost(int $postId, array $fields, array &$effects, array &$errors): void
    {
        if (!array_key_exists('host_user_id', $fields) && !array_key_exists('host_wp_user_id', $fields)) {
            return;
        }
        $userId = (int) ($fields['host_user_id'] ?? $fields['host_wp_user_id'] ?? 0);
        $user = $userId > 0 ? get_userdata($userId) : false;
        if (!$user || !in_array('host', (array) $user->roles, true)) {
            $effects['host'] = 'failed';
            $errors[] = ['code' => 'invalid_host', 'message' => 'The selected host user does not exist or is not a host.'];
            return;
        }
        $result = wp_update_post(['ID' => $postId, 'post_author' => $userId], true);
        if ($result instanceof WP_Error) {
            $effects['host'] = 'failed';
            $errors[] = ['code' => 'host_update_failed', 'message' => $result->get_error_message()];
            return;
        }
        $this->setMeta($postId, 'event_host', (string) $userId);
        $effects['host'] = 'applied';
    }

    private function applyMedia(int $postId, array $fields, array $payload, array &$effects, array &$errors): void
    {
        if (array_key_exists('attachment_id', $payload)) {
            $attachmentId = (int) $payload['attachment_id'];
            if ($attachmentId <= 0
                || get_post_type($attachmentId) !== 'attachment'
                || !current_user_can('read_post', $attachmentId)) {
                $effects['media'] = 'failed';
                $errors[] = ['code' => 'invalid_attachment', 'message' => 'The featured attachment does not exist.'];
                return;
            }
            set_post_thumbnail($postId, $attachmentId);
            if ((int) get_post_thumbnail_id($postId) !== $attachmentId) {
                $effects['media'] = 'failed';
                $errors[] = ['code' => 'featured_media_update_failed', 'message' => 'The featured attachment was not applied.'];
                return;
            }
            $effects['media'] = 'applied';
        }

        if (array_key_exists('additional_media_ids', $fields)) {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $fields['additional_media_ids']))));
            foreach ($ids as $id) {
                if (get_post_type($id) !== 'attachment' || !current_user_can('read_post', $id)) {
                    $effects['media'] = 'failed';
                    $errors[] = ['code' => 'invalid_additional_attachment', 'message' => 'An additional attachment does not exist.'];
                    return;
                }
            }
            $this->setMeta($postId, 'additional_photos', $ids);
            $effects['media'] = 'applied';
        }
    }

    private function applyRelations(int $postId, array $fields, array &$effects, array &$errors): void
    {
        $hasIds = array_key_exists('related_post_ids', $fields) || array_key_exists('related_events', $fields);
        $hasRefs = array_key_exists('related_external_refs', $fields);
        if (!$hasIds && !$hasRefs) {
            return;
        }

        $ids = EventRelations::parseRelatedIds($fields['related_post_ids'] ?? $fields['related_events'] ?? []);
        foreach ((array) ($fields['related_external_refs'] ?? []) as $reference) {
            $binding = $this->bindings->find(trim((string) $reference));
            if (!$binding || (int) ($binding['post_id'] ?? 0) <= 0) {
                $effects['relations'] = 'failed';
                $errors[] = ['code' => 'related_event_not_found', 'message' => 'A related external event reference was not found.'];
                return;
            }
            $ids[] = (int) $binding['post_id'];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id !== $postId)));
        $normalized = [];
        foreach ($ids as $id) {
            $related = get_post($id);
            if (!$related || $related->post_type !== 'event' || !current_user_can('read_post', $id)) {
                $effects['relations'] = 'failed';
                $errors[] = ['code' => 'invalid_related_event', 'message' => 'A related event post does not exist.'];
                return;
            }
            $normalized[] = ['post_id' => $id, 'title' => get_the_title($id), 'permalink' => get_permalink($id)];
        }
        $encoded = wp_json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->setMeta($postId, 'related_events', $encoded ?: '[]');
        $this->setMeta($postId, 'related_event_links', $encoded ?: '[]');
        $effects['relations'] = 'applied';
    }

    private function setMeta(int $postId, string $key, mixed $value): void
    {
        update_post_meta($postId, $key, $value);
        if (isset(self::ACF_KEYS[$key])) {
            update_post_meta($postId, '_' . $key, self::ACF_KEYS[$key]);
        }
    }
}
