# System Checks

Namespace: `Hexa\PluginCore\SystemChecks`

Use this module for plugin health checks, launch-readiness lists, schema audits, and environment checklists that need the same grouped status display across plugins.

## Renderer

`SystemChecksRenderer::render(array $items, array $args = []): string`

Each item is a flat array:

```php
[
    "category" => "WordPress",
    "label" => "Search Engine Visibility",
    "status" => "pass", // pass, fail, warn, or info
    "detail" => "Site is visible to search engines",
    "action_url" => admin_url("options-reading.php"),
    "action_label" => "Reading Settings",
]
```

## Arguments

- `id`: HTML id for the panel.
- `title`: Panel heading.
- `class`: Optional host-plugin class.
- `category_meta`: Category icon and color map keyed by category label.
- `show_progress`: Boolean progress bar toggle.

## Rules

- The host plugin owns the check collection logic.
- Hexa Core owns the grouped display, status counts, progress bar, row states, and linked actions.
- Do not fork one-off checklist HTML in host plugins. Build the flat item array, then pass it to this renderer.
