# Fields

Namespace: `Hexa\PluginCore\Fields`

The one custom-field API for Hexa plugins. With ACF (free or Pro) active,
every call delegates to ACF unchanged. Without ACF, Core registers, stores,
formats and edits the same fields natively, using ACF's storage layout, so
data moves freely between the two modes. ACF Pro is never a requirement.

## Classes

- `Acf`: `active()` and `mode()` (`acf` or `native`).
- `FieldGroups`: `add( $group )` takes the array you would pass to
  `acf_add_local_field_group()`; `ready( $callback, $priority )` runs a
  registration callback on `acf/init` with ACF, otherwise during `init`.
  Lookups: `get_group()`, `fields()`, `get_field()`, `all()`, `remove()`.
- `Field`: `get()`, `update()`, `delete()`, `the()`, `all()`, `object()`,
  `objects()`, and the loop functions `have_rows()`, `the_row()`,
  `get_sub_field()`, `the_sub_field()`, `get_row_index()`,
  `get_row_layout()`, `reset_rows()`. Signatures match ACF's.
  `Field::available()` replaces `function_exists( 'get_field' )` guards.
- `OptionsPages`: `add()` and `add_sub()` mirror `acf_add_options_page()` and
  `acf_add_options_sub_page()`.
- `Form`: `head()`, `render( $args )`, `enqueue()` and `data()` mirror
  `acf_form_head()`, `acf_form()`, `acf_enqueue_scripts()` and
  `acf_get_form_data()`.
- `Hooks`: `on( 'save_post', $callback )` listens on `acf/save_post` and on the
  native `hexa_fields/save_post`, so the callback runs in both modes. Qualified
  hooks work the same way (`prepare_field/name=title`, `load_value/key=...`,
  `format_value/type=...`, `update_value/...`, `render_field/...`, `init`).
- `Storage`, `Values`, `Renderer`, `AdminScreens`: the native engine.

## Native mode

- Storage: post, user, term and comment meta, or `{post_id}_{name}` options,
  each with the `_{name}` field-key reference. Group sub fields are
  `{group}_{field}`; repeater rows are `{repeater}_{row}_{field}` with the row
  count in `{repeater}`; flexible-content layouts are stored as the layout
  name list.
- Return formats match ACF for text, textarea (`new_lines`), wysiwyg, number,
  true_false, select, checkbox, radio, button_group, image, file, gallery,
  post_object, page_link, relationship, user, taxonomy, link, date, date-time,
  time, oembed, color, group, repeater and flexible content.
- Editing: post meta boxes, user profiles (add and edit), taxonomy term
  screens, options pages and `Form::render()`. Location rules supported:
  post_type, post, page_template, post_status, post_taxonomy, post_category,
  user_form, user_role, current_user, current_user_role, taxonomy and
  options_page, plus host-defined rules registered with
  `Hooks::on( 'location/rule_match/<param>', ... )` exactly as with ACF. Flexible content, clone and map fields keep their stored value
  but are edited only with ACF.
- Native hooks fire only as `hexa_fields/*`, never `acf/*`, so third-party ACF
  add-ons are never called without ACF.

## Groups built in the ACF admin screen

ACF stores groups created in its admin screen as `acf-field-group` and `acf-field` posts. Without ACF, `Fields\Database` reads them on first need (one query per request): their fields resolve and format like code-registered ones, active groups render on their screens, and a code-registered group with the same key takes precedence, as in ACF.

## Migrating a host plugin

| ACF | Fields |
| --- | --- |
| `acf_add_local_field_group( $g )` | `FieldGroups::add( $g )` |
| `add_action( 'acf/init', $cb, $p )` | `FieldGroups::ready( $cb, $p )` or `Hooks::on( 'init', $cb, $p )` |
| `add_filter( 'acf/<hook>', ... )` | `Hooks::on( '<hook>', ... )` |
| the same, at file level while plugins load | `hexa_fields_on( '<hook>', ... )` (defined in `bootstrap.php`; registers on `plugins_loaded`) |
| `get_field()` / `update_field()` / `have_rows()` ... | `Field::get()` / `Field::update()` / `Field::have_rows()` ... |
| `acf_add_options_page()` / `acf_add_options_sub_page()` | `OptionsPages::add()` / `OptionsPages::add_sub()` |
| `acf_form_head()` / `acf_form()` | `Form::head()` / `Form::render()` |
| `acf_render_field_wrap( $f )` | `Form::field( $f )` (posts under `acf[<key>]`, as ACF does) |
| `acf_get_field_group()` / `acf_get_fields()` / `acf_get_field()` | `FieldGroups::get_group()` / `fields()` / `get_field()` |
| `function_exists( 'get_field' )` | `Field::available()` |

Run `php bin/migrate-to-fields.php <plugin-root> --dry-run` to preview, then without `--dry-run` to apply the table above. It rewrites calls token by token (comments and strings untouched) and lists what needs a person: calls that run at file load before Core's autoloader exists (move them into a `plugins_loaded` or later callback), ACF plugin detection, and ACF Pro dependency declarations. Remove every "ACF Pro is required" gate and dependency declaration.

## Testing a host plugin

Require `lib/hexa-wordpress-plugin-core/tests/support/fields.php` after the test's own WordPress and ACF stubs. It autoloads the Fields classes and runs them in ACF mode, so `Field::get()` and `FieldGroups::add()` call the test's `get_field()` and `acf_add_local_field_group()` stubs exactly as they call ACF on a live site.
