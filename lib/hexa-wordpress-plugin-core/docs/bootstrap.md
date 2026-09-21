# Bootstrap Namespace

Namespace:

```text
Hexa\PluginCore\CoreBootstrap
```

Folder:

```text
src/CoreBootstrap/
```

## Purpose

The bootstrap namespace controls setup and lifecycle for shared core modules.

It prevents each host plugin from inventing its own initialization sequence.

## Core Class

```text
CoreBootstrap
```

Responsibilities:

- hold the host `PluginContextInterface`
- accept modules through `add_module()`
- call each module's `register()` method once
- prevent double booting

## Required Host Plugin Flow

```php
$core = new CoreBootstrap( $context );

$core
    ->add_module( $module_one )
    ->add_module( $module_two )
    ->boot();
```

## Forbidden Patterns

Do not:

- register WordPress hooks at file include time
- call module logic before `boot()`
- hard-code host plugin constants in this namespace
- make modules instantiate their own host context

