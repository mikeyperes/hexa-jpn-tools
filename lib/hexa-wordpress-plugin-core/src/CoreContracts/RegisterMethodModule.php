<?php

declare( strict_types=1 );

namespace Hexa\PluginCore\CoreContracts;

/** Adapts a legacy object with a public register() method to ModuleInterface. */
final class RegisterMethodModule implements ModuleInterface {
    public function __construct( private readonly object $module ) {
        if ( ! is_callable( [ $module, 'register' ] ) ) {
            throw new \InvalidArgumentException( 'Wrapped modules must expose a callable register() method.' );
        }
    }

    public function register(): void {
        $this->module->register();
    }
}
