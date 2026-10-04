<?php

namespace Hexa\PluginCore\Map;

use Hexa\PluginCore\PublicComponents\ItemLink;
use Hexa\PluginCore\PublicComponents\PublicComponent;

/** Public, bounded location-detail reads and host-neutral rich entry markup. */
final class MapDetails {
    public static function register_routes(): void {
        register_rest_route( PublicComponent::REST_NAMESPACE, '/map/(?P<profile>[a-z0-9_\-]+)/details/(?P<item>\d+)', [
            'methods' => 'GET', 'callback' => [ self::class, 'response' ],
            'permission_callback' => [ self::class, 'permission' ],
        ] );
    }

    public static function permission( $request ): bool {
        $profile = MapRegistry::get( (string) $request['profile'] );

        return null !== $profile && 'sidebar' === $profile['selection'] && PublicComponent::can_view( $profile['public'] );
    }

    public static function response( $request ) {
        $profile = MapRegistry::get( (string) $request['profile'] );
        if ( null === $profile || 'sidebar' !== $profile['selection'] || ! PublicComponent::can_view( $profile['public'] ) ) {
            return new \WP_Error( 'hexa_map_details_not_found', 'Unknown map.', [ 'status' => 404 ] );
        }
        $item = MapLocations::item( $profile, (int) $request['item'] );
        if ( null === $item ) {
            return new \WP_Error( 'hexa_map_location_not_found', 'Unknown location.', [ 'status' => 404 ] );
        }
        $params = (array) $request->get_query_params();
        $hours = max( 0, (int) PublicComponent::scalar( $params['hours'] ?? 0 ) );
        if ( 0 !== $hours && ! array_key_exists( $hours, $profile['windows'] ) ) {
            return new \WP_Error( 'hexa_map_details_window', 'Unknown date window.', [ 'status' => 400 ] );
        }
        $query = [
            'page' => max( 1, min( 10000, (int) PublicComponent::scalar( $params['page'] ?? 1 ) ) ),
            'per_page' => $profile['details_per_page'], 'hours' => $hours,
        ];
        if ( null === $profile['details'] ) {
            $payload = [ 'title' => $item['title'], 'html' => ( new MapRenderer() )->card( $profile, $item ), 'page' => 1, 'pages' => 1, 'total' => 1 ];
        } else {
            $data = (array) call_user_func( $profile['details'], $item['id'], $item['data'], $item, $query );
            $total = max( 0, (int) ( $data['total'] ?? count( (array) ( $data['entries'] ?? [] ) ) ) );
            $pages = max( 1, (int) ceil( $total / $query['per_page'] ) );
            $page = max( 1, min( $pages, (int) ( $data['page'] ?? $query['page'] ) ) );
            $payload = [
                'title' => (string) ( $data['title'] ?? $item['title'] ),
                'html' => self::render( $profile, $item, $data, $query['per_page'] ),
                'page' => $page, 'pages' => $pages, 'total' => $total,
            ];
        }
        $payload['html'] = PublicComponent::inert( $payload['html'] );
        do_action( 'litespeed_tag_add', MapRenderer::CACHE_TAG );
        $response = PublicComponent::rest_response( $payload, $profile['public'] );
        if ( ! $profile['public'] ) {
            $response->header( 'Cache-Control', 'private, no-store' );
        }

        return $response;
    }

    /** Hosts provide escaped-by-Core scalars, never map/sidebar UI or arbitrary client HTML. */
    public static function render( array $profile, array $item, array $data, int $limit ): string {
        $html = '<div class="hmap-detail-context"><p class="hmap-card__meta">'
            . esc_html( $item['title'] . ( '' !== $item['address'] ? ' · ' . $item['address'] : '' ) ) . '</p>';
        if ( '' !== $item['url'] ) {
            $html .= '<a class="hmap-card__cta" href="' . esc_url( $item['url'] ) . '"'
                . ItemLink::attributes( $profile, 'map', 'posts' === $profile['source'] ? $item['id'] : 0 ) . '>'
                . esc_html( $profile['labels']['cta'] ) . '</a>';
        }
        if ( '' !== (string) ( $data['summary'] ?? '' ) ) {
            $html .= '<p class="hmap-detail-summary">' . esc_html( (string) $data['summary'] ) . '</p>';
        }
        $html .= '</div><div class="hmap-detail-entries">';
        $count = 0;
        foreach ( array_slice( (array) ( $data['entries'] ?? [] ), 0, $limit ) as $entry ) {
            if ( ! is_array( $entry ) || '' === (string) ( $entry['title'] ?? '' ) ) {
                continue;
            }
            $count++;
            $url = esc_url( (string) ( $entry['url'] ?? '' ) );
            $attrs = ItemLink::attributes( $profile, 'map', (int) ( $entry['id'] ?? 0 ) );
            $title = esc_html( (string) $entry['title'] );
            $html .= '<article class="hmap-entry">';
            $image = (array) ( $entry['image'] ?? [] );
            $src = esc_url( (string) ( $image['url'] ?? '' ) );
            if ( '' !== $src ) {
                $media = '<img src="' . $src . '" alt="' . esc_attr( (string) ( $image['alt'] ?? '' ) ) . '" loading="lazy" decoding="async">';
                $html .= '' !== $url ? '<a class="hmap-entry__image" href="' . $url . '"' . $attrs . '>' . $media . '</a>' : '<div class="hmap-entry__image">' . $media . '</div>';
            }
            $html .= '<h' . $profile['heading_level'] . ' class="hmap-entry__title">'
                . ( '' !== $url ? '<a href="' . $url . '"' . $attrs . '>' . $title . '</a>' : $title ) . '</h' . $profile['heading_level'] . '>';
            if ( '' !== (string) ( $entry['description'] ?? '' ) ) {
                $html .= '<p class="hmap-entry__description">' . esc_html( (string) $entry['description'] ) . '</p>';
            }
            $html .= '<dl class="hmap-entry__facts">';
            foreach ( (array) ( $entry['facts'] ?? [] ) as $label => $value ) {
                if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
                    $html .= '<div><dt>' . esc_html( (string) $label ) . '</dt><dd>' . esc_html( (string) $value ) . '</dd></div>';
                }
            }
            $html .= '</dl><div class="hmap-entry__actions">';
            foreach ( array_slice( (array) ( $entry['actions'] ?? [] ), 0, 5 ) as $action ) {
                if ( ! is_array( $action ) ) { continue; }
                $href = esc_url( (string) ( $action['url'] ?? '' ) );
                if ( '' !== $href && '' !== (string) ( $action['label'] ?? '' ) ) {
                    $html .= '<a href="' . $href . '"' . ( ! empty( $action['external'] ) ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>' . esc_html( (string) $action['label'] ) . '</a>';
                }
            }
            $html .= '</div></article>';
        }
        if ( 0 === $count ) {
            $html .= '<p class="hmap-detail-empty">' . esc_html( $profile['labels']['details_empty'] ) . '</p>';
        }

        return $html . '</div>';
    }
}
