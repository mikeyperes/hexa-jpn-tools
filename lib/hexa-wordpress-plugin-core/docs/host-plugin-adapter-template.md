# Host Plugin Adapter Template

This document shows how a consuming plugin should wrap `hexa/plugin-core` without polluting the shared namespace.

## Folder Example In A Host Plugin

```text
host-plugin/
  host-plugin.php
  src/
    Admin/
      Shortcodes/
        ShortcodesModule.php
    Bootstrap/
      PluginBootstrap.php
```

Host plugin namespace example:

```text
HWS\BaseTools\
```

Shared core namespace:

```text
Hexa\PluginCore\
```

The host plugin adapter may depend on the shared core:

```php
use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\ShortcodeRegistry\ShortcodeDefinition;
use Hexa\PluginCore\ShortcodeRegistry\ShortcodeRegistry;

final class ShortcodesModule implements ModuleInterface {
    public function register(): void {
        add_action( 'admin_menu', [ $this, 'register_screen' ] );
    }

    public function registry(): ShortcodeRegistry {
        return ( new ShortcodeRegistry() )
            ->add(
                new ShortcodeDefinition(
                    'display_year',
                    'Current Year',
                    '[display_year]',
                    'Outputs the current four-digit year.',
                    'Runs without input and checks for non-empty output.'
                )
            );
    }
}
```

The shared core must never depend back on the host plugin.

