<?php

namespace Hexa\PluginCore\EntitySources;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\SmartSearch\SmartSearchAjaxController;

final class PrimaryEntityModule implements ModuleInterface {
    private PrimaryEntityManager $manager;

    public function __construct( PrimaryEntityManager $manager ) {
        $this->manager = $manager;
    }

    public function register(): void {
        add_action( 'admin_init', [ $this->manager, 'migrate_legacy' ], 5 );
        ( new SmartSearchAjaxController() )->register();
        ( new PrimaryEntityAjaxController( $this->manager ) )->register();
    }
}
