<?php

namespace Hexa\PluginCore\ActivityLog;

final class ActivityLogger {
    /**
     * @var ActivityLogEntry[]
     */
    private array $entries = [];

    private ?ActivityLogConfig $config;

    public function __construct( ?ActivityLogConfig $config = null ) {
        $this->config = $config;

        if ( null !== $config ) {
            $this->entries = $this->load();
        }
    }

    public function add( ActivityLogEntry $entry ): void {
        $this->entries[] = $entry;
        $this->entries   = array_slice( $this->entries, -1 * $this->max_entries() );

        $this->persist();
    }

    /**
     * @return ActivityLogEntry[]
     */
    public function all(): array {
        return $this->entries;
    }

    public function clear(): void {
        $this->entries = [];

        if ( null === $this->config || ! function_exists( 'delete_option' ) ) {
            return;
        }

        if ( $this->config->is_permanent() ) {
            delete_option( $this->config->storage_key() );
        } elseif ( $this->config->is_transient() ) {
            delete_transient( $this->config->storage_key() );
        }
    }

    /**
     * @return ActivityLogEntry[]
     */
    private function load(): array {
        if ( null === $this->config || $this->config->is_page() ) {
            return [];
        }

        if ( ! function_exists( 'get_option' ) ) {
            return [];
        }

        $raw = $this->config->is_permanent()
            ? get_option( $this->config->storage_key(), [] )
            : get_transient( $this->config->storage_key() );

        if ( ! is_array( $raw ) ) {
            return [];
        }

        $entries = [];
        foreach ( $raw as $entry ) {
            if ( ! is_array( $entry ) || empty( $entry['message'] ) ) {
                continue;
            }

            $entries[] = new ActivityLogEntry(
                (string) $entry['message'],
                is_array( $entry['context'] ?? null ) ? $entry['context'] : [],
                isset( $entry['actor'] ) ? (string) $entry['actor'] : null,
                isset( $entry['source'] ) ? (string) $entry['source'] : null,
                isset( $entry['timestamp'] ) ? (string) $entry['timestamp'] : null,
                isset( $entry['level'] ) ? (string) $entry['level'] : 'info',
                isset( $entry['detail'] ) ? (string) $entry['detail'] : '',
                isset( $entry['id'] ) ? (string) $entry['id'] : null
            );
        }

        return array_slice( $entries, -1 * $this->max_entries() );
    }

    private function persist(): void {
        if ( null === $this->config || $this->config->is_page() || ! function_exists( 'update_option' ) ) {
            return;
        }

        $data = array_map(
            static fn( ActivityLogEntry $entry ): array => $entry->to_array(),
            $this->entries
        );

        if ( $this->config->is_permanent() ) {
            update_option( $this->config->storage_key(), $data, false );
            return;
        }

        set_transient( $this->config->storage_key(), $data, $this->config->transient_ttl() );
    }

    private function max_entries(): int {
        return null !== $this->config ? $this->config->max_entries() : 200;
    }
}
