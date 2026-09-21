<?php

namespace Hexa\PluginCore\ShortcodeRegistry;

final class ShortcodeRegistry {
    /**
     * @var array<string, ShortcodeDefinition>
     */
    private array $definitions = [];

    public function add( ShortcodeDefinition $definition ): self {
        $this->definitions[ $definition->id ] = $definition;

        return $this;
    }

    public function get( string $id ): ?ShortcodeDefinition {
        return $this->definitions[ $id ] ?? null;
    }

    /**
     * @return array<string, ShortcodeDefinition>
     */
    public function all(): array {
        return $this->definitions;
    }
}

