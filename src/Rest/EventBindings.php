<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Rest;

use RuntimeException;

final class EventBindings
{
    public function tableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'hexa_jpn_event_bindings';
    }

    public function schemaReady(): bool
    {
        global $wpdb;
        $table = $this->tableName();
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
    }

    public function find(string $externalRef): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . $this->tableName() . ' WHERE external_ref_hash = %s', self::hash($externalRef)),
            ARRAY_A
        );
        if (!is_array($row)) {
            return null;
        }
        if (!hash_equals((string) $row['external_ref'], $externalRef)) {
            throw new RuntimeException('External reference hash collision.');
        }
        $row['outcome_data'] = $this->decodeOutcome((string) ($row['outcome'] ?? ''));
        return $row;
    }

    public function findByPostId(int $postId): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . $this->tableName() . ' WHERE post_id = %d LIMIT 1', $postId),
            ARRAY_A
        );
        if (!is_array($row)) {
            return null;
        }
        $row['outcome_data'] = $this->decodeOutcome((string) ($row['outcome'] ?? ''));
        return $row;
    }

    public function findByOperationId(string $operationId): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . $this->tableName() . ' WHERE operation_id = %s ORDER BY updated_at DESC LIMIT 1',
                $operationId
            ),
            ARRAY_A
        );
        if (!is_array($row)) {
            return null;
        }
        $row['outcome_data'] = $this->decodeOutcome((string) ($row['outcome'] ?? ''));
        return $row;
    }

    public function withLock(string $externalRef, callable $callback): mixed
    {
        global $wpdb;
        $lockName = 'hexa_jpn_evt_' . substr(self::hash($externalRef), 0, 40);
        $acquired = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lockName, 5));
        if ($acquired !== 1) {
            throw new RuntimeException('The event is busy. Try again after reading its current status.');
        }

        try {
            return $callback();
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lockName));
        }
    }

    public function reserve(string $externalRef, ?int $postId, string $operationId, string $digest): bool
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $inserted = $wpdb->insert(
            $this->tableName(),
            [
                'external_ref_hash' => self::hash($externalRef),
                'external_ref' => $externalRef,
                'post_id' => $postId,
                'operation_id' => $operationId,
                'request_digest' => $digest,
                'result' => 'processing',
                'outcome' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        return $inserted === 1;
    }

    public function beginOperation(string $externalRef, string $operationId, string $digest): bool
    {
        global $wpdb;
        return $wpdb->update(
            $this->tableName(),
            [
                'operation_id' => $operationId,
                'request_digest' => $digest,
                'result' => 'processing',
                'outcome' => null,
                'updated_at' => current_time('mysql', true),
            ],
            ['external_ref_hash' => self::hash($externalRef)],
            ['%s', '%s', '%s', '%s', '%s'],
            ['%s']
        ) !== false;
    }

    public function attachPost(string $externalRef, int $postId): bool
    {
        global $wpdb;
        return $wpdb->update(
            $this->tableName(),
            ['post_id' => $postId, 'updated_at' => current_time('mysql', true)],
            ['external_ref_hash' => self::hash($externalRef)],
            ['%d', '%s'],
            ['%s']
        ) !== false;
    }

    public function saveOutcome(string $externalRef, array $outcome): bool
    {
        global $wpdb;
        $encoded = wp_json_encode($outcome, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            return false;
        }
        return $wpdb->update(
            $this->tableName(),
            [
                'result' => (string) ($outcome['result'] ?? 'failed'),
                'outcome' => $encoded,
                'updated_at' => current_time('mysql', true),
            ],
            ['external_ref_hash' => self::hash($externalRef)],
            ['%s', '%s', '%s'],
            ['%s']
        ) !== false;
    }

    public function delete(string $externalRef): void
    {
        global $wpdb;
        $wpdb->delete($this->tableName(), ['external_ref_hash' => self::hash($externalRef)], ['%s']);
    }

    public static function transition(?array $binding, string $operationId, string $digest, ?string $expectedOperationId): array
    {
        $expectedOperationId = $expectedOperationId !== null ? trim($expectedOperationId) : null;
        if ($binding === null) {
            return $expectedOperationId === null || $expectedOperationId === ''
                ? ['action' => 'proceed']
                : ['action' => 'conflict', 'code' => 'stale_operation', 'message' => 'No prior operation exists for the supplied precondition.'];
        }

        $lastOperation = (string) ($binding['operation_id'] ?? '');
        $lastDigest = (string) ($binding['request_digest'] ?? '');
        if (hash_equals($lastOperation, $operationId)) {
            if (!hash_equals($lastDigest, $digest)) {
                return ['action' => 'conflict', 'code' => 'operation_payload_conflict', 'message' => 'The operation ID was already used with a different payload.'];
            }
            return ['action' => 'replay'];
        }

        if ($expectedOperationId === null || !hash_equals($lastOperation, $expectedOperationId)) {
            return ['action' => 'conflict', 'code' => 'stale_operation', 'message' => 'Read the latest event receipt before submitting another operation.'];
        }

        return ['action' => 'proceed'];
    }

    public static function hash(string $externalRef): string
    {
        return hash('sha256', $externalRef);
    }

    private function decodeOutcome(string $outcome): ?array
    {
        if ($outcome === '') {
            return null;
        }
        $decoded = json_decode($outcome, true);
        return is_array($decoded) ? $decoded : null;
    }
}
