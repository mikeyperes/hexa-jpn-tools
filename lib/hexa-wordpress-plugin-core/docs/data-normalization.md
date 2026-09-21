# Data Normalization

Namespace: `Hexa\PluginCore\DataNormalization`

Folder: `src/DataNormalization/`

`ValueNormalizer`, `FieldReader`, and `MediaNormalizer` provide generic scalar, ACF/meta, and media normalization. Hosts retain field-name priority, domain models, schema graph construction, and business validation.

```php
$fields = new FieldReader($post_id);
$name = ValueNormalizer::text($fields->first('organization_name', 'legal_name'));
$logo = MediaNormalizer::image($fields->first('logo', 'brand_logo'), 'full');
```

`ValueNormalizer` exposes `present`, `text`, `url`, `url_values`, `email`, `date`, `number`, `rows`, `row_values`, `strings`, `urls`, `ids`, and `single_or_array`. `url_values` recursively handles nested ACF values and one-URL-per-line fields without extracting links from prose; its optional flags control tag stripping and URL sanitization for compatibility seams. `row_values` preserves ordered, repeated raw repeater values, while `strings` provides sanitized, unique strings. `single_or_array` supports the common schema convention of emitting null, one scalar, or a list.

`FieldReader` is ACF-first with meta fallback and per-instance caching. Its static `acf_value` method supports explicit post, user, option, or other ACF contexts. The optional fourth argument selects PHP `empty()` compatibility; otherwise only null, an empty string, and false use the supplied default.

`MediaNormalizer` accepts IDs, attachment objects, ACF arrays, JSON arrays, URLs, and loose URL text. Attachment images fall back to the full source when a requested size is unavailable, ACF-provided alt/title/caption values override attachment metadata, and galleries deduplicate by normalized URL. `attachment_image_record` and `gallery_records` expose a stable six-key shape for procedural compatibility callbacks; gallery records derive bare-URL titles and retain the first duplicate.

Run `php tests/data-normalization.php`. Host regression tests should compare normalized payloads and schema output before and after adopting Core.
