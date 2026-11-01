<?php

declare( strict_types=1 );

namespace Hexa\PluginCore\ApiKeys;

/** One API key a plugin stores: where it is kept, how it is shown, how to get it and how it is tested. */
final class ApiKeyField {
    /**
     * @param string[] $steps Setup steps shown under the field, in order (HTML allowed).
     */
    public function __construct(
        public readonly string $id,
        public readonly string $slug,
        public readonly string $key_name,
        public readonly string $label,
        public readonly ?ApiKeyVerifier $verifier = null,
        public readonly array $steps = [],
        public readonly string $placeholder = '',
        public readonly string $help_url = '',
        public readonly string $help_label = 'Open the service',
    ) {}
}
