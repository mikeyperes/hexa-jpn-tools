# Item Link Behavior And Lightbox

## Namespace And Folder

`Hexa\PluginCore\PublicComponents\ItemLink` and
`Hexa\PluginCore\PublicComponents\ItemLightbox` in `src/PublicComponents/`.

## Purpose

One setting decides what a click on an item link does in every public
component that lists items (`Calendar`, `Map`):

| `link_behavior` | Click result |
| --- | --- |
| `page` (default) | Follows the link in the same tab. |
| `new_tab` | Opens the link in a new tab (`target="_blank" rel="noopener"`). The legacy calendar `link_target => '_blank'` still means this. |
| `lightbox` | Shows the linked post in an in-page dialog, so visitors can click through a calendar or a map without leaving the page. |

In `lightbox` mode only links to a published, non-password post of an allowed
type open in the dialog; every other link (a user, another post type, an
outside URL) keeps following its link. Every lightbox link keeps its real
`href`, so it works without JavaScript, and a Ctrl/Cmd/Shift/Alt or middle
click still opens the page.

## Setup

```php
'link_behavior' => 'lightbox',
'lightbox'      => [
    'post_types' => [ 'event' ],                     // default: the component's post types
    'render'     => fn( int $post_id ): string => '…', // dialog body (escape it); default: image, title, excerpt
    'assets'     => fn() => wp_enqueue_style( '…' ),   // styles the render markup needs
    'page_link'  => true,                             // false hides the dialog's "Open full page" link
],
```

A map card row may name its post with `id`; otherwise Core resolves it from the
row URL (only in lightbox mode, once per cached payload). A calendar `callback`
provider may set `post_id` on an item the same way.

## REST Endpoint

```text
GET /wp-json/hexa-plugin-core/v1/lightbox/{calendar|map}/{profile}/{post_id}
```

Returns `{ html, title, url }` under the profile's visibility (anonymous
responses are publicly cacheable for 60 seconds). It answers 404 unless the
profile uses `lightbox` and the post is eligible. While `render` runs, the post
is the global post, so template tags and post-aware shortcodes read it.

## Dialog

A native modal `<dialog>`: focus moves into it and returns to the clicked
link, Escape, the close button, a backdrop click, and the browser Back button
close it, and the page behind does not scroll. Phones get a bottom sheet.
Responses are cached in the page, so reopening an item is instant. Labels:
`lightbox_close`, `lightbox_open`, `lightbox_loading`, `lightbox_error`.

## Theming

The dialog copies these custom properties from the clicked component, so a page
builder styles it where it styles the calendar or map: `--hlb-bg`, `--hlb-fg`,
`--hlb-muted`, `--hlb-border`, `--hlb-accent`, `--hlb-radius`, `--hlb-width`
(720px), `--hlb-backdrop`.

## Testing

`php tests/item-link.php`.
