=== Hexa JPN Tools ===
Contributors: michaelperes
Requires at least: 6.0
Requires PHP: 8.1
Stable tag: 1.2.1
License: GPLv2 or later

JPN event management, host tools, and the authenticated Code.Hexa integration contract.

== Description ==

Hexa JPN Tools owns the JPN Miami WordPress event workflow and provides a
versioned REST contract for Code.Hexa using WordPress Application Passwords.

== Changelog ==

= 1.2.1 =
* Fixes host directory live search and pagination on servers whose web firewall blocks the `dir` URL parameter (vendors Hexa WP Core 3.2.1).

= 1.2.0 =
* Adds the event month calendar `[hexa_calendar id="jpn_events"]` on Hexa WP Core Calendar: a lightweight server-rendered month grid whose events link to their pages, with Area, Dates, Kids events, and Featured filters and month navigation that swaps only the grid.
* Vendors Hexa WP Core 3.2.0 (Calendar and the shared QueryFilter structure; the host directory keeps its behavior).

= 1.1.0 =
* Adds the searchable host directory `[hexa_directory id="jpn_hosts"]` on Hexa WP Core DirectorySearch: live prefix/wildcard search, area and upcoming filters, activity sorts, event counts, and recent events per host.
* Adds the Elementor Loop Grid query ID `jpn_past_events`.
* Adds `[jpn_event_photos]` for event Additional Photos, rendering nothing when the gallery is empty.
* Replaces the multi-join event window query behind `[events-photos]` with one indexed join per timestamp, fixing multi-minute page loads.
* Loads the JPN frontend stylesheet site-wide and vendors Hexa WP Core 3.1.0.

= 1.0.3 =
* Formats date-only event cards as a calendar date without an invented midnight time.
* Preserves the supplied time for timed event cards.

= 1.0.2 =
* Preserves date-only event precision and hides unsupported midnight times.
* Refreshes event permalink rules once after plugin upgrades.

= 1.0.1 =
* Aligns the live REST fixture with the integration-capability access contract.

= 1.0.0 =
* Migrates JPN-specific behavior from jpn-structure into an isolated plugin.
* Adds the authenticated hexa-jpn/v1 health, manifest, settings, events, hosts, and operations contract.
* Reuses Hexa WordPress Plugin Core for runtime and update behavior.
