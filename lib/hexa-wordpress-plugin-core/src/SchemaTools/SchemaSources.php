<?php

namespace Hexa\PluginCore\SchemaTools;

use Hexa\PluginCore\DataNormalization\ValueNormalizer;

/**
 * Reads standard WordPress data (posts, users, attachments, terms, the site
 * itself) into SchemaNodes. Plugin-specific fields stay in each plugin; this
 * class only covers what every WordPress site has.
 */
final class SchemaSources {
    /** User profile fields that commonly hold a public profile URL. */
    public const USER_PROFILE_URL_FIELDS = [
        'website', 'url_website', 'facebook_url', 'instagram_url', 'linkedin_url', 'twitter_url', 'x_url',
        'youtube_url', 'crunchbase_url', 'muckrack_url', 'url_facebook', 'url_instagram', 'url_linkedin',
        'url_x', 'url_twitter', 'url_youtube', 'url_crunchbase', 'url_muckrack', 'url_wikipedia', 'url_tiktok',
    ];

    public static function language(): string {
        $language = (string) get_bloginfo( 'language' );
        return '' !== $language ? $language : 'en-US';
    }

    /** The site's WebSite node, published by the given organization or person. */
    public static function website( string $publisher_id = '', array $properties = [] ): array {
        $home = SchemaIds::home();
        return SchemaNodes::website(
            $properties + [
                '@id'         => SchemaIds::website(),
                'url'         => $home,
                'name'        => ValueNormalizer::text( get_bloginfo( 'name' ) ),
                'description' => ValueNormalizer::text( get_bloginfo( 'description' ) ),
                'publisher'   => '' !== $publisher_id ? SchemaNodes::ref( $publisher_id ) : null,
                'inLanguage'  => self::language(),
                'search_url'  => $home . '?s={search_term_string}',
            ]
        );
    }

    /**
     * One media library image at its original upload size, so search
     * engines get the largest copy. Works for offloaded attachments too.
     */
    public static function attachment_image( int $attachment_id, string $id = '', array $properties = [] ): array {
        if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
            return [];
        }
        $url  = (string) wp_get_attachment_url( $attachment_id );
        $meta = wp_get_attachment_metadata( $attachment_id );
        $meta = is_array( $meta ) ? $meta : [];
        $image = SchemaNodes::image(
            [
                'url'     => $url,
                'width'   => (int) ( $meta['width'] ?? 0 ),
                'height'  => (int) ( $meta['height'] ?? 0 ),
                'caption' => (string) wp_get_attachment_caption( $attachment_id ),
                'alt'     => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
            ],
            $id
        );
        return [] === $image ? [] : SchemaGraph::clean( $properties + $image );
    }

    /** The post's featured image as its page's primary image. */
    public static function post_image( int $post_id, string $id = '' ): array {
        $attachment_id = (int) get_post_thumbnail_id( $post_id );
        return $attachment_id > 0 ? self::attachment_image( $attachment_id, $id, [ 'representativeOfPage' => true ] ) : [];
    }

    /**
     * A logo from an attachment ID, URL or image array, falling back to the site icon.
     */
    public static function logo( $value, string $id = '' ): array {
        $attachment_id = 0;
        $url = '';
        $width = 0;
        $height = 0;
        if ( is_array( $value ) ) {
            $url = SchemaGraph::web_url( $value['url'] ?? '' );
            $width = (int) ( $value['width'] ?? 0 );
            $height = (int) ( $value['height'] ?? 0 );
            $attachment_id = absint( $value['ID'] ?? $value['id'] ?? 0 );
        } elseif ( is_numeric( $value ) ) {
            $attachment_id = absint( $value );
        } elseif ( is_string( $value ) ) {
            $url = SchemaGraph::web_url( $value );
        }
        if ( ! $attachment_id && '' !== $url ) {
            $attachment_id = absint( attachment_url_to_postid( $url ) );
        }
        if ( $attachment_id && ( '' === $url || ! $width || ! $height ) ) {
            $image = self::attachment_image( $attachment_id, $id );
            if ( [] !== $image ) {
                return $image;
            }
        }
        if ( '' !== $url ) {
            return SchemaNodes::image( [ 'url' => $url, 'width' => $width, 'height' => $height ], $id );
        }
        $icon_id = absint( get_option( 'site_icon' ) );
        return $icon_id ? self::attachment_image( $icon_id, $id ) : [];
    }

    /**
     * A WordPress user as a Person, from the user's standard profile.
     *
     * @param array<string,mixed> $properties Extra or overriding properties.
     */
    public static function user_person( int $user_id, string $id = '', array $properties = [] ): array {
        $user = $user_id > 0 ? get_userdata( $user_id ) : false;
        if ( ! $user ) {
            return [];
        }
        $url = (string) get_author_posts_url( $user_id );
        $title = self::user_field( $user_id, 'title' ) ?: self::user_field( $user_id, 'job_title' );
        return SchemaNodes::person(
            $properties + [
                '@id'         => '' !== $id ? $id : SchemaIds::for_url( $url, 'person' ),
                'name'        => ValueNormalizer::text( $user->display_name ),
                'url'         => $url,
                'image'       => self::avatar_url( $user_id ),
                'description' => (string) get_the_author_meta( 'description', $user_id ),
                'jobTitle'    => ValueNormalizer::text( is_scalar( $title ) ? $title : '' ),
                'sameAs'      => self::user_same_as( $user_id ),
            ]
        );
    }

    /** @return list<string> */
    public static function user_same_as( int $user_id ): array {
        if ( $user_id <= 0 ) {
            return [];
        }
        $values = [];
        foreach ( self::USER_PROFILE_URL_FIELDS as $field ) {
            $values[] = self::user_field( $user_id, $field );
        }
        $urls = self::user_field( $user_id, 'urls' );
        if ( is_array( $urls ) ) {
            $values[] = array_values( $urls );
        }
        $values[] = (string) get_the_author_meta( 'user_url', $user_id );
        return SchemaNodes::same_as( ...$values );
    }

    /** Home, first category, post. */
    public static function post_breadcrumb( int $post_id, string $id = '' ): array {
        $items = [ [ 'name' => get_bloginfo( 'name' ), 'url' => SchemaIds::home() ] ];
        $categories = 'post' === get_post_type( $post_id ) ? get_the_category( $post_id ) : [];
        if ( ! empty( $categories ) ) {
            $items[] = [ 'name' => $categories[0]->name, 'url' => get_category_link( $categories[0] ) ];
        } else {
            $archive = get_post_type_archive_link( (string) get_post_type( $post_id ) );
            $object = get_post_type_object( (string) get_post_type( $post_id ) );
            if ( $archive && $object && $archive !== SchemaIds::home() ) {
                $items[] = [ 'name' => $object->labels->name, 'url' => $archive ];
            }
        }
        foreach ( array_reverse( get_post_ancestors( $post_id ) ) as $ancestor ) {
            $items[] = [ 'name' => get_the_title( $ancestor ), 'url' => get_permalink( $ancestor ) ];
        }
        $items[] = [ 'name' => get_the_title( $post_id ), 'url' => get_permalink( $post_id ) ];
        return SchemaNodes::breadcrumb( $items, $id );
    }

    /** Home, then the current archive (term with its parents, author, or post type). */
    public static function archive_breadcrumb( string $id = '' ): array {
        $items = [ [ 'name' => get_bloginfo( 'name' ), 'url' => SchemaIds::home() ] ];
        $object = get_queried_object();
        if ( $object instanceof \WP_Term ) {
            foreach ( array_reverse( get_ancestors( $object->term_id, $object->taxonomy, 'taxonomy' ) ) as $ancestor ) {
                $term = get_term( $ancestor, $object->taxonomy );
                if ( $term instanceof \WP_Term ) {
                    $items[] = [ 'name' => $term->name, 'url' => get_term_link( $term ) ];
                }
            }
            $link = get_term_link( $object );
            $items[] = [ 'name' => $object->name, 'url' => is_wp_error( $link ) ? '' : $link ];
        } elseif ( $object instanceof \WP_User ) {
            $items[] = [ 'name' => $object->display_name, 'url' => get_author_posts_url( $object->ID ) ];
        } elseif ( $object instanceof \WP_Post_Type ) {
            $items[] = [ 'name' => $object->labels->name, 'url' => get_post_type_archive_link( $object->name ) ];
        }
        return SchemaNodes::breadcrumb( $items, $id );
    }

    /** The URL of the page being viewed, without query string. */
    public static function current_url(): string {
        if ( is_front_page() || is_home() && ! get_option( 'page_for_posts' ) ) {
            return SchemaIds::home();
        }
        if ( is_singular() ) {
            return (string) get_permalink( get_queried_object_id() );
        }
        $object = get_queried_object();
        if ( $object instanceof \WP_Term ) {
            $link = get_term_link( $object );
            return is_wp_error( $link ) ? '' : (string) $link;
        }
        if ( $object instanceof \WP_User ) {
            return (string) get_author_posts_url( $object->ID );
        }
        if ( $object instanceof \WP_Post_Type ) {
            return (string) get_post_type_archive_link( $object->name );
        }
        if ( is_home() ) {
            return (string) get_permalink( (int) get_option( 'page_for_posts' ) );
        }
        return '';
    }

    /** A name for the archive being viewed. */
    public static function archive_title(): string {
        $object = get_queried_object();
        if ( $object instanceof \WP_Term ) {
            return ValueNormalizer::text( $object->name );
        }
        if ( $object instanceof \WP_User ) {
            return ValueNormalizer::text( $object->display_name );
        }
        if ( $object instanceof \WP_Post_Type ) {
            return ValueNormalizer::text( $object->labels->name );
        }
        return ValueNormalizer::text( wp_get_document_title() );
    }

    /** The post's excerpt, or the first words of its text. */
    public static function post_description( \WP_Post $post, int $words = 30 ): string {
        $text = has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( self::post_text( $post ), $words );
        return ValueNormalizer::text( $text );
    }

    /** The post's plain text without tags or shortcodes. */
    public static function post_text( \WP_Post $post ): string {
        $text = wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );
        return trim( (string) preg_replace( '/\s+/', ' ', $text ) );
    }

    public static function word_count( string $text ): int {
        return '' === $text ? 0 : count( preg_split( '/\s+/', $text ) ?: [] );
    }

    /** @return list<string> */
    public static function term_names( int $post_id, string $taxonomy ): array {
        $terms = get_the_terms( $post_id, $taxonomy );
        if ( ! is_array( $terms ) ) {
            return [];
        }
        return array_values( array_filter( array_map( static fn( $term ): string => ValueNormalizer::text( $term->name ?? '' ), $terms ) ) );
    }

    /** @return list<array<string,mixed>> */
    public static function term_things( int $post_id, string $taxonomy ): array {
        $terms = get_the_terms( $post_id, $taxonomy );
        if ( ! is_array( $terms ) ) {
            return [];
        }
        $things = [];
        foreach ( $terms as $term ) {
            $link = get_term_link( $term, $taxonomy );
            $thing = SchemaNodes::thing( (string) $term->name, is_wp_error( $link ) ? '' : (string) $link );
            if ( [] !== $thing ) {
                $things[] = $thing;
            }
        }
        return $things;
    }

    /**
     * The WebPage node for a post, linked to the site and its images.
     *
     * @param array<string,mixed> $properties Extra or overriding properties.
     */
    public static function post_page( \WP_Post $post, string $type = 'WebPage', array $properties = [] ): array {
        $url = (string) get_permalink( $post );
        return SchemaNodes::web_page(
            $properties + [
                '@id'           => SchemaIds::webpage( $url ),
                'url'           => $url,
                'name'          => ValueNormalizer::text( get_the_title( $post ) ),
                'description'   => self::post_description( $post ),
                'isPartOf'      => SchemaNodes::ref( SchemaIds::website() ),
                'datePublished' => get_the_date( DATE_W3C, $post ),
                'dateModified'  => get_the_modified_date( DATE_W3C, $post ),
                'inLanguage'    => self::language(),
            ],
            $type
        );
    }

    public static function avatar_url( int $user_id ): string {
        try {
            $avatar = get_avatar_url( $user_id, [ 'size' => 512 ] );
            return is_string( $avatar ) ? $avatar : '';
        } catch ( \Throwable $e ) {
            return '';
        }
    }

    /** @return mixed */
    private static function user_field( int $user_id, string $field ) {
        if ( class_exists( '\\Hexa\\PluginCore\\Fields\\Field' ) && \Hexa\PluginCore\Fields\Field::available() ) {
            $value = \Hexa\PluginCore\Fields\Field::get( $field, 'user_' . $user_id );
            if ( ValueNormalizer::present( $value ) ) {
                return $value;
            }
        }
        return get_user_meta( $user_id, $field, true );
    }
}
