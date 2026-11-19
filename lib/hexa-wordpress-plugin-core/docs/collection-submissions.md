# Collection submissions
Namespace: Hexa\PluginCore\FrontendForms. Source: src/FrontendForms/SubmissionStore.php.
Host responsibilities: feature switch, nonce/capability checks, form-specific validation, consent policy, notifications and privacy hook registration.
Call install() once when explicitly enabling a host feature. Own tables are {prefix}hexa_form_submissions and {prefix}hexa_form_rates.

    $store = new \Hexa\PluginCore\FrontendForms\SubmissionStore;
    $store->install();
    $result = $store->add('weekly', 'person@example.com', [
        'server' => $_SERVER, 'page_url' => 'https://example.com/newsletter',
        'source' => 'footer', 'name' => 'Person', 'consent' => true,
    ]);
    // status: created (with id), duplicate, invalid, error
    $store->total('weekly');
    $store->recent('weekly', 20); // descending ID, optional before ID for cursor pagination
    $store->csv($authorizedStream, 'weekly');
    $store->personal_data('person@example.com', 1);
    $store->erase('person@example.com');
    $store->allow(SubmissionStore::client_ip($_SERVER)); // ten attempts / ten-minute window

Email normalizes to lowercase and trim; a unique database key enforces per-collection deduplication under concurrent requests.
Timestamps are UTC. est() uses fixed UTC-05:00 year-round. Export includes both UTC and EST.
Only user_agent, referer, accept_language and page_url are stored, capped at 2048 bytes each.
Cookies and authorization headers are never captured.
Only Cloudflare edge CIDRs may supply CF-Connecting-IP. Other proxies must resolve REMOTE_ADDR at the server,
or use hexa_form_submission_client_ip with explicit trusted-peer checks. X-Forwarded-For is never blindly trusted.
Rate counters use atomic SQL increments, hashed IP/window keys and bounded expired-counter cleanup.
CSV writes in 500-row cursor batches and neutralizes spreadsheet formulas, including leading whitespace.
csv() is an internal primitive; HTTP callers must verify capability and nonce first.
Run tests/submission-store.php for pure normalization/IP/header/escaping cases; consumer integration tests cover actual WordPress/MySQL storage and endpoints.

Single-row deletion: delete($collection, $id) returns true only when exactly that collection/id row was removed. Hosts must verify administrator capability and a nonce bound to both values.
