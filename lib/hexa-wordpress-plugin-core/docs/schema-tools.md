# Schema Tools

Namespace: `Hexa\PluginCore\SchemaTools`

Core is the one schema library for every Hexa plugin: generic node builders, WordPress data adapters, one merged graph per page, and the Rank Math schema switch. Core never decides which page shows which node. Each host plugin decides what it outputs on which pages, builds those nodes with Core, and adds them to `SchemaPage`.

## Classes

- `SchemaNodes`: generic builders for Organization (any organization type), Person, WebSite, WebPage types, Article types, ImageObject, PostalAddress, Place, ContactPoint, area served, PropertyValue, BreadcrumbList, ItemList, Book, Offer, Brand, credential, Demand, Event and named Things. Builders take plain values and return cleaned nodes; they never read WordPress.
- `SchemaSources`: standard WordPress data as nodes: the site's WebSite, attachment and featured images (original upload size, offloaded files included), logos with site-icon fallback, users as Person, post and archive breadcrumbs, post pages, descriptions, terms, language.
- `SchemaIds`: shared `@id` conventions (`/#organization`, `/#website`, `/#person`, `<url>#webpage`, `<url>#article`, `<url>#primaryimage`, `<url>#breadcrumb`) so the same thing built by two plugins merges into one node.
- `SchemaPage`: one graph per page. Plugins register a source callback; Core merges every source's nodes by `@id` and prints one `<script id="hexa-schema">` in the head. On a conflict the source with the lower priority number keeps its value; list values are combined. `data-sources` lists the plugins that contributed.
- `RankMathSchemaControl`: removes Rank Math's JSON-LD block (Rank Math stays active for titles, descriptions and sitemaps). Modes: `keep`, `hexa` (off where a Hexa source has nodes for the page) and `off`. Where Rank Math's schema is removed, the duplicate image block of Featured Image From URL (FIFU) is removed too. HWS Base Tools owns the setting.
- `SchemaGraph`: cleaning, URL guards, validation issues and standalone node references.
- `SchemaDocumentRenderer`: normalization and safe JSON-LD encoding.
- `SchemaDashboardRenderer`: shared schema inspection UI.
- `SchemaInjector`: older one-shot single-provider module, kept only so plugins that have not yet moved to `SchemaPage` keep working. New code uses `SchemaPage`.

```php
use Hexa\PluginCore\SchemaTools\SchemaIds;
use Hexa\PluginCore\SchemaTools\SchemaNodes;
use Hexa\PluginCore\SchemaTools\SchemaPage;
use Hexa\PluginCore\SchemaTools\SchemaSources;

SchemaPage::register( 'example-plugin', static function (): array {
    if ( ! is_singular( 'book' ) ) {
        return []; // This plugin decides where it outputs.
    }
    $post = get_queried_object();
    return [
        SchemaSources::website( SchemaIds::person() ),
        SchemaSources::post_page( $post, 'ItemPage', [ 'mainEntity' => SchemaNodes::ref( get_permalink( $post ) . '#book' ) ] ),
        SchemaNodes::book( [ '@id' => get_permalink( $post ) . '#book', 'name' => get_the_title( $post ), 'author' => SchemaNodes::ref( SchemaIds::person() ) ] ),
    ];
}, 20 );
```

Other plugins can adjust the merged graph with the `hexa_plugin_core_schema_graph` filter (nodes list, contributing sources), for example to add image variants to Article nodes.

Test with `php tests/schema-page.php` and `php tests/schema-document.php`.

## Typed URL Guards

Use `SchemaGraph::web_url()` while resolving a field that must be one HTTP(S) URL. It rejects arrays, booleans, non-web schemes, and malformed values, then applies an optional validated fallback.

Call `SchemaGraph::sanitize_urls()` on a completed host-owned node or graph before output. The sanitizer removes malformed values from URL-range properties while preserving valid URL lists, `Role` values for `url`, and structured policy nodes. `SchemaGraph::validation_issues()` returns property paths and error codes for reports and tests.

```php
$publication_url = SchemaGraph::web_url( $candidate, home_url( '/' ) );
$schema          = SchemaGraph::sanitize_urls( $schema );
$issues          = SchemaGraph::validation_issues( $schema );
```

Typed resolution remains a host responsibility: when one field source has the wrong shape, continue to the next configured source before using a fallback. Sanitization is the final output guard, not a substitute for source selection.
