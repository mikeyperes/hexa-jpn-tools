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
            $payload = [ 'title' => $item['title'], 'context' => '', 'html' => ( new MapRenderer() )->card( $profile, $item ), 'page' => 1, 'pages' => 1, 'total' => 1 ];
        } else {
            $data = (array) call_user_func( $profile['details'], $item['id'], $item['data'], $item, $query );
            $total = max( 0, (int) ( $data['total'] ?? count( (array) ( $data['entries'] ?? [] ) ) ) );
            $pages = max( 1, (int) ceil( $total / $query['per_page'] ) );
            $page = max( 1, min( $pages, (int) ( $data['page'] ?? $query['page'] ) ) );
            $payload = [
                'title' => (string) ( $data['title'] ?? $item['title'] ),
                'context' => 1 === $page ? self::context( $profile, $item, $data ) : '',
                'html' => self::render( $profile, $data, $query['per_page'], 1 === $total ),
                'page' => $page, 'pages' => $pages, 'total' => $total,
            ];
        }
        $payload['context'] = PublicComponent::inert( $payload['context'] );
        $payload['html'] = PublicComponent::inert( $payload['html'] );
        do_action( 'litespeed_tag_add', MapRenderer::CACHE_TAG );
        $response = PublicComponent::rest_response( $payload, $profile['public'] );
        if ( ! $profile['public'] ) {
            $response->header( 'Cache-Control', 'private, no-store' );
        }

        return $response;
    }

    /**
     * The selected place: its name, address, link, and the host's one-line summary.
     * Hosts provide escaped-by-Core scalars, never map/sidebar UI or arbitrary client HTML.
     */
    public static function context( array $profile, array $item, array $data ): string {
        $html = '<div class="hmap-place"><span class="hmap-place__pin" aria-hidden="true"></span><div class="hmap-place__text">'
            . '<p class="hmap-place__name">' . esc_html( $item['title'] ) . '</p>'
            . ( '' !== $item['address'] ? '<p class="hmap-place__address">' . esc_html( $item['address'] ) . '</p>' : '' ) . '</div>';
        if ( '' !== $item['url'] ) {
            $html .= '<a class="hmap-place__link" href="' . esc_url( $item['url'] ) . '"'
                . ItemLink::attributes( $profile, 'map', 'posts' === $profile['source'] ? $item['id'] : 0 ) . '>'
                . esc_html( $profile['labels']['cta'] ) . ' <span aria-hidden="true">→</span></a>';
        }
        $html .= '</div>';
        if ( '' !== (string) ( $data['summary'] ?? '' ) ) {
            $html .= '<p class="hmap-place__summary">' . esc_html( (string) $data['summary'] ) . '</p>';
        }

        return $html;
    }

    /**
     * One page of entries. Several entries render as compact rows (date badge, title, meta,
     * tags, thumbnail, actions); a lone entry renders as a featured card that adds the large
     * image, description, and facts. Entry keys: id, title, url, image{url,alt}, badge{top,main,
     * bottom}, meta[], tags[], description, facts{label:value}, actions[{label,url,external}].
     */
    public static function render( array $profile, array $data, int $limit, bool $feature = false ): string {
        $level = (int) $profile['heading_level'];
        $html = '';
        foreach ( array_slice( (array) ( $data['entries'] ?? [] ), 0, $limit ) as $entry ) {
            if ( ! is_array( $entry ) || '' === (string) ( $entry['title'] ?? '' ) ) {
                continue;
            }
            $url = esc_url( (string) ( $entry['url'] ?? '' ) );
            $attrs = ItemLink::attributes( $profile, 'map', (int) ( $entry['id'] ?? 0 ) );
            $title = esc_html( (string) $entry['title'] );
            $image = (array) ( $entry['image'] ?? [] );
            $src = esc_url( (string) ( $image['url'] ?? '' ) );
            $media = '';
            if ( '' !== $src ) {
                $img = '<img src="' . $src . '" alt="' . esc_attr( (string) ( $image['alt'] ?? '' ) ) . '" loading="lazy" decoding="async">';
                $media = '' !== $url ? '<a class="hmap-entry__media" href="' . $url . '"' . $attrs . ' tabindex="-1">' . $img . '</a>' : '<div class="hmap-entry__media">' . $img . '</div>';
            }
            $badge = (array) ( $entry['badge'] ?? [] );
            $badge_html = '';
            if ( '' !== (string) ( $badge['main'] ?? '' ) ) {
                $badge_html = '<p class="hmap-entry__badge">';
                foreach ( [ 'top', 'main', 'bottom' ] as $part ) {
                    if ( '' !== (string) ( $badge[ $part ] ?? '' ) ) {
                        $badge_html .= '<span class="hmap-entry__badge-' . $part . '">' . esc_html( (string) $badge[ $part ] ) . '</span>';
                    }
                }
                $badge_html .= '</p>';
            }
            $meta = implode( ' · ', array_map( 'esc_html', self::strings( $entry['meta'] ?? [] ) ) );
            $tags = '';
            foreach ( array_slice( self::strings( $entry['tags'] ?? [] ), 0, 4 ) as $tag ) {
                $tags .= '<li>' . esc_html( $tag ) . '</li>';
            }

            $html .= '<li class="hmap-entry">' . ( $feature ? $media : '' ) . '<div class="hmap-entry__row">' . $badge_html . '<div class="hmap-entry__main">'
                . '<h' . $level . ' class="hmap-entry__title">' . ( '' !== $url ? '<a href="' . $url . '"' . $attrs . '>' . $title . '</a>' : $title ) . '</h' . $level . '>'
                . ( '' !== $meta ? '<p class="hmap-entry__meta">' . $meta . '</p>' : '' )
                . ( '' !== $tags ? '<ul class="hmap-entry__tags">' . $tags . '</ul>' : '' )
                . '</div>' . ( $feature ? '' : $media ) . '</div>';
            if ( $feature ) {
                if ( '' !== (string) ( $entry['description'] ?? '' ) ) {
                    $html .= '<p class="hmap-entry__description">' . esc_html( (string) $entry['description'] ) . '</p>';
                }
                $facts = '';
                foreach ( (array) ( $entry['facts'] ?? [] ) as $label => $value ) {
                    if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
                        $facts .= '<div><dt>' . esc_html( (string) $label ) . '</dt><dd>' . esc_html( (string) $value ) . '</dd></div>';
                    }
                }
                $html .= '' !== $facts ? '<dl class="hmap-entry__facts">' . $facts . '</dl>' : '';
            }
            $actions = '';
            foreach ( array_slice( (array) ( $entry['actions'] ?? [] ), 0, 5 ) as $action ) {
                if ( ! is_array( $action ) ) { continue; }
                $href = esc_url( (string) ( $action['url'] ?? '' ) );
                if ( '' !== $href && '' !== (string) ( $action['label'] ?? '' ) ) {
                    $actions .= '<a href="' . $href . '"' . ( ! empty( $action['external'] ) ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>' . esc_html( (string) $action['label'] )
                        . ( ! empty( $action['external'] ) ? ' <span aria-hidden="true">↗</span>' : '' ) . '</a>';
                }
            }
            $html .= ( '' !== $actions ? '<div class="hmap-entry__actions">' . $actions . '</div>' : '' ) . '</li>';
        }
        if ( '' === $html ) {
            return '<p class="hmap-detail-empty">' . esc_html( $profile['labels']['details_empty'] ) . '</p>';
        }

        return '<ul class="hmap-entries' . ( $feature ? ' hmap-entries--feature' : '' ) . '">' . $html . '</ul>';
    }

    /** @return array<int,string> Non-empty trimmed scalar strings. */
    private static function strings( $values ): array {
        return array_values( array_filter( array_map( static fn( $v ): string => is_scalar( $v ) ? trim( (string) $v ) : '', (array) $values ), 'strlen' ) );
    }
}
