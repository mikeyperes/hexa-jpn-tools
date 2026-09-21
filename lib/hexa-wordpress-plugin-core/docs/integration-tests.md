# Integration Tests

Namespace: `Hexa\PluginCore\IntegrationTests`

Every host that boots `CoreBootstrap` is registered automatically with the shared integration-test runtime. The runtime adds read-only Core/package checks and host context/version checks, then exposes one capability-protected WordPress admin report:

```text
/wp-admin/tools.php?page=hexa-integration-tests
/wp-admin/tools.php?page=hexa-integration-tests&format=json
```

Both URLs require an authenticated user with `manage_options`. Loading either URL runs the tests immediately. The HTML report provides pass/fail status, expected and actual values, details, per-test duration, host grouping, and a rerun control. JSON returns the same report contract and uses HTTP 500 when any test fails.

## Host Tests

Plugins add behavior-specific tests through the shared registration hook:

```php
add_action(
    'hexa_plugin_core_register_integration_tests',
    static function ( \Hexa\PluginCore\IntegrationTests\TestRegistry $registry ): void {
        $registry->register(
            'example.settings-contract',
            'Example settings contract is valid',
            static fn(): array => [
                'passed'  => true,
                'summary' => 'The settings contract is valid.',
                'expected'=> 'One canonical option',
                'actual'  => 'One canonical option',
                'details' => [ 'option' => 'example_settings' ],
            ],
            [
                'group'       => 'Example Plugin',
                'host'        => 'example-plugin',
                'description' => 'Protects a release-critical plugin contract.',
            ]
        );
    }
);
```

Callbacks must be deterministic and non-mutating by default. Return `passed`, `summary`, `expected`, `actual`, and optional `details`. Exceptions are caught and reported as failures. IDs must remain stable so release automation can compare reports.

## Built-in Checks

- Selected Core package health and candidate consistency.
- Selected Core source hash against `PACKAGE_HASH`.
- Required Core namespace autoloading.
- Every registered host context has all required values.
- Every host's runtime version matches its WordPress plugin header.

Run the standalone framework test with `php tests/integration-tests.php`.
