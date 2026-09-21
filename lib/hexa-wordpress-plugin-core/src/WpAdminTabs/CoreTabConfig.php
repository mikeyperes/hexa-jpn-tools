<?php

namespace Hexa\PluginCore\WpAdminTabs;

use Hexa\PluginCore\CoreRuntime\CoreVersion;

final class CoreTabConfig {
    private array $values;

    public function __construct( array $values = [] ) {
        $defaults = [
            'tab_id'        => 'hexa-core',
            'label'         => 'Hexa WordPress Plugin Core',
            'tabs_filter'   => '',
            'render_filter' => '',
            'capability'    => 'manage_options',
            'core_root'     => CoreVersion::root_path(),
            'readme_path'   => CoreVersion::root_path() . '/README.md',
            'library_path'  => CoreVersion::root_path() . '/HEXA_PLUGIN_CORE_LIBRARY.md',
        ];

        $values = array_merge( $defaults, $values );

        $values['tab_id'] = function_exists( 'sanitize_key' )
            ? sanitize_key( (string) $values['tab_id'] )
            : preg_replace( '/[^a-z0-9_\\-]/', '', strtolower( (string) $values['tab_id'] ) );

        $this->values = $values;
    }

    public function get( string $key, mixed $default = null ): mixed {
        return array_key_exists( $key, $this->values ) ? $this->values[ $key ] : $default;
    }

    public function tab_id(): string {
        return (string) $this->get( 'tab_id' );
    }

    public function label(): string {
        return (string) $this->get( 'label' );
    }

    public function tabs_filter(): string {
        return (string) $this->get( 'tabs_filter' );
    }

    public function render_filter(): string {
        return (string) $this->get( 'render_filter' );
    }

    public function capability(): string {
        return (string) $this->get( 'capability' );
    }

    public function core_root(): string {
        return (string) $this->get( 'core_root' );
    }

    public function readme_path(): string {
        return (string) $this->get( 'readme_path' );
    }

    public function library_path(): string {
        return (string) $this->get( 'library_path' );
    }
}
