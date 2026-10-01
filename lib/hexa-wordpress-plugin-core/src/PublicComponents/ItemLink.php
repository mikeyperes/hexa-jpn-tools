<?php

namespace Hexa\PluginCore\PublicComponents;

/**
 * What a click on an item link inside a public component (calendar, map) does.
 *
 * A profile sets `link_behavior` to `page` (follow the link, the default),
 * `new_tab` (open it in a new tab), or `lightbox` (show the linked post in an
 * in-page dialog, see ItemLightbox). `lightbox` takes optional settings:
 *
 *     'lightbox' => [
 *         'post_types' => [ 'event' ],            // which linked posts open in the dialog
 *         'render'     => fn( int $post_id ): string, // dialog body markup (escaped); default: image, title, excerpt
 *         'assets'     => fn(): void,             // enqueue styles the render markup needs
 *         'page_link'  => true,                   // show the dialog's "Open full page" link
 *     ]
 *
 * In `lightbox` mode, links to anything else (another post type, a user, an
 * outside URL) keep following the link, and every lightbox link keeps its
 * real href, so it still works without JavaScript or with a modifier click.
 */
final class ItemLink {
    public const MODES = [ 'page', 'new_tab', 'lightbox' ];

    /** Dialog wording; profiles override it through their `labels`. */
    public const LABELS = [
        'lightbox_close'   => 'Close',
        'lightbox_open'    => 'Open full page',
        'lightbox_loading' => 'Loading…',
        'lightbox_error'   => 'This could not load.',
    ];

    /**
     * @param array<string,mixed> $config        Raw profile config.
     * @param string[]            $default_types Post types the dialog shows when the profile names none.
     * @return array{mode:string,post_types:string[],render:?callable,assets:?callable,page_link:bool}
     */
    public static function normalize( array $config, array $default_types = [] ): array {
        // Profiles written before link_behavior existed asked for a new tab with link_target '_blank'.
        $legacy = '_blank' === ( $config['link_target'] ?? '' ) ? 'new_tab' : 'page';
        $mode   = ProfileValues::choice( $config['link_behavior'] ?? $legacy, self::MODES, 'page' );
        $box    = (array) ( $config['lightbox'] ?? [] );
        $types  = ProfileValues::keys( (array) ( $box['post_types'] ?? $default_types ) );

        return [
            'mode'       => 'lightbox' === $mode && [] === $types ? 'page' : $mode,
            'post_types' => $types,
            'render'     => ProfileValues::callback( $box['render'] ?? null ),
            'assets'     => ProfileValues::callback( $box['assets'] ?? null ),
            'page_link'  => (bool) ( $box['page_link'] ?? true ),
        ];
    }

    /**
     * Attributes for one item link: a new-tab target, the lightbox endpoint for a post the
     * dialog may show, or nothing.
     *
     * @param array<string,mixed> $profile   Normalized profile with `id` and `link_behavior`.
     * @param string              $component Component name registered with ItemLightbox ('calendar', 'map').
     */
    public static function attributes( array $profile, string $component, int $post_id ): string {
        $link = $profile['link_behavior'];
        if ( 'new_tab' === $link['mode'] ) {
            return ' target="_blank" rel="noopener"';
        }
        if ( 'lightbox' !== $link['mode'] || ! ItemLightbox::eligible( $link, $post_id ) ) {
            return '';
        }

        return ' data-hlb="' . esc_url( PublicComponent::endpoint( 'lightbox/' . $component . '/' . $profile['id'] . '/' . $post_id ) ) . '"';
    }

    /** Attributes for the component root: the dialog's wording and options (and a REST nonce for a private profile). */
    public static function root_attributes( array $profile ): string {
        if ( 'lightbox' !== $profile['link_behavior']['mode'] ) {
            return '';
        }
        $labels = [ 'page' => $profile['link_behavior']['page_link'] ];
        foreach ( self::LABELS as $key => $default ) {
            $labels[ substr( $key, 9 ) ] = (string) ( $profile['labels'][ $key ] ?? $default );
        }
        $html = ' data-hlb-labels="' . esc_attr( (string) wp_json_encode( $labels ) ) . '"';
        if ( ! $profile['public'] && function_exists( 'wp_create_nonce' ) ) {
            $html .= ' data-hlb-nonce="' . esc_attr( wp_create_nonce( 'wp_rest' ) ) . '"';
        }

        return $html;
    }

    /** The post behind a same-site URL, for links whose source does not supply an ID. */
    public static function post_id( string $url ): int {
        return '' !== $url && function_exists( 'url_to_postid' ) ? (int) url_to_postid( $url ) : 0;
    }
}
