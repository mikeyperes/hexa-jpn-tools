# Standalone Schema Nodes

Namespace: `Hexa\PluginCore\SchemaTools`

Class: `SchemaGraph`

`SchemaGraph::standalone_nodes()` keeps every node in a JSON-LD `@graph` independently detectable. Reference-only objects such as `{"@id":"https://example.com/#organization"}` become identifier URL values. Properties that need a concrete author, publisher, copyright holder, or image retain a detached typed summary without duplicating top-level nodes.

Host plugins remain responsible for building accurate entities, stable `@id` values, and valid relationships. Call the transformation only after host filters finish:

```php
$schema = apply_filters( 'host_schema_array', $schema, $post_id );
$schema = SchemaGraph::standalone_nodes( $schema );
```

The optional second argument overrides the typed-summary property map. Include both
`@id` and `@type` when a host needs a relationship to remain explicitly linked to
its independent top-level node without repeating the node's descriptive fields:

```php
$schema = SchemaGraph::standalone_nodes(
    $schema,
    [
        'author'    => [ '@id', '@type' ],
        'publisher' => [ '@id', '@type' ],
        'image'     => [ '@id', '@type' ],
    ]
);
```

Run `php tests/schema-standalone-nodes.php` and the full Core test suite after changes.
