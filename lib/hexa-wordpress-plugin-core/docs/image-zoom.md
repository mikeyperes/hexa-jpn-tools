# ImageZoom

`Hexa\PluginCore\PublicComponents\ImageZoom` shows one image (a flyer, a poster or a product shot) at full quality on demand.

```php
echo ImageZoom::html( $attachment_id, [ 'size' => 'large', 'class' => 'my-flyer' ] );
```

- **Mouse:** hovering the thumbnail shows the original upload large in a centered preview. The preview fades out when the pointer leaves the thumbnail, the page scrolls, or the window loses focus.
- **Click, tap or Enter:** opens a full-screen viewer. You can zoom with a pinch, the mouse wheel or a double-tap, pan with a finger or the mouse, and close with a swipe down, Escape, a backdrop tap, the × button or the browser Back button. It fades as it closes.
- **No JavaScript:** the link opens the original image.

The thumbnail fills its container (`object-fit: cover`), so size the container. Pass `'fit' => 'contain'` to show the whole image over a blurred fill of itself instead of cropping it. An explicit `sizes` list replaces WordPress's lazy `auto` sizing. The style and script print once per page. Restyle with `--hiz-pop-width`, `--hiz-border`, `--hiz-radius`, `--hiz-bg` and `--hiz-backdrop`.
