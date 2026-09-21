<?php

namespace Hexa\PluginCore\WpCronTasks;

final class WpCronTask {
    private function __construct() {}

    public static function add_interval_schedule( array $schedules, string $schedule_key, int $interval_seconds, string $display ): array {
        $schedules[ $schedule_key ] = [
            'interval' => max( 1, $interval_seconds ),
            'display'  => $display,
        ];

        return $schedules;
    }

    /**
     * @param array<int,mixed> $args
     */
    public static function schedule_interval(
        string $hook,
        string $schedule_key,
        int $interval_seconds,
        string $display,
        array $args = [],
        ?int $start_timestamp = null,
        bool $clear_existing = true
    ): bool {
        $interval_seconds = max( 1, $interval_seconds );

        add_filter(
            'cron_schedules',
            static fn( $schedules ) => self::add_interval_schedule( (array) $schedules, $schedule_key, $interval_seconds, $display )
        );

        return self::schedule_existing( $hook, $schedule_key, $start_timestamp ?: time() + $interval_seconds, $args, $clear_existing );
    }

    /**
     * @param array<int,mixed> $args
     */
    public static function schedule_existing(
        string $hook,
        string $schedule_key,
        ?int $start_timestamp = null,
        array $args = [],
        bool $clear_existing = true
    ): bool {
        if ( $clear_existing ) {
            self::unschedule_hook( $hook, $args );
        }

        if ( wp_next_scheduled( $hook, $args ) ) {
            return true;
        }

        return (bool) wp_schedule_event( $start_timestamp ?: time() + self::day_in_seconds(), $schedule_key, $hook, $args );
    }

    /**
     * @param array<int,mixed> $args
     */
    public static function unschedule_hook( string $hook, array $args = [] ): void {
        $timestamp = wp_next_scheduled( $hook, $args );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, $hook, $args );
        }

        wp_clear_scheduled_hook( $hook, $args );
    }

    /**
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function status( string $hook, array $options = [] ): array {
        $args         = isset( $options['args'] ) && is_array( $options['args'] ) ? $options['args'] : [];
        $next_run     = wp_next_scheduled( $hook, $args );
        $schedule_key = isset( $options['schedule_key'] ) ? (string) $options['schedule_key'] : '';
        $callback     = isset( $options['callback'] ) ? $options['callback'] : null;

        $callback_registered = null === $callback ? true : has_action( $hook, $callback ) !== false;
        $events              = self::events_for_hook( $hook );
        $schedules           = wp_get_schedules();
        $cron_disabled       = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
        $alternate_cron      = defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON;
        $date_format         = isset( $options['date_format'] ) ? (string) $options['date_format'] : 'Y-m-d H:i:s';
        $next_datetime       = $next_run ? self::format_timestamp( (int) $next_run, $date_format, ! empty( $options['site_timezone'] ) ) : null;
        $next_human          = $next_run ? human_time_diff( time(), (int) $next_run ) : null;

        return [
            'hook'                  => $hook,
            'hook_name'             => $hook,
            'is_scheduled'          => ! empty( $next_run ),
            'next_run_timestamp'    => $next_run ?: null,
            'next_run'              => $next_datetime,
            'next_run_datetime'     => $next_datetime,
            'next_run_human'        => $next_human,
            'next_run_relative'     => $next_run ? ( (int) $next_run > time() ? 'in ' . $next_human : 'overdue by ' . human_time_diff( (int) $next_run, time() ) ) : 'Not scheduled',
            'events'                => $events,
            'event_count'           => count( $events ),
            'custom_interval_ok'    => $schedule_key !== '' ? isset( $schedules[ $schedule_key ] ) : true,
            'callback_registered'   => $callback_registered,
            'wp_cron_disabled'      => $cron_disabled,
            'alternate_cron'        => $alternate_cron,
            'cron_healthy'          => ! $cron_disabled && ! empty( $next_run ) && $callback_registered,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function events_for_hook( string $hook ): array {
        $cron_array = _get_cron_array();
        $events     = [];

        if ( ! is_array( $cron_array ) ) {
            return $events;
        }

        foreach ( $cron_array as $timestamp => $cron ) {
            if ( ! isset( $cron[ $hook ] ) || ! is_array( $cron[ $hook ] ) ) {
                continue;
            }

            foreach ( $cron[ $hook ] as $hash => $event ) {
                $events[] = [
                    'timestamp' => (int) $timestamp,
                    'datetime'  => date( 'Y-m-d H:i:s', (int) $timestamp ),
                    'schedule'  => $event['schedule'] ?? 'single',
                    'interval'  => $event['interval'] ?? null,
                    'args'      => $event['args'] ?? [],
                    'hash'      => $hash,
                ];
            }
        }

        return $events;
    }

    public static function day_in_seconds(): int {
        return defined( 'DAY_IN_SECONDS' ) ? (int) DAY_IN_SECONDS : 86400;
    }

    private static function format_timestamp( int $timestamp, string $format, bool $site_timezone ): string {
        if ( $site_timezone && function_exists( 'get_date_from_gmt' ) ) {
            return get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $timestamp ), $format );
        }

        return date( $format, $timestamp );
    }
}
