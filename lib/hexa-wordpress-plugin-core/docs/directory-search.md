# Directory Search

## Namespace And Folder

```text
src/DirectorySearch/
Hexa\PluginCore\DirectorySearch
```

## Purpose

`DirectorySearch` is the one reusable way to put a live, filterable, paginated
search over a set of **posts of selected post types** or **users holding
selected roles** on a public page. A host plugin declares a *profile* with
parameters; Core owns the SQL, request validation, REST endpoint, shortcode,
server rendering, pagination, and live interaction. The host owns only the
profile values, card markup, and any business data a card shows.

The four search domains stay separate:

| Module | Job |
| --- | --- |
| `SearchDisplay` | Native GET search forms that submit `s` to WordPress. |
| `SearchQuery` | Changes one eligible native WordPress search-results query. |
| `SmartSearch` | Admin AJAX typeahead and content pickers. |
| `DirectorySearch` | Public listing pages with live search, filters, sorts, and card templates. |

`DirectorySearch` reuses `SearchTermParser` and the shared `SearchMatchSql`
matcher, so all/any/exact term logic and whole/prefix/contains word matching
behave identically everywhere. Its filters are the shared `QueryFilter`
structure (`docs/query-filters.md`), also used by `Calendar`.

## Setup

1. Add the module once to your `CoreBootstrap` (several hosts may add it; hooks register once):

```php
$bootstrap->add_module( new \Hexa\PluginCore\DirectorySearch\DirectorySearchModule() );
```

2. Register a profile during your plugin boot (or on the
   `hexa_plugin_core_directory_search_register` action):

```php
use Hexa\PluginCore\DirectorySearch\DirectorySearchRegistry;

DirectorySearchRegistry::register( 'team', [
    'source'        => 'users',            // 'posts' | 'users'
    'roles'         => [ 'author' ],       // required for users
    'fields'        => [ 'display_name' ], // users: display_name, nicename, url
    'meta_keys'     => [ 'description' ],  // up to 20 visitor-facing meta keys
    'term_logic'    => 'all',              // all | any | exact
    'word_matching' => 'prefix',           // whole | prefix | contains
    'wildcards'     => true,               // honor * inside terms
    'min_chars'     => 2,
    'per_page'      => 12,                 // 1–50
    'filters'       => [
        'city' => [ 'type' => 'meta', 'meta_key' => 'city', 'compare' => 'serialized',
                    'label' => 'City', 'all_label' => 'All cities', 'options' => fn() => [ '12' => 'Miami' ] ],
        'active' => [ 'type' => 'callback', 'control' => 'toggle', 'label' => 'Active only',
                      'apply' => fn( string $value, array $profile ): ?array => my_active_ids() ],
    ],
    'sorts'         => [
        'name'  => [ 'type' => 'field', 'field' => 'name', 'label' => 'A–Z' ],
        'score' => [ 'type' => 'callback', 'label' => 'Most active', 'callback' => fn( array $ids, array $request ): array => my_order( $ids ) ],
    ],
    'default_sort'  => 'score',
    'prepare'       => fn( array $ids, array $request ): array => my_batch_data( $ids ), // id => data
    'render_item'   => fn( int $id, array $data, array $request ): string => my_card( $id, $data ),
    'labels'        => [ 'placeholder' => 'Search the team…', 'results_many' => '%d people' ],
    'cache_ttl'     => 300,                // 0–3600 seconds (transient)
    'class'         => 'my-directory',
] );
```

3. Place the shortcode anywhere (for example an Elementor Shortcode widget):

```text
[hexa_directory id="team"]
```

## Profile Reference

| Key | Posts | Users | Notes |
| --- | --- | --- | --- |
| `source` | `posts` | `users` | Unsupported values throw. |
| `post_types` | required | — | Published, non-password posts only. |
| `roles` | — | required | Scoped through the blog `capabilities` meta key. |
| `fields` | `title`, `content`, `excerpt`, `slug` | `display_name`, `nicename`, `url` | `user_login` and `user_email` are never searchable, preventing account enumeration. |
| `meta_keys` | postmeta | usermeta | Correlated `EXISTS`; max 20. Only list visitor-facing keys. |
| `taxonomies` | term names | — | Searched by term name. |
| `term_logic` | ✓ | ✓ | `all`, `any`, or `exact` phrase. |
| `word_matching` | ✓ | ✓ | `whole`, `prefix`, `contains`. |
| `wildcards` | ✓ | ✓ | `syn*` word starts, `*gogue` word ends, `s*gogue` one word. |
| `filters` | `meta`, `taxonomy`, `date_range`, `callback`, custom | `meta`, `date_range`, `callback`, custom | Shared `QueryFilter` definitions (`docs/query-filters.md`): select, toggle, and date-range controls; ACF-aware meta compares; keyword options (`terms`, `acf`, `distinct`); host-registered types. |
| `sorts` | `title`, `date`, `modified` | `name`, `registered` | `callback` sorts reorder the first 1,000 candidate IDs; later rows continue in default order and totals stay exact. |
| `prepare` | ✓ | ✓ | Batch-load card data for the current page only. |
| `render_item` | ✓ | ✓ | Must return escaped HTML for one card. |
| `public` | ✓ | ✓ | `false` limits the shortcode and REST endpoint to logged-in readers; live search then sends a REST nonce. |
| `cache_ttl` | ✓ | ✓ | Caches only default views (no search text, no filters, an in-range page) keyed by profile, `cache_version`, sort, and page, so visitors cannot grow the cache. Pagination is rebuilt per request. |

## Visitor Parameters

The same names serve the no-JavaScript form, crawlable pagination links,
shareable URLs, and REST calls:

| Parameter | Meaning |
| --- | --- |
| `dq` | Search text (max 200 characters). |
| `dpage` | Page number (1–1000). |
| `dsort` | A declared sort key. |
| `dfilter[key]` | A declared filter value; select values must be declared options; date ranges use `dfilter[key][from]` and `dfilter[key][to]`. |
| `hds` | The profile id that owns the URL state, so several directories can share a page (the former `dir` is still read; many web firewalls block it). |

## REST Endpoint

```text
GET /wp-json/hexa-plugin-core/v1/directory/{profile}?dq=&dpage=&dsort=&dfilter[key]=&base=/page-path/
```

Returns `{ html, summary, total, page, pages }`. Anonymous responses carry
`Cache-Control: public, max-age=60` and cap LiteSpeed Cache at the same TTL.
`base` is reduced to a same-site root-relative path (max 255 characters) plus
the page's own non-directory query arguments, without tracking parameters
(`utm_*`, `gclid`, `fbclid`, and similar); it only builds pagination links.

## Rendering And Interaction

- The shortcode server-renders the form, summary, first result page, and
  numbered pagination, so the directory works without JavaScript and is
  crawlable.
- A small inline script (printed once per page) upgrades the component:
  debounced typing, immediate filter/sort changes, aborted stale requests,
  `aria-busy`, a polite live summary, URL sync with `history`, back/forward
  support, and in-place pagination.
- Echoed visitor text is made inert to `do_shortcode()` (for example Elementor
  re-parsing `the_content`).
- Styles are minimal and driven by zero-specificity CSS custom properties on `.hds`
  (`--hds-accent`, `--hds-border`, `--hds-muted`, `--hds-field-bg`,
  `--hds-radius`, `--hds-gap`). Hosts theme the component by overriding them
  and styling their own card markup.

## Security And Performance Contract

- Identifiers come only from Core whitelists; every visitor value is bound
  through `$wpdb->prepare()`.
- Users sources must declare roles; private user columns are not searchable.
- Optional sources use correlated `EXISTS` subqueries; nothing joins unless it
  is configured.
- `prepare` receives only the current page's IDs, so hosts can batch their
  per-card data with one query.
- This is a live-query engine for directories of up to a few thousand rows,
  not an index. Fuzzy correction and stemming belong in a dedicated index.

## Testing

```bash
php tests/directory-search.php
php tests/query-filters.php
php tests/search-query-engine.php
php tests/package-integrity.php
```
