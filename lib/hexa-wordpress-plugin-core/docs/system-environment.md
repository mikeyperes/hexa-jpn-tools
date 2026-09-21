# System Environment Namespace

Namespace:

```text
Hexa\PluginCore\SystemEnvironment
```

Folder:

```text
src/SystemEnvironment/
```

Purpose:

- Read constants and INI values safely.
- Parse PHP size strings into bytes.
- Format byte counts for admin output.
- Run shell commands only when the PHP function is available and not disabled.
- Read small Linux system files safely.
- Detect cgroup-aware CPU and memory limits for hosting/container environments.

Primary class:

```php
use Hexa\PluginCore\SystemEnvironment\SystemEnvironment;

$memory = SystemEnvironment::get_memory_info();
$cpu = SystemEnvironment::get_cpu_info();
$bytes = SystemEnvironment::parse_size( ini_get( 'memory_limit' ) );
$label = SystemEnvironment::format_bytes( $bytes );
```
