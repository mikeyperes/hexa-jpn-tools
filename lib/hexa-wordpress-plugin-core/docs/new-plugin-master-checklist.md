# New Plugin Master Checklist

Use this checklist before integrating any WordPress plugin into the Hexa plugin system. Keep one line item per finding in implementation notes so future agents can review quickly.

- Plugin identity: Confirm folder slug, main plugin file, plugin header, text domain, GitHub repository, branch, and canonical plugin basename.
- Version source: Locate every version declaration and decide the single source of truth before editing.
- Namespace ownership: Confirm plugin-specific namespaces stay in the host plugin and shared code stays under `Hexa\PluginCore\`.
- Bootstrap path: Confirm the plugin loads Composer or the vendored Hexa core autoloader before calling core classes.
- Core context: Confirm the plugin creates a `PluginContext` with slug, basename, version, paths, admin page, capability, and GitHub repository.
- Admin tabs: Identify the existing tab registration/rendering system and wire compatible hooks for `WpAdminTabs`.
- Hexa core tab: Confirm the automatic Hexa Core tab renders README, technical docs, UI examples, activity log examples, and core package status.
- WP admin UI: Inventory custom cards, buttons, toggles, tooltips, collapsibles, notices, and copy buttons that should use `WpAdminComponents\CoreUi`.
- AJAX endpoints: Inventory every `wp_ajax_*` and `wp_ajax_nopriv_*` action, nonce, capability, request field, response shape, and error path.
- AJAX migration: Move reusable admin AJAX callbacks to `WpAdminAjax\AjaxActionRegistry` and read request data through `AjaxRequest`.
- Smart search: Identify user, post, CPT, taxonomy, or external searches that should use `SmartSearch` or the same X-Search response shape.
- Activity logs: Identify imports, updaters, tests, background tasks, and destructive actions that need transient, permanent, or page-only logs.
- Shortcodes: List every shortcode tag, callback, parameters, description, examples, test method, and real rendered output.
- Shortcode display: Use `ShortcodeRegistry\ShortcodeDisplayRenderer` for admin shortcode lists instead of hand-built tables.
- Updater: Confirm GitHub updater config, update check flow, direct install flow, progress log, version comparison, and rollback safety.
- Core updater: If the plugin vendors Hexa core, confirm `CorePackageUpdates` compares the bundled core `VERSION` against GitHub.
- Plugin provisioning: Identify one-click installs or companion plugins that should use `PluginProvisioning`.
- Credentials: Identify API keys, tokens, secrets, and passwords that should use `CredentialVault`, masked display, save, test, and delete actions.
- System environment: Identify PHP/WP constants, INI values, memory limits, upload limits, cron status, and server checks that should use `SystemEnvironment`.
- wp-config writes: Identify any `wp-config.php` or `ini_set()` mutation and move it to `WpConfigFile`.
- Cron tasks: Inventory WP-Cron schedules, callbacks, status screens, and cleanup actions for `WpCronTasks`.
- Log files: Inventory debug/error log readers, truncators, classifiers, and viewers for `LogFiles`.
- ACF fields: Identify local field groups, option pages, field keys, field names, and whether fields belong to the plugin or another owner.
- Settings/options: List option names, defaults, sanitizers, autoload behavior, migrations, and cleanup rules.
- Media/uploads: Identify image uploads, generated files, attachment IDs, cropping rules, physical files, and URL display requirements.
- Frontend hooks: Inventory actions, filters, shortcodes, block/render hooks, schema output, footer/header injections, and enqueue behavior.
- Admin cleanup: Inventory UI-hiding or unregistering behavior and separate visual hiding from deregistration when possible.
- Dependencies: Confirm required plugins, optional integrations, CPT availability, functions/classes checked, and admin recovery links.
- Data migrations: Identify legacy option names, post meta, user meta, ACF keys, and migration rules before changing identifiers.
- Security: Verify capabilities, nonces, escaping, sanitization, external requests, file writes, uploads, and download URLs.
- Performance: Check expensive admin page loads, external HTTP calls, uncached queries, large option reads, and work that should move behind AJAX.
- Compatibility: Check multisite, object cache, PHP version, WordPress version, Elementor/ACF/Rank Math interactions, and missing dependency behavior.
- Tests: Define exact admin URL, AJAX action tests, shortcode output tests, WP-CLI checks, and browser proof required before release.
- Git hygiene: Start from the latest clean branch, isolate dirty trees, stage only relevant files, bump version, commit as Michael Peres, and push.
- Documentation: Update README, `HEXA_PLUGIN_CORE_LIBRARY.md`, namespace docs, setup notes, and implementation notes when adding reusable behavior.
