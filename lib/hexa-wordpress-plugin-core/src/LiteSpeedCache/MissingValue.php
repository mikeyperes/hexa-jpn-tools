<?php

namespace Hexa\PluginCore\LiteSpeedCache;

use JsonSerializable;

/** Unique marker returned when a declared option or nested path does not exist. */
final class MissingValue implements JsonSerializable {
    private static ?self $instance = null;

    private function __construct() {}

    public static function instance(): self {
        return self::$instance ??= new self();
    }

    public function jsonSerialize(): string {
        return '[missing]';
    }
}
