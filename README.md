# Hexa JPN Tools

Hexa JPN Tools owns JPN Miami's WordPress-specific event, host, notification,
shortcode, and Code.Hexa integration behavior. The plugin replaces the legacy
`jpn-structure` plugin without changing its event post type, taxonomies, ACF
keys, shortcodes, options, or stored content.

## Identity

- Display name: **Hexa JPN Tools**
- WordPress folder slug: `hexa-jpn-tools`
- Main file: `hexa-jpn-tools.php`
- Canonical basename: `hexa-jpn-tools/hexa-jpn-tools.php`
- PHP namespace: `Hexa\JpnTools`
- Repository: `mikeyperes/hexa-jpn-tools`

## Code.Hexa contract

The authenticated REST namespace is `hexa-jpn/v1`. It uses WordPress's native
Application Password authentication and the dedicated
`manage_jpn_integration` capability. The plugin creates a minimal
`hexa_jpn_integration` role with that capability during activation.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/wp-json/hexa-jpn/v1/health` | Readiness for the plugin, receipt table, role, and Hexa WP Core |
| `GET` | `/wp-json/hexa-jpn/v1/manifest` | Stable identity, capabilities, authentication, and endpoint discovery |
| `GET` | `/wp-json/hexa-jpn/v1/settings` | Bounded JPN settings, areas, and safe host summaries |
| `GET` | `/wp-json/hexa-jpn/v1/events` | Paginated event collection |
| `GET` | `/wp-json/hexa-jpn/v1/events/{external_ref}` | Event state and durable receipt |
| `PUT` | `/wp-json/hexa-jpn/v1/events/{external_ref}` | Idempotent draft-first event create or update |
| `GET` | `/wp-json/hexa-jpn/v1/operations/{operation_id}` | Durable operation receipt lookup |
| `PATCH` | `/wp-json/hexa-jpn/v1/hosts/{id}` | Bounded host Instagram and moderation settings |

Code.Hexa should use these endpoints instead of database access, HTML scraping,
or WordPress internals. The contract never returns application passwords,
emails, raw host access codes, database credentials, or authentication state.

## Hexa WP Core

The plugin vendors and registers Hexa WordPress Plugin Core. Core owns package
selection, plugin update behavior, Core package update behavior, runtime
identity, and shared safety checks. JPN-specific event and host behavior stays
under `Hexa\JpnTools`.

## Host directory

`[hexa_directory id="jpn_hosts"]` renders the searchable host directory through
Hexa WP Core `DirectorySearch`. Core owns search, filters, sorting mechanics,
pagination, the public REST endpoint
(`/wp-json/hexa-plugin-core/v1/directory/jpn_hosts`), and live interaction.
`Hexa\JpnTools\Hosts\HostDirectory` owns the host profile: users with role
`host`, prefix and `*` wildcard matching over name, slug, website, address,
and Instagram, an area filter, an upcoming-events toggle, Upcoming first / Most
events / Recently active / A–Z sorts, event statistics from one aggregate
query, and the one-per-row card.

## Event calendar

`[hexa_calendar id="jpn_events"]` renders the event month calendar through
Hexa WP Core `Calendar`. Core owns the month grid, the bounded month query,
filters, month navigation (12 months back and ahead, `rel="nofollow"`), month
caching, the public REST endpoint
(`/wp-json/hexa-plugin-core/v1/calendar/jpn_events`), and live interaction.
`Hexa\JpnTools\Events\EventCalendar` owns the profile: published events by
`start_date_timestamp`/`end_date_timestamp` in New York time (date-only events
keep their stored last day), Area / Dates (matching events that run during the
chosen days) / Kids events / Featured filters, and each event's time, title,
area, and badges. Within each day, the profile uses Core's generic sort criteria
to order areas A–Z, then events by their starting date and time (earliest first).
Events without an area follow named areas; continuing events use the same order.
Days are not clickable; each event links to its permalink.

## Site search

JPN uses Elementor Pro's native Search widget for live, debounced results and
native Loop Item cards. Set the widget's Query ID to
`jpn_search_upcoming` for ongoing/upcoming events ordered by start time, or
`jpn_search_all` for every public searchable post type. Elementor owns the
live REST request, responsive result grid, loader, empty state, pagination,
keyboard behavior, and GET fallback. Hexa WP Core's
`ElementorSearchAdapter` owns exact-widget query provenance, bounded matching,
public-only result enforcement, request cancellation and stale-response
protection, accessible request states, and organizer lookup through the
`event_host` user reference. JPN supplies its New York date cutoff plus event
location, area, topic, author, and organizer sources. Core's
`ElementorPublicTextIndex` also searches visible text from public Elementor
pages and reusable templates without reading raw builder data or rewriting page
content. It refreshes after page/template saves.

Rebuild or inspect that public text source in bounded batches:

```bash
wp hexa-jpn search-index rebuild --dry-run
wp hexa-jpn search-index rebuild
```

The dry run reports only selected post IDs, character counts, change actions,
and before/after SHA-256 hashes. The write stores normalized public text in
`_hexa_elementor_public_text`; it never stores markup, scripts, style content,
tag attributes, form recipients, or raw `_elementor_data`.

## Host map

`[hexa_map id="jpn_hosts"]` renders the host map through Hexa WP Core `Map`.
Core owns geocoding and storing coordinates (user meta `hexa_map_geo`), the
MapLibre map on OpenFreeMap tiles, clustering, the area filter, a right-side
selection panel, rich entry markup, pagination, caching, and interaction.
`Hexa\JpnTools\Hosts\HostMap` owns the profile: users with the `host` role,
their `address` field, and their `area` term as the filter group. Selecting a
pin opens Core's sidebar on the right (below the map on narrow screens).
`Hexa\JpnTools\Events\AreaMapDetails` supplies upcoming and ongoing events
across all hosts in the selected area, ordered by start date then ID. It uses
`EventQueries::pageBetween()` to count all matching events and load ten per
page; the host directory's three-title card limit does not limit the sidebar.
An unassigned area falls back to the selected host's own events.

Each event includes its featured photo (or first additional photo), title,
stored description, date/time, venue/address, host, stored kids/featured flags,
and Details/RSVP links where available. Photos and titles keep the existing
event lightbox. Private/password-protected events are excluded. No missing
prices or audience restrictions are inferred.

The detail endpoint is
`/wp-json/hexa-plugin-core/v1/map/jpn_hosts/details/{host_id}`. "Any time"
has no future upper bound and shows upcoming/ongoing events; ended timed
events are excluded, and date-only events remain through their last local day.
A selected date chip also limits sidebar events to its hour window. Hosts
with upcoming events pulse, and `next` (the host's next event start) drives
the "Events in the next 24 hours / 48 hours / 1 week / 2 weeks" chips.
The profile declares `event` as related content, so event edits invalidate
map/detail caches. Colors and optional `--hmap-sidebar-width` are CSS tokens
set in Elementor.

## Latest events video

The campaign video step publishes each export with
`wp hexa-jpn video publish <file.mp4> --caption="<window> · <n> events"`
(`wp hexa-jpn video status` shows the current one). The file goes into the
Media Library and the option fields `jpn_latest_video`,
`jpn_latest_video_updated` and `jpn_latest_video_caption` (Notifications
Dashboard → Latest Video); the previous export this command created is
deleted and LiteSpeed pages tagged `jpn_latest_video` are purged.
`[jpn_latest_video]` renders the player with "Last updated X ago" above it
(Hexa WP Core `RelativeTime`, recomputed in the browser so cached pages stay
accurate). Styling lives in Elementor.

## Elementor queries and shortcodes

| Name | Type | Purpose |
| --- | --- | --- |
| `jpn_home_upcoming_events` | Loop Grid query ID | Upcoming events, soonest first. |
| `jpn_past_events` | Loop Grid query ID | Events that started before today, newest first. |
| `[jpn_event_photos]` | Shortcode | Current event's Additional Photos gallery; renders nothing when empty. |
| `[jpn_event_time_range]` | Shortcode | Current event's time range. |
| `[jpn_event_badges]` | Shortcode | Current event's Featured / Kids badges; renders nothing when neither is set. |
| `[jpn_event_facts]` | Shortcode | Current event's labelled When / Where / Host / Who grid (Facts Card loop item). |
| `[jpn_event_actions]` | Shortcode | Current event's RSVP (registration link, when set) and Details buttons. |

## Verification

```bash
php tests/run.php
php tests/architecture.php
find . -name '*.php' -type f -print0 | xargs -0 -n1 php -l
```

After installation, run the WP-CLI smoke and REST fixtures from the exact live
WordPress root as the site owner:

```bash
wp eval-file wp-content/plugins/hexa-jpn-tools/tests/wp-bootstrap-smoke.php
wp eval-file wp-content/plugins/hexa-jpn-tools/tests/wp-rest-fixture.php
```

The REST fixture creates temporary draft records and removes them in `finally`.
It never publishes an event.

See [docs/architecture.md](docs/architecture.md),
[docs/api-contract.md](docs/api-contract.md), and
[docs/migration-runbook.md](docs/migration-runbook.md).
