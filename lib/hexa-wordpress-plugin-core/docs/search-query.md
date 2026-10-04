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
| `user_reference_fields` | up to 10 post-meta keys containing public user IDs | none |
| `results_per_page` | `0` through `100`; `0` keeps WordPress | `0` |
| `orderby` | `relevance`, `newest`, `oldest`, `title` | `relevance` |
| `time_window` | Opt-in `QueryFilter\\NaturalTimeWindow` event-field mapping | disabled |

Term logic and word matching are separate controls. `all` versus `any` decides how multiple terms relate. `whole`, `prefix`, and `contains` decide where each term can match inside a word. `exact` treats the complete submitted text as one contiguous phrase and ignores the word-matching mode.

`time_window` accepts `start_meta_key`, optional `end_meta_key`, optional
`precision_meta_key` plus `date_only_value`, optional IANA `timezone`, and the
dated `post_types` subset. When configured, explicit edge phrases such as
`24 hours`, `next 48 hours`, `within one week`, or `Chabad next 48 hours`
become an inclusive current-instant-through-duration filter. A pure duration
needs no residual keyword; a mixed query retains its other words. Quoted
phrases and uncued prose such as `Open 24 hours` remain ordinary search text.
Durations are limited to 366 days. Field meaning and eligible post types remain
host-owned; parsing and bounded constraints remain in `QueryFilter`.

### `SearchTermParser`

`SearchTermParser::parse(string $query, string $term_logic = 'all'): array`

Preserves quoted phrases, removes duplicate terms case-insensitively, strips explicit wildcard characters, and limits compiled SQL to eight unique terms of at most 80 characters each. Empty quoted exact phrases produce no search clause.

### `SearchQueryEngine`

`new SearchQueryEngine(callable $settings_provider, string $marker_key = 'hexa_search')`

`SearchQueryEngine::register(): void` idempotently registers the public marker query variable, guarded preparation hook, and one exact-query SQL dispatcher. `SearchQueryEngine::build_search_sql()` is public for deterministic testing; hosts should normally let WordPress call the registered hooks.

`SearchQueryEngine::set_meta_constraints(object $query, array $constraints): void` attaches a trusted, bounded post-meta predicate tree to one already prepared query object. The tree supports nested `AND`/`OR` groups, up to 20 leaves and four levels, with `=`, `>`, `>=`, `<`, `<=`, `EXISTS`, and `NOT EXISTS` comparisons over `CHAR`, `NUMERIC`, `SIGNED`, `UNSIGNED`, `DECIMAL`, `DATE`, or `DATETIME` values. Invalid trees fail closed. Constraints are kept in weak exact-object state and are never read from visitor query variables.

When a configured natural time window is recognized, the engine stores its
trusted parsed state in `SearchQueryEngine::TIME_WINDOW_QUERY_VAR`, narrows the
query to the declared dated post types, and combines its constraint tree with
any adapter-supplied constraints. The original `s` value stays in place so
WordPress and Elementor still dispatch their normal search and pagination;
only Core's exact-query SQL uses the residual keyword.

### `MetaConstraintSql`

`MetaConstraintSql::compile(object $database, array $constraints, string $post_id_column = ''): string` compiles the same bounded tree into prepared correlated predicates. This avoids the row multiplication produced by `WP_Meta_Query` joins when an exact search component needs host-owned date or state rules. Hosts declare only keys, values, types, comparisons, and relations; Core owns aliases, casts, preparation, limits, and fail-closed validation.

### `JetEngineSearchAdapter`

`new JetEngineSearchAdapter(callable $settings_provider, string $marker_key = 'hexa_search')`

`JetEngineSearchAdapter::register(): void` bridges a JetEngine posts listing grid to the same engine when a search-results template creates a secondary `WP_Query` instead of rendering the native main query. It copies only the current main search text and host marker, then applies Core's private explicit-query marker. The engine still owns post-type, source, count, ordering, and SQL behavior.

The adapter rejects admin, WP-CLI, AJAX, REST, cron, XML-RPC, feed, empty, suppressed, disabled, non-search, and non-main request contexts before loading host settings. It skips JetEngine grids configured as archive templates because those already consume the native main query. A host can reject a specific grid with `hexa_plugin_core_search_query_jet_engine_should_handle` or the `hexa_search_query_disabled` query argument.

### `ElementorSearchAdapter`

`new ElementorSearchAdapter(callable $settings_provider, string $query_id, ?callable $query_configurator = null, int $max_results_per_page = 50)`

`ElementorSearchAdapter::register(): void` binds one exact Elementor Pro Search widget Query ID to the same bounded engine. Elementor continues to own its native live REST endpoint, Loop Item template, responsive results grid, loader, empty markup, pagination, keyboard behavior, and GET fallback. The adapter validates the widget type and its stored `search_query_query_id`, then weakly binds matching SQL only to that exact `WP_Query`, including its trusted Elementor REST query. It forces published, non-password results and clamps each page to 50 or the lower host limit.

Core also marks only the registered widgets and loads a small native-search companion. It aborts a superseded request as soon as the visitor types again and rejects stale responses. When the current input becomes shorter than the widget's native minimum character setting, Core clears the old native result markup and collapsed combobox state; Elementor resumes ownership at the threshold. For marked widgets inside another keyboard component, the companion handles Escape before the parent can steal it, returns focus to the input, and closes the result list. It also keeps native input icons anchored to the measured input height when a host deliberately places results in normal document flow. Loading, result-count, and empty updates use a visually hidden live region so they do not become result-grid items; request errors replace stale results with one visible full-width status. Elementor's native spinner and branded empty state stay visible. Core does not replace Elementor's renderer or endpoint.

The optional configurator receives the exact query, normalized settings, and verified widget. Use native `WP_Query` arguments there for domain constraints such as an upcoming date window. Post types remain intersected with the normalized allowlist after the callback.

### `ElementorPublicTextIndex`

`new ElementorPublicTextIndex(array $post_types = [], ?callable $extractor = null, int $max_characters = 100000)`

`ElementorPublicTextIndex::register(): void` maintains the private `_hexa_elementor_public_text` search source for declared public post types. An empty post-type list resolves the current public searchable types when indexing runs, after host types registered on `init` are available. It accepts only published, non-password, non-excluded content, renders through Elementor's supported frontend API as an anonymous visitor, removes scripts, styles, tags, attributes, and private widget settings, normalizes whitespace, and stores at most the configured 100,000 characters by default, with a hard limit of 250,000. The prior user and post context is restored after every render. Raw `_elementor_data` is never searched or copied into the index, and existing `post_content` is never rewritten.

Elementor document saves, exact `_elementor_data` changes, publication changes, and reusable-template saves refresh the index. Template dependency discovery reads only exact `template_id` values from Elementor document objects, limits each document traversal to 32 levels and 10,000 nodes, follows at most 100 reusable templates, and refreshes the public documents that depend on them. It does not persist document structure or other widget settings.

`ElementorPublicTextIndex::rebuild(int $page = 1, int $per_page = 100, bool $dry_run = false): array` selects only published, non-password Elementor documents in bounded pages of at most 200. Dry-run items contain only post ID, action, character count, before/after SHA-256 hashes, and a changed flag. A host owns the CLI or deployment wrapper and must opt `ElementorPublicTextIndex::META_KEY` into its existing `custom_fields` search configuration.

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
use Hexa\PluginCore\SearchQuery\ElementorSearchAdapter;
use Hexa\PluginCore\SearchQuery\ElementorPublicTextIndex;

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

$elementor_search = new ElementorSearchAdapter(
    $settings_provider,
    'example_live_search'
);
$elementor_search->register();

$elementor_text = new ElementorPublicTextIndex( get_post_types( [ 'public' => true ], 'names' ) );
$elementor_text->register();

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

Core replaces only the target query's search clause. Selected post fields are combined with optional source checks. Taxonomy names, author display names, selected custom-field values, and referenced-user display names use correlated `EXISTS` subqueries instead of broad joins, preventing duplicate result rows and avoiding unnecessary join work when those sources are disabled.

An exact component adapter may additionally return `['meta_constraints' => <tree>]` from its trusted query configurator. Core appends the compiled predicate to that same query's search clause, so date and state eligibility does not require a multiplying `meta_query`. A configured natural time window is combined with that trusted tree. Constraint-only searches remain eligible when the time phrase consumes the complete search text. WordPress or the component continues to own sorting, pagination, and rendering.

Anonymous searches retain WordPress password protection. Trusted Elementor Search widget queries are explicitly limited to published, non-password content. WordPress or Elementor continues to own pagination, permissions, template selection, and result rendering.

`user_reference_fields` searches the public display name of users referenced by numeric post-meta values. It is intended for public relationships such as an event organizer; email, login, and user meta are never searched. A host using `ElementorPublicTextIndex` may add its `META_KEY` to `custom_fields`; the value contains normalized public text rather than raw builder data.

`contains` uses escaped `LIKE`; `prefix` and `whole` use bounded regular expressions. Custom fields are opt-in and limited to 20 explicit keys. This is a lightweight live-query engine, not an index. Fuzzy correction, stemming, synonyms, weighted fields, comments, attachment contents, and commerce indexing belong in a dedicated indexed implementation.

## Testing

Run the deterministic package tests:

```bash
php tests/search-query-engine.php
php tests/search-display-renderer.php
php tests/package-integrity.php
node tests/elementor-search-client.js
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

## Related

Public directory pages with live search over posts or users use `Hexa\PluginCore\DirectorySearch` (`docs/directory-search.md`), which shares this module's term parser and `SearchMatchSql` matcher.
