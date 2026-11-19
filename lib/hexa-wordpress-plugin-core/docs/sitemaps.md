# Sitemap recommendation engine

The `Sitemaps` namespace is opt-in through `SitemapModule` in a host CoreBootstrap.
Multiple hosts register it safely once. No recommendation read writes Rank Math.

## JSON endpoints (Parts 1 and 2)

All REST routes require `manage_options`. Cookie authentication requires `X-WP-Nonce` (wp_rest); WordPress application passwords also work. No public or nopriv handlers.

- GET `/wp-json/hexa-core/v1/sitemaps?site_type=publication`: no body. Returns `{site,site_type,rank_math_active,generated_at,rows:[{id,label,kind,name,url,setting,recommended,recommended_by,current,available,hidden_from_google,warnings,condition?,counts?,live_check?}]}`. The empty site_type denotes current site. Named types are passed to host recommendation filters, not treated as another site's live settings. Current Rank Math settings and counts always refer to this site.
- POST `/wp-json/hexa-core/v1/sitemaps/check`: `{id}`. Returns `{id,url,result}` with the actual server response. Host filter `hexa_plugin_core_sitemap_check` can reuse an existing checker. Results persist separately.
- POST `/wp-json/hexa-core/v1/sitemaps/change`: `{id,on:boolean}`. Returns `{ok,id,on,made_visible,message}`. Writes that registered Rank Math setting/module; turning a content-type, taxonomy or author sitemap on also sets its Rank Math Titles & Meta robots to index when its pages were hidden (noindex), because Rank Math leaves hidden pages out of the sitemap. Then invalidates Rank Math sitemap storage. Never called automatically. Errors return `{ok:false,message}`.
- POST `/wp-json/hexa-core/v1/sitemaps/conditions`: `{id,more_than:2,min_posts:1,apply:false}`. Returns `{ok,condition}`. Whole taxonomy recommendations require more than `more_than` terms. Counts include all existing terms; would_show counts those meeting min_posts. `apply:true` explicitly enables Rank Math sitemap/entry exclusions; defaults never change output. Author counts are published post authors, and min_posts counts published posts.

AJAX equivalents at admin-ajax.php: `hexa_sitemaps_lookup`, `hexa_sitemaps_check`, `hexa_sitemaps_change`, `hexa_sitemaps_conditions`. Require administrator capability and nonce action `hexa_sitemaps` in field `nonce`. AJAX returns WordPress `{success,data}`. Change uses `on=1|0`. Conditions uses `apply=1` or empty, with the same numeric fields.

WP-CLI JSON: `wp hexa sitemaps list [--site-type=publication]`, `check --id=index`, `change --id=post_type_page --on=1`, `conditions --id=taxonomy_category --more-than=2 --min-posts=3 [--apply]`. CLI uses the trusted operating-system/site account as WordPress CLI normally does.

## Host extension contract

Filter `hexa_plugin_core_sitemap_recommendations` receives the complete associative row map and site_type. Hosts change only recommendation fields for registered types, and can add complete rows for additional providers. `recommended_by` is a list of plugin names. `setting` is the exact Rank Math sitemap option; `kind=module` uses name as module slug. Unwritable external providers use an empty setting. Base Tools bridges its documented `hws_base_tools_sitemap_recommendations` filter into this shared filter.

Snapshots: option `hexa_plugin_core_sitemap_recommendations`, keyed by current/named type. Conditions and live checks use separate options. All options are non-autoloaded. Snapshots refresh per lookup, so plugin activation and new content appear immediately without changing Rank Math.

## System exclusions and address collisions

Declared `RecommendationRegistry::EXCLUDED_POST_TYPES` and `SYSTEM_PREFIXES` classify helper content. Providers extend through `hexa_plugin_core_sitemap_excluded_post_types` and `hexa_plugin_core_sitemap_system_post_type_prefixes`. Policy runs after recommendation providers, so helpers remain recommended off. Rows carry `policy_warnings`. A taxonomy sharing a content-type sitemap address gets `collision_with` (winning content row ID), recommended false and a named warning. Change-on requests are refused before any Rank Math write. Disabling remains allowed. Current settings are never changed by lookup.

Exclusion membership does not register a sitemap or inventory private WordPress internals. Inventory remains registered public content plus the explicitly listed media and Elementor types; exclusions also apply to rows supplied by providers.

## Junk sitemaps

Post types in `EXCLUDED_POST_TYPES` (or matching `SYSTEM_PREFIXES`) and taxonomies in `JUNK_TAXONOMIES` (filter `hexa_plugin_core_sitemap_junk_taxonomies`) are junk: never recommended and flagged `junk` in the lookup. `disable_junk` turns every junk sitemap off in Rank Math in one action; `promote` (id, main) moves one row back to the main list (stored in `hexa_plugin_core_sitemap_promoted`) without changing Rank Math. CLI: `wp hexa sitemaps disable-junk`, `wp hexa sitemaps promote --id=<id> --main=1`.

## Plugin-owned sitemaps

Each plugin declares the sitemaps it owns in its `src/Sitemaps/SitemapTypes.php`, calling `SitemapProviders::register($slug, $plugin_name, $settings_url, [['id'=>'post_type_profile','recommended'=>true|callable,'why'=>'...']])` (filter `hexa_plugin_core_sitemap_providers`). Lookup then gives every declared row `owner`, `owner_label`, `owner_url`, the owner's `recommended` and `why`; undeclared rows keep `owner` empty. Providers apply after the host recommendation filter and before the junk and collision policy.

Site-type rules: a declared row with `'site_types'=>['personal_website', ...]` applies only when the lookup's site type is one of them. It sets the recommendation and reason but never takes ownership, so a plugin can give a site-type rule for a row another plugin or Core owns (for example `authors`). Site-type rules run after every owner's rule. A callable recommendation receives `($row, $site_type)`. With no `--site-type`, the lookup uses filter `hexa_plugin_core_site_type`, which the plugin that records the site's type supplies.

`SitemapsPanel::render($slug)` is the plugin's own Sitemaps section: its declared rows only, with recommendation, Rank Math state, a new-tab address and a turn on/off button through the shared `hexa_sitemaps_*` AJAX actions. Other screens (HWS Base Tools) show owned rows read-only and link to `owner_url`.
