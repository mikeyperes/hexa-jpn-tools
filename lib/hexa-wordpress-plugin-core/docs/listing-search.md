# Listing Search

## Namespace And Folder

```text
src/ListingSearch/
Hexa\PluginCore\ListingSearch
```

## Purpose

A search box and filters that narrow a page's existing list of posts: a
category, tag, other taxonomy, author, post type, search or blog page, or any
Elementor loop marked as the listing. It extends WordPress's own query instead
of running a separate one, so themes, Elementor and other plugins keep
working, and it searches inside the page it sits on (a search box on a
category page searches that category).

## How It Works

- `ListingContext` turns the page into WP_Query arguments (`current()`), or
  rebuilds them from a token (`term:category:12`, `author:5`,
  `post_type:event`, `search`, `home`). Hosts widen a context with
  `hexa_plugin_core_listing_context_args`; site search covers the types from
  `hexa_plugin_core_listing_search_post_types` (default: every public type not
  excluded from search, pages included).
- `ListingSearch::state()` reads the address: `hls` (profile id), `hq` (text;
  on the search page WordPress's own `s`), `hf[key][]` (filters). Values are
  validated by the shared `QueryFilterSet`.
- `ListingSearchModule` narrows the page's main query and every query marked
  with the `hexa_listing` argument through `QuerySql::attach()` (one exact
  query object, extra WHERE conditions). Elementor Loop Grid, Posts and
  Archive Posts widgets are marked when they carry the CSS class
  `hexa-listing-results`, show the page's own list ("Current Query"), or a
  plugin says so through `hexa_plugin_core_listing_search_target`.
- `ListingSearchRenderer` prints a GET form for the page's own address: text,
  filters with the number of results behind each option, Clear and a result
  line (only while searching). With JavaScript, typing (350 ms pause) and
  ticking fetch the same address, swap every `.hexa-listing-results` element
  and the filter counts in place, restart Elementor widgets so Load more keeps
  working, and replace the address. Without JavaScript the form submits.
- Counts and the total run against `QuerySql::post_ids_sql()`: the SQL
  WordPress builds for the same listing, so they always match the results.

## Profile

```php
ListingSearchRegistry::register( 'listing', [
    'filters'     => [ /* QueryFilter definitions, posts source */ ],
    'search'      => [ SettingsClass::class, 'settings' ], // SearchQueryConfiguration settings
    'search_args' => [ 'hexa_search' => '1', 'hexa_search_query_explicit' => '1' ], // site search counts only
    'post_types'  => [ 'post' ],          // only on a page that is not an archive
    'target'      => '.hexa-listing-results',
    'labels'      => [ 'placeholder_context' => 'Search %s', 'results_many' => '%d results' ],
] );

echo ( new ListingSearchRenderer() )->render( 'listing', [ 'filters' => 'category,author', 'results_many' => '%d stories' ] );
```

On a term page the filter for that taxonomy lists only its subcategories (or
hides), on an author page the author filter hides, and the content-type filter
shows only where several types are listed. HWS Base Tools registers the
`listing` profile and the `[hws_listing_search]` shortcode.

## Styling

The markup inherits the site's fonts and colours. Classes start with `hls-`;
custom properties `--hls-accent`, `--hls-border`, `--hls-radius`,
`--hls-panel-bg` and `--hls-field-height` restyle it. The swapped listing gets
`hls-loading` while it reloads. `hexa-listing:updated` fires on `document`
after each refresh.

## Testing

```bash
php tests/listing-search.php
```
