# Hexa WordPress Plugin Core

Shared WordPress plugin core for Hexa plugins.

This package exists to stop each plugin from re-implementing the same admin tabs, activity logs, updater wiring, shortcode lists, and setup patterns differently.

## Package Identity

These names are fixed. Do not rename them in plugin implementations.

| Item | Value |
| --- | --- |
| Repository folder | `hexa-wordpress-plugin-core` |
| Composer package | `hexa/plugin-core` |
| Root namespace | `Hexa\PluginCore\` |
| Autoload path | `src/` |
| Version source | `VERSION` |

## Required Folder Map

Every sub-namespace has its own folder under `src/`.

```text
hexa-wordpress-plugin-core/
  VERSION
  PACKAGE_HASH
  bootstrap.php
  src/
    AcfFieldFactory/    -> Hexa\PluginCore\AcfFieldFactory
    ActivityLog/        -> Hexa\PluginCore\ActivityLog
    BrandColors/        -> Hexa\PluginCore\BrandColors
    BrandProfiles/      -> Hexa\PluginCore\BrandProfiles
    CoreBootstrap/      -> Hexa\PluginCore\CoreBootstrap
    CoreContracts/      -> Hexa\PluginCore\CoreContracts
    CorePackageUpdates/ -> Hexa\PluginCore\CorePackageUpdates
    CoreRuntime/        -> Hexa\PluginCore\CoreRuntime
    ContentCleanup/     -> Hexa\PluginCore\ContentCleanup
    ContentTypes/       -> Hexa\PluginCore\ContentTypes
    CredentialVault/    -> Hexa\PluginCore\CredentialVault
    DatabaseCleanup/    -> Hexa\PluginCore\DatabaseCleanup
    DataNormalization/  -> Hexa\PluginCore\DataNormalization
    EntitySources/      -> Hexa\PluginCore\EntitySources
    FieldStructures/    -> Hexa\PluginCore\FieldStructures
    FrontendForms/      -> Hexa\PluginCore\FrontendForms
    FaqSets/            -> Hexa\PluginCore\FaqSets
    GettingStartedChecklist/
                        -> Hexa\PluginCore\GettingStartedChecklist
    IntegrationTests/   -> Hexa\PluginCore\IntegrationTests
    LogFiles/           -> Hexa\PluginCore\LogFiles
    LiteSpeedCache/     -> Hexa\PluginCore\LiteSpeedCache
    MediaUploads/       -> Hexa\PluginCore\MediaUploads
    ObjectCache/        -> Hexa\PluginCore\ObjectCache
    PluginChecks/       -> Hexa\PluginCore\PluginChecks
    PluginProvisioning/ -> Hexa\PluginCore\PluginProvisioning
    PluginUpdates/      -> Hexa\PluginCore\PluginUpdates
    QuerySafety/        -> Hexa\PluginCore\QuerySafety
    SnippetRegistry/    -> Hexa\PluginCore\SnippetRegistry
    ShortcodeRegistry/  -> Hexa\PluginCore\ShortcodeRegistry
    SiteStructure/      -> Hexa\PluginCore\SiteStructure
    SchemaDetection/    -> Hexa\PluginCore\SchemaDetection
    SchemaTools/        -> Hexa\PluginCore\SchemaTools
    SearchDisplay/      -> Hexa\PluginCore\SearchDisplay
    SearchQuery/        -> Hexa\PluginCore\SearchQuery
    SmartSearch/        -> Hexa\PluginCore\SmartSearch
    SystemEnvironment/  -> Hexa\PluginCore\SystemEnvironment
    Taxonomies/         -> Hexa\PluginCore\Taxonomies
    WpAdminUiCleanup/   -> Hexa\PluginCore\WpAdminUiCleanup
    WpAdminComponents/  -> Hexa\PluginCore\WpAdminComponents
    WpAdminAjax/        -> Hexa\PluginCore\WpAdminAjax
    WpAdminTabs/        -> Hexa\PluginCore\WpAdminTabs
    WpConfigFile/       -> Hexa\PluginCore\WpConfigFile
    WpCronTasks/        -> Hexa\PluginCore\WpCronTasks
    WordPressOperations/-> Hexa\PluginCore\WordPressOperations
```

## Stable 1.0 Release

Version 1.0.0 establishes the shared Core contracts as the stable major baseline for Hexa WordPress plugins. It consolidates reusable content-type and ACF registration, optional canonical entity sources, schema document handling, admin UI, AJAX, updater, search, cleanup, and frontend display infrastructure behind the `Hexa\PluginCore` namespace.

Version 1.1.2 renders canonical-entity social links as labeled rows with complete clickable URLs and makes disabled Core-managed ACF groups suppress active database copies that share the same group key.

Version 1.1.3 separates the complete WordPress/ACF entity field inventory into a reusable renderer so host plugins can place it in their field-management area without duplicating it inside the primary-entity selector.

Version 1.1.4 adds an opt-in unconfigured website-type state, an intentional empty primary-entity presentation, and reliable hidden-state styling for empty Smart Search selections.

Version 1.1.5 adds fault-tolerant schema URL normalization and reports invalid schema URLs without breaking page output or scans.

Version 1.1.6 adds reusable standalone schema-node normalization so host plugins can keep every top-level JSON-LD entity independently detectable while preserving typed author, publisher, copyright-holder, and image summaries.

Version 1.1.7 adds `MediaGalleryDetailsRenderer`, a host-neutral collapsed image-details panel with selectable attachments, full and generated-size URLs, new-tab links, and shared dynamic clipboard buttons.

Version 1.1.8 makes media URL copying fall back to the synchronous browser copy path when an exposed Clipboard API rejects the write.

Version 1.1.9 adds a reusable ACF gallery-details module with live native-gallery synchronization, context-aware AJAX removal, larger previews, and separate image-data and URL clipboard actions.

Version 1.2.0 adds an automatically registered static-front-page query invariant plus reusable main-or-explicit query eligibility predicates. Hexa query callbacks can reject the exact configured front-page main query, suppressed filters, background requests, and unmarked secondary loops before loading settings or attaching SQL filters, while Core repairs later post-type or page-ID mutations as defense in depth. The native search engine now uses one idempotent weak-state SQL dispatcher so duplicate preparation cannot stack closures or retain abandoned query objects.

Version 2.1.4 keeps Getting Started parent-step and full-checklist runs available when only child tasks are awaiting input. Runnable subtasks execute in their registered order while each input-dependent child validates itself, so an early dependency-provisioning task can install required packages before later actions run. Template selection continues to distinguish selected from loaded states and expose loading, success, and failure feedback. `TemplateSelectionControl` retains its no-design toggle, responsive column constraints, accessible visual grid, scaled preview viewports, selected-state behavior, host save hooks, and AJAX-tab reinitialization.

Version 3.0.0 establishes the coordinated major release for the expanded Core data-normalization, operations, provisioning, checklist-state, fleet-synchronization, and reusable admin infrastructure shipped in this source tree.

Version 3.0.6 preserves each plugin's inactive, site-active, or network-active state across native updates, normalizes the installed package into its canonical folder, clears discovery caches before restoration, and verifies the restored activation scope with explicit errors when installation or reactivation fails.

Version 3.0.5 makes external ACF sibling cards visually secondary to their CPT with quieter surfaces, smaller titles, compact switches and chevrons, and softer relationship rails. Imported field rows show label, name, and type with a per-field JSON disclosure sourced from the actual ACF definition, while expanded section and action spacing improves scanability.

Version 3.0.4 puts functional enable switches directly in CPT and ACF card headers, removes redundant status badges and key chips, and renders each CPT's ACF cards as immediate siblings outside the CPT accordion.

Version 3.0.3 organizes content-type management as collapsed CPT parent cards. Each parent contains its own CPT configuration followed by a separate collapsed child section for every attached ACF field group, including group metadata and a compact field inventory.

Version 3.0.2 replaces the content-type dashboard grid with a single-column settings flow that keeps editable labels and URL controls visible and moves technical metadata into a secondary disclosure.

Version 3.0.1 preserves explicit `@id` and `@type` relationship summaries when standalone schema nodes are normalized.

## Schema Tools

The 1.x line includes reusable schema graph helpers and a generic schema dashboard renderer. Host plugins can build their own schema objects, normalize independently detectable graph nodes with `SchemaGraph::standalone_nodes()`, opt into explicit `@id` and `@type` relationship summaries for linked top-level nodes, expose debug JSON, show ideal-vs-actual graph examples, provide validator links, render collapsed shortcode cards, and pass plugin-specific schema action panels through HexaWP Core instead of duplicating dashboard UI.

Do not create `HWS\BaseTools\PluginCore`, `HexaWordPressPluginCore`, `Hexa\Core`, or plugin-specific namespaces inside this package. Consuming plugins may have their own namespaces, but this shared package always stays under `Hexa\PluginCore`.

## First Core Areas

- `AcfFieldFactory`: reusable ACF field array factories for host field-group registrations.
- `ActivityLog`: shared activity log records, storage modes, and expandable dark log renderer.
- `BrandColors`: shared HWS Base Tools brand color readers, hex normalization, RGB conversion, and color-control payloads.
- `BrandProfiles`: normalized domain, identity, logo, color, and support-email values for reusable public experiences.
- `CoreBootstrap`: consistent setup/init protocol for loading this core in a host plugin.
- `CoreContracts`: interfaces that host plugins and core modules must follow.
- `CorePackageUpdates`: compares and updates one vendored Core package, downloads once for an explicit fleet update, and automatically propagates the newest verified bundle across registered host plugins after plugin lifecycle changes.
- `CoreRuntime`: runtime value objects, plugin context, version metadata, and selected-package integrity diagnostics.
- `ContentCleanup`: old content detection, backup file detection/deletion, article/media cleanup, all-matching and all-except-latest-X batch deletion, guarded AJAX actions, collapsible service cards, human-readable rule and scan-location detail cards, AJAX table updates, and collapsed Hexa Core Log Type 1 cleanup activity UI.
- `ContentTypes`: immutable WordPress post-type keys with reusable registration, editable labels and rewrite slugs, guarded AJAX persistence, functional header toggles, collapsed CPT cards, and immediate external ACF sibling cards.
- `CredentialVault`: encrypted API-key/secret storage, masking, and credential field examples.
- `DatabaseCleanup`: guarded provider-backed cleanup sessions, per-task cleanup, per-table optimization, pre/post provider state restoration, and live AJAX progress.
- `DataNormalization`: compatibility-friendly scalar, ACF/meta field, and WordPress media normalizers for host-owned data mappings.
- `EntitySources`: optional canonical website/entity selection, derived semantic types, legacy migration, user/post resolution, complete author/profile cards, attached-author extraction, field inspection, and reusable admin UI.
- `FieldStructures`: reusable ACF group registration and settings panels, a generic live ACF gallery-details module, plus displays and status checks for ACF groups, custom post types, taxonomies, and option-backed feature structures.
- `FrontendForms`: canonical public field schemas plus WordPress-safe WYSIWYG normalization and plain-text projection.
- `FaqSets`: shared FAQ set sanitizing, item normalization, primary-set resolution, safe answer links, FAQPage schema, and reusable list or accordion output.
- `GettingStartedChecklist`: reusable plugin startup/onboarding checklist UI, persistent live status/summary/reset, exact template routing, cumulative item capabilities, mutation-aware batch stopping, individual-only batch skipping, guarded AJAX execution, sequential subtasks, callback normalization, reports, previews, and technical activity logs.
- `IntegrationTests`: automatic Core and host release checks, plugin-defined test registration, exception-safe pass/fail execution, capability-protected HTML and JSON report URLs, detailed expected/actual output, and per-test timing.
- `LogFiles`: shared error-log source definitions, tail readers, classifiers, search/highlight UI, and renderers.
- `LiteSpeedCache`: array-driven host profiles, generic audit/apply/verify and casting, effective/stored provenance, and one-batch writes through the official LiteSpeed Conf API; Core supplies no recommended values.
- `MediaUploads`: reusable image MIME, extension, and size policy plus guarded WordPress Media Library storage.
- `ObjectCache`: provider-specific object-cache status and activation adapters, including verified LiteSpeed Redis checks.
- `PluginChecks`: shared required-plugin definitions, status checks, reusable collapsible plugin inventory tables, presence-based green/red Font Awesome SVG title indicators, Required/Optional badges, AJAX install/activate/deactivate/delete actions, subtle secondary row controls, update-cache refresh, and activity-log UI.
- `PluginProvisioning`: shared plugin discovery, site/network activation status checks, WordPress.org installs, GitHub ZIP installs, folder normalization, and activation.
- `PluginUpdates`: shared GitHub/update configuration objects and host plugin updater.
- `QuerySafety`: suppressed-filter/request eligibility checks, exact static-front-page detection, and parse-before-mutation invariant capture with final-priority repair.
- `SnippetRegistry`: shared snippet definitions, option toggles, test rules, related snippets, related shortcodes, basic README rendering, generic AJAX handlers, and the canonical snippets table UI.
- `ShortcodeRegistry`: shortcode definition registry, dashboard display renderer, examples, live output, and test runner contracts.
- `SiteStructure`: reusable critical page blueprint management, assigned page storage, WordPress navigation menu creation, custom menu-item creation, add-all-assigned-pages actions, menu structure attachment, and page-to-menu-item tools.
- `SchemaDetection`: reusable JSON-LD URL scans, source detection, semantic property validation, duplicate schema conflict checks, FAQ validation, and dark admin report rendering.
- `SchemaTools`: shared schema-document normalization, typed HTTP(S) URL guards, fail-closed URL-property sanitization, graph-node deduplication, JSON-LD rendering, and one-shot WordPress output injection while host plugins retain their schema builders.
- `SearchDisplay`: five reusable front-end WordPress search-form templates with shared markup, CSS, and accessible interactions.
- `SearchQuery`: bounded native WordPress result matching for all/any/exact terms, whole/prefix/contains word modes, selected post types and sources, one-query-only SQL hooks, and guarded JetEngine search-template bridging.
- `SmartSearch`: smart search/X-Search AJAX endpoint and reusable typeahead renderer.
- `SystemEnvironment`: safe constants, INI, shell wrappers, size parsing, CPU/memory detection, and byte formatting.
- `Taxonomies`: reusable taxonomy definitions, callback-backed registration, and shared reference UI for host-owned editorial taxonomies.
- `WpAdminUiCleanup`: shared admin UI cleanup definitions, AJAX toggles, target-screen CSS/JS, postbox hide/collapse behavior, and footer filters.
- `WpAdminComponents`: shared visual primitives such as cards, subcards, buttons, pills, tooltips, collapsible sections, three-column visual template selectors, selectable media gallery details, color controls, font-family controls, and scoped CSS override editors and references.
- `WpAdminAjax`: WordPress admin-AJAX nonce, capability, request parsing, action registration, and handler guards.
- `WpAdminTabs`: admin tab definitions, registry, host hook integration, and the automatic Hexa core documentation tab.
- `WpConfigFile`: safe `wp-config.php` constant and `ini_set()` reads/writes with validation and rollback backup handling.
- `WpCronTasks`: reusable WP-Cron interval registration, scheduling, unscheduling, event inspection, and health status payloads.
- `WordPressOperations`: native immediate core/plugin/theme updates, future auto-update policy, bounded discussion/comment actions, and permalink hard repair with structured before/after results.

## Host Plugin Integration Rule

A plugin using this package must provide a host context. The host context is the only place plugin-specific values belong.

Examples of host-specific values:

- plugin slug
- plugin basename
- plugin version
- plugin root path
- plugin root URL
- GitHub repository
- admin page slug
- WordPress capability

Core classes must read those values from `PluginContextInterface`. They must not hard-code a host plugin name.

## Required Setup Protocol

Every plugin that uses this core follows the same sequence:

1. Require the vendored root `bootstrap.php` and register the package candidate.
2. Wait for the shared resolver to select one package on `plugins_loaded`.
3. Build a `PluginContext`.
4. Build a `CoreBootstrap` with that context.
5. Register modules with the bootstrap and call `boot()` once.

Example:

```php
$core_root = __DIR__ . '/lib/hexa-wordpress-plugin-core';
require_once $core_root . '/bootstrap.php';
\hexa_plugin_core_register_package( 'hws-base-tools', $core_root );

use Hexa\PluginCore\CoreBootstrap\CoreBootstrap;
use Hexa\PluginCore\CoreRuntime\PluginContext;

$context = new PluginContext(
    [
        'slug'        => 'hws-base-tools',
        'basename'    => plugin_basename( __FILE__ ),
        'version'     => '10.18.27',
        'path'        => plugin_dir_path( __FILE__ ),
        'url'         => plugin_dir_url( __FILE__ ),
        'github_repo' => 'mikeyperes/hws-base-tools',
        'admin_page'  => 'hws-core-tools',
        'capability'  => 'manage_options',
    ]
);

( new CoreBootstrap( $context ) )
    ->add_module( $shortcodes_module )
    ->add_module( $tabs_module )
    ->add_module( $updater_module )
    ->boot();
```

## Development

Run the complete standalone suite with `php tests/run.php`.

## Agent Rule

Before adding implementations in another Codex or Claude chat, read:

- `AGENTS.md`
- `HEXA_PLUGIN_CORE_LIBRARY.md`
- `docs/folder-map.md`
- `docs/setup-protocol.md`
- `docs/implementation-checklist.md`
- `docs/integration-tests.md`
- `docs/new-plugin-master-checklist.md`
- `docs/content-cleanup.md`
- `docs/content-types.md`
- `docs/database-cleanup.md`
- `docs/entity-sources.md`
- `docs/object-cache.md`
- `docs/site-structure.md`
- `docs/schema-detection.md`
- `docs/schema-tools.md`
- `docs/search-display.md`
- `docs/search-query.md`
- `docs/field-structures.md`
- `docs/faq-sets.md`
- `docs/taxonomies.md`
- `docs/brand-colors.md`
- `docs/brand-profiles.md`
- `docs/frontend-forms.md`
- `docs/media-uploads.md`
- `docs/snippet-registry.md`
- the namespace-specific doc for the folder being changed

If a new feature does not fit an existing namespace, document the proposed namespace first before adding code.

## Core Package Versioning

The shared core is a library, not a WordPress plugin. Its current version is stored in the repository root `VERSION` file.

Host plugins that vendor this package should render a separate core-package status panel under the host plugin updater:

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

This panel compares the vendored `VERSION` in the host plugin with the public GitHub repository `VERSION`. The host plugin updater and the vendored core updater both render as default-open persistent collapse cards. Each card reports the Git repo, Git URL, Git branch, Git version, current version, current-vs-Git comparison, green/red status flag, check-for-updates action, normalized ZIP download, and live update activity log.

## Native Search Query Behavior

Version 0.19.60 adds a guarded JetEngine listing-grid adapter to `Hexa\PluginCore\SearchQuery`. Version 0.19.59 introduced the reusable native WordPress search-results engine, separating all/any/exact term logic from whole/prefix/contains word matching, supporting selected public post types and explicit native or advanced sources, and keeping display options outside the behavior contract.

Its `pre_get_posts` coordination is deliberately narrow: unrelated, admin, WP-CLI, AJAX, REST, cron, feed, unmarked nested, disabled, and empty queries are rejected before host settings are loaded. A trusted adapter may explicitly mark a secondary query created by a search-results template; one permanent dispatcher consumes weak state bound to that exact `WP_Query` object without retaining abandoned queries or stacking callbacks. See `docs/search-query.md` for the host protocol and mandatory performance guards.

## Collection Filters and Sidebar Header

Version 0.19.66 stores multiple open-card keys in one comma-delimited `hpc_open` value so WordPress admin canonicalization cannot discard all but the last key during refresh. The reader remains compatible with repeated legacy parameters.

Version 0.19.65 prevents parser- and AJAX-insertion `toggle` events from overwriting query or local-storage state before a collapsible has completed Core initialization. This keeps explicit closed state authoritative for default-open cards.

Version 0.19.64 makes every titled `CoreUi::collapsible()` section refresh-safe and linkable through the `hpc_open` query parameter. Query state is restored after full-page and AJAX tab loads, explicit query keys and opt-out are supported, and existing local-storage persistence remains available as a fallback.

Version 0.19.63 lets CoreUi::toggle() accept sanitized host input classes and data attributes, allowing AJAX-saving host plugins to use the shared toggle renderer without rebuilding its markup.

Version 0.19.62 gives collection-filter search inputs enough selector specificity and left padding to keep the shared search icon separate from placeholder and entered text under WordPress admin styles.

Version 0.19.57 gives filtered items an explicit Core hide state so host row layouts cannot override `hidden` and leave nonmatches visible. Core collapsible titles also wrap instead of truncating on narrow screens.

Version 0.19.56 lets reusable Getting Started checklists opt into Core collection search. Nested and standalone actions are filterable, parent context is indexed with each child, counts update immediately, and active searches are reapplied after template row replacement.

Version 0.19.55 keeps the complete grouped sidebar in normal document flow. The
rail no longer uses sticky positioning, so long navigation remains reachable by
normal page scrolling without pinning part of the sidebar to the viewport.

Version 0.19.52 adds CoreUi::collection_filter() for searchable admin-card collections. Hosts supply the target ID, item selector, optional group selector, labels, and empty-state text. Core owns case-insensitive matching, visible/total counts, group hiding, clear and Escape behavior, and reinitialization after hexa-core-host-tab-loaded.

Version 0.19.53 also initializes collection filters after DOMContentLoaded so first-render panels work before any AJAX navigation.

Version 0.19.54 adds an optional host-selected text selector so shared logs and diagnostics do not create false search matches.

Version 0.19.77 adds reusable public brand profiles, canonical front-end field schemas, WordPress-safe WYSIWYG values, and guarded image upload policies/Media Library storage for branded host plugins.

Version 0.19.76 gives the shared template controls plain-language Original Template, site-value, and custom-value labels. Typography inheritance toggles align consistently on the left, and inherited editors are disabled and visibly muted while the toggle remains operable.

Version 0.19.75 makes `TemplateColorControl` preserve an explicit picker or hex event before synchronizing its nested fallback display. Hosts now persist the newly selected custom color regardless of Core script render order.

Version 0.19.74 adds the generic `TemplateColorResolver`, `TemplateColorControl`, and `TemplateTypography` contracts. Template-based hosts now share one four-mode decorative color flow and one three-mode typography flow, including reset behavior, native palette fallbacks, Elementor-aware custom typography, per-property preservation, and live preview variables.

Version 0.19.73 makes typography color preservation disable the complete Core color editor, including its native picker and import actions, while leaving the preservation toggle available.

Version 0.19.72 adds a shared light highlight and stronger boundary to open `CoreUi::collapsible()` sections so expanded tools remain visually distinct without host CSS.

Version 0.19.71 keeps the typography color-preservation toggle attached to the Core color heading when detailed picker values wrap on narrower screens.

Version 0.19.70 adds `TypographyControl`, a complete host-neutral typography editor that places each preservation toggle beside its Core-owned family, weight, color, or size field. It supports multiple size fields and decorative color controls that remain editable while inherited text color is preserved.

Version 0.19.69 adds `TypographyPreservation` and `TypographyPreservationControl`. Hosts provide a setting prefix and optional target keys; Core owns the four reusable font-family, size, color, and weight preservation settings, toggle markup, target disabling, preview-state classes, and change events.

Version 0.19.68 extends `FontFamilyControl` with an optional Core-owned font-weight selector backed by `FontWeightProvider`. Hosts can persist a default or validated 100-900 weight without duplicating option markup or validation.

Version 0.19.67 adds `FontFamilyProvider` and `FontFamilyControl`. Hosts can offer template, native primary, native secondary, and deduplicated Elementor font choices while saving validated source identifiers instead of arbitrary CSS.

Use `TypographyPreservation::defaults()` and `TypographyPreservation::setting_keys()` for host persistence. Render `TypographyPreservationControl` inside a `data-hpc-typography-scope` container to expose the same four preservation toggles to any template feature. Core emits prefix-scoped state classes and `hexa-typography-preserve-change` events; the host only maps its typography controls and omits preserved CSS declarations.

Version 0.19.61 adds reusable inherited-value support to ColorControl. Hosts can persist an empty override while Core displays the inherited color and keeps picker, editable hex, RGB, swatch, copy, import, and inherit actions synchronized.

The grouped sidebar now places plugin identity and its expand/collapse control in one rail header. The expanded control sits at the top-right; collapsed mode hides identity and centers the control in the compact rail.

## Scoped CSS Override References

Version 0.19.48 updates `Hexa\PluginCore\WpAdminComponents\ScopedCssOverride`. Host plugins provide a scope selector, short instructions, a formatted HTML structure example, and a formatted CSS example. Core renders a copyable `CoreUi::detail_card()` that is closed by default and prevents each host plugin from rebuilding this reference UI.

Pass a setting key, saved value, host input class, and save-status HTML to render the actual CSS editor. Omit the setting key when a read-only reference is needed. Host plugins remain responsible for validation, persistence, and frontend output.

## Plugin Inventory Policy

Version 0.19.46 supports compact inventories without a separate Source column. Core places source beneath the plugin path, uses a fixed seven-column desktop layout without horizontal scrolling, and stacks labeled cells below 900px.

Version 0.19.45 keeps plugin inventory and plugin-check AJAX fragments free of duplicate Core/DynamicButton asset tags, so refreshed row markup contains only fragment content.

Version 0.19.44 makes plugin inventory policy explicit: satisfied required plugins render green, only host-configured entries are forbidden, absent forbidden entries can remain visible as compliant, and installed plugins outside registered policy remain neutral.

## Getting Started Checklist

Version 0.19.56 adds the opt-in `show_search`, `search_label`, `search_placeholder`, and `search_empty_message` checklist configuration. Core owns the search UI and nested filtering behavior; host plugins only enable and label it.

Version 0.19.40 decomposes the checklist and site-structure renderers into bounded asset collaborators and splits page menus and template workspaces behind the unchanged `PageStructureManager` facade. The package-local architecture test locks the public API and keeps every affected class below 700 lines.

Version 0.19.35 adds `show_type_badges` to `GettingStartedChecklistConfig`. Host plugins can hide the non-interactive request-type pill when a checklist is used as a simple action list.

Version 0.19.34 restores Getting Started Checklist rows to a single continuous list for simple actions. Only parent steps with actual subtasks render as expandable sections, so one-action checklist items keep their individual run button without a fake expand/collapse control.

Version 0.19.33 adds human-readable before/action/verified-after report summaries to Getting Started Checklist reports, including clearer wp-config and deleted-file report wording.

Version 0.19.32 adds optional image preview assets to checklist reports and renames wp-config report columns to `Target Value` and `Verified Value` so setup tasks can distinguish requested configuration from read-back proof.

Version 0.19.31 adds updater package hygiene. Core ZIP builders, direct plugin updates, vendored Core package updates, and GitHub plugin provisioning now exclude VCS metadata such as `.git`, `.svn`, `.hg`, and `.bzr`; native plugin updates purge ignored metadata before install and fail with a clear error if locked metadata remains. GitHub access tokens are no longer appended to package URLs and must travel only through request headers.

Version 0.19.30 adds an Activate action for installed-but-inactive forbidden plugin rows, so cleanup inventories can temporarily activate an unwanted plugin before taking other action when needed.

Version 0.19.29 adds `PluginRecommendationRegistry`, a reusable Hexa plugin recommendation registry for site inventory scans, aggregate recommendations, per-provider recommendations, and installed plugins that are not recommended by any registered Hexa plugin.

Version 0.19.28 adds subtle secondary Deactivate and Delete controls to reusable plugin inventory rows. Installed plugins can be deactivated or deleted through guarded AJAX actions while must-use and drop-in plugins remain blocked.

Version 0.19.27 adds `GettingStartedChecklist\DestructiveSampleRunner`, a reusable typed-confirmation sample that creates temporary posts/media, deletes only those temporary records, and returns Core deleted-post/deleted-file reports with permalinks, media URLs, file locations, and sizes.
Version 0.19.26 adds reusable Getting Started Checklist templates. Host plugins can register named templates such as `default` or `diamond_website`; Core renders the picker, loads the selected template's predefined step structure, sends the active template id through AJAX, and resolves callbacks against the selected template.

Version 0.19.25 adds reusable Getting Started Checklist result reports, typed destructive confirmation inputs, and a WpAdminUiCleanup adapter that turns registered cleanup options into checklist subtasks with their attributes listed directly in the checklist UI.

`Hexa\PluginCore\GettingStartedChecklist` provides the reusable setup checklist that every plugin can use for first-run checks, onboarding, or ordered setup tasks. Host plugins register the typed step list and callbacks; Core owns the UI, AJAX endpoint, sequential runner, collapsible parent steps, nested subtask processing, type badges, spinner/check/X states, and collapsed technical activity log.

```php
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistAjaxController;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistConfig;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistRenderer;

$config = new GettingStartedChecklistConfig([
    'root_id'      => 'my-plugin-getting-started',
    'nonce_action' => 'my_plugin_getting_started',
    'run_action'   => 'my_plugin_getting_started_run_item',
    'steps'        => [
        [
            'id'          => 'environment',
            'label'       => 'Verify Environment',
            'type'        => 'status_check',
            'description' => 'Checks WordPress and PHP values.',
            'subtasks'    => [
                [
                    'id'       => 'wordpress',
                    'label'    => 'WordPress Runtime',
                    'type'     => 'status_check',
                    'callback' => 'my_plugin_check_wordpress_runtime',
                ],
            ],
        ],
    ],
]);

( new GettingStartedChecklistAjaxController( $config ) )->register();
( new GettingStartedChecklistRenderer( $config ) )->render();
```

Callbacks receive a single payload array containing `step`, `subtask`, `context`, `request`, `request_type`, `is_subtask`, and `item_id`. Use `type` values of `callback`, `status_check`, `setup_action`, `feature_toggle`, `config_mutation`, `ajax_request`, or `custom`. Return `true`, `false`, a string, `WP_Error`, or an array with `success`, `message`, `logs`, and optional `data`.

## Content Cleanup

`Hexa\PluginCore\ContentCleanup` provides reusable cleanup structures for wp-admin. Host plugins pass their own action names, allowed post types, backup locations, and deletion limits; Core renders each cleanup service as a separate collapsible card, with fixed reports, filters, result tables, edit links, row flags, destructive buttons, row loaders, and closed-by-default dark activity logs.

Version 0.19.22 keeps cleanup renderers compatible with sites where another plugin has already loaded an older `Hexa\PluginCore\WpAdminComponents\CoreUi` class before the current plugin renders.

Version 0.19.21 keeps cleanup detection criteria backend-only in the operator UI and removes fixed minimum table widths so cleanup reports stay contained inside their collapsible cards.

Version 0.19.20 changes Getting Started Checklist parent rows into visible collapsible sections and keeps the technical activity log collapsed by default so checklist pages do not read like a debug log dump.

Version 0.19.16 changes cleanup services to manual scan by default. Content Cleanup, Backup Cleanup, and Article/Media Cleanup render immediately with a clear "Press Scan" empty state and only start AJAX work when the user clicks a scan button. Host plugins can opt back into load-time scanning with `auto_scan => true`.

Version 0.19.17 makes Article/Media Cleanup batch deletion the primary visible workflow. The two main actions are delete all matching posts or delete matching posts except the latest X, each with its own associated-media toggle. Filters, preview, selected-row deletion, and row deletion remain available in a collapsed Advanced Filters & Preview card.

Version 0.19.15 adds reusable article/media batch deletion for "delete all matching posts" and "delete all matching except the latest X posts." Batch deletion ignores the preview limit, runs through repeated AJAX requests, logs every batch, and can delete associated featured/inline/gallery media when the visible media cleanup toggle is enabled.

Version 0.19.14 adds a subtle Core detail-card variant and uses it for cleanup descriptions, detection rules, and scan-location details so secondary context stays collapsed and visually quiet. Backup scans now show an active loading row and log the file patterns searched, folders inspected, directory entries looked at, matched files, and no-result state.

Version 0.19.13 adds reusable collapsed detail subcards and uses them in cleanup services to show human-readable detection rules, descriptions, and every configured backup scan location with resolved directory status.

Version 0.19.12 keeps Core toggle inputs clipped to a 1px focusable control so hidden checkbox fields cannot create horizontal page overflow.

```php
use Hexa\PluginCore\ContentCleanup\ContentCleanupAjaxController;
use Hexa\PluginCore\ContentCleanup\ContentCleanupConfig;
use Hexa\PluginCore\ContentCleanup\ContentCleanupRenderer;

$config = new ContentCleanupConfig([
    'root_id'                => 'example-cleanup',
    'title'                  => 'Cleanup',
    'nonce_action'           => 'example_cleanup',
    'scan_action'            => 'example_cleanup_scan',
    'trash_action'           => 'example_cleanup_trash',
    'delete_action'          => 'example_cleanup_delete',
    'post_types'             => [ 'page' => 'Pages' ],
    'default_published_days' => 365,
    'show_filters'           => false,
    'count_label'            => 'Reported',
    'detection_rules'        => [
        [
            'id'                 => 'home_not_front',
            'label'              => 'Home',
            'tone'               => 'warning',
            'terms'              => [ 'home' ],
            'fields'             => [ 'title', 'slug' ],
            'exclude_option_ids' => [ 'page_on_front' ],
        ],
    ],
]);

( new ContentCleanupAjaxController( $config ) )->register();
( new ContentCleanupRenderer( $config ) )->render();
```

Version 0.19.4 adds:

- `BackupCleanupConfig`, `BackupCleanupScanner`, `BackupCleanupAjaxController`, and `BackupCleanupRenderer` for configured backup-file roots and extension-limited AJAX deletion.
- `ArticleMediaCleanupConfig`, `ArticleMediaCleanupScanner`, `ArticleMediaCleanupAjaxController`, and `ArticleMediaCleanupRenderer` for filtering posts, previewing matches, selecting rows, deleting individual posts, deleting all matching posts in AJAX batches, deleting all matching except the latest X posts, and optionally deleting detected featured/inline/gallery media attachments.

## Brand Color Controls

`Hexa\PluginCore\BrandColors\BrandColorProvider` reads the HWS Base Tools Brand Assets primary and secondary color options and can read Elementor site-setting color/font tokens. `Hexa\PluginCore\WpAdminComponents\ColorControl` renders one reusable admin color control with picker, editable hex value, RGB value, swatch, copy button, optional HWS primary import, and optional inherited-value persistence. `Hexa\PluginCore\WpAdminComponents\TemplateColorControl` renders the shared Original Template Color, Site Primary Color, Site Secondary Color, and Custom Design Color source flow for template-owned decorative colors. `Hexa\PluginCore\WpAdminComponents\DetailedColorPicker` renders the paired primary/secondary control with optional Elementor import and optional font controls.

Host plugins should pass their own setting key and wire save/import AJAX while reusing this markup instead of recreating color pickers.

```php
use Hexa\PluginCore\BrandColors\BrandColorProvider;
use Hexa\PluginCore\WpAdminComponents\ColorControl;

$brand = BrandColorProvider::payload('#2d5277');

echo ColorControl::render([
    'key' => 'accent_color',
    'label' => 'Accent color',
    'value' => $settings['accent_color'] ?? $brand['primary_color'],
    'default' => $brand['primary_color'],
    'import_brand' => true,
]);
```

Detailed visual example:

```text
Detailed Color Picker
+----------------------+----------------------+
| Primary color        | Secondary color      |
| Picker Hex RGB Copy  | Picker Hex RGB Copy  |
| Swatch               | Swatch               |
+----------------------+----------------------+
[Import Elementor colors and fonts]
```

```php
use Hexa\PluginCore\BrandColors\BrandColorProvider;
use Hexa\PluginCore\WpAdminComponents\DetailedColorPicker;

$brand = BrandColorProvider::payload('#2d5277');

echo DetailedColorPicker::render([
    'title' => 'Brand card colors',
    'description' => 'Use site design tokens or override this card.',
    'primary' => [
        'key' => 'primary_color',
        'value' => $settings['primary_color'] ?? $brand['primary_color'],
        'hex_input_class' => 'plugin-primary-color',
    ],
    'secondary' => [
        'key' => 'secondary_color',
        'value' => $settings['secondary_color'] ?? $brand['secondary_color'],
        'hex_input_class' => 'plugin-secondary-color',
    ],
    'show_primary' => true,
    'show_secondary' => true,
    'show_elementor_import' => true,
    'show_fonts' => true,
    'fonts' => [
        [
            'key' => 'primary_font_family',
            'token' => 'primary_font_family',
            'label' => 'Primary font family',
            'value' => $settings['primary_font_family'] ?? '',
        ],
        [
            'key' => 'secondary_font_family',
            'token' => 'secondary_font_family',
            'label' => 'Secondary font family',
            'value' => $settings['secondary_font_family'] ?? '',
        ],
    ],
]);
```

## SiteStructure Section Rendering

`Hexa\PluginCore\SiteStructure\SiteStructureRenderer` can render page assignments and menu tools together or separately. Use `show_pages => false` for a menu-only tab, and `show_menus => false` when a plugin keeps navigation tools somewhere else. This keeps menu building generic while host plugins provide their own page blueprint and action names.

## Activity Log Component

Use the activity component for updater progress, imports, tests, maintenance tasks, and any admin workflow that benefits from a collapsible dark monitor. Activity logs are collapsed by default; a host must explicitly pass `collapsed => false` when an open log is required.

Storage modes:

| Mode | Storage | Lifetime |
| --- | --- | --- |
| `page` | Rendered only | Removed on page refresh |
| `transient` | WordPress transient | Removed after TTL or clear |
| `permanent` | WordPress option | Kept until clear |

```php
use Hexa\PluginCore\ActivityLog\ActivityLogConfig;
use Hexa\PluginCore\ActivityLog\ActivityLogEntry;
use Hexa\PluginCore\ActivityLog\ActivityLogger;
use Hexa\PluginCore\ActivityLog\ActivityLogRenderer;

$config = new ActivityLogConfig(
    [
        'id'          => 'example-activity-log',
        'title'       => 'Example Activity Log',
        'storage'     => ActivityLogConfig::STORAGE_TRANSIENT,
        'storage_key' => 'example_activity_log',
        'collapsed'   => true,
    ]
);

$logger = new ActivityLogger( $config );
$logger->add( new ActivityLogEntry( 'Started import.', [ 'batch' => 12 ], 'admin', 'importer', null, 'info' ) );

( new ActivityLogRenderer( $config ) )->render( $logger->all() );
```

## Automatic Core Tab

Host dashboards expose a tab-list filter and tab-render filter. The core registers itself through those hooks:

```php
use Hexa\PluginCore\WpAdminTabs\CoreTabConfig;
use Hexa\PluginCore\WpAdminTabs\CoreTabModule;

( new CoreTabModule(
    new CoreTabConfig(
        [
            'tabs_filter'   => 'example_dashboard_tabs',
            'render_filter' => 'example_dashboard_render_tab',
            'core_root'     => __DIR__ . '/lib/hexa-wordpress-plugin-core',
            'readme_path'   => __DIR__ . '/lib/hexa-wordpress-plugin-core/README.md',
            'library_path'  => __DIR__ . '/HEXA_PLUGIN_CORE_LIBRARY.md',
        ]
    )
) )->register();
```

## UI Primitives

Use `Hexa\PluginCore\WpAdminComponents\CoreUi` for reusable admin UI pieces.

```php
use Hexa\PluginCore\WpAdminComponents\CoreUi;

CoreUi::render_assets();

echo CoreUi::card(
    [
        'title'     => 'System Status',
        'body_html' => '<p>Everything is healthy.</p>',
        'meta_html' => CoreUi::pill( 'Healthy', 'success' ),
    ]
);

echo CoreUi::collapsible(
    [
        'title'     => 'Advanced details',
        'body_html' => '<p>Hidden until expanded.</p>',
    ]
);
```

Titled `CoreUi::collapsible()` sections automatically synchronize their open state to a comma-delimited `hpc_open` query parameter and restore it after refresh or AJAX tab loading. Hosts may provide a stable `query_key`; use `query_state => false` only when URL persistence is inappropriate.

For a formatted, closed-by-default scoped CSS editor or reference:

```php
use Hexa\PluginCore\WpAdminComponents\ScopedCssOverride;

echo ScopedCssOverride::render(
    [
        'title'        => 'Component CSS override',
        'selector'     => 'body .example-component',
        'instructions' => [ 'Keep every rule inside this selector.' ],
        'html_example' => '<div class="example-component">...</div>',
        'css_example'  => "body .example-component {\n  color: #111827;\n}",
        'open'         => false,
    ]
);
```

## Credentials / API Keys

Use `Hexa\PluginCore\CredentialVault` for API-key and secret storage.

```php
$store = new \Hexa\PluginCore\CredentialVault\CredentialStore();
$store->store( 'openai', 'api_key', $raw_key );
$key = $store->get( 'openai', 'api_key' );
$masked = $store->get_masked( 'openai', 'api_key' );
$exists = $store->exists( 'openai', 'api_key' );
```

The storage key pattern is:

```text
hpc_cred_{slug}_{keyName}
```

## Front-End Search Display

Use `Hexa\PluginCore\SearchDisplay\SearchDisplayRenderer` for public site-search forms. It ships the `icon-reveal`, `overlay`, `pill`, `underline`, and `command` templates. Every template submits a native WordPress GET request using the `s` query parameter.

```php
echo \Hexa\PluginCore\SearchDisplay\SearchDisplayRenderer::render(
    [
        'style'       => 'pill',
        'accent'      => '#2f6df6',
        'placeholder' => 'Search stories...',
    ]
);
```

Host plugins own saved settings and shortcode registration. They must call this renderer for both admin previews and front-end output. Do not copy its markup or assets into the host plugin.

`SearchDisplay` is not the content-picker typeahead API. Use `SmartSearch` for AJAX result suggestions inside tools and admin workflows.

## Smart Search / X-Search

Use `Hexa\PluginCore\SmartSearch` for reusable typeahead search. This is the WordPress equivalent of Laravel `<x-hexa-smart-search>`.

```php
( new \Hexa\PluginCore\SmartSearch\SmartSearchRenderer() )->render(
    [
        'id'        => 'plugin-content-search',
        'label'     => 'Find content',
        'source'    => 'posts',
        'post_type' => 'any',
    ]
);
```

The core module registers:

```text
wp_ajax_hexa_plugin_core_smart_search
```

## WP Admin AJAX Registry

Use `Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry` for host plugin admin-AJAX actions. Host plugins provide action names and callbacks; core performs capability checks, nonce checks, request normalization, exception handling, and JSON responses.

```php
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;

( new AjaxActionRegistry(
    [
        'capability'   => 'manage_options',
        'nonce_action' => 'example_admin',
        'nonce_field'  => 'nonce',
    ]
) )->register(
    [
        'example_load_tab' => [
            'callback' => static function ( AjaxRequest $request ): array {
                return [ 'tab' => $request->key( 'tab', 'overview' ) ];
            },
        ],
    ]
);
```

## Shortcode Display Renderer

Use `Hexa\PluginCore\ShortcodeRegistry\ShortcodeDisplayRenderer` for shortcode admin lists. Every row should show the shortcode, description, real output value, and examples with parameters.

```php
use Hexa\PluginCore\ShortcodeRegistry\ShortcodeDisplayRenderer;

echo ( new ShortcodeDisplayRenderer() )->render(
    [
        [
            'label'       => 'Publication Name',
            'shortcode'   => '[smp_publication_field field="legal_name" format="text"]',
            'description' => 'Outputs the publication legal name.',
            'source'      => 'publication option: legal_name',
            'examples'    => [
                [
                    'label'      => 'Text value',
                    'shortcode'  => '[smp_publication_field field="legal_name" format="text"]',
                    'parameters' => [ 'field' => 'legal_name', 'format' => 'text' ],
                ],
            ],
        ],
    ],
    [
        'title'       => 'Shortcodes',
        'description' => 'Copy examples or inspect live output.',
    ]
);
```

## Error Log Viewer

Use `Hexa\PluginCore\LogFiles` for reusable error-log monitoring.

```php
use Hexa\PluginCore\LogFiles\ErrorLogPanelRenderer;
use Hexa\PluginCore\LogFiles\ErrorLogSource;

( new ErrorLogPanelRenderer() )->render(
    [
        new ErrorLogSource( 'debug', 'debug.log', WP_CONTENT_DIR . '/debug.log', true, 'delete-debug-log' ),
        new ErrorLogSource( 'error', 'error_log', ABSPATH . 'error_log', true, 'delete-error-log' ),
    ]
);
```


## Host Dashboard Tabs

Use `Hexa\PluginCore\WpAdminTabs\HostTabsRenderer` for the complete host dashboard shell. Core owns the tab or grouped-sidebar markup, responsive layout, AJAX loading, status updates, browser history, accessibility relationships, and optional persisted sidebar state. The host provides routes, labels, groups, request details, and the panel renderer.

For sidebar layouts, hosts may pass a generic `sidebar_identity` array containing plugin and Core names, installed versions, GitHub versions, and repository URLs. Core owns its escaped markup, external-link safety, responsive wrapping, and collapsed-state visibility.

```php
( new \Hexa\PluginCore\WpAdminTabs\HostTabsRenderer() )->render(
    [
        "tabs"                => $tabs,
        "active"              => $active,
        "page_url"            => admin_url( "options-general.php?page=example-plugin" ),
        "ajax_action"         => "example_load_tab",
        "nonce"               => $nonce,
        "root_id"             => "example-plugin-tabs",
        "panel_id"            => "example-plugin-panel",
        "layout"              => "sidebar",
        "groups"              => $navigation_groups,
        "sidebar_identity"    => [
            "plugin_name"     => "Example Plugin",
            "current_version" => $installed_version,
            "github_version"  => $github_version,
            "github_url"      => "https://github.com/example/example-plugin",
            "core_name"       => "Hexa WP Core",
            "core_version"    => $core_version,
            "core_github_url" => "https://github.com/mikeyperes/hexa-wordpress-plugin-core",
        ],
        "sidebar_collapsible" => true,
        "sidebar_collapsed"   => false,
        "sidebar_persist"     => true,
        "render_callback"     => [ $dashboard, "tab" ],
    ]
);
```

The sidebar is expanded by default, uses a 214px desktop rail, collapses to an icon control, and stores state under a key scoped to `root_id` when persistence is enabled. Optional identity metadata sits above navigation while expanded and is hidden when collapsed. Its rail remains in normal document flow and has no internal scroll container, so the complete navigation is reached through page scrolling. Mobile navigation and version text wrap without horizontal scrolling. Omit `layout` and `groups` for the standard top tab bar.

## System Checks

`Hexa\PluginCore\SystemChecks\SystemChecksRenderer` renders grouped pass/fail/warn/info checklists from a flat item array. Use it for launch readiness, plugin health, schema audits, and environment checks instead of duplicating checklist HTML in host plugins. See `docs/system-checks.md`.

## Schema Detection

`Hexa\PluginCore\SchemaDetection\SchemaPageScanner` fetches public URLs and extracts JSON-LD schema blocks into structured payloads. It also uses `SchemaGraph::validation_issues()` so malformed URL-property values fail even when the JSON itself is valid. `Hexa\PluginCore\SchemaDetection\SchemaScanRenderer` renders those payloads as a dark admin report with source labels, semantic property paths, duplicate-type conflict warnings, invalid JSON rows, and FAQPage validation. Host plugins keep their own expectations and pass those expected rows into the renderer. See `docs/schema-detection.md`.

```php
echo ( new \Hexa\PluginCore\SchemaDetection\SchemaScanRenderer() )->renderReport( [ $scan ], [ "title" => "Schema Detection Results" ] );
```

## FAQ Sets

`Hexa\PluginCore\FaqSets\FaqSetManager` sanitizes repeatable FAQ set data, normalizes question and answer items, resolves a `primary` set, adds safe link attributes to answer HTML, generates FAQPage schema, and renders reusable list or accordion output. Host plugins keep their own option names and shortcodes. See `docs/faq-sets.md`.

```php
$manager = new \Hexa\PluginCore\FaqSets\FaqSetManager();
$set = $manager->resolveSet( $sets, "primary", $primary_slug );
echo $manager->renderFaqs(
    $set,
    [
        "style" => "accordion",
        "inject_schema" => true,
    ]
);
```
