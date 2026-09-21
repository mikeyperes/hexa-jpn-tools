<?php

namespace Hexa\PluginCore\GettingStartedChecklist;

final class DestructiveSampleRunner {
    public const DEFAULT_CONFIRMATION_TEXT = 'I APPROVE DELETING SAMPLE POSTS';

    private function __construct() {}

    /**
     * @return array<string,mixed>
     */
    public static function confirmation_input( array $args = [] ): array {
        return ChecklistReportBuilder::confirmation_input(
            (string) ( $args['id'] ?? 'delete_sample_posts_confirmation' ),
            (string) ( $args['confirm_text'] ?? self::DEFAULT_CONFIRMATION_TEXT ),
            (string) ( $args['label'] ?? 'Delete sample posts confirmation' ),
            [
                'description' => (string) ( $args['description'] ?? 'Type exactly: ' . ( $args['confirm_text'] ?? self::DEFAULT_CONFIRMATION_TEXT ) . '. This sample creates and deletes only temporary sample posts and temporary media generated during this task.' ),
            ]
        );
    }

    /**
     * @return array<string,mixed>
     */
    public static function run( array $args = [] ): array {
        if ( function_exists( 'current_user_can' ) && ( ! current_user_can( 'delete_posts' ) || ! current_user_can( 'upload_files' ) ) ) {
            return self::payload( false, 'Current user cannot create/delete sample posts and media.', 'error' );
        }

        if ( defined( 'ABSPATH' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $post_count   = max( 1, min( 10, (int) ( $args['post_count'] ?? 3 ) ) );
        $post_type    = sanitize_key( (string) ( $args['post_type'] ?? 'post' ) );
        $title_prefix = trim( (string) ( $args['title_prefix'] ?? 'Hexa Core Delete Sample' ) );
        $run_id       = gmdate( 'YmdHis' ) . '-' . wp_generate_password( 6, false, false );
        $created      = [];
        $deleted      = [];
        $deleted_files = [];
        $failed       = [];

        for ( $i = 1; $i <= $post_count; $i++ ) {
            $title   = ( '' !== $title_prefix ? $title_prefix : 'Hexa Core Delete Sample' ) . ' ' . $run_id . ' #' . $i;
            $post_id = wp_insert_post(
                [
                    'post_title'   => $title,
                    'post_name'    => sanitize_title( $title ),
                    'post_type'    => '' !== $post_type ? $post_type : 'post',
                    'post_status'  => 'draft',
                    'post_content' => 'Temporary post created by the Hexa WP Core destructive delete sample.',
                ],
                true
            );

            if ( is_wp_error( $post_id ) ) {
                $failed[] = 'Sample post ' . $i . ' could not be created: ' . $post_id->get_error_message();
                continue;
            }

            $media = self::create_sample_media_attachment( (int) $post_id, $run_id, $i );
            if ( is_wp_error( $media ) ) {
                $failed[] = 'Sample media for post ' . (int) $post_id . ' could not be created: ' . $media->get_error_message();
                $media    = [];
            }

            if ( is_array( $media ) && ! empty( $media['url'] ) ) {
                wp_update_post(
                    [
                        'ID'           => (int) $post_id,
                        'post_content' => 'Temporary post created by the Hexa WP Core destructive delete sample.' . "\n\n" . '<img src="' . esc_url_raw( (string) $media['url'] ) . '" alt="Hexa delete sample media">',
                    ]
                );
            }

            $created[] = [
                'id'        => (int) $post_id,
                'title'     => $title,
                'permalink' => get_permalink( (int) $post_id ),
                'media'     => is_array( $media ) && ! empty( $media ) ? [ $media ] : [],
            ];
        }

        foreach ( $created as $item ) {
            $media_report = [];
            foreach ( (array) $item['media'] as $media ) {
                $attachment_id = (int) ( $media['id'] ?? 0 );
                $path          = (string) ( $media['path'] ?? '' );
                $url           = (string) ( $media['url'] ?? '' );
                $size_bytes    = $path && file_exists( $path ) ? (int) filesize( $path ) : 0;

                if ( $attachment_id > 0 ) {
                    $deleted_attachment = wp_delete_attachment( $attachment_id, true );
                    if ( ! $deleted_attachment ) {
                        $failed[] = 'Attachment ' . $attachment_id . ' could not be deleted.';
                        continue;
                    }
                } elseif ( $path && file_exists( $path ) ) {
                    @unlink( $path );
                }

                $media_report[]  = $url ?: $path;
                $deleted_files[] = [
                    'file'       => $path ? basename( $path ) : 'Attachment ' . $attachment_id,
                    'path'       => $path,
                    'size_bytes' => $size_bytes,
                    'size'       => $size_bytes > 0 ? size_format( $size_bytes ) : '',
                ];
            }

            $deleted_post = wp_delete_post( (int) $item['id'], true );
            if ( ! $deleted_post ) {
                $failed[] = 'Post ' . (int) $item['id'] . ' could not be deleted.';
                continue;
            }

            $deleted[] = [
                'title'     => (string) $item['title'],
                'id'        => (int) $item['id'],
                'permalink' => (string) $item['permalink'],
                'media'     => $media_report,
            ];
        }

        $success = [] === $failed;

        return self::payload(
            $success,
            $success ? 'Sample destructive delete completed.' : 'Sample destructive delete completed with failures.',
            $success ? 'success' : 'warning',
            [
                'created_count'       => count( $created ),
                'deleted_post_count'  => count( $deleted ),
                'deleted_media_count' => count( $deleted_files ),
                'failures'            => $failed,
            ],
            [
                ChecklistReportBuilder::deleted_posts(
                    $deleted,
                    [
                        'title'   => (string) ( $args['posts_report_title'] ?? 'Sample Deleted Posts' ),
                        'summary' => count( $deleted ) . ' temporary sample posts were created and permanently deleted after typed confirmation.',
                    ]
                ),
                ChecklistReportBuilder::deleted_files(
                    $deleted_files,
                    [
                        'title'   => (string) ( $args['files_report_title'] ?? 'Sample Media Files Deleted' ),
                        'summary' => count( $deleted_files ) . ' temporary sample media files were permanently deleted.',
                    ]
                ),
            ]
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function payload( bool $success, string $message, string $level = 'success', array $context = [], array $reports = [] ): array {
        $data = $context;
        if ( [] !== $reports ) {
            $data['reports'] = array_values( array_filter( $reports ) );
        }

        return [
            'success' => $success,
            'message' => $message,
            'logs'    => [
                [
                    'level'   => $level,
                    'message' => $message,
                    'context' => $context,
                ],
            ],
            'data'    => $data,
        ];
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    private static function create_sample_media_attachment( int $post_id, string $run_id, int $index ): array|\WP_Error {
        $png_data = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=', true );
        if ( false === $png_data ) {
            return new \WP_Error( 'hexa_sample_media_decode_failed', 'Sample image payload could not be decoded.' );
        }

        $filename = 'hexa-delete-sample-' . sanitize_file_name( $run_id . '-' . $index ) . '.png';
        $upload   = wp_upload_bits( $filename, null, $png_data );
        if ( ! empty( $upload['error'] ) ) {
            return new \WP_Error( 'hexa_sample_media_upload_failed', (string) $upload['error'] );
        }

        $file_path = (string) ( $upload['file'] ?? '' );
        $file_url  = (string) ( $upload['url'] ?? '' );
        if ( '' === $file_path || ! file_exists( $file_path ) ) {
            return new \WP_Error( 'hexa_sample_media_missing_file', 'Sample image file was not created.' );
        }

        $filetype      = wp_check_filetype( $file_path );
        $attachment_id = wp_insert_attachment(
            [
                'post_mime_type' => $filetype['type'] ?: 'image/png',
                'post_title'     => 'Hexa Delete Sample Media ' . $run_id . ' #' . $index,
                'post_status'    => 'inherit',
            ],
            $file_path,
            $post_id,
            true
        );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $file_path );
            return $attachment_id;
        }

        $metadata = wp_generate_attachment_metadata( (int) $attachment_id, $file_path );
        if ( is_array( $metadata ) ) {
            wp_update_attachment_metadata( (int) $attachment_id, $metadata );
        }
        set_post_thumbnail( $post_id, (int) $attachment_id );

        return [
            'id'   => (int) $attachment_id,
            'url'  => $file_url,
            'path' => $file_path,
        ];
    }
}
