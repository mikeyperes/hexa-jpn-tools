<?php

declare(strict_types=1);

set_error_handler( static function ( int $severity, string $message, string $file, int $line ): bool {
    throw new ErrorException( $message, 0, $severity, $file, $line );
} );

function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

require dirname( __DIR__ ) . '/src/PublicComponents/RelativeTime.php';

use Hexa\PluginCore\PublicComponents\RelativeTime;

$assertions = 0;
$expect = static function ( bool $condition, string $message ) use ( &$assertions ): void {
    $assertions++;
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$now = 1790000000;
$expect( 'just now' === RelativeTime::text( $now - 30, $now ), 'Under a minute reads "just now".' );
$expect( '1 min ago' === RelativeTime::text( $now - 60, $now ) && '59 min ago' === RelativeTime::text( $now - 3599, $now ), 'Minutes.' );
$expect( '1 hour ago' === RelativeTime::text( $now - 3600, $now ) && '23 hours ago' === RelativeTime::text( $now - 86399, $now ), 'Hours, singular and plural.' );
$expect( '1 day ago' === RelativeTime::text( $now - 86400, $now ) && '12 days ago' === RelativeTime::text( $now - 12 * 86400, $now ), 'Days.' );
$expect( 'just now' === RelativeTime::text( $now + 500, $now ), 'A future timestamp never reads negative.' );
$first = RelativeTime::html( $now - 7200, 'x', $now );
$expect( str_contains( $first, 'datetime="' . gmdate( 'c', $now - 7200 ) . '"' ) && str_contains( $first, '>2 hours ago</time>' ) && str_contains( $first, 'class="hexa-reltime x"' ), 'The element carries the ISO time, class, and server text.' );
$expect( str_contains( $first, 'id="hexa-reltime-js"' ) && ! str_contains( RelativeTime::html( $now, '', $now ), 'hexa-reltime-js' ), 'The refresh script prints once per page.' );
$expect( '' === RelativeTime::html( 0 ), 'No timestamp renders nothing.' );

echo "PASS: relative time ({$assertions} assertions).\n";
