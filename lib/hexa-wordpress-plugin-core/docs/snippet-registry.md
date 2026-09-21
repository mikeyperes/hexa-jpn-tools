# Snippet Registry

Namespace:

```text
Hexa\PluginCore\SnippetRegistry
```

Folder:

```text
src/SnippetRegistry/
```

## Purpose

The snippet registry is the shared Hexa Core layer for host plugin feature snippets.

It replaces plugin-specific snippet tables with a generic definition model, renderer, and AJAX toggle/test controller. Host plugins still own their snippet functions, option names, shortcodes, and detailed documentation.

## Public Classes

- `SnippetDefinition`: normalized host snippet metadata.
- `SnippetRegistry`: stores definitions, reads/writes enabled state, and evaluates test rules.
- `SnippetsTableRenderer`: canonical Hexa Core snippets UI. It renders the dense category table, status/test pills, optional toggle switch, inline details, shortcodes, test rules, and readme sections.
- `SnippetRenderer`: backward-compatible wrapper that delegates to `SnippetsTableRenderer`.
- `SnippetAjaxController`: optional generic AJAX controller for toggle and test actions.

## Definition Shape

```php
[
    'id'          => 'enable_example_feature',
    'name'        => 'Example Feature',
    'category'    => 'frontend',
    'description' => 'Registers frontend behavior for example pages.',
    'function'    => 'register_example_feature',
    'option_key'  => 'enable_example_feature',
    'snippets'    => [
        [
            'label'       => 'Activation function',
            'value'       => 'register_example_feature',
            'description' => 'Called after the option is enabled.',
        ],
    ],
    'shortcodes'  => [
        [
            'tag'         => 'example_shortcode',
            'label'       => 'Example shortcode',
            'value'       => '[example_shortcode]',
            'description' => 'Renders the example output.',
        ],
    ],
    'testing'     => [
        [
            'id'          => 'option_enabled',
            'label'       => 'Snippet option is enabled',
            'type'        => 'option_enabled',
            'required'    => true,
            'description' => 'Confirms the controlling option is active.',
        ],
    ],
    'readme'      => 'Longer operational notes for the snippet.',
]
```

## Testing Rules

Built-in rule types:

- `option_enabled`: checks the snippet option state.
- `function_exists`: checks that the configured function is loaded.
- `shortcode_exists`: checks that WordPress has a shortcode tag registered.
- `callback`: runs a host-provided callback.

Rules render inside the Testing component as expandable rows. Required failures mark the snippet test as failed; optional failures mark it as a warning.

## Host Plugin Responsibilities

Host plugins must:

1. Build a `SnippetRegistry` from their snippet definitions.
2. Render `SnippetRenderer` or `SnippetsTableRenderer` in the host snippets tab.
3. Register `SnippetAjaxController` or route existing core AJAX actions to `SnippetRegistry::set_enabled()` and `SnippetRegistry::test()`.
4. Keep snippet functions in the host plugin namespace.
5. Keep host-specific shortcodes and readme text in the host plugin.

## Table Renderer Args

The table renderer accepts:

- `title`: visible table title.
- `description`: visible helper copy under the title.
- `ajax_url`: admin-AJAX endpoint. Defaults to `admin_url( 'admin-ajax.php' )` when available.
- `toggle_action`: AJAX action for snippet enable/disable.
- `test_action`: AJAX action for snippet tests.
- `nonce`: nonce value sent with AJAX requests.
- `nonce_field`: request field for the nonce. Defaults to `nonce`.
- `categories`: map of category IDs to `label` and `description`.
- `root_id`: optional DOM ID for the snippets root.
- `show_toggle`: set to `false` for a read-only implementation catalog when another screen owns feature controls.

Each definition can pass:

- `description`: the short explanation shown in the detail panel.
- `info`: richer HTML detail shown under the description.
- `snippets`: hooks, callbacks, files, or internal structures used by the snippet.
- `shortcodes`: related shortcode tags, values, and descriptions.
- `testing` or `test_rules`: expandable rule checks for the Test panel.
- `readme`: longer operational notes for the snippet.

## Example Usage

```php
$registry = ( new \Hexa\PluginCore\SnippetRegistry\SnippetRegistry() )
    ->add_many( $definitions );

echo ( new \Hexa\PluginCore\SnippetRegistry\SnippetRenderer() )->render(
    $registry,
    [
        'toggle_action' => 'example_toggle_snippet',
        'test_action'   => 'example_test_snippet',
        'nonce'         => wp_create_nonce( 'example_admin' ),
        'nonce_field'   => 'nonce',
    ]
);
```

## Verification

Use PHP lint on the new classes and load the host snippets tab. Test a snippet toggle and test button through the visible admin UI or with a nonce-backed admin-AJAX request.
