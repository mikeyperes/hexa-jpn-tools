# WP Admin AJAX Namespace

Namespace:

```text
Hexa\PluginCore\WpAdminAjax
```

Folder:

```text
src/WpAdminAjax/
```

Purpose:

- Create and verify WordPress nonces for admin AJAX endpoints.
- Read nonce values from `nonce`, `_ajax_nonce`, or `_wpnonce`.
- Enforce capabilities with consistent JSON errors.
- Register AJAX actions consistently.
- Normalize request values through `AjaxRequest`.
- Wrap AJAX callbacks with capability checks, nonce checks, exception handling, and JSON responses.

Primary low-level guard:

```php
use Hexa\PluginCore\WpAdminAjax\AjaxGuard;

$nonce = AjaxGuard::create_nonce( 'example_action' );
AjaxGuard::require_nonce_or_error( 'example_action' );
AjaxGuard::require_capability_or_error( 'manage_options' );

AjaxGuard::handle(
    static function () {
        return [ 'message' => 'Done' ];
    },
    [
        'capability'   => 'manage_options',
        'nonce_action' => 'example_action',
    ]
);
```

Preferred action registry:

```php
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxFailure;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;

( new AjaxActionRegistry(
    [
        'capability'   => 'manage_options',
        'nonce_action' => 'example_admin',
        'nonce_field'  => 'nonce',
    ]
) )->register(
    [
        'example_search' => [
            'callback' => static function ( AjaxRequest $request ): array {
                $term = $request->text( 'term', '' );

                if ( '' === $term ) {
                    throw AjaxFailure::bad_request( 'Search term is required.' );
                }

                return [ 'term' => $term, 'results' => [] ];
            },
        ],
    ]
);
```

Registry callback rules:

- Return an array for `wp_send_json_success`.
- Throw `AjaxFailure` for expected validation errors.
- Return `WP_Error` only when wrapping a WordPress API result.
- Do not call `wp_send_json_*` inside host callbacks registered through the registry.
- Read request values from `AjaxRequest`, not raw `$_POST`.
