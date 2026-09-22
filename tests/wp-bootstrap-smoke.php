<?php

if (!defined('WP_CLI') || !WP_CLI) {
    throw new RuntimeException('Run this smoke check with WP-CLI.');
}

$root = dirname(__DIR__);
$assertions = 0;
$expect = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Bootstrap assertion failed: ' . $message);
    }
};
$hasClassCallback = static function (string $hookName, string $className): bool {
    global $wp_filter;
    $hook = $wp_filter[$hookName] ?? null;
    if (!$hook instanceof WP_Hook) {
        return false;
    }
    foreach ($hook->callbacks as $callbacks) {
        foreach ($callbacks as $callback) {
            $function = $callback['function'] ?? null;
            if (is_array($function) && is_object($function[0] ?? null) && $function[0] instanceof $className) {
                return true;
            }
        }
    }
    return false;
};

$privacyMuLoaded = function_exists('jpn_code_reference_private_strip_blocks');
$thumbnailMuLoaded = is_readable(WPMU_PLUGIN_DIR . '/jpn-event-admin-thumbnails.php');
if (!defined('HEXA_JPN_TOOLS_VERSION')) {
    require $root . '/hexa-jpn-tools.php';
}

$expect(defined('HEXA_JPN_TOOLS_VERSION') && HEXA_JPN_TOOLS_VERSION === '1.0.2', 'plugin bootstrap defines version 1.0.2');
$expect(class_exists(Hexa\JpnTools\Plugin::class), 'namespaced plugin class autoloads');
$expect(
    $privacyMuLoaded
        ? !$hasClassCallback('the_content', Hexa\JpnTools\Frontend\Privacy::class)
        : $hasClassCallback('the_content', Hexa\JpnTools\Frontend\Privacy::class),
    $privacyMuLoaded ? 'plugin defers privacy hooks while the legacy MU owner is present' : 'plugin owns privacy hooks after the MU cutover'
);
$expect(
    $thumbnailMuLoaded
        ? !$hasClassCallback('manage_event_posts_columns', Hexa\JpnTools\Admin\EventAdmin::class)
        : $hasClassCallback('manage_event_posts_columns', Hexa\JpnTools\Admin\EventAdmin::class),
    $thumbnailMuLoaded ? 'plugin defers event columns while the legacy MU owner is present' : 'plugin owns event columns after the MU cutover'
);

(new Hexa\JpnTools\Content\ContentTypes())->registerTypes();
$eventType = get_post_type_object('event');
$serviceType = get_post_type_object('service');
$areaTaxonomy = get_taxonomy('area');
$expect($eventType instanceof WP_Post_Type && $eventType->rewrite['slug'] === 'event', 'event post type retains the event permalink slug');
$expect($serviceType instanceof WP_Post_Type && $serviceType->rewrite['slug'] === 'service', 'service post type retains the service permalink slug');
$expect($areaTaxonomy instanceof WP_Taxonomy && $areaTaxonomy->rewrite['slug'] === 'area', 'area taxonomy retains the area permalink slug');
$expect(post_type_supports('event', 'author') && post_type_supports('event', 'thumbnail'), 'event post type retains author and thumbnail support');

update_option('hexa_jpn_plugin_version', '1.0.1', false);
Hexa\JpnTools\Migrations\Migration::maybeUpgrade();
$expect(get_option('hexa_jpn_plugin_version') === '1.0.2', 'plugin upgrade records version 1.0.2');
$rewriteRules = (array) get_option('rewrite_rules', []);
$expect(isset($rewriteRules['event/([^/]+)/?$']), 'plugin upgrade refreshes the event permalink rule');

(new Hexa\JpnTools\Content\AcfFields())->registerFields();
$expect(function_exists('acf_get_local_field_group') && is_array(acf_get_local_field_group('group_6768f6933c3ea')), 'historical Event ACF group is registered locally');
$expect(is_array(acf_get_local_field_group('group_jpn_event_host_link')), 'event host ACF group is registered locally');
$expect(is_array(acf_get_local_field_group('group_jpn_host_meta')), 'host metadata ACF group is registered locally');

$expect(shortcode_exists('events-photos'), 'events photos shortcode is registered');
$expect(shortcode_exists('jpn_upcoming_event_banner'), 'upcoming event banner shortcode is registered');
$expect(shortcode_exists('jpn_event_venue'), 'event venue shortcode is registered');
$expect(shortcode_exists('jpn_event_time_range'), 'event time range shortcode is registered');

Hexa\JpnTools\Integration\CoreIntegration::boot();
$core = Hexa\JpnTools\Integration\CoreIntegration::status();
$expect(($core['available'] ?? false) === true, 'shared Hexa Plugin Core is available through HWS Base Tools');
$expect(($core['healthy'] ?? false) === true, 'shared Hexa Plugin Core reports healthy');

do_action('rest_api_init');
$routes = rest_get_server()->get_routes();
$expect(isset($routes['/hexa-jpn/v1/manifest']), 'manifest REST route is registered');
$expect(isset($routes['/hexa-jpn/v1/health']), 'health REST route is registered');
$expect(isset($routes['/hexa-jpn/v1/settings']), 'settings REST route is registered');
$expect(isset($routes['/hexa-jpn/v1/events']), 'event collection REST route is registered');
$expect(isset($routes['/hexa-jpn/v1/events/(?P<external_ref>[^/]+)']), 'exact event REST route is registered');
$expect(isset($routes['/hexa-jpn/v1/operations/(?P<operation_id>[^/]+)']), 'operation receipt REST route is registered');
$expect(isset($routes['/hexa-jpn/v1/hosts/(?P<id>\\d+)']), 'bounded host PATCH route is registered');

WP_CLI::success(sprintf(
    'Hexa JPN bootstrap smoke check passed %d assertions; privacy owner=%s, thumbnail owner=%s.',
    $assertions,
    $privacyMuLoaded ? 'MU plugin' : 'Hexa JPN Tools',
    $thumbnailMuLoaded ? 'MU plugin' : 'Hexa JPN Tools'
));
