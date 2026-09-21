# UI Namespace

Namespace:

```text
Hexa\PluginCore\WpAdminComponents
```

Folder:

```text
src/WpAdminComponents/
```

## Purpose

The UI namespace owns shared visual primitives for Hexa plugin admin screens.

Host plugins should use these primitives instead of rebuilding card, button, tooltip, and collapsible markup differently in each plugin.

## Classes

```text
CoreUi
MediaGalleryDetailsRenderer
ScopedCssOverride
ColorControl
ColorPalette
ElementorPaletteDetector
DetailedColorPicker
FontFamilyControl
TypographyPreservationControl
TypographyControl
TemplateSelectionControl
```

## Components

```text
render_assets()
card()
subcard()
collapsible()
pill()
tooltip()
copy_button()
MediaGalleryDetailsRenderer::render()
ScopedCssOverride::render()
ColorControl::render()
ColorPalette::render()
ElementorPaletteDetector::render()
DetailedColorPicker::render()
FontFamilyControl::render()
TypographyPreservationControl::render()
TypographyControl::render()
TemplateSelectionControl::render()
```

## Example

```php
use Hexa\PluginCore\WpAdminComponents\CoreUi;

CoreUi::render_assets();

echo CoreUi::card(
    [
        'title'     => 'Plugin Status',
        'body_html' => '<p>All systems are healthy.</p>',
        'meta_html' => CoreUi::pill( 'Healthy', 'success' ),
    ]
);
```

Selectable media gallery details example:

```php
use Hexa\PluginCore\WpAdminComponents\MediaGalleryDetailsRenderer;

echo MediaGalleryDetailsRenderer::render(
    $acf_gallery_value,
    [
        'title'       => 'Details',
        'persist_key' => 'profile-photo-details',
    ]
);
```

The renderer accepts attachment IDs, ACF image arrays, or attachment objects. It lists the full image and every generated intermediate size, renders each URL as a selectable new-tab link, uses `DynamicButton` for clipboard feedback, and provides per-image plus select-all controls. Hosts own the ACF field and attach the renderer from the appropriate field hook.

Visual template selector example:

```php
use Hexa\PluginCore\WpAdminComponents\TemplateSelectionControl;

echo TemplateSelectionControl::render(
    [
        'id'      => 'profile-card-template',
        'name'    => 'profile_card_template',
        'value'   => $settings['profile_card_template'] ?? 'compact',
        'title'   => 'Profile card design',
        'columns' => 1,
        'custom_control' => 'toggle',
        'custom' => [
            'label'              => 'No plugin design',
            'toggle_label'       => 'No plugin design',
            'toggle_description' => 'Disable the template choices and provide your own markup and styles.',
        ],
        'templates' => [
            'compact' => [
                'label'        => 'Compact',
                'description'  => 'A restrained horizontal card.',
                'preview_html' => $renderer->render_preview( 'compact' ),
            ],
        ],
        'input_class' => 'plugin-setting',
    ]
);
```

Core renders the requested one-to-four-column desktop grid and scales trusted host preview markup inside fixed-height noninteractive viewports. The default custom/no-design state is a choice card; set `custom_control` to `toggle` to put it above the grid, disable every template choice while active, and restore the previous selection when switched off. Hosts own persistence and frontend behavior. Listen for the bubbling `hexa-template-selection-change` event to save a choice. Custom mode must be handled by the host by omitting automatic markup, shortcode output, and plugin styling unless an explicit named template is requested.

Scoped CSS editor or reference example:

```php
use Hexa\PluginCore\WpAdminComponents\ScopedCssOverride;

echo ScopedCssOverride::render(
    [
        'title'        => 'Header CSS override',
        'selector'     => 'body .example-header',
        'instructions' => [ 'Keep every rule inside this selector.' ],
        'html_example' => '<header class="example-header">...</header>',
        'css_example'  => "body .example-header {\n  color: #111827;\n}",
        'open'         => false,
    ]
);
```

## Query-Backed Collapsibles

`CoreUi::collapsible()` automatically gives every titled section a stable query key. Opening or closing a section updates the current URL with one comma-delimited `hpc_open` parameter, and Core restores those sections after a full refresh or an AJAX tab load.

Typography preservation disables and visibly mutes every editor that can mutate the inherited site value. For Core color fields, this includes the picker, hex input, stored value input, brand import, and inherited-color action; the left-aligned site-value toggle remains enabled.

Open collapsibles use the shared Core highlight state: a pale summary background and a slightly stronger boundary. Hosts should not add separate open-card colors.

```php
echo CoreUi::collapsible(
    [
        'title'     => 'Article first-letter drop cap',
        'body_html' => '<p>Settings...</p>',
        'query_key' => 'article-first-letter-drop-cap', // Optional stable override.
    ]
);
```

The title slug is used when `query_key` is omitted. Set `query_state => false` only for a section whose open state must never appear in the URL. `persist_key` remains available for local-storage fallback; query-string state is authoritative whenever `hpc_open` is present.

## Rule

If a host plugin needs cards, subcards, collapsibles, tooltips, status pills, copy buttons, visual template selection, selectable media gallery details, scoped CSS override editors and references, brand-aware isolated color controls, combined typography fields, typography preservation, saved color palettes, or Elementor palette detection, add the missing parameter or helper here first.
