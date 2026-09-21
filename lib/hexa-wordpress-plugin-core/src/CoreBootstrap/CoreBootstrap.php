<?php

namespace Hexa\PluginCore\CoreBootstrap;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\CoreContracts\PluginContextInterface;
use Hexa\PluginCore\CorePackageUpdates\CorePackageFleetSyncModule;
use Hexa\PluginCore\IntegrationTests\IntegrationTestRuntime;
use Hexa\PluginCore\QuerySafety\StaticFrontPageQueryGuard;

final class CoreBootstrap {
    private PluginContextInterface $context;

    /**
     * @var ModuleInterface[]
     */
    private array $modules = [];

    private bool $booted = false;

    public function __construct( PluginContextInterface $context ) {
        $this->context = $context;
    }

    public function context(): PluginContextInterface {
        return $this->context;
    }

    public function add_module( ModuleInterface $module ): self {
        $this->modules[] = $module;

        return $this;
    }

    public function boot(): void {
        if ( $this->booted ) {
            return;
        }

        ( new StaticFrontPageQueryGuard() )->register();
        // Some standalone consumers require CoreBootstrap directly instead of
        // loading the package registry/autoloader. Keep that supported while
        // automatically enabling fleet synchronization in normal host boots.
        if ( class_exists( CorePackageFleetSyncModule::class ) ) {
            ( new CorePackageFleetSyncModule() )->register();
        }
        IntegrationTestRuntime::register_host( $this->context );

        foreach ( $this->modules as $module ) {
            $module->register();
        }

        $this->booted = true;
    }
}
