<?php
declare(strict_types=1);

// Site search returns only what any visitor may see and search engines may list.

function get_option( string $name ) {
    return [ 'page_on_front' => 12, 'page_for_posts' => 0 ][ $name ] ?? false;
}

require_once dirname( __DIR__ ) . '/src/SearchQuery/SearchQueryEngine.php';

final class PublicOnlyQuery {
    public array $vars = [ 'post__not_in' => [ 5 ], 'meta_query' => [ [ 'key' => 'kept', 'value' => '1' ] ] ];
    public function get( string $key ) { return $this->vars[ $key ] ?? ''; }
    public function set( string $key, $value ): void { $this->vars[ $key ] = $value; }
}

$query = new PublicOnlyQuery();
\Hexa\PluginCore\SearchQuery\SearchQueryEngine::restrict_to_public( $query );
$fail = static function ( string $message ): void { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); };

'publish' === $query->vars['post_status'] || $fail( 'published only' );
false === $query->vars['has_password'] || $fail( 'no password-protected items' );
[ 5, 12 ] === $query->vars['post__not_in'] || $fail( 'front page excluded and existing exclusions kept' );
[ [ 'key' => 'kept', 'value' => '1' ] ] === $query->vars['meta_query'] || $fail( 'existing meta query kept and no noindex meta joins added' );
1 === ( $query->vars['hexa_search_public_only'] ?? null ) || $fail( 'query marked public-only for the search SQL' );

// Elementor and the native engine both call it for one query; a second call must change nothing.
$once = $query->vars;
\Hexa\PluginCore\SearchQuery\SearchQueryEngine::restrict_to_public( $query );
$once === $query->vars || $fail( 'restrict_to_public is idempotent' );

$sql = \Hexa\PluginCore\SearchQuery\SearchQueryEngine::noindex_exclusion_sql( (object) [ 'posts' => 'wp_posts', 'postmeta' => 'wp_postmeta' ] );
str_starts_with( $sql, 'wp_posts.ID NOT IN (SELECT hexa_sq_ni.post_id FROM wp_postmeta hexa_sq_ni WHERE ' ) || $fail( 'noindex is one subquery on postmeta' );
str_contains( $sql, "hexa_sq_ni.meta_key = 'rank_math_robots' AND hexa_sq_ni.meta_value LIKE '%\"noindex\"%'" ) || $fail( 'Rank Math noindex excluded' );
str_contains( $sql, "hexa_sq_ni.meta_key = '_yoast_wpseo_meta-robots-noindex' AND hexa_sq_ni.meta_value = '1'" ) || $fail( 'Yoast noindex excluded' );

echo "PASS: site search is public-only (published, no password, no noindex, no front page).\n";
