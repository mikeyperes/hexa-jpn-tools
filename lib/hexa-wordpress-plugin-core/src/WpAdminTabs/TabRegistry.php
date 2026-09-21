<?php

namespace Hexa\PluginCore\WpAdminTabs;

final class TabRegistry {
    /**
     * @var array<string, TabDefinition>
     */
    private array $tabs = [];

    public function add( TabDefinition $tab ): self {
        $this->tabs[ $tab->id ] = $tab;

        return $this;
    }

    public function get( string $id ): ?TabDefinition {
        return $this->tabs[ $id ] ?? null;
    }

    /**
     * @return array<string, TabDefinition>
     */
    public function all(): array {
        return $this->tabs;
    }
}

