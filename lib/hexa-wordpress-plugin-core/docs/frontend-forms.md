# Front-End Forms

Namespace:

```text
Hexa\PluginCore\FrontendForms
```

`FieldSchema` defines the host-neutral public form types shared by WordPress plugins: text, textarea, email, URL, number, date, select, radio, checkbox, WYSIWYG, image, and file. It normalizes field keys, labels, help, placeholders, options, and required state while rejecting duplicate or unsafe keys.

`RichTextValue` sanitizes WYSIWYG input through `wp_kses_post()` and creates a plain-text projection for logs, external APIs, and line-item descriptions.

```php
use Hexa\PluginCore\FrontendForms\FieldSchema;
use Hexa\PluginCore\FrontendForms\RichTextValue;

$fields = FieldSchema::normalize( $host_service_schema );
$safe_html = RichTextValue::sanitize( wp_unslash( $_POST['description'] ?? '' ) );
$plain_text = RichTextValue::plain_text( $safe_html );
```

Core does not own a host's service fields, persistence, checkout rules, or final markup. Hosts must still verify capabilities/nonces, validate required values, escape ordinary text, and use the rich-text sanitizer only for fields explicitly declared as `wysiwyg`.

Test with:

```bash
php -n tests/frontend-form-schema.php
```
