<?php

declare(strict_types=1);

$filters = [];
$meta = [
    6248 => [
        'start_date_precision' => 'date',
        'start_date_timestamp' => '1799902800',
    ],
    6249 => [
        'start_date_precision' => 'date_time',
        'start_date_timestamp' => '1799941500',
    ],
];

function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
{
}

function add_filter(string $hook, callable $callback, int $priority, int $acceptedArgs): void
{
    global $filters;

    $filters[$hook] = compact('callback', 'priority', 'acceptedArgs');
}

function get_post_meta(int $postId, string $key, bool $single = false): mixed
{
    global $meta;

    return $meta[$postId][$key] ?? '';
}

require dirname(__DIR__) . '/src/Events/EventDates.php';
require dirname(__DIR__) . '/src/Content/AcfFields.php';

$fields = new Hexa\JpnTools\Content\AcfFields();
$fields->register();

$filter = $filters['acf/format_value/name=start_date'] ?? null;
if (!is_array($filter) || $filter['priority'] !== 20 || $filter['acceptedArgs'] !== 2) {
    throw new RuntimeException('The precision-aware start-date formatter is not registered after ACF formatting.');
}

if ($fields->formatStartDate('January 14, 2027 12:00 am', 6248) !== 'January 14, 2027') {
    throw new RuntimeException('Date-only event cards still expose an invented midnight time.');
}

if ($fields->formatStartDate('January 14, 2027 10:45 am', 6249) !== 'January 14, 2027 10:45 am') {
    throw new RuntimeException('Timed event cards no longer retain their supplied time.');
}

echo "Date-only event card rendering regression check passed.\n";
