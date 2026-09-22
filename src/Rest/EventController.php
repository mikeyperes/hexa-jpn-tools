<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Rest;

use Hexa\JpnTools\Events\EventDates;
use Hexa\JpnTools\Events\EventRelations;
use Hexa\JpnTools\Security\IntegrationAccess;
use RuntimeException;
use Throwable;
use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

final class EventController
{
    public const NAMESPACE = 'hexa-jpn/v1';
    private const MAX_PAGE_SIZE = 100;

    private EventWriter $writer;

    public function __construct(
        private EventBindings $bindings,
        private EventDates $dates,
        EventRelations $relations
    ) {
        $this->writer = new EventWriter($bindings, $dates, $relations);
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/events', [
            'methods' => 'GET',
            'callback' => [$this, 'listEvents'],
            'permission_callback' => [$this, 'canRead'],
        ]);
        register_rest_route(self::NAMESPACE, '/events/(?P<external_ref>[^/]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getEvent'],
                'permission_callback' => [$this, 'canRead'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'putEvent'],
                'permission_callback' => [$this, 'canWrite'],
            ],
        ]);
    }

    public function canRead(): bool
    {
        return IntegrationAccess::canManage();
    }

    public function canWrite(): bool
    {
        return IntegrationAccess::canManage();
    }

    public function listEvents(WP_REST_Request $request): WP_REST_Response
    {
        $page = max(1, (int) ($request->get_param('page') ?: 1));
        $perPage = max(1, min(self::MAX_PAGE_SIZE, (int) ($request->get_param('per_page') ?: 50)));
        $order = strtoupper((string) $request->get_param('order')) === 'DESC' ? 'DESC' : 'ASC';
        $allowed = ['publish', 'draft', 'pending', 'future', 'private'];
        $requestedStatuses = $request->get_param('statuses');
        if (is_string($requestedStatuses)) {
            $requestedStatuses = explode(',', $requestedStatuses);
        }
        $statuses = array_values(array_intersect($allowed, array_map('sanitize_key', (array) ($requestedStatuses ?: ['publish']))));
        if ($statuses === []) {
            $statuses = ['publish'];
        }

        $include = $this->includeIds($request->get_param('include'));
        if ($include instanceof WP_REST_Response) {
            return $include;
        }

        $metaQuery = [];
        $from = (int) $request->get_param('from');
        $to = (int) $request->get_param('to');
        if (rest_sanitize_boolean($request->get_param('only_upcoming'))) {
            $from = max($from, time());
        }
        if ($from > 0) {
            $metaQuery[] = ['key' => 'start_date_timestamp', 'value' => $from, 'compare' => '>=', 'type' => 'NUMERIC'];
        }
        if ($to > 0) {
            $metaQuery[] = ['key' => 'start_date_timestamp', 'value' => $to, 'compare' => '<', 'type' => 'NUMERIC'];
        }

        $hasDateFilter = $from > 0 || $to > 0;
        $queryArgs = [
            'post_type' => 'event',
            'post_status' => $statuses,
            'posts_per_page' => $perPage + 1,
            'offset' => ($page - 1) * $perPage,
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
            'meta_query' => $metaQuery,
        ];
        if ($include !== []) {
            $queryArgs['post__in'] = $include;
            if (!$hasDateFilter) {
                $queryArgs['orderby'] = 'post__in';
            }
        }
        if ($include === [] || $hasDateFilter) {
            $queryArgs['meta_key'] = 'start_date_timestamp';
            $queryArgs['orderby'] = 'meta_value_num';
            $queryArgs['order'] = $order;
        }
        $postType = get_post_type_object('event');
        $editOthersCapability = $postType && isset($postType->cap->edit_others_posts)
            ? (string) $postType->cap->edit_others_posts
            : 'edit_others_posts';
        if (!current_user_can($editOthersCapability)) {
            $queryArgs['author'] = get_current_user_id();
        }

        $query = new WP_Query($queryArgs);
        $posts = array_values(array_filter(
            $query->posts,
            static fn ($post): bool => $post instanceof \WP_Post && current_user_can('edit_post', (int) $post->ID)
        ));
        $hasMore = count($posts) > $perPage;
        if ($hasMore) {
            array_pop($posts);
        }

        return new WP_REST_Response([
            'schema_version' => 1,
            'events' => array_map(fn ($post): array => $this->snapshot((int) $post->ID), $posts),
            'page' => $page,
            'per_page' => $perPage,
            'has_more' => $hasMore,
        ], 200);
    }

    public function getEvent(WP_REST_Request $request): WP_REST_Response
    {
        $externalRef = $this->externalRef($request);
        if ($externalRef instanceof WP_REST_Response) {
            return $externalRef;
        }

        if (preg_match('/^wordpress:post:(\d+)$/', $externalRef, $match)) {
            $postId = (int) $match[1];
            $post = get_post($postId);
            if (!$post || $post->post_type !== 'event') {
                return $this->notFound($externalRef);
            }
            if (!current_user_can('edit_post', $postId)) {
                return $this->errorResponse('forbidden_event', 'The authenticated user cannot read this event.', 403, $externalRef);
            }
            $binding = $this->bindings->schemaReady() ? $this->bindings->findByPostId($postId) : null;
            return $this->observedResponse($binding ? (string) $binding['external_ref'] : $externalRef, $postId, $binding);
        }

        if (!$this->bindings->schemaReady()) {
            return $this->errorResponse('binding_schema_unavailable', 'The JPN event binding schema is not installed.', 503, $externalRef);
        }
        $binding = $this->bindings->find($externalRef);
        if (!$binding || (int) ($binding['post_id'] ?? 0) <= 0) {
            return $this->notFound($externalRef, $binding);
        }
        $postId = (int) $binding['post_id'];
        $post = get_post($postId);
        if (!$post || $post->post_type !== 'event') {
            return $this->notFound($externalRef, $binding);
        }
        if (!current_user_can('edit_post', $postId)) {
            return $this->errorResponse('forbidden_event', 'The authenticated user cannot read this event.', 403, $externalRef);
        }
        return $this->observedResponse($externalRef, $postId, $binding);
    }

    public function putEvent(WP_REST_Request $request): WP_REST_Response
    {
        $externalRef = $this->externalRef($request);
        if ($externalRef instanceof WP_REST_Response) {
            return $externalRef;
        }
        if (!$this->bindings->schemaReady()) {
            return $this->errorResponse('binding_schema_unavailable', 'The JPN event binding schema is not installed.', 503, $externalRef);
        }

        $payload = $request->get_json_params();
        if (!is_array($payload) || $payload === []) {
            $payload = $request->get_body_params();
        }
        $validation = $this->validatePayload($payload, $externalRef);
        if ($validation instanceof WP_REST_Response) {
            return $validation;
        }
        $operationId = trim((string) $payload['operation_id']);
        $digest = self::requestDigest($payload);
        if (!empty($payload['request_digest']) && !hash_equals($digest, (string) $payload['request_digest'])) {
            return $this->errorResponse('request_digest_mismatch', 'The supplied request digest does not match the payload.', 409, $externalRef, $operationId, $digest);
        }

        $operationMode = null;
        $operationPostId = null;
        try {
            return $this->bindings->withLock($externalRef, function () use ($externalRef, $payload, $operationId, $digest, &$operationMode, &$operationPostId): WP_REST_Response {
                $binding = $this->bindings->find($externalRef);
                $decision = EventBindings::transition(
                    $binding,
                    $operationId,
                    $digest,
                    isset($payload['expected_operation_id']) ? (string) $payload['expected_operation_id'] : null
                );
                if ($decision['action'] === 'conflict') {
                    return $this->errorResponse((string) $decision['code'], (string) $decision['message'], 409, $externalRef, $operationId, $digest);
                }
                if ($decision['action'] === 'replay') {
                    $original = is_array($binding['outcome_data'] ?? null) ? $binding['outcome_data'] : null;
                    if (!$original) {
                        return $this->errorResponse('operation_in_progress', 'The operation has no final receipt. Read status before deciding whether to continue.', 409, $externalRef, $operationId, $digest);
                    }
                    $replay = $original;
                    $replay['original_outcome'] = (string) ($original['result'] ?? 'failed');
                    $replay['result'] = 'replayed';
                    return new WP_REST_Response($replay, 200);
                }

                $existingPostId = (int) ($payload['existing_post_id'] ?? 0);
                $postId = $binding ? (int) ($binding['post_id'] ?? 0) : $existingPostId;
                $mode = $postId > 0 ? 'update' : 'create';
                $operationMode = $mode;
                $operationPostId = $postId > 0 ? $postId : null;
                if ($binding && $existingPostId > 0 && $postId !== $existingPostId) {
                    return $this->errorResponse('post_binding_conflict', 'The supplied post does not match the existing external reference.', 409, $externalRef, $operationId, $digest, null, false, $mode);
                }
                if ($postId > 0) {
                    $post = get_post($postId);
                    if (!$post || $post->post_type !== 'event') {
                        return $this->errorResponse('invalid_existing_post', 'The selected existing post is not an event.', 422, $externalRef, $operationId, $digest, $postId, false, $mode);
                    }
                    if (!current_user_can('edit_post', $postId)) {
                        return $this->errorResponse('forbidden_event', 'The authenticated user cannot edit this event.', 403, $externalRef, $operationId, $digest, $postId, false, $mode);
                    }
                    $other = $this->bindings->findByPostId($postId);
                    if ($other && !hash_equals((string) $other['external_ref'], $externalRef)) {
                        return $this->errorResponse('post_binding_conflict', 'The selected event is already bound to another external reference.', 409, $externalRef, $operationId, $digest, $postId, false, $mode);
                    }
                }

                if ($binding === null) {
                    if (!$this->bindings->reserve($externalRef, $postId > 0 ? $postId : null, $operationId, $digest)) {
                        return $this->errorResponse('binding_conflict', 'The external reference could not be reserved.', 409, $externalRef, $operationId, $digest, $postId ?: null, false, $mode);
                    }
                } elseif (!$this->bindings->beginOperation($externalRef, $operationId, $digest)) {
                    return $this->errorResponse('receipt_update_failed', 'The operation receipt could not be started.', 500, $externalRef, $operationId, $digest, $postId ?: null, false, $mode);
                }

                $created = false;
                if ($postId <= 0) {
                    $fields = (array) ($payload['fields'] ?? []);
                    $title = trim((string) ($fields['title'] ?? ''));
                    $start = trim((string) ($fields['start_at'] ?? $fields['start_date'] ?? ''));
                    if ($title === '' || $start === '') {
                        $outcome = $this->errorBody('new_event_fields_required', 'New events require title and start_at.', $externalRef, $operationId, $digest, null, false, $mode);
                        $this->bindings->saveOutcome($externalRef, $outcome);
                        return new WP_REST_Response($outcome, 422);
                    }
                    $inserted = wp_insert_post([
                        'post_type' => 'event',
                        'post_status' => 'draft',
                        'post_title' => sanitize_text_field($title),
                        'post_content' => '',
                        'post_author' => get_current_user_id(),
                    ], true);
                    if ($inserted instanceof WP_Error) {
                        $outcome = $this->errorBody('event_create_failed', $inserted->get_error_message(), $externalRef, $operationId, $digest, null, false, $mode);
                        $this->bindings->saveOutcome($externalRef, $outcome);
                        return new WP_REST_Response($outcome, 500);
                    }
                    $postId = (int) $inserted;
                    $operationPostId = $postId;
                    $created = true;
                    if (!$this->bindings->attachPost($externalRef, $postId)) {
                        $outcome = $this->errorBody('binding_attach_failed', 'The draft was created but its binding could not be stored.', $externalRef, $operationId, $digest, $postId, false, $mode);
                        $this->bindings->saveOutcome($externalRef, $outcome);
                        return new WP_REST_Response($outcome, 500);
                    }
                }

                $written = $this->writer->write($postId, $payload, $created);
                $outcome = array_merge(
                    $this->base($externalRef, $operationId, $digest, $postId, $mode),
                    $written,
                    ['event' => $this->snapshot($postId)]
                );
                if (!$this->bindings->saveOutcome($externalRef, $outcome)) {
                    $outcome['success'] = false;
                    $outcome['result'] = 'partial';
                    $outcome['error'] = ['code' => 'receipt_persist_failed', 'message' => 'The event changed but the final receipt could not be stored.'];
                }
                $status = $outcome['success'] ? ($created ? 201 : 200) : 500;
                return new WP_REST_Response($outcome, $status);
            });
        } catch (Throwable $exception) {
            error_log('Hexa JPN Tools event operation failed: ' . $exception->getMessage());
            if ($operationMode !== null) {
                $outcome = $this->errorBody(
                    'event_operation_failed',
                    'The event operation failed before it could complete.',
                    $externalRef,
                    $operationId,
                    $digest,
                    $operationPostId,
                    false,
                    $operationMode
                );
                try {
                    $binding = $this->bindings->find($externalRef);
                    if ($binding
                        && hash_equals((string) ($binding['operation_id'] ?? ''), $operationId)
                        && hash_equals((string) ($binding['request_digest'] ?? ''), $digest)) {
                        $this->bindings->saveOutcome($externalRef, $outcome);
                    }
                } catch (Throwable) {
                    // Preserve the original operation error when receipt recovery also fails.
                }
                return new WP_REST_Response($outcome, 503);
            }
            return $this->errorResponse('event_operation_failed', 'The event operation could not start.', 503, $externalRef, $operationId, $digest);
        }
    }

    public static function requestDigest(array $payload): string
    {
        unset($payload['operation_id'], $payload['expected_operation_id'], $payload['request_digest']);
        $normalized = self::sortRecursive($payload);
        return hash('sha256', (string) wp_json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private static function sortRecursive(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map([self::class, 'sortRecursive'], $value);
        }
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = self::sortRecursive($item);
        }
        return $value;
    }

    private function validatePayload(mixed $payload, string $externalRef): ?WP_REST_Response
    {
        if (!is_array($payload)) {
            return $this->errorResponse('invalid_payload', 'The request body must be an object.', 400, $externalRef);
        }
        $operationId = trim((string) ($payload['operation_id'] ?? ''));
        if ($operationId === '' || strlen($operationId) > 191) {
            return $this->errorResponse('invalid_operation_id', 'operation_id is required and must be no more than 191 bytes.', 422, $externalRef);
        }
        if (isset($payload['status']) && $payload['status'] !== null && !in_array($payload['status'], ['draft', 'publish'], true)) {
            return $this->errorResponse('invalid_status', 'status must be draft, publish, or null.', 422, $externalRef, $operationId);
        }
        if (isset($payload['fields']) && !is_array($payload['fields'])) {
            return $this->errorResponse('invalid_fields', 'fields must be an object.', 422, $externalRef, $operationId);
        }
        return null;
    }

    private function observedResponse(string $externalRef, int $postId, ?array $binding): WP_REST_Response
    {
        $outcome = is_array($binding['outcome_data'] ?? null) ? $binding['outcome_data'] : [];
        $body = array_merge(
            $this->base(
                $externalRef,
                $binding ? (string) ($binding['operation_id'] ?? '') : null,
                $binding ? (string) ($binding['request_digest'] ?? '') : null,
                $postId,
                isset($outcome['mode']) ? (string) $outcome['mode'] : null
            ),
            [
                'success' => true,
                'result' => 'found',
                'original_outcome' => $outcome['result'] ?? ($binding['result'] ?? null),
                'effects' => $outcome['effects'] ?? [],
                'error' => $outcome['error'] ?? null,
                'public_state_changed' => (bool) ($outcome['public_state_changed'] ?? false),
                'event' => $this->snapshot($postId),
            ]
        );
        return new WP_REST_Response($body, 200);
    }

    private function snapshot(int $postId): array
    {
        $post = get_post($postId);
        $bindingSchemaReady = $this->bindings->schemaReady();
        $currentBinding = $bindingSchemaReady ? $this->bindings->findByPostId($postId) : null;
        $start = (int) get_post_meta($postId, 'start_date_timestamp', true);
        $end = (int) get_post_meta($postId, 'end_date_timestamp', true);
        $startPrecision = (string) get_post_meta($postId, 'start_date_precision', true);
        $endPrecision = (string) get_post_meta($postId, 'end_date_precision', true);
        $areaId = $this->normalizeAreaId(get_post_meta($postId, 'area', true));
        if ($areaId <= 0) {
            $terms = wp_get_object_terms($postId, 'area', ['fields' => 'ids']);
            $areaId = !is_wp_error($terms) && $terms !== [] ? (int) $terms[0] : 0;
        }
        $area = $areaId > 0 ? get_term($areaId, 'area') : null;
        $relatedIds = EventRelations::parseRelatedIds(get_post_meta($postId, 'related_events', true));
        $additional = get_post_meta($postId, 'additional_photos', true);
        $additionalIds = [];
        foreach ((array) $additional as $item) {
            if (is_array($item)) {
                $item = $item['ID'] ?? $item['id'] ?? null;
            } elseif (is_object($item)) {
                $item = $item->ID ?? null;
            }
            $id = is_numeric($item) ? (int) $item : 0;
            if ($id > 0 && get_post_type($id) === 'attachment' && current_user_can('read_post', $id)) {
                $additionalIds[] = $id;
            }
        }
        $additionalIds = array_values(array_unique($additionalIds));
        $hostId = $post ? (int) $post->post_author : 0;
        $host = $hostId > 0 ? get_userdata($hostId) : false;
        $featuredId = (int) get_post_thumbnail_id($postId);
        if ($featuredId > 0 && !current_user_can('read_post', $featuredId)) {
            $featuredId = 0;
        }
        $relatedIds = array_values(array_filter(
            $relatedIds,
            static fn (int $id): bool => get_post_type($id) === 'event' && current_user_can('read_post', $id)
        ));
        $relatedRefs = [];
        if ($bindingSchemaReady) {
            foreach ($relatedIds as $relatedId) {
                $binding = $this->bindings->findByPostId($relatedId);
                if ($binding) {
                    $relatedRefs[] = (string) $binding['external_ref'];
                }
            }
        }
        $registrationUrl = trim((string) get_post_meta($postId, 'link', true));
        $createdAt = $post ? get_post_time(DATE_ATOM, true, $post) : false;

        return [
            'post_id' => $postId,
            'external_ref' => $currentBinding ? (string) $currentBinding['external_ref'] : null,
            'operation_id' => $currentBinding ? (string) $currentBinding['operation_id'] : null,
            'post_status' => $post ? (string) $post->post_status : null,
            'post_created_at' => is_string($createdAt) && $createdAt !== '' ? $createdAt : null,
            'title' => $post ? (string) $post->post_title : '',
            'slug' => $post ? (string) $post->post_name : '',
            'content' => $post ? EventRelations::stripLegacyBlock((string) $post->post_content) : '',
            'permalink' => $post ? get_permalink($postId) : null,
            'edit_url' => $post ? get_edit_post_link($postId, 'raw') : null,
            'start_at' => $start > 0 ? $this->dates->formatTimestamp($start, $startPrecision === 'date' ? 'Y-m-d' : DATE_ATOM) : null,
            'end_at' => $end > 0 ? $this->dates->formatTimestamp($end, $endPrecision === 'date' ? 'Y-m-d' : DATE_ATOM) : null,
            'start_precision' => in_array($startPrecision, ['date', 'date_time'], true) ? $startPrecision : null,
            'end_precision' => in_array($endPrecision, ['date', 'date_time'], true) ? $endPrecision : null,
            'start_timestamp' => $start ?: null,
            'end_timestamp' => $end ?: null,
            'link' => $registrationUrl,
            'registration_url' => $registrationUrl,
            'location_name' => (string) get_post_meta($postId, 'location', true),
            'location_address' => (string) (get_post_meta($postId, 'location_address', true) ?: get_post_meta($postId, 'address', true)),
            'additional_information' => (string) get_post_meta($postId, 'additional_information', true),
            'featured_event' => get_post_meta($postId, 'featured_event', true) === '1',
            'kids_event' => get_post_meta($postId, 'kids_event', true) === '1',
            'host_user_id' => $hostId ?: null,
            'host_display_name' => $host ? (string) $host->display_name : null,
            'area_term_id' => $areaId ?: null,
            'area_name' => $area && !is_wp_error($area) ? (string) $area->name : null,
            'featured_media_id' => $featuredId ?: null,
            'featured_media_url' => $featuredId > 0 ? wp_get_attachment_url($featuredId) : null,
            'media_sizes' => $featuredId > 0 ? $this->mediaSizes($featuredId) : [],
            'additional_media_ids' => $additionalIds,
            'related_post_ids' => $relatedIds,
            'related_external_refs' => $relatedRefs,
            'code_submission_id' => (int) (get_post_meta($postId, '_hexa_jpn_code_submission_id', true) ?: get_post_meta($postId, 'jpn_code_submission_id', true)) ?: null,
        ];
    }

    private function externalRef(WP_REST_Request $request): string|WP_REST_Response
    {
        $externalRef = trim(rawurldecode((string) $request->get_param('external_ref')));
        if ($externalRef === '' || strlen($externalRef) > 512) {
            return $this->errorResponse('invalid_external_ref', 'The external reference is required and must be no more than 512 bytes.', 400, $externalRef);
        }
        return $externalRef;
    }

    private function base(string $externalRef, ?string $operationId, ?string $digest, ?int $postId, ?string $mode = null): array
    {
        $post = $postId ? get_post($postId) : null;
        return [
            'schema_version' => 1,
            'external_ref' => $externalRef,
            'operation_id' => $operationId ?: null,
            'request_digest' => $digest ?: null,
            'mode' => in_array($mode, ['create', 'update'], true) ? $mode : null,
            'result' => null,
            'original_outcome' => null,
            'success' => false,
            'post_id' => $postId ?: null,
            'post_status' => $post ? (string) $post->post_status : null,
            'permalink' => $post ? get_permalink($postId) : null,
            'edit_url' => $post ? get_edit_post_link($postId, 'raw') : null,
            'effects' => [],
            'error' => null,
            'public_state_changed' => false,
        ];
    }

    private function notFound(string $externalRef, ?array $binding = null): WP_REST_Response
    {
        $body = $this->errorBody(
            'event_not_found',
            'No event exists for the external reference.',
            $externalRef,
            $binding ? (string) ($binding['operation_id'] ?? '') : null,
            $binding ? (string) ($binding['request_digest'] ?? '') : null,
            isset($binding['post_id']) ? (int) $binding['post_id'] : null,
            false,
            isset($binding['outcome_data']['mode']) ? (string) $binding['outcome_data']['mode'] : null
        );
        $body['result'] = 'not_found';
        return new WP_REST_Response($body, 404);
    }

    private function errorResponse(string $code, string $message, int $status, string $externalRef, ?string $operationId = null, ?string $digest = null, ?int $postId = null, bool $publicChanged = false, ?string $mode = null): WP_REST_Response
    {
        return new WP_REST_Response($this->errorBody($code, $message, $externalRef, $operationId, $digest, $postId, $publicChanged, $mode), $status);
    }

    private function errorBody(string $code, string $message, string $externalRef, ?string $operationId = null, ?string $digest = null, ?int $postId = null, bool $publicChanged = false, ?string $mode = null): array
    {
        $body = $this->base($externalRef, $operationId, $digest, $postId, $mode);
        $body['result'] = 'failed';
        $body['error'] = ['code' => $code, 'message' => $message];
        $body['public_state_changed'] = $publicChanged;
        return $body;
    }

    private function includeIds(mixed $value): array|WP_REST_Response
    {
        if ($value === null || $value === '') {
            return [];
        }
        $items = is_string($value) ? explode(',', $value) : (array) $value;
        if (count($items) > self::MAX_PAGE_SIZE) {
            return new WP_REST_Response([
                'schema_version' => 1,
                'success' => false,
                'result' => 'failed',
                'error' => ['code' => 'include_limit_exceeded', 'message' => 'include accepts at most 100 event IDs.'],
            ], 422);
        }

        $ids = [];
        foreach ($items as $item) {
            if (is_array($item) || is_object($item) || !preg_match('/^\d+$/D', trim((string) $item))) {
                return new WP_REST_Response([
                    'schema_version' => 1,
                    'success' => false,
                    'result' => 'failed',
                    'error' => ['code' => 'invalid_include', 'message' => 'include must contain positive integer event IDs.'],
                ], 422);
            }
            $id = (int) $item;
            if ($id <= 0) {
                return new WP_REST_Response([
                    'schema_version' => 1,
                    'success' => false,
                    'result' => 'failed',
                    'error' => ['code' => 'invalid_include', 'message' => 'include must contain positive integer event IDs.'],
                ], 422);
            }
            $ids[] = $id;
        }
        $ids = array_values(array_unique($ids));
        if (count($ids) > self::MAX_PAGE_SIZE) {
            return new WP_REST_Response([
                'schema_version' => 1,
                'success' => false,
                'result' => 'failed',
                'error' => ['code' => 'include_limit_exceeded', 'message' => 'include accepts at most 100 event IDs.'],
            ], 422);
        }

        return $ids;
    }

    private function mediaSizes(int $attachmentId): array
    {
        $sizes = array_values(array_unique(array_merge(['thumbnail', 'medium', 'medium_large', 'large'], get_intermediate_image_sizes(), ['full'])));
        $output = [];
        foreach (array_slice($sizes, 0, 50) as $size) {
            $image = wp_get_attachment_image_src($attachmentId, $size);
            if (!is_array($image) || empty($image[0])) {
                continue;
            }
            $output[(string) $size] = [
                'url' => (string) $image[0],
                'width' => (int) ($image[1] ?? 0),
                'height' => (int) ($image[2] ?? 0),
            ];
        }
        return $output;
    }

    private function normalizeAreaId(mixed $value): int
    {
        if (is_array($value)) {
            $value = reset($value);
        }
        if (is_object($value) && isset($value->term_id)) {
            $value = $value->term_id;
        }
        return is_numeric($value) ? (int) $value : 0;
    }

}
