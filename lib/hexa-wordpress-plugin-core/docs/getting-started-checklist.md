# Getting Started Checklist

Namespace:

```text
Hexa\PluginCore\GettingStartedChecklist
```

Use this module when a plugin needs a reusable setup or onboarding process that runs plugin-owned callbacks in a predictable sequence.

Set `show_type_badges` to `false` when the checklist is being used as a simple action list and the request type would read like non-clickable clutter.

Set `show_search` to `true` when a checklist contains enough actions to need filtering. Core renders the shared collection-search UI, searches both parent and actionable child metadata, updates visible/total counts, force-hides nonmatches even when host row CSS declares a display mode, hides empty parent groups, and reapplies the active query when a template replaces the checklist rows. Hosts may customize `search_label`, `search_placeholder`, and `search_empty_message`; the default remains disabled so existing checklists do not change unexpectedly.

## Core Classes

- `GettingStartedChecklistConfig`: host-specific IDs, labels, action names, nonce settings, capability, and registered steps.
- `GettingStartedChecklistStep`: one top-level checklist item. A step may have a callback, subtasks, or both.
- `GettingStartedChecklistSubtask`: one nested checklist item under a parent step.
- `GettingStartedChecklistRunner`: executes one step or subtask and normalizes callback results.
- `GettingStartedChecklistStateStore`: persists per-template outcomes, derives parent status, builds summaries, and resets state.
- `GettingStartedChecklistAjaxController`: registers the guarded AJAX endpoint through `WpAdminAjax\AjaxActionRegistry`.
- `GettingStartedChecklistRenderer`: renders the checklist UI, sequential AJAX runner, spinner/check/X states, nested subtasks, and technical activity log.
- `GettingStartedChecklistAssets`: internal scoped CSS and browser behavior used by the renderer.

## Internal Rendering Boundary

`GettingStartedChecklistRenderer` owns checklist markup and host configuration. `GettingStartedChecklistAssets` owns only the reusable scoped CSS and browser runtime. Host plugins continue to instantiate the renderer and must not call or replace the asset collaborator.

State persistence is enabled by default. `state_option` is derived from the checklist root/action unless the host supplies a stable option key. `status_action` and `reset_action` are registered beside `run_action`; the browser restores state on load and after template changes. Set `persistence_enabled => false` only for an intentionally ephemeral checklist.

Every step and subtask accepts `batch_enabled` (default `true`), `destructive` (default `false`), `mutating`, and an optional additional `capability`. `status_check` is read-only by default; every other type is mutating unless the host explicitly declares a read-only callback with `mutating => false`, and destructive items are always mutating. Full-checklist runs skip batch-disabled items, continue after failed read-only status checks, and halt on the first mutating failure. Core labels destructive and individual-only items; hosts remain responsible for confirmations and callback safety.

The checklist-level capability remains mandatory on every AJAX action. Step and subtask capabilities are cumulative item-level requirements checked again by the controller and runner; a subtask cannot replace or weaken its parent or host capability. Explicit template IDs are matched exactly. Invalid or crafted IDs never resolve to the default template and a step is never looked up in another template.

Verify the boundary with `php -n tests/architecture-boundaries.php`; it checks class sizes and renders the extracted asset payload.

## Host Plugin Rule

The host plugin owns only the process definition and callback functions. The UI, AJAX contract, sequential execution, status rendering, request type display, and log rendering belong in Hexa WP Core.

## Step Types

Every step and subtask may declare a `type`. The type is shown in the UI and is passed to callbacks as `request_type`.

Allowed types:

- `callback`: generic PHP callback.
- `status_check`: reads/report status without changing configuration.
- `setup_action`: runs a setup or repair command.
- `feature_toggle`: enables or disables a plugin feature.
- `config_mutation`: writes configuration such as options, constants, or settings.
- `ajax_request`: represents a host-owned AJAX request step. Register the actual callback in the host plugin.
- `custom`: anything plugin-specific that does not fit the other types.

Each step may also define:

- `action_label`: button label for that item. If omitted, Core chooses one from the type.
- `request`: structured request metadata. Core passes the raw request array to the callback and redacts secret/token/password/nonce/key values in public output.

## Required Inputs

Use `required_inputs` when a checklist item cannot run until an operator types a value. `inputs` is accepted as an alias for the same structure. Core owns the UI fields, client-side validation, AJAX payload, server-side validation, sanitization, and callback payload. The host plugin owns only the callback that consumes the typed value.

Supported field types are `text`, `email`, `url`, `password`, `number`, `tel`, `search`, and `confirmation`. Number fields support `min`, `max`, and `step`; Core enforces the range in both the browser and the guarded server runner. Required fields block item, step, and full-checklist execution until valid values are entered.

```php
[
    'id'          => 'smtp_setup',
    'label'       => 'Apply SMTP Settings',
    'type'        => 'config_mutation',
    'callback'    => 'my_plugin_apply_smtp_settings',
    'required_inputs' => [
        [
            'id'          => 'from_email',
            'label'       => 'From email',
            'type'        => 'email',
            'required'    => true,
            'placeholder' => '',
            'description' => 'Passed to the callback as $payload["inputs"]["from_email"].',
        ],
    ],
]
```

Do not hardcode site-specific values in Core or in host checklist definitions. For SMTP, alert emails, API keys, confirmation text, or destructive approval text, collect the value through `required_inputs` and feed `$payload["inputs"]` into the existing host implementation.

## Result Reports

Checklist callbacks can return reusable reports under `data.reports`. Table reports are built with `ChecklistReportBuilder::table()`. For generated assets, add image preview metadata so the UI shows a visible preview plus an open-in-new-tab link before the table:

```php
ChecklistReportBuilder::table(
    'generated_assets',
    'Generated Assets',
    $rows,
    ['asset' => 'Asset', 'url' => 'URL'],
    [
        'meta' => [
            'preview_assets' => [
                [
                    'label'       => 'Generated PNG',
                    'format'      => 'PNG',
                    'url'         => $png_url,
                    'preview_url' => add_query_arg('preview', time(), $png_url),
                    'meta'        => 'Attachment ID: ' . $attachment_id,
                ],
            ],
        ],
    ]
);
```

For wp-config reports, Core labels columns as `Before Action`, `Requested Value`, and `Verified After`. Host plugins must use the verified value when deciding whether a task actually passed.

Version 0.19.33 supports plain-English report summaries through `meta.documentation` and `meta.summary_items`. Use these for every checklist item that mutates state so the operator can see what existed before, what action ran, and what was verified afterward without interpreting only raw table values.

## Destructive Confirmation Sample

Use `DestructiveSampleRunner` when a plugin needs a visible example of a destructive task that cannot run until the operator types an exact confirmation phrase. The runner creates temporary draft posts and temporary featured media, deletes only the records it created, and returns reusable Core reports for:

- Deleted posts: title, ID, permalink, and deleted media URLs.
- Deleted files: file name, absolute location, and size.

```php
use Hexa\PluginCore\GettingStartedChecklist\DestructiveSampleRunner;

[
    'id'          => 'sample_delete_posts_with_media',
    'label'       => 'Sample Delete Posts With Media',
    'type'        => 'setup_action',
    'description' => 'Creates temporary sample posts/media and deletes only those after typed confirmation.',
    'callback'    => 'my_plugin_run_quick_start_task',
    'required_inputs' => [
        DestructiveSampleRunner::confirmation_input(),
    ],
]

function my_plugin_run_quick_start_task(array $payload): array {
    return DestructiveSampleRunner::run([
        'title_prefix' => 'My Plugin Delete Sample',
        'post_count'   => 3,
    ]);
}
```

Do not use this runner to delete production posts. It is a reusable UI/proof sample for typed destructive confirmation and report rendering.

## Workflow Extensions

Use the browser workflow extension API when a host step needs a specialized live visualization that is not a general checklist report. Core still owns validation, state, AJAX helpers, logs, and the checklist lifecycle; the host owns only its domain-specific renderer and result mapping.

Each rendered checklist root exposes `root.hexaChecklistApi` and dispatches:

- `hexa:checklist:ready` when the API is available.
- `hexa:checklist:run` before Core runs a step or item.

The run event detail includes `api`, `row`, `scope`, `stepId`, `subtaskId`, `handled`, and `promise`. A host claims only its own step by setting `handled = true` and assigning a promise. The promise may resolve to `true`/`false` or `{success, mutating}`. Core normalizes that result through `api.normalizeRunOutcome`; `api.rowMutates`, `api.runItem`, `api.runStep`, and `api.runAll` support host-owned programmatic workflows while preserving the same batch-stop behavior.

```js
const root = document.querySelector('[data-hpc-getting-started-checklist]');

root.addEventListener('hexa:checklist:run', (event) => {
    const detail = event.detail;
    if (detail.scope !== 'step' || detail.stepId !== 'host-owned-workflow') return;

    detail.handled = true;
    detail.promise = runHostWorkflow(detail.api, detail.row);
});
```

Do not add host step IDs, AJAX action names, field keys, or domain-specific CSS/JavaScript to `GettingStartedChecklistRenderer`.

## Basic Setup

```php
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistAjaxController;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistConfig;
use Hexa\PluginCore\GettingStartedChecklist\GettingStartedChecklistRenderer;

$config = new GettingStartedChecklistConfig([
    'root_id'      => 'my-plugin-getting-started',
    'title'        => 'Getting Started Checklist',
    'description'  => 'Runs setup checks for this plugin.',
    'show_search'  => true,
    'capability'   => 'manage_options',
    'nonce_action' => 'my_plugin_getting_started',
    'nonce_field'  => 'nonce',
    'run_action'   => 'my_plugin_getting_started_run_item',
    'status_action'=> 'my_plugin_getting_started_status',
    'reset_action' => 'my_plugin_getting_started_reset',
    'state_option' => 'my_plugin_getting_started_state',
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

add_action('init', function() use ($config) {
    ( new GettingStartedChecklistAjaxController($config) )->register(); // run, status, reset
});

function my_plugin_render_getting_started_tab(): void {
    ( new GettingStartedChecklistRenderer(my_plugin_getting_started_config()) )->render();
}
```

## Callback Contract

Callbacks receive one array:

```php
[
    'step'       => [...],
    'subtask'    => [...]|null,
    'context'    => [...],
    'request'    => [...],
    'request_type' => 'status_check',
    'is_subtask' => true|false,
    'item_id'    => 'step-id' or 'step-id:subtask-id',
    'inputs'     => ["from_email" => "typed@example.com"],
]
```

Callbacks may return:

- `true` or `false`
- a success message string
- `WP_Error`
- an array:

```php
[
    'success' => true,
    'message' => 'Finished.',
    'logs'    => [
        ['level' => 'info', 'message' => 'Detailed log line.', 'context' => ['key' => 'value']],
    ],
    'data'    => ['optional' => 'payload'],
]
```

## UI Behavior

- `Run Checklist` executes top-level steps in order.
- Simple steps render as one row in a continuous list with their own run button.
- Only steps that actually contain subtasks render as expandable parent sections.
- A step with subtasks shows the parent row as running while each subtask runs one after another.
- A completed item shows a green check SVG.
- A failed item shows a red X SVG.
- The currently running item shows a spinner.
- The technical activity log below the checklist receives callback logs and core transition logs in real time.
