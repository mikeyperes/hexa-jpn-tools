# Schema Tools

Namespace: `Hexa\PluginCore\SchemaTools`

Core owns JSON-LD document normalization, graph utilities, duplicate-node merging, script rendering, and WordPress output hooks. Host plugins continue to own schema object selection and field mapping.

## Classes

- `SchemaGraph`: cleans nested schema values, normalizes HTTP(S) URLs, removes malformed URL-property values, reports semantic property issues, and supports graph composition.
- `SchemaDocumentRenderer`: normalizes documents, deduplicates graph nodes, and renders safe JSON or JSON-LD scripts.
- `SchemaInjector`: one-shot module that calls a host provider on a configured WordPress hook.
- `SchemaDashboardRenderer`: shared schema inspection UI.

```php
$bootstrap->add_module(
    new \Hexa\PluginCore\SchemaTools\SchemaInjector(
        [ ExampleSchemaProvider::class, 'current' ],
        [ 'hook' => 'wp_head', 'priority' => 2, 'script_id' => 'example-schema' ]
    )
);
```

Test normalization and node deduplication with `php tests/schema-document.php`. Host regression tests must compare graph types, IDs, relationships, and critical fields against the pre-migration frontend output.

## Typed URL Guards

Use `SchemaGraph::web_url()` while resolving a field that must be one HTTP(S) URL. It rejects arrays, booleans, non-web schemes, and malformed values, then applies an optional validated fallback.

Call `SchemaGraph::sanitize_urls()` on a completed host-owned node or graph before output. The sanitizer removes malformed values from URL-range properties while preserving valid URL lists, `Role` values for `url`, and structured policy nodes. `SchemaGraph::validation_issues()` returns property paths and error codes for reports and tests.

```php
$publication_url = SchemaGraph::web_url( $candidate, home_url( '/' ) );
$schema          = SchemaGraph::sanitize_urls( $schema );
$issues          = SchemaGraph::validation_issues( $schema );
```

Typed resolution remains a host responsibility: when one field source has the wrong shape, continue to the next configured source before using a fallback. Sanitization is the final output guard, not a substitute for source selection.
