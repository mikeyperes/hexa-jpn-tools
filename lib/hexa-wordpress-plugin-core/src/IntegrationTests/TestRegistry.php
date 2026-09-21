<?php

namespace Hexa\PluginCore\IntegrationTests;

final class TestRegistry {
    /** @var array<string,TestDefinition> */
    private array $definitions = [];

    public function add( TestDefinition $definition ): self {
        $this->definitions[ $definition->id() ] = $definition;
        return $this;
    }

    public function register( string $id, string $title, callable $callback, array $args = [] ): self {
        return $this->add( new TestDefinition( $id, $title, $callback, $args ) );
    }

    /** @return array<string,TestDefinition> */
    public function all(): array {
        return $this->definitions;
    }
}
