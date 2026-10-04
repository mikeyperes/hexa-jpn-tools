<?php

namespace Hexa\PluginCore\Map;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\PublicComponents\ItemLightbox;

/**
 * Wires the `[hexa_map id="…"]` shortcode, background geocoding, and map
 * cache invalidation.
 *
 * Geocoding never runs during a visitor request: an hourly WP-Cron event
 * places up to `batch` new or changed addresses per profile, and an address
 * edit schedules a one-off run a minute later. Several host plugins may add
 * this module; hooks register once per request.
 */
final class MapModule implements ModuleInterface {
    public const SHORTCODE = 'hexa_map';
    public const CRON_HOOK = 'hexa_plugin_core_map_geocode';

    private static bool $registered = false;

    private static bool $dirty = false;

    public function register(): void {
        if ( self::$registered || ! function_exists( 'add_action' ) ) {
            return;
        }
        self::$registered = true;

        add_shortcode( self::SHORTCODE, [ $this, 'shortcode' ] );
        add_action( 'rest_api_init', [ MapDetails::class, 'register_routes' ] );
        ItemLightbox::component( 'map', [ MapRegistry::class, 'get' ] );
        add_action( self::CRON_HOOK, [ $this, 'geocode' ] );
        add_action( 'init', [ $this, 'schedule' ] );

        foreach ( [ 'user', 'post' ] as $type ) {
            foreach ( [ 'added', 'updated', 'deleted' ] as $change ) {
                add_action( "{$change}_{$type}_meta", [ $this, 'meta_changed' ], 10, 3 );
            }
        }
        foreach ( [ 'save_post', 'before_delete_post', 'trashed_post', 'untrashed_post', 'set_object_terms' ] as $hook ) {
            add_action( $hook, [ $this, 'post_changed' ] );
        }
        foreach ( [ 'profile_update', 'user_register', 'deleted_user', 'set_user_role' ] as $hook ) {
            add_action( $hook, [ $this, 'user_changed' ] );
        }
    }

    /** @param array<string,mixed>|string $attributes */
    public function shortcode( $attributes = [] ): string {
        $attributes = shortcode_atts( [ 'id' => '' ], is_array( $attributes ) ? $attributes : [], self::SHORTCODE );

        return ( new MapRenderer() )->render( (string) $attributes['id'] );
    }

    public function schedule(): void {
        if ( [] !== MapRegistry::all() && ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + 300, 'hourly', self::CRON_HOOK );
        }
    }

    /**
     * Places pending addresses for every profile (or one), `batch` per profile per run.
     *
     * @return array<string,array{placed:int,missed:int,remaining:int}>
     */
    public function geocode( string $profile_id = '' ): array {
        $results = [];
        foreach ( MapRegistry::all() as $id => $profile ) {
            if ( '' === $profile_id || $profile_id === $id ) {
                $results[ $id ] = MapLocations::geocode_pending( $profile );
            }
        }

        return $results;
    }

    /** @param int|int[] $meta_id @param int|string $object_id */
    public function meta_changed( $meta_id, $object_id, $meta_key ): void {
        $type = str_ends_with( current_filter(), '_user_meta' ) ? 'users' : 'posts';
        foreach ( MapRegistry::all() as $profile ) {
            if ( 'posts' === $type && in_array( (string) get_post_type( (int) $object_id ), $profile['related_post_types'], true ) ) {
                $this->changed();
            }
            if ( $profile['source'] !== $type || $meta_key === $profile['geo_meta'] ) {
                continue;
            }
            if ( is_string( $profile['address'] ) && $meta_key === $profile['address'] ) {
                // Place the new address soon, outside this request.
                $next = wp_next_scheduled( self::CRON_HOOK );
                if ( false === $next || $next > time() + 120 ) {
                    wp_schedule_single_event( time() + 60, self::CRON_HOOK );
                }
                $this->changed();
            } elseif ( is_array( $profile['group'] ) && $meta_key === $profile['group']['meta_key'] ) {
                $this->changed();
            }
        }
    }

    /** @param int|string $post_id */
    public function post_changed( $post_id ): void {
        $type = (string) get_post_type( (int) $post_id );
        foreach ( MapRegistry::all() as $profile ) {
            if ( in_array( $type, array_merge( $profile['post_types'], $profile['related_post_types'] ), true ) ) {
                $this->changed();
                return;
            }
        }
    }

    public function user_changed(): void {
        foreach ( MapRegistry::all() as $profile ) {
            if ( 'users' === $profile['source'] ) {
                $this->changed();
                return;
            }
        }
    }

    /** Starts a new payload generation once, after the request, so every write of this save is included. */
    private function changed(): void {
        if ( ! self::$dirty ) {
            self::$dirty = true;
            add_action( 'shutdown', [ MapLocations::class, 'bump' ] );
        }
    }
}
