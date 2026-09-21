<?php

use Hexa\JpnTools\Content\ContentTypes;
use Hexa\JpnTools\Events\EventDates;
use Hexa\JpnTools\Events\EventQueries;
use Hexa\JpnTools\Events\EventRelations;
use Hexa\JpnTools\Rest\EventBindings;
use Hexa\JpnTools\Rest\EventController;
use Hexa\JpnTools\Rest\HostController;
use Hexa\JpnTools\Security\IntegrationAccess;

if (!defined('WP_CLI') || !WP_CLI) {
    throw new RuntimeException('Run this fixture with WP-CLI.');
}

$pluginRoot = dirname(__DIR__);
if (!class_exists(EventController::class)) {
    require $pluginRoot . '/hexa-jpn-tools.php';
}

(new ContentTypes())->registerTypes();
$dates = new EventDates();
$queries = new EventQueries($dates);
$relations = new EventRelations($queries);
$bindings = new EventBindings();
if (!$bindings->schemaReady()) {
    WP_CLI::error('The Hexa JPN binding schema is not installed. Run the schema migration after taking the rollback checkpoint.');
}

$controller = new EventController($bindings, $dates, $relations);
$controller->registerRoutes();
(new HostController())->registerRoutes();

$administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
$administratorId = isset($administrators[0]) ? (int) $administrators[0] : 0;
if ($administratorId <= 0) {
    WP_CLI::error('No administrator is available for the authenticated REST fixture.');
}
wp_set_current_user($administratorId);
IntegrationAccess::install();

$assertions = 0;
$createdPosts = [];
$createdRefs = [];
$createdUserId = 0;
$token = 'fixture-' . gmdate('YmdHis') . '-' . strtolower(wp_generate_password(6, false, false));

$expect = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Fixture assertion failed: ' . $message);
    }
};

$request = static function (string $method, string $route, ?array $body = null, array $query = []): WP_REST_Response {
    $rest = new WP_REST_Request($method, $route);
    foreach ($query as $key => $value) {
        $rest->set_param((string) $key, $value);
    }
    if ($body !== null) {
        $rest->set_header('content-type', 'application/json');
        $rest->set_body((string) wp_json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
    return rest_ensure_response(rest_do_request($rest));
};

$eventRoute = static fn (string $externalRef): string => '/' . EventController::NAMESPACE . '/events/' . rawurlencode($externalRef);

try {
    $manifest = $request('GET', '/' . EventController::NAMESPACE . '/manifest');
    $manifestData = (array) $manifest->get_data();
    $expect($manifest->get_status() === 200, 'authenticated manifest returns HTTP 200');
    $expect(($manifestData['plugin']['name'] ?? null) === 'Hexa JPN Tools', 'manifest identifies Hexa JPN Tools');
    $expect(($manifestData['plugin']['version'] ?? null) === '1.0.1', 'manifest exposes plugin version 1.0.1');
    $expect(($manifestData['plugin']['slug'] ?? null) === 'hexa-jpn-tools', 'manifest exposes the canonical plugin slug');
    $expect(($manifestData['timezone'] ?? null) === EventDates::TIMEZONE, 'manifest exposes the event timezone');
    $manifestJson = (string) wp_json_encode($manifestData);
    $expect(!str_contains($manifestJson, 'user_email') && !str_contains($manifestJson, 'jpn_host_code'), 'manifest excludes host email and access codes');

    $health = $request('GET', '/' . EventController::NAMESPACE . '/health');
    $healthData = (array) $health->get_data();
    $expect($health->get_status() === 200 && ($healthData['status'] ?? null) === 'ok', 'authenticated health check reports ready');
    $expect(($healthData['plugin']['slug'] ?? null) === 'hexa-jpn-tools', 'health identifies the canonical plugin');

    $settings = $request('GET', '/' . EventController::NAMESPACE . '/settings');
    $settingsData = (array) $settings->get_data();
    $expect($settings->get_status() === 200, 'authenticated settings returns HTTP 200');
    foreach ((array) ($settingsData['hosts'] ?? []) as $host) {
        $expect(array_keys((array) $host) === ['id', 'display_name', 'slug', 'area_term_id', 'auto_approve', 'website', 'instagram_handle', 'host_code_configured'], 'manifest host row uses the bounded public contract');
    }
    foreach ((array) ($settingsData['areas'] ?? []) as $area) {
        $expect(array_keys((array) $area) === ['id', 'name', 'slug'], 'manifest area row uses the bounded public contract');
    }

    wp_set_current_user(0);
    $unauthorized = $request('GET', '/' . EventController::NAMESPACE . '/manifest');
    $expect(in_array($unauthorized->get_status(), [401, 403], true), 'anonymous manifest access is rejected');
    wp_set_current_user($administratorId);

    $login = 'hexa-jpn-' . strtolower(wp_generate_password(8, false, false));
    $createdUserId = wp_insert_user([
        'user_login' => $login,
        'user_pass' => wp_generate_password(32, true, true),
        'display_name' => 'Hexa JPN Fixture Host',
        'role' => 'host',
    ]);
    if ($createdUserId instanceof WP_Error) {
        throw new RuntimeException($createdUserId->get_error_message());
    }
    $createdUserId = (int) $createdUserId;
    $hostPatchRoute = '/' . EventController::NAMESPACE . '/hosts/' . $createdUserId;
    $hostPatch = $request('PATCH', $hostPatchRoute, [
        'instagram_handle' => 'hexa.jpn_fixture',
        'auto_approve' => false,
        'host_code' => $token . '-private',
    ]);
    $hostPatchData = (array) $hostPatch->get_data();
    $expect($hostPatch->get_status() === 200 && ($hostPatchData['result'] ?? null) === 'updated', 'administrator can update the bounded host fields');
    $expect(array_keys((array) ($hostPatchData['host'] ?? [])) === ['id', 'display_name', 'slug', 'area_term_id', 'auto_approve', 'website', 'instagram_handle', 'host_code_configured'], 'host PATCH returns the safe manifest summary shape');
    $expect(($hostPatchData['host']['host_code_configured'] ?? false) === true, 'host PATCH returns code presence only');
    $expect(!str_contains((string) wp_json_encode($hostPatchData), $token . '-private'), 'host PATCH never returns the private host code');
    $expect((string) get_user_meta($createdUserId, 'instagram_handle', true) === 'hexa.jpn_fixture', 'host PATCH stores the canonical Instagram handle');
    $expect((string) get_user_meta($createdUserId, 'jpn_instagram_handle', true) === 'hexa.jpn_fixture', 'host PATCH synchronizes the JPN Instagram alias');
    $expect((string) get_user_meta($createdUserId, 'instagram_url', true) === 'https://www.instagram.com/hexa.jpn_fixture/', 'host PATCH synchronizes the legacy Instagram URL');

    wp_set_current_user($createdUserId);
    $selfPatch = $request('PATCH', $hostPatchRoute, ['instagram_handle' => '@hexa.jpn_self']);
    $expect($selfPatch->get_status() === 403, 'host without the integration capability cannot use the first-party API');
    $selfModeration = $request('PATCH', $hostPatchRoute, ['auto_approve' => true]);
    $expect($selfModeration->get_status() === 403, 'host cannot change moderation through the integration API');
    wp_set_current_user($administratorId);

    $relatedPostId = wp_insert_post([
        'post_type' => 'event',
        'post_status' => 'draft',
        'post_title' => 'Hexa JPN Fixture Related ' . $token,
        'post_author' => $administratorId,
    ], true);
    if ($relatedPostId instanceof WP_Error) {
        throw new RuntimeException($relatedPostId->get_error_message());
    }
    $relatedPostId = (int) $relatedPostId;
    $createdPosts[] = $relatedPostId;

    $attachmentIds = get_posts([
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'DESC',
    ]);
    $attachmentId = isset($attachmentIds[0]) && current_user_can('read_post', (int) $attachmentIds[0])
        ? (int) $attachmentIds[0]
        : 0;

    $externalRef = 'code:jpn:' . $token;
    $createdRefs[] = $externalRef;
    $createPayload = [
        'operation_id' => $token . '-create',
        'status' => 'draft',
        'code_submission_id' => 987654321,
        'fields' => [
            'title' => 'Hexa JPN Fixture ' . $token,
            'start_at' => '2030-03-10T13:30:00-04:00',
            'end_at' => '2030-03-10T15:00:00-04:00',
            'location_name' => 'Fixture Hall',
            'location_address' => '123 Fixture Way',
            'related_post_ids' => [$relatedPostId],
        ],
    ];
    if ($attachmentId > 0) {
        $createPayload['attachment_id'] = $attachmentId;
        $createPayload['fields']['additional_media_ids'] = [$attachmentId];
    }

    $created = $request('PUT', $eventRoute($externalRef), $createPayload);
    $createdData = (array) $created->get_data();
    $eventPostId = (int) ($createdData['post_id'] ?? 0);
    if ($eventPostId > 0) {
        $createdPosts[] = $eventPostId;
    }
    $expect($created->get_status() === 201, 'new event returns HTTP 201');
    $expect(($createdData['schema_version'] ?? null) === 1, 'create receipt has schema version 1');
    $expect(($createdData['external_ref'] ?? null) === $externalRef, 'create receipt preserves the exact external reference');
    $expect(($createdData['operation_id'] ?? null) === $createPayload['operation_id'], 'create receipt preserves the exact operation ID');
    $expect(($createdData['mode'] ?? null) === 'create', 'create receipt records create mode');
    $expect(($createdData['result'] ?? null) === 'created' && ($createdData['success'] ?? false) === true, 'create receipt records a successful create');
    $expect(($createdData['public_state_changed'] ?? true) === false, 'draft create records no public state change');
    $expect($eventPostId > 0 && get_post_status($eventPostId) === 'draft', 'new event is created as a draft');
    $expect((string) get_post_meta($eventPostId, 'start_date', true) === '2030-03-10 13:30:00', 'start date uses Miami local storage');
    $expect((int) get_post_meta($eventPostId, 'start_date_timestamp', true) === 1899394200, 'start date stores a true Unix timestamp');
    $expect((int) get_post_meta($eventPostId, '_hexa_jpn_code_submission_id', true) === 987654321, 'Code submission ID is stored under the canonical key');

    $operation = $request('GET', '/' . EventController::NAMESPACE . '/operations/' . rawurlencode($createPayload['operation_id']));
    $operationData = (array) $operation->get_data();
    $expect($operation->get_status() === 200, 'operation receipt lookup returns HTTP 200');
    $expect(($operationData['external_ref'] ?? null) === $externalRef, 'operation receipt resolves its external reference');
    $expect(($operationData['receipt']['result'] ?? null) === 'created', 'operation receipt returns the durable create result');

    $replay = $request('PUT', $eventRoute($externalRef), $createPayload);
    $replayData = (array) $replay->get_data();
    $expect($replay->get_status() === 200, 'same operation replay returns HTTP 200');
    $expect(($replayData['result'] ?? null) === 'replayed' && ($replayData['original_outcome'] ?? null) === 'created', 'same operation replays the prior create outcome');
    $expect(($replayData['mode'] ?? null) === 'create' && ($replayData['post_id'] ?? null) === $eventPostId, 'replay retains create mode and post binding');

    $changedReplayPayload = $createPayload;
    $changedReplayPayload['fields']['title'] .= ' changed';
    $changedReplay = $request('PUT', $eventRoute($externalRef), $changedReplayPayload);
    $expect($changedReplay->get_status() === 409, 'same operation with changed payload conflicts');
    $expect(((array) $changedReplay->get_data())['error']['code'] === 'operation_payload_conflict', 'changed replay returns the operation payload conflict code');

    $stalePayload = $createPayload;
    $stalePayload['operation_id'] = $token . '-stale';
    $stale = $request('PUT', $eventRoute($externalRef), $stalePayload);
    $expect($stale->get_status() === 409, 'new operation without latest precondition conflicts');

    $updatePayload = [
        'operation_id' => $token . '-update',
        'expected_operation_id' => $createPayload['operation_id'],
        'status' => 'draft',
        'fields' => [
            'title' => 'Hexa JPN Fixture Updated ' . $token,
            'start_at' => '2030-03-11T14:45:00-04:00',
            'start_date' => '2030-03-12 09:00:00',
            'related_post_ids' => [$relatedPostId],
        ],
    ];
    $updated = $request('PUT', $eventRoute($externalRef), $updatePayload);
    $updatedData = (array) $updated->get_data();
    $expect($updated->get_status() === 200, 'existing event update returns HTTP 200');
    $expect(($updatedData['mode'] ?? null) === 'update' && ($updatedData['result'] ?? null) === 'updated', 'update receipt records update mode and outcome');
    $expect((string) get_post_meta($eventPostId, 'start_date', true) === '2030-03-11 14:45:00', 'canonical start_at wins when both date aliases are supplied');

    $found = $request('GET', $eventRoute($externalRef));
    $foundData = (array) $found->get_data();
    $snapshot = (array) ($foundData['event'] ?? []);
    $expect($found->get_status() === 200 && ($foundData['result'] ?? null) === 'found', 'exact external reference lookup succeeds');
    $expect(($foundData['mode'] ?? null) === 'update' && ($foundData['original_outcome'] ?? null) === 'updated', 'exact lookup returns the latest durable receipt state');
    foreach (['external_ref', 'operation_id', 'post_created_at', 'slug', 'registration_url', 'media_sizes', 'host_display_name', 'area_name', 'related_post_ids', 'related_external_refs'] as $field) {
        $expect(array_key_exists($field, $snapshot), 'snapshot includes ' . $field);
    }
    $expect(($snapshot['external_ref'] ?? null) === $externalRef, 'bound snapshot includes its safe external reference');
    $expect(($snapshot['operation_id'] ?? null) === $updatePayload['operation_id'], 'bound snapshot includes its latest operation ID');
    $expect(in_array($relatedPostId, (array) ($snapshot['related_post_ids'] ?? []), true), 'authorized snapshot includes readable related draft');
    if ($attachmentId > 0) {
        $expect(($snapshot['featured_media_id'] ?? null) === $attachmentId, 'authorized snapshot includes readable featured media');
        $expect((array) ($snapshot['media_sizes'] ?? []) !== [], 'snapshot includes available featured media sizes');
    }

    $alias = $request('GET', $eventRoute('wordpress:post:' . $eventPostId));
    $expect($alias->get_status() === 200 && ((array) $alias->get_data())['post_id'] === $eventPostId, 'legacy wordpress:post alias resolves the event');

    $missingRef = 'code:jpn:missing:' . $token;
    $missing = $request('GET', $eventRoute($missingRef));
    $missingData = (array) $missing->get_data();
    $expect($missing->get_status() === 404 && ($missingData['result'] ?? null) === 'not_found', 'authenticated exact absence returns HTTP 404 and not_found');
    wp_set_current_user(0);
    $hiddenMissing = $request('GET', $eventRoute($missingRef));
    $expect(in_array($hiddenMissing->get_status(), [401, 403], true), 'anonymous lookup stops at authentication before absence');
    wp_set_current_user($administratorId);

    $list = $request('GET', '/' . EventController::NAMESPACE . '/events', null, [
        'statuses' => 'draft',
        'include' => (string) $eventPostId,
        'per_page' => 1,
        'page' => 1,
    ]);
    $listData = (array) $list->get_data();
    $expect($list->get_status() === 200 && count((array) ($listData['events'] ?? [])) === 1, 'bounded include collection returns the requested draft');
    $expect((int) $listData['events'][0]['post_id'] === $eventPostId, 'include collection returns the exact post ID');
    $undatedList = $request('GET', '/' . EventController::NAMESPACE . '/events', null, [
        'statuses' => 'draft',
        'include' => (string) $relatedPostId,
    ]);
    $undatedData = (array) $undatedList->get_data();
    $expect($undatedList->get_status() === 200 && (int) $undatedData['events'][0]['post_id'] === $relatedPostId, 'include collection returns an undated event without a date-meta join');
    $expect(($undatedData['events'][0]['external_ref'] ?? null) === null && ($undatedData['events'][0]['operation_id'] ?? null) === null, 'unbound snapshot returns null binding identifiers');
    $tooMany = $request('GET', '/' . EventController::NAMESPACE . '/events', null, ['include' => range(1, 101)]);
    $expect($tooMany->get_status() === 422 && ((array) $tooMany->get_data())['error']['code'] === 'include_limit_exceeded', 'collection rejects more than 100 include IDs');

    $partialRef = 'code:jpn:partial:' . $token;
    $createdRefs[] = $partialRef;
    $partialPayload = [
        'operation_id' => $token . '-partial',
        'status' => 'draft',
        'fields' => [
            'title' => 'Hexa JPN Fixture Partial ' . $token,
            'start_at' => '2030-04-01T11:00:00-04:00',
            'area' => 999999999,
        ],
    ];
    $partial = $request('PUT', $eventRoute($partialRef), $partialPayload);
    $partialData = (array) $partial->get_data();
    $partialPostId = (int) ($partialData['post_id'] ?? 0);
    if ($partialPostId > 0) {
        $createdPosts[] = $partialPostId;
    }
    $expect($partial->get_status() === 500 && ($partialData['result'] ?? null) === 'partial', 'failed field after draft creation returns a partial receipt');
    $expect(($partialData['mode'] ?? null) === 'create' && ($partialData['success'] ?? true) === false, 'partial draft receipt retains create mode and failed success state');
    $expect(($partialData['external_ref'] ?? null) === $partialRef && ($partialData['operation_id'] ?? null) === $partialPayload['operation_id'], 'partial receipt retains binding fields');
    $expect(($partialData['public_state_changed'] ?? true) === false && get_post_status($partialPostId) === 'draft', 'partial create remains a nonpublic draft');
    $partialReplay = $request('PUT', $eventRoute($partialRef), $partialPayload);
    $partialReplayData = (array) $partialReplay->get_data();
    $expect(($partialReplayData['result'] ?? null) === 'replayed' && ($partialReplayData['original_outcome'] ?? null) === 'partial', 'partial operation replays the durable partial receipt');
    $expect(($partialReplayData['mode'] ?? null) === 'create' && ($partialReplayData['success'] ?? true) === false, 'partial replay retains create mode and failed success state');

    $adoptionPostId = wp_insert_post([
        'post_type' => 'event',
        'post_status' => 'draft',
        'post_title' => 'Hexa JPN Fixture Adoption ' . $token,
        'post_author' => $administratorId,
    ], true);
    if ($adoptionPostId instanceof WP_Error) {
        throw new RuntimeException($adoptionPostId->get_error_message());
    }
    $adoptionPostId = (int) $adoptionPostId;
    $createdPosts[] = $adoptionPostId;
    $adoptionRefA = 'code:jpn:adopt-a:' . $token;
    $adoptionRefB = 'code:jpn:adopt-b:' . $token;
    $createdRefs[] = $adoptionRefA;
    $createdRefs[] = $adoptionRefB;
    $adoptPayload = [
        'operation_id' => $token . '-adopt-a',
        'existing_post_id' => $adoptionPostId,
        'status' => 'draft',
        'fields' => ['title' => 'Hexa JPN Fixture Adopted ' . $token],
    ];
    $adopted = $request('PUT', $eventRoute($adoptionRefA), $adoptPayload);
    $expect($adopted->get_status() === 200 && ((array) $adopted->get_data())['mode'] === 'update', 'first legacy post adoption succeeds as an update');
    $adoptPayload['operation_id'] = $token . '-adopt-b';
    $secondAdoption = $request('PUT', $eventRoute($adoptionRefB), $adoptPayload);
    $expect($secondAdoption->get_status() === 409 && ((array) $secondAdoption->get_data())['error']['code'] === 'post_binding_conflict', 'one post cannot be adopted by a second external reference');

    $fixtureUser = get_userdata($createdUserId);
    if (!$fixtureUser instanceof WP_User) {
        throw new RuntimeException('The temporary host user could not be reloaded.');
    }
    $fixtureUser->set_role('contributor');
    wp_update_post(['ID' => $eventPostId, 'post_author' => $createdUserId]);
    wp_set_current_user($createdUserId);
    $contributorRead = $request('GET', $eventRoute($externalRef));
    $expect($contributorRead->get_status() === 403, 'event author without the integration capability cannot read through the first-party API');
    wp_set_current_user($administratorId);

    global $wpdb;
    $indexes = $wpdb->get_results('SHOW INDEX FROM ' . $bindings->tableName(), ARRAY_A);
    $hasUniquePost = false;
    foreach ((array) $indexes as $index) {
        if (($index['Column_name'] ?? '') === 'post_id' && (int) ($index['Non_unique'] ?? 1) === 0) {
            $hasUniquePost = true;
        }
    }
    $expect($hasUniquePost, 'binding schema enforces one unique external reference per post');

    WP_CLI::success(sprintf('Hexa JPN REST fixture passed %d assertions using drafts only.', $assertions));
} finally {
    wp_set_current_user($administratorId);
    foreach (array_values(array_unique($createdRefs)) as $reference) {
        $bindings->delete($reference);
    }
    foreach (array_reverse(array_values(array_unique(array_filter(array_map('intval', $createdPosts))))) as $postId) {
        wp_delete_post($postId, true);
    }
    if ($createdUserId > 0) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($createdUserId);
    }
}
