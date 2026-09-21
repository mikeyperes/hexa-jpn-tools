# Credentials

Namespace: `Hexa\PluginCore\CredentialVault`

WordPress equivalent of the Laravel Hexa `CredentialService`.

## Classes

- `CredentialStore`
- `CredentialFieldRenderer`

## Storage

Credential option keys use:

```text
hpc_cred_{slug}_{keyName}
```

Values are encrypted with the WordPress auth salt when OpenSSL is available.

## Usage

```php
$store = new \Hexa\PluginCore\CredentialVault\CredentialStore();
$store->store( 'brevo', 'api_key', $raw_key );
$key = $store->get( 'brevo', 'api_key' );
$masked = $store->get_masked( 'brevo', 'api_key' );
$exists = $store->exists( 'brevo', 'api_key' );
$store->delete( 'brevo', 'api_key' );
```

## UI Requirements

Every API-key implementation must include:

- Visual field
- Masked current value
- Setup steps
- Save action
- Test action
- Delete action
