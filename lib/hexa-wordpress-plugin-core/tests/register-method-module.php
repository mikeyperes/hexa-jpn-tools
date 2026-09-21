<?php

declare( strict_types=1 );

$root = dirname( __DIR__ );
require_once $root . '/src/CoreContracts/ModuleInterface.php';
require_once $root . '/src/CoreContracts/RegisterMethodModule.php';

use Hexa\PluginCore\CoreContracts\RegisterMethodModule;

$legacy = new class() {
    public int $calls = 0;

    public function register(): void {
        $this->calls++;
    }
};

( new RegisterMethodModule( $legacy ) )->register();
if ( 1 !== $legacy->calls ) {
    fwrite( STDERR, "FAIL: RegisterMethodModule did not delegate exactly once.\n" );
    exit( 1 );
}

try {
    new RegisterMethodModule( new stdClass() );
    fwrite( STDERR, "FAIL: RegisterMethodModule accepted an invalid object.\n" );
    exit( 1 );
} catch ( InvalidArgumentException $exception ) {
    // Expected.
}

echo "PASS: RegisterMethodModule centralizes legacy register() adapters.\n";
