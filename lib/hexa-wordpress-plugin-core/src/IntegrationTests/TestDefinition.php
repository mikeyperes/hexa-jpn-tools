<?php

namespace Hexa\PluginCore\IntegrationTests;

use InvalidArgumentException;

final class TestDefinition {
    private string $id;
    private string $title;
    private string $group;
    private string $description;
    private string $host;
    private bool $critical;
    private $callback;

    public function __construct( string $id, string $title, callable $callback, array $args = [] ) {
        $id = self::normalize_id( $id );
        if ( '' === $id || '' === trim( $title ) ) {
            throw new InvalidArgumentException( 'Integration tests require a stable ID and title.' );
        }

        $this->id          = $id;
        $this->title       = trim( $title );
        $this->callback    = $callback;
        $this->group       = trim( (string) ( $args['group'] ?? 'General' ) ) ?: 'General';
        $this->description = trim( (string) ( $args['description'] ?? '' ) );
        $this->host        = self::normalize_id( (string) ( $args['host'] ?? 'core' ) ) ?: 'core';
        $this->critical    = ! array_key_exists( 'critical', $args ) || (bool) $args['critical'];
    }

    public function id(): string {
        return $this->id;
    }

    public function title(): string {
        return $this->title;
    }

    public function group(): string {
        return $this->group;
    }

    public function description(): string {
        return $this->description;
    }

    public function host(): string {
        return $this->host;
    }

    public function critical(): bool {
        return $this->critical;
    }

    public function execute(): mixed {
        return call_user_func( $this->callback, $this );
    }

    private static function normalize_id( string $value ): string {
        $value = strtolower( trim( $value ) );
        return trim( (string) preg_replace( '/[^a-z0-9._-]+/', '-', $value ), '-' );
    }
}
