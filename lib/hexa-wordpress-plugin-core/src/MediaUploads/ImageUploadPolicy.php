<?php

namespace Hexa\PluginCore\MediaUploads;

/**
 * Reusable image limits for public and administrator upload forms.
 */
final class ImageUploadPolicy {
    private const EXTENSIONS = [
        'image/jpeg' => [ 'jpg', 'jpeg' ],
        'image/png' => [ 'png' ],
        'image/webp' => [ 'webp' ],
    ];

    /** @param array<int,string> $mime_types */
    public function __construct(
        private int $max_bytes = 10485760,
        private array $mime_types = [ 'image/jpeg', 'image/png', 'image/webp' ]
    ) {
        $this->max_bytes = max( 1, $this->max_bytes );
        $this->mime_types = array_values( array_intersect( array_keys( self::EXTENSIONS ), array_unique( $this->mime_types ) ) );
    }

    /**
     * @param array<string,mixed> $file
     * @return array{valid:bool,errors:array<int,string>,mime_type:string,size_bytes:int}
     */
    public function validate( array $file ): array {
        $errors = [];
        $upload_error = (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE );
        $size = max( 0, (int) ( $file['size'] ?? 0 ) );
        $name = basename( (string) ( $file['name'] ?? '' ) );
        $mime_type = $this->detected_mime( $file );

        if ( UPLOAD_ERR_OK !== $upload_error ) {
            $errors[] = self::upload_error_message( $upload_error );
        }
        if ( $size < 1 ) {
            $errors[] = 'The selected image is empty.';
        } elseif ( $size > $this->max_bytes ) {
            $errors[] = 'The selected image exceeds the allowed size.';
        }
        if ( ! in_array( $mime_type, $this->mime_types, true ) ) {
            $errors[] = 'The selected file is not an allowed image type.';
        }

        $extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
        if ( isset( self::EXTENSIONS[ $mime_type ] ) && ! in_array( $extension, self::EXTENSIONS[ $mime_type ], true ) ) {
            $errors[] = 'The image extension does not match its detected type.';
        }

        return [
            'valid' => [] === $errors,
            'errors' => array_values( array_unique( $errors ) ),
            'mime_type' => $mime_type,
            'size_bytes' => $size,
        ];
    }

    public function accept_attribute(): string {
        return implode( ',', $this->mime_types );
    }

    public function max_bytes(): int {
        return $this->max_bytes;
    }

    /** @param array<string,mixed> $file */
    private function detected_mime( array $file ): string {
        $temporary_name = (string) ( $file['tmp_name'] ?? '' );
        if ( "" !== $temporary_name && is_readable( $temporary_name ) && class_exists( '\\finfo' ) ) {
            $detector = new \finfo( FILEINFO_MIME_TYPE );
            $detected = $detector->file( $temporary_name );
            if ( is_string( $detected ) ) {
                return strtolower( $detected );
            }
        }
        return strtolower( trim( (string) ( $file['type'] ?? '' ) ) );
    }

    private static function upload_error_message( int $error ): string {
        return match ( $error ) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The selected image exceeds the server upload limit.',
            UPLOAD_ERR_PARTIAL => 'The selected image was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No image was selected.',
            default => 'The selected image could not be uploaded.',
        };
    }
}
