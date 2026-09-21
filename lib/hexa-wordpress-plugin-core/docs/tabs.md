# Tabs Namespace

Namespace:

```text
Hexa\PluginCore\WpAdminTabs
```

Folder:

```text
src/WpAdminTabs/
```

## Purpose

The tabs namespace standardizes admin tab definitions and rendering.

Host plugins decide which tabs exist. The core provides a consistent registry, definition format, and an automatic Hexa core documentation tab.

## Required Pattern

Each tab has:

- ID
- label
- renderer
- optional capability
- optional status metadata, such as deprecated

Tab IDs are lowercase slugs:

```text
overview
shortcodes
settings
update-center
```

Do not use labels as IDs. Do not include emojis in IDs.

## Automatic Hexa Core Tab

Classes:

```text
CoreTabConfig
CoreTabModule
CoreTabRenderer
```

Host dashboards expose two filters:

```php
$tabs = apply_filters( 'example_dashboard_tabs', $tabs );

if ( apply_filters( 'example_dashboard_render_tab', false, $tab_id ) ) {
    return;
}
```

Then the host plugin registers the core tab:

```php
( new CoreTabModule(
    new CoreTabConfig(
        [
            'tabs_filter'   => 'example_dashboard_tabs',
            'render_filter' => 'example_dashboard_render_tab',
            'core_root'     => __DIR__ . '/lib/hexa-wordpress-plugin-core',
            'readme_path'   => __DIR__ . '/lib/hexa-wordpress-plugin-core/README.md',
            'library_path'  => __DIR__ . '/HEXA_PLUGIN_CORE_LIBRARY.md',
        ]
    )
) )->register();
```

The core tab renders:

- README file locations
- friendly explanation
- technical explanation
- README-style guide
- activity log demo

## Reusable Host Dashboard Shell

Class:

```text
HostTabsRenderer
```

Use this renderer for the complete visible host navigation shell. Do not duplicate host-level tab markup, AJAX loaders, navigation history, or sidebar state code in the consuming plugin.

For a grouped sidebar, pass:

- `layout => sidebar`
- `groups` as ordered labels and tab ID lists
- `sidebar_collapsible => true` to render the icon-only collapse control
- `sidebar_collapsed => false` for the initial state
- `sidebar_persist => true` to restore state from `localStorage`
- `sidebar_identity` for optional host plugin and Core name/version links rendered above the navigation

Persistence is scoped by `root_id`. The optional `sidebar_identity` contract is rendered and styled entirely by Core, opens repository links in a new tab with safe relationship attributes, hides in collapsed mode, and wraps on mobile. The default expanded rail is 214px and the collapsed rail is 44px. The rail remains in normal document flow instead of sticking to the viewport, deliberately uses page scrolling instead of an internal vertical scrollbar, and wraps mobile links without horizontal scrolling.

```php
( new HostTabsRenderer() )->render(
    [
        "tabs"                => $tabs,
        "active"              => $active,
        "page_url"            => $page_url,
        "ajax_action"         => "example_load_tab",
        "nonce"               => $nonce,
        "root_id"             => "example-plugin-tabs",
        "panel_id"            => "example-plugin-panel",
        "layout"              => "sidebar",
        "groups"              => $groups,
        "sidebar_identity"    => [
            "plugin_name"     => "Example Plugin",
            "current_version" => $installed_version,
            "github_version"  => $github_version,
            "github_url"      => "https://github.com/example/example-plugin",
            "core_name"       => "Hexa WP Core",
            "core_version"    => $core_version,
            "core_github_url" => "https://github.com/mikeyperes/hexa-wordpress-plugin-core",
        ],
        "sidebar_collapsible" => true,
        "sidebar_collapsed"   => false,
        "sidebar_persist"     => true,
        "render_callback"     => [ $dashboard, "render_tab" ],
    ]
);
```

The host remains responsible for registering every route, authorizing and serving the AJAX response, and rendering each tab body. Core remains responsible for shell HTML, CSS, navigation JavaScript, accessibility attributes, loading state, and history.
