<?php

namespace Hexa\PluginCore\CoreContracts;

interface PluginContextInterface {
    public function get( string $key, mixed $default = null ): mixed;

    public function all(): array;
}

