# Support Namespace

Namespace:

```text
Hexa\PluginCore\CoreRuntime
```

Folder:

```text
src/CoreRuntime/
```

## Purpose

Support contains small shared value objects and helpers that are not specific to one module.

## Current Class

```text
PluginContext
```

`PluginContext` stores host plugin identity:

- `slug`
- `basename`
- `version`
- `path`
- `url`
- `github_repo`
- `admin_page`
- `capability`

## Support Rules

Support classes should stay small.

Do not put feature logic here. If a helper grows into a feature, move it into its real namespace.

Examples:

Good:

```text
PluginContext
StringValue
Url
Path
```

Bad:

```text
ShortcodeRenderer
UpdaterManager
TabsScreen
ActivityLogController
```

