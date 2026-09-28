# Relative Time

Namespace and folder: `Hexa\PluginCore\PublicComponents\RelativeTime` in `src/PublicComponents/`.

## Purpose

One reusable "last updated" age for public pages: `RelativeTime::html( $timestamp )`
returns `<time class="hexa-reltime" datetime="…" title="<exact date>" data-hexa-reltime>2 hours ago</time>`.
The server writes the age at request time; a tiny inline script (printed once per
page) recomputes every such element on load and each minute, so full-page caches
never show a stale age. Wording: `just now`, `N min ago`, `N hour(s) ago`, `N day(s) ago`.

## Host responsibilities

Pass a Unix timestamp and an optional extra class; style the element in the page builder.

```php
echo 'Last updated ' . \Hexa\PluginCore\PublicComponents\RelativeTime::html( $updated_at );
```

## Testing

`php tests/relative-time.php`.
