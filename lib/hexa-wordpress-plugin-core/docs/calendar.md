# Calendar

## Namespace And Folder

```text
src/Calendar/
Hexa\PluginCore\Calendar
```

## Purpose

`Calendar` is the one reusable, lightweight month-grid calendar for public
pages. A host plugin declares a *profile*; Core owns the month grid, the
bounded query, request validation, the REST endpoint, the shortcode, server
rendering, filters, caching, and the small live interaction. The host owns only
the profile values, each item's link, and optional item markup.

- No library: about 5 KB of CSS and 4 KB of JavaScript (about 3 KB together
  gzipped), printed inline once per page and only where a calendar renders.
- Cache-safe: page caches cannot show a stale “today” or miss new items. Pages
  that render a public calendar carry the LiteSpeed tag `hexa_calendar`, which
  is purged when calendar content changes, and their lifetime is capped at the
  next local midnight. The script also re-renders the grid in place when a
  cached page's day differs from the visitor's current day in the profile
  timezone.
- Server-rendered: the current month works without JavaScript and every item is
  a real link.
- Days are not interactive. Each item inside a day links to the URL the
  profile defines (permalink by default).
- Mobile collapses the grid into a list of days that have items.

## Setup

1. Add the module once to your `CoreBootstrap` (several hosts may add it; hooks register once):

```php
$bootstrap->add_module( new \Hexa\PluginCore\Calendar\CalendarModule() );
```

2. Register a profile during your plugin boot (or on the
   `hexa_plugin_core_calendar_register` action):

```php
use Hexa\PluginCore\Calendar\CalendarRegistry;

CalendarRegistry::register( 'events', [
    'post_types'    => [ 'event' ],
    'start'         => [ 'meta' => 'start_date_timestamp', 'format' => 'timestamp' ],
    'end'           => 'end_date_timestamp',  // optional; stored in the start field's format
    'timezone'      => 'America/New_York',   // default: site timezone
    'link'          => 'permalink',          // or [ 'meta' => 'ticket_url', 'fallback' => 'permalink' ] or fn( int $id, array $item ): string
    'filters'       => [                     // docs/query-filters.md
        'area'  => [ 'type' => 'taxonomy', 'taxonomy' => 'area', 'options' => 'terms', 'all_label' => 'All areas' ],
        'dates' => [ 'type' => 'date_range', 'meta_key' => 'start_date_timestamp', 'end_meta_key' => 'end_date_timestamp', 'format' => 'timestamp' ],
        'kids'  => [ 'type' => 'meta', 'meta_key' => 'kids_event', 'control' => 'toggle', 'label' => 'Kids events' ],
    ],
    'prepare'       => fn( array $ids, array $request ): array => my_batch_data( $ids ), // id => data
    'item_class'    => fn( array $item, array $data ): string => ! empty( $data['featured'] ) ? 'is-featured' : '',
    'render_item'   => null,                 // optional: fn( array $item, array $data, array $request ): string (inner HTML, no links)
    'labels'        => [ 'count_many' => '%d events', 'empty' => 'Nothing is scheduled in %s.' ],
    'class'         => 'my-calendar',
] );
```

3. Place the shortcode anywhere (for example an Elementor Shortcode widget):

```text
[hexa_calendar id="events"]
```

## Profile Reference

| Key | Default | Notes |
| --- | --- | --- |
| `source` | `posts` | `posts` (Core query) or `callback` (`provider`). |
| `post_types` | `['post']` | Published, non-password posts only. |
| `start` | required | A meta key string (Unix timestamp), `['meta' => key, 'format' => 'timestamp'|'datetime'|'date']`, or `['column' => 'date']` (post date). |
| `end` | none | A meta key or `['meta' => key]`, always in the start field's format (a different declared format is rejected). A missing or empty end, or one before the start, means the item ends when it starts. |
| `end_midnight` | `auto` | How an end at exactly local midnight reads: `auto` keeps that day for all-day items (a stored last day, such as a date-only end) and drops it for timed items (ending as the day begins); or `inclusive` / `exclusive` for every item. |
| `provider` | — | `callback` source: `fn( int $from, int $to, array $request, array $profile ): array` of `['id', 'title', 'url', 'start', 'end', 'all_day', 'data']`. |
| `timezone` | site | IANA identifier; days, times, and date filters (unless a filter declares its own `timezone`) use it. |
| `week_start` | site `start_of_week` | 0 (Sunday) – 6. |
| `months_back`, `months_ahead` | 12, 12 | Visitors (and crawlers) can open only this window; month links are `rel="nofollow"`. Max 60. |
| `max_per_day` | 3 | Extra items collapse behind `+N more` (native `<details>`). When expanded, the control reads `Show less`; collapsing restores the count. Override these strings through `labels.more` and `labels.less`. |
| `max_span_days` | 7 | Longer items appear once, on their first day inside the month (or first visible day when they lie wholly outside it), labeled “Until …”. |
| `max_items` | 500 | Per grid window (max 2,000). |
| `link` | `permalink` | Where each item goes; see above. |
| `link_behavior` / `lightbox` | `page` | What a click does: `page`, `new_tab`, or `lightbox` (the item opens in an in-page dialog). See `docs/item-link.md`. The legacy `link_target` `_blank` still opens a new tab. |
| `title` | post title | Optional `fn( int $id, array $item ): string`. |
| `time_format` | site `time_format` | Items at local midnight are all-day and show no time. |
| `sort` | continued DESC, start ASC, title ASC | Ordered per-day criteria, evaluated after placement and before the visible/more split. See sorting below. An empty list preserves provider/query order. |
| `filters` | none | Shared QueryFilter definitions. Date-range controls take the request's month window as limits unless they declare `min`/`max`; give a date range `end_meta_key` so ongoing items match. |
| `prepare` | — | Batch data for exactly the loaded IDs. |
| `render_item` | Core markup | Inner HTML of the item link; must be escaped and contain no links. `$item['when']` holds the time label. |
| `item_class` | — | Extra classes per item (sanitized). |
| `heading_level` | 2 | Month title heading (2–6). |
| `public` | `true` | `false` limits the shortcode and REST endpoint to logged-in readers. |
| `cache_ttl` | 300 | Seconds (0–3600) to cache the unfiltered item list per month. |
| `cache_version` | `1` | Bump to invalidate cached months after a code change. |

## Visitor Parameters

| Parameter | Meaning |
| --- | --- |
| `cmonth` | `YYYY-MM`, clamped to the profile window. |
| `cfilter[key]` | Filter values (see `docs/query-filters.md`). |
| `cal` | The profile id that owns the URL state, so several calendars can share a page. |

An active date-range filter narrows the months visitors may open (month links
outside it are disabled). A requested month outside the range opens the
range's first month. Days outside the range are shaded; their items stay
readable. `base` and page links drop tracking parameters (`utm_*`, `gclid`,
`fbclid`, and similar) so cached pages never carry one visitor's campaign IDs.

## REST Endpoint

```text
GET /wp-json/hexa-plugin-core/v1/calendar/{profile}?cmonth=2026-10&cfilter[key]=&base=/page-path/
```

Returns `{ html, month, label, total, status }`; `status` updates the
component's persistent live region (“September 2026 · 5 events”). Anonymous responses carry
`Cache-Control: public, max-age=60` and cap LiteSpeed Cache at the same TTL.

## Caching

The unfiltered item list of each month is cached (transient) for `cache_ttl`,
keyed by profile, `cache_version`, a content generation, and month, so
visitors cannot grow the cache. Saving, trashing, or deleting a post of a
calendar post type, or changing its date, filter, or link fields or its terms,
starts a new generation once the request finishes. Filtered views are never
cached. Markup is rebuilt per request.

## Accessibility

Month links, the month title, and the item links are the only focusable
elements; days are not. After a month change focus returns to the matching
navigation link (or the month title), and the result count is announced
through a live region that stays in place while the grid is swapped.

## Theming

Override the zero-specificity custom properties on `.hcal` (or your profile
`class`); structural rules are ordinary class selectors, so scope overrides
with the profile class (for example `.my-calendar .hcal-item`): `--hcal-accent`, `--hcal-accent-fg`, `--hcal-border`, `--hcal-muted`,
`--hcal-outside-bg`, `--hcal-item-bg`, `--hcal-item-hover`, `--hcal-item-fg`,
`--hcal-field-bg`, `--hcal-radius`, `--hcal-gap`, `--hcal-cell-h`. Structural
classes: `.hcal-head`, `.hcal-title`, `.hcal-nav`, `.hcal-btn`, `.hcal-filters`,
`.hcal-days`, `.hcal-day` (`.is-outside`, `.is-today`, `.is-past`, `.has-items`,
`.is-out-of-range`), `.hcal-item` (`.is-continued`, `.is-long`), `.hcal-time`,
`.hcal-name`, `.hcal-more`, `.hcal-empty`.

## Testing

```bash
php tests/calendar.php
php tests/query-filters.php
php tests/package-integrity.php
```

## Per-day sorting

`Hexa\PluginCore\Calendar\CalendarSort` owns stable multi-criterion ordering.
Hosts supply only criteria and any domain values in the existing batch `prepare`
callback or item provider. This is presentation ordering within each day: it does
not change the bounded month query, its item limit, or placement across dates.
Server markup and REST month fragments use the same order, including the items
collapsed under “more”. Continuing items are not given extra priority unless a
criterion explicitly requests it.

```php
'sort' => [
    [ 'field' => 'data.area', 'type' => 'text', 'direction' => 'asc' ],
    [ 'field' => 'start', 'type' => 'number', 'direction' => 'asc' ],
],
```

Criteria are evaluated in the order declared. A string is shorthand for a field
criterion. `field` selects any scalar item value, including nested `data.*`,
`start`, `end`, `title`, `all_day`, `continued`, and `long`. Alternatively,
`value => fn( array $item ) => ...` computes a scalar value from the complete
placed item once per criterion per item, allowing custom ranks or derived values.
Declare exactly one of `field` or `value`.

- `direction`: `asc` (default) or `desc`.
- `type`: `auto` (default, PHP scalar comparison), `text` (case-insensitive
  natural comparison), `number`, or `boolean`. Normalize domain-specific values
  in the host callback when necessary.
- `missing`: `last` (default) or `first`, independent of direction. Absent/null,
  empty-string, and non-scalar values count as missing; zero and false do not.

Ties retain input order. An explicitly empty `sort` list preserves query/provider
order. Omitting `sort` retains the previous order: `continued` descending,
`start` ascending, then `title` ascending. Existing direct callers of
`CalendarGrid::place()` retain that default; its optional sixth argument accepts
the same criteria. Pure callers can use `CalendarSort::items( $items, $criteria )`
without WordPress. Invalid criteria throw `InvalidArgumentException` at profile
registration. Bump the profile's `cache_version` when changing prepared sort
values; sorting itself runs after cached items are loaded.

When a check is requested, use a small pure `CalendarSort::items()` fixture with
multiple primary values and dates, plus tied and missing values, or the existing
calendar fixture entry point. No browser or WordPress installation is required
for the sorting function.
