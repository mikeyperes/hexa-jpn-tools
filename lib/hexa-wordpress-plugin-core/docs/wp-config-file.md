# WP Config File

Namespace:

```text
Hexa\PluginCore\WpConfigFile
```

Use this namespace for safe WordPress `wp-config.php` reads and writes.

## Public Class

```text
WpConfigFile
```

## Responsibilities

- Resolve the default `wp-config.php` path.
- Modify `define()` constants safely.
- Modify `ini_set()` statements safely.
- Validate the config file before and after mutation.
- Create a rollback backup before writing.
- Restore the backup if writing fails.
- Read user-defined constants while excluding sensitive database/auth constants.
- Read PHP INI values while honoring `ini_set()` overrides in `wp-config.php`.
- Toggle a PHP INI value at runtime and persist it to `wp-config.php`.

## Method Reference

```php
WpConfigFile::default_path(): string
WpConfigFile::modify_constants( array $constants_to_update, ?string $path = null, array $args = [] ): array
WpConfigFile::defined_constants( array $exclude = [] ): array
WpConfigFile::constant_status( string $constant_name ): mixed
WpConfigFile::get_php_ini_value( string $setting_name, ?string $path = null, ?callable $logger = null ): string
WpConfigFile::toggle_php_ini_value( string $setting_name, string $new_value, ?string $path = null, ?callable $logger = null, array $args = [] ): string
```

## Example

```php
use Hexa\PluginCore\WpConfigFile\WpConfigFile;

$result = WpConfigFile::modify_constants([
    'WP_DEBUG' => 'false',
    'WP_DEBUG_DISPLAY' => 'false',
    'WP_MEMORY_LIMIT' => '4096M',
    'ini_display_errors' => [
        'type' => 'ini',
        'value' => '0',
    ],
]);
```

## Host Plugin Rule

Host plugins may choose which constants to expose in their UI. The mutation mechanics belong in `WpConfigFile`, not in host plugin files.
