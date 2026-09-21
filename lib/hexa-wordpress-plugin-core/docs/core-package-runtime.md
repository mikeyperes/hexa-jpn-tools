# Core Package Runtime

Namespace:

```text
Hexa\PluginCore\CoreRuntime
```

Root bootstrap:

```text
bootstrap.php
```

## Purpose

Multiple active WordPress plugins may vendor Hexa WordPress Plugin Core. They must not register competing autoloaders for the same `Hexa\\PluginCore` namespace. The package registry collects every vendored candidate before host modules boot and assigns the namespace to one compatible package root.

## Host Responsibilities

1. Require the vendored `bootstrap.php` from the main plugin file.
2. Call `hexa_plugin_core_register_package()` with a unique host slug and package root.
3. Do not reference a Core class until `plugins_loaded`.
4. Build `PluginContext`, `CoreBootstrap`, and modules only after selection.
5. Ship the canonical `VERSION` and `PACKAGE_HASH` with the vendored package.

## Selection And Integrity

- The newest candidate satisfying every registered minimum and optional maximum version wins.
- Exactly one selected package autoloader owns `Hexa\\PluginCore`.
- Equal versions with different package hashes produce an integrity issue.
- Classes already loaded from another root produce a runtime issue.
- Late incompatible candidates remain visible in diagnostics.

Use `CorePackageRuntime::report()` for machine-readable status. The automatic Core tab displays the same report for operators.

## Testing

Run:

```bash
php tests/core-package-registry.php
```

Consumer integration tests must load all active host bootstrap files in one process, resolve the package registry, instantiate representative Core classes, and confirm every `ReflectionClass::getFileName()` begins with the selected Core root.
