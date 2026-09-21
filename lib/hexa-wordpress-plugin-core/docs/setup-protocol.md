# Setup Protocol

All host plugins must initialize `hexa/plugin-core` the same way.

## Goal

The core owns reusable behavior. The host plugin owns identity and site-specific choices.

Host identity includes:

- plugin slug
- plugin basename
- plugin version
- plugin path
- plugin URL
- GitHub repository
- admin page slug
- WordPress capability

These values must be passed through `PluginContext`.

## Package Selection

Every host must register its vendored package before referencing a `Hexa\\PluginCore` class. Do not register a plugin-specific Core autoloader.

```php
$core_root = __DIR__ . '/lib/hexa-wordpress-plugin-core';

require_once $core_root . '/bootstrap.php';

\hexa_plugin_core_register_package(
    'example-plugin',
    $core_root,
    [
        'minimum_version' => trim( (string) file_get_contents( $core_root . '/VERSION' ) ),
    ]
);
```

The global package registry waits until `plugins_loaded`, evaluates every registered candidate, and registers one autoloader for one selected package root. Selection prefers the newest package satisfying every host constraint. Same-version packages with different `PACKAGE_HASH` values are reported as integrity failures.

No host may load a Core class during its main plugin file. Host modules boot on `plugins_loaded` after the registry resolver.

## Standard Module Sequence

```php
use Hexa\PluginCore\CoreBootstrap\CoreBootstrap;
use Hexa\PluginCore\CoreRuntime\PluginContext;

$context = new PluginContext(
    [
        'slug'        => 'example-plugin',
        'basename'    => plugin_basename( __FILE__ ),
        'version'     => '1.0.0',
        'path'        => plugin_dir_path( __FILE__ ),
        'url'         => plugin_dir_url( __FILE__ ),
        'github_repo' => 'owner/example-plugin',
        'admin_page'  => 'example-plugin',
        'capability'  => 'manage_options',
    ]
);

$core = new CoreBootstrap( $context );

$core
    ->add_module( $module_one )
    ->add_module( $module_two )
    ->boot();
```

## Module Rule

Modules implement:

```php
Hexa\PluginCore\CoreContracts\ModuleInterface
```

Modules must not execute behavior at file include time. They register WordPress hooks only inside:

```php
public function register(): void
```

## Boot Rule

`CoreBootstrap::boot()` is idempotent. Calling it twice must not double-register modules.

`CoreRuntime\\CorePackageRuntime::report()` returns the selected package, all registered candidates, integrity issues, and any namespaced class loaded outside the selected root. The automatic Core tab renders this report.

## Host Plugin Adapter Rule

A host plugin may write adapter classes in its own namespace.

Example:

```text
HWS\BaseTools\Admin\Shortcodes\ShortcodesModule
```

That adapter may consume:

```text
Hexa\PluginCore\ShortcodeRegistry\ShortcodeRegistry
```

But the shared core must not reference the host adapter namespace.
