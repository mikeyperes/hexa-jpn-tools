<?php

declare(strict_types=1);

function wp_unslash(mixed $value): mixed
{
    return is_string($value) ? stripslashes($value) : $value;
}

function maybe_unserialize(mixed $value): mixed
{
    if (!is_string($value)) {
        return $value;
    }
    $decoded = @unserialize($value, ['allowed_classes' => false]);
    return $decoded === false && $value !== 'b:0;' ? $value : $decoded;
}

function wp_json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false
{
    return json_encode($value, $flags, $depth);
}

$root = dirname(__DIR__);
require_once $root . '/src/Events/EventDates.php';
require_once $root . '/src/Events/EventQueries.php';
require_once $root . '/src/Events/EventRelations.php';
require_once $root . '/src/Admin/NotificationDashboard.php';
require_once $root . '/src/Frontend/Privacy.php';
require_once $root . '/src/Rest/EventBindings.php';
require_once $root . '/src/Rest/EventController.php';

use Hexa\JpnTools\Admin\NotificationDashboard;
use Hexa\JpnTools\Events\EventDates;
use Hexa\JpnTools\Events\EventRelations;
use Hexa\JpnTools\Frontend\Privacy;
use Hexa\JpnTools\Rest\EventBindings;
use Hexa\JpnTools\Rest\EventController;

$assertions = 0;
$failures = [];

$expect = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, "FAIL: {$message}\n");
    }
};

$dates = new EventDates();
$normalized = $dates->normalize('2026-07-04 16:30:00');
$expect($normalized['storage'] === '2026-07-04 16:30:00', 'local storage date is preserved');
$expect($normalized['precision'] === 'date_time', 'timed events retain date-time precision');
$expect(str_ends_with($normalized['iso8601'], '-04:00'), 'summer event uses Miami daylight offset');
$expect($dates->normalize('2026-07-04T20:30:00+00:00')['storage'] === '2026-07-04 16:30:00', 'offset date normalizes to Miami time');
$dateOnly = $dates->normalize('2027-01-14');
$expect($dateOnly['storage'] === '2027-01-14 00:00:00', 'date-only events retain calendar storage without inventing a source time');
$expect($dateOnly['display'] === 'January 14' && $dateOnly['precision'] === 'date', 'date-only events expose date precision and no midnight display');

$gapRejected = false;
try {
    $dates->normalize('2026-03-08 02:30:00');
} catch (RuntimeException) {
    $gapRejected = true;
}
$expect($gapRejected, 'nonexistent DST wall time is rejected');

$springNow = (new DateTimeImmutable('2026-03-08 12:00:00', $dates->timezone()))->getTimestamp();
[$springStart, $springEnd] = $dates->calendarWindow('today', $springNow);
$expect($springEnd - $springStart === 23 * 3600, 'spring DST calendar day has 23 real hours');
$fallNow = (new DateTimeImmutable('2026-11-01 12:00:00', $dates->timezone()))->getTimestamp();
[$fallStart, $fallEnd] = $dates->calendarWindow('today', $fallNow);
$expect($fallEnd - $fallStart === 25 * 3600, 'fall DST calendar day has 25 real hours');

$parsed = EventRelations::parseRelatedIds('[{"post_id":9},{"id":4},9,"bad"]');
$expect($parsed === [9, 4], 'related JSON is normalized and deduplicated');
$parsedSerialized = EventRelations::parseRelatedIds(serialize([['post_id' => 12], 13]));
$expect($parsedSerialized === [12, 13], 'legacy serialized relations remain readable');
$excluded = EventRelations::excludedIdsFromGraph(
    [1 => [2], 2 => [1, 3], 3 => [2], 8 => [9], 9 => [8]],
    [1 => 300, 2 => 100, 3 => 200, 8 => 10, 9 => 20],
    [1, 2, 3, 9]
);
sort($excluded);
$expect($excluded === [1, 3], 'each related component retains its earliest eligible future event');
$legacyContent = 'Before<!-- jpn-related-events:start --><p>Old links</p><!-- jpn-related-events:end -->After';
$expect(EventRelations::stripLegacyBlock($legacyContent) === 'BeforeAfter', 'legacy embedded relation block is removed');

$private = '<p>Public</p><!-- jpn-code-reference:start --><p>Code ID 55 ↗</p><!-- jpn-code-reference:end --><p>End</p>';
$expect(!str_contains(Privacy::stripBlocks($private), 'Code ID'), 'private Code reference block is removed');
$expect(Privacy::stripText('Title Code ID #55 &#8599; tail') === 'Title tail', 'private Code reference text is removed');

$single = NotificationDashboard::splitMessages(['Event A'], 200, "Header\n", 'Footer');
$expect($single === ["Header\nEvent AFooter"], 'single message retains header and footer');
$events = [str_repeat('A', 35), "📣" . str_repeat('B', 45), str_repeat('C', 35)];
$parts = NotificationDashboard::splitMessages($events, 90, "Header\n", "\nFooter");
$payload = '';
foreach ($parts as $part) {
    $expect(strlen($part) <= 90, 'split message stays within its byte limit');
    $payload .= (string) preg_replace('/^Part \d+\/\d+:\n/', '', $part);
}
$expect($payload === "Header\n" . implode('', $events) . "\nFooter", 'split messages preserve exact ordered content');
$expect(count($parts) > 1, 'long message is split into multiple parts');

$binding = ['operation_id' => 'op-1', 'request_digest' => 'digest-1'];
$expect(EventBindings::transition(null, 'op-1', 'digest-1', null)['action'] === 'proceed', 'first operation proceeds');
$expect(EventBindings::transition(null, 'op-1', 'digest-1', 'older')['code'] === 'stale_operation', 'unexpected first-operation precondition conflicts');
$expect(EventBindings::transition($binding, 'op-1', 'digest-1', null)['action'] === 'replay', 'same operation and digest replays');
$expect(EventBindings::transition($binding, 'op-1', 'digest-2', null)['code'] === 'operation_payload_conflict', 'reused operation with changed payload conflicts');
$expect(EventBindings::transition($binding, 'op-2', 'digest-2', null)['code'] === 'stale_operation', 'new operation requires latest precondition');
$expect(EventBindings::transition($binding, 'op-2', 'digest-2', 'op-1')['action'] === 'proceed', 'new operation with latest precondition proceeds');

$firstPayload = ['operation_id' => 'a', 'fields' => ['title' => 'T', 'related_post_ids' => [2, 1]], 'status' => 'draft'];
$secondPayload = ['status' => 'draft', 'fields' => ['related_post_ids' => [2, 1], 'title' => 'T'], 'operation_id' => 'b'];
$expect(EventController::requestDigest($firstPayload) === EventController::requestDigest($secondPayload), 'request digest ignores operation metadata and object key order');
$secondPayload['fields']['related_post_ids'] = [1, 2];
$expect(EventController::requestDigest($firstPayload) !== EventController::requestDigest($secondPayload), 'request digest preserves list order');

$bootstrapPath = $root . '/hexa-jpn-tools.php';
$bootstrap = (string) file_get_contents($bootstrapPath);
$expect(str_contains($bootstrap, 'Plugin Name: Hexa JPN Tools'), 'prepared bootstrap has the final display name');
$expect(str_contains($bootstrap, "define('HEXA_JPN_TOOLS_VERSION', '1.1.0')"), 'plugin header and version constant use version 1.1.0');
$expect(str_contains($bootstrap, '\\Hexa\\JpnTools\\Plugin::register();'), 'plugin bootstrap registers the isolated namespaced plugin');
$expect(str_contains($bootstrap, "hexa_plugin_core_register_package('hexa-jpn-tools'"), 'plugin registers its vendored Hexa WP Core candidate');

if ($failures !== []) {
    fwrite(STDERR, sprintf("%d/%d assertions failed.\n", count($failures), $assertions));
    exit(1);
}

fwrite(STDOUT, sprintf("PASS: %d assertions.\n", $assertions));
