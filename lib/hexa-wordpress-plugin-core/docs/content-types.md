# Content Types

Namespace: `Hexa\PluginCore\ContentTypes`

Use this namespace when multiple host plugins need the same CPT registration, settings, AJAX, ACF association, and UI contract. Host plugins own each definition and its business behavior. Core keeps the WordPress post-type key immutable while allowing the public rewrite slug and singular/plural labels to change.

## Classes

- `ContentTypeDefinition`: normalizes and validates host definitions.
- `ContentTypeSettingsStore`: resolves defaults and legacy options and persists labels, slug, enable state, and field-group toggles.
- `ContentTypeRegistry`: module and definition registry.
- `ContentTypeRegistrar`: idempotent CPT, taxonomy, and ACF registration.
- `ContentTypeAjaxController`: guarded AJAX persistence and rewrite flushing.
- `ContentTypeRenderer`: shared hierarchical management UI. Every CPT is a collapsed accordion whose header contains its title and functional enable switch. Its ACF field-group cards are separate collapsed siblings placed immediately after and outside the CPT accordion, each with its own title and enable switch. ACF siblings intentionally use a smaller, quieter secondary treatment so the CPT remains the dominant level. Imported field rows show `label — name — type`, and each row includes a collapsed JSON breakdown sourced from the actual ACF definition. Text-only host inventories remain supported as a compatibility fallback.

```php
$registry = new \Hexa\PluginCore\ContentTypes\ContentTypeRegistry(
    [
        'option_name' => 'example_content_types',
        'ajax_action' => 'example_save_content_type',
        'nonce_action' => 'example_content_types',
    ]
);
$registry->add(
    [
        'id' => 'book',
        'owner' => 'Example Plugin',
        'post_type' => [
            'key' => 'book',
            'singular' => 'Book',
            'plural' => 'Books',
            'rewrite_slug' => 'books',
            'args' => [ 'public' => true, 'show_in_rest' => true ],
        ],
    ]
);
$bootstrap->add_module( $registry );
```

Use `registration_mode => external` only when the current plugin extends a CPT owned elsewhere. Test with `php tests/content-types.php`, then verify registration, labels, slug, ACF groups, and existing content on the exact WordPress editor and archive URLs.
