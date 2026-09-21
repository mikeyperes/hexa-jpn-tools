<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assertions = 0;
$failures = [];
$expect = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, "FAIL: {$message}\n");
    }
};
$read = static fn (string $relative): string => (string) file_get_contents($root . '/' . $relative);

$main = $read('hexa-jpn-tools.php');
$core = $read('src/Integration/CoreIntegration.php');
$system = $read('src/Rest/SystemController.php');
$events = $read('src/Rest/EventController.php');
$hosts = $read('src/Rest/HostController.php');
$migration = $read('src/Migrations/Migration.php');

$expect(str_contains($main, 'Plugin Name: Hexa JPN Tools'), 'display name is canonical');
$expect(str_contains($main, 'GitHub Plugin URI: https://github.com/mikeyperes/hexa-jpn-tools'), 'GitHub updater points at the canonical repository');
$expect(str_contains($main, "Text Domain: hexa-jpn-tools"), 'text domain matches the folder slug');
$expect(str_contains($main, "hexa_plugin_core_register_package('hexa-jpn-tools'"), 'vendored Hexa WP Core is registered');
$expect(trim($read('lib/hexa-wordpress-plugin-core/VERSION')) === '3.0.6', 'vendored Hexa WP Core version is 3.0.6');
$expect(str_contains($core, "'slug'        => 'hexa-jpn-tools'"), 'Core PluginContext uses the canonical slug');
$expect(str_contains($core, "'github_repo' => 'mikeyperes/hexa-jpn-tools'"), 'Core PluginContext uses the canonical repository');
$expect(str_contains($core, 'GitHubPluginUpdater'), 'plugin updater is delegated to Hexa WP Core');
$expect(str_contains($core, 'CorePackageAjaxController'), 'Core package updater is delegated to Hexa WP Core');

foreach (glob($root . '/src/*.php') ?: [] as $file) {
    $expect(str_contains((string) file_get_contents($file), 'namespace Hexa\\JpnTools'), basename($file) . ' uses the isolated plugin namespace');
}
foreach (glob($root . '/src/*/*.php') ?: [] as $file) {
    $expect(str_contains((string) file_get_contents($file), 'namespace Hexa\\JpnTools\\'), str_replace($root . '/', '', $file) . ' uses the isolated plugin namespace');
}

$expect(str_contains($system, "'/health' => 'health'"), 'versioned contract exposes health');
$expect(str_contains($system, "'/manifest' => 'manifest'"), 'versioned contract exposes the manifest');
$expect(str_contains($system, "'/settings' => 'settings'"), 'versioned contract exposes bounded settings');
$expect(str_contains($events, "'/events'"), 'versioned contract exposes event collection');
$expect(str_contains($read('src/Rest/OperationController.php'), "'/operations/(?P<operation_id>[^/]+)'"), 'versioned contract exposes operation receipts');
$expect(str_contains($events, 'IntegrationAccess::canManage()'), 'event access uses the dedicated integration capability');
$expect(str_contains($hosts, 'IntegrationAccess::canManage()'), 'host access uses the dedicated integration capability');
$expect(!str_contains($system, 'DB_PASSWORD'), 'contract contains no database credential path');
$expect(!str_contains($system, 'wp_remote_get'), 'contract does not scrape the site');
$expect(!str_contains($system, 'user_email') && !str_contains($system, 'jpn_host_code'), 'manifest and settings do not expose private host values');
$expect(str_contains($migration, "'jpn-structure/initialization.php'"), 'activation recognizes the exact legacy plugin basename');
$expect(str_contains($migration, "'legacy_plugins_preserved' => true"), 'cutover records that legacy files stay preserved');
$expect(!str_contains($migration, 'delete_plugins('), 'migration never removes the legacy plugin');

$allSource = '';
foreach (glob($root . '/src/*/*.php') ?: [] as $file) {
    $allSource .= (string) file_get_contents($file);
}
$expect(!str_contains($allSource, 'wp_ajax_nopriv_'), 'plugin registers no anonymous mutation endpoint');
$expect(!str_contains($allSource, 'mysqli_') && !str_contains($allSource, 'PDO('), 'plugin has no external direct-database integration');

if ($failures !== []) {
    fwrite(STDERR, sprintf("%d/%d architecture assertions failed.\n", count($failures), $assertions));
    exit(1);
}

fwrite(STDOUT, sprintf("PASS: %d architecture assertions.\n", $assertions));
