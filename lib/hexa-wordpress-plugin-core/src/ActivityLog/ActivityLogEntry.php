<?php

namespace Hexa\PluginCore\ActivityLog;

final class ActivityLogEntry {
    public function __construct(
        public readonly string $message,
        public readonly array $context = [],
        public readonly ?string $actor = null,
        public readonly ?string $source = null,
        public readonly ?string $timestamp = null,
        public readonly string $level = 'info',
        public readonly string $detail = '',
        public readonly ?string $id = null
    ) {
    }

    public function to_array(): array {
        $context_json = function_exists( 'wp_json_encode' )
            ? wp_json_encode( $this->context )
            : json_encode( $this->context );

        return [
            'id'        => $this->id ?: md5( $this->message . (string) $context_json . ( $this->timestamp ?: '' ) ),
            'level'     => $this->level,
            'message'   => $this->message,
            'detail'    => $this->detail,
            'context'   => $this->context,
            'actor'     => $this->actor,
            'source'    => $this->source,
            'timestamp' => $this->timestamp ?: gmdate( 'c' ),
        ];
    }
}
