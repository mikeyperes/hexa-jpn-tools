# Task Runner

Namespace: `Hexa\PluginCore\WpAdminComponents\TaskRunner`

Shared pieces for an admin screen that runs a long job as a series of AJAX
steps, so the page reports every step live and never reloads.

## PHP

Give the root element the `hpc-runner-app` class for the shared look.

- `TaskRunner::header( [ 'title', 'text', 'actions_html' ] )`: page title,
  one-line description and the main buttons.
- `TaskRunner::segments( [ id => label ] )`: segmented switcher between
  panels marked `data-hpc-runner-panel="id"`; the last choice is remembered.

- `TaskRunner::stats( $cards )`: stat cards. Each card has `key`, `label`,
  optional `value` and `hint`; the client updates `[data-hpc-stat="key"]`.
- `TaskRunner::progress( [ 'stop' => true ] )`: slim progress bar with a
  percentage and an optional Stop link, hidden until the first step.
- `TaskRunner::log( [ 'title' => 'Live Log', 'open' => true ] )`: live log
  console with a Clear button.
- `TaskRunner::state( $status, $label )`: status badge (`pending`, `running`,
  `done`, `error`, `skipped`).
- Layout classes: `.hpc-runner-card`, `.hpc-runner-toolbar`,
  `.hpc-runner-chip` (`.is-active`), `.hpc-runner-bulkbar`, `.hpc-runner-search`,
  `.hpc-runner-link`, `.hpc-runner-table-wrap` / `.hpc-runner-table` (`td.num`),
  `.hpc-runner-badge` (`.is-green`, `.is-amber`, `.is-red`, `.is-blue`),
  `.hpc-runner-check`, `details.hpc-runner-more`, `.hpc-button.small`.

## JavaScript

`window.HexaWpCoreTaskRunner.create( root )` reads `data-ajax-url`,
`data-nonce` and `data-nonce-field` from the root and returns a runner:

- `post( action, payload )`: guarded admin-ajax POST; resolves `data`, rejects
  with the server message.
- `queue( items, worker, { onProgress, onError, concurrency } )`: runs
  `worker( item )` one item at a time, or `concurrency` at once for read-only
  work such as counting; a failed item is logged and the queue continues.
- `loop( action, payload, { onStep, maxSteps } )`: repeats a batched action
  until the server returns `done: true`.
- `begin()`, `cancel()`, `cancelled()`, `end()`, `busy`: stop between steps.
- `progress( done, total, title, text )`, `log( level, message )`,
  `setRow( row, status, label )`, `showSegment( id )`, `segmentCount( id, n )`,
  plus `esc`, `bytes`, `number`, `state`, `badge( label, tone )`.

Use it with `DynamicButton` for button states and `DynamicNotice` for the
final message. Host plugins keep their own AJAX actions and business rules.
