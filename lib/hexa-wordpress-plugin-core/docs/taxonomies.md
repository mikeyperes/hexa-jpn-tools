# Taxonomies

Namespace: `Hexa\PluginCore\Taxonomies`

Use this namespace for reusable host-owned taxonomy registration and reference UI. Host plugins define taxonomy keys, object types, labels, terms, and editorial meaning; Core owns normalization, callback execution, duplicate-safe registration, and display.

## Classes

- `TaxonomyDefinition`: normalized taxonomy contract.
- `TaxonomyRegistry`: module and callback-backed WordPress registration.
- `TaxonomyRenderer`: shared taxonomy status and documentation UI.

```php
$registry = new \Hexa\PluginCore\Taxonomies\TaxonomyRegistry();
$registry->add(
    [
        'id' => 'article-types',
        'taxonomy' => 'example_article_type',
        'label' => 'Article Types',
        'object_types' => [ 'post' ],
        'args' => [ 'public' => false, 'show_ui' => true ],
    ]
);
$bootstrap->add_module( $registry );
```

Test with `php tests/taxonomies.php`, then verify the exact editor metabox, saved term, supported post types, and consuming schema behavior.
