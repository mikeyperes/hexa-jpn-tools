# Plugin Checks

Namespace:

```text
Hexa\PluginCore\PluginChecks
```

Folder:

```text
src/PluginChecks/
```

## Purpose

Plugin Checks is the shared Hexa WP Core feature for dependency/plugin requirement tabs.

Host plugins pass a list of plugin definitions. Core checks whether each plugin is installed, active, up to date, and optionally aligned with the expected auto-update state. It can render either the original plugin-check cards or a policy-aware inventory table with Core collapsible sections, explicit Policy, Installation, and Status columns, AJAX plugin actions, and an activity log.

## Public Classes

- `PluginCheckDefinition`: normalizes one plugin requirement.
- `PluginCheckService`: resolves installed/active/update status and calls shared install/activate helpers.
- `PluginChecksRenderer`: renders the admin UI, summary pills, plugin cards, dynamic buttons, and activity log.
- `PluginChecksAjaxController`: registers AJAX actions for status refresh, update-cache refresh, install-and-activate, and activate.
- `PluginInventoryRenderer`: renders reusable expanded-by-default collapsible inventory cards with Plugin, Policy, Installation, Status, Auto-Update, Version, optional Source, and Action columns. When Source is disabled, its value appears beneath the plugin path in a fixed seven-column layout that stacks labeled cells below 900px. Forbidden rows expose Deactivate when active, Activate when inactive, and Delete when removable.
- `PluginInventoryAjaxController`: registers AJAX actions for the inventory renderer and returns refreshed table HTML.

## Definition Shape

```php
[
    'id'          => 'classic-editor',
    'name'        => 'Classic Editor',
    'plugin_file' => 'classic-editor/classic-editor.php',
    'slug'        => 'classic-editor',
    'source'      => 'wordpress_org',
    'required'    => true,
    'recommended' => true,
    'auto_update_expected' => true,
    'checks'      => [
        'installed'   => true,
        'active'      => true,
        'up_to_date'  => true,
        'auto_update' => true,
    ],
    'notes'       => 'Required for the editorial workflow.',
]
```

Supported sources:

- `wordpress_org`: installs through the WordPress.org plugin API using `wp_org_slug` or `slug`.
- `github`: installs from a GitHub repository using `github_repo` and optional `github_branch`.
- `pro`: shows the configured `download_url` and does not attempt AJAX install.
- `manual`: shows the configured `download_url` or the WordPress plugin upload page.
- `must_use`: checks a must-use plugin from `get_mu_plugins()` and treats presence as active.
- `dropin`: checks a WordPress drop-in from `get_dropins()` or `_get_dropins()` and treats presence as active.

## Host Integration

Register AJAX:

```php
( new \Hexa\PluginCore\PluginChecks\PluginChecksAjaxController(
    my_plugin_required_plugins(),
    [
        'capability'    => 'update_plugins',
        'nonce_action'  => 'my_plugin_admin',
        'nonce_field'   => 'nonce',
        'action_prefix' => 'my_plugin_plugin_checks',
    ]
) )->register();
```

Render the tab:

```php
echo ( new \Hexa\PluginCore\PluginChecks\PluginChecksRenderer() )->render(
    my_plugin_required_plugins(),
    [
        'title'         => 'Plugin Checks',
        'description'   => 'Required plugin health for this integration.',
        'nonce'         => wp_create_nonce( 'my_plugin_admin' ),
        'nonce_field'   => 'nonce',
        'action_prefix' => 'my_plugin_plugin_checks',
    ]
);
```

Render a reusable inventory section:

```php
( new \Hexa\PluginCore\PluginChecks\PluginInventoryAjaxController(
    my_plugin_required_plugins(),
    [
        'capability'    => 'install_plugins',
        'nonce_action'  => 'my_plugin_admin',
        'nonce_field'   => 'nonce',
        'action_prefix' => 'my_plugin_inventory',
        'renderer_args' => [
            'title'       => 'Plugin Status',
            'persist_key' => 'my-plugin-status',
            'columns'     => [
                'auto_update' => true,
                'version'     => true,
                'source'      => true,
            ],
        ],
    ]
) )->register();

echo ( new \Hexa\PluginCore\PluginChecks\PluginInventoryRenderer() )->render(
    my_plugin_required_plugins(),
    [
        'title'         => 'Plugin Status',
        'description'   => 'Install and verify the plugins this integration uses.',
        'nonce'         => wp_create_nonce( 'my_plugin_admin' ),
        'nonce_field'   => 'nonce',
        'action_prefix' => 'my_plugin_inventory',
        'persist_key'   => 'my-plugin-status',
        'open'          => true,
        'columns'       => [
            'auto_update' => true,
            'version'     => true,
            'source'      => true,
        ],
    ]
);
```

## Behavior

- Missing WordPress.org and GitHub plugins get an AJAX **Install and activate** action.
- Installed but inactive plugins get an AJAX **Activate** action.
- Pro/manual plugins show an external download/upload link.
- The **Refresh checks** button refreshes the WordPress plugin update cache through AJAX.
- The **Install and activate missing** button processes visible install/activate actions sequentially with no page refresh.
- The inventory renderer puts each section in a Core collapsible card, expanded by default, with memory persistence through `persist_key`.
- The Policy column distinguishes `Required: satisfied`, `Required: missing`, `Required: inactive`, `Forbidden: installed`, `Forbidden: absent`, `Recommended`, and neutral `Not listed` states.
- A required plugin that satisfies its configured checks is green. Red is reserved for an unsatisfied required check or an installed forbidden plugin.
- Only a host definition with `should_not_contain => true` is forbidden. Installed plugins omitted from every registered policy are neutral and must never be converted into forbidden entries.
- The Installation and Status columns report factual installed/active state separately from policy. Optional or unlisted facts remain neutral unless the host explicitly configures a check.
- Forbidden definitions remain visible when absent unless the host deliberately sets `hide_compliant_forbidden => true`.
- Missing or otherwise noncompliant required rows get a red left-side marker.
- Inventory and plugin-check AJAX fragments render buttons without re-emitting shared Core or DynamicButton asset tags inside row markup.
- Run `php -n tests/plugin-inventory-policy.php` to verify the policy-state contract.
