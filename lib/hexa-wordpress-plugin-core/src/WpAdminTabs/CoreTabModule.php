<?php

namespace Hexa\PluginCore\WpAdminTabs;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\SmartSearch\SmartSearchAjaxController;

final class CoreTabModule implements ModuleInterface {
    private CoreTabConfig $config;

    public function __construct( CoreTabConfig $config ) {
        $this->config = $config;
    }

    public function register(): void {
        ( new SmartSearchAjaxController() )->register();

        if ( '' !== $this->config->tabs_filter() ) {
            add_filter( $this->config->tabs_filter(), [ $this, 'register_tab' ] );
        }

        if ( '' !== $this->config->render_filter() ) {
            add_filter( $this->config->render_filter(), [ $this, 'render_tab' ], 10, 2 );
        }
    }

    public function register_tab( array $tabs ): array {
        $tabs[ $this->config->tab_id() ] = $this->config->label();

        return $tabs;
    }

    public function render_tab( bool $rendered, string $tab_id ): bool {
        if ( $rendered || $tab_id !== $this->config->tab_id() ) {
            return $rendered;
        }

        if ( function_exists( 'current_user_can' ) && ! current_user_can( $this->config->capability() ) ) {
            echo '<div class="notice notice-error"><p>You do not have permission to view the Hexa core tab.</p></div>';
            return true;
        }

        ( new CoreTabRenderer( $this->config ) )->render();

        return true;
    }
}
