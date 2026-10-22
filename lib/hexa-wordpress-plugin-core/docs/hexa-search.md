# Search

## Namespace And Folder

```text
src/Search/
Hexa\PluginCore\Search
```

## Purpose

The one public search for every use. A search box with filters and, for
directories, sorts. Results show in one of two places:

- `list` (default): the page's own list narrows in place. That is a
  category, tag, other taxonomy, author, post type, search or blog page, or
  any Elementor loop marked as the list (a homepage section, a landing page).
  It extends WordPress's own query instead of running a separate one.
- `cards`: the box renders its own result cards with the profile's
  `render_item` (a people or host directory, or any list with no loop of its
  own), with sorts and Load more.

One script refreshes every box without a page reload, and one loading look
dims the results under a spinner. `SearchBox::header()` draws the header
search box. There is no other public search box, script or loader in Core.

## How It Works

- `SearchContext` turns the page into WP_Query arguments (`current()`), or
  rebuilds them from a token (`term:category:12`, `author:5`,
  `post_type:event`, `search`, `home`). On an ordinary page, `for_scope()`
  builds them from the box's `post_type`, `category`, `tag` and `author`
  options. Hosts widen a context with `hexa_plugin_core_search_context_args`;
  site search covers the types from `hexa_plugin_core_search_post_types`
  (default: every public type not excluded from search, pages included).
- `Search::state()` reads the address: `hs` (the profile that owns it), `hq`
  (text; on the search page WordPress's own `s`), `hf[key][]` (filters), `hp`
  (page of cards) and `ho` (sort). Values are validated by `QueryFilterSet`.
- `SearchModule` narrows the page's main query and every query marked with
  the `hexa_search_list` argument through `QuerySql::attach()` (one exact query
  object, extra WHERE conditions). Elementor Loop Grid, Posts and Archive
  Posts widgets are marked when they carry the CSS class
  `hexa-listing-results`, show the page's own list ("Current Query"), or a
  plugin says so through `hexa_plugin_core_search_target`. It also adds the
  `[hexa_search id="profile"]` shortcode.
- `Search::results()` runs one page of cards on `WP_Query` or
  `WP_User_Query`, including callback sorts over up to 1,000 matching IDs.
- `SearchBox::render()` prints a GET form for the page's own address: text,
  filters with the number of results behind each option, a sort (cards with
  several sorts), Clear and a status line. With JavaScript, typing (350 ms
  pause) and ticking fetch the same address and swap the results in place
  (the list's `.hexa-listing-results` elements, or the box's
  `#hls-{id}-results`), plus the filter counts, restart Elementor widgets and
  replace the address. Load more appends the next page of cards (on scroll
  when `load_mode` is `infinite`); numbered pages swap. Without JavaScript
  the form submits and the links work.
- Counts and totals run against `Search::ids_sql()`: the SQL WordPress builds
  for the same query, so they always match the results.

## Profile

```php
SearchRegistry::register( 'listing', [
    'filters'     => [ /* QueryFilter definitions */ ],
    'search'      => [ SettingsClass::class, 'settings' ], // SearchQueryConfiguration settings
    'search_args' => [ 'hexa_search' => '1' ],             // site search counts only
    'post_types'  => [ 'post' ],                           // a box on an ordinary page
] );

SearchRegistry::register( 'hosts', [
    'source'       => 'users',
    'roles'        => [ 'host' ],
    'fields'       => [ 'display_name', 'nicename', 'url' ],
    'meta_keys'    => [ 'address', 'website' ],
    'filters'      => [ /* QueryFilter definitions, users source */ ],
    'sorts'        => [ 'name' => [ 'type' => 'field', 'field' => 'name', 'label' => 'Name A–Z' ] ],
    'render_item'  => [ $this, 'card' ],     // fn( int $id, array $prepared, array $state ): string
    'prepare'      => [ $this, 'prepare' ],  // fn( int[] $ids, array $state ): array<int,array>
    'per_page'     => 12,
    'load_mode'    => 'infinite',            // infinite | load_more | numbered
    'cache_ttl'    => 300,                   // default views only
] );

echo SearchBox::render( 'listing', [ 'post_type' => 'press_release', 'filters' => 'category', 'style' => 'default-1' ] );
echo SearchBox::header( [ 'style' => 'icon-reveal', 'hidden' => [ 'hexa_search' => '1' ] ] );
```

Text matches the site's `SearchQuery` settings (`search`) or the explicit
`fields`, `meta_keys` and `taxonomies` with `term_logic`, `word_matching` and
`wildcards`. Users always use explicit fields; login and email are never
searchable. On a term page the filter for that taxonomy lists only its
subcategories (or hides), on an author page the author filter hides, and the
content-type filter shows only where several types are listed.

Box options: `style` (`none`, `default-1`, `default-2`), `placeholder`,
`filters`, `target`, `class`, `hidden`, `results_one`, `results_many`,
`empty`, `clear`, scope (`post_type`, `category`, `tag`, `author`) and the look
options `accent`, `border`, `radius`, `panel_bg`, `field_width`,
`field_height`, `font_size`, which set that one box's custom properties.

## Styling

Classes start with `hls-` and are documented with full markup in HWS Base
Tools' `docs/hws-search.md`. Rules are scoped `.hls.hls`, so theme field
styles cannot reshape the box; restyle it through the `--hls-*` custom
properties, or choose `none`. While results load, the results element gets
`hls-loading` and `aria-busy="true"`: its content dims to
`--hls-loading-opacity` and a spinner (`--hls-loader-size`,
`--hls-loader-surface`, `--hls-loader-offset`, accent `--hls-accent`) shows.
`hexa-search:updated` fires on `document` after each refresh.

## Testing

```bash
php tests/search.php
```
