# Schema Detection

Namespace:

```text
Hexa\PluginCore\SchemaDetection
```

Use this module when a host plugin needs to fetch public pages, inspect JSON-LD schema blocks, identify likely sources, detect duplicate schema objects, and render a dark admin report.

## Classes

- `SchemaPageScanner`: fetches URLs or accepts raw HTML and returns structured schema scan payloads.
- `SchemaScanRenderer`: renders one or more scan payloads into a reusable admin report.

## Scan URLs

```php
use Hexa\PluginCore\SchemaDetection\SchemaPageScanner;

$scanner = new SchemaPageScanner();
$scan = $scanner->scanUrl(
    home_url( "/" ),
    [
        "title" => "Homepage",
        "cache_bust" => true,
        "timeout" => 15,
    ]
);
```

## Render Results

```php
use Hexa\PluginCore\SchemaDetection\SchemaScanRenderer;

$renderer = new SchemaScanRenderer();
echo $renderer->renderReport(
    [ $scan ],
    [
        "title" => "Schema Detection Results: HOMEPAGE",
        "subtitle" => "Scanned through the host plugin AJAX tool.",
        "expected" => [ "SFPF Expected: Person" ],
        "debug" => false,
    ]
);
```

## Payload Shape

The scanner returns the URL, HTTP status, timing, body size, JSON-LD blocks, invalid JSON blocks, semantic property issues, detected schema types, types grouped by source, duplicate-type conflicts, and FAQPage issues. Semantic issues include the JSON-LD block number and exact property path so a syntactically valid but malformed value cannot pass the report.

Host plugins should keep plugin-specific expectations in the host layer and pass them into `SchemaScanRenderer` as `expected` lines.
