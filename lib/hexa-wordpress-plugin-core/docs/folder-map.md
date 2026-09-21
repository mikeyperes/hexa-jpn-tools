# Folder Map

This package uses one root namespace and explicit folders for every sub-namespace.

## Fixed Root

```text
Hexa\PluginCore\
```

The Composer package name is:

```text
hexa/plugin-core
```

The repository folder is:

```text
hexa-wordpress-plugin-core
```

The package version is stored in the root `VERSION` file. Root `bootstrap.php` selects one vendored package owner for the shared namespace, and `PACKAGE_HASH` identifies the executable package build.

## Sub-Namespace Folders

| Folder | Namespace | Purpose |
| --- | --- | --- |
| `src/AcfFieldFactory/` | `Hexa\PluginCore\AcfFieldFactory` | Reusable ACF field array factories for host field-group registrations. |
| `src/ActivityLog/` | `Hexa\PluginCore\ActivityLog` | Activity logs and activity storage adapters. |
| `src/BrandColors/` | `Hexa\PluginCore\BrandColors` | HWS brand color readers plus safe Elementor color and font-source discovery and resolution. |
| `src/BrandProfiles/` | `Hexa\PluginCore\BrandProfiles` | Normalized public domain, identity, logo, color, and support-email values. |
| `src/CoreBootstrap/` | `Hexa\PluginCore\CoreBootstrap` | Core setup, module registration, and lifecycle. |
| `src/CoreContracts/` | `Hexa\PluginCore\CoreContracts` | Interfaces and the generic legacy `register()` adapter shared across modules and host plugins. |
| `src/CorePackageUpdates/` | `Hexa\PluginCore\CorePackageUpdates` | Vendored Core checks, update UI, explicit fleet downloads, and automatic verified local fleet synchronization after plugin lifecycle changes. |
| `src/CoreRuntime/` | `Hexa\PluginCore\CoreRuntime` | Shared value objects and small helpers. |
| `src/ContentCleanup/` | `Hexa\PluginCore\ContentCleanup` | Old content detection, filterable WordPress content scans, guarded trash/delete actions, AJAX table updates, and live cleanup activity logs. |
| `src/ContentTypes/` | `Hexa\PluginCore\ContentTypes` | Immutable-key CPT definitions, settings, registration, ACF integration, AJAX, and management UI. |
| `src/CredentialVault/` | `Hexa\PluginCore\CredentialVault` | Encrypted credential/API-key storage, masking, and credential field examples. |
| `src/DatabaseCleanup/` | `Hexa\PluginCore\DatabaseCleanup` | Provider-backed database cleanup sessions and table optimization. |
| `src/DataNormalization/` | `Hexa\PluginCore\DataNormalization` | Generic scalar, ACF/meta field, and WordPress media normalization. |
| `src/EntitySources/` | `Hexa\PluginCore\EntitySources` | Optional canonical entity settings, user/post resolution, attached-user extraction, migration, inspection, AJAX, and UI. |
| `src/FieldStructures/` | `Hexa\PluginCore\FieldStructures` | ACF group registration, toggles, settings panels, AJAX, and reusable field-structure displays. |
| `src/FrontendForms/` | `Hexa\PluginCore\FrontendForms` | Canonical public form-field schemas, WYSIWYG sanitization, and plain-text projection. |
| `src/FaqSets/` | `Hexa\PluginCore\FaqSets` | FAQ set sanitizing, item normalization, primary-set resolution, safe answer links, FAQPage schema, and reusable list or accordion output. |
| `src/GettingStartedChecklist/` | `Hexa\PluginCore\GettingStartedChecklist` | Reusable getting-started checklist config, step/subtask definitions, guarded AJAX runner, sequential setup UI, and technical activity logs. |
| `src/IntegrationTests/` | `Hexa\PluginCore\IntegrationTests` | Core/host release assertions, deterministic runner, protected HTML/JSON endpoint, and detailed pass/fail report UI. |
| `src/LogFiles/` | `Hexa\PluginCore\LogFiles` | Error-log sources, readers, classifiers, and reusable viewer panels. |
| `src/LiteSpeedCache/` | `Hexa\PluginCore\LiteSpeedCache` | Host-declared profiles, generic audit/apply/verify, and the official LiteSpeed Conf adapter. |
| `src/MediaUploads/` | `Hexa\PluginCore\MediaUploads` | Reusable image validation and guarded WordPress Media Library storage. |
| `src/ObjectCache/` | `Hexa\PluginCore\ObjectCache` | Object-cache provider status and activation adapters. |
| `src/PluginProvisioning/` | `Hexa\PluginCore\PluginProvisioning` | Plugin discovery, status checks, WordPress.org installs, GitHub ZIP installs, folder normalization, and activation. |
| `src/PluginUpdates/` | `Hexa\PluginCore\PluginUpdates` | Host plugin GitHub version checks, update transients, zip downloads, and updater panels. |
| `src/QuerySafety/` | `Hexa\PluginCore\QuerySafety` | Filter/request eligibility checks plus exact static-front-page invariant capture and repair. |
| `src/SnippetRegistry/` | `Hexa\PluginCore\SnippetRegistry` | Snippet definitions, option toggles, test rules, related snippet internals, related shortcodes, basic README rendering, and AJAX handlers. |
| `src/ShortcodeRegistry/` | `Hexa\PluginCore\ShortcodeRegistry` | Shortcode definitions, registries, dashboard display rows, examples, live output, and testing. |
| `src/SiteStructure/` | `Hexa\PluginCore\SiteStructure` | Critical page blueprints, callback-backed assigned page storage, starter templates, page details, WordPress navigation menu creation, custom menu items, add-all-pages actions, menu structure attachment, and page-to-menu-item tools. |
| `src/SchemaDetection/` | `Hexa\PluginCore\SchemaDetection` | JSON-LD page scans, schema source detection, duplicate-type conflicts, FAQ validation, and dark report rendering. |
| `src/SchemaTools/` | `Hexa\PluginCore\SchemaTools` | Schema graph helpers, normalized JSON-LD documents, deduplication, and output injection. |
| `src/SearchDisplay/` | `Hexa\PluginCore\SearchDisplay` | Reusable public WordPress search-form templates, markup, styling, and accessible interactions. |
| `src/SearchQuery/` | `Hexa\PluginCore\SearchQuery` | Bounded term parsing, normalized native-search settings, selected source SQL, exact-query hook scoping, and guarded search-template adapters. |
| `src/SmartSearch/` | `Hexa\PluginCore\SmartSearch` | Smart search/X-Search AJAX endpoints and reusable typeahead renderers. |
| `src/SystemChecks/` | `Hexa\PluginCore\SystemChecks` | Grouped readiness, launch, schema, and environment checklist renderers. |
| `src/SystemEnvironment/` | `Hexa\PluginCore\SystemEnvironment` | Safe constants, INI, shell wrappers, size parsing, CPU/memory detection, and byte formatting. |
| `src/Taxonomies/` | `Hexa\PluginCore\Taxonomies` | Reusable taxonomy definitions, callback-backed registration, and reference UI. |
| `src/Typography/` | `Hexa\PluginCore\Typography` | Prefix-scoped typography-preservation setting keys, defaults, values, and preview-state classes. |
| `src/WpAdminAjax/` | `Hexa\PluginCore\WpAdminAjax` | WordPress admin-AJAX nonce, capability, request parsing, action registration, and callback guards. |
| `src/WpAdminComponents/` | `Hexa\PluginCore\WpAdminComponents` | Shared UI primitives: cards, subcards, buttons, pills, tooltips, collapsibles, selectable media gallery details, color/font controls, and scoped CSS override references. |
| `src/WpAdminTabs/` | `Hexa\PluginCore\WpAdminTabs` | Admin tab definitions, registries, rendering contracts, and the automatic core tab. |
| `src/WpConfigFile/` | `Hexa\PluginCore\WpConfigFile` | Safe wp-config.php constant and ini_set reads/writes with validation and rollback backup handling. |
| `src/WpCronTasks/` | `Hexa\PluginCore\WpCronTasks` | WP-Cron interval registration, scheduling, unscheduling, event inspection, and health status payloads. |
| `src/WordPressOperations/` | `Hexa\PluginCore\WordPressOperations` | Native WordPress update, auto-update policy, discussion/comment, and permalink status/action services. |

## Naming Rules

Class names are singular unless they are collection registries.

Good:

```text
ActivityLogEntry
ActivityLogger
CoreBootstrap
PluginContext
ShortcodeDefinition
ShortcodeRegistry
TabDefinition
TabRegistry
UpdaterConfig
```

Bad:

```text
HwsActivityLogger
HexaBaseToolsTabs
PluginCoreShortcodesManagerThing
UpdaterStuff
```

## Adding A New Namespace

Do not add a new sub-namespace casually.

Before adding one, document:

1. Why none of the existing folders fit.
2. The exact folder name.
3. The exact namespace.
4. The public classes that will live there.
5. Which host plugin needs it first.

Then update:

- `README.md`
- `AGENTS.md`
- `docs/folder-map.md`
