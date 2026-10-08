<?php

namespace Hexa\PluginCore\SearchQuery;

/**
 * Host-declared labels that flag what kind of content a search result is,
 * for example "Press Release" versus "Site Content".
 *
 * Several hosts may register on one site; a later registration replaces the
 * same post type's label and a non-empty default. Unmapped post types fall
 * back to the default, then to the post type's own singular name. Elementor
 * Loop Items read the label through the "Result Type" dynamic tag, and each
 * loop item already carries WordPress's `type-{post_type}` class for styling.
 */
final class ResultTypeLabels {
    public const ELEMENTOR_TAG = 'hexa-result-type';

    private const MAX_LABEL_LENGTH = 60;

    /** @var array<string,string> */
    private static array $labels = [];

    private static string $default = '';

    private static bool $hooked = false;

    /** @param array<string,string> $labels post type => visitor-facing label */
    public static function register( array $labels, string $default = '' ): void {
        foreach ( $labels as $post_type => $label ) {
            $key = self::key( (string) $post_type );
            $label = self::clean( (string) $label );
            if ( '' !== $key && '' !== $label ) {
                self::$labels[ $key ] = $label;
            }
        }

        $default = self::clean( $default );
        if ( '' !== $default ) {
            self::$default = $default;
        }

        if ( ! self::$hooked && function_exists( 'add_action' ) ) {
            self::$hooked = true;
            add_action( 'elementor/dynamic_tags/register', [ self::class, 'register_elementor_tag' ] );
        }
    }

    public static function label_for( string $post_type ): string {
        $key = self::key( $post_type );
        if ( isset( self::$labels[ $key ] ) ) {
            return self::$labels[ $key ];
        }
        if ( '' !== self::$default ) {
            return self::$default;
        }

        $object = '' !== $key && function_exists( 'get_post_type_object' ) ? get_post_type_object( $key ) : null;

        return is_object( $object ) && isset( $object->labels->singular_name )
            ? self::clean( (string) $object->labels->singular_name )
            : '';
    }

    /** @param mixed $manager Elementor's dynamic tags manager. */
    public static function register_elementor_tag( $manager ): void {
        if ( is_object( $manager ) && method_exists( $manager, 'register' )
            && class_exists( '\\Elementor\\Core\\DynamicTags\\Tag' )
        ) {
            $manager->register( new ElementorResultTypeTag() );
        }
    }

    /** Test helper: forget every registered label. */
    public static function reset(): void {
        self::$labels = [];
        self::$default = '';
    }

    private static function key( string $value ): string {
        return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( trim( $value ) ) );
    }

    private static function clean( string $value ): string {
        $value = trim( (string) preg_replace( '/\s+/u', ' ', strip_tags( $value ) ) );

        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, self::MAX_LABEL_LENGTH ) : substr( $value, 0, self::MAX_LABEL_LENGTH );
    }
}
