<?php

namespace Hexa\PluginCore\CorePackageUpdates;

use Hexa\PluginCore\PluginUpdates\UpdaterFilesystem;
use WP_Error;

/**
 * Keeps every registered host plugin's vendored Core package byte-equivalent
 * to the newest verified package already present on the site.
 */
final class CorePackageFleetSynchronizer {
    /** @return array<string,mixed>|WP_Error */
    public function synchronize( ?array $report = null ): array|WP_Error {
        $report ??= $this->runtime_report();
        $candidates = $this->fresh_candidates( (array) ( $report['candidates'] ?? [] ) );
        if ( [] === $candidates ) {
            return new WP_Error( 'hexa_core_sync_candidates_missing', 'No registered Hexa WP Core packages were available to synchronize.' );
        }

        usort( $candidates, static function ( array $left, array $right ): int {
            $version = version_compare( (string) $right['version'], (string) $left['version'] );
            return 0 !== $version ? $version : strcmp( (string) $left['root'], (string) $right['root'] );
        } );

        $highest_version = (string) $candidates[0]['version'];
        $highest         = array_values( array_filter( $candidates, static fn( array $candidate ): bool => $highest_version === (string) $candidate['version'] ) );
        $highest_hashes  = array_values( array_unique( array_column( $highest, 'calculated_hash' ) ) );
        if ( 1 !== count( $highest_hashes ) ) {
            return new WP_Error( 'hexa_core_sync_source_conflict', 'The newest registered Core packages claim the same version but contain different executable source.' );
        }

        $valid_sources = array_values( array_filter( $highest, static fn( array $candidate ): bool => (bool) $candidate['integrity'] ) );
        if ( [] === $valid_sources ) {
            return new WP_Error( 'hexa_core_sync_source_invalid', 'The newest registered Core package failed its PACKAGE_HASH verification.' );
        }
        $source = $valid_sources[0];
        if ( ! $this->satisfies_hosts( $highest_version, $candidates ) ) {
            return new WP_Error( 'hexa_core_sync_incompatible', 'The newest registered Core package does not satisfy every host plugin version constraint.' );
        }

        $updated = [];
        $current = [];
        $plans   = [];
        foreach ( $candidates as $candidate ) {
            $same_package = (string) $candidate['version'] === $highest_version
                && (string) $candidate['calculated_hash'] === (string) $source['calculated_hash']
                && $candidate['integrity'];
            if ( $same_package ) {
                $current[] = (string) $candidate['host'];
                continue;
            }

            $plan = UpdaterFilesystem::stage_directory_replacement( (string) $source['root'], (string) $candidate['root'] );
            if ( is_wp_error( $plan ) ) {
                $this->rollback_plans( $plans );
                $plan->add_data( [ 'host' => $candidate['host'], 'core_root' => $candidate['root'], 'phase' => 'stage' ] );
                return $plan;
            }
            $plans[] = [ 'plan' => $plan, 'candidate' => $candidate ];
        }

        foreach ( $plans as $index => $item ) {
            $candidate = (array) $item['candidate'];
            $committed = UpdaterFilesystem::commit_directory_replacement( (array) $item['plan'] );
            if ( is_wp_error( $committed ) ) {
                $rollback = $this->rollback_plans( $plans );
                $committed->add_data( [ 'host' => $candidate['host'], 'core_root' => $candidate['root'], 'phase' => 'commit', 'rollback_errors' => $rollback ] );
                return $committed;
            }
            $plans[ $index ]['plan'] = $committed;

            $verified_hash    = $this->source_hash( (string) $candidate['root'] );
            $verified_version = $this->read_value( (string) $candidate['root'] . '/VERSION' );
            if ( $verified_version !== $highest_version || $verified_hash !== (string) $source['calculated_hash'] ) {
                $rollback = $this->rollback_plans( $plans );
                $error = new WP_Error( 'hexa_core_sync_verify_failed', 'A synchronized Core package failed its post-copy verification.' );
                $error->add_data( [ 'host' => $candidate['host'], 'core_root' => $candidate['root'], 'phase' => 'verify', 'rollback_errors' => $rollback ] );
                return $error;
            }
            $updated[] = (string) $candidate['host'];
        }

        $cleanup_warnings = $this->finalize_plans( $plans );

        return [
            'success'       => true,
            'version'       => $highest_version,
            'hash'          => (string) $source['calculated_hash'],
            'source_host'   => (string) $source['host'],
            'updated_hosts' => array_values( array_unique( $updated ) ),
            'current_hosts' => array_values( array_unique( $current ) ),
            'changed'       => [] !== $updated,
            'cleanup_warnings' => $cleanup_warnings,
        ];
    }

    /** @param array<int,array<string,mixed>> $items
     *  @return array<int,string>
     */
    private function rollback_plans( array $items ): array {
        $errors = [];
        foreach ( array_reverse( $items ) as $item ) {
            $result = UpdaterFilesystem::rollback_directory_replacement( (array) ( $item['plan'] ?? [] ) );
            if ( is_wp_error( $result ) ) {
                $errors[] = $result->get_error_message();
            }
        }
        return $errors;
    }

    /** @param array<int,array<string,mixed>> $items
     *  @return array<int,string>
     */
    private function finalize_plans( array $items ): array {
        $warnings = [];
        foreach ( $items as $item ) {
            $result = UpdaterFilesystem::finalize_directory_replacement( (array) ( $item['plan'] ?? [] ) );
            if ( is_wp_error( $result ) ) {
                $warnings[] = $result->get_error_message();
            }
        }
        return $warnings;
    }

    /** @return array<string,mixed> */
    private function runtime_report(): array {
        if ( ! class_exists( '\\HexaPluginCorePackageRegistry', false ) || ! is_callable( [ '\\HexaPluginCorePackageRegistry', 'report' ] ) ) {
            return [];
        }
        return (array) \HexaPluginCorePackageRegistry::report();
    }

    /**
     * @param array<int,mixed> $registered
     * @return array<int,array<string,mixed>>
     */
    private function fresh_candidates( array $registered ): array {
        $candidates = [];
        $seen       = [];
        foreach ( $registered as $candidate ) {
            if ( ! is_array( $candidate ) ) {
                continue;
            }
            $root = realpath( rtrim( (string) ( $candidate['root'] ?? '' ), '/\\' ) );
            if ( false === $root || ! is_dir( $root . '/src' ) || isset( $seen[ $root ] ) ) {
                continue;
            }
            $version = $this->read_value( $root . '/VERSION' );
            $stored  = $this->read_value( $root . '/PACKAGE_HASH' );
            $actual  = $this->source_hash( $root );
            $candidates[] = [
                'host'            => trim( (string) ( $candidate['host'] ?? '' ) ),
                'root'            => $root,
                'version'         => $version,
                'stored_hash'     => $stored,
                'calculated_hash' => $actual,
                'integrity'       => '' !== $stored && hash_equals( $stored, $actual ),
                'minimum_version' => trim( (string) ( $candidate['minimum_version'] ?? '' ) ),
                'maximum_version' => trim( (string) ( $candidate['maximum_version'] ?? '' ) ),
            ];
            $seen[ $root ] = true;
        }
        return $candidates;
    }

    /** @param array<int,array<string,mixed>> $candidates */
    private function satisfies_hosts( string $version, array $candidates ): bool {
        foreach ( $candidates as $candidate ) {
            $minimum = (string) $candidate['minimum_version'];
            $maximum = (string) $candidate['maximum_version'];
            if ( '' !== $minimum && version_compare( $version, $minimum, '<' ) ) {
                return false;
            }
            if ( '' !== $maximum && version_compare( $version, $maximum, '>' ) ) {
                return false;
            }
        }
        return true;
    }

    private function source_hash( string $root ): string {
        return class_exists( '\\HexaPluginCorePackageRegistry', false ) && is_callable( [ '\\HexaPluginCorePackageRegistry', 'source_hash' ] )
            ? (string) \HexaPluginCorePackageRegistry::source_hash( $root )
            : '';
    }

    private function read_value( string $path ): string {
        return is_readable( $path ) ? trim( (string) file_get_contents( $path ) ) : '';
    }
}
