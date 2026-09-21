<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Rest;

use Hexa\JpnTools\Admin\HostRole;
use Hexa\JpnTools\Events\EventDates;
use Hexa\JpnTools\Integration\CoreIntegration;
use Hexa\JpnTools\Migrations\Migration;
use Hexa\JpnTools\Security\IntegrationAccess;
use WP_REST_Request;
use WP_REST_Response;

final class SystemController
{
    private const MAX_PAGE_SIZE = 100;

    public function __construct(
        private EventBindings $bindings,
        private EventDates $dates
    ) {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        foreach (['/health' => 'health', '/manifest' => 'manifest', '/settings' => 'settings'] as $route => $callback) {
            register_rest_route(EventController::NAMESPACE, $route, [
                'methods' => 'GET',
                'callback' => [$this, $callback],
                'permission_callback' => [IntegrationAccess::class, 'canManage'],
            ]);
        }
    }

    public function health(): WP_REST_Response
    {
        $migration = Migration::status();
        $healthy = $this->bindings->schemaReady()
            && ($migration['host_role_ready'] ?? false)
            && ($migration['integration_capability_ready'] ?? false)
            && ($migration['integration_role_ready'] ?? false);

        return new WP_REST_Response([
            'schema_version' => 1,
            'status' => $healthy ? 'ok' : 'degraded',
            'plugin' => self::pluginIdentity(),
            'rest_namespace' => EventController::NAMESPACE,
            'timezone' => $this->dates->timezone()->getName(),
            'binding_schema_ready' => $this->bindings->schemaReady(),
            'hexa_core' => CoreIntegration::status(),
        ], $healthy ? 200 : 503);
    }

    public function manifest(): WP_REST_Response
    {
        return new WP_REST_Response([
            'schema_version' => 1,
            'plugin' => self::pluginIdentity(),
            'site' => ['home_url' => home_url('/'), 'site_url' => site_url('/')],
            'timezone' => EventDates::TIMEZONE,
            'authentication' => [
                'type' => 'wordpress_application_password',
                'role' => IntegrationAccess::ROLE,
                'capability' => IntegrationAccess::CAPABILITY,
            ],
            'capabilities' => [
                'health' => true,
                'settings' => true,
                'event_collection' => true,
                'event_upsert' => $this->bindings->schemaReady(),
                'operation_receipts' => $this->bindings->schemaReady(),
                'draft_first' => true,
                'max_events_per_page' => 100,
            ],
            'endpoints' => [
                'health' => rest_url(EventController::NAMESPACE . '/health'),
                'manifest' => rest_url(EventController::NAMESPACE . '/manifest'),
                'settings' => rest_url(EventController::NAMESPACE . '/settings'),
                'events' => rest_url(EventController::NAMESPACE . '/events'),
                'event' => rest_url(EventController::NAMESPACE . '/events/{external_ref}'),
                'operation' => rest_url(EventController::NAMESPACE . '/operations/{operation_id}'),
                'host' => rest_url(EventController::NAMESPACE . '/hosts/{id}'),
            ],
        ], 200);
    }

    public function settings(WP_REST_Request $request): WP_REST_Response
    {
        $hostPage = max(1, (int) $request->get_param('hosts_page'));
        $hostPerPage = max(1, min(self::MAX_PAGE_SIZE, (int) ($request->get_param('hosts_per_page') ?: self::MAX_PAGE_SIZE)));
        $hosts = get_users([
            'role' => HostRole::ROLE,
            'number' => $hostPerPage + 1,
            'offset' => ($hostPage - 1) * $hostPerPage,
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);
        $hostsHaveMore = count($hosts) > $hostPerPage;
        $hostRows = array_map(
            static fn ($host): array => HostController::summary($host),
            array_slice($hosts, 0, $hostPerPage)
        );

        $areaTerms = get_terms(['taxonomy' => 'area', 'hide_empty' => false, 'number' => 201, 'orderby' => 'name']);
        if (is_wp_error($areaTerms)) {
            return $this->error('settings_areas_unavailable', 'The JPN area settings could not be loaded.', 503);
        }
        if (count($areaTerms) > 200) {
            return $this->error('settings_area_capacity_exceeded', 'The JPN area settings exceed the supported capacity of 200 areas.', 409);
        }

        $areas = array_map(
            static fn ($term): array => ['id' => (int) $term->term_id, 'name' => (string) $term->name, 'slug' => (string) $term->slug],
            $areaTerms
        );

        return new WP_REST_Response([
            'schema_version' => 1,
            'settings' => [
                'default_new_user_role' => (string) get_option(HostRole::DEFAULT_ROLE_OPTION, HostRole::ROLE),
                'default_featured_image_id' => (int) get_option('hexa_jpn_default_featured_image_id', 1354),
            ],
            'areas' => $areas,
            'hosts' => $hostRows,
            'hosts_page' => $hostPage,
            'hosts_per_page' => $hostPerPage,
            'hosts_has_more' => $hostsHaveMore,
        ], 200);
    }

    private static function pluginIdentity(): array
    {
        return ['name' => 'Hexa JPN Tools', 'slug' => 'hexa-jpn-tools', 'version' => HEXA_JPN_TOOLS_VERSION];
    }

    private function error(string $code, string $message, int $status): WP_REST_Response
    {
        return new WP_REST_Response([
            'schema_version' => 1,
            'success' => false,
            'result' => 'failed',
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
