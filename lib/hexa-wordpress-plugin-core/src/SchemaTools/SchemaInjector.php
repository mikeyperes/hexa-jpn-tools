<?php

namespace Hexa\PluginCore\SchemaTools;

use Hexa\PluginCore\CoreContracts\ModuleInterface;

final class SchemaInjector implements ModuleInterface {
    private $provider;
    private $should_output;
    private array $config;
    private bool $rendered = false;

    public function __construct( callable $provider, array $config = [] ) {
        $this->provider = $provider;
        $this->should_output = isset( $config['should_output'] ) && is_callable( $config['should_output'] ) ? $config['should_output'] : null;
        $this->config = array_merge( [ 'hook' => 'wp_head', 'priority' => 30, 'script_id' => 'hexa-schema', 'class' => '' ], $config );
    }

    public function register(): void {
        add_action( (string) $this->config['hook'], [ $this, 'output' ], (int) $this->config['priority'] );
    }

    public function output(): void {
        if ( $this->rendered || ( $this->should_output && ! call_user_func( $this->should_output ) ) ) {
            return;
        }
        $schema = call_user_func( $this->provider );
        if ( ! is_array( $schema ) || empty( $schema ) ) {
            return;
        }
        $this->rendered = true;
        echo "\n" . ( new SchemaDocumentRenderer() )->script( $schema, (string) $this->config['script_id'], (string) $this->config['class'] ) . "\n";
    }
}
