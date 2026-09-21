<?php

namespace Hexa\PluginCore\PluginUpdates;

final class UpdaterFilesystem {
    private const IGNORED_PACKAGE_DIRECTORIES = [
        '.git' => true,
        '.svn' => true,
        '.hg'  => true,
        '.bzr' => true,
    ];

    private const IGNORED_PACKAGE_FILES = [
        '.DS_Store' => true,
        'Thumbs.db' => true,
    ];

    public static function delete_directory( string $dir ): void {
        self::delete_path( $dir );
    }

    public static function delete_path( string $path ): bool {
        if ( ! file_exists( $path ) && ! is_link( $path ) ) {
            return true;
        }

        if ( is_link( $path ) || is_file( $path ) ) {
            return @unlink( $path );
        }

        if ( ! is_dir( $path ) ) {
            return false;
        }

        $scan = @scandir( $path );
        if ( false === $scan ) {
            return false;
        }

        $files = array_diff( $scan, [ '.', '..' ] );

        foreach ( $files as $file ) {
            if ( ! self::delete_path( $path . '/' . $file ) ) {
                return false;
            }
        }

        return @rmdir( $path );
    }

    public static function is_ignored_package_path( string $path, string $root = '' ): bool {
        $relative_path = self::relative_path( $path, $root );

        if ( '' === $relative_path ) {
            return false;
        }

        $segments = array_values( array_filter( explode( '/', $relative_path ), static fn( $segment ) => '' !== $segment ) );
        foreach ( $segments as $segment ) {
            if ( isset( self::IGNORED_PACKAGE_DIRECTORIES[ $segment ] ) ) {
                return true;
            }
        }

        $basename = basename( $relative_path );

        return isset( self::IGNORED_PACKAGE_FILES[ $basename ] );
    }

    /**
     * @return array<int,string>|\WP_Error
     */
    public static function purge_ignored_package_paths( string $root, bool $strict = false ): array|\WP_Error {
        if ( ! is_dir( $root ) ) {
            return [];
        }

        $removed = [];
        $failed  = [];

        self::purge_ignored_from_directory( self::normalize_path( $root ), self::normalize_path( $root ), $removed, $failed );

        if ( $strict && $failed ) {
            return new \WP_Error(
                'hexa_plugin_core_package_metadata_locked',
                'Could not remove package metadata before update: ' . implode( ', ', array_slice( $failed, 0, 8 ) )
            );
        }

        return $removed;
    }

    public static function copy_directory_clean( string $source, string $destination ): true|\WP_Error {
        $source      = untrailingslashit( $source );
        $destination = untrailingslashit( $destination );

        if ( ! is_dir( $source ) ) {
            return new \WP_Error( 'hexa_plugin_core_copy_source_missing', 'Source folder does not exist: ' . $source );
        }

        $purged = self::purge_ignored_package_paths( $destination, true );
        if ( is_wp_error( $purged ) ) {
            return $purged;
        }

        $copied = self::copy_entry_clean( $source, $destination, $source );

        if ( is_wp_error( $copied ) ) {
            return $copied;
        }

        return true;
    }

    /**
     * Replace a directory through a sibling staging directory and restore the
     * original directory if the final move fails. Unlike copy_directory_clean(),
     * this removes files that no longer exist in the source package.
     */
    public static function replace_directory_clean( string $source, string $destination ): true|\WP_Error {
        $plan = self::stage_directory_replacement( $source, $destination );
        if ( is_wp_error( $plan ) ) {
            return $plan;
        }
        if ( ! empty( $plan['noop'] ) ) {
            return true;
        }

        $committed = self::commit_directory_replacement( $plan );
        if ( is_wp_error( $committed ) ) {
            self::rollback_directory_replacement( $plan );
            return $committed;
        }

        return self::finalize_directory_replacement( $committed );
    }

    /**
     * Copies and verifies a replacement beside its destination without
     * changing the live directory. Callers can stage an entire fleet before
     * committing any member.
     *
     * @return array<string,mixed>|\WP_Error
     */
    public static function stage_directory_replacement( string $source, string $destination ): array|\WP_Error {
        $source      = untrailingslashit( $source );
        $destination = untrailingslashit( $destination );
        $source_real = realpath( $source );
        $target_real = realpath( $destination );

        if ( false === $source_real || ! is_dir( $source_real ) ) {
            return new \WP_Error( 'hexa_plugin_core_replace_source_missing', 'Replacement source folder does not exist: ' . $source );
        }
        if ( false !== $target_real && $source_real === $target_real ) {
            return [
                'source'          => $source_real,
                'destination'     => $destination,
                'stage'           => '',
                'backup'          => '',
                'had_destination' => true,
                'committed'       => false,
                'noop'            => true,
                'manifest_hash'   => self::directory_manifest_hash( $source_real ),
            ];
        }

        $parent = dirname( $destination );
        if ( ! is_dir( $parent ) ) {
            return new \WP_Error( 'hexa_plugin_core_replace_parent_missing', 'Replacement parent folder does not exist: ' . $parent );
        }

        $token  = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : str_replace( '.', '', uniqid( '', true ) );
        $stage  = $parent . '/.' . basename( $destination ) . '-stage-' . $token;
        $backup = $parent . '/.' . basename( $destination ) . '-backup-' . $token;

        $copied = self::copy_directory_clean( $source_real, $stage );
        if ( is_wp_error( $copied ) ) {
            self::delete_directory( $stage );
            return $copied;
        }
        if ( self::directory_manifest_hash( $source_real ) !== self::directory_manifest_hash( $stage ) ) {
            self::delete_directory( $stage );
            return new \WP_Error( 'hexa_plugin_core_replace_stage_invalid', 'The staged package did not match its source package.' );
        }

        $had_destination = file_exists( $destination ) || is_link( $destination );
        return [
            'source'          => $source_real,
            'destination'     => $destination,
            'stage'           => $stage,
            'backup'          => $backup,
            'had_destination' => $had_destination,
            'committed'       => false,
            'noop'            => false,
            'manifest_hash'   => self::directory_manifest_hash( $source_real ),
        ];
    }

    /** @param array<string,mixed> $plan
     *  @return array<string,mixed>|\WP_Error
     */
    public static function commit_directory_replacement( array $plan ): array|\WP_Error {
        if ( ! empty( $plan['noop'] ) ) {
            return $plan;
        }
        $destination     = (string) ( $plan['destination'] ?? '' );
        $stage           = (string) ( $plan['stage'] ?? '' );
        $backup          = (string) ( $plan['backup'] ?? '' );
        $had_destination = ! empty( $plan['had_destination'] );
        $expected_hash   = (string) ( $plan['manifest_hash'] ?? '' );
        if ( '' === $destination || '' === $stage || '' === $backup || ! is_dir( $stage ) || self::directory_manifest_hash( $stage ) !== $expected_hash ) {
            return new \WP_Error( 'hexa_plugin_core_replace_plan_invalid', 'The staged replacement plan is missing or failed verification.' );
        }
        if ( $had_destination && ! file_exists( $destination ) && ! is_link( $destination ) ) {
            return new \WP_Error( 'hexa_plugin_core_replace_target_changed', 'The live package disappeared after staging; replacement was cancelled.' );
        }
        if ( ! $had_destination && ( file_exists( $destination ) || is_link( $destination ) ) ) {
            return new \WP_Error( 'hexa_plugin_core_replace_target_changed', 'A live package appeared after staging; replacement was cancelled.' );
        }
        if ( $had_destination && ! @rename( $destination, $backup ) ) {
            return new \WP_Error( 'hexa_plugin_core_replace_backup_failed', 'Could not move the existing package to its rollback directory.' );
        }

        if ( ! @rename( $stage, $destination ) ) {
            if ( $had_destination ) {
                @rename( $backup, $destination );
            }
            self::delete_directory( $stage );
            return new \WP_Error( 'hexa_plugin_core_replace_commit_failed', 'Could not move the staged package into its final directory.' );
        }

        $plan['committed'] = true;
        return $plan;
    }

    /** @param array<string,mixed> $plan */
    public static function rollback_directory_replacement( array $plan ): true|\WP_Error {
        if ( ! empty( $plan['noop'] ) ) {
            return true;
        }
        $destination     = (string) ( $plan['destination'] ?? '' );
        $stage           = (string) ( $plan['stage'] ?? '' );
        $backup          = (string) ( $plan['backup'] ?? '' );
        $had_destination = ! empty( $plan['had_destination'] );
        $committed       = ! empty( $plan['committed'] );

        if ( $committed ) {
            if ( ( file_exists( $destination ) || is_link( $destination ) ) && ! self::delete_path( $destination ) ) {
                return new \WP_Error( 'hexa_plugin_core_replace_rollback_delete_failed', 'The failed replacement could not be removed during rollback.' );
            }
            if ( $had_destination && ( ! file_exists( $backup ) || ! @rename( $backup, $destination ) ) ) {
                return new \WP_Error( 'hexa_plugin_core_replace_rollback_restore_failed', 'The prior package could not be restored from its rollback directory.' );
            }
        }
        if ( '' !== $stage && ( file_exists( $stage ) || is_link( $stage ) ) && ! self::delete_path( $stage ) ) {
            return new \WP_Error( 'hexa_plugin_core_replace_rollback_stage_failed', 'The staged replacement could not be removed during rollback.' );
        }

        return true;
    }

    /** @param array<string,mixed> $plan */
    public static function finalize_directory_replacement( array $plan ): true|\WP_Error {
        if ( empty( $plan['committed'] ) || empty( $plan['had_destination'] ) ) {
            return true;
        }
        $backup = (string) ( $plan['backup'] ?? '' );
        if ( '' !== $backup && ( file_exists( $backup ) || is_link( $backup ) ) && ! self::delete_path( $backup ) ) {
            return new \WP_Error( 'hexa_plugin_core_replace_finalize_failed', 'The verified replacement is live, but its rollback directory could not be removed.' );
        }
        return true;
    }

    private static function directory_manifest_hash( string $root ): string {
        $root  = self::normalize_path( $root );
        $files = [];
        if ( ! is_dir( $root ) ) {
            return '';
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS )
        );
        foreach ( $iterator as $file ) {
            if ( ! $file instanceof \SplFileInfo || ! $file->isFile() || $file->isLink() ) {
                continue;
            }
            $path = self::normalize_path( $file->getPathname() );
            if ( self::is_ignored_package_path( $path, $root ) ) {
                continue;
            }
            $files[] = $path;
        }
        sort( $files, SORT_STRING );
        $context = hash_init( 'sha256' );
        foreach ( $files as $path ) {
            hash_update( $context, substr( $path, strlen( $root ) ) );
            hash_update_file( $context, $path );
        }
        return hash_final( $context );
    }

    public static function add_folder_to_zip( \ZipArchive $zip, string $folder, string $base_path ): void {
        self::add_directory_to_zip( $zip, untrailingslashit( $folder ), $base_path, untrailingslashit( $folder ) );
    }

    /**
     * @param array<int,string> $removed
     * @param array<int,string> $failed
     */
    private static function purge_ignored_from_directory( string $dir, string $root, array &$removed, array &$failed ): void {
        $scan = @scandir( $dir );
        if ( false === $scan ) {
            return;
        }

        $files = array_diff( $scan, [ '.', '..' ] );

        foreach ( $files as $file ) {
            $path = $dir . '/' . $file;

            if ( self::is_ignored_package_path( $path, $root ) ) {
                if ( self::delete_path( $path ) ) {
                    $removed[] = $path;
                } else {
                    $failed[] = $path;
                }

                continue;
            }

            if ( is_dir( $path ) && ! is_link( $path ) ) {
                self::purge_ignored_from_directory( $path, $root, $removed, $failed );
            }
        }
    }

    private static function add_directory_to_zip( \ZipArchive $zip, string $dir, string $base_path, string $root ): void {
        $scan = @scandir( $dir );
        if ( false === $scan ) {
            return;
        }

        $files = array_diff( $scan, [ '.', '..' ] );
        foreach ( $files as $file ) {
            $path = $dir . '/' . $file;

            if ( self::is_ignored_package_path( $path, $root ) ) {
                continue;
            }

            if ( is_dir( $path ) && ! is_link( $path ) ) {
                self::add_directory_to_zip( $zip, $path, $base_path, $root );
                continue;
            }

            if ( ! is_file( $path ) ) {
                continue;
            }

            $relative_path = $base_path . '/' . substr( self::normalize_path( $path ), strlen( self::normalize_path( $root ) ) + 1 );
            $zip->addFile( $path, $relative_path );
        }
    }

    private static function copy_entry_clean( string $source, string $destination, string $source_root ): true|\WP_Error {
        if ( self::is_ignored_package_path( $source, $source_root ) ) {
            return true;
        }

        if ( is_link( $source ) ) {
            return true;
        }

        if ( is_dir( $source ) ) {
            if ( ! is_dir( $destination ) && ! wp_mkdir_p( $destination ) ) {
                return new \WP_Error( 'hexa_plugin_core_copy_directory_failed', 'Could not create folder: ' . $destination );
            }

            $scan = @scandir( $source );
            if ( false === $scan ) {
                return new \WP_Error( 'hexa_plugin_core_copy_scan_failed', 'Could not scan folder: ' . $source );
            }

            $files = array_diff( $scan, [ '.', '..' ] );
            foreach ( $files as $file ) {
                $result = self::copy_entry_clean( $source . '/' . $file, $destination . '/' . $file, $source_root );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }
            }

            return true;
        }

        if ( ! is_file( $source ) ) {
            return true;
        }

        $parent = dirname( $destination );
        if ( ! is_dir( $parent ) && ! wp_mkdir_p( $parent ) ) {
            return new \WP_Error( 'hexa_plugin_core_copy_parent_failed', 'Could not create folder: ' . $parent );
        }

        if ( ! @copy( $source, $destination ) ) {
            return new \WP_Error( 'hexa_plugin_core_copy_file_failed', 'Could not copy file: ' . $source );
        }

        @chmod( $destination, fileperms( $source ) & 0777 );

        return true;
    }

    private static function relative_path( string $path, string $root = '' ): string {
        $path = self::normalize_path( $path );
        $root = self::normalize_path( $root );

        if ( '' !== $root ) {
            if ( $path === $root ) {
                return '';
            }

            if ( str_starts_with( $path, $root . '/' ) ) {
                return substr( $path, strlen( $root ) + 1 );
            }
        }

        return ltrim( $path, '/' );
    }

    private static function normalize_path( string $path ): string {
        $path = str_replace( '\\', '/', $path );

        return rtrim( $path, '/' );
    }
}
