<?php

namespace Hexa\PluginCore\Map;

/**
 * Reads a profile's items, their addresses, and their stored coordinates, and
 * geocodes items whose address is new or has changed.
 *
 * Coordinates live on the item itself (post meta or user meta, key
 * `geo_meta`) with a hash of the address they came from, so an edited
 * address is detected without extra bookkeeping. An address no service could
 * place is remembered as a miss and not retried until it changes.
 */
final class MapLocations {
    /** Option bumped whenever map content changes; cached payloads of older generations are never read again. */
    public const GENERATION_OPTION = 'hexa_plugin_core_map_generation';

    /** @param array<string,mixed> $profile @return int[] Published posts or users with the profile's roles, oldest first. */
    public static function item_ids( array $profile ): array {
        if ( 'users' === $profile['source'] ) {
            $ids = get_users( [ 'role__in' => $profile['roles'], 'fields' => 'ID', 'number' => MapProfile::MAX_ITEMS, 'orderby' => 'ID', 'order' => 'ASC' ] );
        } else {
            $ids = get_posts( [ 'post_type' => $profile['post_types'], 'post_status' => 'publish', 'has_password' => false, 'fields' => 'ids', 'numberposts' => MapProfile::MAX_ITEMS, 'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => true ] );
        }
        $ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
        self::prime( $profile, $ids );

        return $ids;
    }

    /** @param array<string,mixed> $profile */
    public static function address( array $profile, int $id ): string {
        $value = is_callable( $profile['address'] ) ? call_user_func( $profile['address'], $id ) : self::meta( $profile, $id, (string) $profile['address'] );

        return MapGeocoder::normalize( is_scalar( $value ) ? (string) $value : '' );
    }

    public static function hash( string $address ): string {
        return substr( sha1( strtolower( $address ) ), 0, 16 );
    }

    /**
     * Stored coordinates for the item's current address, or null when missing, stale, or a miss.
     *
     * @param array<string,mixed> $profile
     * @return array{lat:float,lng:float}|null
     */
    public static function coordinates( array $profile, int $id, string $address ): ?array {
        $geo = self::meta( $profile, $id, $profile['geo_meta'] );
        if ( ! is_array( $geo ) || ( $geo['h'] ?? '' ) !== self::hash( $address ) || ! isset( $geo['lat'], $geo['lng'] ) ) {
            return null;
        }

        return [ 'lat' => (float) $geo['lat'], 'lng' => (float) $geo['lng'] ];
    }

    /**
     * Items with an address whose coordinates are missing or stale (misses of the same address excluded).
     *
     * @param array<string,mixed> $profile
     * @return array<int,string> id => address
     */
    public static function pending( array $profile ): array {
        $pending = [];
        foreach ( self::item_ids( $profile ) as $id ) {
            $address = self::address( $profile, $id );
            if ( '' === $address ) {
                continue;
            }
            $geo = self::meta( $profile, $id, $profile['geo_meta'] );
            if ( ! is_array( $geo ) || ( $geo['h'] ?? '' ) !== self::hash( $address ) ) {
                $pending[ $id ] = $address;
            }
        }

        return $pending;
    }

    /**
     * Geocodes up to $limit pending items and stores the results.
     *
     * @param array<string,mixed> $profile
     * @return array{placed:int,missed:int,remaining:int}
     */
    public static function geocode_pending( array $profile, ?int $limit = null, ?MapGeocoder $geocoder = null ): array {
        $pending  = self::pending( $profile );
        $batch    = array_slice( $pending, 0, $limit ?? $profile['batch'], true );
        $geocoder = $geocoder ?? new MapGeocoder();
        $result   = [ 'placed' => 0, 'missed' => 0, 'remaining' => count( $pending ) - count( $batch ) ];

        foreach ( $batch as $id => $address ) {
            $point = $geocoder->geocode( $address, $profile['geocoders'], $profile['country'] );
            $geo   = [ 'h' => self::hash( $address ), 't' => time() ] + ( null !== $point ? [ 'lat' => round( $point['lat'], 6 ), 'lng' => round( $point['lng'], 6 ), 'src' => $point['src'] ] : [ 'miss' => 1 ] );
            self::store( $profile, (int) $id, $geo );
            $result[ null !== $point ? 'placed' : 'missed' ]++;
        }
        if ( [] !== $batch ) {
            self::bump();
        }

        return $result;
    }

    /**
     * Items on the map: id, title, url, group, coordinates, and the host's batch data.
     *
     * @param array<string,mixed> $profile
     * @return array<int,array{id:int,title:string,url:string,group:string,lat:float,lng:float,data:array<string,mixed>}>
     */
    public static function items( array $profile ): array {
        $located = [];
        foreach ( self::item_ids( $profile ) as $id ) {
            $address = self::address( $profile, $id );
            $point   = '' !== $address ? self::coordinates( $profile, $id, $address ) : null;
            if ( null !== $point ) {
                $located[ $id ] = $point + [ 'address' => $address ];
            }
            if ( count( $located ) >= $profile['max_items'] ) {
                break;
            }
        }

        $data  = null !== $profile['prepare'] && [] !== $located ? (array) call_user_func( $profile['prepare'], array_keys( $located ) ) : [];
        $items = [];
        foreach ( $located as $id => $point ) {
            $row = is_array( $data[ $id ] ?? null ) ? $data[ $id ] : [];
            $items[] = [
                'id'      => $id,
                'title'   => self::title( $profile, $id, $row ),
                'url'     => self::link( $profile, $id, $row ),
                'group'   => self::group( $profile, $id, $row ),
                'address' => $point['address'],
                'lat'     => $point['lat'],
                'lng'     => $point['lng'],
                'data'    => $row,
            ];
        }

        return $items;
    }

    public static function generation(): string {
        return function_exists( 'get_option' ) ? (string) get_option( self::GENERATION_OPTION, '0' ) : '0';
    }

    /** Starts a new payload generation and purges cached map pages (a no-op without LiteSpeed Cache). */
    public static function bump(): void {
        update_option( self::GENERATION_OPTION, sprintf( '%.6F', microtime( true ) ), true );
        do_action( 'litespeed_purge', MapRenderer::CACHE_TAG );
    }

    /** @param array<string,mixed> $profile @param array<string,mixed> $data */
    private static function title( array $profile, int $id, array $data ): string {
        if ( null !== $profile['title'] ) {
            $title = call_user_func( $profile['title'], $id, $data );
        } elseif ( 'users' === $profile['source'] ) {
            $user  = get_userdata( $id );
            $title = $user ? $user->display_name : '';
        } else {
            $title = get_the_title( $id );
        }

        return html_entity_decode( wp_strip_all_tags( (string) $title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    }

    /** @param array<string,mixed> $profile @param array<string,mixed> $data */
    private static function link( array $profile, int $id, array $data ): string {
        if ( null !== $profile['link'] ) {
            return (string) call_user_func( $profile['link'], $id, $data );
        }

        return 'users' === $profile['source'] ? (string) get_author_posts_url( $id ) : (string) get_permalink( $id );
    }

    /** @param array<string,mixed> $profile @param array<string,mixed> $data */
    private static function group( array $profile, int $id, array $data ): string {
        $group = $profile['group'];
        if ( null === $group ) {
            return '';
        }
        if ( is_callable( $group ) ) {
            return html_entity_decode( (string) call_user_func( $group, $id, $data ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        }

        if ( '' !== $group['meta_key'] ) {
            $value = maybe_unserialize( self::meta( $profile, $id, $group['meta_key'] ) );
            $value = is_array( $value ) ? reset( $value ) : $value;
            if ( '' === $group['taxonomy'] || ! is_numeric( $value ) ) {
                return is_scalar( $value ) ? (string) $value : '';
            }
            $term = get_term( (int) $value, $group['taxonomy'] );
        } else {
            $terms = 'posts' === $profile['source'] ? get_the_terms( $id, $group['taxonomy'] ) : false;
            $term  = is_array( $terms ) ? reset( $terms ) : null;
        }

        return $term && ! is_wp_error( $term ) ? html_entity_decode( (string) $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : '';
    }

    /** @param array<string,mixed> $profile @return mixed */
    private static function meta( array $profile, int $id, string $key ) {
        return 'users' === $profile['source'] ? get_user_meta( $id, $key, true ) : get_post_meta( $id, $key, true );
    }

    /** @param array<string,mixed> $profile @param array<string,mixed> $geo */
    private static function store( array $profile, int $id, array $geo ): void {
        'users' === $profile['source'] ? update_user_meta( $id, $profile['geo_meta'], $geo ) : update_post_meta( $id, $profile['geo_meta'], $geo );
    }

    /** @param array<string,mixed> $profile @param int[] $ids */
    private static function prime( array $profile, array $ids ): void {
        if ( [] === $ids ) {
            return;
        }
        if ( 'users' === $profile['source'] ) {
            if ( function_exists( 'cache_users' ) ) {
                cache_users( $ids );
            }
        } elseif ( function_exists( 'update_meta_cache' ) ) {
            update_meta_cache( 'post', $ids );
        }
    }
}
