=== Hexa JPN Tools ===
Contributors: michaelperes
Requires at least: 6.0
Requires PHP: 8.1
Stable tag: 1.8.10
License: GPLv2 or later

JPN event management, host tools, and the authenticated Code.Hexa integration contract.

== Description ==

Hexa JPN Tools owns the JPN Miami WordPress event workflow and provides a
versioned REST contract for Code.Hexa using WordPress Application Passwords.

== Changelog ==

= 1.8.10 =
* Fix: the host directory (/hosts/) failed to load in 1.8.8 and 1.8.9 because Core's directory passes extra arguments to the card callback.

= 1.8.9 =
* Event page host card: laid out for a half-width column (photo beside details, stats and buttons below), full photo, wrapping address.
* Contact button falls back to the host's WhatsApp, email or phone when no preferred method is chosen.

= 1.8.8 =
* Hosts: new Phone and Preferred Contact Method fields; host cards show the host's description and a contact button for the preferred method.
* Event pages: `[jpn_event_host]` shows the event's host as a full card (photo, description, website and links, preferred contact, upcoming events) linking to the host page.

= 1.8.7 =
* Latest events video: optional download button with the file size below the player (`[jpn_latest_video download="Download video"]`).

= 1.8.6 =
* Event lightbox closes cleanly after a quick close and reopen. Bundles Hexa WP Core 3.8.1.

= 1.8.5 =
* Event calendar and host map: clicking an event opens it in a lightbox (flyer, details, RSVP and Details buttons) instead of leaving the page. Bundles Hexa WP Core 3.8.0.

= 1.8.4 =
* Host map: a host's card always opens above its pin and fits inside the map, including on phones. Bundles Hexa WP Core 3.7.2.

= 1.8.3 =
* Host map: smoother pin selection (bigger tap targets, nearest-pin clicks, hover highlight with the host name, selected pin, card kept in view). Bundles Hexa WP Core 3.7.1.

= 1.8.2 =
* Map filter chips no longer turn pink on hover/focus (theme button styles); aligned count badges. Bundles Hexa WP Core 3.6.2.

= 1.8.1 =
* Bundles Hexa WP Core 3.6.1.

= 1.8.0 =
* Host map date filter: "Events in the next 24 hours / 48 hours / 1 week / 2 weeks" chips with counts, combined with the area filter (each host's next event start).
* Bundles Hexa WP Core 3.6.0.

= 1.7.0 =
* Adds the latest events video: `wp hexa-jpn video publish <file.mp4> --caption=…` stores the campaign's exported slideshow in the Media Library and the "Latest Video" option fields (replacing the previous export), and `[jpn_latest_video]` shows it with a live "Last updated X ago" line.
* Bundles Hexa WP Core 3.5.1.

= 1.6.0 =
* Adds the host map [hexa_map id="jpn_hosts"] on Hexa WP Core Map: every host with a street address, placed automatically (US Census geocoder, OpenStreetMap fallback) and re-placed when the address changes, with area filter, pulsing pins for hosts with upcoming events, and a card with the next events.
* Bundles Hexa WP Core 3.5.0.

= 1.5.0 =
* ACF Pro is no longer required: event, host and notification fields, the notifications options page and their hooks run on Hexa WP Core 3.4.9 Fields (ACF when active, native otherwise).
* Bundles Hexa WP Core 3.4.9.

= 1.4.2 =
* Events photo feed: use the large WordPress image rendition for sharper branded feed images while avoiding full-size originals.

= 1.4.1 =
* Photo downloads: file names use the plain event title (no HTML entities); the phone button prepares the large size so the page stays light on mobile data; the ZIP keeps the originals.

= 1.4.0 =
* `[events-photos download="yes"]` adds a "Save all photos to your phone" button (the phone's share sheet saves every event photo at once) and a ZIP of the same photos named by date and title, from `/wp-json/hexa-jpn/v1/photos.zip?days=N`.

= 1.3.0 =
* Adds `[jpn_event_badges]`, `[jpn_event_facts]` and `[jpn_event_actions]` for the Facts Card event loop item: Featured/Kids badges, a labelled When / Where / Host / Who grid, and RSVP / Details buttons.

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
