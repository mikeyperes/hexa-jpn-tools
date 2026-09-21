# Typography Preservation

`Hexa\PluginCore\Typography\TypographyPreservation` defines reusable, prefix-scoped settings for preserving font family, font size, font color, and font weight while a host applies a visual template.

Use `defaults()` when defining host settings and `setting_keys()` when building persistence allowlists. Use `values()` or `preserves()` when deciding which CSS declarations the host should emit.

`Hexa\PluginCore\WpAdminComponents\TypographyPreservationControl` renders all four toggles. Place the component inside an element with `data-hpc-typography-scope` and pass target setting keys for controls that should be disabled while a value is preserved.

`Hexa\PluginCore\Typography\TemplateTypography` defines the shared three-mode contract: Original Template, Use Site Typography, and Custom Typography. Original Template emits no typography overrides. Use Site Typography inherits every supported property. Custom Typography applies only configured properties whose adjacent `Use site ...` toggle is off.

`Hexa\PluginCore\WpAdminComponents\TypographyControl` is the preferred complete UI. It composes the Core font family, font weight, color, size, and optional style fields into one control and places each preservation toggle beside the field it governs. Set `mode_control` to expose the shared three-mode selector. A host supplies setting keys, values, labels, save classes, preview-variable mappings, and optional field limits; it does not rebuild the layout or mode UI.

Optional font-style fields follow the font-family inheritance setting. When `Use site font` is active, Core disables and visibly mutes both the family selector and every associated style field so the UI matches the emitted CSS.

Each site-value toggle aligns on the left before its editor. The color toggle stays outside the muted color row, so it remains operable when the picker, hex value, and related actions are disabled.

Core adds a prefix-scoped class such as `hpc-typography-article-heading-preserve-font-family` to the scope and dispatches `hexa-typography-preserve-change`. Host plugins keep responsibility for AJAX persistence, template selectors, and the CSS declarations specific to their output.

Template integrations must keep native template declarations as CSS fallbacks. Custom preview variables are removed in Template Default and Site Typography modes so switching modes cannot leave stale inline values behind.
