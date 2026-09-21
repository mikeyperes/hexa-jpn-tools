# Smart Search / X-Search

Namespace: `Hexa\PluginCore\SmartSearch`

WordPress equivalent of Laravel `<x-hexa-smart-search>`.

## Classes

- `SmartSearchAjaxController`
- `SmartSearchRenderer`

## AJAX Endpoint

```text
wp_ajax_hexa_plugin_core_smart_search
```

## Request

```text
q=search term
source=posts
post_type=any
limit=8
```

## Response Shape

```json
{
  "success": true,
  "data": {
    "results": [
      {
        "id": 123,
        "value": 123,
        "name": "Post title",
        "subtitle": "Post - publish"
      }
    ]
  }
}
```

## Renderer

```php
( new \Hexa\PluginCore\SmartSearch\SmartSearchRenderer() )->render(
    [
        'id'        => 'my-content-search',
        'label'     => 'Find content',
        'source'    => 'posts',
        'post_type' => 'any',
    ]
);
```

## Extension

Host plugins can alter results with:

```php
add_filter(
    'hexa_plugin_core_smart_search_results',
    function ( array $results, string $source, string $query, int $limit ): array {
        return $results;
    },
    10,
    4
);
```
