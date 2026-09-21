# Media Uploads

Namespace:

```text
Hexa\PluginCore\MediaUploads
```

`ImageUploadPolicy` is the shared JPEG, PNG, and WEBP upload boundary. It checks the upload status, byte limit, detected MIME type, and filename extension. SVG is intentionally excluded from the default public upload policy.

`WordPressImageUploader` applies that policy and stores one image in the WordPress Media Library. The host must verify its capability and nonce before calling `store()`; Core does not invent host permissions or action names.

```php
use Hexa\PluginCore\MediaUploads\ImageUploadPolicy;
use Hexa\PluginCore\MediaUploads\WordPressImageUploader;

check_ajax_referer( 'example_upload', 'nonce' );
current_user_can( 'upload_files' ) || wp_die( 'Forbidden', 403 );

$policy = new ImageUploadPolicy( 10 * MB_IN_BYTES );
$result = ( new WordPressImageUploader( $policy ) )->store( 'supporting_photo' );
```

The host owns attachment association, retention, deletion, and customer-facing presentation. Never expose server paths or trust browser-provided MIME values when a readable temporary upload is available.

Test with:

```bash
php -n tests/image-upload-policy.php
```
