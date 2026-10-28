# Hexa WordPress Plugin Core

> Feature base for HWS Skills. Read before building on this plugin; use or
> extend these features instead of rebuilding them.

**Purpose:** the shared library bundled inside every Hexa plugin, so no plugin rebuilds admin tabs, AJAX, logs, fields, updaters, search or cleanup on its own. **Admin:** none of its own; the bundling plugin shows Core in its `update-center` tab and the automatic Hexa Core tab. **Depends on:** WordPress; ACF is optional (Fields work without it).

## Design examples and styling

- Search box, filters and result cards: `docs/hexa-search.md`
- Calendar: `docs/calendar.md`
- Map: `docs/map.md`
- Image zoom viewer: `docs/image-zoom.md`
- Item links and lightbox: `docs/item-link.md`
- Relative time: `docs/relative-time.md`
- Admin cards, buttons, pills, collapsibles: `docs/ui.md`
- Task runner dashboard: `docs/task-runner.md`

## Features

### Plugin bootstrap and context
- **Does:** loads one Core copy per site (the newest bundled one among all plugins), builds the plugin's context and boots its modules.
- **Use:** `hexa_plugin_core_register_package( '<slug>', <core root> )`, then `new PluginContext( [ slug, basename, version, path, url, github_repo, admin_page, capability ] )` and `( new CoreBootstrap( $context ) )->add_module( $m )->boot()`. Modules implement `ModuleInterface` (or extend `RegisterMethodModule`). `CorePackageRuntime::report()` shows which Core copy was selected and any integrity issues. See [Setup protocol](#setup-protocol).
- **Code:** `bootstrap.php`, `src/CoreBootstrap/`, `src/CoreContracts/`, `src/CoreRuntime/`

### Admin tabs and dashboard shell
- **Does:** the plugin's one admin page: top tabs or a grouped, collapsible sidebar with plugin and Core versions, tabs loaded by AJAX with the tab kept in the URL, plus the automatic Hexa Core tab showing this README.
- **Use:** `( new HostTabsRenderer() )->render( [ tabs, active, page_url, ajax_action, nonce, root_id, panel_id, layout => 'sidebar', groups, sidebar_identity, render_callback ] )`; `TabRegistry`/`TabDefinition` for the list; `( new CoreTabModule( new CoreTabConfig( [ tabs_filter, render_filter, core_root, readme_path, library_path ] ) ) )->register()`.
- **Extend:** the host's `<prefix>_dashboard_tabs` and `<prefix>_render_dashboard_tab` filters.
- **Code:** `src/WpAdminTabs/` (docs: `docs/tabs.md`)

### Admin UI components
- **Does:** the shared admin look: cards, subcards, detail cards, buttons, pills, tooltips, toggles, collapsible sections that remember their open state in the URL (`hpc_open`), searchable card lists, save notices without reload, loading buttons, the read-only source banner, visual template pickers, image-details panels and scoped CSS editors.
- **Use:** `CoreUi::render_assets()`, then `CoreUi::card()`, `collapsible()`, `detail_card()`, `pill()`, `toggle()`, `collection_filter()`; `DynamicNotice::render()`, `DynamicButton::render()`, `SourceBanner::render( [ owner, edit_url ] )`, `TemplateSelectionControl::render()`, `MediaGalleryDetailsRenderer::render()`, `ScopedCssOverride::render( [ title, selector, instructions, html_example, css_example ] )`.
- **Style:** `hpc-*` classes.
- **Code:** `src/WpAdminComponents/` (docs: `docs/ui.md`)

### Task runner
- **Does:** live progress for admin jobs that run as AJAX steps: header, panel switcher, stat tiles, progress bar with Stop, status badges, job tables, bulk bar and a live log.
- **Use:** `TaskRunner::header()`, `segments()`, `stats()`, `progress()`, `log()`, `render_assets()`; in the browser `HexaWpCoreTaskRunner.post()`, `queue()` (one at a time, or `concurrency` for read-only steps) and `loop()` (batches).
- **Style:** root class `hpc-runner-app`.
- **Code:** `src/WpAdminComponents/TaskRunner.php` (docs: `docs/task-runner.md`)

### Colour controls and brand colours
- **Does:** one colour picker (picker, hex, RGB, swatch, copy, import site colour, inherit), a paired primary/secondary picker with Elementor import, and the template colour flow (original, site primary, site secondary, custom); reads the site's brand colours from HWS Base Tools and Elementor.
- **Use:** `BrandColorProvider::payload( '#fallback' )`; `ColorControl::render( [ key, label, value, default, import_brand ] )`, `DetailedColorPicker::render()`, `TemplateColorControl::render()` with `TemplateColorResolver`; `ColorPalette`, `ElementorPaletteDetector`.
- **Code:** `src/BrandColors/`, `src/WpAdminComponents/ColorControl.php` (docs: `docs/brand-colors.md`)

### Typography controls
- **Does:** font family and weight pickers (template, site and Elementor fonts, saved as a source name, not raw CSS), a full typography editor, and "keep the site's font, size, colour or weight" switches.
- **Use:** `FontFamilyControl::render()` with `FontFamilyProvider` and `FontWeightProvider`; `TypographyControl::render()`; `TypographyPreservationControl::render()` inside a `data-hpc-typography-scope` container, with `TypographyPreservation::defaults()` and `setting_keys()` for saving; `TemplateTypography::options()`.
- **Extend:** listen for the `hexa-typography-preserve-change` browser event.
- **Code:** `src/Typography/`, `src/BrandColors/Font*Provider.php`, `src/WpAdminComponents/Typography*Control.php` (docs: `docs/typography-preservation.md`, `docs/font-family-control.md`)

### Brand profiles
- **Does:** one normalized brand record (domain, name, logo, colours, support email) for public pages a plugin brands.
- **Use:** `BrandProfile::from_array( $data )`, `->css_variables()`, `->to_array()`.
- **Code:** `src/BrandProfiles/` (docs: `docs/brand-profiles.md`)

### AJAX registry
- **Does:** admin AJAX actions with nonce and capability checks, request parsing, error handling and JSON replies done once.
- **Use:** `( new AjaxActionRegistry( [ capability, nonce_action, nonce_field ] ) )->register( [ 'action' => [ 'callback' => fn( AjaxRequest $r ) => [...] ] ] )`; `AjaxRequest::key()` and friends read input; throw `AjaxFailure` for a clean error.
- **Code:** `src/WpAdminAjax/` (docs: `docs/wp-admin-ajax.md`)

### Activity log
- **Does:** a collapsible dark log of what a job did, kept for the page, for a while (transient) or until cleared (option).
- **Use:** `new ActivityLogConfig( [ id, title, storage => STORAGE_PAGE|STORAGE_TRANSIENT|STORAGE_PERMANENT, storage_key, collapsed ] )`, `( new ActivityLogger( $config ) )->add( new ActivityLogEntry( $message, $context, $actor, $source, null, 'info' ) )`, `( new ActivityLogRenderer( $config ) )->render( $logger->all() )`.
- **Code:** `src/ActivityLog/` (docs: `docs/activity.md`)

### Shortcode registry
- **Does:** lists a plugin's shortcodes in admin with description, live output, examples with every parameter and a test run.
- **Use:** `ShortcodeRegistry::add()` with `ShortcodeDefinition`; `( new ShortcodeDisplayRenderer() )->render( $rows, [ title, description ] )` or `ShortcodeCatalogRenderer`; `ShortcodeTester::prepare()` returns `ShortcodeTestResult`.
- **Code:** `src/ShortcodeRegistry/` (docs: `docs/shortcodes.md`)

### Shortcode breakdown
- **Does:** explains one shortcode on a settings screen: the shortcode with a copy button, what it does, any alias tags, every attribute with its default, a plain explanation and its choices, and copyable examples.
- **Use:** `ShortcodeBreakdown::render( [ tag, title, description, aliases, attributes => [ name => [ default, description, options ] ], examples => [ [ label, shortcode ] ] ] )`.
- **Style:** root class `hpc-shortcode-breakdown`; code is colour-coded by `CodeDisplay`.
- **Code:** `src/ShortcodeRegistry/ShortcodeBreakdown.php`

### Code display
- **Does:** a colour-coded, read-only code block (HTML, CSS, PHP, JavaScript, JSON or shortcodes) with a title bar, language badge and copy button; highlighting runs on the server, every character escaped, no script library.
- **Use:** `CodeDisplay::render( $code, [ language, title, max_height ] )`; `CodeDisplay::highlight( $code, $language )` for inline markup.
- **Style:** root class `hpc-code-display`, token classes `hcd-*`.
- **Where:** every code sample on Core's own tab (Hexa Core tab and field structures) uses it.
- **Code:** `src/WpAdminComponents/CodeDisplay.php`

### Snippet registry
- **Does:** switchable code snippets with tests, related snippets and shortcodes, and the shared snippets table.
- **Use:** `SnippetRegistry::add()`/`add_many()`, `is_enabled()`, `set_enabled()`, `test()`; `SnippetsTableRenderer::render()`; `SnippetAjaxController::register()` for the toggle and test buttons.
- **Code:** `src/SnippetRegistry/` (docs: `docs/snippet-registry.md`)

### Fields (no ACF needed)
- **Does:** the one custom-field API: field groups, reading and saving values, repeaters, options pages, edit forms and the client-friendly help view, with ACF's exact function signatures and storage. With ACF active it hands every call to ACF; without it Core stores, formats and edits the same data itself. Safe during plugin startup: before ACF has started (`acf/init`), `Field::get()` returns the stored value instead of starting ACF too early (`Acf::ready()`). Without ACF, the field script and media library load only on post, user and term edit screens (`AdminScreens::field_screen()`).
- **Use:** `FieldGroups::add( $group )`, `Field::get()`/`update()`/`have_rows()`/`the_row()`/`get_sub_field()`, `OptionsPages::add()`, `Form::render()`/`Form::field()`, `Hooks::on( 'include_fields', ... )` or `hexa_fields_on()` while plugins load; field `developer_instructions` and group `client_view` (`Audience`).
- **Extend:** `hexa_fields/load_field_groups`, `hexa_fields/*` (ACF-named hooks), `hexa_core_fields_client_view`, ACF's location filters.
- **Code:** `src/Fields/` (docs: `docs/fields.md`); `bin/migrate-to-fields.php` moves a plugin off ACF calls.

### Field factory and field-group screens
- **Does:** short builders for field arrays, plus the admin cards that list a plugin's field groups, post types and taxonomies with on/off switches, status checks and a live gallery-details panel.
- **Use:** `AcfFieldFactory::text()`, `textarea()`, `wysiwyg()`, `url()`, `select()`, `toggle()`, `image()`, `gallery()` and the rest; `AcfFieldGroupRegistry::add()` + `register()` with `AcfFieldGroupRenderer`; `AcfSettingsPanel`; `AcfGalleryDetailsModule`; `FieldStructureRenderer::render()`.
- **Code:** `src/AcfFieldFactory/`, `src/FieldStructures/` (docs: `docs/field-structures.md`)

### Content types
- **Does:** registers a plugin's post types with editable labels and URL bases, on/off switches, attached field groups, and refreshes WordPress's URL rules when they change.
- **Use:** `ContentTypeRegistry::add()`/`add_many()` + `register()`; `ContentTypeRenderer::render()` and `ContentTypeAjaxController::register()` for the Content Model screen.
- **Code:** `src/ContentTypes/` (docs: `docs/content-types.md`)

### Taxonomies
- **Does:** registers a plugin's taxonomies with a reference screen, and limits how many terms non-privileged users may pick (radio buttons when the limit is one, checked again on save).
- **Use:** `TaxonomyRegistry::add()` + `register()`, `TaxonomyRenderer::render()`; `( new TermChoiceLimits( $rules ) )->register()`.
- **Code:** `src/Taxonomies/` (docs: `docs/taxonomies.md`)

### Front-end form fields and image uploads
- **Does:** one field schema for public forms, safe rich-text values with a plain-text copy, and image uploads checked for type and size and stored in the Media Library.
- **Use:** `FieldSchema::normalize()`, `RichTextValue::sanitize()`/`plain_text()`; `ImageUploadPolicy::validate()`/`accept_attribute()`, `WordPressImageUploader::store()`.
- **Code:** `src/FrontendForms/`, `src/MediaUploads/` (docs: `docs/frontend-forms.md`, `docs/media-uploads.md`)

### Data normalization
- **Does:** reads field and meta values safely and turns them into clean text, URLs, emails, dates, numbers, rows, images and galleries for a plugin's own data mapping.
- **Use:** `FieldReader::read()`/`first()`, `ValueNormalizer::text()`/`url()`/`email()`/`date()`/`rows()`, `MediaNormalizer::image()`/`gallery()`/`schema_image()`.
- **Code:** `src/DataNormalization/` (docs: `docs/data-normalization.md`)

### Website type and main entity
- **Does:** the site's type (person, company, publication and so on) and its main entity (a user or post), with the matching profile card, field inventory and admin picker.
- **Use:** `PrimaryEntityModule` (or `PrimaryEntityManager::settings()`/`resolve()`/`save()`), `PrimaryEntityRenderer::render()`, `CanonicalEntityResolver::resolve()`/`field()`, `EntityProfileCardRenderer::render()`, `EntityFieldInventoryRenderer::render()`.
- **Code:** `src/EntitySources/` (docs: `docs/entity-sources.md`)

### Credential vault
- **Does:** stores API keys and secrets encrypted, shows them masked.
- **Use:** `$store = new CredentialStore(); $store->store( 'service', 'api_key', $raw ); $store->get(); $store->get_masked(); $store->exists()`; option name `hpc_cred_{slug}_{keyName}`; `CredentialFieldRenderer::render_example()`.
- **Code:** `src/CredentialVault/` (docs: `docs/credentials.md`)

### Cron tasks
- **Does:** WP-Cron intervals, scheduling, unscheduling and a health readout per hook.
- **Use:** `WpCronTask::add_interval_schedule()`, `schedule_interval()`, `schedule_existing()`, `unschedule_hook()`, `status()`, `events_for_hook()`.
- **Code:** `src/WpCronTasks/` (docs: `docs/wp-cron-tasks.md`)

### Plugin checks and inventory
- **Does:** required and optional plugin lists with green/red status and install, activate, deactivate and delete buttons, the full site plugin inventory, and which Hexa plugin recommends which plugin.
- **Use:** `PluginCheckService` with `PluginCheckDefinition::from_array()`, `PluginChecksRenderer::render()` + `PluginChecksAjaxController::register()`; `PluginInventoryRenderer` + `PluginInventoryAjaxController`; `PluginRecommendationRegistry::register_hexa_plugin()`.
- **Extend:** `hexa_plugin_core_recommended_plugin_definitions`, `hexa_plugin_core_recommendation_providers`.
- **Code:** `src/PluginChecks/` (docs: `docs/plugin-checks.md`)

### Plugin installs and the plugin bridge
- **Does:** installs and activates plugins from WordPress.org or a GitHub zip into the right folder; once any Hexa plugin is on a site, an administrator Application Password can install or update allowed plugins over REST (the PHP 7.4 build on older PHP).
- **Use:** `PluginProvisioner::install_github_plugin()`, `install_wordpress_org_plugin()`, `plugin_status_by_folder()`; `PluginBridge::register()` (REST `hexa-plugin-core/v1/plugins/github`).
- **Extend:** `hexa_plugin_core/plugin_bridge_owners`.
- **Code:** `src/PluginProvisioning/` (docs: `docs/plugin-provisioning.md`)

### Plugin updater
- **Does:** updates the plugin from GitHub inside WordPress's updater, and the Git Report panel: repo, branch, GitHub against installed version, update button, version downloads and a live log; keeps the plugin's active state across updates.
- **Use:** `$config = UpdaterConfig::from_plugin_file( __FILE__, [...] )`, `( new GitHubPluginUpdater( $config ) )->register()`, `( new UpdaterAjaxController( $config ) )->register()`, `( new UpdaterPanelRenderer( $config ) )->render()`; `bin/build-php74-release.sh` makes the PHP 7.4 release zip.
- **Code:** `src/PluginUpdates/` (docs: `docs/updater.md`)

### Core package panel and fleet sync
- **Does:** shows the bundled Core against Core on GitHub with an update button, and copies the newest verified Core into every Hexa plugin on the site after plugin installs, updates and activations. After copying it loads the home page once and puts every copy back when the site no longer loads (server error or WordPress's critical-error page).
- **Use:** `$core = CorePackageConfig::from_core_root( <core root>, [ github_repo, github_branch, nonce_action, ajax_action_prefix ] )`, `( new CorePackageAjaxController( $core ) )->register()`, `( new CorePackagePanelRenderer( $core ) )->render()`; `CorePackageFleetSyncModule` runs the sync.
- **Extend:** `hexa_plugin_core_package_synchronized`, `hexa_plugin_core_package_sync_failed`.
- **Code:** `src/CorePackageUpdates/` (docs: `docs/core-package-runtime.md`)

### Integration tests
- **Does:** Core's and each plugin's release checks with pass/fail, expected against actual output and timing, on an admin page and as JSON.
- **Use:** `IntegrationTestRuntime::register_host()`, then add `TestDefinition`s on `hexa_plugin_core_register_integration_tests`; report at the `hexa-integration-tests` admin page (`TestEndpointController::url()`).
- **Code:** `src/IntegrationTests/` (docs: `docs/integration-tests.md`)

### Getting Started checklist
- **Does:** an ordered setup checklist with templates, nested steps, run-one or run-all, spinner/tick/cross states, saved status, reports and a technical log.
- **Use:** `new GettingStartedChecklistConfig( [ root_id, nonce_action, run_action, steps, templates, show_search ] )`, `( new GettingStartedChecklistAjaxController( $config ) )->register()`, `( new GettingStartedChecklistRenderer( $config ) )->render()`. Callbacks get one payload array and return `true`/`false`/a string/`WP_Error`/`[ success, message, logs, data ]`. `DestructiveSampleRunner` for a typed-confirmation sample.
- **Code:** `src/GettingStartedChecklist/` (docs: `docs/getting-started-checklist.md`)

### System checks
- **Does:** grouped pass/fail/warn/info checklists from a flat list, for launch readiness, plugin health and environment checks.
- **Use:** `( new SystemChecksRenderer() )->render( $items )`.
- **Code:** `src/SystemChecks/` (docs: `docs/system-checks.md`)

### System environment
- **Does:** safe reads of PHP constants and settings, shell calls that respect disabled functions, CPU and memory detection and byte formatting.
- **Use:** `SystemEnvironment::get_constant()`, `get_ini()`, `safe_shell_exec()`, `get_cpu_info()`, `get_memory_info()`, `parse_size()`.
- **Code:** `src/SystemEnvironment/` (docs: `docs/system-environment.md`)

### WP config file
- **Does:** reads and writes `wp-config.php` constants and `ini_set()` values safely, with a backup to roll back to.
- **Use:** `WpConfigFile::modify_constants()`, `defined_constants()`, `constant_status()`, `get_php_ini_value()`, `toggle_php_ini_value()`.
- **Code:** `src/WpConfigFile/` (docs: `docs/wp-config-file.md`)

### Log files
- **Does:** an error-log viewer: tail of each log, sorted by severity, search and highlight, delete.
- **Use:** `( new ErrorLogPanelRenderer() )->render( [ new ErrorLogSource( 'debug', 'debug.log', WP_CONTENT_DIR . '/debug.log', true, 'delete-debug-log' ) ] )`; `ErrorLogReader`, `ErrorLogClassifier`.
- **Code:** `src/LogFiles/` (docs: `docs/logs.md`)

### WordPress operations
- **Does:** updates WordPress, plugins and themes now, sets future auto-updates, closes or deletes comments and repairs permalinks, each with before/after results.
- **Use:** `UpdateOperations::update_all()` (or `update_core()`, `update_plugins()`, `update_themes()`), `AutoUpdatePolicy::apply()`, `DiscussionOperations::close_comments_and_pings()`/`delete_comments()`, `PermalinkOperations::repair()`; each has `status()`.
- **Code:** `src/WordPressOperations/` (docs: `docs/wordpress-operations.md`)

### Admin cleanup and client view
- **Does:** switches that hide admin clutter (notices, meta boxes, columns, admin-bar items, footer text), for everyone or only non-administrators, some turning on by themselves with a reason; presets for comments, FIFU, Rank Math and LiteSpeed; and keeping other plugins' notices and profile sections away from clients.
- **Use:** `( new CleanupRegistry( ... ) )->register()` with `CleanupOptionDefinition` (modes such as `meta_box_remove`, `audience => non_admins`, `auto_enabled`), `CleanupRenderer::render()` + `CleanupAjaxController::register()`; `CleanupPresets::comments_meta_box()`, `fifu_meta_box()`, `rankmath_lock_modified_date()`, `litespeed_ui()`, `client_notices()`, `client_profile()`; `ClientFacingPlugins::strip( $hook )`; `CleanupChecklistAdapter` turns options into checklist steps.
- **Extend:** `hexa_core_client_facing_plugins`.
- **Code:** `src/WpAdminUiCleanup/`

### Content cleanup
- **Does:** finds old or stray content, backup files, and posts with their media to delete: delete all matching, or all but the latest X, in AJAX batches with logs.
- **Use:** `ContentCleanupConfig` + `ContentCleanupAjaxController` + `ContentCleanupRenderer`; `BackupCleanup*` for backup files; `ArticleMediaCleanup*` for posts and their media. Scans run when the user presses Scan unless `auto_scan => true`.
- **Code:** `src/ContentCleanup/` (docs: `docs/content-cleanup.md`)

### Database cleanup (deprecated)
- **Does:** WP-Optimize-backed cleanup tasks and table optimization with live progress. HWS Base Tools now owns database cleanup; this module is removed once no site calls it.
- **Use:** `DatabaseCleanupService`, `DatabaseCleanupAjaxController`, `DatabaseCleanupRenderer`; do not add new callers.
- **Code:** `src/DatabaseCleanup/` (docs: `docs/database-cleanup.md`)

### Object cache
- **Does:** reads and turns on LiteSpeed's Redis object cache, verified.
- **Use:** `LiteSpeedRedisService::status()`, `enable()`.
- **Code:** `src/ObjectCache/` (docs: `docs/object-cache.md`)

### LiteSpeed Cache settings
- **Does:** checks, applies and verifies a plugin-supplied set of LiteSpeed Cache settings in one write through LiteSpeed's own settings API, showing the live and stored value of each. Core supplies no recommended values.
- **Use:** a `Profile` of `SettingDefinition`s, then `LiteSpeedCacheService::audit()`, `apply()`, `verify()` with `LiteSpeedConfAdapter` (or `CallbackConfigurationAdapter` in tests).
- **Code:** `src/LiteSpeedCache/` (docs: `docs/litespeed-cache.md`)

### Users profile bridge
- **Does:** a REST route to read and edit one user's profile, fields and avatar (never role, login or password), so signed connections can manage author profiles.
- **Use:** `UserProfileBridge::register()`; `GET/POST hexa-plugin-core/v1/users/{id}/profile` for users with `list_users` and `edit_user`.
- **Code:** `src/Users/`

### Site structure
- **Does:** the plugin's list of key pages, which page fills each, creating pages from templates, placing each page under its parent (also a parent another plugin owns) with its old address redirecting, the facts each page needs, and building WordPress menus from them.
- **Use:** `PageStructureManager` with the host's page blueprint (`parent`, `slugs` alternatives with `*`, `required_facts`, config `facts_getter`); `ensure_pages()` sets every page up in one call, `ensure_page( $key )` one, `resolve_page_id( $key )` finds a page from any plugin (filter `hexa_site_structure_page_id`), `missing_facts( $key )`, `PageStructureManager::place_under_parent()`, `PageRedirects`; `SiteStructureRenderer::render( [ show_pages, show_menus ] )`, `SiteStructureAjaxController::register()`; `PageStructureMenuService`, `PageStructureTemplateService`.
- **Code:** `src/SiteStructure/` (docs: `docs/site-structure.md`)

### Schema tools
- **Does:** the one schema library: node builders, WordPress data sources, shared `@id`s and one merged `hexa-schema` JSON-LD block per page from every plugin, plus the Rank Math schema switch and a schema dashboard.
- **Use:** `SchemaNodes::organization()`, `person()`, `article()`, `web_page()` and the rest with `SchemaSources` and `SchemaIds`; `SchemaPage::register( $source, $callback )` to add nodes; `RankMathSchemaControl::register()`; `SchemaGraph::standalone_nodes()`, `validation_issues()`; `SchemaDashboardRenderer::render()`.
- **Extend:** `hexa_plugin_core_schema_graph`.
- **Code:** `src/SchemaTools/` (docs: `docs/schema-tools.md`, `docs/schema-standalone-nodes.md`)

### Schema detection
- **Does:** scans public URLs for JSON-LD, names its source, checks properties, duplicate types and FAQ markup, and shows a dark report.
- **Use:** `SchemaPageScanner` to scan, `( new SchemaScanRenderer() )->renderReport( [ $scan ], [ 'title' => '...' ] )`.
- **Code:** `src/SchemaDetection/` (docs: `docs/schema-detection.md`)

### FAQ sets
- **Does:** FAQ sets with a primary set, safe answer links, FAQPage schema and list or accordion output.
- **Use:** `$m = new FaqSetManager(); $set = $m->resolveSet( $sets, 'primary', $slug ); echo $m->renderFaqs( $set, [ 'style' => 'accordion', 'inject_schema' => true ] )`; `FaqSourceResolver`.
- **Code:** `src/FaqSets/` (docs: `docs/faq-sets.md`)

### Search
- **Does:** the one search for every use: a search box with filters (type, category, tag, author, any taxonomy or custom field) and, for people or host directories, sorts. Results show in one of two places: the page's own list narrows in place (category, tag, author, search and post type pages, or any Elementor loop marked `hexa-listing-results`, such as a homepage section), or the box renders its own result cards. Typing and ticking refresh the results without a page reload; the results dim with one shared spinner while loading; filters take several choices and show counts; cards load more on scroll with a Load more link. Every state is a plain URL. The status line marks its count as `<b class="hls-count">` for styling, and any element marked `data-hls-sync` (such as a heading naming the search term) is refreshed with the results. Also draws the header search box (a magnifying glass that opens a field in place, or an always-open field), which goes to the search page.
- **Use:** register a profile with `SearchRegistry::register( 'id', [...] )` (add `render_item` for cards, `source` = `users` with `roles` for people), then `[hexa_search id="id"]` or `SearchBox::render( 'id', $options )`; `SearchBox::header( $options )` for the header. HWS Base Tools ships `[hws_search]` and `[hws_search_box]`. Add `SearchModule` to the host bootstrap.
- **Extend:** `hexa_plugin_core_search_target`, `hexa_plugin_core_search_context_args`, `hexa_plugin_core_search_post_types`, `hexa_plugin_core_search_register`.
- **Code:** `src/Search/`. Markup and styling: HWS Base Tools `docs/hws-search.md`; API: `docs/hexa-search.md`.

### Search matching
- **Does:** the text matching behind every search: all/any/exact terms, whole/prefix/contains words, chosen post types and fields, public items only (no drafts, passwords or noindex pages), natural time windows ("this week"), result-type labels with an Elementor dynamic tag, and a separate index of public Elementor text.
- **Use:** `SearchQueryEngine` with `SearchQueryConfiguration` (`searchable_post_types()`, `searchable_taxonomies()`), `SearchQueryEngine::restrict_to_public()`, `SearchMatchSql`, `ResultTypeLabels`, `ElementorPublicTextIndex`.
- **Extend:** `hexa_plugin_core_search_query_should_handle`.
- **Code:** `src/SearchQuery/` (docs: `docs/search-query.md`)

### Full-text search index
- **Does:** fast live search on large sites: one MySQL FULLTEXT row per published post with the configured search sources; the search engine uses it instead of scanning posts and postmeta.
- **Use:** `( new SearchIndex( $settings_provider ) )->register()`, `'index' => true` in the search settings, then build once with `rebuild()` pages.
- **Code:** `src/SearchQuery/SearchIndex.php`

### Query filters
- **Does:** visitor filters declared once (taxonomy, custom field, date range, author, post type, callback or a custom type) with several-choice checkboxes, any/all matching, number ranges, nested terms, counts, URL arguments and SQL, shared by Search, Calendar and Map.
- **Use:** filter definitions in a profile's `filters`; `QueryFilterSet`, `QuerySql`; new types with `QueryFilterTypes::register()` on `hexa_plugin_core_query_filter_types`; `NaturalTimeWindow` for "next 2 weeks".
- **Extend:** `hexa_plugin_core_query_filter_author_condition`.
- **Code:** `src/QueryFilter/` (docs: `docs/query-filters.md`)

### Query safety
- **Does:** keeps Hexa query changes off the static front page, admin, AJAX, REST, cron and unmarked secondary loops, and repairs a front-page query another plugin changed.
- **Use:** `QueryEligibility::allows_main_filtered_frontend_query( $query )` or `allows_main_or_explicit_filtered_frontend_query()` before touching a query; `StaticFrontPageQueryGuard` registers automatically.
- **Extend:** `hexa_plugin_core_should_protect_static_front_page_query`, `hexa_plugin_core_static_front_page_query_repaired`.
- **Code:** `src/QuerySafety/` (docs: `docs/query-safety.md`)

### Smart search (typeahead)
- **Does:** a typeahead box for picking content inside admin tools (the WordPress twin of Laravel's `<x-hexa-smart-search>`); not the public search.
- **Use:** `( new SmartSearchRenderer() )->render( [ id, label, source => 'posts', post_type ] )`; AJAX action `hexa_plugin_core_smart_search`.
- **Extend:** `hexa_plugin_core_smart_search_results`.
- **Code:** `src/SmartSearch/`

### Calendar
- **Does:** a month calendar of posts from a registered profile; each item links to its page, a new tab or a lightbox.
- **Use:** register a profile with `CalendarRegistry::register( 'id', [...] )` (post types, start/end fields, link, filters, sort), add `CalendarModule`, then `[hexa_calendar id="id"]`. Public data at REST `hexa-plugin-core/v1/calendar/{profile}`.
- **Extend:** `hexa_plugin_core_calendar_register`.
- **Code:** `src/Calendar/` (docs: `docs/calendar.md`)

### Map
- **Does:** a brandable map of posts or users by address, geocoded in the background without an API key, with clustered pins, group and date filters, item cards and a details sidebar.
- **Use:** `MapRegistry::register( 'id', [...] )`, add `MapModule`, then `[hexa_map id="id"]`; details at REST `hexa-plugin-core/v1/map/{profile}/details/{item}`.
- **Style:** `--hmap-*` CSS variables, including the base map.
- **Extend:** `hexa_plugin_core_map_register`.
- **Code:** `src/Map/` (docs: `docs/map.md`)

### Public components
- **Does:** shared pieces for public pages: the image zoom viewer (hover preview, full-screen pinch zoom), item links and the in-page lightbox (Previous and Next through the component's items, the open item kept in the address as `?hlb=<post ID>` so a reload reopens it), cache-safe "5 min ago" times, and the profile store and request helpers behind Search, Calendar and Map.
- **Use:** `ImageZoom::html( $attachment_id, $options )`; `ItemLink::attributes()` with `link_behavior` = `page`/`new_tab`/`lightbox` (REST `hexa-plugin-core/v1/lightbox/{component}/{profile}/{post}`); `RelativeTime::html( $timestamp )`; `ProfileStore`, `ProfileValues`, `PublicComponent`.
- **Code:** `src/PublicComponents/` (docs: `docs/image-zoom.md`, `docs/item-link.md`, `docs/relative-time.md`)

## Setup protocol

Every plugin that uses Core follows the same sequence; plugin-specific values live only in its `PluginContext`, never in Core.

1. Require the bundled `bootstrap.php` and register the package.
2. Wait for the shared resolver to select one package on `plugins_loaded`.
3. Build a `PluginContext`.
4. Build a `CoreBootstrap` with that context.
5. Add the modules and call `boot()` once.

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

## Package identity

These names are fixed. Do not rename them in plugin implementations.

- Repository folder: `hexa-wordpress-plugin-core`
- Composer package: `hexa/plugin-core`
- Root namespace: `Hexa\PluginCore\`, one sub-namespace per `src/` folder (`docs/folder-map.md`). Never create `HWS\BaseTools\PluginCore`, `HexaWordPressPluginCore`, `Hexa\Core` or plugin-specific namespaces here.
- Autoload path: `src/`
- Version source: `VERSION`; release notes in `CHANGELOG.md`.

Core is a library, not a WordPress plugin. Changed Core code always ships under a new `VERSION`, because sites load the newest bundled copy among all plugins.

## Development

Run the complete PHP suite with `php tests/run.php`.

Before adding code in another Codex or Claude chat, read `AGENTS.md`, `HEXA_PLUGIN_CORE_LIBRARY.md`, `docs/folder-map.md`, `docs/setup-protocol.md`, `docs/implementation-checklist.md`, `docs/integration-tests.md`, `docs/new-plugin-master-checklist.md` and the doc for the folder being changed. A feature that does not fit an existing namespace gets its namespace documented first. Every new or changed feature updates its entry in `## Features` in the same commit.
