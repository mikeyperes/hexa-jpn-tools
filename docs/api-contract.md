# Code.Hexa API contract

## Authentication

Use HTTPS Basic Authentication with a dedicated WordPress Application Password.
The WordPress user must have `manage_jpn_integration`; assigning the
`hexa_jpn_integration` role supplies that capability. Credentials stay in the
protected Code.Hexa credential service and never appear in request logs or
plugin settings.

All responses are JSON. Contract responses include `schema_version: 1`.

## Discovery

`GET /wp-json/hexa-jpn/v1/health` is the readiness check.

`GET /wp-json/hexa-jpn/v1/manifest` is the stable discovery document. It
returns plugin identity, site URLs, timezone, authentication type, supported
features, and endpoint templates. It does not return data collections.

`GET /wp-json/hexa-jpn/v1/settings` returns safe operational settings, areas,
and paginated hosts. Query parameters:

- `hosts_page`: positive page number.
- `hosts_per_page`: 1–100.

## Events

`GET /events` supports `page`, `per_page`, `statuses`, `order`, `from`, `to`,
`only_upcoming`, and `include`. Pages are capped at 100.

`GET /events/{external_ref}` returns the event and latest receipt. A reference
may be a Code-owned stable reference or `wordpress:post:{id}` for observation.

`PUT /events/{external_ref}` accepts:

- `operation_id` (required, maximum 191 bytes)
- `expected_operation_id` for updates after the first operation
- `request_digest` when the caller wants the plugin to verify its digest
- `existing_post_id` to adopt one exact Event post
- `status`: `draft`, `publish`, or `null`
- `fields`: bounded event fields
- `attachment_id` and `additional_media_ids`
- `code_submission_id`

New events require `fields.title` and `fields.start_at` and are created as
drafts before any requested status transition. The durable receipt reports
each applied effect and whether public state changed.

`fields.start_at` and `fields.end_at` accept strict `YYYY-MM-DD` values when the
source supplies no time. The plugin preserves that date-only precision in event
snapshots and frontend output instead of presenting midnight as an event time.

`GET /operations/{operation_id}` returns the latest durable receipt for an
operation ID. Code.Hexa can use this after a timeout instead of scraping the
site or guessing whether a write succeeded.

## Hosts

`PATCH /hosts/{id}` accepts only:

- `instagram_handle`
- `auto_approve`
- `host_code`

The response never returns the host code, only `host_code_configured`.
