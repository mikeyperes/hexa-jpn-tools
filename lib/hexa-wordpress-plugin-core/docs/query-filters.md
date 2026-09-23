# Query Filters

## Namespace And Folder

```text
src/QueryFilter/
Hexa\PluginCore\QueryFilter
```

## Purpose

`QueryFilter` is the one declarative way to let visitors narrow a public
posts or users query. A component profile (`DirectorySearch`, `Calendar`, or a
future component) declares `filters`; Core normalizes the definitions, parses
untrusted visitor values, builds bounded SQL, renders the controls, and builds
URL arguments. Components own only their parameter name and CSS class prefix.

## Declaring Filters

```php
'filters' => [
    // Taxonomy term (posts). 'terms' lists the taxonomy's non-empty terms.
    'area'  => [ 'type' => 'taxonomy', 'taxonomy' => 'area', 'label' => 'Area', 'all_label' => 'All areas', 'options' => 'terms' ],

    // Date range on a custom field or core column.
    'dates' => [ 'type' => 'date_range', 'meta_key' => 'start_date_timestamp', 'end_meta_key' => 'end_date_timestamp', 'format' => 'timestamp', 'label' => 'Dates' ],

    // Any custom field (ACF included): a toggle for true/false fields…
    'kids'  => [ 'type' => 'meta', 'meta_key' => 'kids_event', 'control' => 'toggle', 'label' => 'Kids events' ],
    // …or a select, with options from the ACF field, stored values, or the host.
    'cost'  => [ 'type' => 'meta', 'meta_key' => 'cost', 'options' => 'acf' ],
    'tags'  => [ 'type' => 'meta', 'meta_key' => 'tags', 'compare' => 'serialized', 'options' => fn() => my_tag_options() ],

    // Business rules Core cannot know: return allowed IDs, null (no limit), or [] (none).
    'open'  => [ 'type' => 'callback', 'control' => 'toggle', 'label' => 'Open now', 'apply' => fn( $value, array $profile ): ?array => my_open_ids() ],
],
```

Common keys: `type`, `control`, `label`, `all_label` (selects), `from_label`
and `to_label` (date ranges), `min` and `max` (Y-m-d date limits), `options`.
At most 12 filters per profile; incomplete or unknown definitions are dropped.

## Built-In Types

| Type | Sources | Controls | Keys |
| --- | --- | --- | --- |
| `meta` | posts, users | `select`, `toggle` | `meta_key`; `compare` `=` (default) or `serialized` for ACF arrays; toggle `value` (default `1`). Options keywords: `acf` (field choices), `distinct` (values stored on published posts of the component's types, max 100). |
| `taxonomy` | posts | `select` | `taxonomy`; `term_field` `slug` (default) or `term_id`; in a hierarchical taxonomy a term also matches its descendants unless `include_children` is `false`. Options keyword: `terms`. |
| `date_range` | posts, users | `date_range` | `meta_key` with `format` `timestamp` (Unix seconds), `datetime` ('Y-m-d H:i:s', ACF date-time picker), or `date` ('Ymd', ACF date picker); or `column` posts `date`/`modified`, users `registered`. Optional `end_meta_key` (same format) matches items that overlap the range instead of only those starting in it. `timezone` (IANA; default the component's, else the site's). Either side may be empty. |
| `callback` | posts, users | all | `apply( $value, $profile )` returns IDs, `null`, or `[]`. |

`options` may be a static `value => label` array, any callable (closure,
`[ $object, 'method' ]`, `'function_name'`, or `'Class::method'`), or a keyword
the type declares in `keywords()` (`terms`, `acf`, `distinct`). Host lists are
kept whole; keyword lists are bounded (200). Resolved options are memoized per
request.

## Visitor Values

| Control | Parameter | Parsed value |
| --- | --- | --- |
| `select` | `{param}[key]=value` | A declared option key, else ignored. |
| `toggle` | `{param}[key]=1` | `'1'` for `1`/`yes`/`on`/`true`. |
| `date_range` | `{param}[key][from]=Y-m-d&{param}[key][to]=Y-m-d` | `['from' => …, 'to' => …]`; impossible dates dropped, reversed ranges swapped, clamped to `min`/`max`. |

A date range covers whole days in the filter's timezone: from the start of
`from` to the start of the day after `to`.

## Adding A Type

```php
use Hexa\PluginCore\QueryFilter\QueryFilterSet;
use Hexa\PluginCore\QueryFilter\QueryFilterType;
use Hexa\PluginCore\QueryFilter\QueryFilterTypes;

final class PriceBandFilterType extends QueryFilterType {
    public function normalize( array $definition, string $source ): ?array {
        return [ 'meta_key' => 'price' ];               // null rejects the definition
    }

    public function where( $database, array $filter, $value, array $context ): string {
        $a = $context['alias'];                          // unique per filter
        return 'cheap' === $value ? $database->prepare(
            "EXISTS (SELECT 1 FROM {$database->postmeta} {$a} WHERE {$a}.post_id = {$context['table']}.ID"
            . " AND {$a}.meta_key = %s AND CAST({$a}.meta_value AS DECIMAL) < %d)", 'price', 25
        ) : '';
    }
}

QueryFilterTypes::register( 'price_band', new PriceBandFilterType() );

// In a profile: select values reach where() only when they are declared options.
'filters' => [ 'price' => [ 'type' => 'price_band', 'label' => 'Price', 'options' => [ 'cheap' => 'Under $25' ] ] ],
```

Profiles normalize lazily when first rendered, so a type may be registered at
any point during plugin boot (before or after the profiles that use it), or on
the `hexa_plugin_core_query_filter_types` action, which fires once at the first
type lookup. Built-in type names cannot be replaced. Override `controls()`
(a subset of `select`, `toggle`, `date_range`; a type offering none is
rejected), `sources()`, `keywords()` with `keyword_options()`, or return an
`options` key from `normalize()` when the defaults do not fit.

## Security And Performance Contract

- Identifiers come from normalized definitions; every visitor value is bound
  through `$wpdb->prepare()`.
- Every filter is one correlated `EXISTS` subquery (or an ID list), so rows
  never duplicate and inactive filters cost nothing.
- Select values must match declared options; `distinct` options never read
  user meta, so private user data cannot be enumerated.

## Testing

```bash
php tests/query-filters.php
```
