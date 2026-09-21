# Object Cache

Namespace:

```text
Hexa\PluginCore\ObjectCache
```

## Purpose

Provider adapters in this namespace report separately whether object caching is configured and whether it is actually working.

`LiteSpeedRedisService` checks:

- LiteSpeed Cache installation and activation.
- LiteSpeed object-cache and Redis settings.
- The object-cache drop-in.
- PHP Redis extension connectivity and PING.
- A WordPress cache set/get/delete round trip.

`enable()` activates LiteSpeed when needed, saves one supported Redis settings batch through LiteSpeed's official `Conf::update_confs()` API, refreshes the provider-managed drop-in, flushes cache, and returns before/after evidence. The host owns only the AJAX guard and presentation surface.

```php
$service = new LiteSpeedRedisService();
$status  = $service->status();
$result  = $service->enable();
```

Do not report Redis as active from settings alone. `active` is true only when the LiteSpeed-managed drop-in is loaded, direct Redis connectivity succeeds, WordPress reports an external object cache, and the WordPress cache round trip succeeds. Because WordPress loads drop-ins before plugins, a successful first-time configuration can return `requires_new_request`; verify it on the next request. A foreign `object-cache.php` is never treated as LiteSpeed Redis.
