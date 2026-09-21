<?php

namespace Hexa\PluginCore\CoreRuntime;

use Hexa\PluginCore\CoreContracts\PluginContextInterface;
use InvalidArgumentException;

final class PluginContext implements PluginContextInterface {
    private const REQUIRED_KEYS = [
        'slug',
        'basename',
        'version',
        'path',
        'url',
        'github_repo',
        'admin_page',
        'capability',
    ];

    private array $values;

    public function __construct( array $values ) {
        foreach ( self::REQUIRED_KEYS as $key ) {
            if ( ! array_key_exists( $key, $values ) || $values[ $key ] === '' || $values[ $key ] === null ) {
                throw new InvalidArgumentException( "Missing plugin context key: {$key}" );
            }
        }

        $this->values = $values;
    }

    public function get( string $key, mixed $default = null ): mixed {
        return array_key_exists( $key, $this->values ) ? $this->values[ $key ] : $default;
    }

    public function all(): array {
        return $this->values;
    }
}

