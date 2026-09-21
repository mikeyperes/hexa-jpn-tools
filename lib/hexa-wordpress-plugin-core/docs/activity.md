# Activity Namespace

Namespace:

```text
Hexa\PluginCore\ActivityLog
```

Folder:

```text
src/ActivityLog/
```

## Purpose

The activity namespace stores and renders normalized activity log records for admin actions, updater actions, tests, imports, and maintenance tasks.

## Required Record Shape

Activity records should include:

- message
- context
- timestamp
- actor, when available
- source module

Activity logs must avoid secrets, tokens, passwords, private keys, and raw request payloads.

## Storage Modes

```text
page       render-only; removed on page refresh
transient  stored by WordPress transients with a TTL
permanent  stored by WordPress options until cleared
```

## Classes

```text
ActivityLogConfig
ActivityLogEntry
ActivityLogger
ActivityLogRenderer
```

## Example

```php
$config = new ActivityLogConfig(
    [
        'id'          => 'import-log',
        'title'       => 'Import Log',
        'storage'     => ActivityLogConfig::STORAGE_TRANSIENT,
        'storage_key' => 'example_import_log',
        'collapsed'   => false,
    ]
);

$logger = new ActivityLogger( $config );
$logger->add( new ActivityLogEntry( 'Import started.', [ 'source' => 'csv' ], 'admin', 'importer', null, 'info' ) );
$logger->add( new ActivityLogEntry( 'Rows processed.', [ 'rows' => 120 ], 'admin', 'importer', null, 'success' ) );

( new ActivityLogRenderer( $config ) )->render( $logger->all() );
```

## UI Contract

The renderer is dark by default. It includes:

- expandable/collapsible container
- entry count and storage mode pills
- level badges for `info`, `success`, `warning`, and `error`
- hidden details for verbose context
- scrollable log body for long-running workflows
