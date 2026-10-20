<?php

declare(strict_types=1);

$root = dirname( __DIR__ );
require $root . '/src/SearchQuery/SearchIndex.php';

use Hexa\PluginCore\SearchQuery\SearchIndex;

function assert_same( mixed $expected, mixed $actual, string $message ): void {
    if ( $expected !== $actual ) {
        fwrite( STDERR, $message . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . "\n" );
        exit( 1 );
    }
}

$all = SearchIndex::boolean_expression( [ 'telecom', '5G', 'new york', 'o\'brien' ], 'all', 'prefix', 3 );
assert_same( '+telecom* +"new york" +brien*', $all['expression'], 'Indexable words become required prefixes and phrases' );
assert_same( [ '5G', 'o\'brien' ], $all['remaining'], 'Words below the token size fall back to the scan' );

$any = SearchIndex::boolean_expression( [ 'utah', 'clinic' ], 'any', 'whole', 3 );
assert_same( 'utah clinic', $any['expression'], 'Any-word logic drops the required operator; whole words drop the prefix' );

$operators = SearchIndex::boolean_expression( [ '-spam+', '(x)' ], 'all', 'prefix', 3 );
assert_same( '+spam*', $operators['expression'], 'Boolean operators in visitor input are stripped' );
assert_same( [ '(x)' ], $operators['remaining'], 'Terms with no indexable word fall back' );

echo "Search index passed.\n";
