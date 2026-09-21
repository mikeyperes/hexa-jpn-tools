# Brand Colors

Namespace:

```text
Hexa\PluginCore\BrandColors
```

Folder:

```text
src/BrandColors/
```

## Purpose

Brand color helpers keep host plugins from rebuilding the same HWS Base Tools Brand Assets lookup and color conversion logic.

## Classes

```text
BrandColorProvider
FontFamilyProvider
TemplateColorResolver
```

## Contract

- `BrandColorProvider::primary_color($fallback)` returns the HWS Base Tools primary color, normalized as a six-character hex value.
- `BrandColorProvider::secondary_color($fallback)` returns the HWS Base Tools secondary color.
- `BrandColorProvider::payload($fallback)` returns source labels, hex values, RGB strings, and the Brand Assets admin URL.
- `BrandColorProvider::elementor_payload($fallback_primary, $fallback_secondary)` returns Elementor primary/secondary color and font-family tokens when Elementor site settings exist.
- `BrandColorProvider::elementor_palette()` returns every Elementor system/custom color as normalized display rows.
- `BrandColorProvider::elementor_font_palette()` returns every valid Elementor system/custom typography entry and its global CSS variable.
- `FontFamilyProvider::options()` returns safe template, native, and unique Elementor font-source choices.
- `FontFamilyProvider::normalize_selection()` validates a saved source identifier; `css_value()` resolves it for frontend output.
- `TemplateColorResolver` normalizes Template Default, Site Primary, Site Secondary, and Custom source modes and maps one resolved accent through host-defined CSS variable transforms.
- `BrandColorProvider::rgb_string($hex)` converts a hex value to `rgb(r, g, b)`.
- `Hexa\PluginCore\WpAdminComponents\ColorControl::render()` owns the visual picker/hex/RGB/swatch/copy/import control and optional inherited-value state.
- `Hexa\PluginCore\WpAdminComponents\TemplateColorControl::render()` owns the reusable four-mode source selector, native/site/custom swatches, custom picker, reset action, and live CSS-variable preview.
- Explicit picker and hex events are resolved before the nested fallback display is synchronized, so host save listeners always receive the newly selected value regardless of script render order.
- `Hexa\PluginCore\WpAdminComponents\ElementorPaletteDetector::render()` owns the reference-only Elementor palette detector.
- `Hexa\PluginCore\WpAdminComponents\ColorPalette::render()` owns multi-color saved palettes and can compose the Elementor detector.
- `Hexa\PluginCore\WpAdminComponents\DetailedColorPicker::render()` owns the paired primary/secondary visual picker and optional font controls.
- `Hexa\PluginCore\WpAdminComponents\FontFamilyControl::render()` owns the reusable Elementor-aware font selector.
- `Hexa\PluginCore\BrandColors\FontWeightProvider` owns the validated default and 100-900 weight choices used by the optional font-picker weight field.

## Host Plugin Rule

Host plugins pass setting keys and wire AJAX persistence. Core owns the reusable visual structure and HWS brand color lookup.

For template systems, the host also owns an explicit registry of template palettes and color-bearing CSS variables. Only mapped decorative surfaces may consume those variables. Template Default must emit no override so the original selected template remains exact.

```php
use Hexa\PluginCore\BrandColors\BrandColorProvider;
use Hexa\PluginCore\WpAdminComponents\ColorControl;

$brand = BrandColorProvider::payload('#2d5277');

echo ColorControl::render([
    'key' => 'accent_color',
    'label' => 'Accent color',
    'value' => $settings['accent_color'] ?? $brand['primary_color'],
    'default' => $brand['primary_color'],
    'import_brand' => true,
]);
```

## Template Color Control

Use `TemplateColorControl::render()` for selectable designs that need the same color flow everywhere:

- Template Default: emit no override and preserve the selected template exactly.
- Site Primary: map the HWS Brand Assets primary color to the registered decorative surfaces.
- Site Secondary: map the HWS Brand Assets secondary color to those surfaces.
- Custom: map one saved custom accent to those surfaces; a blank custom value previews the selected template's native accent.

```php
use Hexa\PluginCore\WpAdminComponents\TemplateColorControl;

echo TemplateColorControl::render([
    'source_key'   => 'card_color_source',
    'source'       => $settings['card_color_source'] ?? 'template_default',
    'custom_key'   => 'card_accent_color',
    'custom'       => $settings['card_accent_color'] ?? '',
    'template_key' => 'card_template',
    'template'     => $settings['card_template'] ?? 'card-a',
    'palettes'     => [
        'card-a' => [ 'accent' => '#2563eb' ],
        'card-b' => [ 'accent' => '#d63428' ],
    ],
    'variables'    => [
        '--example-accent' => 'color',
        '--example-tint'   => 'rgba:0.10',
        '--example-ink'    => 'contrast',
    ],
    'input_class'  => 'example-setting',
    'preview_scope'=> '.example-preview',
]);
```

## Isolated Color Control

Use `ColorControl::render()` when a feature needs one color field. It is storage-agnostic and does not require the palette structure.

The control is isolated from the palette wrapper. Callers may hide any visual part with `show_picker`, `show_hex_input`, `show_rgb`, `show_hex_code`, `show_swatch`, or `show_copy`. Set `allow_inherit` and pass `inherited_value` when an empty stored value should inherit a parent color. In inherited mode, `value_input_class` belongs on the hidden persisted field while `hex_input_class` styles the editable effective value.

```php
use Hexa\PluginCore\WpAdminComponents\ColorControl;

echo ColorControl::render([
    'key' => 'button_color',
    'label' => 'Button color',
    'value' => $settings['button_color'] ?? '#3157d5',
    'hex_input_class' => 'my-plugin-button-color',
    'show_rgb' => false,
    'show_hex_code' => false,
]);
```

## Generic Color Palette

Use `ColorPalette::render()` when a feature needs a named set of saved color fields and an optional reference-only Elementor palette.

The core owns the card/grid/picker/detector UI. The host plugin only passes field keys, labels, values, and classes used by its save code.

```php
use Hexa\PluginCore\WpAdminComponents\ColorPalette;

echo ColorPalette::render([
    'title' => 'Colors',
    'description' => 'Edit any value and save the feature settings.',
    'colors' => [
        [
            'key' => 'primary_color',
            'label' => 'Primary color',
            'value' => $settings['primary_color'] ?? '#3157d5',
            'hex_input_class' => 'my-plugin-color',
        ],
        [
            'key' => 'secondary_color',
            'label' => 'Secondary color',
            'value' => $settings['secondary_color'] ?? '#111827',
            'hex_input_class' => 'my-plugin-color',
        ],
    ],
    'elementor_detector' => [
        'title' => 'Elementor palette',
        'button_label' => 'Load Elementor colors',
    ],
]);
```

## Elementor Palette Detector

Use `ElementorPaletteDetector::render()` by itself when a screen only needs the Elementor reference palette without any saved color fields.

```php
use Hexa\PluginCore\WpAdminComponents\ElementorPaletteDetector;

echo ElementorPaletteDetector::render([
    'title' => 'Elementor palette',
    'description' => 'Reference only. Copy any value into another field.',
]);
```

## Detailed Color Picker

Use this when a feature needs primary and secondary colors together, optional Elementor token import, and optional font controls.

Visual example:

```text
Detailed Color Picker
+----------------------+----------------------+
| Primary color        | Secondary color      |
| Picker Hex RGB Copy  | Picker Hex RGB Copy  |
| Swatch               | Swatch               |
+----------------------+----------------------+
[Import Elementor colors and fonts]
```

```php
use Hexa\PluginCore\BrandColors\BrandColorProvider;
use Hexa\PluginCore\WpAdminComponents\DetailedColorPicker;

$brand = BrandColorProvider::payload('#2d5277');

echo DetailedColorPicker::render([
    'title' => 'Loop item design tokens',
    'primary' => [
        'key' => 'primary_color',
        'value' => $settings['primary_color'] ?? $brand['primary_color'],
        'hex_input_class' => 'plugin-primary-color',
    ],
    'secondary' => [
        'key' => 'secondary_color',
        'value' => $settings['secondary_color'] ?? $brand['secondary_color'],
        'hex_input_class' => 'plugin-secondary-color',
    ],
    'show_primary' => true,
    'show_secondary' => true,
    'show_elementor_import' => true,
    'show_fonts' => true,
    'fonts' => [
        [
            'key' => 'primary_font_family',
            'token' => 'primary_font_family',
            'label' => 'Primary font family',
            'value' => $settings['primary_font_family'] ?? '',
        ],
        [
            'key' => 'secondary_font_family',
            'token' => 'secondary_font_family',
            'label' => 'Secondary font family',
            'value' => $settings['secondary_font_family'] ?? '',
        ],
    ],
]);
```
