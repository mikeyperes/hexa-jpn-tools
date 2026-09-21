<?php

namespace Hexa\PluginCore\ActivityLog;

final class ActivityLogConfig {
    public const STORAGE_TRANSIENT = 'transient';
    public const STORAGE_PERMANENT = 'permanent';
    public const STORAGE_PAGE = 'page';

    private array $values;

    public function __construct( array $values = [] ) {
        $defaults = [
            'id'            => 'hexa-core-activity-log',
            'title'         => 'Activity Log',
            'storage'       => self::STORAGE_PAGE,
            'storage_key'   => 'hexa_core_activity_log',
            'max_entries'   => 200,
            'transient_ttl' => 3600,
            'collapsed'     => true,
            'dark'          => true,
        ];

        $values = array_merge( $defaults, $values );

        if ( ! in_array( $values['storage'], [ self::STORAGE_TRANSIENT, self::STORAGE_PERMANENT, self::STORAGE_PAGE ], true ) ) {
            $values['storage'] = self::STORAGE_PAGE;
        }

        $values['id']            = $this->key( (string) $values['id'] );
        $values['storage_key']   = $this->key( (string) $values['storage_key'] );
        $values['max_entries']   = max( 1, (int) $values['max_entries'] );
        $values['transient_ttl'] = max( 60, (int) $values['transient_ttl'] );

        $this->values = $values;
    }

    public function get( string $key, mixed $default = null ): mixed {
        return array_key_exists( $key, $this->values ) ? $this->values[ $key ] : $default;
    }

    public function id(): string {
        return (string) $this->get( 'id' );
    }

    public function title(): string {
        return (string) $this->get( 'title' );
    }

    public function storage(): string {
        return (string) $this->get( 'storage' );
    }

    public function storage_label(): string {
        return match ( $this->storage() ) {
            self::STORAGE_TRANSIENT => 'Transient',
            self::STORAGE_PERMANENT => 'Permanent',
            default => 'Page only',
        };
    }

    public function storage_key(): string {
        return (string) $this->get( 'storage_key' );
    }

    public function max_entries(): int {
        return (int) $this->get( 'max_entries' );
    }

    public function transient_ttl(): int {
        return (int) $this->get( 'transient_ttl' );
    }

    public function collapsed(): bool {
        return (bool) $this->get( 'collapsed' );
    }

    public function dark(): bool {
        return (bool) $this->get( 'dark' );
    }

    public function is_transient(): bool {
        return self::STORAGE_TRANSIENT === $this->storage();
    }

    public function is_permanent(): bool {
        return self::STORAGE_PERMANENT === $this->storage();
    }

    public function is_page(): bool {
        return self::STORAGE_PAGE === $this->storage();
    }

    private function key( string $value ): string {
        if ( function_exists( 'sanitize_key' ) ) {
            return sanitize_key( $value );
        }

        $value = strtolower( $value );

        return preg_replace( '/[^a-z0-9_\\-]/', '', $value ) ?: 'hexa-core-activity-log';
    }
}
