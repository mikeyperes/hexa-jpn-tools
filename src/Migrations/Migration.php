<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Migrations;

use Hexa\JpnTools\Admin\HostRole;
use Hexa\JpnTools\Content\ContentTypes;
use Hexa\JpnTools\Events\EventDates;
use Hexa\JpnTools\Rest\EventBindings;
use Hexa\JpnTools\Security\IntegrationAccess;
use Throwable;

final class Migration
{
    public static function activate(): void
    {
        self::installBindingSchema();
        self::migrateRole();
        IntegrationAccess::install();
        self::recordLegacyCutover();
        (new ContentTypes())->registerTypes();
        flush_rewrite_rules(false);
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules(false);
    }

    public static function installBindingSchema(): array
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $wpdb->prefix . 'hexa_jpn_event_bindings';
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            external_ref_hash char(64) NOT NULL,
            external_ref text NOT NULL,
            post_id bigint(20) unsigned NULL,
            operation_id varchar(191) NOT NULL,
            request_digest char(64) NOT NULL,
            result varchar(32) NOT NULL DEFAULT 'processing',
            outcome longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (external_ref_hash),
            UNIQUE KEY post_id (post_id),
            KEY operation_id (operation_id),
            KEY result_updated (result, updated_at)
        ) {$charset};";
        $changes = dbDelta($sql);
        update_option('hexa_jpn_binding_schema_version', 2, false);

        return ['table' => $table, 'changes' => array_values((array) $changes), 'ready' => $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table];
    }

    public static function migrateRole(): array
    {
        $contributor = get_role('contributor');
        $host = get_role(HostRole::ROLE);
        if (!$host) {
            $host = add_role(HostRole::ROLE, __('Host', 'hexa-jpn-tools'), $contributor ? $contributor->capabilities : ['read' => true, 'edit_posts' => true]);
        }
        if (!$host) {
            return ['success' => false, 'message' => 'The host role could not be created.'];
        }

        foreach ((array) ($contributor ? $contributor->capabilities : []) as $capability => $allowed) {
            if ($allowed) {
                $host->add_cap((string) $capability, true);
            }
        }
        update_option(HostRole::DEFAULT_ROLE_OPTION, HostRole::ROLE, false);
        update_option('hexa_jpn_role_schema_version', 1, false);
        add_option('hexa_jpn_default_featured_image_id', 1354, '', false);

        return ['success' => true, 'role' => HostRole::ROLE, 'capabilities' => array_keys((array) $host->capabilities)];
    }

    public static function migrateTimezoneAndDates(bool $execute = false): array
    {
        $dates = new EventDates();
        $timezoneBefore = wp_timezone_string();
        $page = 1;
        $seen = 0;
        $updated = 0;
        $invalid = [];

        do {
            $query = new \WP_Query([
                'post_type' => 'event',
                'post_status' => 'any',
                'posts_per_page' => 200,
                'paged' => $page,
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
                'no_found_rows' => false,
            ]);
            foreach (array_map('intval', $query->posts) as $postId) {
                $seen++;
                $valid = true;
                foreach (['start', 'end'] as $kind) {
                    $sourceKey = $kind . '_date';
                    $raw = trim((string) get_post_meta($postId, $sourceKey, true));
                    if ($raw === '') {
                        continue;
                    }
                    try {
                        $normalized = $dates->normalize($raw);
                        if ($execute) {
                            update_post_meta($postId, $sourceKey, $normalized['storage']);
                            update_post_meta($postId, $sourceKey . '_timestamp', (string) $normalized['timestamp']);
                            update_post_meta($postId, $sourceKey . '_display', $normalized['display']);
                        }
                    } catch (Throwable $exception) {
                        $valid = false;
                        if (count($invalid) < 50) {
                            $invalid[] = ['post_id' => $postId, 'field' => $sourceKey];
                        }
                    }
                }
                if ($valid) {
                    $updated++;
                }
            }
            $page++;
        } while ($page <= (int) $query->max_num_pages);

        if ($execute) {
            update_option('timezone_string', EventDates::TIMEZONE);
            update_option('hexa_jpn_date_schema_version', 1, false);
        }

        return [
            'execute' => $execute,
            'timezone_before' => $timezoneBefore,
            'timezone_target' => EventDates::TIMEZONE,
            'events_seen' => $seen,
            'events_valid' => $updated,
            'invalid_count' => $seen - $updated,
            'invalid_examples' => $invalid,
        ];
    }

    public static function status(): array
    {
        $host = get_role(HostRole::ROLE);
        $administrator = get_role('administrator');
        $integrationRole = get_role(IntegrationAccess::ROLE);
        $bindings = new EventBindings();

        return [
            'plugin_version' => defined('HEXA_JPN_TOOLS_VERSION') ? HEXA_JPN_TOOLS_VERSION : null,
            'binding_schema_version' => (int) get_option('hexa_jpn_binding_schema_version', 0),
            'binding_schema_ready' => $bindings->schemaReady(),
            'role_schema_version' => (int) get_option('hexa_jpn_role_schema_version', 0),
            'host_role_ready' => $host !== null,
            'integration_capability_ready' => $administrator !== null && $administrator->has_cap(IntegrationAccess::CAPABILITY),
            'integration_role_ready' => $integrationRole !== null && $integrationRole->has_cap(IntegrationAccess::CAPABILITY),
            'configured_default_role' => (string) get_option(HostRole::DEFAULT_ROLE_OPTION, ''),
            'wordpress_default_role' => (string) get_option('default_role', ''),
            'date_schema_version' => (int) get_option('hexa_jpn_date_schema_version', 0),
            'timezone' => wp_timezone_string(),
            'mu_cutover' => self::muCutoverStatus(),
            'legacy_cutover' => get_option('hexa_jpn_legacy_cutover', []),
        ];
    }

    public static function rollbackSnapshot(): array
    {
        $optionNames = [
            'timezone_string',
            'gmt_offset',
            'default_role',
            HostRole::DEFAULT_ROLE_OPTION,
            'hexa_jpn_default_featured_image_id',
            'hexa_jpn_binding_schema_version',
            'hexa_jpn_role_schema_version',
            'hexa_jpn_date_schema_version',
            'hexa_jpn_legacy_cutover',
        ];
        $options = [];
        foreach ($optionNames as $name) {
            $sentinel = new \stdClass();
            $value = get_option($name, $sentinel);
            $exists = $value !== $sentinel;
            $options[$name] = [
                'exists' => $exists,
                'value' => $exists ? $value : null,
            ];
        }

        $host = get_role(HostRole::ROLE);
        $eventMeta = [];
        $metaKeys = [
            'start_date',
            'start_date_timestamp',
            'start_date_display',
            'end_date',
            'end_date_timestamp',
            'end_date_display',
        ];
        $page = 1;
        do {
            $query = new \WP_Query([
                'post_type' => 'event',
                'post_status' => 'any',
                'posts_per_page' => 500,
                'paged' => $page,
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
                'no_found_rows' => false,
                'update_post_meta_cache' => true,
            ]);
            foreach (array_map('intval', $query->posts) as $postId) {
                $meta = [];
                foreach ($metaKeys as $key) {
                    $meta[$key] = get_post_meta($postId, $key, true);
                }
                $eventMeta[(string) $postId] = $meta;
            }
            $page++;
        } while ($page <= (int) $query->max_num_pages);

        return [
            'snapshot_version' => 1,
            'captured_at_gmt' => gmdate(DATE_ATOM),
            'site_url' => site_url('/'),
            'options' => $options,
            'host_role' => [
                'exists' => $host !== null,
                'name' => $host ? (string) $host->name : null,
                'capabilities' => $host ? (array) $host->capabilities : [],
            ],
            'administrator_integration_capability' => ($administrator = get_role('administrator'))
                ? $administrator->has_cap(IntegrationAccess::CAPABILITY)
                : false,
            'event_date_meta' => $eventMeta,
            'mu_cutover' => self::muCutoverStatus(),
        ];
    }

    public static function muCutoverStatus(): array
    {
        $paths = [
            WPMU_PLUGIN_DIR . '/jpn-code-reference-privacy.php',
            WPMU_PLUGIN_DIR . '/jpn-event-admin-thumbnails.php',
        ];
        return [
            'plugin_privacy_ready' => class_exists('Hexa\\JpnTools\\Frontend\\Privacy'),
            'plugin_thumbnails_ready' => class_exists('Hexa\\JpnTools\\Admin\\EventAdmin'),
            'mu_files' => array_map(static fn (string $path): array => ['path' => $path, 'present' => is_readable($path)], $paths),
            'instruction' => 'After plugin verification, the release owner must move both MU files to its rollback checkpoint. The plugin activates each replacement automatically when the corresponding MU file is absent.',
        ];
    }

    private static function recordLegacyCutover(): void
    {
        $legacyPlugins = ['jpn-structure/initialization.php'];
        $active = array_values(array_intersect($legacyPlugins, (array) get_option('active_plugins', [])));

        update_option('hexa_jpn_legacy_cutover', [
            'recorded_at_gmt' => gmdate(DATE_ATOM),
            'legacy_plugins_active_before' => $active,
            'legacy_plugins_preserved' => true,
        ], false);

        if ($active === []) {
            return;
        }

        if (!function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        deactivate_plugins($active, true);
    }
}
