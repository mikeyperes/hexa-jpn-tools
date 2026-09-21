# Search Display

## Namespace And Folder

```text
src/SearchDisplay/
Hexa\PluginCore\SearchDisplay
```

## Purpose

`SearchDisplayRenderer` is the single source of truth for public WordPress search-form markup. It provides five selectable designs:

- `icon-reveal`: compact magnifier that expands in place.
- `overlay`: button that opens an accessible search dialog.
- `pill`: always-visible rounded search field.
- `underline`: minimal field with a focus underline.
- `command`: prominent field with a submit button and keyboard hint.

All styles submit a native WordPress GET request with the query in `name="s"`. This module does not fetch or display AJAX search suggestions.

## Host Responsibilities

The consuming plugin owns:

- the saved default style, accent, and placeholder options;
- the shortcode name and shortcode registration;
- the admin setting capability and nonce;
- the template picker and save action.

The consuming plugin must call `SearchDisplayRenderer::render()` for both each admin preview and the live shortcode. It must not copy the renderer markup, CSS, SVG, or JavaScript. A host using `SearchQuery` in shortcode-only scope must pass the same hidden request marker to every public form and preview.

## Example

```php
use Hexa\PluginCore\SearchDisplay\SearchDisplayRenderer;

echo SearchDisplayRenderer::render(
    [
        'style'       => 'command',
        'accent'      => '#1f8a5b',
        'placeholder' => 'Search the publication...',
        'label'       => 'Search',
        'action_url'  => home_url( '/' ),
        'hidden_fields' => [ 'example_search' => '1' ],
    ]
);
```

Supported renderer arguments are `style`, `accent`, `placeholder`, `action_url`, `label`, `radius`, `id`, and `hidden_fields`. Hidden field names are sanitized, scalar values are escaped, and at most ten fields are rendered.

## Search Namespace Boundaries

Use `SearchDisplay` for a public search form that navigates to WordPress search results.

Use `SearchQuery` to configure how an explicitly eligible native WordPress search-results query matches terms and sources.

Use `SmartSearch` for an AJAX typeahead or content picker that displays suggestions while a user types. They are intentionally separate namespaces and protocols.

## Testing

Run:

```bash
php tests/search-display-renderer.php
```

In a host plugin, also verify the exact visible workflows:

1. Render every admin picker preview.
2. Save a template and reload the settings tab.
3. Render the host shortcode on a front-end page.
4. Submit a query and confirm navigation to `/?s=query`.
5. For `overlay`, verify click, Cmd/Ctrl+K, Escape, backdrop close, and focus behavior.
