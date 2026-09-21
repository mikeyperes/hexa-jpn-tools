# Field Structures

Namespace:

```text
Hexa\PluginCore\FieldStructures
```

Use this namespace for reusable displays and status checks around ACF field groups, custom post types, taxonomies, and option-backed feature structures.

Host plugins still own the exact fields they register. Core owns the common dashboard structure used to explain, toggle, test, and document those fields.

## Classes

```text
Hexa\PluginCore\AcfFieldFactory\AcfFieldFactory
AcfFieldGroupRegistry
AcfFieldGroupSettingsStore
AcfFieldGroupAjaxController
AcfFieldGroupRenderer
AcfSettingsPanel
FieldStructureManager
FieldStructureRenderer
```

`AcfFieldGroupRegistry` is the single registration path for host-owned local ACF groups. A host supplies established group arrays or callbacks, option names, capability, and AJAX names. Core owns the `acf/init` hook, enable-state persistence, generic toggle UI, and guarded save response. When a managed group is disabled, Core also marks a database-imported copy with the same group key inactive so old ACF imports cannot bypass the toggle. `AcfSettingsPanel` embeds selected existing ACF groups inside a host dashboard tab while preserving their option storage.

## ACF Field Factory

Use `AcfFieldFactory` for reusable ACF field arrays that should keep the same shape across host plugins while still letting each plugin decide where the field is registered.

The public typed builders are `field`, `text`, `textarea`, `wysiwyg`, `url`, `email`, `number`, `date`, `select`, `toggle`, `image`, `gallery`, `group`, `repeater`, `relationship`, `user`, and `tab`. Each accepts an ACF argument array, preserves extra ACF arguments, and leaves the stable `key` caller-owned. `multiPostObject()` retains its original contract.

```php
use Hexa\PluginCore\AcfFieldFactory\AcfFieldFactory;

AcfFieldFactory::multiPostObject(
    [
        "key"          => "field_example_disabled_objects",
        "label"        => "Disable Feature On Specific Posts Or Pages",
        "name"         => "example_disabled_objects",
        "instructions" => "Select posts, pages, or public CPT entries where this feature should not render.",
        "post_types"   => [ "post", "page", "press-release" ],
    ]
);
```

`multiPostObject()` returns one ACF `post_object` field with `multiple => 1`, `return_format => id`, `allow_null => 1`, and `ui => 1`. It is intentionally not a repeater.

## Render Example

```php
use Hexa\PluginCore\FieldStructures\FieldStructureRenderer;

echo ( new FieldStructureRenderer() )->render(
    [
        [
            "id"           => "post_faqs",
            "label"        => "Post FAQ ACF",
            "type"         => "acf",
            "setting_key"  => "post_faqs_acf_enabled",
            "enabled"      => true,
            "registered"   => function(): bool { return function_exists("acf_get_field_group") && acf_get_field_group("group_example"); },
            "acf_group_key" => "group_example",
            "location"     => "post editor",
            "fields"       => [ "question", "answer", "enabled_for_schema" ],
            "code_example" => "[smp_post_faqs]",
        ],
    ],
    [ "save_action" => "example_save_settings", "nonce" => Example_Admin_Ajax::nonce() ]
);
```

Rules: use one row per structure, include identity details, include use instructions and test reports where available, and do not put plugin-specific ACF arrays inside Hexa Core.

Test the builders with `php tests/acf-field-factory.php`. Test registration and persistence with `php tests/acf-field-groups.php`. Test settings-panel page and tab scoping with `php tests/acf-settings-panel.php`; host plugins must also open the exact target editor/settings tab and confirm their field keys and stored values remain unchanged.
