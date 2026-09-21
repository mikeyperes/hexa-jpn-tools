<?php

namespace Hexa\PluginCore\MediaUploads;

/**
 * Stores one already-authorized upload in the WordPress Media Library.
 */
final class WordPressImageUploader {
    public function __construct( private ?ImageUploadPolicy $policy = null ) {
        $this->policy = $this->policy ?? new ImageUploadPolicy();
    }

    /**
     * The host must verify its capability and nonce before calling this method.
     *
     * @return array{success:bool,attachment_id?:int,url?:string,mime_type?:string,size_bytes?:int,errors?:array<int,string>}
     */
    public function store( string $field_name, int $parent_post_id = 0 ): array {
        $file = isset( $_FILES[ $field_name ] ) && is_array( $_FILES[ $field_name ] ) ? $_FILES[ $field_name ] : [];
        $validation = $this->policy->validate( $file );
        if ( ! $validation['valid'] ) {
            return [ 'success' => false, 'errors' => $validation['errors'] ];
        }

        if ( ! function_exists( 'media_handle_upload' ) && defined( 'ABSPATH' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }
        if ( ! function_exists( 'media_handle_upload' ) ) {
            return [ 'success' => false, 'errors' => [ 'WordPress media handling is unavailable.' ] ];
        }

        $attachment_id = media_handle_upload( $field_name, max( 0, $parent_post_id ) );
        if ( function_exists( 'is_wp_error' ) && is_wp_error( $attachment_id ) ) {
            return [ 'success' => false, 'errors' => [ (string) $attachment_id->get_error_message() ] ];
        }

        $attachment_id = (int) $attachment_id;
        $url = function_exists( 'wp_get_attachment_url' ) ? (string) wp_get_attachment_url( $attachment_id ) : "";
        return [
            'success' => $attachment_id > 0,
            'attachment_id' => $attachment_id,
            'url' => $url,
            'mime_type' => $validation['mime_type'],
            'size_bytes' => $validation['size_bytes'],
        ];
    }
}
