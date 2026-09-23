<?php

namespace Hexa\PluginCore\Calendar;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\PublicComponents\PublicComponent;

/**
 * Wires the `[hexa_calendar id="…"]` shortcode, the public read-only REST
 * endpoint `GET /wp-json/hexa-plugin-core/v1/calendar/{profile}`, and month
 * cache invalidation when calendar posts, their dates, or their terms change.
 *
 * Several host plugins may add this module; hooks register once per request.
 */
final class CalendarModule implements ModuleInterface {
    public const SHORTCODE = 'hexa_calendar';

    private static bool $registered = false;

    private static bool $dirty = false;

    public function register(): void {
        if ( self::$registered || ! function_exists( 'add_action' ) ) {
            return;
        }
        self::$registered = true;

        add_shortcode( self::SHORTCODE, [ $this, 'shortcode' ] );
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );

        foreach ( [ 'save_post', 'before_delete_post', 'trashed_post', 'untrashed_post', 'set_object_terms' ] as $hook ) {
            add_action( $hook, [ $this, 'post_changed' ] );
        }
        foreach ( [ 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ] as $hook ) {
            add_action( $hook, [ $this, 'meta_changed' ], 10, 3 );
        }
    }

    /** @param array<string,mixed>|string $attributes */
    public function shortcode( $attributes = [] ): string {
        $attributes = shortcode_atts( [ 'id' => '' ], is_array( $attributes ) ? $attributes : [], self::SHORTCODE );

        return ( new CalendarRenderer() )->render( (string) $attributes['id'] );
    }

    public function register_routes(): void {
        register_rest_route(
            PublicComponent::REST_NAMESPACE,
            '/calendar/(?P<profile>[a-z0-9_\-]+)',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'rest_month' ],
                'permission_callback' => [ $this, 'rest_permission' ],
            ]
        );
    }

    /** @param \WP_REST_Request $request */
    public function rest_permission( $request ): bool {
        $profile = CalendarRegistry::get( (string) $request['profile'] );

        return null !== $profile && PublicComponent::can_view( $profile['public'] );
    }

    /** @param \WP_REST_Request $request */
    public function rest_month( $request ) {
        $profile = CalendarRegistry::get( (string) $request['profile'] );
        if ( null === $profile ) {
            return new \WP_Error( 'hexa_calendar_not_found', 'Unknown calendar.', [ 'status' => 404 ] );
        }

        $params = (array) $request->get_query_params();
        $base   = CalendarRenderer::sanitize_base( PublicComponent::scalar( $params[ PublicComponent::PARAM_BASE ] ?? '/', '/' ) );

        return PublicComponent::rest_response( ( new CalendarRenderer() )->month( $profile, CalendarRequest::from_input( $params, $profile ), $base ), $profile['public'] );
    }

    /** @param int|string $post_id */
    public function post_changed( $post_id ): void {
        if ( ! self::$dirty && in_array( (string) get_post_type( (int) $post_id ), $this->watched()['post_types'], true ) ) {
            self::$dirty = true;
            // Purge cached calendar pages with this response (no-op without LiteSpeed Cache), and start a new
            // month-cache generation after the request, so every date, term, and meta write of this save is included.
            do_action( 'litespeed_purge', CalendarRenderer::CACHE_TAG );
            add_action( 'shutdown', [ $this, 'bump' ] );
        }
    }

    /** @param int|int[] $meta_id @param int|string $post_id */
    public function meta_changed( $meta_id, $post_id, $meta_key ): void {
        if ( ! self::$dirty && in_array( (string) $meta_key, $this->watched()['meta_keys'], true ) ) {
            $this->post_changed( $post_id );
        }
    }

    /** Starts a new month-cache generation; entries of older generations are never read again and expire by TTL. */
    public function bump(): void {
        update_option( CalendarRenderer::GENERATION_OPTION, sprintf( '%.6F', microtime( true ) ), true );
    }

    /** @return array{post_types:string[],meta_keys:string[]} */
    private function watched(): array {
        static $watched = null;
        if ( null !== $watched ) {
            return $watched;
        }

        $watched = [ 'post_types' => [], 'meta_keys' => [] ];
        foreach ( CalendarRegistry::all() as $profile ) {
            $watched['post_types'] = array_merge( $watched['post_types'], $profile['post_types'] );
            foreach ( [ $profile['start'], $profile['end'] ] as $field ) {
                if ( is_array( $field ) && '' !== $field['meta_key'] ) {
                    $watched['meta_keys'][] = $field['meta_key'];
                }
            }
            foreach ( $profile['filters'] as $filter ) {
                if ( '' !== (string) ( $filter['meta_key'] ?? '' ) ) {
                    $watched['meta_keys'][] = (string) $filter['meta_key'];
                }
            }
            if ( is_array( $profile['link'] ) && isset( $profile['link']['meta_key'] ) ) {
                $watched['meta_keys'][] = $profile['link']['meta_key'];
            }
        }
        $watched = array_map( static fn( array $values ): array => array_values( array_unique( $values ) ), $watched );

        return $watched;
    }
}
