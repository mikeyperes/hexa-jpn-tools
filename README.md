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
area, and badges.
Days are not clickable; each event links to its permalink.

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
