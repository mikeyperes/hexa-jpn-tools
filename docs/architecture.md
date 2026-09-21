# Architecture

## Ownership

Hexa JPN Tools owns only JPN-specific WordPress behavior:

- `Admin`: event editing, exports, notification message preparation, and host role behavior.
- `Content`: Event, Service, and Profile registrations plus their JPN ACF groups.
- `Events`: Miami-local date normalization, bounded queries, and related-event behavior.
- `Frontend`: the four existing JPN shortcodes and private Code-reference filtering.
- `Rest`: the authenticated Code.Hexa contract and durable event receipts.
- `Security`: the dedicated integration role and capability.
- `Migrations`: additive schema, role, date, and legacy cutover state.

Hexa WP Core owns reusable package selection, updater behavior, Core package
updates, plugin context, and shared runtime checks. This plugin does not fork or
reimplement those systems.

## Runtime flow

1. `hexa-jpn-tools.php` registers the bundled Core candidate and the isolated autoloader.
2. `Plugin::register()` defers the full boot until `plugins_loaded`.
3. `CoreIntegration` creates the canonical `PluginContext` and updater modules.
4. Host modules register their WordPress actions, filters, shortcodes, and REST routes.
5. Activation installs only additive data structures and records the legacy cutover.

## Storage

Existing WordPress objects remain authoritative:

- Events stay in the `event` post type.
- JPN fields keep their existing post meta, user meta, ACF field keys, and option names.
- Durable Code.Hexa bindings stay in `{prefix}hexa_jpn_event_bindings`.
- The binding table stores external references, idempotency digests, operation IDs, and bounded JSON receipts.

No Code.Hexa process receives database credentials or queries this database
directly. WordPress validates capabilities and performs all reads and writes.

## Security

- Every REST route requires `manage_jpn_integration`.
- WordPress Application Passwords provide standard revocable authentication.
- The `hexa_jpn_integration` role contains only `read` and the dedicated capability.
- New events always begin as drafts.
- Repeated operation IDs are idempotent; changed payloads conflict.
- A later operation must supply the last operation ID.
- Host responses return only whether a private host code exists.
- There are no anonymous AJAX or REST mutation routes.

## Performance

- Lists are paginated and capped at 100 records per request.
- Area and relation scans have explicit bounds.
- Event snapshots use WordPress caches and return attachment IDs/URLs instead of media bytes.
- Network calls are limited to Hexa WP Core's explicit update checks.
