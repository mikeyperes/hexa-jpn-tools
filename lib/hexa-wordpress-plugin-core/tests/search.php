<?php

declare(strict_types=1);

$root = dirname( __DIR__ );

set_error_handler( static function ( int $severity, string $message, string $file, int $line ): bool {
    throw new ErrorException( $message, 0, $severity, $file, $line );
} );

if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}

final class FakeSearchWpdb {
    public string $posts = 'wp_posts';
    public string $postmeta = 'wp_postmeta';
    public string $users = 'wp_users';
    public string $usermeta = 'wp_usermeta';
    public string $terms = 'wp_terms';
    public string $term_taxonomy = 'wp_term_taxonomy';
    public string $term_relationships = 'wp_term_relationships';
    /** @var string[] */
    public array $queries = [];

    public function esc_like( string $value ): string {
        return addcslashes( $value, '_%\\' );
    }

    public function prepare( string $sql, mixed ...$values ): string {
        $index = 0;
        return (string) preg_replace_callback( '/%[sd]/', static function ( array $match ) use ( &$index, $values ): string {
            $value = $values[ $index++ ] ?? '';
            return '%d' === $match[0] ? (string) (int) $value : "'" . str_replace( "'", "''", (string) $value ) . "'";
        }, $sql );
    }

    public function get_results( string $sql, $output = null ): array {
        $this->queries[] = $sql;
        if ( str_contains( $sql, "taxonomy = 'category'" ) ) {
            return [ [ 'option_value' => 'news', 'total' => '7' ], [ 'option_value' => 'markets', 'total' => '2' ] ];
        }

        return [ [ 'option_value' => 'jane', 'total' => '4' ] ];
    }

    public function get_var( string $sql ): string {
        $this->queries[] = $sql;
        return '9';
    }
}

function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function is_taxonomy_hierarchical( string $taxonomy ): bool {
    return 'category' === $taxonomy;
}

foreach ( [ 'PublicComponents/ProfileValues', 'PublicComponents/ProfileStore', 'PublicComponents/PublicComponent', 'QueryFilter/QueryFilterType', 'QueryFilter/MetaFilterType', 'QueryFilter/TaxonomyFilterType', 'QueryFilter/DateRangeFilterType', 'QueryFilter/CallbackFilterType', 'QueryFilter/AuthorFilterType', 'QueryFilter/PostTypeFilterType', 'QueryFilter/QuerySql', 'QueryFilter/QueryFilterTypes', 'QueryFilter/QueryFilterSet', 'SearchQuery/SearchQueryConfiguration', 'Search/SearchProfile', 'Search/SearchRegistry', 'Search/SearchContext', 'Search/Search' ] as $class ) {
    require $root . '/src/' . $class . '.php';
}

use Hexa\PluginCore\QueryFilter\QueryFilterSet;
use Hexa\PluginCore\QueryFilter\QuerySql;
use Hexa\PluginCore\Search\Search;
use Hexa\PluginCore\Search\SearchContext;
use Hexa\PluginCore\Search\SearchProfile;

$assertions = 0;
$expect = static function ( bool $condition, string $message ) use ( &$assertions ): void {
    $assertions++;
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$profile = SearchProfile::normalize( 'Listing', [
    'filters' => [
        'type'     => [ 'type' => 'post_type', 'options' => [ 'post' => 'Posts', 'page' => 'Pages' ], 'control' => 'checkboxes' ],
        'category' => [ 'type' => 'taxonomy', 'taxonomy' => 'category', 'options' => [ 'news' => 'News', 'markets' => 'Markets', 'empty' => 'Empty' ], 'control' => 'checkboxes', 'hide_empty' => true ],
        'tag'      => [ 'type' => 'taxonomy', 'taxonomy' => 'post_tag', 'options' => [ 'a' => 'A' ], 'control' => 'checkboxes' ],
        'author'   => [ 'type' => 'author', 'options' => [ 'jane' => 'Jane' ], 'control' => 'checkboxes', 'match' => 'all' ],
    ],
    'target'  => '.my-list"><script>',
] );
$expect( 'listing' === $profile['id'] && 4 === count( $profile['filters'] ) && 'list' === $profile['results'], 'Profiles normalize their id, keep valid filters and narrow the page list by default.' );
$expect( ! str_contains( $profile['target'], '<' ), 'The refresh target is reduced to selector characters.' );
$expect( [ 'newest' ] === array_keys( $profile['sorts'] ) && 'DESC' === $profile['sorts']['newest']['order'], 'Posts default to newest first.' );

$card = static fn( int $id ): string => 'card ' . $id;
$people = SearchProfile::normalize( 'people', [ 'source' => 'users', 'roles' => [ 'host' ], 'render_item' => $card, 'fields' => [ 'display_name', 'user_email' ] ] );
$expect( 'cards' === $people['results'] && [ 'display_name' ] === $people['fields'] && isset( $people['sorts']['name'] ), 'People searches render cards, never search email, and sort by name.' );
foreach ( [ [ 'source' => 'users', 'render_item' => $card ], [ 'source' => 'users', 'roles' => [ 'host' ] ], [ 'source' => 'rows' ] ] as $bad ) {
    $threw = false;
    try {
        SearchProfile::normalize( 'bad', $bad );
    } catch ( InvalidArgumentException $error ) {
        $threw = true;
    }
    $expect( $threw, 'Unsafe profiles are refused: users need roles and cards, sources are whitelisted.' );
}

$term_context   = [ 'type' => 'term', 'taxonomy' => 'category', 'term_id' => 12, 'post_types' => [ 'post' ] ];
$tag_context    = [ 'type' => 'term', 'taxonomy' => 'post_tag', 'term_id' => 3, 'post_types' => [ 'post' ] ];
$author_context = [ 'type' => 'author', 'taxonomy' => '', 'term_id' => 0, 'post_types' => [ 'post' ] ];
$search_context = [ 'type' => 'search', 'taxonomy' => '', 'term_id' => 0, 'post_types' => [ 'post', 'page' ] ];

$on_category = Search::filters_for( $profile, $term_context );
$expect( 12 === $on_category['category']['child_of'] && ! isset( $on_category['type'] ), 'On a category page the category filter lists its subcategories and the type filter hides.' );
$expect( ! isset( Search::filters_for( $profile, $tag_context )['tag'] ), 'On a tag page the flat tag filter disappears.' );
$expect( ! isset( Search::filters_for( $profile, $author_context )['author'] ), 'On an author page the author filter disappears.' );
$expect( isset( Search::filters_for( $profile, $search_context )['type'] ), 'Site search shows the content-type filter.' );

$section = SearchContext::for_scope( [ 'post_type' => 'press_release, news', 'category' => 'Business', 'author' => '7,x' ] );
$expect( [ 'press_release', 'news' ] === $section['post_types'] && 'business' === $section['args']['category_name'] && [ 7 ] === $section['args']['author__in'] && 'page' === $section['type'], 'A box on an ordinary page searches what its scope options say.' );
$expect( 'page:post' === SearchContext::for_scope( [] )['token'], 'An unscoped box on an ordinary page searches posts.' );

$expect( 'abc def' === Search::text( "  abc\x01<b>def  " ) || 'abc b def' === Search::text( "  abc\x01<b>def  " ), 'Visitor text loses control characters and markup.' );
$expect( Search::MAX_TEXT_LENGTH === strlen( Search::text( str_repeat( 'a', 500 ) ) ), 'Visitor text is bounded.' );

$state = [ 'profile' => $people, 'context' => null, 'text' => 'chabad', 'values' => [], 'sort' => 'name', 'page' => 2 ];
$expect( [ 'hs' => 'people', 'hq' => 'chabad', 'hp' => 3 ] === Search::args( $state, 3 ), 'Card pages keep the text and move to the next page in the address.' );

$expect( 'wp_posts.ID IN (SELECT hexa_ids.ID FROM (SELECT ID FROM x) hexa_ids)' === QuerySql::in_ids( 'wp_posts.ID', 'SELECT ID FROM x' ), 'Counts restrict by the list\'s own ID query.' );

$db     = new FakeSearchWpdb();
$scope  = QueryFilterSet::scope( 'search', $profile );
$values = QueryFilterSet::parse( $profile['filters'], [ 'category' => [ 'news', 'bogus' ], 'author' => [ 'jane' ] ], $scope );
$expect( [ 'category' => [ 'news' ], 'author' => [ 'jane' ] ] === $values, 'Checkbox values keep only declared options.' );
$seen   = [];
$counts = QueryFilterSet::counts( $db, $profile['filters'], $values, $scope, static function ( array $own ) use ( &$seen ): string {
    $seen[] = array_keys( $own );
    return 'SELECT ID FROM wp_posts';
} );
$expect( in_array( [ 'author' ], $seen, true ), "An 'any' filter is counted without its own selection." );
$expect( in_array( [ 'category', 'author' ], $seen, true ), "An 'all' filter is counted within its own selection." );
$expect( [ 'news' => 7, 'markets' => 2 ] === $counts['category'], 'Taxonomy counts map to option values.' );

$html = QueryFilterSet::controls( $profile['filters'], $values, 'hf', 'hls', $scope, $counts, [ 'category' ] );
$expect( str_contains( $html, 'name="hf[category][]" value="news" checked' ) && str_contains( $html, '<span class="hls-count">7</span>' ), 'Checkboxes show their state and counts.' );
$expect( ! str_contains( $html, 'value="empty"' ), 'Options with no results are hidden when hide_empty is set.' );

echo "PASS: search contract ({$assertions} assertions).\n";
