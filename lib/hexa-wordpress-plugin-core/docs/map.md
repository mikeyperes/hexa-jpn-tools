# Map

## Namespace And Folder

```text
src/Map/
Hexa\PluginCore\Map
```

## Purpose

`Map` is the one reusable, brandable location map for public pages. A host
plugin declares a *profile* (which posts or users appear and where their
street address is stored); Core owns geocoding and storing coordinates, the
map, pin clustering, the group filter, item cards, an optional right selection
sidebar, caching, and the interaction. The host owns profile values and item
data, including any related entries shown after a selection.

- Free and keyless: MapLibre GL JS draws open vector tiles (OpenFreeMap by
  default). Addresses are placed by the US Census geocoder and/or
  OpenStreetMap Nominatim.
- The map library and tiles load only when the component nears the viewport;
  sidebar entries load only after a selection.
- Brandable without code: every color is a `--hmap-*` CSS custom property,
  and the script recolors the base map's land, water, parks, buildings, roads,
  boundaries and labels from the same tokens, so a page builder's Custom CSS
  restyles the whole map.
- Geocoding never runs in a visitor request. An hourly WP-Cron event places up
  to `batch` new or changed addresses per profile, and an address edit
  schedules a run a minute later. Results are stored on the item with a hash
  of the address, so an edited address is placed again and an address no
  service can place is not retried until it changes.
- Smooth selection: every pin has a generous invisible target and a click picks
  the nearest pin; hover enlarges it and shows the item's name; the chosen pin
  stays highlighted. The default popup opens above the pin, sized to the map.
  Opt into `selection => 'sidebar'` for a scrollable right panel and a resized
  map centered on the selected location. On narrow screens, the panel stacks
  below the map. The map instance is exposed as `element.hmapMap`.
- Accessible and crawlable: the count is a live region, filters are real
  buttons, one-finger scrolling passes through on touch screens, and every
  item is also a plain link in a `<details>` list (opened automatically if the
  map library cannot load).

## Setup

1. Add the module once to your `CoreBootstrap`:

```php
$bootstrap->add_module( new \Hexa\PluginCore\Map\MapModule() );
```

2. Register a profile during your plugin boot (or on the
   `hexa_plugin_core_map_register` action):

```php
use Hexa\PluginCore\Map\MapRegistry;

MapRegistry::register( 'hosts', [
    'source'    => 'users',                     // or 'posts' with 'post_types' => [ 'venue' ]
    'roles'     => [ 'host' ],
    'address'   => 'address',                   // meta key, or fn( int $id ): string
    'geocoders' => [ 'census', 'nominatim' ],   // order tried; or fn( string $address ): ?array{lat,lng}
    'country'   => 'us',                        // Nominatim country filter
    'group'     => [ 'meta' => 'area', 'taxonomy' => 'area' ], // or [ 'taxonomy' => t ] for post terms, or fn( int $id, array $data ): string
    'prepare'   => fn( array $ids ): array => my_batch_data( $ids ), // id => data, only for placed items
    'highlight' => fn( int $id, array $data ): bool => $data['upcoming'] > 0, // pulsing pin
    'card'      => fn( int $id, array $data ): array => [
        'kicker'     => 'Boca Raton',            // default: the item's group
        'meta'       => [ '1 Main St', '12 events' ], // default: [ address ]
        'list_label' => 'Upcoming',
        'list'       => [ [ 'label' => 'Oct 4', 'text' => 'Shabbat dinner', 'url' => 'https://…' ] ],
        'cta'        => 'View host',
    ],
    'view'      => [ 'center' => [ 26.2, -80.19 ], 'zoom' => 8.4, 'fit_zoom' => 13, 'max_zoom' => 17 ], // center is [lat, lng]
    'labels'    => [ 'count_one' => '%d host', 'count_many' => '%d hosts', 'cta' => 'View host' ],
    'class'     => 'my-map',
] );
```

3. Place the shortcode anywhere (for example an Elementor Shortcode widget):

```text
[hexa_map id="hosts"]
```

4. Brand it with CSS custom properties on the component (for example in the
   widget's Custom CSS):

```css
selector .hmap {
    --hmap-height: 600px;
    --hmap-accent: #25D366; --hmap-accent-fg: #04130a; --hmap-live: #25D366;
    --hmap-land: #040705; --hmap-water: #0d2219; --hmap-park: #08110b; --hmap-building: #0e1611;
    --hmap-road: #142019; --hmap-road-major: #1d3a27; --hmap-boundary: #1f3326; --hmap-label: #5f6d63;
    --hmap-surface: #0c120e; --hmap-text: #f1f4f1; --hmap-muted: #8f978f; --hmap-border: rgba(255,255,255,.1);
    --hmap-radius: 4px; --hmap-glow: rgba(37,211,102,.35);
}
```

## Profile Reference

| Key | Default | Notes |
| --- | --- | --- |
| `source` | `posts` | `posts` (published, no password) or `users`. |
| `post_types` / `roles` | `['post']` / — | Which items appear. |
| `address` | required | Meta key or `fn( int $id ): string`. Markup, entities, a trailing country, and whitespace are cleaned. |
| `geo_meta` | `hexa_map_geo` | Meta key where Core stores `{h, lat, lng, src, t}` or `{h, miss, t}`. |
| `geocoders` | `['nominatim']` | `census` (US only, no rate limit) and/or `nominatim` (worldwide, paced at one request per second), or a callback. Each is tried with and without a suite/unit number. |
| `batch` | `25` | Addresses placed per profile per cron run. |
| `group` | none | Filter group per item; chips show the `chips` (6) largest groups with more than one item, and a select lists every group. |
| `prepare` | none | `fn( int[] $ids ): array` batch data for placed items, passed to `title`, `link`, `card`, `highlight`, `render_item`. |
| `title` / `link` | name / profile URL | Users: display name and author URL; posts: title and permalink. Or `fn( int $id, array $data )`. |
| `card` | group, address, CTA | `fn( int $id, array $data, array $item ): array` with `kicker`, `meta`, `list_label`, `list`, `cta`. Core escapes and renders it. |
| `selection` | `popup` | `popup` or `sidebar`; sidebar opens on the right, below the map on narrow screens. |
| `details` | none | `fn( int $id, array $data, array $item, array $query ): array` supplying the selection title, summary, and paginated rich entries. Without a provider, sidebar uses the existing item card. |
| `details_per_page` | `10` | Server-controlled entry limit, 1–50. |
| `related_post_types` | `[]` | Related content types whose saves, deletion, terms, or metadata changes invalidate map and detail caches, including maps sourced from users. |
| `link_behavior` / `lightbox` | `page` | What a click on a card link does: `page`, `new_tab`, or `lightbox` (a linked post opens in an in-page dialog; rows may name their post with `id`). See `docs/item-link.md`. |
| `render_item` | none | `fn( int $id, array $data, array $item ): string` full card markup (escape it yourself). |
| `highlight` | none | `fn( int $id, array $data ): bool` pulsing pin (reduced motion disables the pulse). |
| `next` | none | `fn( int $id, array $data ): int` Unix start of the item's next dated entry (0 when none). Enables the date filter chips. |
| `windows` | 24 h, 48 h, 1 week, 2 weeks | Date filter choices as `hours => label`. The browser keeps items whose `next` starts within that many hours of its own clock (and not more than a day ago), combined with the group filter, so cached pages stay correct; each chip shows its count. |
| `view` | zoom 9 | `center` `[lat, lng]` and `zoom` for the "All" view (without a center the map fits every pin), `fit_zoom` caps zoom when a group is chosen, `max_zoom`. |
| `style` | OpenFreeMap dark | Any https MapLibre style using the OpenMapTiles schema is recolored by the tokens. |
| `library` | MapLibre from unpkg | `['js' => https URL, 'css' => https URL]` to self-host. |
| `cluster` | `true` | Cluster nearby pins; a cluster zooms in on click. |
| `max_items` | `500` | Upper bound 2000. |
| `heading_level` | `3` | Card title heading. |
| `labels` | English | `region`, `loading`, `all`, `filter`, `more`, `when`, `when_all`, `when_prefix`, `count_one`, `count_many`, `list`, `cta`; sidebar adds `details`, `details_open`, `details_close`, `details_loading`, `details_error`, `details_empty`, `details_retry`, `details_previous`, `details_next`, `details_page`. |
| `cache_ttl` | `3600` | Seconds the rendered payload is cached (per content generation); also caps the LiteSpeed page lifetime. |
| `cache_version` / `class` / `public` | — | As in the other public components. |

## Selection Details

`Hexa\PluginCore\Map\MapDetails` owns the bounded read endpoint and rich
entry renderer. Add these values to a map profile:

```php
'selection' => 'sidebar',
'details_per_page' => 10,
'related_post_types' => [ 'activity' ],
'details' => function ( int $id, array $data, array $item, array $query ): array {
    // Host query must scope to the selected location/group, apply the requested
    // date window, count all matching entries, and clamp the requested page.
    $result = my_location_entries( $id, $query['page'], $query['per_page'], $query['hours'] );
    return [
        'title' => $item['group'] ?: $item['title'],
        'summary' => 'Activities at this location',
        'total' => $result['total'],
        'page' => $result['page'],
        'entries' => $result['entries'], // see entry shape below
    ];
},
```

Each entry is structured data, escaped and rendered by Core:

```php
[
    'id' => $post_id, // optional post ID for the profile's existing lightbox
    'title' => 'Activity title',
    'url' => get_permalink( $post_id ),
    'image' => [ 'url' => $image_url, 'alt' => 'Activity photo' ],
    'description' => 'A short plain-text description.',
    'facts' => [ 'When' => 'Oct 4 at 6 PM', 'Where' => 'Main hall' ],
    'actions' => [ [ 'label' => 'Details', 'url' => get_permalink( $post_id ) ],
                   [ 'label' => 'Register', 'url' => $registration_url, 'external' => true ] ],
]
```

`GET /wp-json/hexa-plugin-core/v1/map/{profile}/details/{item}?page=1&hours=0`
returns `title`, escaped `html`, `page`, `pages`, and `total`. `MapLocations::item()`
resolves one eligible placed item, requiring the profile's user roles or a
published password-free post of a declared type; geocoding never runs here.
The provider receives `page` (1–10000), `per_page` (from the profile), and
`hours` (0 or one of the profile's window keys). Zero means no upper date
window; past/ongoing/upcoming semantics remain host-owned. Unknown windows
are rejected. Profiles retain their public/private visibility rules.

Core bounds rendered entries and action counts, escapes facts and text,
sanitizes URLs, keeps output shortcode-inert, and preserves image/title
lightbox links. Actions retain their supplied destination, with external
actions opening a new tab. Hosts own related-content eligibility, grouping,
ordering, date semantics, and missing-field fallback.

Core owns loading, empty/error/retry states, pagination, selected pin state,
request cancellation and stale-response protection, close/Escape, and focus
restoration. Keyboard users can select through the location list; controls for
locations outside the active filters are disabled. Changing filters clears
the selection. `--hmap-sidebar-width` defaults to `380px` (at most half the
desktop map); all other colors and spacing use the existing map tokens.

## Caching

The payload (points with rendered cards, group counts, and the list) is cached
per profile, `cache_version`, and content generation. The generation changes,
and LiteSpeed pages tagged `hexa_map` are purged, whenever coordinates are
stored, an address or group field changes, a user's role or profile changes
(users source), or a post of a mapped type changes (posts source). Declared
`related_post_types` also invalidate the generation and map cache tag on post
and metadata changes, so related entries can refresh user-based map highlights.
Anonymous public detail responses use the shared 60-second REST cache lifetime
and `hexa_map` tag. Private details require a signed-in reader and use
`private, no-store`.

## Operations

```php
// Place every pending address now (WP-CLI: wp eval), batch-limited per profile:
( new \Hexa\PluginCore\Map\MapModule() )->geocode( 'hosts' );

// What is still waiting to be placed:
\Hexa\PluginCore\Map\MapLocations::pending( \Hexa\PluginCore\Map\MapRegistry::get( 'hosts' ) );
```

## Host Responsibilities

- Declare the profile, card data, and optional paginated details provider;
  never build map/sidebar markup, interaction, pins, geocoding, or tile styling
  in the host. Declare content dependencies in `related_post_types`.
- Put brand colors in the page builder (CSS tokens), not in the host plugin.
- Attribution to OpenStreetMap and the tile provider stays visible (MapLibre's
  compact attribution control).

## Testing

`php tests/map.php` covers address cleaning, provider order and response
parsing without network access, profile validation, pending detection,
change detection by address hash, remembered misses, cache generation bumps,
escaping and shortcode-inert output, group chips and select, and asset size.

For requested selection verification, use one selected eligible location's
details endpoint to inspect its title, image/facts markup, total, and page.
Browser interaction can separately exercise a pin, close/Escape, pagination,
filter changes, and narrow-screen placement. These are optional verification
methods; documentation does not imply that they ran for a release.
