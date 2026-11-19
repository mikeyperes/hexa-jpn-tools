# Front-End Forms

Namespace:

```text
Hexa\PluginCore\FrontendForms
```

`FieldSchema` defines the host-neutral public form types shared by WordPress plugins: text, textarea, email, URL, number, date, select, radio, checkbox, WYSIWYG, image, and file. It normalizes field keys, labels, help, placeholders, options, and required state while rejecting duplicate or unsafe keys.

`RichTextValue` sanitizes WYSIWYG input through `wp_kses_post()` and creates a plain-text projection for logs, external APIs, and line-item descriptions.

```php
use Hexa\PluginCore\FrontendForms\FieldSchema;
use Hexa\PluginCore\FrontendForms\RichTextValue;

$fields = FieldSchema::normalize( $host_service_schema );
$safe_html = RichTextValue::sanitize( wp_unslash( $_POST['description'] ?? '' ) );
$plain_text = RichTextValue::plain_text( $safe_html );
```

Core does not own a host's service fields, persistence, checkout rules, or final markup. Hosts must still verify capabilities/nonces, validate required values, escape ordinary text, and use the rich-text sanitizer only for fields explicitly declared as `wysiwyg`.

Test with:

```bash
php -n tests/frontend-form-schema.php
```

## SubmissionStore snapshots

`Hexa\PluginCore\FrontendForms\SubmissionStore::scan(int $after=0, int $limit=100, ?string $collection=null, ?int $upper=null)` returns `items`, `upper_id`, `next_after_id`, `has_more`. Pass the returned upper bound and cursor into each next page. IDs ascend; inserts after the snapshot upper bound wait until the next scan, and deleted cursor rows do not break paging. Limit clamps to 1-500; an optional collection uses the same strict ID validation as writes. A database read failure throws RuntimeException.

Hosts own authentication, authorization, response headers, and data minimization. For example, an authenticated administrative export can call `$store->scan(0,100,'weekly')` then `$store->scan($page['next_after_id'],100,'weekly',$page['upper_id'])`. This is read-only and works while collection is disabled. A complete rescan is required to reconcile deletions; it is not a change-feed or transactionally isolated snapshot.

The consuming Base Tools `tests/newsletter-http-integration.php` exercises real authenticated paging, forbidden requests, argument validation and disabled-collector reads.

## Deletion journal and list totals

`SubmissionStore::install()` also creates `hexa_form_deletions`: deletion_id (ascending event cursor), id (original subscriber ID), collection, deleted_at (UTC). `delete($collection,$id)` and `erase($email)` atomically lock matching rows, insert tombstones and remove rows. A journal/removal error rolls back the transaction; repeated deletes add no event. InnoDB tables are required. A per-store advisory lock serializes removal transactions and their commits, preventing a later commit from inserting events behind an already observed cursor. Hosts should call removal outside a caller-owned transaction.

`deleted($after=0,$limit=100)` reads deletion_id > after in ascending order; its items/next_after_id/has_more envelope caps limit to 1-500. `lists()` returns sorted list/total rows from current submissions and journal history, preserving known empty lists. Both methods throw on read failure. Hosts own permissions, schema upgrades, API timestamp names and private cache headers. Keep event IDs distinct from subscriber IDs when advancing the cursor. The journal is append-only, retains no email/name/IP/headers, and starts at migration; it cannot reconstruct older removals.

Example: `$page=$store->deleted($saved_cursor,100);` then apply each item's id/collection deletion and persist `$page['next_after_id']` only after the page succeeds. No external consumer is selected by Core. Consuming integration tests cover real administrator/password authorization, admin delete, cross-collection privacy erase, rollback and cursor order.
