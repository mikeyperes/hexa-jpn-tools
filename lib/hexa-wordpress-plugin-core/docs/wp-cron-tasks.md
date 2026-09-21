# WP Cron Tasks

Namespace:

```text
Hexa\PluginCore\WpCronTasks
```

Use this namespace for reusable WordPress cron task mechanics.

## Public Class

```text
WpCronTask
```

## Responsibilities

- Register custom cron intervals.
- Schedule recurring events.
- Unschedule and clear events for a hook.
- Inspect all cron events for a hook.
- Report callback registration, next run, custom interval registration, and WP-Cron health.

## Method Reference

```php
WpCronTask::add_interval_schedule( array $schedules, string $schedule_key, int $interval_seconds, string $display ): array
WpCronTask::schedule_interval( string $hook, string $schedule_key, int $interval_seconds, string $display, array $args = [], ?int $start_timestamp = null, bool $clear_existing = true ): bool
WpCronTask::schedule_existing( string $hook, string $schedule_key, ?int $start_timestamp = null, array $args = [], bool $clear_existing = true ): bool
WpCronTask::unschedule_hook( string $hook, array $args = [] ): void
WpCronTask::status( string $hook, array $options = [] ): array
WpCronTask::events_for_hook( string $hook ): array
WpCronTask::day_in_seconds(): int
```

## Example

```php
use Hexa\PluginCore\WpCronTasks\WpCronTask;

WpCronTask::schedule_interval(
    'hws_log_cleaner_cron',
    'hws_log_cleaner_interval',
    5 * WpCronTask::day_in_seconds(),
    'Every 5 days (HWS Log Cleaner)'
);

$status = WpCronTask::status(
    'hws_log_cleaner_cron',
    [
        'callback' => 'hws_base_tools\\hws_log_cleaner_run',
        'schedule_key' => 'hws_log_cleaner_interval',
    ]
);
```

## Host Plugin Rule

The core schedules and reports cron metadata. The host plugin still owns the actual callback behavior, option names, reports, UI text, and task-specific settings.
