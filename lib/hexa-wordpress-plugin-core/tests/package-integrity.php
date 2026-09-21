<?php

declare(strict_types=1);

$root = dirname( __DIR__ );
require $root . '/bootstrap.php';

$declared = trim( (string) file_get_contents( $root . '/PACKAGE_HASH' ) );
$actual   = HexaPluginCorePackageRegistry::source_hash( $root );

if ( '' === $declared || ! hash_equals( $declared, $actual ) ) {
    fwrite( STDERR, "FAIL: PACKAGE_HASH does not match executable Core source.\n" );
    exit( 1 );
}

echo "PASS: PACKAGE_HASH matches executable Core source.\n";
