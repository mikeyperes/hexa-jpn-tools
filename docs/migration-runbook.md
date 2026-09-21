# Migration runbook

## Preconditions

1. Create and publish `mikeyperes/hexa-jpn-tools` from the canonical source.
2. Take a recoverable WordPress checkpoint.
3. Record active plugins, the current Hexa WP Core report, site/home URLs, Event counts, Host counts, and the current REST route list.
4. Keep `wp-content/plugins/jpn-structure` and its backup directory intact.

## Cutover

1. Install `hexa-jpn-tools` into the canonical plugin folder.
2. Activate `hexa-jpn-tools` once.
3. Activation creates or updates the receipt table, the Host role, the integration role/capability, and the cutover record.
4. If `jpn-structure/initialization.php` was active, activation silently deactivates it and records that prior state. It does not delete or rename the legacy directory.
5. Flush rewrite rules through the activation hook.

## Acceptance

1. Confirm only `hexa-jpn-tools/hexa-jpn-tools.php` is active among the two JPN plugins.
2. Run `tests/wp-bootstrap-smoke.php`.
3. Run `tests/wp-rest-fixture.php`; confirm all temporary records are removed.
4. Confirm `/health`, `/manifest`, and `/settings` with the dedicated integration user and Application Password.
5. Confirm an anonymous request is rejected.
6. Compare Event, Host, area, shortcode, notification, and frontend counts/rendering with the pre-cutover evidence.
7. Confirm the homepage and representative Event, Service, Profile, author archive, and Notifications Dashboard URLs return normally.
8. Configure Code.Hexa to use the REST contract and verify one draft-first event receipt end to end.

## Rollback

1. Deactivate Hexa JPN Tools.
2. Reactivate `jpn-structure/initialization.php`.
3. Restore the checkpoint only if data behavior changed; the new plugin otherwise leaves legacy posts, meta, options, roles, and shortcodes intact.
4. Keep the binding table and cutover record for diagnosis unless the checkpoint is restored.

Do not remove either legacy plugin directory or the two JPN MU-plugin files in
the initial release. Remove an obsolete owner only after the full acceptance
period proves its replacement on the live site.
