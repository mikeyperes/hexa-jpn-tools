<?php

declare(strict_types=1);

require dirname( __DIR__ ) . '/src/WpConfigFile/WpConfigFile.php';

use Hexa\PluginCore\WpConfigFile\WpConfigFile;

$fail = static function ( string $message ): void {
    fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
    exit( 1 );
};

$path = tempnam( __DIR__, '.hexa-wp-config-' );
if ( false === $path ) {
    $fail( 'Could not create the inert wp-config fixture.' );
}

$backup = $path . '.last-backup';
$fixture = "<?php\n"
    . str_repeat( "// inert wp-config fixture padding\n", 20 )
    . "define( 'DB_NAME', 'fixture' );\n";

file_put_contents( $path, $fixture );

$payload = chr( 92 ) . chr( 39 ) . "); \$GLOBALS[chr(104).chr(101).chr(120).chr(97)] = true; //";
$result  = WpConfigFile::modify_constants(
    [
        'ini_display_errors'     => [
            'type'  => 'ini',
            'value' => $payload,
        ],
        'HEXA_LITERAL_FIXTURE'   => $payload,
        'HEXA_NUMERIC_FIXTURE'   => '123',
        'HEXA_BOOLEAN_FIXTURE'   => 'false',
    ],
    $path,
    [
        'minimum_bytes'         => 1,
        'permanent_backup_path' => $backup,
    ]
);

if ( empty( $result['status'] ) ) {
    @unlink( $path );
    @unlink( $backup );
    $fail( 'WpConfigFile rejected the inert fixture.' );
}

$content = (string) file_get_contents( $path );
$expected_ini = 'ini_set( ' . var_export( 'display_errors', true ) . ', ' . var_export( $payload, true ) . ' );';
$expected_define = 'define( ' . var_export( 'HEXA_LITERAL_FIXTURE', true ) . ', ' . var_export( $payload, true ) . ' );';

str_contains( $content, $expected_ini ) || $fail( 'INI values are not serialized as safe PHP string literals.' );
str_contains( $content, $expected_define ) || $fail( 'String constants are not serialized as safe PHP string literals.' );
str_contains( $content, "define( 'HEXA_NUMERIC_FIXTURE', 123 );" ) || $fail( 'Numeric-string constants no longer preserve numeric semantics.' );
str_contains( $content, "define( 'HEXA_BOOLEAN_FIXTURE', false );" ) || $fail( 'Boolean-string constants no longer preserve boolean semantics.' );

$lint_command = escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $path );
exec( $lint_command, $lint_output, $lint_status );
0 === $lint_status || $fail( 'The generated wp-config fixture is not valid PHP.' );

unset( $GLOBALS['hexa'] );
require $path;
empty( $GLOBALS['hexa'] ) || $fail( 'A serialized value escaped its PHP literal and executed the inert marker.' );

@unlink( $path );
@unlink( $backup );

echo "PASS: WpConfigFile safely serializes PHP literals while preserving scalar semantics.\n";
