<?php

namespace Hexa\PluginCore\CorePackageUpdates;

use Hexa\PluginCore\PluginUpdates\UpdaterFilesystem;

final class CorePackageZipBuilder {
    private CorePackageConfig $config;

    public function __construct( CorePackageConfig $config ) {
        $this->config = $config;
    }

    public function build_current(): array|\WP_Error {
        return $this->build( $this->config->github_branch(), $this->config->proper_folder_name() . '.zip' );
    }

    private function build( string $ref, string $filename ): array|\WP_Error {
        if ( ! class_exists( '\ZipArchive' ) ) {
            return new \WP_Error( 'hexa_core_package_zip_missing', 'ZipArchive is not available on this server.' );
        }

        $upload_dir  = wp_upload_dir();
        $temp_dir    = trailingslashit( $upload_dir['basedir'] ) . 'hexa-core-package-temp-' . time() . '-' . wp_rand( 1000, 9999 );
        $temp_zip    = $temp_dir . '/github-download.zip';
        $extract_dir = $temp_dir . '/extracted';
        $final_zip   = trailingslashit( $upload_dir['basedir'] ) . $filename;

        if ( ! wp_mkdir_p( $temp_dir ) || ! wp_mkdir_p( $extract_dir ) ) {
            return new \WP_Error( 'hexa_core_package_temp_failed', 'Could not create temporary ZIP workspace.' );
        }

        $response = wp_remote_get(
            $this->config->zip_url( $ref ),
            [
                'timeout'  => 60,
                'stream'   => true,
                'filename' => $temp_zip,
            ]
        );

        if ( is_wp_error( $response ) ) {
            UpdaterFilesystem::delete_directory( $temp_dir );

            return $response;
        }

        if ( ! file_exists( $temp_zip ) ) {
            UpdaterFilesystem::delete_directory( $temp_dir );

            return new \WP_Error( 'hexa_core_package_download_missing', 'Download failed; ZIP file was not created.' );
        }

        $zip = new \ZipArchive();
        if ( true !== $zip->open( $temp_zip ) ) {
            UpdaterFilesystem::delete_directory( $temp_dir );

            return new \WP_Error( 'hexa_core_package_zip_open_failed', 'Failed to open downloaded ZIP file.' );
        }

        $zip->extractTo( $extract_dir );
        $zip->close();

        $extracted_folders = glob( $extract_dir . '/*', GLOB_ONLYDIR );
        if ( empty( $extracted_folders ) ) {
            UpdaterFilesystem::delete_directory( $temp_dir );

            return new \WP_Error( 'hexa_core_package_zip_empty', 'No folder found inside extracted ZIP.' );
        }

        $source_folder = $extracted_folders[0];
        $target_folder = $extract_dir . '/' . $this->config->proper_folder_name();

        if ( basename( $source_folder ) !== $this->config->proper_folder_name() ) {
            @rename( $source_folder, $target_folder );
        } else {
            $target_folder = $source_folder;
        }

        if ( ! is_dir( $target_folder ) ) {
            UpdaterFilesystem::delete_directory( $temp_dir );

            return new \WP_Error( 'hexa_core_package_folder_normalize_failed', 'Could not normalize the extracted folder name.' );
        }

        $new_zip = new \ZipArchive();
        if ( true !== $new_zip->open( $final_zip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
            UpdaterFilesystem::delete_directory( $temp_dir );

            return new \WP_Error( 'hexa_core_package_zip_create_failed', 'Failed to create normalized ZIP file.' );
        }

        UpdaterFilesystem::add_folder_to_zip( $new_zip, $target_folder, $this->config->proper_folder_name() );
        $new_zip->close();

        UpdaterFilesystem::delete_directory( $temp_dir );

        return [
            'message'  => 'Hexa Core ZIP created successfully.',
            'url'      => trailingslashit( $upload_dir['baseurl'] ) . $filename,
            'filename' => $filename,
        ];
    }
}
