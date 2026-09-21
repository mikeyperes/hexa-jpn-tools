# Contracts Namespace

Namespace:

```text
Hexa\PluginCore\CoreContracts
```

Folder:

```text
src/CoreContracts/
```

## Purpose

Contracts define the public agreements between this shared core and each host plugin.

Interfaces belong here when more than one namespace depends on the same behavior.

## Current Contracts

### ModuleInterface

```php
public function register(): void;
```

Every bootable module implements this. It should register hooks, filters, AJAX actions, or admin render callbacks. It should not run feature behavior directly.

### PluginContextInterface

```php
public function get( string $key, mixed $default = null ): mixed;
public function all(): array;
```

Every host plugin passes identity and config through this contract.

## Adding Contracts

Only add a contract when at least two modules need the same interface or a host plugin adapter must implement it.

Do not add vague contracts like:

```text
ManagerInterface
HelperInterface
CoreInterface
```

Name contracts after the behavior they guarantee.

