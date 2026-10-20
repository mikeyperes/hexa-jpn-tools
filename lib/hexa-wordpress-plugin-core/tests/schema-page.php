<?php

declare(strict_types=1);

$filters = [];
function add_action( string $hook, mixed $callback, int $priority = 10, int $args = 1 ): void {}
function add_filter( string $hook, mixed $callback, int $priority = 10, int $args = 1 ): void { $GLOBALS['filters'][ $hook ] = $callback; }
function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed { return $value; }
function sanitize_key( string $key ): string { return strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', $key ) ); }
function untrailingslashit( string $value ): string { return rtrim( $value, '/' ); }
function is_admin(): bool { return false; }
function wp_strip_all_tags( string $text, bool $breaks = false ): string { return strip_tags( $text ); }
function esc_url_raw( string $url ): string { return $url; }
function sanitize_email( string $email ): string { return $email; }

$root = dirname( __DIR__ );
foreach ( [ 'DataNormalization/ValueNormalizer', 'SchemaTools/SchemaGraph', 'SchemaTools/SchemaDocumentRenderer', 'SchemaTools/SchemaNodes', 'SchemaTools/SchemaPage', 'SchemaTools/RankMathSchemaControl' ] as $class ) {
    require $root . '/src/' . $class . '.php';
}

use Hexa\PluginCore\SchemaTools\RankMathSchemaControl;
use Hexa\PluginCore\SchemaTools\SchemaNodes;
use Hexa\PluginCore\SchemaTools\SchemaPage;

function page_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

$org_id = 'https://example.test/#organization';
$publication = [
    SchemaNodes::organization( [ '@id' => $org_id, 'name' => 'Example News', 'url' => 'https://example.test/', 'sameAs' => [ 'https://x.com/example', 'not a url' ] ], 'NewsMediaOrganization' ),
    SchemaNodes::website( [ '@id' => 'https://example.test/#website', 'url' => 'https://example.test/', 'name' => 'Example News', 'publisher' => SchemaNodes::ref( $org_id ) ] ),
];
$company = [
    SchemaNodes::organization( [ '@id' => $org_id, 'name' => 'Wrong Name', 'sameAs' => [ 'https://linkedin.com/company/example' ], 'telephone' => '+1 555' ] ),
    SchemaNodes::website( [ '@id' => 'https://example.test/#website', 'url' => 'https://example.test/', 'name' => 'Example News' ] ),
];

$document = SchemaPage::build( [ 'publication' => $publication, 'company' => $company ] );
$graph = $document['@graph'];
page_assert( 2 === count( $graph ), 'Nodes with the same @id from two sources must merge into one node.' );
page_assert( 'NewsMediaOrganization' === $graph[0]['@type'] && 'Example News' === $graph[0]['name'], 'The higher priority source keeps its type and name.' );
page_assert( '+1 555' === $graph[0]['telephone'], 'A lower priority source fills properties the first one lacks.' );
page_assert( [ 'https://x.com/example', 'https://linkedin.com/company/example' ] === $graph[0]['sameAs'], 'List properties are combined and invalid URLs dropped.' );
page_assert( [ 'publication', 'company' ] === SchemaPage::contributors(), 'Contributors are listed in priority order.' );
page_assert( [ '@id' => $org_id, '@type' => 'NewsMediaOrganization' ] === $graph[1]['publisher'], 'Linked references keep @id and @type.' );

$article = SchemaNodes::article( [ '@id' => 'https://example.test/a/#article', 'name' => str_repeat( 'Long headline ', 20 ) ], 'NewsArticle' );
page_assert( 110 >= mb_strlen( $article['headline'] ) && 'NewsArticle' === $article['@type'], 'Article headlines are limited to 110 characters.' );
page_assert( [] === SchemaNodes::breadcrumb( [ [ 'name' => 'Home', 'url' => 'https://example.test/' ] ] ), 'A one-item breadcrumb is not output.' );
page_assert( 'Organization' === SchemaNodes::organization( [ 'name' => 'X' ], 'Bogus' )['@type'], 'Unknown organization types fall back to Organization.' );

$mode = RankMathSchemaControl::OFF;
RankMathSchemaControl::register( static function () use ( &$mode ): string { return $mode; } );
page_assert( [] === RankMathSchemaControl::filter( [ 'a' => [ '@type' => 'Article' ] ] ), 'Off removes Rank Math schema.' );
$mode = RankMathSchemaControl::KEEP;
page_assert( [ 'a' => 1 ] === RankMathSchemaControl::filter( [ 'a' => 1 ] ), 'On keeps Rank Math schema.' );

echo "PASS: Schema page merges sources by @id and the Rank Math switch honours its mode.\n";
