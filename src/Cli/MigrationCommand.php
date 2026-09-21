<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Cli;

use Hexa\JpnTools\Migrations\Migration;

final class MigrationCommand
{
    public static function register(): void
    {
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('hexa-jpn migrate', new self());
            \WP_CLI::add_command('hexa-jpn status', [self::class, 'status']);
            \WP_CLI::add_command('hexa-jpn checkpoint', [self::class, 'checkpoint']);
        }
    }

    /**
     * Runs one explicit Hexa JPN Tools migration.
     *
     * ## OPTIONS
     *
     * --step=<step>
     * : schema, roles, or timezone.
     *
     * [--execute]
     * : Required for the timezone/date mutation. Without it, timezone is a dry run.
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $step = sanitize_key((string) ($assocArgs['step'] ?? ''));
        $execute = isset($assocArgs['execute']);
        $result = match ($step) {
            'schema' => Migration::installBindingSchema(),
            'roles' => Migration::migrateRole(),
            'timezone' => Migration::migrateTimezoneAndDates($execute),
            default => ['success' => false, 'message' => 'Use --step=schema, --step=roles, or --step=timezone.'],
        };
        \WP_CLI::line((string) wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if (($result['success'] ?? true) === false) {
            \WP_CLI::halt(1);
        }
    }

    public static function status(): void
    {
        \WP_CLI::line((string) wp_json_encode(Migration::status(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public static function checkpoint(): void
    {
        \WP_CLI::line((string) wp_json_encode(Migration::rollbackSnapshot(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
