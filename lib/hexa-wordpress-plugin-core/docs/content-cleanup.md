# Content Cleanup

Namespace:

```text
Hexa\PluginCore\ContentCleanup
```

Use this namespace when a host plugin needs to detect old WordPress content, report backup files, clean backup files, or delete selected articles with optional associated media cleanup through wp-admin.

## Classes

- `ContentCleanupConfig`: owns host-specific labels, action names, nonce settings, allowed post types, statuses, default filters, fixed report mode, detection rules, limits, and protected post IDs.
- `ContentCleanupScanner`: normalizes criteria, queries WordPress content, applies detection rules, marks protected rows, and performs trash or permanent delete actions.
- `ContentCleanupAjaxController`: registers scan, trash, and delete AJAX actions through `WpAdminAjax\AjaxActionRegistry`.
- `ContentCleanupRenderer`: renders the filter UI, results table, edit links, destructive action buttons, and Hexa Core Log Type 1 activity log.
- `BackupCleanupConfig`: owns host-specific backup roots, allowed extensions, action names, and nonce settings.
- `BackupCleanupScanner`: scans configured backup roots, reports backup metadata, and deletes only files still matching configured locations.
- `BackupCleanupAjaxController`: registers backup scan/delete AJAX actions.
- `BackupCleanupRenderer`: renders the backup table, row delete buttons, loaders, and live activity log.
- `ArticleMediaCleanupConfig`: owns post cleanup post types, statuses, keep-recent defaults, limits, action names, and nonce settings.
- `ArticleMediaCleanupScanner`: filters posts, builds deletion plans, detects featured/inline/gallery media attachments, performs permanent deletion, and verifies each post and attachment no longer exists.
- `ArticleMediaCleanupAjaxController`: registers article scan/delete AJAX actions.
- `ArticleMediaCleanupRenderer`: renders filters, keep-most-recent, select-all, delete-selected, row delete buttons, per-post/per-media progress states, and a live activity log.

## Host Responsibilities

- Pass plugin-specific AJAX action names.
- Pass a plugin-specific nonce action and nonce field.
- Limit `post_types` to the content the tool is allowed to manage.
- Keep destructive actions behind a capability such as `manage_options`.
- Add plugin-specific protected page IDs when needed.
- Set `show_filters` to `false` when the host wants a fixed report instead of a visible filter form.
- Pass `detection_rules` when the report should show only matching content and display yellow/red row flags.
- For backup cleanup, pass explicit `locations` with paths and allowed extensions. Do not pass broad roots such as `ABSPATH`.
- For article cleanup, keep media deletion off by default. The visible "Delete associated media" checkbox must be explicit.

Core always protects the WordPress front page, posts page, and privacy policy page.

## Example

```php
use Hexa\PluginCore\ContentCleanup\ContentCleanupAjaxController;
use Hexa\PluginCore\ContentCleanup\ContentCleanupConfig;
use Hexa\PluginCore\ContentCleanup\ContentCleanupRenderer;

$config = new ContentCleanupConfig([
    'root_id'                => 'example-cleanup',
    'title'                  => 'Cleanup',
    'description'            => 'Detect old pages and clean them up through guarded AJAX actions.',
    'capability'             => 'manage_options',
    'nonce_action'           => 'example_cleanup',
    'nonce_field'            => 'nonce',
    'scan_action'            => 'example_cleanup_scan',
    'trash_action'           => 'example_cleanup_trash',
    'delete_action'          => 'example_cleanup_delete',
    'post_types'             => [ 'page' => 'Pages' ],
    'default_post_type'      => 'page',
    'default_status'         => 'publish',
    'default_published_days' => 365,
    'default_limit'          => 50,
    'show_filters'           => false,
    'count_label'            => 'Reported',
    'detection_rules'        => [
        [
            'id'                 => 'home_not_front',
            'label'              => 'Home',
            'tone'               => 'warning',
            'terms'              => [ 'home' ],
            'fields'             => [ 'title', 'slug' ],
            'exclude_option_ids' => [ 'page_on_front' ],
            'description'        => 'Contains home but is not the assigned WordPress front page.',
        ],
        [
            'id'          => 'old_delete',
            'label'       => 'Old/Delete',
            'tone'        => 'danger',
            'terms'       => [ 'old', 'delete' ],
            'fields'      => [ 'title', 'slug' ],
            'description' => 'Contains old or delete in the title or slug.',
        ],
    ],
]);

( new ContentCleanupAjaxController( $config ) )->register();
( new ContentCleanupRenderer( $config ) )->render();
```

## UI Contract

The renderer shows:

- filter criteria
- detected content rows
- row flags from detection rules
- title with slug below it
- status
- date published
- date modified
- edit link that opens in a new tab
- red move-to-trash action
- red permanent-delete action
- live dark activity log below the table

The UI updates through AJAX without page refreshes. Server responses include activity log entries, and the browser appends each step into the log.

## Backup File Cleanup

Backup cleanup is intended for configured plugin backup folders only. The delete AJAX endpoint accepts a row ID, rescans configured locations, and deletes the file only if it is still present under one of those configured roots and has an allowed extension.

```php
use Hexa\PluginCore\ContentCleanup\BackupCleanupAjaxController;
use Hexa\PluginCore\ContentCleanup\BackupCleanupConfig;
use Hexa\PluginCore\ContentCleanup\BackupCleanupRenderer;

$backup_config = new BackupCleanupConfig([
    'root_id'       => 'example-backup-cleanup',
    'title'         => 'Backup Files',
    'nonce_action'  => 'example_cleanup',
    'scan_action'   => 'example_backup_scan',
    'delete_action' => 'example_backup_delete',
    'locations'     => [
        'updraftplus' => [
            'name'       => 'UpdraftPlus',
            'path'       => WP_CONTENT_DIR . '/updraft/',
            'extensions' => [ 'zip', 'gz', 'sql' ],
        ],
    ],
]);

( new BackupCleanupAjaxController( $backup_config ) )->register();
( new BackupCleanupRenderer( $backup_config ) )->render();
```

## Article And Media Cleanup

Article cleanup filters posts by post type, status, search term, limit, and "keep most recent X" offset. The UI supports select-all, selected row deletion, and individual row deletion. Every visible post and detected media item moves through pending, deleting, deleted, kept, or failed states without removing the evidence row. By default, article deletion does not delete media. If the visible media checkbox is enabled, Core deletes detected featured images plus inline/gallery attachment IDs after the article is deleted and verifies each record is gone.

```php
use Hexa\PluginCore\ContentCleanup\ArticleMediaCleanupAjaxController;
use Hexa\PluginCore\ContentCleanup\ArticleMediaCleanupConfig;
use Hexa\PluginCore\ContentCleanup\ArticleMediaCleanupRenderer;

$article_config = new ArticleMediaCleanupConfig([
    'root_id'             => 'example-article-cleanup',
    'title'               => 'Article & Media Cleanup',
    'nonce_action'        => 'example_cleanup',
    'scan_action'         => 'example_article_scan',
    'delete_action'       => 'example_article_delete',
    'post_types'          => [ 'post' => 'Posts' ],
    'default_keep_recent' => 10,
]);

( new ArticleMediaCleanupAjaxController( $article_config ) )->register();
( new ArticleMediaCleanupRenderer( $article_config ) )->render();
```
