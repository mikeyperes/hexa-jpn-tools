<?php

namespace Hexa\PluginCore\SchemaTools;

/**
 * The page a schema structure is built for: what kind of page it is, the
 * WordPress object it shows and its address.
 *
 * Structure builders take a context instead of reading the global query, so
 * the same builder serves the live page, an admin preview and a test.
 */
final class SchemaContext {
    public const FRONT_PAGE = 'front_page';
    public const SINGULAR   = 'singular';
    public const TERM       = 'term';
    public const AUTHOR     = 'author';
    public const ARCHIVE    = 'post_type_archive';
    public const POSTS_HOME = 'home';
    public const OTHER      = 'other';

    public function __construct(
        public readonly string $kind,
        public readonly ?object $object = null,
        public readonly string $url = ''
    ) {
    }

    /** The page being viewed. */
    public static function current(): self {
        $front_id = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
        if ( is_front_page() ) {
            $post = $front_id > 0 ? get_post( $front_id ) : null;
            return new self( self::FRONT_PAGE, $post instanceof \WP_Post ? $post : null, SchemaIds::home() );
        }
        if ( is_singular() ) {
            $post = get_queried_object();
            return $post instanceof \WP_Post ? new self( self::SINGULAR, $post, (string) get_permalink( $post ) ) : new self( self::OTHER );
        }
        $object = get_queried_object();
        if ( $object instanceof \WP_Term ) {
            $link = get_term_link( $object );
            return new self( self::TERM, $object, is_wp_error( $link ) ? '' : (string) $link );
        }
        if ( $object instanceof \WP_User ) {
            return new self( self::AUTHOR, $object, (string) get_author_posts_url( $object->ID ) );
        }
        if ( $object instanceof \WP_Post_Type ) {
            return new self( self::ARCHIVE, $object, (string) get_post_type_archive_link( $object->name ) );
        }
        if ( is_home() ) {
            return new self( self::POSTS_HOME, null, SchemaSources::current_url() );
        }
        return new self( self::OTHER, null, SchemaSources::current_url() );
    }

    /** A post's own page; the static front page counts as the front page. */
    public static function for_post( int|\WP_Post $post ): ?self {
        $post = get_post( $post );
        if ( ! $post instanceof \WP_Post ) {
            return null;
        }
        $front_id = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
        if ( $front_id > 0 && $front_id === (int) $post->ID ) {
            return new self( self::FRONT_PAGE, $post, SchemaIds::home() );
        }
        return new self( self::SINGULAR, $post, (string) get_permalink( $post ) );
    }

    public static function for_front_page(): self {
        $front_id = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
        $post = $front_id > 0 ? get_post( $front_id ) : null;
        return new self( self::FRONT_PAGE, $post instanceof \WP_Post ? $post : null, SchemaIds::home() );
    }

    /** A content type's list page. */
    public static function for_archive( string $post_type ): ?self {
        $object = get_post_type_object( $post_type );
        $link   = $object ? get_post_type_archive_link( $post_type ) : false;
        return $object && $link ? new self( self::ARCHIVE, $object, (string) $link ) : null;
    }

    public function is_front_page(): bool {
        return self::FRONT_PAGE === $this->kind;
    }

    /** The WordPress post the page shows (the front page's page included). */
    public function post(): ?\WP_Post {
        return $this->object instanceof \WP_Post ? $this->object : null;
    }

    public function post_id(): int {
        return null !== $this->post() ? (int) $this->post()->ID : 0;
    }

    /**
     * A single post's page, optionally of the given post types. The static
     * front page is not singular here; check is_front_page() for it.
     *
     * @param string|list<string> $post_types
     */
    public function is_singular( string|array $post_types = [] ): bool {
        if ( self::SINGULAR !== $this->kind || null === $this->post() ) {
            return false;
        }
        $post_types = (array) $post_types;
        return [] === $post_types || in_array( $this->post()->post_type, $post_types, true );
    }

    /** A content type's list page. */
    public function is_archive_of( string $post_type ): bool {
        return self::ARCHIVE === $this->kind && $this->object instanceof \WP_Post_Type && $this->object->name === $post_type;
    }
}
