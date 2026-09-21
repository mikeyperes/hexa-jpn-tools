# Implementation Checklist

Use this before adding code to any plugin that consumes `hexa/plugin-core`.

## Before Writing Code

1. Confirm the change belongs in shared core or in the host plugin.
2. If it is shared behavior, use `Hexa\PluginCore`.
3. If it is site/plugin-specific behavior, keep it in the host plugin namespace.
4. Read `docs/folder-map.md`.
5. Read `docs/setup-protocol.md`.
6. Read the namespace-specific doc for the folder being changed.

## Naming Checklist

- Repository folder is `hexa-wordpress-plugin-core`.
- Composer package is `hexa/plugin-core`.
- Shared namespace starts with `Hexa\PluginCore\`.
- Folder and namespace match exactly.
- Host plugin slug does not appear in shared core classes.

## Required Class Placement

- New module interface: `src/CoreContracts/`
- Module bootstrap/lifecycle: `src/CoreBootstrap/`
- Activity logs: `src/ActivityLog/`
- Dashboard tabs: `src/WpAdminTabs/`
- Shortcode registry/testing/display metadata: `src/ShortcodeRegistry/`
- Critical page and navigation menu blueprints: `src/SiteStructure/`
- Host plugin GitHub/update shared configuration: `src/PluginUpdates/`
- Vendored Hexa WordPress Plugin Core update checks: `src/CorePackageUpdates/`
- WP admin-AJAX action registration/request handling: `src/WpAdminAjax/`
- Generic helper/value object: `src/CoreRuntime/`

## Required Documentation

For every new public class, update a relevant doc with:

- purpose
- namespace
- expected host context values
- example usage
- test method

Namespace docs:

- `docs/activity.md`
- `docs/bootstrap.md`
- `docs/contracts.md`
- `docs/host-plugin-adapter-template.md`
- `HEXA_PLUGIN_CORE_LIBRARY.md`
- `docs/shortcodes.md`
- `docs/site-structure.md`
- `docs/new-plugin-master-checklist.md`
- `docs/wp-admin-ajax.md`
- `docs/support.md`
- `docs/tabs.md`
- `docs/updater.md`

## Required Tests Or Verification

At minimum:

```bash
composer validate --strict
find src -name '*.php' -print0 | xargs -0 -n1 php -l
```

When integrated into a WordPress plugin, also verify:

- the host plugin still activates
- the core bootstrap runs once
- admin tabs still load
- AJAX endpoints still pass nonce/capability checks
- shortcode test rows render and can be run one at a time
