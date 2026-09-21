# Hexa WordPress Plugin Core Agent Instructions

These instructions are for Codex, Claude, and any other implementation agent modifying or consuming `hexa-wordpress-plugin-core`.

## Non-Negotiable Names

Use these exact names:

- Repository folder: `hexa-wordpress-plugin-core`
- Composer package: `hexa/plugin-core`
- Root PHP namespace: `Hexa\PluginCore\`
- Source folder: `src/`
- Version file: `VERSION`
- Package fingerprint: `PACKAGE_HASH`

Do not invent alternatives. Do not use host plugin names inside this package.

Forbidden shared-core namespaces:

- `HWS\BaseTools\PluginCore`
- `hws_base_tools`
- `HexaWordPressPluginCore`
- `Hexa\Core`
- `HexaWP`
- `HexaTiger`

Host plugin namespaces may exist only inside the host plugin repository. Shared code in this package must remain `Hexa\PluginCore`.

## Folder Contract

Each sub-namespace must have a matching folder:

```text
src/ActivityLog/        Hexa\PluginCore\ActivityLog
src/AcfFieldFactory/    Hexa\PluginCore\AcfFieldFactory
src/BrandColors/        Hexa\PluginCore\BrandColors
src/BrandProfiles/      Hexa\PluginCore\BrandProfiles
src/CoreBootstrap/      Hexa\PluginCore\CoreBootstrap
src/CoreContracts/      Hexa\PluginCore\CoreContracts
src/CorePackageUpdates/ Hexa\PluginCore\CorePackageUpdates
src/CoreRuntime/        Hexa\PluginCore\CoreRuntime
src/ContentTypes/       Hexa\PluginCore\ContentTypes
src/CredentialVault/    Hexa\PluginCore\CredentialVault
src/DatabaseCleanup/    Hexa\PluginCore\DatabaseCleanup
src/DataNormalization/  Hexa\PluginCore\DataNormalization
src/EntitySources/      Hexa\PluginCore\EntitySources
src/FaqSets/            Hexa\PluginCore\FaqSets
src/FieldStructures/    Hexa\PluginCore\FieldStructures
src/FrontendForms/      Hexa\PluginCore\FrontendForms
src/IntegrationTests/   Hexa\PluginCore\IntegrationTests
src/LogFiles/           Hexa\PluginCore\LogFiles
src/LiteSpeedCache/     Hexa\PluginCore\LiteSpeedCache
src/MediaUploads/       Hexa\PluginCore\MediaUploads
src/ObjectCache/        Hexa\PluginCore\ObjectCache
src/PluginProvisioning/ Hexa\PluginCore\PluginProvisioning
src/PluginUpdates/      Hexa\PluginCore\PluginUpdates
src/QuerySafety/        Hexa\PluginCore\QuerySafety
src/SnippetRegistry/    Hexa\PluginCore\SnippetRegistry
src/ShortcodeRegistry/  Hexa\PluginCore\ShortcodeRegistry
src/SiteStructure/      Hexa\PluginCore\SiteStructure
src/SearchDisplay/      Hexa\PluginCore\SearchDisplay
src/SearchQuery/        Hexa\PluginCore\SearchQuery
src/SchemaTools/        Hexa\PluginCore\SchemaTools
src/SmartSearch/        Hexa\PluginCore\SmartSearch
src/SystemEnvironment/  Hexa\PluginCore\SystemEnvironment
src/Taxonomies/         Hexa\PluginCore\Taxonomies
src/WpAdminAjax/        Hexa\PluginCore\WpAdminAjax
src/WpAdminComponents/  Hexa\PluginCore\WpAdminComponents
src/WpAdminTabs/        Hexa\PluginCore\WpAdminTabs
src/WpConfigFile/       Hexa\PluginCore\WpConfigFile
src/WpCronTasks/        Hexa\PluginCore\WpCronTasks
src/WordPressOperations/ Hexa\PluginCore\WordPressOperations
```

If you add a namespace, add it to `README.md`, `docs/folder-map.md`, and this file in the same change.

## Setup Protocol

Every host plugin must initialize the core in the same order:

1. Require root `bootstrap.php` and register the host package candidate.
2. Let the shared resolver select one package before any Core class is referenced.
3. Create `Hexa\PluginCore\CoreRuntime\PluginContext`.
4. Create `Hexa\PluginCore\CoreBootstrap\CoreBootstrap`.
5. Add modules and call `boot()` once.

Never make a module boot itself at file include time. Modules register hooks from their `register()` method only.

## Implementation Rules

- Put interfaces in `src/CoreContracts`.
- Put reusable ACF field array factories in `src/AcfFieldFactory`.
- Put runtime value objects and version metadata in `src/CoreRuntime`.
- Put reusable immutable-key CPT definitions, settings, registration, AJAX, and UI in `src/ContentTypes`; hosts own the actual content-type definitions and business rules.
- Put optional canonical website/entity selection, attached-user resolution, source migration, field inspection, and UI in `src/EntitySources`.
- Put non-mutating Core/host release checks, pass/fail normalization, protected report endpoints, and report UI in `src/IntegrationTests`; hosts register only their plugin-specific assertions.
- Put reusable database cleanup sessions, table optimization, provider activation, and live row reporting in `src/DatabaseCleanup`.
- Put generic scalar, field, and media normalization in `src/DataNormalization`; hosts retain domain mapping and schema construction.
- Put host-declared LiteSpeed option/profile audit, apply, and verification in `src/LiteSpeedCache`; Core must not define a recommended profile.
- Put native immediate updates, future auto-update policy, discussion/comment actions, and permalink repair in `src/WordPressOperations`.
- Put reusable object-cache provider status and activation adapters in `src/ObjectCache`.
- Put admin tab abstractions in `src/WpAdminTabs`.
- Put reusable visual primitives in `src/WpAdminComponents`.
- Put reusable ACF field-group registration, toggles, settings panels, AJAX, and field-structure displays in `src/FieldStructures`; hosts own their field arrays.
- Put FAQ normalization, source adapters, reusable HTML, and FAQPage graph helpers in `src/FaqSets`.
- Put normalized public brand identities in `src/BrandProfiles`; product and service data remains host-owned.
- Put canonical public field schemas and rich-text normalization in `src/FrontendForms`.
- Put reusable image validation and Media Library adapters in `src/MediaUploads`; hosts retain nonce, capability, ownership, and retention rules.
- Put reusable error-log viewer/read/classification features in `src/LogFiles`.
- Put reusable plugin discovery, install, activation, GitHub ZIP provisioning, and folder-normalization helpers in `src/PluginProvisioning`.
- Put reusable snippet definitions, option toggles, test rules, related snippets, related shortcodes, basic README rendering, and AJAX handlers in `src/SnippetRegistry`.
- Put reusable API-key/secret storage, masking, and credential setup UI in `src/CredentialVault`.
- Put reusable smart search/X-Search endpoint and typeahead UI in `src/SmartSearch`.
- Put reusable critical page blueprints, assigned page storage, navigation menu creation, menu structure attachment, and page-to-menu-item tools in `src/SiteStructure`.
- Put activity log abstractions, storage modes, and the shared dark renderer in `src/ActivityLog`.
- Put shortcode registries, definitions, display renderers, examples, live output, and testing tools in `src/ShortcodeRegistry`.
- Put reusable front-end WordPress search-form templates and their shared interaction assets in `src/SearchDisplay`.
- Put reusable native WordPress search-result matching, bounded term parsing, query configuration, and exact-query SQL scoping in `src/SearchQuery`.
- Put schema document normalization, graph rendering, deduplication, and output injection in `src/SchemaTools`; hosts retain schema object construction.
- Put reusable taxonomy definitions, registration, and reference UI in `src/Taxonomies`; hosts retain taxonomy terms and editorial meaning.
- Keep all three search domains separate: `SearchDisplay` renders native GET forms, `SearchQuery` changes an explicitly eligible native results query, and `SmartSearch` provides AJAX typeahead/content selection.
- Put safe constants, INI, shell wrappers, size parsing, CPU/memory detection, and byte formatting in `src/SystemEnvironment`.
- Put host plugin GitHub/update configuration and updater abstractions in `src/PluginUpdates`.
- Put exact WordPress query eligibility predicates and narrowly scoped invariant repair in `src/QuerySafety`; host query callbacks must reject ineligible queries before loading settings or attaching SQL filters.
- Put vendored core package version checks and core package update UI in `src/CorePackageUpdates`; do not treat the shared core as a WordPress plugin.
- Put WordPress admin-AJAX nonce/capability/request parsing/action registry/handler guards in `src/WpAdminAjax`.
- Put bootstrap/lifecycle orchestration in `src/CoreBootstrap`.
- Put safe `wp-config.php` constant and `ini_set()` reads/writes in `src/WpConfigFile`.
- Put reusable WP-Cron task scheduling, unscheduling, interval registration, and status inspection in `src/WpCronTasks`.

## WordPress Rules

- Core code may call WordPress functions only where the class is explicitly WordPress-facing.
- Generic value objects should not call WordPress functions.
- Do not hard-code `manage_options`; read capability from the context unless a host explicitly overrides it.
- Do not hard-code plugin slugs, GitHub repos, admin page slugs, paths, URLs, or versions.
- Do not duplicate credential/API-key storage or masking in host plugins.
- Do not duplicate typeahead search UI in host plugins.
- Never attach broad search SQL. Reject admin, AJAX, REST, cron, feeds, nested queries, empty searches, and unrelated requests before loading host settings; use one idempotent `posts_search` dispatcher with weak exact-object state instead of query-capturing closures.
- Call `QuerySafety\StaticFrontPageQueryGuard::is_static_front_page_main_query()` before settings or provider reads in any host callback that could change a home/front-page query. Core's final invariant repair is defense in depth, not permission to run broad callbacks first.
- Call `QuerySafety\QueryEligibility::allows_main_filtered_frontend_query()` or `allows_main_or_explicit_filtered_frontend_query()` before pairing a query mutation with `posts_*` SQL filters. Secondary Elementor or related-content loops require an explicit host-owned query marker and strict allowed values; never infer their context from global conditional functions.

## Documentation Rule

Any new public class or protocol must be documented in `docs/`.

Minimum documentation for a new implementation:

- namespace
- folder
- purpose
- host plugin responsibilities
- example usage
- testing method

For new plugin audits, start with `docs/new-plugin-master-checklist.md`. Do not invent a new audit checklist inside a host plugin.

Also update `HEXA_PLUGIN_CORE_LIBRARY.md` whenever a public namespace, class, setup protocol, or host integration pattern changes. That file is intended to be copied into every plugin that consumes the core.

The automatic core tab must remain host-neutral. Host plugins provide hook names; the implementation stays in `Hexa\PluginCore\WpAdminTabs`.

## Commit Hygiene

Do not create backup files. Do not commit generated vendor files unless explicitly requested. Keep implementation and docs in the same commit when they define a new public pattern.
