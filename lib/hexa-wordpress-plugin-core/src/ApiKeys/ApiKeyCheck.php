<?php

declare( strict_types=1 );

namespace Hexa\PluginCore\ApiKeys;

/** The result of testing one API key: active, invalid, missing or error, with a plain message. */
final class ApiKeyCheck {
    public const ACTIVE  = 'active';
    public const INVALID = 'invalid';
    public const MISSING = 'missing';
    public const ERROR   = 'error';

    /** @param array<string,string> $details */
    private function __construct( public readonly string $status, public readonly string $message, public readonly array $details = [] ) {}

    /** @param array<string,string> $details */
    public static function active( string $message, array $details = [] ): self {
        return new self( self::ACTIVE, $message, $details );
    }

    public static function invalid( string $message ): self {
        return new self( self::INVALID, $message );
    }

    public static function missing( string $message = 'No key is saved yet.' ): self {
        return new self( self::MISSING, $message );
    }

    public static function error( string $message ): self {
        return new self( self::ERROR, $message );
    }

    public function ok(): bool {
        return self::ACTIVE === $this->status;
    }

    /** @return array<string,mixed> */
    public function to_array(): array {
        return [ 'status' => $this->status, 'ok' => $this->ok(), 'message' => $this->message, 'details' => $this->details ];
    }
}
