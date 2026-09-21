<?php

namespace Hexa\PluginCore\PluginUpdates;

final class DirectPluginInstaller {
    private UpdaterConfig $config;

    private UpdateProgressStore $progress;

    public function __construct( UpdaterConfig $config, ?UpdateProgressStore $progress = null ) {
        $this->config   = $config;
        $this->progress = $progress ?: new UpdateProgressStore( $config->progress_key() );
    }

    public function run(): array|\WP_Error {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        @set_time_limit( 300 );

        $canonical_plugin_dir  = WP_PLUGIN_DIR . '/' . $this->config->proper_folder_name();
        $runtime_plugin_dir    = WP_PLUGIN_DIR . '/' . $this->config->runtime_folder_name();
        $canonical_plugin_file = $this->config->canonical_plugin_basename();
        $download_url          = $this->config->zip_url();
        $upgrade_root          = WP_CONTENT_DIR . '/upgrade';

        if ( ! wp_mkdir_p( $upgrade_root ) ) {
            $upgrade_root = get_temp_dir();
        }

        $work_dir    = trailingslashit( $upgrade_root ) . $this->config->plugin_slug() . '-update-' . time() . '-' . wp_rand( 1000, 9999 );
        $temp_zip    = $work_dir . '/source.zip';
        $extract_dir = $work_dir . '/extracted';

        $fail = function ( string $message ) use ( $work_dir ) {
            UpdaterFilesystem::delete_directory( $work_dir );
            $this->progress->finish( 'error', $message );

            return new \WP_Error( 'hexa_plugin_core_update_failed', $message );
        };

        $this->progress->reset();
        $this->progress->step( 'Preparing workspace.' );

        if ( ! wp_mkdir_p( $work_dir ) || ! wp_mkdir_p( $extract_dir ) ) {
            return $fail( 'Could not create the update workspace under wp-content/upgrade.' );
        }

        if ( $this->config->runtime_folder_name() !== $this->config->proper_folder_name() ) {
            $this->progress->step( 'Detected non-canonical install folder "' . $this->config->runtime_folder_name() . '"; will normalize to "' . $this->config->proper_folder_name() . '".', 'warn' );
        }

        $this->progress->step( 'Downloading latest build from ' . $this->config->github_repo() . ' (' . $this->config->github_branch() . ').' );
        $response = wp_remote_get(
            $download_url,
            [
                'timeout'  => 180,
                'stream'   => true,
                'filename' => $temp_zip,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $fail( 'Download failed: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code && 200 !== (int) $code ) {
            return $fail( 'Download failed: GitHub returned HTTP ' . $code . '.' );
        }

        if ( ! file_exists( $temp_zip ) || filesize( $temp_zip ) < 1024 ) {
            return $fail( 'Download failed: the archive did not arrive or was empty.' );
        }

        $this->progress->step( 'Downloaded ' . size_format( filesize( $temp_zip ), 1 ) . '.', 'done' );
        $this->progress->step( 'Extracting archive.' );

        WP_Filesystem();
        $unzip = unzip_file( $temp_zip, $extract_dir );
        if ( is_wp_error( $unzip ) ) {
            return $fail( 'Extract failed: ' . $unzip->get_error_message() );
        }

        $this->progress->step( 'Locating plugin files.' );
        $extracted_folders = glob( $extract_dir . '/*', GLOB_ONLYDIR );
        if ( empty( $extracted_folders ) ) {
            return $fail( 'Extract failed: no plugin folder was found inside the archive.' );
        }

        $source_folder = $extracted_folders[0];
        if ( ! file_exists( $source_folder . '/' . $this->config->plugin_starter_file() ) ) {
            return $fail( 'The downloaded archive does not look like this plugin; missing ' . $this->config->plugin_starter_file() . '.' );
        }

        $staged_folder = $extract_dir . '/' . $this->config->proper_folder_name();
        if ( $source_folder !== $staged_folder && ! @rename( $source_folder, $staged_folder ) ) {
            return $fail( 'Could not normalize the extracted folder name to "' . $this->config->proper_folder_name() . '".' );
        }

        $new_plugin_data = get_plugin_data( $staged_folder . '/' . $this->config->plugin_starter_file(), false, false );
        $new_version     = ! empty( $new_plugin_data['Version'] ) ? $new_plugin_data['Version'] : 'unknown';
        $purged_staged   = UpdaterFilesystem::purge_ignored_package_paths( $staged_folder, true );

        if ( is_wp_error( $purged_staged ) ) {
            return $fail( 'Package cleanup failed: ' . $purged_staged->get_error_message() );
        }

        $backup_dir = '';
        if ( is_dir( $canonical_plugin_dir ) ) {
            $current_data    = get_plugin_data( $canonical_plugin_dir . '/' . $this->config->plugin_starter_file(), false, false );
            $current_version = ! empty( $current_data['Version'] ) ? $current_data['Version'] : 'unknown';
            $backup_dir      = WP_PLUGIN_DIR . '/' . $this->config->proper_folder_name() . '.bak-' . time();

            $this->progress->step( 'Backing up current version (v' . $current_version . ').' );

            if ( ! @rename( $canonical_plugin_dir, $backup_dir ) ) {
                $copy = UpdaterFilesystem::copy_directory_clean( $canonical_plugin_dir, $backup_dir );
                if ( is_wp_error( $copy ) ) {
                    return $fail( 'Could not back up the current plugin folder; aborting before changes.' );
                }

                global $wp_filesystem;
                $wp_filesystem->delete( $canonical_plugin_dir, true );
            }
        }

        $this->progress->step( 'Installing v' . $new_version . ' into ' . $this->config->proper_folder_name() . '/.' );
        $installed = @rename( $staged_folder, $canonical_plugin_dir );
        if ( ! $installed ) {
            $copy      = UpdaterFilesystem::copy_directory_clean( $staged_folder, $canonical_plugin_dir );
            $installed = ! is_wp_error( $copy );
        }

        if ( ! $installed ) {
            if ( $backup_dir && is_dir( $backup_dir ) && ! is_dir( $canonical_plugin_dir ) ) {
                @rename( $backup_dir, $canonical_plugin_dir );
            }

            return $fail( 'Install failed: could not place the new plugin files. Previous version was restored.' );
        }

        $this->sweep_duplicate_folders( $canonical_plugin_dir, $runtime_plugin_dir );
        $this->repoint_active_plugin( $canonical_plugin_file );

        $this->progress->step( 'Cleaning up.' );
        UpdaterFilesystem::delete_directory( $backup_dir );
        UpdaterFilesystem::delete_directory( $work_dir );

        foreach ( (array) glob( WP_PLUGIN_DIR . '/' . $this->config->proper_folder_name() . '.bak-*', GLOB_ONLYDIR ) as $stale ) {
            UpdaterFilesystem::delete_directory( $stale );
        }

        ( new GitHubPluginUpdater( $this->config ) )->clear_cache();

        $confirmed         = get_plugin_data( $canonical_plugin_dir . '/' . $this->config->plugin_starter_file(), false, false );
        $confirmed_version = ! empty( $confirmed['Version'] ) ? $confirmed['Version'] : $new_version;

        $this->progress->step( 'Done; site is now on v' . $confirmed_version . '.', 'done' );
        $this->progress->finish( 'done', 'Updated to v' . $confirmed_version . '.' );

        return [
            'message'       => 'Updated to v' . $confirmed_version . '.',
            'new_version'   => $confirmed_version,
            'active_plugin' => $canonical_plugin_file,
            'reload'        => true,
        ];
    }

    private function sweep_duplicate_folders( string $canonical_plugin_dir, string $runtime_plugin_dir ): void {
        $targets = [];

        if ( $this->config->runtime_folder_name() !== $this->config->proper_folder_name() ) {
            $targets[] = $runtime_plugin_dir;
        }

        foreach ( (array) glob( WP_PLUGIN_DIR . '/' . $this->config->proper_folder_name() . '-*', GLOB_ONLYDIR ) as $stray ) {
            if ( $stray !== $canonical_plugin_dir ) {
                $targets[] = $stray;
            }
        }

        $targets = array_unique( $targets );
        if ( ! $targets ) {
            return;
        }

        $this->progress->step( 'Removing ' . count( $targets ) . ' duplicate folder(s).' );
        foreach ( $targets as $target ) {
            UpdaterFilesystem::delete_directory( $target );
        }
    }

    private function repoint_active_plugin( string $canonical_plugin_file ): void {
        $current_active   = (array) get_option( 'active_plugins', [] );
        $runtime_basename = $this->config->plugin_basename();

        if ( $runtime_basename !== $canonical_plugin_file && in_array( $runtime_basename, $current_active, true ) ) {
            $this->progress->step( 'Repointing active_plugins from "' . $runtime_basename . '" to "' . $canonical_plugin_file . '".' );
            $current_active = array_values(
                array_unique(
                    array_map(
                        static fn( $plugin ) => $plugin === $runtime_basename ? $canonical_plugin_file : $plugin,
                        $current_active
                    )
                )
            );
            update_option( 'active_plugins', $current_active );
        } elseif ( ! in_array( $canonical_plugin_file, $current_active, true ) ) {
            $this->progress->step( 'Adding "' . $canonical_plugin_file . '" to active_plugins.' );
            $current_active[] = $canonical_plugin_file;
            update_option( 'active_plugins', $current_active );
        }
    }
}
