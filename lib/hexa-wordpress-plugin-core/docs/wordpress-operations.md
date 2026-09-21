# WordPress Operations

Namespace: `Hexa\PluginCore\WordPressOperations`

Folder: `src/WordPressOperations/`

These host-neutral services return structured status and action results. Hosts own capability/nonce guards, confirmation UI, action selection, and scheduling.

- `UpdateOperations` refreshes discovery and uses native upgrader APIs with a quiet automatic skin. It never disables maintenance mode.
- `AutoUpdatePolicy` applies future core/plugin/theme policy through native site options and compares canonical sorted plugin/theme lists.
- `DiscussionOperations` closes future defaults and existing comments/pings in bounded batches, and permanently deletes comments through `wp_delete_comment(..., true)`. Explicit lists continue beyond 100 IDs. All-record runs stop when a batch makes no progress; item reports and `unprocessed_ids` are bounded and advertise truncation.
- `PermalinkOperations` preserves the current structure for `repair('')`, hard-flushes, and requires non-empty verified rules.

```php
$result = (new UpdateOperations())->update_all();
$result = (new DiscussionOperations())->close_comments_and_pings();
```

Action results contain `success`, `action`, `before`, `after`, `counts`, `items`, and `messages`; discussion mutations also include `items_truncated`, bounded `unprocessed_ids`, `unprocessed_ids_truncated`, and `stopped_no_progress`. Status also includes `available`. Empty discussion ID arrays mean all applicable records.

Run `php tests/wordpress-operations.php`. Hosts must separately test their protected AJAX/UI layer and exact environment.
