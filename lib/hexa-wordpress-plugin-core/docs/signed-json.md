# Signed JSON requests

Namespace: `Hexa\PluginCore\ApiKeys`, folder `src/ApiKeys/`.

`SignedJsonClient` sends HTTPS POST and returns the ordinary WordPress response or WP_Error. The host passes its configured endpoint, protected site key, calling URL or domain (Core normalizes case, www and trailing dots and rejects IP identities), header prefix and timeout. It owns response handling and any server verification policy.

Headers append `Key`, `Domain`, `Timestamp`, `Nonce` and `Signature` to the prefix. The signature is hex HMAC-SHA256 with the site key over POST, URL path, Unix timestamp, nonce, domain and hex SHA256 of the exact JSON bytes, joined with newlines without a trailing newline. Empty payloads encode as `{}`. Every send creates a fresh 32-byte random nonce. Redirects, query strings, fragments and URL credentials are refused; TLS verification stays enabled and no cookies are sent.

Example: `(new SignedJsonClient($prefix))->post($endpoint, $payload, $key, $domain, $timeout)`.

Run `php tests/signed-json.php` for fixed-vector, signed-component, transport, encoding, nonce and invalid-address checks.
