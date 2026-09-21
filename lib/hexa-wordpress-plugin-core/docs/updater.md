# Plugin Update Namespaces

Host plugin updater namespace:

```text
Hexa\PluginCore\PluginUpdates
```

Host plugin updater folder:

```text
src/PluginUpdates/
```

Vendored core package updater namespace:

```text
Hexa\PluginCore\CorePackageUpdates
```

Vendored core package updater folder:

```text
src/CorePackageUpdates/
```

## Purpose

These namespaces standardize GitHub/update configuration without nesting under a vague `Updater` bucket.

The updater core must not hard-code one plugin's repository. It reads repository and plugin identity from the host `PluginContext` or an explicit `UpdaterConfig`.

The plugin update namespace starts with the HWS Base Tools updater behavior and makes it abstract:

- a plugin passes its slug/basename and GitHub repo URL
- GitHub URLs are normalized to `owner/repo`
- the updater checks the remote plugin header version
- WordPress core update transients receive the GitHub version
- GitHub `repo-main` folders are normalized to the canonical plugin folder
- the direct updater downloads, extracts, backs up, installs, cleans duplicate folders, and clears caches
- package builders and installers exclude nested VCS metadata such as `.git`, `.svn`, `.hg`, and `.bzr`
- native WordPress plugin updates purge ignored metadata before install and return a clear `WP_Error` if locked metadata remains
- native updates capture whether the plugin was inactive, site-active, or network-active before WordPress's silent deactivation, then clear discovery caches and verify silent restoration under the canonical basename
- GitHub access tokens never belong in package URLs; private GitHub auth must use request headers
- the admin panel can render the same update-status flow for any host plugin
- a separate core-package panel compares a vendored Hexa WordPress Plugin Core `VERSION` file against the public core GitHub repository

## GitHub Slug Rule

GitHub repositories use:

```text
owner/repository
```

Do not include a protocol, branch, or `.git` suffix in the repository value.

Good:

```text
mikeyperes/hws-base-tools
```

Bad:

```text
https://github.com/mikeyperes/hws-base-tools
hws-base-tools-main
mikeyperes/hws-base-tools.git
```

## Classes

```text
UpdaterConfig
GitHubVersionClient
GitHubPluginUpdater
PluginUpdateStatus
UpdateProgressStore
DirectPluginInstaller
PluginZipBuilder
UpdaterAjaxController
UpdaterPanelRenderer
UpdaterFilesystem
```

```text
CorePackageConfig
CorePackageVersionClient
CorePackageStatus
CorePackageInstaller
CorePackageAjaxController
CorePackagePanelRenderer
```

`CorePackageInstaller::run()` preserves the low-level single-host contract. `registered_hosts()` reads distinct Core roots from the bootstrap registry. `run_registered_hosts()` downloads and verifies once, stages the clean package for every registered root, then commits the fleet as one rollback unit; without candidates it falls back to `run()`. The shared AJAX updater calls the fleet method, so an update launched from any host plugin updates every registered vendored copy.

```php
$installer = new CorePackageInstaller($core_config, $progress_store);
$result = $installer->run_registered_hosts();
```

`CoreBootstrap` also registers `CorePackageFleetSyncModule`. After a plugin
install, update, or activation—and on a later authorized admin request—the
module compares the fresh on-disk version and `PACKAGE_HASH` for every
registered host. It stages every target before changing any live package,
retains all prior packages until every target passes post-commit verification,
removes stale files, and rolls the whole fleet back after any commit or
verification failure. It performs no network download; a released host plugin
must still bundle the canonical current Core package.

Fleet results report `new_version`, `updated_count`, per-host `{host, core_root, new_version}` rows, and `core_roots`. Test discovery with `php tests/core-package-fleet.php`; unit tests must use temporary roots rather than production packages.

## Minimal Host Integration

```php
use Hexa\PluginCore\PluginUpdates\GitHubPluginUpdater;
use Hexa\PluginCore\PluginUpdates\UpdaterAjaxController;
use Hexa\PluginCore\PluginUpdates\UpdaterConfig;
use Hexa\PluginCore\PluginUpdates\UpdaterPanelRenderer;

$config = UpdaterConfig::from_plugin_file(
    __FILE__,
    'https://github.com/owner/example-plugin',
    [
        'plugin_slug'         => 'example-plugin',
        'plugin_starter_file' => 'example-plugin.php',
        'nonce_action'        => 'example_plugin_nonce',
        'ajax_action_prefix'  => 'example_plugin_updater',
    ]
);

( new GitHubPluginUpdater( $config ) )->register();
( new UpdaterAjaxController( $config ) )->register();

// Inside an admin page:
( new UpdaterPanelRenderer( $config ) )->render();
```

Slug plus GitHub URL setup:

```php
$config = UpdaterConfig::from_slug_and_github_url(
    'example-plugin',
    'https://github.com/owner/example-plugin',
    [
        'plugin_starter_file' => 'example-plugin.php',
        'plugin_name'         => 'Example Plugin',
        'version'             => '1.0.0',
    ]
);
```

## Vendored Core Package Integration

The Hexa WordPress Plugin Core is not a WordPress plugin. It uses a root `VERSION` file.

Host plugins that vendor the core should render this panel below their own plugin updater:

```php
use Hexa\PluginCore\CorePackageUpdates\CorePackageAjaxController;
use Hexa\PluginCore\CorePackageUpdates\CorePackageConfig;
use Hexa\PluginCore\CorePackageUpdates\CorePackagePanelRenderer;

$core_config = CorePackageConfig::from_core_root(
    __DIR__ . '/lib/hexa-wordpress-plugin-core',
    [
        'github_repo'        => 'mikeyperes/hexa-wordpress-plugin-core',
        'github_branch'      => 'main',
        'nonce_action'       => 'example_plugin_nonce',
        'ajax_action_prefix' => 'example_plugin_core_package',
    ]
);

( new CorePackageAjaxController( $core_config ) )->register();
( new CorePackagePanelRenderer( $core_config ) )->render();
```

`CorePackageStatus` compares the installed vendored `VERSION` against:

```text
https://raw.githubusercontent.com/mikeyperes/hexa-wordpress-plugin-core/main/VERSION
```

Both updater panels use the shared Hexa Core expandable section styling. Panels are open by default and remember expand/collapse state in the browser. The report must show Git repo, Git URL, Git branch, Git version, installed version, comparison status, a green current flag, a red outdated flag, check-for-updates, GitHub pull/update, normalized ZIP download, and an activity log for long-running update work.

## Required Terms

Use these names consistently:

| Term | Meaning |
| --- | --- |
| `plugin_slug` | Plugin folder slug. |
| `plugin_basename` | WordPress plugin basename, `folder/main-file.php`. |
| `plugin_starter_file` | Main plugin file with the WordPress plugin header. |
| `canonical_plugin_basename` | Expected canonical basename after normalization. |
| `github_repo` | GitHub `owner/repo`. |
| `github_branch` | Branch to check/download. |
