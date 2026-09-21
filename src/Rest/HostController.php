<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Rest;

use Hexa\JpnTools\Admin\HostRole;
use Hexa\JpnTools\Security\IntegrationAccess;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_User;

final class HostController
{
    private const INSTAGRAM_ACF_KEY = 'field_67c952da83ab2';
    private const HOST_CODE_ACF_KEY = 'field_jpn_host_code';
    private const AUTO_APPROVE_ACF_KEY = 'field_jpn_auto_approve';
    private const ALLOWED_FIELDS = ['instagram_handle', 'auto_approve', 'host_code'];

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(EventController::NAMESPACE, '/hosts/(?P<id>\d+)', [
            'methods' => 'PATCH',
            'callback' => [$this, 'patchHost'],
            'permission_callback' => [$this, 'canPatch'],
            'args' => [
                'id' => [
                    'sanitize_callback' => 'absint',
                    'validate_callback' => static fn (mixed $value): bool => (int) $value > 0,
                ],
            ],
        ]);
    }

    public function canPatch(WP_REST_Request $request): bool
    {
        return IntegrationAccess::canManage();
    }

    public function patchHost(WP_REST_Request $request): WP_REST_Response
    {
        $userId = (int) $request->get_param('id');
        $host = get_userdata($userId);
        if (!$host instanceof WP_User || !in_array(HostRole::ROLE, (array) $host->roles, true)) {
            return $this->error('host_not_found', 'The selected JPN host does not exist.', 404);
        }

        $payload = $request->get_json_params();
        if (!is_array($payload) || $payload === []) {
            $payload = $request->get_body_params();
        }
        if (!is_array($payload) || $payload === []) {
            return $this->error('invalid_host_fields', 'Supply at least one supported host field.', 422);
        }

        $unknown = array_values(array_diff(array_keys($payload), self::ALLOWED_FIELDS));
        if ($unknown !== []) {
            return $this->error('unsupported_host_field', 'Only instagram_handle, auto_approve, and host_code may be changed.', 422);
        }

        $values = [];
        if (array_key_exists('instagram_handle', $payload)) {
            $instagram = $this->normalizeInstagram($payload['instagram_handle']);
            if ($instagram instanceof WP_Error) {
                return $this->error($instagram->get_error_code(), $instagram->get_error_message(), 422);
            }
            $values['instagram_handle'] = $instagram;
        }
        if (array_key_exists('auto_approve', $payload)) {
            if (!$this->isBooleanValue($payload['auto_approve'])) {
                return $this->error('invalid_auto_approve', 'auto_approve must be a boolean value.', 422);
            }
            $values['auto_approve'] = rest_sanitize_boolean($payload['auto_approve']) ? '1' : '0';
        }
        if (array_key_exists('host_code', $payload)) {
            if (!is_scalar($payload['host_code']) && $payload['host_code'] !== null) {
                return $this->error('invalid_host_code', 'host_code must be text or null.', 422);
            }
            $hostCode = sanitize_text_field((string) ($payload['host_code'] ?? ''));
            if (strlen($hostCode) > 191) {
                return $this->error('invalid_host_code', 'host_code must be no more than 191 bytes.', 422);
            }
            $values['host_code'] = $hostCode;
        }

        $effects = [];
        $failures = [];
        foreach ($values as $field => $value) {
            $stored = match ($field) {
                'instagram_handle' => $this->storeInstagram($userId, $value),
                'auto_approve' => $this->storeAliases($userId, [
                    'jpn_auto_approve' => $value,
                    '_jpn_auto_approve' => self::AUTO_APPROVE_ACF_KEY,
                ]),
                'host_code' => $this->storeAliases($userId, [
                    'jpn_host_code' => $value,
                    '_jpn_host_code' => self::HOST_CODE_ACF_KEY,
                ]),
                default => false,
            };
            $effects[$field] = $stored ? 'applied' : 'failed';
            if (!$stored) {
                $failures[] = $field;
            }
        }

        if ($failures !== []) {
            return new WP_REST_Response([
                'schema_version' => 1,
                'success' => false,
                'result' => count($failures) < count($values) ? 'partial' : 'failed',
                'host' => self::summary($host),
                'effects' => $effects,
                'error' => [
                    'code' => 'host_update_incomplete',
                    'message' => 'One or more host fields could not be verified.',
                    'fields' => $failures,
                ],
            ], 500);
        }

        return new WP_REST_Response([
            'schema_version' => 1,
            'success' => true,
            'result' => 'updated',
            'host' => self::summary($host),
            'effects' => $effects,
            'error' => null,
        ], 200);
    }

    public static function summary(WP_User|int $host): array
    {
        $host = $host instanceof WP_User ? $host : get_userdata($host);
        if (!$host instanceof WP_User) {
            return [];
        }

        $instagram = '';
        foreach (['instagram_handle', 'jpn_instagram_handle', 'instagram_url'] as $key) {
            $instagram = trim((string) get_user_meta($host->ID, $key, true));
            if ($instagram !== '') {
                break;
            }
        }

        return [
            'id' => (int) $host->ID,
            'display_name' => (string) $host->display_name,
            'slug' => (string) $host->user_nicename,
            'area_term_id' => self::normalizeAreaId(get_user_meta($host->ID, 'area', true)) ?: null,
            'auto_approve' => rest_sanitize_boolean(get_user_meta($host->ID, 'jpn_auto_approve', true)),
            'website' => esc_url_raw((string) get_user_meta($host->ID, 'website', true)),
            'instagram_handle' => self::extractInstagramHandle($instagram),
            'host_code_configured' => trim((string) get_user_meta($host->ID, 'jpn_host_code', true)) !== '',
        ];
    }

    private function normalizeInstagram(mixed $value): string|WP_Error
    {
        if (!is_scalar($value) && $value !== null) {
            return new WP_Error('invalid_instagram_handle', 'instagram_handle must be text or null.');
        }
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return '';
        }
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $host = strtolower((string) parse_url($value, PHP_URL_HOST));
            if (!in_array($host, ['instagram.com', 'www.instagram.com'], true)) {
                return new WP_Error('invalid_instagram_handle', 'instagram_handle URL must use instagram.com.');
            }
        }
        $handle = self::extractInstagramHandle($value);
        if ($handle === null || !preg_match('/^[A-Za-z0-9._]{1,30}$/D', $handle)) {
            return new WP_Error('invalid_instagram_handle', 'instagram_handle must contain 1 to 30 letters, numbers, periods, or underscores.');
        }
        return $handle;
    }

    private static function extractInstagramHandle(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $path = trim((string) parse_url($value, PHP_URL_PATH), '/');
            $value = explode('/', $path)[0] ?? '';
        }
        $value = ltrim(trim($value), '@');
        return $value !== '' ? $value : null;
    }

    private static function normalizeAreaId(mixed $value): int
    {
        if (is_array($value)) {
            $value = reset($value);
        }
        if (is_object($value) && isset($value->term_id)) {
            $value = $value->term_id;
        }
        return is_numeric($value) ? (int) $value : 0;
    }

    private function storeInstagram(int $userId, string $handle): bool
    {
        return $this->storeAliases($userId, [
            'instagram_handle' => $handle,
            'jpn_instagram_handle' => $handle,
            'instagram_url' => $handle === '' ? '' : 'https://www.instagram.com/' . $handle . '/',
            '_instagram_url' => self::INSTAGRAM_ACF_KEY,
        ]);
    }

    private function storeAliases(int $userId, array $values): bool
    {
        foreach ($values as $key => $value) {
            if ((string) get_user_meta($userId, (string) $key, true) !== (string) $value) {
                update_user_meta($userId, (string) $key, $value);
            }
            if ((string) get_user_meta($userId, (string) $key, true) !== (string) $value) {
                return false;
            }
        }
        return true;
    }

    private function isBooleanValue(mixed $value): bool
    {
        return is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true);
    }

    private function error(string $code, string $message, int $status): WP_REST_Response
    {
        return new WP_REST_Response([
            'schema_version' => 1,
            'success' => false,
            'result' => 'failed',
            'host' => null,
            'effects' => [],
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
