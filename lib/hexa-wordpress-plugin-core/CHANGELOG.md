# Hexa WordPress Plugin Core changelog

Release notes moved from the README (3.31.1). Newest first; each Core release adds its line here.

Version 3.34.2 makes the Core fleet sync check the site after copying: it loads the home page once and restores every copied Core package when the site returns a server error or WordPress's critical-error page (`hexa_core_sync_site_broken`). An older host plugin that still used code a newer Core removed (Base Tools 13.13.2 with Core 3.34.1, missing `DirectorySearch`) took americasgoneviral.com down with HTTP 500 on 2026-10-23.

Version 3.34.1 draws `ScopedCssOverride`'s HTML structure and CSS examples and `SchemaScanRenderer`'s "View full JSON" in `CodeDisplay`.

Version 3.34.0 shows the schema graph JSON (`SchemaTools\SchemaDashboardRenderer`) and each snippet's readme (`SnippetRegistry\SnippetsTableRenderer`) in `CodeDisplay` instead of plain grey blocks.

Version 3.33.0 makes field reads safe during plugin startup: before ACF has started (`acf/init`), `Field::get()` returns the stored value instead of calling ACF, which started ACF too early and crashed the admin (`Fields\Acf::ready()`). Without ACF, the native field script and media library load only on post, user and term edit screens instead of every admin page (`AdminScreens::field_screen()`). Core's own tab and the field-structure cards show their code samples in `CodeDisplay`.

Version 3.32.0 adds `ShortcodeRegistry\ShortcodeBreakdown` (one shortcode with copy button, aliases, every attribute with default, explanation and choices, and examples) and `WpAdminComponents\CodeDisplay` (colour-coded HTML/CSS/PHP/JS code block with copy button), for every plugin's settings screens.

Version 3.31.1 rewrites the README's `## Features` into one entry per capability (Does / Use / Extend / Code) for every `src/` folder, removes the narrative sections those entries replace, and moves the release notes here. No code changes.

Version 3.31.0 adds `WpAdminComponents\SourceBanner` (read-only "Original content at <owner>. Click here to edit." notice for data owned by another plugin) and makes the native field engine (no ACF) honor a field's `wrapper` `class` and `id` and add `data-name`, as ACF does, so hosts can show or hide fields by class.

Version 3.30.4 (loops showing the page's own results are found without a marker class), 3.30.3 (filter controls styled in every look) and 3.30.2 replace the four separate searches (`SearchDisplay`, `SearchQuery\ElementorSearchAdapter`, `DirectorySearch`, `ListingSearch`) with one: `Hexa\PluginCore\Search`. One profile shape covers posts and role-scoped users; one box either narrows the page's own list (archives, the search page, any Elementor loop marked `hexa-listing-results`, with `post_type`/`category`/`tag`/`author` scope for ordinary pages) or renders its own cards with sorts and Load more; one script refreshes every box in place with one loader (the results dim and a spinner shows); `SearchBox::header()` draws the header search box (icon-reveal or field, never a popup). Address parameters are `hs`, `hq`, `hf`, `hp`, `ho`; filters `hexa_plugin_core_search_target`, `hexa_plugin_core_search_context_args`, `hexa_plugin_core_search_post_types`; shortcode `[hexa_search id]`; the directory REST endpoint is removed.

Version 3.29.8 adds `ListingSearchRenderer::preview( $style )`, a still picture of each look for settings screens. Version 3.29.7 draws the ticked-filter count as an outlined number in the text colour, visible on any background. Version 3.29.6 scopes the box styles so theme field styles cannot reshape it (restyle through `--hls-*`). Version 3.29.5 gives the listing search box three looks (`none`, `default-1` compact, `default-2` editorial), every rule at zero specificity so site CSS wins. Version 3.29.4 opens a filter list leftward when it would run off the right edge of the screen. Version 3.29.3 prints the listing search box's style and script with every box, so a page whose content another plugin renders first in the background keeps a working box. Version 3.29 adds `Hexa\PluginCore\ListingSearch` (search and filters inside any WordPress listing, refreshed in place), moves `DirectorySearch` onto `WP_Query`/`WP_User_Query`, gives `QueryFilter` several-choice checkboxes with any/all matching, number ranges, nested term lists, per-option counts and the `author` and `post_type` types, and removes the JetEngine search adapter.

Version 3.28.1 also removes staff plugins' Profile contact fields and footer scripts for clients (Rank Math's Twitter, Facebook and extra-link fields and its "Rank Math SEO" heading).

Version 3.28.0 keeps staff business away from clients (users below administrator) at the source. `WpAdminUiCleanup\ClientFacingPlugins` names the plugins that may speak to clients (`hexa-*`, `hws-*`, ACF, WP User Avatars; filter `hexa_core_client_facing_plugins`) and `strip( $hook )` removes every other plugin's callbacks from a hook before it runs. `CleanupPresets::client_notices()` strips the admin-notice hooks, so Rank Math notices and donation banners never reach clients. `CleanupPresets::client_profile()` strips staff plugins' Profile sections (Rank Math SEO, Elementor AI), turns Application Passwords off through `wp_is_application_passwords_available_for_user`, removes the color picker through its own hook and hides the remaining hook-less Personal Options rows by WordPress's row classes. `Fields\Audience` adds `'hide' => true` to `client_view`: a staff-only group is not registered for client-view users; the registry card offers "Hide the whole group from them".

Version 3.27.3 makes the hover preview hint line easier to read: brighter text at 12.5px and a soft glow on its accent dot.

Version 3.27.2 keeps the hover preview open while the pointer is on it: it closes only when the pointer leaves the preview (or the thumbnail without entering it) or on a click outside. Clicking the preview image opens the full-screen viewer and the detail links are real links (outside links open in a new tab).

Version 3.27.1 smooths the ImageZoom hover preview: it fades and settles in over about half a second once the image is decoded, with rounder corners (16px) and a softer hint line that follows them.

Version 3.27.0 adds a client-friendly field view (`Fields\Audience`). Any field can carry `developer_instructions` (shortcodes, meta keys, compatibility notes) beside its friendly `instructions`. A group's `client_view` (`['enabled' => true, 'roles' => 'below_admin']` by default, or a role list) decides who sees only the friendly help; everyone else sees both. `AcfFieldGroupRegistry` cards add a Client-friendly view switch with "Everyone below administrator" or chosen roles, saved per group. Filter: `hexa_core_fields_client_view`.

Version 3.26.0 gives `ImageZoom` optional `delay` (ms, up to 5000) with a countdown ring on the thumbnail, a one-line `hint` above the preview (a default explainer when a delay is set; empty string hides it) and a `detail` side panel (title, label/value facts, short text, link lines). Without options the markup is unchanged.

Version 3.25.0 gives `TaskRunner` a full dashboard look: `header()`, `segments()` (remembered panel switcher), KPI tiles with tones, toolbars with search and filter chips, a bulk-selection bar, soft badges, expandable lists, a slim progress bar with Stop and a light Activity log. `queue()` accepts `concurrency` for read-only steps. Root class: `hpc-runner-app`.

Version 3.24.1 adds `WpAdminComponents\TaskRunner`, the shared live-progress pieces for admin jobs that run as AJAX steps: stat cards, progress meter, status badges, job tables, a live log and the `HexaWpCoreTaskRunner` client (`post`, one-at-a-time `queue`, batched `loop`, stop between steps). Docs: `docs/task-runner.md`. `DatabaseCleanup` (the WP-Optimize wrapper) is deprecated: HWS Base Tools now owns database cleanup itself, and the module will be removed once every site runs a Base Tools release that no longer calls it.

Version 3.22.0 makes Core the one schema library: `SchemaNodes` builders, `SchemaSources` WordPress adapters, `SchemaIds`, the shared per-page graph `SchemaPage` (one `hexa-schema` block merged from every plugin) and `RankMathSchemaControl`. Plugins decide where they output; Core builds, merges and prints.

Version 3.21.1 fixes a search hang from 3.21.0: the noindex rule was a `meta_query` that WordPress compiles into unkeyed postmeta joins, and Elementor searches applied it twice, so one query multiplied every post by its meta rows and ran for minutes. `restrict_to_public()` now only marks the query (`hexa_search_public_only`), and the search SQL excludes noindex with one indexed postmeta subquery (`SearchQueryEngine::noindex_exclusion_sql()`). Calling it twice changes nothing.

Version 3.21.0 makes site search public-only for everyone: `SearchQueryEngine::restrict_to_public()` limits every prepared search (the regular search page and the Elementor Search widget) to published, non-password items, drops anything marked noindex (Rank Math `rank_math_robots`, Yoast `_yoast_wpseo_meta-robots-noindex`) and never returns the site's front or posts page. Before, the regular search page used WordPress's default, which shows private posts to signed-in editors.

Version 3.20.0 adds the Elementor search results page mode (`hexa-search-page-results` and `hexa-search-default` CSS classes): results in the page, the spinner over the current results while loading, default content hidden while results show.

Version 3.19.1 makes short words such as "5G" or the "1" in "GLP-1" fast with the search index: they are matched on the indexed text, narrowed by the query's longer words.

Version 3.19.0 adds `SearchQuery\SearchIndex`, an optional MySQL full-text index the search engine uses with `'index' => true`, so live search on sites with tens of thousands of posts answers in milliseconds instead of seconds.

Version 3.18.3 keeps theme and page-builder image rules (such as `height: auto`) from shrinking the ImageZoom thumbnail inside its box.

Version 3.18.2 adds the ImageZoom `fit => contain` option (the whole image over a blurred fill of itself, nothing cropped) and keeps an explicit `sizes` list from being overridden by lazy `auto` sizing, so tall thumbnails stay sharp.

Version 3.18.1 keeps page-builder lightboxes (Elementor) from opening on top of the ImageZoom viewer.

Version 3.18.0 adds `PublicComponents\ImageZoom`, a reusable flyer/poster viewer: `ImageZoom::html( $attachment_id )` prints the thumbnail; hovering shows the original image large in a centered preview that fades out when the pointer leaves, and a click or tap opens a full-screen viewer with pinch, wheel and double-tap zoom, finger panning, and swipe-down, Escape or backdrop to close. Docs: `docs/image-zoom.md`.

Version 3.17.0 adds `SearchQuery\ResultTypeLabels`, host-declared labels that flag each search result's kind (for example Press Release, External PR or Site Content), the matching **Result Type** Elementor dynamic tag for search-result Loop Items, and `SearchQueryConfiguration::searchable_post_types()` / `searchable_taxonomies()` for full-site search scopes.

Version 3.16.0 lets any cleanup option apply only to non-administrators (`audience` => `non_admins`, with `audience_capability`), removes list-table columns (`columns` + `column_hooks`) and admin-bar nodes (`admin_bar_nodes`), adds the `rankmath_lock_modified_date()` and `litespeed_ui()` presets, and adds `Taxonomies\TermChoiceLimits`, which limits how many terms users without a capability may pick (radio buttons when the limit is one), enforced again on save.

Version 3.15.1 adds the lightbox `media` layout: a large dialog with the original-quality photo filling the left and the details scrolling on the right (stacked on phones), through `ItemLightbox::media()` and `'layout' => 'media'`.

Version 3.15.0 adds the `meta_box_remove` cleanup mode, which removes editor meta boxes through WordPress's `remove_meta_box()` after every plugin has added its boxes (optionally per post type), an `auto_enabled` condition that turns an option on by itself with a shown reason, and `CleanupPresets` with the shared Comments box (on automatically while comments are closed) and FIFU box options, so host plugins stop re-implementing them.

Version 3.14.4 synchronizes the registered native Elementor Search input's collapsed state when an outside click closes its result list, while preserving inside clicks and unregistered widgets. It includes the 3.14.3 native reopen lifecycle bridge.

Version 3.14.3 synchronizes the native Elementor Search input's expanded state when Elementor reopens a visible result list after Escape. The lifecycle bridge applies only to registered widgets and preserves native rendering, keyboard behavior, and the existing natural search and map features.

Version 3.14.2 moves the map's zoom controls beside the open selection panel on desktop so the panel never covers them.

Version 3.14.1 adds bounded natural search time windows to the shared QueryFilter/SearchQuery contract, including duration-only and keyword-plus-window searches while preserving native WordPress and Elementor query handling.

Version 3.14.0 redesigns the map selection panel as a sectioned overlay that slides in over the map (a bottom sheet on narrow screens) instead of resizing it, glides the camera so the selected pin stays clear, and organizes entries as compact rows with date badges, meta lines, tags and thumbnails, a featured card for a single entry, a loading skeleton, and a "Show more" footer that appends pages in one scrolling list.

Version 3.13.4 keeps native Elementor Search keyboard and icon behavior intact when results are nested inside another keyboard component or intentionally rendered in normal document flow.

Version 3.13.3 clears a registered Elementor Search widget's old result markup as soon as its current input falls below the widget's native minimum character setting, while preserving native rendering at and above that threshold.

Version 3.13.2 keeps host-owned upcoming-event eligibility in the generic Core search contract through bounded correlated meta constraints, avoiding row-multiplying `WP_Meta_Query` joins while preserving native Elementor listing grids and ordering.

Version 3.13.1 keeps live-search status announcements out of Elementor result grids by hiding non-error status content accessibly, and replaces stale result cards with one full-width visible error state when a request fails.

Version 3.13.0 adds the generic `SearchQuery\\ElementorSearchAdapter` for trusted native Elementor Search widgets, including bounded public matching, exact Query ID provenance, live REST-safe query preparation, cancellation and stale-response protection, accessible request states, and preserved Loop Item listing grids. `ElementorPublicTextIndex` adds a separate bounded index of anonymously rendered public Elementor text, including reusable templates, without searching raw document data or rewriting `post_content`.

Version 3.12.0 adds an opt-in Map selection sidebar with lazy, paginated detail REST responses, rich image/title/fact/action entries, responsive placement, request cancellation, focus management, and related-content cache invalidation.

Version 3.11.0 adds stable per-day multi-criterion calendar sorting with field paths, value callbacks, type-aware comparison, and explicit missing-value placement. Hosts can sort by any item data, including nested `data.*` values, while preserving stable ties.

Version 3.10.0 removes `Hexa\PluginCore\DraftPreview`. Public draft links are an HWS Base Tools feature, not a shared Core module.

Version 3.8.1 closes the item lightbox cleanly on a quick close and reopen: unlocking page scroll, clearing the dialog, and returning focus now happen as the dialog closes instead of in the later close event.

Version 3.8.0 adds one `link_behavior` setting to the `Calendar` and `Map` components: `page` (follow the link, the default), `new_tab`, or `lightbox`, which shows the linked post in an in-page dialog (`ItemLink`, `ItemLightbox`, `GET /wp-json/hexa-plugin-core/v1/lightbox/{component}/{profile}/{post}`), so visitors can click through a calendar or a map without leaving the page. Docs: `docs/item-link.md`.

Version 3.7.3 serializes `wp-config.php` INI and constant values with PHP literals, preventing quote-bearing input from escaping the generated assignment while preserving numeric and boolean scalar behavior.

Version 3.7.2 keeps every `Map` item card fully inside the map, including on phones: the card always opens above its pin, its width and height are capped to the map (taller content scrolls inside the card), and the map glides just enough to fit the whole card. Before, a card opened near the bottom of a small map could run off the map.

Version 3.7.1 sets the Map asset budget to 6.5 KB gzipped (the 3.7.0 selection code added about 0.8 KB), so the Core suite passes again.

Version 3.7.0 makes choosing a location on a `Map` smooth: each pin has an invisible 22px target and clicks pick the nearest pin, hovering enlarges the pin with a ring and shows the location's name, the chosen pin stays highlighted, clicking another pin swaps the card in place, clicking empty map closes it, and the map glides so the card opens fully in view. Cached map payloads are keyed by a payload version so shape changes never serve stale data.

Version 3.6.2 hardens the Map filter chips against theme button styles (hover, focus and active states no longer inherit a theme's button colors, such as Hello Elementor's pink) and shows chip counts as aligned tabular-number badges.

Version 3.6.1 adds `Hexa\PluginCore\Users\UserProfileBridge`: `GET/POST hexa-plugin-core/v1/users/{id}/profile` reads one user with safe meta and the avatar provider, and writes native profile fields, meta, fields and the avatar for a user with `list_users` and `edit_user`. It never changes role, login, password or session data, and serves a local avatar when no avatar plugin is active. Host plugins switch it on with `UserProfileBridge::register()`, so Application Password and signed HWS Base Tools connections can manage author profiles.

Version 3.6.0 adds a date filter to `Hexa\PluginCore\Map`: profiles that supply `next` (each item's next dated start) get "Any time · 24 hours · 48 hours · 1 week · 2 weeks" chips with live counts, applied in the browser against the visitor's clock and combined with the group filter. Windows are configurable. Docs: `docs/map.md`.

Version 3.5.1 adds `Hexa\PluginCore\PublicComponents\RelativeTime`, a cache-safe "5 min ago / 2 days ago" `<time>` element whose age is recomputed in the browser, for "last updated" lines on public pages. Docs: `docs/relative-time.md`.

Version 3.5.0 adds `Hexa\PluginCore\Map`, a brandable public location map (`[hexa_map id="…"]`). Hosts register a profile of posts or users with a street address field; Core geocodes new and changed addresses in the background with keyless services (US Census, OpenStreetMap Nominatim), stores the coordinates on each item, and renders a lazily loaded MapLibre map on free OpenFreeMap tiles with clustered pins, highlighted pins, a group filter, item cards, and a plain link list. Every color, including the base map, comes from `--hmap-*` CSS tokens. Full protocol: `docs/map.md`.

Version 3.4.16 refreshes WordPress's URL rules once whenever a `ContentTypeRegistry`'s post types, their URL bases or the permalink structure change, including right after the host plugin is activated through REST, WP-CLI or an updater. Before, new post type URLs returned 404 until Settings > Permalinks was re-saved.

Version 3.4.15 makes `PluginBridge` choose the release zip for the running PHP: the `-php74.zip` build below PHP 8.2, the normal zip otherwise, installed into the same plugin folder.

Version 3.4.14 lets Hexa plugins run on PHP 7.4 without giving up modern source. `bin/build-php74-release.sh <plugin-git-dir> <ref> <folder> <out.zip>` rewrites a plugin and its bundled Core to PHP 7.4 syntax with Rector (`bin/rector-php74.php`), lints every file on PHP 7.4 and zips it; attach it to the release as `<folder>-<version>-php74.zip`. The GitHub updater reads the branch source's `Requires PHP`: sites on older PHP get that PHP 7.4 build, or, when a release has none, an offer that states the real requirement so WordPress refuses it instead of installing code the site cannot parse. Sites on current PHP keep the branch source.

Version 3.4.13 makes `PluginBridge` load on PHP 8.1 (its install step returned a PHP 8.2-only `true` type).

Version 3.4.12 adds `PluginBridge`: once any Hexa plugin that bundles Core is on a site, an administrator Application Password can install or update other allowed plugins from their GitHub release zips over REST (`hexa-plugin-core/v1/plugins/github`), without wp-admin.

Version 3.4.11 makes the migration tool emit `hexa_fields_on()` for every ACF hook registration, since a registration inside a function can still run while plugins load, before Core's classes are autoloadable. Runtime code is unchanged from 3.4.10.

Version 3.4.10 fires ACF's `include_fields` registration moment natively (just before `init`, as ACF does), so groups registered with `Hooks::on( 'include_fields', ... )` or `hexa_fields_on()` exist without ACF.

Version 3.4.9 resolves the published fields of trashed ACF admin-screen groups by key or name, as `acf_get_field()` does, while never listing or rendering those groups.

Version 3.4.8 passes every native location-rule result through ACF's location filters (`location/match_rule/type=<param>`, `location/match_rule`, `location/rule_match/<param>`, `location/rule_match`), as `acf_match_location_rule()` does, so host visibility filters work without ACF.

Version 3.4.7 adds `hexa_fields_on()` to `bootstrap.php` for field hooks registered while plugins load (before Core's classes can be autoloaded; the migration tool emits it for file-level hooks), and applies the `load_field_groups` filter to native group lists as ACF does.

Version 3.4.6 reads field groups created in the ACF admin screen (`acf-field-group`/`acf-field` posts) when ACF is not active, exactly as ACF does: their fields resolve and format, active ones render on their screens, and code-registered groups with the same key win. A missing value now takes the field's or its type's `default_value`, as `acf_get_value()` does. `Field::all()` follows `get_field_objects()` exactly (stored-value order; a value is listed when its reference resolves to a field of the same name), page-link and post fields resolve only posts `acf_get_posts()` returns, and WYSIWYG output runs ACF's `acf_the_content` filter chain.

Version 3.4.5 adds `Form::field()`, the equivalent of `acf_render_field_wrap()`: one field outside a form, posted under `acf[<key>]` in both modes, and maps it in `bin/migrate-to-fields.php`.

Version 3.4.4 makes native Fields read exactly as ACF reads: a name resolves through its stored field-key reference (a never-saved name returns the raw value or null, as `get_field()` does; only `update()` also matches registered names), and option references use ACF's `_options_<name>` storage name.

Version 3.4.3 derives a missing field-group key from its title and a missing field key from its name, exactly as ACF does, and ships `tests/support/fields.php` so host-plugin unit tests can run Fields against their own ACF stubs.

Version 3.4.2 makes native `Field::objects()`/`all()` list exactly what ACF lists: every top-level field with a stored `_name` reference on the object (verified field-for-field against ACF on hexaprwire.com's real releases and outlet records).

Version 3.4.1 lets host-defined location rules (`Hooks::on( 'location/rule_match/<param>', ... )`) decide where native field groups appear, as ACF does.

Version 3.4.0 adds `Hexa\PluginCore\Fields`, the one custom-field API for every Hexa plugin: field-group registration, `Field::get()`/`update()` and row loops, options pages, forms and ACF lifecycle hooks, all with ACF-identical signatures. With ACF active every call delegates to ACF; without it Core stores, formats and edits the same data natively in ACF's storage layout, so ACF Pro is no longer a requirement. Content types, field-structure registries, the settings panel, entity sources, FAQ sets, the gallery module and query filters now use it. See `docs/fields.md`.

Version 3.3.0 makes content-type field groups work without ACF. `ContentTypes\NativeFieldGroups` registers each enabled group as a native meta box and post meta when ACF is not active, storing values under the same meta keys ACF uses so existing data remains readable and carries over if ACF is activated later. The Content Model screen shows the active field storage.

Version 3.2.2 gives calendar overflow disclosures distinct collapsed and expanded labels, so the control changes from the event count to `Show less` while open and supports host-defined `labels.less` text.

Version 3.2.1 renames the DirectorySearch URL-owner parameter from `dir` to `hds` (the old name is still read), because common web firewalls such as ModSecurity/Imunify360 reject any request carrying `dir=`, which broke live search and pagination.

Version 3.2.0 adds `Hexa\PluginCore\Calendar`, a lightweight library-free month-grid calendar (`[hexa_calendar]`, `GET /wp-json/hexa-plugin-core/v1/calendar/{profile}`) whose items link to profile-defined URLs, and `Hexa\PluginCore\QueryFilter`, the one shared visitor-filter structure (taxonomy, custom field/ACF, date range, callback, and host-registered types) now used by both `Calendar` and `DirectorySearch`. Shared public-component helpers move into `Hexa\PluginCore\PublicComponents`; the `DirectorySearch` public API is unchanged (the former filter constants and `DirectorySearchRequest::filter_options()` remain as deprecated aliases) and gains date-range filters.

Version 3.1.0 adds `Hexa\PluginCore\DirectorySearch`, a declarative public directory search over published posts or role-scoped users (all/any/exact terms, whole/prefix/contains matching, `*` wildcards, meta/taxonomy/callback filters, field/callback sorts, card templates, `[hexa_directory]`, and `GET /wp-json/hexa-plugin-core/v1/directory/{profile}`), and moves term matching into the shared `SearchQuery\SearchMatchSql` helper used by the native results engine.

Version 3.0.7 adds the shared dynamic admin-notice component for consistent no-refresh save feedback and makes generic plugin deactivation preserve site or network scope with an explicit network-capability guard.

Version 3.0.6 preserves each plugin's inactive, site-active, or network-active state across native updates, normalizes the installed package into its canonical folder, clears discovery caches before restoration, and verifies the restored activation scope with explicit errors when installation or reactivation fails.

Version 3.0.5 makes external ACF sibling cards visually secondary to their CPT with quieter surfaces, smaller titles, compact switches and chevrons, and softer relationship rails. Imported field rows show label, name, and type with a per-field JSON disclosure sourced from the actual ACF definition, while expanded section and action spacing improves scanability.

Version 3.0.4 puts functional enable switches directly in CPT and ACF card headers, removes redundant status badges and key chips, and renders each CPT's ACF cards as immediate siblings outside the CPT accordion.

Version 3.0.3 organizes content-type management as collapsed CPT parent cards. Each parent contains its own CPT configuration followed by a separate collapsed child section for every attached ACF field group, including group metadata and a compact field inventory.

Version 3.0.2 replaces the content-type dashboard grid with a single-column settings flow that keeps editable labels and URL controls visible and moves technical metadata into a secondary disclosure.

Version 3.0.1 preserves explicit `@id` and `@type` relationship summaries when standalone schema nodes are normalized.

Version 3.0.0 establishes the coordinated major release for the expanded Core data-normalization, operations, provisioning, checklist-state, fleet-synchronization, and reusable admin infrastructure shipped in this source tree.

Version 2.1.4 keeps Getting Started parent-step and full-checklist runs available when only child tasks are awaiting input. Runnable subtasks execute in their registered order while each input-dependent child validates itself, so an early dependency-provisioning task can install required packages before later actions run. Template selection continues to distinguish selected from loaded states and expose loading, success, and failure feedback. `TemplateSelectionControl` retains its no-design toggle, responsive column constraints, accessible visual grid, scaled preview viewports, selected-state behavior, host save hooks, and AJAX-tab reinitialization.

Version 1.2.0 adds an automatically registered static-front-page query invariant plus reusable main-or-explicit query eligibility predicates. Hexa query callbacks can reject the exact configured front-page main query, suppressed filters, background requests, and unmarked secondary loops before loading settings or attaching SQL filters, while Core repairs later post-type or page-ID mutations as defense in depth. The native search engine now uses one idempotent weak-state SQL dispatcher so duplicate preparation cannot stack closures or retain abandoned query objects.

Version 1.1.9 adds a reusable ACF gallery-details module with live native-gallery synchronization, context-aware AJAX removal, larger previews, and separate image-data and URL clipboard actions.

Version 1.1.8 makes media URL copying fall back to the synchronous browser copy path when an exposed Clipboard API rejects the write.

Version 1.1.7 adds `MediaGalleryDetailsRenderer`, a host-neutral collapsed image-details panel with selectable attachments, full and generated-size URLs, new-tab links, and shared dynamic clipboard buttons.

Version 1.1.6 adds reusable standalone schema-node normalization so host plugins can keep every top-level JSON-LD entity independently detectable while preserving typed author, publisher, copyright-holder, and image summaries.

Version 1.1.5 adds fault-tolerant schema URL normalization and reports invalid schema URLs without breaking page output or scans.

Version 1.1.4 adds an opt-in unconfigured website-type state, an intentional empty primary-entity presentation, and reliable hidden-state styling for empty Smart Search selections.

Version 1.1.3 separates the complete WordPress/ACF entity field inventory into a reusable renderer so host plugins can place it in their field-management area without duplicating it inside the primary-entity selector.

Version 1.1.2 renders canonical-entity social links as labeled rows with complete clickable URLs and makes disabled Core-managed ACF groups suppress active database copies that share the same group key.

Version 1.0.0 establishes the shared Core contracts as the stable major baseline for Hexa WordPress plugins. It consolidates reusable content-type and ACF registration, optional canonical entity sources, schema document handling, admin UI, AJAX, updater, search, cleanup, and frontend display infrastructure behind the `Hexa\PluginCore` namespace.

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

Version 0.19.66 stores multiple open-card keys in one comma-delimited `hpc_open` value so WordPress admin canonicalization cannot discard all but the last key during refresh. The reader remains compatible with repeated legacy parameters.

Version 0.19.65 prevents parser- and AJAX-insertion `toggle` events from overwriting query or local-storage state before a collapsible has completed Core initialization. This keeps explicit closed state authoritative for default-open cards.

Version 0.19.64 makes every titled `CoreUi::collapsible()` section refresh-safe and linkable through the `hpc_open` query parameter. Query state is restored after full-page and AJAX tab loads, explicit query keys and opt-out are supported, and existing local-storage persistence remains available as a fallback.

Version 0.19.63 lets CoreUi::toggle() accept sanitized host input classes and data attributes, allowing AJAX-saving host plugins to use the shared toggle renderer without rebuilding its markup.

Version 0.19.62 gives collection-filter search inputs enough selector specificity and left padding to keep the shared search icon separate from placeholder and entered text under WordPress admin styles.

Version 0.19.61 adds reusable inherited-value support to ColorControl. Hosts can persist an empty override while Core displays the inherited color and keeps picker, editable hex, RGB, swatch, copy, import, and inherit actions synchronized.

Version 0.19.60 adds a guarded JetEngine listing-grid adapter to `Hexa\PluginCore\SearchQuery`. Version 0.19.59 introduced the reusable native WordPress search-results engine, separating all/any/exact term logic from whole/prefix/contains word matching, supporting selected public post types and explicit native or advanced sources, and keeping display options outside the behavior contract.

Version 0.19.57 gives filtered items an explicit Core hide state so host row layouts cannot override `hidden` and leave nonmatches visible. Core collapsible titles also wrap instead of truncating on narrow screens.

Version 0.19.56 lets reusable Getting Started checklists opt into Core collection search. Nested and standalone actions are filterable, parent context is indexed with each child, counts update immediately, and active searches are reapplied after template row replacement.

Version 0.19.56 adds the opt-in `show_search`, `search_label`, `search_placeholder`, and `search_empty_message` checklist configuration. Core owns the search UI and nested filtering behavior; host plugins only enable and label it.

Version 0.19.55 keeps the complete grouped sidebar in normal document flow. The rail no longer uses sticky positioning, so long navigation remains reachable by normal page scrolling without pinning part of the sidebar to the viewport.

Version 0.19.54 adds an optional host-selected text selector so shared logs and diagnostics do not create false search matches.

Version 0.19.53 also initializes collection filters after DOMContentLoaded so first-render panels work before any AJAX navigation.

Version 0.19.52 adds CoreUi::collection_filter() for searchable admin-card collections. Hosts supply the target ID, item selector, optional group selector, labels, and empty-state text. Core owns case-insensitive matching, visible/total counts, group hiding, clear and Escape behavior, and reinitialization after hexa-core-host-tab-loaded.

Version 0.19.48 updates `Hexa\PluginCore\WpAdminComponents\ScopedCssOverride`. Host plugins provide a scope selector, short instructions, a formatted HTML structure example, and a formatted CSS example. Core renders a copyable `CoreUi::detail_card()` that is closed by default and prevents each host plugin from rebuilding this reference UI.

Version 0.19.46 supports compact inventories without a separate Source column. Core places source beneath the plugin path, uses a fixed seven-column desktop layout without horizontal scrolling, and stacks labeled cells below 900px.

Version 0.19.45 keeps plugin inventory and plugin-check AJAX fragments free of duplicate Core/DynamicButton asset tags, so refreshed row markup contains only fragment content.

Version 0.19.44 makes plugin inventory policy explicit: satisfied required plugins render green, only host-configured entries are forbidden, absent forbidden entries can remain visible as compliant, and installed plugins outside registered policy remain neutral.

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

Version 0.19.22 keeps cleanup renderers compatible with sites where another plugin has already loaded an older `Hexa\PluginCore\WpAdminComponents\CoreUi` class before the current plugin renders.

Version 0.19.21 keeps cleanup detection criteria backend-only in the operator UI and removes fixed minimum table widths so cleanup reports stay contained inside their collapsible cards.

Version 0.19.20 changes Getting Started Checklist parent rows into visible collapsible sections and keeps the technical activity log collapsed by default so checklist pages do not read like a debug log dump.

Version 0.19.17 makes Article/Media Cleanup batch deletion the primary visible workflow. The two main actions are delete all matching posts or delete matching posts except the latest X, each with its own associated-media toggle. Filters, preview, selected-row deletion, and row deletion remain available in a collapsed Advanced Filters & Preview card.

Version 0.19.16 changes cleanup services to manual scan by default. Content Cleanup, Backup Cleanup, and Article/Media Cleanup render immediately with a clear "Press Scan" empty state and only start AJAX work when the user clicks a scan button. Host plugins can opt back into load-time scanning with `auto_scan => true`.

Version 0.19.15 adds reusable article/media batch deletion for "delete all matching posts" and "delete all matching except the latest X posts." Batch deletion ignores the preview limit, runs through repeated AJAX requests, logs every batch, and can delete associated featured/inline/gallery media when the visible media cleanup toggle is enabled.

Version 0.19.14 adds a subtle Core detail-card variant and uses it for cleanup descriptions, detection rules, and scan-location details so secondary context stays collapsed and visually quiet. Backup scans now show an active loading row and log the file patterns searched, folders inspected, directory entries looked at, matched files, and no-result state.

Version 0.19.13 adds reusable collapsed detail subcards and uses them in cleanup services to show human-readable detection rules, descriptions, and every configured backup scan location with resolved directory status.

Version 0.19.12 keeps Core toggle inputs clipped to a 1px focusable control so hidden checkbox fields cannot create horizontal page overflow.

Version 0.19.4 adds:
