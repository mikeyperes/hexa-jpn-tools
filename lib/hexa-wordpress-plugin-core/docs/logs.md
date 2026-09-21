# Logs Namespace

Namespace:

```text
Hexa\PluginCore\LogFiles
```

Folder:

```text
src/LogFiles/
```

## Purpose

The logs namespace standardizes error-log monitoring across Hexa plugins.

It started from the HWS Base Tools Overview error-log viewer:

- `debug.log`
- root `error_log`
- `wp-admin/error_log`
- fatal/syntax extraction
- warning/notice classification
- search within the visible log
- delete buttons supplied by the host plugin

## Classes

```text
ErrorLogSource
ErrorLogClassifier
ErrorLogReader
ErrorLogPanelRenderer
```

## Example

```php
use Hexa\PluginCore\LogFiles\ErrorLogPanelRenderer;
use Hexa\PluginCore\LogFiles\ErrorLogSource;

( new ErrorLogPanelRenderer() )->render(
    [
        new ErrorLogSource( 'debug', 'debug.log', WP_CONTENT_DIR . '/debug.log', true, 'delete-debug-log' ),
        new ErrorLogSource( 'error', 'error_log', ABSPATH . 'error_log', true, 'delete-error-log' ),
        new ErrorLogSource( 'admin-error', 'wp-admin/error_log', ABSPATH . 'wp-admin/error_log' ),
    ],
    [
        'id'         => 'hws-error-log-panel',
        'tail_lines' => 150,
    ]
);
```

## Next Extraction Target

The HWS Log Cleaner cron/settings system should move into this namespace after the viewer is proven in production.
