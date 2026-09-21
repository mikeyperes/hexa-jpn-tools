<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Rest;

use Hexa\JpnTools\Security\IntegrationAccess;
use WP_REST_Request;
use WP_REST_Response;

final class OperationController
{
    public function __construct(private EventBindings $bindings)
    {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(EventController::NAMESPACE, '/operations/(?P<operation_id>[^/]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get'],
            'permission_callback' => [IntegrationAccess::class, 'canManage'],
        ]);
    }

    public function get(WP_REST_Request $request): WP_REST_Response
    {
        $operationId = trim(rawurldecode((string) $request->get_param('operation_id')));
        if ($operationId === '' || strlen($operationId) > 191) {
            return $this->error('invalid_operation_id', 'The operation ID is required and must be no more than 191 bytes.', 400);
        }
        if (!$this->bindings->schemaReady()) {
            return $this->error('binding_schema_unavailable', 'The JPN event binding schema is not installed.', 503);
        }

        $binding = $this->bindings->findByOperationId($operationId);
        if ($binding === null) {
            return $this->error('operation_not_found', 'No receipt exists for the operation ID.', 404);
        }

        $outcome = is_array($binding['outcome_data'] ?? null) ? $binding['outcome_data'] : [];
        return new WP_REST_Response([
            'schema_version' => 1,
            'operation_id' => $operationId,
            'external_ref' => (string) $binding['external_ref'],
            'post_id' => isset($binding['post_id']) ? (int) $binding['post_id'] : null,
            'result' => (string) ($binding['result'] ?? 'processing'),
            'receipt' => $outcome ?: null,
        ], 200);
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
