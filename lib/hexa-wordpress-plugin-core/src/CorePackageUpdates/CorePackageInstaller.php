<?php

namespace Hexa\PluginCore\CorePackageUpdates;

use Hexa\PluginCore\PluginUpdates\UpdaterFilesystem;
use Hexa\PluginCore\PluginUpdates\UpdateProgressStore;
use Hexa\PluginCore\CoreRuntime\CoreVersion;
use WP_Error;

final class CorePackageInstaller {
    private CorePackageConfig $config;

    private ?UpdateProgressStore $progress;

    public function __construct( CorePackageConfig $config, ?UpdateProgressStore $progress = null ) {
        $this->config = $config;
        $this->progress = $progress;
    }

    public function run(): array|WP_Error {
        $result = $this->install_targets(
            [ [ 'host' => '', 'root' => $this->config->core_root() ] ],
            false
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return [
            'message'     => 'Hexa WordPress Plugin Core updated.',
            'new_version' => (string) $result['new_version'],
            'core_root'   => $this->config->core_root(),
        ];
    }

    /**
     * Returns the distinct host/Core roots registered through bootstrap.php.
     *
     * @return array<int,array{host:string,root:string,version:string,hash:string}>
     */
    public function registered_hosts(): array {
        if ( ! class_exists( '\\HexaPluginCorePackageRegistry', false ) || ! is_callable( [ '\\HexaPluginCorePackageRegistry', 'report' ] ) ) {
            return [];
        }

        $report = \HexaPluginCorePackageRegistry::report();
        $hosts  = [];
        $seen   = [];
        foreach ( (array) ( $report['candidates'] ?? [] ) as $candidate ) {
            if ( ! is_array( $candidate ) ) {
                continue;
            }
            $root = rtrim( (string) ( $candidate['root'] ?? '' ), '/\\' );
            if ( '' === $root || ! is_dir( $root ) || isset( $seen[ $root ] ) ) {
                continue;
            }
            $hosts[] = [
                'host'    => trim( (string) ( $candidate['host'] ?? '' ) ),
                'root'    => $root,
                'version' => trim( (string) ( $candidate['version'] ?? '' ) ),
                'hash'    => trim( (string) ( $candidate['hash'] ?? '' ) ),
            ];
            $seen[ $root ] = true;
        }
        return $hosts;
    }

    /**
     * Downloads Core once and synchronizes every distinct registered host root.
     * Falls back to run() when the bootstrap registry has no candidates.
     *
     * @return array<string,mixed>|WP_Error
     */
    public function run_registered_hosts(): array|WP_Error {
        $hosts = $this->registered_hosts();
        if ( [] === $hosts ) {
            return $this->run();
        }
        return $this->install_targets( $hosts, true );
    }

    /** @param array<int,array<string,mixed>> $targets
     *  @return array<string,mixed>|WP_Error
     */
    private function install_targets( array $targets, bool $fleet ): array|WP_Error {
        $this->reset_progress();
        $this->step( 'Preparing WordPress filesystem access.' );

        if ( ! function_exists( 'download_url' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if ( ! function_exists( 'unzip_file' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if ( ! function_exists( 'copy_dir' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        WP_Filesystem();

        $this->step( 'Downloading latest Hexa WordPress Plugin Core from ' . $this->config->github_repo() . ' (' . $this->config->github_branch() . ').' );
        $tmp_zip = download_url( $this->config->zip_url(), (int) $this->config->get( 'timeout', 15 ) );
        if ( is_wp_error( $tmp_zip ) ) {
            return $this->fail( $tmp_zip );
        }

        $extract_to = trailingslashit( get_temp_dir() ) . 'hexa-plugin-core-' . wp_generate_uuid4();
        wp_mkdir_p( $extract_to );

        $this->step( 'Unzipping downloaded core package.' );
        $unzipped = unzip_file( $tmp_zip, $extract_to );
        wp_delete_file( $tmp_zip );

        if ( is_wp_error( $unzipped ) ) {
            return $this->fail( $unzipped );
        }

        $this->step( 'Verifying downloaded package structure.' );
        $source = $this->locate_source( $extract_to );
        if ( ! $source ) {
            $this->delete_dir( $extract_to );

            return $this->fail( new WP_Error( 'hexa_core_source_missing', 'Downloaded core package did not contain VERSION and src.' ) );
        }

        $source_version = trim( (string) file_get_contents( trailingslashit( $source ) . 'VERSION' ) );
        $source_stored_hash = is_readable( trailingslashit( $source ) . 'PACKAGE_HASH' )
            ? trim( (string) file_get_contents( trailingslashit( $source ) . 'PACKAGE_HASH' ) )
            : '';
        $source_actual_hash = class_exists( '\\HexaPluginCorePackageRegistry', false )
            ? (string) \HexaPluginCorePackageRegistry::source_hash( $source )
            : '';
        if ( '' === $source_version || '' === $source_stored_hash || ! hash_equals( $source_stored_hash, $source_actual_hash ) ) {
            $this->delete_dir( $extract_to );
            return $this->fail( new WP_Error( 'hexa_core_source_integrity_failed', 'Downloaded Core package failed VERSION or PACKAGE_HASH verification.' ) );
        }

        $updated = [];
        $plans   = [];
        foreach ( $targets as $target ) {
            $root = rtrim( (string) ( $target['root'] ?? '' ), '/\\' );
            $host = trim( (string) ( $target['host'] ?? '' ) );
            if ( '' === $root ) {
                continue;
            }
            $this->step( 'Staging verified Core files for ' . ( '' !== $host ? $host . ' at ' : '' ) . $root . ' without VCS metadata.' );
            $plan = UpdaterFilesystem::stage_directory_replacement( $source, $root );
            if ( is_wp_error( $plan ) ) {
                $rollback_errors = $this->rollback_plans( $plans );
                $this->delete_dir( $extract_to );
                if ( method_exists( $plan, 'add_data' ) ) {
                    $plan->add_data( [ 'host' => $host, 'core_root' => $root, 'phase' => 'stage', 'rollback_errors' => $rollback_errors ] );
                }
                return $this->fail( $plan );
            }
            $plans[] = [ 'plan' => $plan, 'host' => $host, 'root' => $root ];
        }

        if ( [] === $plans ) {
            $this->delete_dir( $extract_to );
            return $this->fail( new WP_Error( 'hexa_core_targets_missing', 'No valid registered Core package roots were available.' ) );
        }

        foreach ( $plans as $index => $item ) {
            $host = (string) $item['host'];
            $root = (string) $item['root'];
            $this->step( 'Committing staged Core files for ' . ( '' !== $host ? $host . ' at ' : '' ) . $root . '.' );
            $committed = UpdaterFilesystem::commit_directory_replacement( (array) $item['plan'] );
            if ( is_wp_error( $committed ) ) {
                $rollback_errors = $this->rollback_plans( $plans );
                $this->delete_dir( $extract_to );
                $committed->add_data( [ 'host' => $host, 'core_root' => $root, 'phase' => 'commit', 'rollback_errors' => $rollback_errors ] );
                return $this->fail( $committed );
            }
            $plans[ $index ]['plan'] = $committed;

            $installed_hash = class_exists( '\\HexaPluginCorePackageRegistry', false )
                ? (string) \HexaPluginCorePackageRegistry::source_hash( $root )
                : '';
            $installed_version = CoreVersion::current( $root );
            if ( $installed_version !== $source_version || ! hash_equals( $source_actual_hash, $installed_hash ) ) {
                $rollback_errors = $this->rollback_plans( $plans );
                $this->delete_dir( $extract_to );
                $error = new WP_Error( 'hexa_core_target_verify_failed', 'A committed Core package failed its post-copy VERSION or source-hash verification.' );
                $error->add_data( [ 'host' => $host, 'core_root' => $root, 'phase' => 'verify', 'rollback_errors' => $rollback_errors ] );
                return $this->fail( $error );
            }
            $updated[] = [
                'host'        => $host,
                'core_root'   => $root,
                'new_version' => $installed_version,
            ];
        }

        $cleanup_warnings = $this->finalize_plans( $plans );
        $this->delete_dir( $extract_to );

        $this->step( 'Clearing cached Git version data.' );
        ( new CorePackageVersionClient( $this->config ) )->clear_cache();

        $new_version = (string) $updated[0]['new_version'];
        $this->step( 'Confirmed vendored core version ' . $new_version . ' across ' . count( $updated ) . ' package root(s).', 'done' );
        $this->finish( 'done', 'Updated Hexa WordPress Plugin Core to v' . $new_version . ( $fleet ? ' across the registered host fleet.' : '.' ) );

        return [
            'message'     => $fleet ? 'Registered Hexa WordPress Plugin Core packages synchronized.' : 'Hexa WordPress Plugin Core updated.',
            'new_version' => $new_version,
            'updated_count' => count( $updated ),
            'hosts'       => $updated,
            'core_roots'  => array_values( array_map( static fn( array $item ): string => (string) $item['core_root'], $updated ) ),
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

    private function reset_progress(): void {
        if ( $this->progress ) {
            $this->progress->reset();
        }
    }

    private function step( string $message, string $status = 'running' ): void {
        if ( $this->progress ) {
            $this->progress->step( $message, $status );
        }
    }

    private function finish( string $state, string $message ): void {
        if ( $this->progress ) {
            $this->progress->finish( $state, $message );
        }
    }

    private function fail( WP_Error $error ): WP_Error {
        $this->finish( 'error', $error->get_error_message() );

        return $error;
    }

    private function locate_source( string $extract_to ): string|false {
        $entries = glob( trailingslashit( $extract_to ) . '*' );
        if ( ! $entries ) {
            return false;
        }

        foreach ( $entries as $entry ) {
            if (
                is_dir( $entry )
                && is_readable( trailingslashit( $entry ) . $this->config->version_file() )
                && is_dir( trailingslashit( $entry ) . 'src' )
            ) {
                return $entry;
            }
        }

        return false;
    }

    private function delete_dir( string $path ): void {
        global $wp_filesystem;

        if ( $wp_filesystem ) {
            $wp_filesystem->delete( $path, true );
        }
    }
}
