<?php

namespace Hexa\PluginCore\IntegrationTests;

use Hexa\PluginCore\CoreContracts\PluginContextInterface;

final class IntegrationTestRuntime {
    /** @var array<string,array<string,mixed>> */
    private static array $hosts = [];

    public static function register_host( PluginContextInterface $context ): void {
        $values = $context->all();
        $slug   = self::key( (string) ( $values['slug'] ?? '' ) );
        if ( '' !== $slug ) {
            self::$hosts[ $slug ] = $values;
        }
        TestEndpointController::register_once();
    }

    public static function registry(): TestRegistry {
        $registry = new TestRegistry();
        self::register_core_tests( $registry );
        foreach ( self::$hosts as $slug => $context ) {
            self::register_host_tests( $registry, $slug, $context );
        }
        return $registry;
    }

    /** @return array<string,array<string,mixed>> */
    public static function hosts(): array {
        return self::$hosts;
    }

    private static function register_core_tests( TestRegistry $registry ): void {
        $registry->register(
            'core.runtime-package',
            'Selected Core package is healthy',
            static function(): array {
                if ( ! class_exists( 'HexaPluginCorePackageRegistry', false ) ) {
                    return [ 'passed' => false, 'summary' => 'The shared package registry is unavailable.', 'expected' => 'Managed Core package registry', 'actual' => 'Missing' ];
                }
                $report = \HexaPluginCorePackageRegistry::report();
                $selected = is_array( $report['selected'] ?? null ) ? $report['selected'] : [];
                $issues = is_array( $report['issues'] ?? null ) ? $report['issues'] : [];
                return [
                    'passed'  => ! empty( $report['healthy'] ),
                    'summary' => empty( $issues ) ? 'One compatible, internally consistent Core package is active.' : count( $issues ) . ' Core package integrity issue(s) were detected.',
                    'expected'=> 'No package conflicts, late candidates, or source mismatches',
                    'actual'  => empty( $issues ) ? 'No issues' : count( $issues ) . ' issue(s)',
                    'details' => [
                        'selected_version' => (string) ( $selected['version'] ?? 'unknown' ),
                        'selected_host'    => (string) ( $selected['host'] ?? 'unknown' ),
                        'candidate_count'  => count( (array) ( $report['candidates'] ?? [] ) ),
                        'issues'           => $issues,
                    ],
                ];
            },
            [ 'group' => 'Hexa WP Core', 'description' => 'Detects mixed bundles, conflicting hashes, incompatible constraints, and late Core loading.', 'host' => 'core' ]
        );

        $registry->register(
            'core.source-hash',
            'Selected Core source matches PACKAGE_HASH',
            static function(): array {
                if ( ! class_exists( 'HexaPluginCorePackageRegistry', false ) ) {
                    return [ 'passed' => false, 'summary' => 'The package registry is unavailable.' ];
                }
                $report   = \HexaPluginCorePackageRegistry::report();
                $selected = is_array( $report['selected'] ?? null ) ? $report['selected'] : [];
                $root     = (string) ( $selected['root'] ?? '' );
                $declared = is_file( $root . '/PACKAGE_HASH' ) ? trim( (string) file_get_contents( $root . '/PACKAGE_HASH' ) ) : '';
                $actual   = '' !== $root ? \HexaPluginCorePackageRegistry::source_hash( $root ) : '';
                return [
                    'passed'   => '' !== $declared && hash_equals( $declared, $actual ),
                    'summary'  => '' !== $declared && hash_equals( $declared, $actual ) ? 'Executable Core source matches the release manifest.' : 'Core source differs from its release manifest.',
                    'expected' => $declared ?: 'Declared PACKAGE_HASH',
                    'actual'   => $actual ?: 'Unable to calculate',
                    'details'  => [ 'root' => $root ],
                ];
            },
            [ 'group' => 'Hexa WP Core', 'description' => 'Catches partial deploys and unversioned edits in bootstrap.php or src/.', 'host' => 'core' ]
        );

        $registry->register(
            'core.required-classes',
            'Required Core contracts autoload',
            static function(): array {
                $classes = [
                    'Hexa\\PluginCore\\CoreBootstrap\\CoreBootstrap',
                    'Hexa\\PluginCore\\QuerySafety\\QueryEligibility',
                    'Hexa\\PluginCore\\QuerySafety\\StaticFrontPageQueryGuard',
                    'Hexa\\PluginCore\\WpAdminComponents\\CoreUi',
                    'Hexa\\PluginCore\\WpAdminAjax\\AjaxActionRegistry',
                    'Hexa\\PluginCore\\EntitySources\\CanonicalEntityResolver',
                    'Hexa\\PluginCore\\IntegrationTests\\TestRunner',
                ];
                $missing = array_values( array_filter( $classes, static fn( string $class ): bool => ! class_exists( $class ) ) );
                return [
                    'passed'   => [] === $missing,
                    'summary'  => [] === $missing ? 'All shared runtime contracts autoload correctly.' : count( $missing ) . ' required class(es) failed to autoload.',
                    'expected' => count( $classes ) . ' available classes',
                    'actual'   => ( count( $classes ) - count( $missing ) ) . ' available classes',
                    'details'  => [ 'missing' => $missing ],
                ];
            },
            [ 'group' => 'Hexa WP Core', 'description' => 'Confirms the selected package can autoload critical shared namespaces.', 'host' => 'core' ]
        );
    }

    /** @param array<string,mixed> $context */
    private static function register_host_tests( TestRegistry $registry, string $slug, array $context ): void {
        $label = ucwords( str_replace( [ '-', '_' ], ' ', $slug ) );
        $registry->register(
            $slug . '.plugin-context',
            $label . ' runtime context is complete',
            static function() use ( $context ): array {
                $required = [ 'slug', 'basename', 'version', 'path', 'url', 'github_repo', 'admin_page', 'capability' ];
                $missing = [];
                foreach ( $required as $key ) {
                    if ( ! isset( $context[ $key ] ) || '' === trim( (string) $context[ $key ] ) ) {
                        $missing[] = $key;
                    }
                }
                return [
                    'passed'   => [] === $missing,
                    'summary'  => [] === $missing ? 'The plugin supplied every required shared-runtime value.' : 'The plugin context is incomplete.',
                    'expected' => implode( ', ', $required ),
                    'actual'   => [] === $missing ? 'All keys present' : 'Missing: ' . implode( ', ', $missing ),
                    'details'  => [ 'version' => (string) ( $context['version'] ?? '' ), 'repository' => (string) ( $context['github_repo'] ?? '' ) ],
                ];
            },
            [ 'group' => $label, 'description' => 'Validates the host contract consumed by shared Core services.', 'host' => $slug ]
        );

        $registry->register(
            $slug . '.version-contract',
            $label . ' code and plugin header versions agree',
            static function() use ( $context ): array {
                $root      = rtrim( (string) ( $context['path'] ?? '' ), '/\\' );
                $main_file = $root . '/' . basename( (string) ( $context['basename'] ?? '' ) );
                $header    = is_readable( $main_file ) ? (string) file_get_contents( $main_file ) : '';
                preg_match( '/^[ \t\/*#@]*Version:\s*([^\r\n*]+)/mi', $header, $matches );
                $header_version  = trim( (string) ( $matches[1] ?? '' ) );
                $runtime_version = trim( (string) ( $context['version'] ?? '' ) );
                return [
                    'passed'   => '' !== $runtime_version && $runtime_version === $header_version,
                    'summary'  => $runtime_version === $header_version ? 'Runtime metadata and the WordPress plugin header report the same release.' : 'Plugin version sources disagree or the main file is unreadable.',
                    'expected' => $runtime_version ?: 'Runtime version',
                    'actual'   => $header_version ?: 'Header unavailable',
                    'details'  => [ 'main_file' => $main_file ],
                ];
            },
            [ 'group' => $label, 'description' => 'Prevents release headers and internal version constants from drifting.', 'host' => $slug ]
        );
    }

    private static function key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( (string) preg_replace( '/[^a-z0-9_-]/i', '', $value ) );
    }
}
