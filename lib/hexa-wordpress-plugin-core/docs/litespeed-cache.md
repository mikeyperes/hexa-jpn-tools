# LiteSpeed Cache Profiles

Namespace: `Hexa\PluginCore\LiteSpeedCache`

Folder: `src/LiteSpeedCache/`

Core provides array-driven definitions, generic audit/apply/verify and result
assembly, plus the official `LiteSpeed\Conf` adapter. Hosts own every
recommended value, applicability rule, capability/nonce guard, and UI.

```php
$profile = new Profile([
    'id' => 'host-profile',
    'settings' => [
        'cache_enabled' => [
            'option_name' => 'litespeed.conf',
            'option_path' => 'cache.enabled',
            'expected' => true,
            'cast' => 'bool',
        ],
    ],
]);
$service = new LiteSpeedCacheService($profile);
$result = $service->apply();
```

`option_name` is LiteSpeed's public option ID. By default,
`LiteSpeedConfAdapter` resolves `LiteSpeed\Conf::cls()`, inspects both the
stored/original value (`conf($id, true)`) and effective value (`conf($id)`),
and records local, network, constant, primary-site, filter, server, or external
provenance. Missing IDs and effective overrides are review items and are not
written. Every writable profile difference is passed through one
`update_confs()` call so LiteSpeed retains its type normalization, purge, cron,
generated-file, and cloud-synchronization behavior.

`ConfigurationAdapterInterface` is the storage boundary. Callers may inject a
custom adapter, or retain the compatibility reader/writer callbacks. Callback
readers return `LiteSpeedCacheService::missing_value()` for absent values. The
singleton marker remains distinct from expected `false`, `0`, `0.0`, `''`, or
`[]`. Supported comparison casts are `bool`, `int`, `float`, `string`, and
line-list `array`.

Run `php tests/litespeed-cache.php`. Host tests must assert host-owned profile values against the exact LiteSpeed installation.
