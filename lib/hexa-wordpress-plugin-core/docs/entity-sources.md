# Entity Sources

Namespace: `Hexa\PluginCore\EntitySources`

This namespace provides an optional canonical website/entity source shared by host plugins. The primary entity may be a WordPress user or a post-backed Person, Organization, Publication, or profile. Sites without a primary entity remain valid.

## Classes

- `CanonicalEntityResolver`: resolves saved settings, extracts ACF/native fields, and finds the WordPress user bound to a post-backed entity.
- `PrimaryEntityManager`: owns site-type and entity settings plus one-time legacy migration.
- `PrimaryEntityModule`: registers migration, search, and save hooks.
- `PrimaryEntityAjaxController`: guarded AJAX saving.
- `PrimaryEntityRenderer`: shared selector, derived semantic-type display, AJAX-refreshed preview, field inventory, and consumer status UI.
- `EntityProfileCardRenderer`: complete user identity, account/contact details, labeled social-link rows with full clickable URLs, profile photos, gallery, and biography UI.
- `EntityFieldInventoryRenderer`: independently placeable WordPress/ACF field inventory with protected-value masking, field counts, responsive tables, and collapsed group cards.
- `EntityFieldInspector`: groups native and ACF values while masking credential-like fields.

```php
$manager = new \Hexa\PluginCore\EntitySources\PrimaryEntityManager(
    [
        'entity_option' => 'hws_primary_entity',
        'site_type_option' => 'hws_site_type',
        'site_types' => [ 'news_outlet' => 'News Outlet', 'personal_website' => 'Personal Website' ],
        'site_entity_types' => [ 'news_outlet' => 'publication', 'personal_website' => 'person' ],
        'allow_entity_type_selection' => false,
        'sources' => [
            'wordpress_user' => [ 'label' => 'WordPress Author', 'kind' => 'user' ],
        ],
        'ajax_action' => 'hws_save_primary_entity',
    ]
);
$bootstrap->add_module( new \Hexa\PluginCore\EntitySources\PrimaryEntityModule( $manager ) );
```

When `site_entity_types` contains the selected website type, the manager stores that semantic type and ignores a conflicting submitted value. This lets a Personal Website always resolve as Person, a Company Website as Organization, and a News Outlet as Publication without exposing a redundant selector. A host may still enable generic semantic selection explicitly for a workflow that truly needs it.

Host plugins consume `CanonicalEntityResolver::resolve()` and keep legacy settings as read-only migration fallbacks. Test with `php tests/entity-sources.php`, then verify optional/no-entity, direct-user, derived-type, and post-with-bound-author cases in the live UI and each consuming plugin.

Set `show_field_inventory` to `false` in `PrimaryEntityRenderer` arguments when a host places `EntityFieldInventoryRenderer` on a dedicated content-type or field-management tab. The entity selector keeps its profile summary while the same resolved entity feeds the relocated inventory.
