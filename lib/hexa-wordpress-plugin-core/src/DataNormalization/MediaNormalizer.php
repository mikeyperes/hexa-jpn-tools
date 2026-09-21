<?php

declare( strict_types=1 );

namespace Hexa\PluginCore\DataNormalization;

/** Normalizes common ACF/WordPress media values into stable image arrays. */
final class MediaNormalizer {
    /** @var array<string,array<string,mixed>> */
    private static array $image_cache = [];

    public static function clear_cache(): void {
        self::$image_cache = [];
    }

    public static function attachment_id( mixed $value ): int {
        if ( is_object( $value ) && isset( $value->ID ) ) {
            return self::absint( $value->ID );
        }
        if ( is_array( $value ) ) {
            return self::absint( $value['ID'] ?? $value['id'] ?? 0 );
        }
        if ( is_numeric( $value ) ) {
            return self::absint( $value );
        }
        $url = ValueNormalizer::url( $value );
        return '' !== $url && function_exists( 'attachment_url_to_postid' ) ? self::absint( attachment_url_to_postid( $url ) ) : 0;
    }

    /** @return array<string,mixed> */
    public static function image( mixed $value, string $size = 'full', bool $derive_url_title = false ): array {
        $size = '' !== trim( $size ) ? $size : 'full';
        $id = self::attachment_id( $value );
        $cache_key = $id > 0 && ! is_array( $value ) ? $id . ':' . $size . ':' . (int) $derive_url_title : '';
        if ( '' !== $cache_key && array_key_exists( $cache_key, self::$image_cache ) ) {
            return self::$image_cache[ $cache_key ];
        }

        $fallback_value = $value;
        if ( is_array( $value ) ) {
            $sized_url = is_array( $value['sizes'] ?? null ) ? ( $value['sizes'][ $size ] ?? null ) : null;
            $fallback_value = $value['url'] ?? $sized_url ?? $value['full_url'] ?? '';
        }
        $fallback_url = ValueNormalizer::url( $fallback_value );
        $source = $id > 0 && function_exists( 'wp_get_attachment_image_src' ) ? wp_get_attachment_image_src( $id, $size ) : false;
        $full = $id > 0 && function_exists( 'wp_get_attachment_image_src' ) ? wp_get_attachment_image_src( $id, 'full' ) : false;
        $source_url = is_array( $source ) ? ValueNormalizer::url( $source[0] ?? '' ) : '';
        $full_source_url = is_array( $full ) ? ValueNormalizer::url( $full[0] ?? '' ) : '';
        $url = $source_url ?: $full_source_url ?: $fallback_url;
        if ( '' === $url ) {
            if ( '' !== $cache_key ) {
                self::$image_cache[ $cache_key ] = [];
            }
            return [];
        }
        $alt = is_array( $value ) && array_key_exists( 'alt', $value ) && null !== $value['alt']
            ? ValueNormalizer::text( $value['alt'] )
            : ( $id > 0 && function_exists( 'get_post_meta' ) ? ValueNormalizer::text( get_post_meta( $id, '_wp_attachment_image_alt', true ) ) : '' );
        $title = is_array( $value ) && array_key_exists( 'title', $value ) && null !== $value['title']
            ? ValueNormalizer::text( $value['title'] )
            : ( $id > 0 && function_exists( 'get_the_title' ) ? ValueNormalizer::text( get_the_title( $id ) ) : '' );
        $caption = is_array( $value ) && array_key_exists( 'caption', $value ) && null !== $value['caption']
            ? ValueNormalizer::text( $value['caption'] )
            : ( $id > 0 && function_exists( 'wp_get_attachment_caption' ) ? ValueNormalizer::text( wp_get_attachment_caption( $id ) ) : '' );
        if ( '' === $title && $derive_url_title ) {
            $title = basename( (string) parse_url( $url, PHP_URL_PATH ) );
        }
        $input_full_url = is_array( $value ) ? ValueNormalizer::url( $value['full_url'] ?? '' ) : '';
        $image = array_filter( [
            'id'       => $id,
            'url'      => $url,
            'full_url' => $full_source_url ?: $input_full_url ?: $url,
            'width'    => is_array( $source ) ? self::absint( $source[1] ?? 0 ) : 0,
            'height'   => is_array( $source ) ? self::absint( $source[2] ?? 0 ) : 0,
            'alt'      => $alt,
            'title'    => $title,
            'caption'  => $caption,
        ], static fn( mixed $item ): bool => ValueNormalizer::present( $item ) );
        if ( '' !== $cache_key ) {
            self::$image_cache[ $cache_key ] = $image;
        }
        return $image;
    }

    /** @return list<array<string,mixed>> */
    public static function gallery(
        mixed $value,
        string $size = 'full',
        bool $derive_url_titles = false,
        bool $keep_first_duplicate = false
    ): array {
        $size = '' !== trim( $size ) ? $size : 'full';
        if ( function_exists( 'maybe_unserialize' ) ) {
            $value = maybe_unserialize( $value );
        }
        if ( is_string( $value ) && '' !== trim( $value ) ) {
            $decoded = json_decode( $value, true );
            if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
                $value = $decoded;
            }
        }
        if ( ! is_array( $value ) ) {
            $urls = ValueNormalizer::urls( $value );
            $value = [] !== $urls ? $urls : [ $value ];
        }
        $images = [];
        foreach ( $value as $item ) {
            $image = self::image( $item, $size, $derive_url_titles );
            if ( [] !== $image ) {
                if ( $keep_first_duplicate && isset( $images[ $image['url'] ] ) ) {
                    continue;
                }
                $images[ $image['url'] ] = $image;
            }
        }
        return array_values( $images );
    }

    /**
     * Return the stable six-key image shape used by legacy host callbacks.
     *
     * @return array{id:int,url:string,full_url:string,alt:string,title:string,caption:string}|null
     */
    public static function attachment_image_record( mixed $attachment_id, string $size = 'large' ): ?array {
        $attachment_id = self::absint( $attachment_id );
        if ( $attachment_id <= 0 ) {
            return null;
        }
        $image = self::image( $attachment_id, '' !== $size ? $size : 'large' );
        return [] === $image ? null : self::image_record( $image );
    }

    /**
     * Normalize loose gallery fields to stable six-key records, retaining the
     * first occurrence of each URL for compatibility with procedural hosts.
     *
     * @return list<array{id:int,url:string,full_url:string,alt:string,title:string,caption:string}>
     */
    public static function gallery_records( mixed $value, string $size = 'large' ): array {
        if ( ! is_array( $value ) && ! is_string( $value ) ) {
            return [];
        }
        return array_map(
            static fn( array $image ): array => self::image_record( $image ),
            self::gallery( $value, '' !== $size ? $size : 'large', true, true )
        );
    }

    /** @return array<string,mixed> */
    public static function schema_image( array $image, string $id ): array {
        if ( empty( $image['url'] ) ) {
            return [];
        }
        return array_filter( [
            '@type'       => 'ImageObject',
            '@id'         => $id,
            'url'         => $image['full_url'] ?? $image['url'],
            'contentUrl'  => $image['full_url'] ?? $image['url'],
            'width'       => $image['width'] ?? null,
            'height'      => $image['height'] ?? null,
            'caption'     => $image['caption'] ?? $image['title'] ?? null,
            'description' => $image['alt'] ?? null,
        ], static fn( mixed $item ): bool => ValueNormalizer::present( $item ) );
    }

    private static function absint( mixed $value ): int {
        return function_exists( 'absint' ) ? absint( $value ) : abs( (int) $value );
    }

    /**
     * @param array<string,mixed> $image
     * @return array{id:int,url:string,full_url:string,alt:string,title:string,caption:string}
     */
    private static function image_record( array $image ): array {
        return [
            'id'       => self::absint( $image['id'] ?? 0 ),
            'url'      => (string) ( $image['url'] ?? '' ),
            'full_url' => (string) ( $image['full_url'] ?? $image['url'] ?? '' ),
            'alt'      => (string) ( $image['alt'] ?? '' ),
            'title'    => (string) ( $image['title'] ?? '' ),
            'caption'  => (string) ( $image['caption'] ?? '' ),
        ];
    }
}
