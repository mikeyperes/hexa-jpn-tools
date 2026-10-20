<?php

declare(strict_types=1);

$GLOBALS['jpn_test_timezone'] = 'America/New_York';
function wp_timezone(): DateTimeZone { return new DateTimeZone($GLOBALS['jpn_test_timezone']); }
require __DIR__.'/../src/Events/EventDates.php';
use Hexa\JpnTools\Events\EventDates;

$dates = new EventDates();
$east = $dates->normalize('2026-10-20 18:00:00');
if ($east['iso8601'] !== '2026-10-20T18:00:00-04:00') throw new RuntimeException('Eastern event parsing changed.');
$GLOBALS['jpn_test_timezone'] = 'America/Los_Angeles';
$west = $dates->normalize('2026-10-20 18:00:00');
if ($west['iso8601'] !== '2026-10-20T18:00:00-07:00' || $west['timestamp'] - $east['timestamp'] !== 10800) throw new RuntimeException('Pacific event parsing did not follow the site setting.');
$now = (new DateTimeImmutable('2026-10-20T04:30:00Z'))->getTimestamp();
$cutoff = EventDates::startOfToday($now);
if ((new DateTimeImmutable('@'.$cutoff))->setTimezone(wp_timezone())->format('Y-m-d H:i:s') !== '2026-10-19 00:00:00') throw new RuntimeException('Pacific calendar changed day too early.');
echo "Site timezone regression passed: Eastern parsing, Pacific parsing and Pacific midnight.\n";
