<?php

declare(strict_types=1);

$tests = glob( __DIR__ . '/*.php' ) ?: [];
$tests = array_values( array_filter( $tests, static fn( string $path ): bool => basename( $path ) !== basename( __FILE__ ) ) );
sort( $tests, SORT_STRING );

$passed = 0;
foreach ( $tests as $test ) {
    $command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $test );
    passthru( $command, $status );
    if ( 0 !== $status ) {
        fwrite( STDERR, 'FAIL: ' . basename( $test ) . " exited with status {$status}.\n" );
        exit( $status );
    }
    $passed++;
}

echo "Core suite passed: {$passed} test files.\n";
