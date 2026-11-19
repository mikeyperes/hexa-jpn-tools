<?php
require __DIR__ . '/../src/ApiKeys/HmacSignature.php';
require __DIR__ . '/../src/ApiKeys/SignedJsonClient.php';

use Hexa\PluginCore\ApiKeys\HmacSignature;
use Hexa\PluginCore\ApiKeys\SignedJsonClient;

class WP_Error {
    public string $code;
    public function __construct( string $code, string $message ) { $this->code = $code; }
}
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_remote_post( $url, $args ) {
    $GLOBALS['sent'][] = [ $url, $args ];
    return [ 'response' => [ 'code' => 200 ] ];
}
$checks = 0;
function check( bool $condition, string $message ): void {
    global $checks;
    if ( ! $condition ) { throw new RuntimeException( $message ); }
    ++$checks;
}
$parts = [ 'POST', '/v1/example', '1700000000', str_repeat( 'a', 64 ), 'example.test', '{"text":"hello"}', 'fixture-key' ];
$signature = HmacSignature::sign( ...$parts );
check( '0542f4027c31d64530f7eacd4985f04e3595dc63887ce061df1ed08dabf21776' === $signature, 'Cross-language fixed signature vector' );
foreach ( array_keys( $parts ) as $index ) {
    $changed = $parts;
    $changed[ $index ] .= 'x';
    check( HmacSignature::sign( ...$changed ) !== $signature, 'Every signed component and key changes the signature' );
}
$client = new SignedJsonClient( 'X-Fixture-' );
$client->post( 'https://api.example.test/v1/example', [], 'fixture-key', 'example.test', 12 );
$client->post( 'https://api.example.test/v1/example', [ 'text' => 'hello' ], 'fixture-key', 'example.test', 12 );
$client->post( 'https://api.example.test/v1/example', [], 'fixture-key', 'https://WWW.Example.Test./', 12 );
check( 'example.test' === $GLOBALS['sent'][2][1]['headers']['X-Fixture-Domain'], 'Calling URL is normalized once in Core' );
check( $client->post( 'https://api.example.test/v1/example', [], 'fixture-key', '127.0.0.1', 12 ) instanceof WP_Error, 'IP identity rejected' );
check( $client->post( 'https://api.example.test/v1/example', [], 'fixture-key', 'invalid host', 12 ) instanceof WP_Error, 'Malformed identity rejected' );
$first = $GLOBALS['sent'][0][1];
$second = $GLOBALS['sent'][1][1];
check( '{}' === $first['body'], 'Empty payload is a JSON object' );
check( '{"text":"hello"}' === $second['body'], 'Exact JSON bytes are sent' );
check( 0 === $first['redirection'] && true === $first['sslverify'] && [] === $first['cookies'], 'No redirects or cookies and TLS verification enabled' );
check( 12 === $first['timeout'], 'Caller owns timeout' );
$headers = $first['headers'];
check( 'fixture-key' === $headers['X-Fixture-Key'] && 'example.test' === $headers['X-Fixture-Domain'], 'Calling identity headers' );
check( 1 === preg_match( '/^[a-f0-9]{64}$/D', $headers['X-Fixture-Nonce'] ), 'Cryptographic nonce shape' );
check( $headers['X-Fixture-Nonce'] !== $second['headers']['X-Fixture-Nonce'], 'Every send uses a fresh nonce' );
check( abs( time() - (int) $headers['X-Fixture-Timestamp'] ) < 2, 'Current timestamp' );
check( HmacSignature::sign( 'POST', '/v1/example', $headers['X-Fixture-Timestamp'], $headers['X-Fixture-Nonce'], 'example.test', $first['body'], 'fixture-key' ) === $headers['X-Fixture-Signature'], 'Transport signs the exact body and path' );
foreach ( [ 'http://api.example.test/v1/example', 'https://api.example.test/v1/example?x=1', 'https://api.example.test/v1/example#x', 'https://user:pass@api.example.test/v1/example' ] as $url ) {
    check( $client->post( $url, [], 'fixture-key', 'example.test', 12 ) instanceof WP_Error, 'Unsafe address rejected' );
}
check( $client->post( 'https://api.example.test/v1/example', [], '', 'example.test', 12 ) instanceof WP_Error, 'Missing key rejected' );
check( $client->post( 'https://api.example.test/v1/example', [], 'fixture-key', '', 12 ) instanceof WP_Error, 'Missing domain rejected' );
check( $client->post( 'https://api.example.test/v1/example', [ 'value' => INF ], 'fixture-key', 'example.test', 12 ) instanceof WP_Error, 'Unencodable JSON rejected' );
check( 3 === count( $GLOBALS['sent'] ), 'Rejected calls never reach transport' );
echo "Signed JSON: $checks checks passed\n";
