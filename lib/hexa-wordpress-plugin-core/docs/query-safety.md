# Query Safety

Namespace: `Hexa\PluginCore\QuerySafety`

Folder: `src/QuerySafety/`

## Purpose

`QueryEligibility` rejects requests where a host mutation would be unsafe because its paired SQL filters are suppressed, or because the request is admin, WP-CLI, AJAX, cron, REST, or XML-RPC. Its public methods authorize only the main query or a secondary query carrying an exact host-owned marker; request context alone never authorizes mutation.

`StaticFrontPageQueryGuard` prevents a `pre_get_posts` callback from making WordPress's configured static front page ineligible to return its page. `CoreBootstrap` registers one exact-object baseline capture on `parse_query` immediately, before `pre_get_posts` can mutate the query. It schedules the final protector once for all hosts at `wp_loaded`, after ordinary query-hook registrations, and repairs only the captured query's `page_id`, `p`, and page eligibility at the latest numeric `pre_get_posts` priority.

The public `is_static_front_page_main_query()` predicate rejects non-main, non-page, admin, WP-CLI, AJAX, cron, REST, XML-RPC, missing-ID, posts-front, and unrelated singular queries before it reads the two front-page options.

## Host Responsibilities

Hosts must call the predicate before loading settings or providers, changing query variables, or attaching SQL filters in any callback that can handle a home or front-page query. Hosts must still provide exact archive, search, or explicit secondary-query markers. The final Core repair does not make broad query callbacks safe or inexpensive.

```php
use Hexa\PluginCore\QuerySafety\QueryEligibility;
use Hexa\PluginCore\QuerySafety\StaticFrontPageQueryGuard;

public function filter_queries( \WP_Query $query ): void {
    if ( ! QueryEligibility::allows_main_or_explicit_filtered_frontend_query(
        $query,
        'example_query_context',
        [ 'home', 'author' ]
    )
        || StaticFrontPageQueryGuard::is_static_front_page_main_query( $query )
    ) {
        return;
    }

    // Apply host behavior only after its remaining cheap context guards pass.
}
```

Use `allows_main_filtered_frontend_query()` when the feature must never touch a secondary query. Use `allows_main_or_explicit_filtered_frontend_query()` only with a private host marker and strict value allowlist. The paired `posts_*` callback must check that same marker and values before adding SQL. Never infer a secondary loop's purpose from global `is_front_page()`, `is_home()`, `is_singular()`, or `is_author()` state.

When a repair is required, Core fires `hexa_plugin_core_static_front_page_query_repaired`. The callback receives the `WP_Query` object and an array of changed variables with `from` and `to` values. A compatibility plugin can return `false` from `hexa_plugin_core_should_protect_static_front_page_query` to disable repair for that exact query.

The invariant does not rewrite arbitrary SQL clauses. A callback registered later at the same numeric priority can still run after it, so host-side early guards remain mandatory.

## Testing

Run:

```bash
php tests/static-front-page-query-guard.php
php tests/package-integrity.php
```

The regression test verifies idempotent automatic registration across multiple host boot attempts, parse-before-mutation capture, final-priority repair, exact query matching, same-object reparse replacement, strict secondary markers, suppressed-filter rejection, preservation of safe post types, and zero front-page option reads for unrelated queries.
