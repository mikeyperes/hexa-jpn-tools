# Plugin Provisioning

Namespace:

```text
Hexa\PluginCore\PluginProvisioning
```

Use this namespace for reusable WordPress plugin lifecycle tasks that are not tied to one host plugin.

## Public Class

```text
PluginProvisioner
```

## Responsibilities

- Find an installed plugin file by folder slug.
- Return plugin status by folder slug or plugin file, including network-only activation.
- Prepare the WordPress filesystem safely.
- Remove temporary install work directories under `wp-content/upgrade`.
- Normalize GitHub archive folders from `plugin-main` or `plugin-master` to the canonical plugin slug.
- Install a plugin from a public GitHub repository ZIP.
- Install a plugin from WordPress.org by slug.
- Activate an installed plugin file.
- Ensure a GitHub-hosted plugin is installed and active.

## Host Plugin Rule

The host plugin owns its catalog data. For example, HWS Base Tools decides that `smp-verified-profiles` maps to `mikeyperes/smp-verified-profiles`.

Core only owns the reusable mechanics:

```php
use Hexa\PluginCore\PluginProvisioning\PluginProvisioner;

$status = PluginProvisioner::plugin_status_by_folder( 'smp-verified-profiles' );

$result = PluginProvisioner::ensure_github_plugin_active(
    'smp-verified-profiles',
    'mikeyperes/smp-verified-profiles',
    [
        'branch'      => 'main',
        'work_prefix' => 'hws-github-plugin',
        'timeout'     => 60,
    ]
);
```

Status arrays expose both `active` and `network_active`. A network-activated plugin is always reported as active and is not activated a second time as a site-only plugin.

## Method Reference

```php
PluginProvisioner::normalize_github_repo( string $repo_or_url ): string
PluginProvisioner::find_plugin_file_by_folder( string $folder ): string
PluginProvisioner::plugin_status_by_folder( string $folder ): array
PluginProvisioner::plugin_status_by_file( string $plugin_file ): array
PluginProvisioner::prepare_filesystem(): mixed
PluginProvisioner::cleanup_work_dir( string $path, string $prefix = 'hexa-github-plugin-' ): void
PluginProvisioner::normalize_github_folder( string $slug )
PluginProvisioner::install_github_plugin( string $slug, string $repo_or_url, array $args = [] ): string|\WP_Error
PluginProvisioner::install_wordpress_org_plugin( string $slug, bool $activate = true ): array|\WP_Error
PluginProvisioner::activate_plugin_file( string $plugin_file ): array|\WP_Error
PluginProvisioner::ensure_github_plugin_active( string $slug, string $repo_or_url, array $args = [] ): array|\WP_Error
```

## Agent Notes

Do not put HWS-specific plugin names, repositories, UI labels, or table renderers in this namespace. Keep those in the host plugin. If another plugin needs the same install mechanics, pass its own catalog values into `PluginProvisioner`.
