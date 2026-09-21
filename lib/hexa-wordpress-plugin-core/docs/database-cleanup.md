# Database Cleanup

Namespace:

```text
Hexa\PluginCore\DatabaseCleanup
```

## Purpose

This module runs a provider-backed database cleanup as a guarded session. It reports the provider state, executes supported cleanup tasks, optimizes each discovered table individually, reports every result through AJAX, and restores the provider plugin to its pre-run activation state.

## Classes

- `DatabaseCleanupService`: provider state, session storage, cleanup execution, table verification, and activation restoration.
- `DatabaseCleanupAjaxController`: nonce/capability guarded status, start, task, table, and finish actions.
- `DatabaseCleanupRenderer`: live task/table progress and activity reporting.

## Host Responsibilities

The host supplies the capability, nonce, AJAX action names, and provider plugin file. The current provider adapter uses WP-Optimize's public optimizer API. The host must not duplicate the session runner or progress UI.

```php
$config = [
    'root_id'                 => 'example-database-cleanup',
    'title'                   => 'Database Cleanup',
    'capability'              => 'manage_options',
    'nonce_action'            => 'example_database_cleanup',
    'nonce_field'             => 'nonce',
    'wp_optimize_plugin_file' => 'wp-optimize/wp-optimize.php',
    'status_action'           => 'example_database_cleanup_status',
    'start_action'            => 'example_database_cleanup_start',
    'cleanup_action'          => 'example_database_cleanup_task',
    'table_action'            => 'example_database_cleanup_table',
    'finish_action'           => 'example_database_cleanup_finish',
];

( new DatabaseCleanupAjaxController( $config ) )->register();
( new DatabaseCleanupRenderer( $config ) )->render();
```

## State Rule

If WP-Optimize is inactive before a run, Core activates it and returns it to inactive when the session finishes. If it is already active, Core leaves it active. A failed or expired session must never deactivate a plugin Core did not activate.
