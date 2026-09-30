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
map, pin clustering, the group filter, the item card, caching, and the
interaction. The host owns only the profile values and each item's card data.

- Free and keyless: MapLibre GL JS draws open vector tiles (OpenFreeMap by
  default). Addresses are placed by the US Census geocoder and/or
  OpenStreetMap Nominatim.
- Light: about 15 KB of inline CSS and JavaScript (under 6.5 KB gzipped). The map
  library and tiles load only when the component nears the viewport.
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
  stays highlighted and the map glides so its card opens fully in view. The map
  instance is exposed as `element.hmapMap` for site extensions.
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
| `labels` | English | `region`, `loading`, `all`, `filter`, `more`, `when`, `when_all`, `when_prefix` (optional lead-in before the date chips), `count_one`, `count_many`, `list`, `cta`. |
| `cache_ttl` | `3600` | Seconds the rendered payload is cached (per content generation); also caps the LiteSpeed page lifetime. |
| `cache_version` / `class` / `public` | — | As in the other public components. |

## Caching

The payload (points with rendered cards, group counts, and the list) is cached
per profile, `cache_version`, and content generation. The generation changes,
and LiteSpeed pages tagged `hexa_map` are purged, whenever coordinates are
stored, an address or group field changes, a user's role or profile changes
(users source), or a post of a mapped type changes (posts source).

## Operations

```php
// Place every pending address now (WP-CLI: wp eval), batch-limited per profile:
( new \Hexa\PluginCore\Map\MapModule() )->geocode( 'hosts' );

// What is still waiting to be placed:
\Hexa\PluginCore\Map\MapLocations::pending( \Hexa\PluginCore\Map\MapRegistry::get( 'hosts' ) );
```

## Host Responsibilities

- Declare the profile and card data; never build map markup, pins, geocoding,
  or tile styling in the host.
- Put brand colors in the page builder (CSS tokens), not in the host plugin.
- Attribution to OpenStreetMap and the tile provider stays visible (MapLibre's
  compact attribution control).

## Testing

`php tests/map.php` covers address cleaning, provider order and response
parsing without network access, profile validation, pending detection,
change detection by address hash, remembered misses, cache generation bumps,
escaping and shortcode-inert output, group chips and select, and asset size.
