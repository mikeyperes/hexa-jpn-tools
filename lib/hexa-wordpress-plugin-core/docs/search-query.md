# Native Search Query

## Namespace And Folder

```text
src/SearchQuery/
Hexa\PluginCore\SearchQuery
```

## Purpose

`SearchQuery` is the reusable native WordPress search-results engine. It lets a host plugin define how words match and which content sources are searched without copying `pre_get_posts` or SQL filters into every plugin.

It does not render a search box and it does not return AJAX suggestions:

- `SearchDisplay` renders public GET forms that submit `s` to WordPress.
- `SearchQuery` changes one eligible native WordPress results query.
- `SmartSearch` powers AJAX typeahead and content pickers.

## Public Classes

### `SearchQueryConfiguration`

`SearchQueryConfiguration::normalize(array $settings, array $available_post_types = [], array $available_taxonomies = []): array`

Normalizes untrusted host settings against Core modes and host-provided public objects.

| Key | Accepted values | Default |
| --- | --- | --- |
| `enabled` | boolean-like value | `false` |
| `scope` | `shortcode`, `all` | `shortcode` |
| `term_logic` | `all`, `any`, `exact` | `all` |
| `word_matching` | `whole`, `prefix`, `contains` | `contains` |
| `post_types` | host-allowed public post-type names | `post`, `page` |
| `fields` | `title`, `content`, `excerpt`, `slug` | title, content, excerpt |
| `taxonomies` | host-allowed public taxonomy names | none |
| `authors` | boolean-like value | `false` |
| `custom_fields` | up to 20 normalized meta keys | none |
| `results_per_page` | `0` through `100`; `0` keeps WordPress | `0` |
| `orderby` | `relevance`, `newest`, `oldest`, `title` | `relevance` |

Term logic and word matching are separate controls. `all` versus `any` decides how multiple terms relate. `whole`, `prefix`, and `contains` decide where each term can match inside a word. `exact` treats the complete submitted text as one contiguous phrase and ignores the word-matching mode.

### `SearchTermParser`

`SearchTermParser::parse(string $query, string $term_logic = 'all'): array`

Preserves quoted phrases, removes duplicate terms case-insensitively, strips explicit wildcard characters, and limits compiled SQL to eight unique terms of at most 80 characters each. Empty quoted exact phrases produce no search clause.

### `SearchQueryEngine`

`new SearchQueryEngine(callable $settings_provider, string $marker_key = 'hexa_search')`

`SearchQueryEngine::register(): void` idempotently registers the public marker query variable, guarded preparation hook, and one exact-query SQL dispatcher. `SearchQueryEngine::build_search_sql()` is public for deterministic testing; hosts should normally let WordPress call the registered hooks.

### `JetEngineSearchAdapter`

`new JetEngineSearchAdapter(callable $settings_provider, string $marker_key = 'hexa_search')`

`JetEngineSearchAdapter::register(): void` bridges a JetEngine posts listing grid to the same engine when a search-results template creates a secondary `WP_Query` instead of rendering the native main query. It copies only the current main search text and host marker, then applies Core's private explicit-query marker. The engine still owns post-type, source, count, ordering, and SQL behavior.

The adapter rejects admin, WP-CLI, AJAX, REST, cron, XML-RPC, feed, empty, suppressed, disabled, non-search, and non-main request contexts before loading host settings. It skips JetEngine grids configured as archive templates because those already consume the native main query. A host can reject a specific grid with `hexa_plugin_core_search_query_jet_engine_should_handle` or the `hexa_search_query_disabled` query argument.

## Required Host Protocol

The host plugin owns:

1. A separate option containing its search behavior settings.
2. Discovery of allowed public post types and taxonomies.
3. Capability and nonce checks for every settings mutation.
4. A unique public marker query variable when using `shortcode` scope.
5. The search-form shortcode and admin UI.
6. Frontend tests using controlled content fixtures.

Example:

```php
use Hexa\PluginCore\SearchDisplay\SearchDisplayRenderer;
use Hexa\PluginCore\SearchQuery\SearchQueryConfiguration;
use Hexa\PluginCore\SearchQuery\SearchQueryEngine;
use Hexa\PluginCore\SearchQuery\JetEngineSearchAdapter;

$marker = 'example_search';
$settings_provider = static function (): array {
    $stored = get_option( 'example_search_behavior', [] );

    return SearchQueryConfiguration::normalize(
        is_array( $stored ) ? $stored : [],
        get_post_types( [ 'public' => true ], 'names' ),
        get_taxonomies( [ 'public' => true ], 'names' )
    );
};
$engine = new SearchQueryEngine(
    $settings_provider,
    $marker
);
$engine->register();

$jet_engine = new JetEngineSearchAdapter( $settings_provider, $marker );
$jet_engine->register();

echo SearchDisplayRenderer::render(
    [
        'style'         => 'pill',
        'hidden_fields' => [ $marker => '1' ],
    ]
);
```

The host should cache its normalized settings within a request if another host callback needs the same data. Do not merge display options and behavior options; they have different ownership and failure modes.

## Query Safety Contract

The engine uses `pre_get_posts` only as a narrow coordination point. Before invoking the host settings provider it rejects:

- non-object or incompatible query values;
- wp-admin and WP-CLI;
- AJAX, cron, REST, and XML-RPC requests;
- non-main and non-search queries, except a secondary query carrying Core's trusted explicit adapter marker;
- feeds;
- empty search text;
- `suppress_filters` queries;
- queries carrying `hexa_search_query_disabled`.

After normalization it rejects disabled configurations and unmarked requests in `shortcode` scope. Only then does it set allowed post types, count, and ordering. One idempotently registered `posts_search` dispatcher checks weak exact-object state and consumes that state when the target reaches the filter. Repeated preparation replaces the same object's pending state instead of stacking callbacks, and an abandoned query cannot be retained by Core when `posts_search` never runs. Host code must never add the explicit adapter marker to ordinary loops.

Never replace the exact-object dispatcher with broad unconditional SQL or a query-capturing closure. Never perform option, post-type, or taxonomy discovery before the cheap request/query guards. This ordering is part of the public performance contract.

Host code can make a final request-specific decision with:

```php
add_filter(
    'hexa_plugin_core_search_query_should_handle',
    static function ( bool $allowed, WP_Query $query, array $settings ): bool {
        return $allowed;
    },
    10,
    3
);
```

## SQL Model

Core replaces only the target query's search clause. Selected post fields are combined with optional source checks. Taxonomy names, author display names, and selected custom-field values use correlated `EXISTS` subqueries instead of broad joins, preventing duplicate result rows and avoiding unnecessary join work when those sources are disabled.

Anonymous searches retain WordPress password protection. WordPress continues to own post status, pagination, permissions, template selection, and result rendering.

`contains` uses escaped `LIKE`; `prefix` and `whole` use bounded regular expressions. Custom fields are opt-in and limited to 20 explicit keys. This is a lightweight live-query engine, not an index. Fuzzy correction, stemming, synonyms, weighted fields, comments, attachment contents, and commerce indexing belong in a dedicated indexed implementation.

## Testing

Run the deterministic package tests:

```bash
php tests/search-query-engine.php
php tests/search-display-renderer.php
php tests/package-integrity.php
```

Every host release must additionally use the visible frontend workflow to verify:

1. AJAX settings save and persistence after reload.
2. Disabled mode leaves ordinary WordPress search unchanged.
3. Shortcode scope changes marked searches but not unmarked searches.
4. All-word, any-word, exact-phrase, prefix, and whole-word fixtures return the expected differences.
5. Post-type and field selections exclude controlled nonmatching fixtures.
6. Native form submission reaches `/?s=...` with the marker.
7. No PHP notice, page error, console error, or unrelated query mutation occurs.
8. Repeated preparation does not add another `posts_search` callback, and abandoned query objects remain collectible.

Restore the original host option and remove every test fixture after verification.
