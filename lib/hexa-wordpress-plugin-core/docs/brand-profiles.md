# Brand Profiles

Namespace:

```text
Hexa\PluginCore\BrandProfiles
```

`BrandProfile` is the shared value object for public branded experiences. It normalizes a domain, display name, HTTPS logo URL, primary and accent colors, and support email. Host plugins own persistence and decide which request host selects a profile; Core owns normalization and the canonical CSS-variable names.

```php
use Hexa\PluginCore\BrandProfiles\BrandProfile;

$brand = BrandProfile::from_array( get_option( 'example_brand', [] ) );
$values = $brand->to_array();
$variables = $brand->css_variables();
```

Do not place product names, service catalogs, Stripe identifiers, or email-template IDs in Core. Those remain in the host plugin or application.

Test with:

```bash
php -n tests/brand-profile.php
```
