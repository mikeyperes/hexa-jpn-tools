<?php

namespace Hexa\PluginCore\Map;

use Hexa\PluginCore\PublicComponents\ItemLightbox;
use Hexa\PluginCore\PublicComponents\ItemLink;
use Hexa\PluginCore\PublicComponents\PublicComponent;

/**
 * Renders a map component: an optional group filter, the map stage, and a
 * plain list of every item (the no-JavaScript and screen-reader view).
 *
 * The map library (MapLibre GL JS) and the vector style load only when the
 * component nears the viewport. Colors come from `--hmap-*` CSS custom
 * properties on the component, so a page builder restyles the map, its
 * pins, and its cards without code. Item cards are Core markup filled from
 * the profile's `card` data, or the profile's own `render_item` markup; the
 * profile's `link_behavior` decides what a click on a card link does.
 */
final class MapRenderer {
    /** LiteSpeed Cache tag on every page that renders a public map; purged when map content changes. */
    public const CACHE_TAG = 'hexa_map';

    /** Bumped whenever the cached point shape changes, so older cached payloads are never served. */
    public const PAYLOAD_VERSION = 4;

    private static bool $assets_printed = false;

    public function render( string $profile_id ): string {
        $profile = MapRegistry::get( $profile_id );
        if ( null === $profile || ! PublicComponent::can_view( $profile['public'] ) ) {
            return '';
        }

        // Cards show time-sensitive host data: cap the page lifetime at the payload lifetime and tag
        // the page so map content changes purge it (both no-ops without LiteSpeed Cache).
        if ( $profile['public'] && function_exists( 'do_action' ) ) {
            do_action( 'litespeed_control_set_ttl', max( 300, $profile['cache_ttl'] ) );
            do_action( 'litespeed_tag_add', self::CACHE_TAG );
        }

        $payload = $this->payload( $profile );
        $labels  = $profile['labels'];
        $count   = count( $payload['points'] );
        $dom_id  = 'hmap-' . $profile['id'];
        $config  = [
            'style'   => $profile['style'],
            'library' => $profile['library'],
            'view'    => $profile['view'],
            'cluster' => $profile['cluster'],
            'selection' => $profile['selection'],
            'labels'  => [ 'one' => $labels['count_one'], 'many' => $labels['count_many'] ] + $labels,
        ];

        $html = '<div class="hmap ' . esc_attr( $profile['class'] ) . '" id="' . esc_attr( $dom_id ) . '"'
            . ' data-hmap="' . esc_attr( (string) wp_json_encode( $config ) ) . '"'
            . ' data-hmap-points="' . esc_attr( (string) wp_json_encode( $payload['points'] ) ) . '"'
            . ( 'sidebar' === $profile['selection'] ? PublicComponent::live_attributes( 'hmap', 'map/' . $profile['id'] . '/details', $profile['public'] ) : '' )
            . ItemLink::root_attributes( $profile ) . '>'
            . '<div class="hmap-bar">' . $this->filter( $profile, $payload['groups'], $count, $dom_id )
            . '<p class="hmap-status" role="status" aria-live="polite">' . esc_html( $this->count( $labels, $count ) ) . '</p>'
            . $this->windows( $profile ) . '</div>'
            . '<div class="hmap-stage"><div class="hmap-canvas" role="region" aria-label="' . esc_attr( $labels['region'] ) . '"></div>'
            . '<p class="hmap-loading">' . esc_html( $labels['loading'] ) . '</p>' . $this->sidebar( $profile, $dom_id ) . '</div>'
            . $payload['list']
            . '</div>';

        // Titles and addresses are echoed; keep them inert to a later do_shortcode() pass.
        return $this->assets() . ItemLightbox::assets( $profile['link_behavior'] ) . PublicComponent::inert( $html );
    }

    /**
     * Points (with rendered cards), group counts, and the item list, cached per
     * profile, cache version, and content generation for `cache_ttl` seconds.
     *
     * @param array<string,mixed> $profile
     * @return array{points:array<int,array<string,mixed>>,groups:array<string,int>,list:string}
     */
    public function payload( array $profile ): array {
        $key    = 'hexa_map_' . md5( self::PAYLOAD_VERSION . '|' . $profile['id'] . '|' . $profile['cache_version'] . '|' . MapLocations::generation() );
        $cached = $profile['cache_ttl'] > 0 && function_exists( 'get_transient' ) ? get_transient( $key ) : false;
        if ( is_array( $cached ) && isset( $cached['points'], $cached['groups'], $cached['list'] ) ) {
            return $cached;
        }

        $items  = MapLocations::items( $profile );
        $points = [];
        $groups = [];
        $list   = '';
        usort( $items, static fn( array $a, array $b ): int => strcasecmp( $a['title'], $b['title'] ) );
        foreach ( $items as $item ) {
            $points[] = [
                'id'   => $item['id'],
                'la'   => round( $item['lat'], 6 ),
                'lo'   => round( $item['lng'], 6 ),
                'g'    => $item['group'],
                'live' => null !== $profile['highlight'] && (bool) call_user_func( $profile['highlight'], $item['id'], $item['data'] ),
                'n'    => null !== $profile['next'] ? max( 0, (int) call_user_func( $profile['next'], $item['id'], $item['data'] ) ) : 0,
                't'    => $item['title'],
                'h'    => $this->card( $profile, $item ),
            ];
            if ( '' !== $item['group'] ) {
                $groups[ $item['group'] ] = ( $groups[ $item['group'] ] ?? 0 ) + 1;
            }
            $list .= '<li>' . ( '' !== $item['url'] ? '<a href="' . esc_url( $item['url'] ) . '"' . $this->link( $profile, $item ) . '>' . esc_html( $item['title'] ) . '</a>' : esc_html( $item['title'] ) )
                . ( '' !== $item['group'] ? ' <span>· ' . esc_html( $item['group'] ) . '</span>' : '' )
                . ( 'sidebar' === $profile['selection'] ? ' <button type="button" data-hmap-select="' . esc_attr( (string) $item['id'] ) . '" hidden>' . esc_html( $profile['labels']['details_open'] ) . '</button>' : '' ) . '</li>';
        }
        uksort( $groups, static fn( string $a, string $b ): int => ( $groups[ $b ] <=> $groups[ $a ] ) ?: strcasecmp( $a, $b ) );

        $payload = [
            'points' => $points,
            'groups' => $groups,
            'list'   => '' !== $list ? '<details class="hmap-list"><summary>' . esc_html( $profile['labels']['list'] ) . '</summary><ul>' . $list . '</ul></details>' : '',
        ];
        if ( $profile['cache_ttl'] > 0 && function_exists( 'set_transient' ) ) {
            set_transient( $key, $payload, $profile['cache_ttl'] );
        }

        return $payload;
    }

    /** The selection panel: fixed header, one scrolling body (place, then entries), and a "show more" footer. */
    private function sidebar( array $profile, string $dom_id ): string {
        if ( 'sidebar' !== $profile['selection'] ) { return ''; }
        $labels = $profile['labels'];

        return '<aside class="hmap-sidebar" aria-labelledby="' . esc_attr( $dom_id . '-detail-title' ) . '" hidden>'
            . '<div class="hmap-sidebar__header"><div class="hmap-sidebar__heading">'
            . '<p class="hmap-sidebar__status" role="status" aria-live="polite"></p>'
            . '<h2 id="' . esc_attr( $dom_id . '-detail-title' ) . '" class="hmap-sidebar__title" tabindex="-1">' . esc_html( $labels['details'] ) . '</h2></div>'
            . '<button type="button" class="hmap-sidebar__close" aria-label="' . esc_attr( $labels['details_close'] ) . '"><span aria-hidden="true"></span></button></div>'
            . '<div class="hmap-sidebar__body"><div class="hmap-sidebar__context"></div><div class="hmap-sidebar__entries"></div>'
            . '<div class="hmap-sidebar__error" hidden><p>' . esc_html( $labels['details_error'] ) . '</p><button type="button" class="hmap-sidebar__retry">' . esc_html( $labels['details_retry'] ) . '</button></div></div>'
            . '<div class="hmap-sidebar__footer" hidden><p class="hmap-sidebar__shown"></p><button type="button" class="hmap-sidebar__more">' . esc_html( $labels['details_more'] ) . '</button></div></aside>';
    }

    /**
     * One item's card: the profile's `render_item` markup, or Core markup from `card` data
     * (kicker, meta lines, list_label, list of [label, text, url], cta).
     *
     * @param array<string,mixed> $profile
     * @param array<string,mixed> $item
     */
    public function card( array $profile, array $item ): string {
        if ( null !== $profile['render_item'] ) {
            return (string) call_user_func( $profile['render_item'], $item['id'], $item['data'], $item );
        }

        $card = [ 'kicker' => $item['group'], 'meta' => [ $item['address'] ], 'list_label' => '', 'list' => [], 'cta' => $profile['labels']['cta'] ];
        if ( null !== $profile['card'] ) {
            $card = array_merge( $card, (array) call_user_func( $profile['card'], $item['id'], $item['data'], $item ) );
        }

        $tag  = 'h' . $profile['heading_level'];
        $url  = (string) $item['url'];
        $html = '<div class="hmap-card">';
        if ( '' !== (string) $card['kicker'] ) {
            $html .= '<p class="hmap-card__kicker">' . esc_html( (string) $card['kicker'] ) . '</p>';
        }
        $attrs = $this->link( $profile, $item );
        $html .= '<' . $tag . ' class="hmap-card__title">' . ( '' !== $url ? '<a href="' . esc_url( $url ) . '"' . $attrs . '>' . esc_html( $item['title'] ) . '</a>' : esc_html( $item['title'] ) ) . '</' . $tag . '>';
        foreach ( (array) $card['meta'] as $line ) {
            if ( is_scalar( $line ) && '' !== trim( (string) $line ) ) {
                $html .= '<p class="hmap-card__meta">' . esc_html( (string) $line ) . '</p>';
            }
        }
        $rows = '';
        foreach ( (array) $card['list'] as $row ) {
            if ( ! is_array( $row ) || '' === (string) ( $row['text'] ?? '' ) ) {
                continue;
            }
            $inner = ( '' !== (string) ( $row['label'] ?? '' ) ? '<b>' . esc_html( (string) $row['label'] ) . '</b>' : '' ) . '<span>' . esc_html( (string) $row['text'] ) . '</span>';
            // A row may name the post it links to with `id`; otherwise it is looked up from its URL.
            $row_id = (int) ( $row['id'] ?? ( 'lightbox' === $profile['link_behavior']['mode'] ? ItemLink::post_id( (string) ( $row['url'] ?? '' ) ) : 0 ) );
            $rows  .= '<li>' . ( '' !== (string) ( $row['url'] ?? '' ) ? '<a href="' . esc_url( (string) $row['url'] ) . '"' . ItemLink::attributes( $profile, 'map', $row_id ) . '>' . $inner . '</a>' : '<span class="hmap-card__row">' . $inner . '</span>' ) . '</li>';
        }
        if ( '' !== $rows ) {
            $html .= ( '' !== (string) $card['list_label'] ? '<p class="hmap-card__label">' . esc_html( (string) $card['list_label'] ) . '</p>' : '' ) . '<ul>' . $rows . '</ul>';
        }
        if ( '' !== $url && '' !== (string) $card['cta'] ) {
            $html .= '<a class="hmap-card__cta" href="' . esc_url( $url ) . '"' . $attrs . '>' . esc_html( (string) $card['cta'] ) . ' <span aria-hidden="true">→</span></a>';
        }

        return $html . '</div>';
    }

    /** Link attributes for an item's own URL; a posts-source item is the post itself, a user item never opens in the lightbox. */
    private function link( array $profile, array $item ): string {
        return ItemLink::attributes( $profile, 'map', 'posts' === $profile['source'] ? (int) $item['id'] : 0 );
    }

    /** @param array<string,mixed> $profile @param array<string,int> $groups */
    private function filter( array $profile, array $groups, int $count, string $dom_id ): string {
        if ( count( $groups ) < 2 ) {
            return '';
        }

        $labels = $profile['labels'];
        $chips  = array_slice( array_filter( $groups, static fn( int $n ): bool => $n > 1 ), 0, $profile['chips'], true );
        $html   = '<div class="hmap-groups" role="group" aria-label="' . esc_attr( $labels['filter'] ) . '">'
            . '<button type="button" class="hmap-chip" data-hmap-group="" aria-pressed="true">' . esc_html( $labels['all'] ) . ' <span>' . esc_html( (string) $count ) . '</span></button>';
        foreach ( $chips as $group => $n ) {
            $html .= '<button type="button" class="hmap-chip" data-hmap-group="' . esc_attr( (string) $group ) . '" aria-pressed="false">' . esc_html( (string) $group ) . ' <span>' . esc_html( (string) $n ) . '</span></button>';
        }
        if ( count( $groups ) > count( $chips ) ) {
            $sorted = $groups;
            ksort( $sorted, SORT_NATURAL | SORT_FLAG_CASE );
            $html .= '<select class="hmap-select" aria-label="' . esc_attr( $labels['filter'] ) . '"><option value="">' . esc_html( $labels['more'] ) . '</option>';
            foreach ( $sorted as $group => $n ) {
                $html .= '<option value="' . esc_attr( (string) $group ) . '">' . esc_html( $group . ' (' . $n . ')' ) . '</option>';
            }
            $html .= '</select>';
        }

        return $html . '</div>';
    }

    /**
     * Date filter chips: only items whose next dated entry starts within the chosen
     * number of hours. The browser applies it against its own clock, so a cached
     * page stays correct; chip counts are filled in by the script.
     *
     * @param array<string,mixed> $profile
     */
    private function windows( array $profile ): string {
        if ( null === $profile['next'] || [] === $profile['windows'] ) {
            return '';
        }
        $prefix = $profile['labels']['when_prefix'];
        $html   = '<div class="hmap-windows" role="group" aria-label="' . esc_attr( $profile['labels']['when'] ) . '">'
            . ( '' !== $prefix ? '<span class="hmap-windows__label" aria-hidden="true">' . esc_html( $prefix ) . '</span>' : '' )
            . '<button type="button" class="hmap-chip" data-hmap-hours="0" aria-pressed="true">' . esc_html( $profile['labels']['when_all'] ) . '</button>';
        foreach ( $profile['windows'] as $hours => $label ) {
            $html .= '<button type="button" class="hmap-chip" data-hmap-hours="' . esc_attr( (string) $hours ) . '" aria-pressed="false">' . esc_html( $label ) . ' <span></span></button>';
        }

        return $html . '</div>';
    }

    /** @param array<string,string> $labels */
    private function count( array $labels, int $count ): string {
        return sprintf( 1 === $count ? $labels['count_one'] : $labels['count_many'], $count );
    }

    private function assets(): string {
        if ( self::$assets_printed ) {
            return '';
        }
        self::$assets_printed = true;

        return '<style id="hexa-map-css">' . self::css() . '</style><script id="hexa-map-js">' . self::js() . '</script>';
    }

    public static function css(): string {
        return <<<'CSS'
.hmap{--hmap-height:520px;--hmap-radius:8px;--hmap-text:#f2f4f3;--hmap-muted:#9aa3a0;--hmap-border:rgba(255,255,255,.12);--hmap-surface:#111614;--hmap-land:#0d1110;--hmap-water:#16222a;--hmap-park:#121a15;--hmap-building:#171d1b;--hmap-road:#1f2724;--hmap-road-major:#2c3632;--hmap-boundary:#3a4541;--hmap-label:#7d8783;--hmap-accent:#4f9dff;--hmap-accent-fg:#06121f;--hmap-live:var(--hmap-accent);--hmap-glow:rgba(79,157,255,.18);position:relative;color:var(--hmap-text);font:inherit}
.hmap-bar{display:flex;flex-wrap:wrap;align-items:center;gap:8px 16px;margin:0 0 14px}
.hmap-groups{display:flex;flex-wrap:wrap;gap:8px}
.hmap-windows{display:flex;flex-wrap:wrap;gap:8px;flex-basis:100%}
.hmap-windows{align-items:center}
.hmap-windows__label{margin-right:6px;color:var(--hmap-muted);font-size:12px;letter-spacing:.12em;text-transform:uppercase}
.hmap .hmap-chip,.hmap .hmap-chip:hover,.hmap .hmap-chip:focus,.hmap .hmap-chip:active{appearance:none;display:inline-flex;align-items:center;gap:8px;min-height:38px;margin:0;padding:0 8px 0 14px;border:1px solid var(--hmap-border);border-radius:999px;background:var(--hmap-surface);box-shadow:none;color:var(--hmap-text);font:inherit;font-size:14px;font-weight:500;line-height:1;text-decoration:none;text-transform:none;letter-spacing:0;cursor:pointer;transition:border-color .15s,background-color .15s,color .15s}
.hmap .hmap-chip:not(:has(span)){padding-right:14px}
.hmap .hmap-chip:hover{border-color:var(--hmap-accent);background:var(--hmap-surface);color:var(--hmap-text)}
.hmap .hmap-chip span{display:inline-flex;align-items:center;justify-content:center;min-width:24px;height:22px;padding:0 7px;border-radius:999px;background:rgba(255,255,255,.08);color:var(--hmap-muted);font-size:12px;font-weight:600;font-variant-numeric:tabular-nums;line-height:1}
.hmap .hmap-chip[aria-pressed=true],.hmap .hmap-chip[aria-pressed=true]:hover,.hmap .hmap-chip[aria-pressed=true]:focus{border-color:var(--hmap-accent);background:var(--hmap-accent);color:var(--hmap-accent-fg);font-weight:700}
.hmap .hmap-chip[aria-pressed=true] span{background:rgba(0,0,0,.2);color:var(--hmap-accent-fg)}
.hmap .hmap-chip:focus-visible,.hmap-select:focus-visible{outline:2px solid var(--hmap-accent);outline-offset:2px}
.hmap .hmap-select{flex:0 0 auto;width:auto;min-height:40px;max-width:100%;padding:0 34px 0 14px;border:1px solid var(--hmap-border);border-radius:999px;background:var(--hmap-surface) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M1 1l4 4 4-4' fill='none' stroke='%23999' stroke-width='1.5'/%3E%3C/svg%3E") no-repeat right 14px center;color:var(--hmap-text);font:inherit;font-size:14px;appearance:none;cursor:pointer}
.hmap-status{margin:0 0 0 auto;color:var(--hmap-muted);font-size:13px;letter-spacing:.04em}
.hmap-stage{position:relative;height:var(--hmap-height);border:1px solid var(--hmap-border);border-radius:var(--hmap-radius);overflow:hidden;background:var(--hmap-land);box-shadow:0 30px 80px -40px var(--hmap-glow),inset 0 0 0 1px rgba(255,255,255,.02)}
.hmap-stage:after{content:"";position:absolute;inset:0;pointer-events:none;background:radial-gradient(120% 90% at 50% 45%,transparent 55%,rgba(0,0,0,.55) 100%);z-index:2}
.hmap-stage>.hmap-canvas{position:absolute;inset:0;width:100%;height:100%}
.hmap-loading{position:absolute;inset:0;display:grid;place-items:center;margin:0;color:var(--hmap-muted);font-size:13px;letter-spacing:.08em;text-transform:uppercase;z-index:1}
.hmap.is-ready .hmap-loading{display:none}
.hmap.is-failed .hmap-stage{display:none}
.hmap-list{margin:14px 0 0;color:var(--hmap-muted);font-size:14px}
.hmap-list summary{cursor:pointer;min-height:32px;line-height:32px}
.hmap-list span{color:var(--hmap-muted)}
.hmap-list ul{columns:3 220px;gap:24px;margin:8px 0 0;padding:0;list-style:none}
.hmap-list li{break-inside:avoid;padding:3px 0}
.hmap-list a{color:var(--hmap-text);text-decoration:none}
.hmap-list a:hover{color:var(--hmap-accent)}
.hmap .maplibregl-ctrl-group{background:var(--hmap-surface);border:1px solid var(--hmap-border);box-shadow:none}
.hmap .maplibregl-ctrl-group button+button{border-top:1px solid var(--hmap-border)}
.hmap .maplibregl-ctrl-group button .maplibregl-ctrl-icon{filter:invert(1) opacity(.8)}
.hmap .maplibregl-ctrl.maplibregl-ctrl-attrib{background:rgba(0,0,0,.55);color:var(--hmap-muted);font-size:11px}
.hmap .maplibregl-ctrl-attrib a{color:var(--hmap-muted)}
.hmap .maplibregl-ctrl-attrib-button{filter:invert(1)}
.hmap .maplibregl-cooperative-gesture-screen{background:rgba(0,0,0,.6);color:#fff;font:inherit;font-size:15px;z-index:3}
.hmap-popup{z-index:4}
.hmap-popup .maplibregl-popup-content{padding:16px 18px;border:1px solid var(--hmap-border);border-radius:var(--hmap-radius);background:var(--hmap-surface);color:var(--hmap-text);box-shadow:0 18px 40px rgba(0,0,0,.55);font:inherit}
.hmap-popup.maplibregl-popup-anchor-bottom .maplibregl-popup-tip,.hmap-popup.maplibregl-popup-anchor-bottom-left .maplibregl-popup-tip,.hmap-popup.maplibregl-popup-anchor-bottom-right .maplibregl-popup-tip{border-top-color:var(--hmap-surface)}
.hmap-popup.maplibregl-popup-anchor-top .maplibregl-popup-tip,.hmap-popup.maplibregl-popup-anchor-top-left .maplibregl-popup-tip,.hmap-popup.maplibregl-popup-anchor-top-right .maplibregl-popup-tip{border-bottom-color:var(--hmap-surface)}
.hmap-popup.maplibregl-popup-anchor-left .maplibregl-popup-tip{border-right-color:var(--hmap-surface)}
.hmap-popup.maplibregl-popup-anchor-right .maplibregl-popup-tip{border-left-color:var(--hmap-surface)}
.hmap-popup .maplibregl-popup-close-button{width:32px;height:32px;color:var(--hmap-muted);font-size:20px}
.hmap-tip,.hmap-tip *{pointer-events:none}
.hmap-tip{z-index:4}
.hmap-tip .maplibregl-popup-content{padding:7px 12px;border:1px solid var(--hmap-accent);border-radius:999px;background:var(--hmap-surface);color:var(--hmap-text);box-shadow:0 8px 24px rgba(0,0,0,.5);font:inherit;font-size:13px;font-weight:600;line-height:1.2;white-space:nowrap}
.hmap-tip .maplibregl-popup-tip{display:none}
.hmap-card{display:grid;gap:8px;font-size:14px;line-height:1.45}
.hmap-card__kicker{margin:0;color:var(--hmap-accent);font-size:11px;font-weight:600;letter-spacing:.14em;text-transform:uppercase}
.hmap-card__title{margin:0;padding-right:18px;font-size:17px;line-height:1.25}
.hmap-card__title a{color:inherit;text-decoration:none}
.hmap-card__meta{margin:0;color:var(--hmap-muted);font-size:13px}
.hmap-card__label{margin:4px 0 0;color:var(--hmap-muted);font-size:11px;letter-spacing:.12em;text-transform:uppercase}
.hmap-card ul{display:grid;gap:4px;margin:0;padding:0;list-style:none}
.hmap-card li a{display:flex;gap:10px;color:var(--hmap-text);text-decoration:none}
.hmap-card li b{flex:none;min-width:46px;color:var(--hmap-accent);font-weight:600}
.hmap-card li a:hover span{text-decoration:underline}
.hmap-card__cta{justify-self:start;margin-top:4px;color:var(--hmap-accent);font-weight:600;text-decoration:none}
.hmap-card__cta:hover{text-decoration:underline}
.hmap-sidebar[hidden],.hmap-sidebar [hidden],.hmap-list button[hidden]{display:none!important}
.hmap-sidebar{--hmap-ease:cubic-bezier(.22,.8,.24,1);position:absolute;top:12px;right:12px;bottom:12px;z-index:5;display:flex;flex-direction:column;width:var(--hmap-sidebar-width,400px);max-width:calc(100% - 24px);box-sizing:border-box;border:1px solid var(--hmap-border);border-radius:calc(var(--hmap-radius) + 6px);background:var(--hmap-surface);box-shadow:0 28px 70px -16px rgba(0,0,0,.7),0 0 0 1px rgba(255,255,255,.02);color:var(--hmap-text);font:inherit;overflow:hidden;opacity:0;transform:translate3d(calc(100% + 24px),0,0);visibility:hidden;transition:transform .34s var(--hmap-ease),opacity .2s ease,visibility 0s linear .34s}
.hmap-stage.has-selection .hmap-sidebar{opacity:1;transform:none;visibility:visible;transition:transform .34s var(--hmap-ease),opacity .16s ease,visibility 0s}
.hmap .maplibregl-ctrl-top-right{transition:right .34s cubic-bezier(.22,.8,.24,1)}
.hmap-stage.has-selection .maplibregl-ctrl-top-right{right:calc(min(var(--hmap-sidebar-width,400px),100% - 24px) + 12px)}
.hmap-sidebar__header{flex:none;display:flex;align-items:flex-start;gap:12px;padding:18px 18px 14px 20px;border-bottom:1px solid transparent;transition:border-color .15s}
.hmap-sidebar.is-scrolled .hmap-sidebar__header{border-bottom-color:var(--hmap-border)}
.hmap-sidebar__heading{flex:1;min-width:0}
.hmap-sidebar__status{min-height:14px;margin:0 0 6px;color:var(--hmap-accent);font-size:11px;font-weight:600;letter-spacing:.14em;line-height:1.3;text-transform:uppercase}
.hmap-sidebar__title{margin:0;color:inherit;font-size:22px;font-weight:600;line-height:1.2;overflow-wrap:anywhere;outline:0}
.hmap .hmap-sidebar__close,.hmap .hmap-sidebar__close:hover,.hmap .hmap-sidebar__close:focus{appearance:none;flex:none;position:relative;width:34px;height:34px;min-height:0;margin:0;padding:0;border:1px solid var(--hmap-border);border-radius:999px;background:transparent;box-shadow:none;color:var(--hmap-muted);cursor:pointer;transition:border-color .15s,color .15s,background-color .15s}
.hmap .hmap-sidebar__close:hover{border-color:var(--hmap-accent);background:rgba(255,255,255,.05);color:var(--hmap-text)}
.hmap-sidebar__close span:before,.hmap-sidebar__close span:after{content:"";position:absolute;left:50%;top:50%;width:14px;height:1.5px;border-radius:2px;background:currentColor;transform:translate(-50%,-50%) rotate(45deg)}
.hmap-sidebar__close span:after{transform:translate(-50%,-50%) rotate(-45deg)}
.hmap-sidebar__body{flex:1;min-height:0;overflow-y:auto;overscroll-behavior:contain;padding:0 20px 20px;scrollbar-width:thin;scrollbar-color:var(--hmap-border) transparent}
.hmap-place{display:grid;grid-template-columns:auto minmax(0,1fr);gap:0 12px;align-items:start;padding:14px;border:1px solid var(--hmap-border);border-radius:var(--hmap-radius);background:rgba(255,255,255,.025)}
.hmap-place__pin{grid-row:span 2;position:relative;width:30px;height:30px;border-radius:999px;background:var(--hmap-glow)}
.hmap-place__pin:after{content:"";position:absolute;inset:9px;border-radius:999px;background:var(--hmap-accent);box-shadow:0 0 0 2px var(--hmap-surface)}
.hmap-place__name{margin:0;font-size:14px;font-weight:600;line-height:1.35}
.hmap-place__address{margin:2px 0 0;color:var(--hmap-muted);font-size:13px;line-height:1.4}
.hmap-place__link{grid-column:2;justify-self:start;margin-top:8px;color:var(--hmap-accent);font-size:13px;font-weight:600;text-decoration:none}
.hmap-place__link:hover{text-decoration:underline}
.hmap-place__summary{margin:12px 2px 0;color:var(--hmap-muted);font-size:13px;line-height:1.5}
.hmap-sidebar__entries{margin-top:16px}
.hmap-entries,.hmap-skeleton{display:grid;gap:10px;margin:0;padding:0;list-style:none}
.hmap-entry{display:grid;gap:10px;min-width:0;padding:12px;border:1px solid var(--hmap-border);border-radius:var(--hmap-radius);background:rgba(255,255,255,.02);transition:border-color .15s,background-color .15s;animation:hmap-in .3s var(--hmap-ease,ease) both}
.hmap-entry:hover{border-color:color-mix(in srgb,var(--hmap-accent) 45%,transparent);background:rgba(255,255,255,.04)}
.hmap-entry:nth-child(2){animation-delay:.03s}.hmap-entry:nth-child(3){animation-delay:.06s}.hmap-entry:nth-child(4){animation-delay:.09s}.hmap-entry:nth-child(n+5){animation-delay:.12s}
.hmap-entry__row{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:12px;align-items:start}
.hmap-entry__badge{display:grid;justify-items:center;align-content:center;width:50px;min-height:56px;margin:0;padding:6px 0;box-sizing:border-box;border:1px solid var(--hmap-border);border-radius:calc(var(--hmap-radius) - 2px);background:rgba(255,255,255,.05);line-height:1;text-align:center}
.hmap-entry__badge-top{color:var(--hmap-accent);font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase}
.hmap-entry__badge-main{margin:4px 0 3px;font-size:20px;font-weight:700;font-variant-numeric:tabular-nums}
.hmap-entry__badge-bottom{color:var(--hmap-muted);font-size:10px;letter-spacing:.08em;text-transform:uppercase}
.hmap-entry__main{min-width:0}
.hmap-entry__title{display:-webkit-box;margin:0;overflow:hidden;color:inherit;font-size:15px;font-weight:600;line-height:1.3;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.hmap-entry__title a{color:inherit;text-decoration:none}.hmap-entry__title a:hover{color:var(--hmap-accent)}
.hmap-entry__meta{margin:4px 0 0;color:var(--hmap-muted);font-size:13px;line-height:1.4}
.hmap-entry__tags{display:flex;flex-wrap:wrap;gap:6px;margin:8px 0 0;padding:0;list-style:none}
.hmap-entry__tags li{padding:3px 8px;border-radius:999px;background:var(--hmap-glow);color:var(--hmap-accent);font-size:11px;font-weight:600;line-height:1.3}
.hmap-entry__media{display:block;width:64px;height:64px;overflow:hidden;border-radius:calc(var(--hmap-radius) - 2px);background:var(--hmap-land)}
.hmap-entry__media img{display:block;width:100%;height:100%;object-fit:cover}
.hmap-entry__actions{display:flex;flex-wrap:wrap;gap:8px;padding-left:62px}
.hmap-entry:not(:has(.hmap-entry__badge)) .hmap-entry__actions{padding-left:0}
.hmap-entry__actions a{display:inline-flex;align-items:center;gap:5px;min-height:30px;padding:0 12px;border:1px solid var(--hmap-border);border-radius:999px;color:var(--hmap-text);font-size:12px;font-weight:600;line-height:1;text-decoration:none;transition:border-color .15s,color .15s,filter .15s}
.hmap-entry__actions a:hover{border-color:var(--hmap-accent);color:var(--hmap-accent)}
.hmap-entry__actions a:first-child,.hmap-entry__actions a:first-child:hover{border-color:var(--hmap-accent);background:var(--hmap-accent);color:var(--hmap-accent-fg)}
.hmap-entry__actions a:first-child:hover{filter:brightness(1.1)}
.hmap-entries--feature .hmap-entry{gap:14px;padding:0;overflow:hidden}
.hmap-entries--feature .hmap-entry>:not(.hmap-entry__media){margin:0 16px}
.hmap-entries--feature .hmap-entry>:first-child:not(.hmap-entry__media){margin-top:16px}
.hmap-entries--feature .hmap-entry>:last-child{margin-bottom:16px}
.hmap-entries--feature .hmap-entry__media{width:auto;height:auto;border-radius:0}
.hmap-entries--feature .hmap-entry__media img{height:auto;max-height:300px;object-fit:contain}
.hmap-entries--feature .hmap-entry__title{font-size:18px;-webkit-line-clamp:3}
.hmap-entries--feature .hmap-entry__actions{padding-left:0}
.hmap-entries--feature .hmap-entry__actions a{min-height:36px;padding:0 16px;font-size:13px}
.hmap-entry__description{color:var(--hmap-muted);font-size:14px;line-height:1.55}
.hmap-entry__facts{display:grid;font-size:13px;line-height:1.45;border-top:1px solid var(--hmap-border)}
.hmap-entry__facts>div{display:grid;grid-template-columns:76px minmax(0,1fr);gap:12px;padding:9px 0;border-bottom:1px solid var(--hmap-border)}
.hmap-entry__facts dt{padding-top:2px;color:var(--hmap-muted);font-size:11px;font-weight:400;letter-spacing:.1em;text-transform:uppercase}
.hmap-entry__facts dd{margin:0;overflow-wrap:anywhere}
.hmap-skeleton li{height:82px;border-radius:var(--hmap-radius);background:linear-gradient(90deg,rgba(255,255,255,.03) 20%,rgba(255,255,255,.08) 50%,rgba(255,255,255,.03) 80%) 0 0/300% 100%;animation:hmap-shimmer 1.2s linear infinite}
.hmap-skeleton li:first-child{height:64px}
.hmap-detail-empty,.hmap-sidebar__error{margin:0;padding:18px;border:1px dashed var(--hmap-border);border-radius:var(--hmap-radius);color:var(--hmap-muted);font-size:13px;line-height:1.5;text-align:center}
.hmap-sidebar__error{margin-top:16px}.hmap-sidebar__error p{margin:0 0 12px}
.hmap-sidebar__footer{flex:none;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 20px;border-top:1px solid var(--hmap-border);background:var(--hmap-surface)}
.hmap-sidebar__shown{margin:0;color:var(--hmap-muted);font-size:12px}
.hmap .hmap-sidebar__more,.hmap .hmap-sidebar__retry,.hmap-list button{appearance:none;min-height:34px;margin:0;padding:0 14px;border:1px solid var(--hmap-border);border-radius:999px;background:transparent;box-shadow:none;color:var(--hmap-text);font:inherit;font-size:13px;font-weight:600;letter-spacing:normal;text-transform:none;cursor:pointer;transition:border-color .15s,color .15s}
.hmap .hmap-sidebar__more:hover,.hmap .hmap-sidebar__retry:hover,.hmap-list button:hover{border-color:var(--hmap-accent);background:transparent;color:var(--hmap-accent)}
.hmap .hmap-sidebar__more:disabled{opacity:.5;cursor:default}
.hmap-sidebar button:focus-visible,.hmap-list button:focus-visible,.hmap-sidebar a:focus-visible{outline:2px solid var(--hmap-accent);outline-offset:3px}
@keyframes hmap-in{from{opacity:0;transform:translate3d(0,6px,0)}to{opacity:1;transform:none}}
@keyframes hmap-shimmer{to{background-position:-300% 0}}
@media(max-width:767px){.hmap-stage.has-selection .maplibregl-ctrl-top-right{right:0}.hmap-sidebar{top:auto;right:8px;bottom:8px;left:8px;width:auto;max-width:none;max-height:78%;transform:translate3d(0,calc(100% + 16px),0)}.hmap-stage.has-selection .hmap-sidebar{transform:none}.hmap-sidebar__header{padding:16px 14px 12px 16px}.hmap-sidebar__body{padding:0 16px 16px}.hmap-sidebar__footer{padding:10px 16px}}
@media(prefers-reduced-motion:reduce){.hmap-sidebar,.hmap-stage.has-selection .hmap-sidebar,.hmap-entry,.hmap-skeleton li{transition:none;animation:none}}
@media (max-width:767px){.hmap{--hmap-height:440px}.hmap-status{flex-basis:100%;margin:2px 0 0}}
CSS;
    }

    public static function js(): string {
        return <<<'JS'
(function () {
  'use strict';
  var lib = null;
  function load(cfg) {
    if (window.maplibregl) { return Promise.resolve(window.maplibregl); }
    if (lib) { return lib; }
    lib = new Promise(function (resolve, reject) {
      var css = document.createElement('link');
      css.rel = 'stylesheet'; css.href = cfg.css;
      document.head.appendChild(css);
      var js = document.createElement('script');
      js.src = cfg.js; js.async = true;
      js.onload = function () { window.maplibregl ? resolve(window.maplibregl) : reject(); };
      js.onerror = reject;
      document.head.appendChild(js);
    });
    return lib;
  }
  function tokens(el) {
    var s = getComputedStyle(el), t = {};
    ['land', 'water', 'park', 'building', 'road', 'road-major', 'boundary', 'label', 'accent', 'accent-fg', 'live'].forEach(function (k) {
      t[k] = s.getPropertyValue('--hmap-' + k).trim();
    });
    return t;
  }
  function paint(map, id, prop, value) {
    if (value) { try { map.setPaintProperty(id, prop, value); } catch (e) {} }
  }
  // Recolors an OpenMapTiles-schema style (OpenFreeMap, MapTiler, Protomaps OMT) from the component's CSS tokens.
  function tint(map, t) {
    map.getStyle().layers.forEach(function (l) {
      var sl = l['source-layer'] || '', id = l.id;
      if (l.type === 'background') { paint(map, id, 'background-color', t.land); }
      else if (l.type === 'hillshade' || l.type === 'raster') { map.setLayoutProperty(id, 'visibility', 'none'); }
      else if (l.type === 'fill') {
        if (sl === 'water') { paint(map, id, 'fill-color', t.water); paint(map, id, 'fill-outline-color', t.water); }
        else if (sl === 'building') { paint(map, id, 'fill-color', t.building); paint(map, id, 'fill-outline-color', t.building); }
        else if (sl === 'landcover' || sl === 'landuse' || sl === 'park') { paint(map, id, 'fill-color', t.park); }
        else if (sl === 'transportation' || sl === 'aeroway') { paint(map, id, 'fill-color', t.road); }
      } else if (l.type === 'line') {
        if (sl === 'waterway') { paint(map, id, 'line-color', t.water); }
        else if (sl === 'boundary') { paint(map, id, 'line-color', t.boundary); }
        else if (sl === 'transportation' || sl === 'aeroway') { paint(map, id, 'line-color', /motorway|major|trunk|primary/.test(id) ? t['road-major'] : t.road); }
      } else if (l.type === 'symbol') {
        paint(map, id, 'text-color', t.label); paint(map, id, 'text-halo-color', t.land); paint(map, id, 'icon-opacity', 0);
      }
    });
  }
  function fc(points) {
    return { type: 'FeatureCollection', features: points.map(function (p, i) {
      return { type: 'Feature', id: i, geometry: { type: 'Point', coordinates: [p.lo, p.la] }, properties: { i: i, live: p.live ? 1 : 0 } };
    }) };
  }
  function fit(map, points, view, animate) {
    if (!points.length) { return; }
    if (!view.bounds && view.center) { map.jumpTo({ center: view.center, zoom: view.zoom }); return; }
    var b = new window.maplibregl.LngLatBounds();
    points.forEach(function (p) { b.extend([p.lo, p.la]); });
    map.fitBounds(b, { padding: 56, maxZoom: view.fitZoom, duration: animate ? 700 : 0 });
  }
  function init(el) {
    var cfg = JSON.parse(el.getAttribute('data-hmap') || '{}');
    var all = JSON.parse(el.getAttribute('data-hmap-points') || '[]');
    var canvas = el.querySelector('.hmap-canvas');
    var status = el.querySelector('.hmap-status');
    var controls = el.querySelectorAll('[data-hmap-group]');
    var select = el.querySelector('.hmap-select');
    var hourControls = el.querySelectorAll('[data-hmap-hours]');
    var state = { g: '', h: 0 };
    var shown = all;
    function within(p, h) {
      if (h <= 0) { return true; }
      var now = Date.now() / 1000;
      return p.n > 0 && p.n >= now - 86400 && p.n <= now + h * 3600;
    }
    function pick() {
      return all.filter(function (p) { return (state.g === '' || p.g === state.g) && within(p, state.h); });
    }
    function counts() {
      Array.prototype.forEach.call(hourControls, function (b) {
        var h = +b.getAttribute('data-hmap-hours'), span = b.querySelector('span');
        if (span) { span.textContent = all.filter(function (p) { return (state.g === '' || p.g === state.g) && within(p, h); }).length; }
      });
    }
    counts();
    load(cfg.library).then(function (gl) {
      var t = tokens(el);
      var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      var map = new gl.Map({
        container: canvas, style: cfg.style, center: cfg.view.center || [0, 0], zoom: cfg.view.zoom || 2,
        maxZoom: cfg.view.maxZoom, attributionControl: { compact: true }, cooperativeGestures: true,
        dragRotate: false, pitchWithRotate: false, touchPitch: false
      });
      map.touchZoomRotate.disableRotation();
      el.hmapMap = map;
      map.addControl(new gl.NavigationControl({ showCompass: false }), 'top-right');
      var popup = new gl.Popup({ closeButton: true, closeOnClick: false, maxWidth: '300px', offset: 14, anchor: 'bottom', className: 'hmap-popup' });
      var stage = el.querySelector('.hmap-stage'), sidebar = el.querySelector('.hmap-sidebar');
      var side = {};
      if (sidebar) {
        ['title', 'status', 'body', 'context', 'entries', 'error', 'footer', 'shown', 'more', 'retry'].forEach(function (k) { side[k] = sidebar.querySelector('.hmap-sidebar__' + k); });
      }
      var sidePage = 1, sideLastPage = 1, detailSeq = 0, detailAbort = null, retryAppend = false, returnFocus = null, hideTimer = null;
      var none = { top: 0, right: 0, bottom: 0, left: 0 };
      canvas.tabIndex = 0;
      function isOpen() { return !!sidebar && stage.classList.contains('has-selection'); }
      // Camera padding that keeps the selected pin clear of the panel (a bottom sheet on narrow maps).
      function inset() {
        var w = stage.clientWidth, h = stage.clientHeight, sw = sidebar.offsetWidth;
        return sw >= w * 0.8 ? { top: 0, right: 0, left: 0, bottom: Math.round(h * 0.55) } : { top: 0, bottom: 0, left: 0, right: sw + 12 };
      }
      function closeSelection(restore, instant) {
        popup.remove(); tip.remove();
        mark(selId, 'sel', false); selId = null;
        ++detailSeq;
        if (detailAbort) { detailAbort.abort(); detailAbort = null; }
        if (isOpen()) {
          stage.classList.remove('has-selection'); sidebar.removeAttribute('aria-busy');
          if (instant || reduce) { map.jumpTo({ padding: none }); } else { map.easeTo({ padding: none, duration: 450 }); }
          clearTimeout(hideTimer);
          hideTimer = setTimeout(function () {
            if (isOpen()) { return; }
            sidebar.hidden = true; side.context.innerHTML = ''; side.entries.innerHTML = '';
          }, reduce ? 0 : 360);
        }
        if (restore && returnFocus && returnFocus.isConnected) { returnFocus.focus({preventScroll:true}); }
        returnFocus = null;
      }
      function count(d) { return (d === 1 ? cfg.labels.details_count_one : cfg.labels.details_count_many).replace('%d', d); }
      function loadDetails(page, append) {
        var p = shown[selId], endpoint = el.getAttribute('data-hmap-endpoint');
        if (!sidebar || !p || !endpoint) { return; }
        if (detailAbort) { detailAbort.abort(); }
        detailAbort = window.AbortController ? new AbortController() : null;
        var n = ++detailSeq;
        sidebar.setAttribute('aria-busy', 'true'); side.error.hidden = true;
        if (append) { side.more.disabled = true; side.more.textContent = cfg.labels.details_loading; }
        else { side.status.textContent = cfg.labels.details_loading; }
        function failed() {
          sidebar.removeAttribute('aria-busy'); retryAppend = append; side.error.hidden = false;
          side.more.disabled = false; side.more.textContent = cfg.labels.details_more;
          if (!append) { side.entries.innerHTML = ''; side.status.textContent = ''; }
        }
        var url = new URL(endpoint, location.href);
        if (url.origin !== location.origin) { failed(); return; }
        if (url.searchParams.has('rest_route')) {
          url.searchParams.set('rest_route', url.searchParams.get('rest_route').replace(/\/$/, '') + '/' + p.id);
        } else { url.pathname = url.pathname.replace(/\/$/, '') + '/' + p.id; }
        url.searchParams.set('page', page); url.searchParams.set('hours', state.h);
        var nonce = el.getAttribute('data-hmap-nonce'), headers = {'Accept':'application/json'};
        if (nonce) { headers['X-WP-Nonce'] = nonce; }
        fetch(url.href, {credentials:nonce?'same-origin':'omit', headers:headers, signal:detailAbort?detailAbort.signal:undefined})
          .then(function(r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.json(); })
          .then(function(d) {
            if (n !== detailSeq || !isOpen()) { return; }
            sidebar.removeAttribute('aria-busy');
            side.title.textContent = d.title || p.t;
            if (append) {
              var tmp = document.createElement('div'), list, cur = side.entries.querySelector('.hmap-entries');
              tmp.innerHTML = d.html || ''; list = tmp.querySelector('.hmap-entries');
              while (cur && list && list.firstChild) { cur.appendChild(list.firstChild); }
            } else { side.context.innerHTML = d.context || ''; side.entries.innerHTML = d.html || ''; }
            sidePage = +d.page || 1; sideLastPage = +d.pages || 1;
            var total = +d.total || 0;
            side.status.textContent = count(total);
            side.footer.hidden = sidePage >= sideLastPage;
            side.shown.textContent = cfg.labels.details_shown.replace('%1$d', side.entries.querySelectorAll('.hmap-entry').length).replace('%2$d', total);
            side.more.disabled = false; side.more.textContent = cfg.labels.details_more;
          }).catch(function(e) {
            if (n !== detailSeq || !isOpen() || e.name === 'AbortError') { return; }
            failed();
          });
      }
      map.on('style.load', function () {
        tint(map, t);
        map.addSource('hmap', { type: 'geojson', data: fc(shown), cluster: cfg.cluster, clusterRadius: 44, clusterMaxZoom: 13 });
        var font = ['Noto Sans Regular'];
        map.getStyle().layers.some(function (l) { var f = l.layout && l.layout['text-font']; if (f && Array.isArray(f)) { font = f; return true; } return false; });
        var ring = ['step', ['get', 'point_count'], 20, 5, 25, 15, 31];
        map.addLayer({ id: 'hmap-cluster-halo', type: 'circle', source: 'hmap', filter: ['has', 'point_count'],
          paint: { 'circle-color': t.accent, 'circle-opacity': 0.16, 'circle-radius': ['+', ring, 9], 'circle-blur': 0.4 } });
        map.addLayer({ id: 'hmap-cluster', type: 'circle', source: 'hmap', filter: ['has', 'point_count'],
          paint: { 'circle-color': t.land, 'circle-radius': ring, 'circle-stroke-color': t.accent, 'circle-stroke-width': 2 } });
        map.addLayer({ id: 'hmap-cluster-count', type: 'symbol', source: 'hmap', filter: ['has', 'point_count'],
          layout: { 'text-field': ['get', 'point_count_abbreviated'], 'text-font': font, 'text-size': 13, 'text-allow-overlap': true },
          paint: { 'text-color': t.accent } });
        map.addLayer({ id: 'hmap-pulse', type: 'circle', source: 'hmap', filter: ['all', ['!', ['has', 'point_count']], ['==', ['get', 'live'], 1]],
          paint: { 'circle-color': t.live || t.accent, 'circle-opacity': 0, 'circle-radius': 8 } });
        map.addLayer({ id: 'hmap-point-halo', type: 'circle', source: 'hmap', filter: ['!', ['has', 'point_count']],
          paint: { 'circle-color': t.accent, 'circle-opacity': 0.28, 'circle-radius': 13, 'circle-blur': 0.7 } });
        var active = ['any', ['boolean', ['feature-state', 'hover'], false], ['boolean', ['feature-state', 'sel'], false]];
        map.addLayer({ id: 'hmap-point', type: 'circle', source: 'hmap', filter: ['!', ['has', 'point_count']],
          paint: { 'circle-color': ['case', ['==', ['get', 'live'], 1], t.live || t.accent, t.accent],
            'circle-radius': ['case', active, 9, 6], 'circle-radius-transition': { duration: 150 },
            'circle-stroke-color': ['case', active, '#ffffff', t.land], 'circle-stroke-width': ['case', active, 3, 2.5] } });
        // Invisible, generous target around every pin so a pin is easy to hover and tap.
        map.addLayer({ id: 'hmap-hit', type: 'circle', source: 'hmap', filter: ['!', ['has', 'point_count']],
          paint: { 'circle-color': '#000000', 'circle-opacity': 0, 'circle-radius': 22 } });
        if (!reduce) {
          var start = performance.now();
          (function pulse(now) {
            if (!map.getLayer('hmap-pulse')) { return; }
            var k = ((now - start) % 1800) / 1800;
            map.setPaintProperty('hmap-pulse', 'circle-radius', 8 + k * 18);
            map.setPaintProperty('hmap-pulse', 'circle-opacity', 0.45 * (1 - k));
            requestAnimationFrame(pulse);
          })(start);
        }
        fit(map, shown, cfg.view, false);
        el.classList.add('is-ready');
      });
      var tip = new gl.Popup({ closeButton: false, closeOnClick: false, offset: 16, className: 'hmap-tip', maxWidth: '280px' });
      var hoverId = null, selId = null;
      function mark(id, key, on) {
        if (id === null || !map.getSource('hmap')) { return; }
        var s = {}; s[key] = on;
        map.setFeatureState({ source: 'hmap', id: id }, s);
      }
      // The pin or cluster nearest the pointer within the hit radius, so near-misses still select.
      function nearest(e) {
        var r = 22, box = [[e.point.x - r, e.point.y - r], [e.point.x + r, e.point.y + r]];
        var found = map.getLayer('hmap-hit') ? map.queryRenderedFeatures(box, { layers: ['hmap-hit', 'hmap-cluster'] }) : [];
        var best = null, bestD = Infinity;
        found.forEach(function (f) {
          var q = map.project(f.geometry.coordinates), d = Math.pow(q.x - e.point.x, 2) + Math.pow(q.y - e.point.y, 2);
          if (d < bestD) { bestD = d; best = f; }
        });
        return best;
      }
      function hover(f) {
        var id = f && f.properties.cluster_id === undefined && f.properties.i !== undefined ? +f.properties.i : null;
        map.getCanvas().style.cursor = f ? 'pointer' : '';
        if (id === hoverId) { return; }
        mark(hoverId, 'hover', false);
        hoverId = id;
        mark(hoverId, 'hover', true);
        var p = id !== null ? shown[id] : null;
        if (p && p.t && id !== selId) { tip.setLngLat([p.lo, p.la]).setText(p.t).addTo(map); } else { tip.remove(); }
      }
      function openCard(i, trigger) {
        i = +i;
        var p = shown[i];
        if (!p) { return; }
        tip.remove();
        if (sidebar) {
          mark(selId, 'sel', false); selId = i; mark(selId, 'sel', true);
          if (!returnFocus) { returnFocus = trigger || canvas; }
          clearTimeout(hideTimer);
          side.title.textContent = p.g || p.t; side.status.textContent = cfg.labels.details_loading;
          side.context.innerHTML = ''; side.error.hidden = true; side.footer.hidden = true;
          side.entries.innerHTML = '<ul class="hmap-skeleton" aria-hidden="true"><li></li><li></li><li></li></ul>';
          side.body.scrollTop = 0; sidebar.classList.remove('is-scrolled');
          // Unhide first and commit the off-screen position, so the panel slides in instead of popping.
          if (sidebar.hidden) { sidebar.hidden = false; void sidebar.offsetWidth; }
          stage.classList.add('has-selection');
          map.easeTo({ center: [p.lo, p.la], padding: inset(), duration: reduce ? 0 : 450 });
          side.title.focus({preventScroll:true}); loadDetails(1, false); return;
        }
        var c = map.getContainer(), w = c.clientWidth, h = c.clientHeight;
        // The card always opens above its pin and never exceeds the map: narrow maps (phones) get a
        // narrower card, and a card taller than the map scrolls inside itself.
        popup.setMaxWidth(Math.min(300, w - 24) + 'px').setLngLat([p.lo, p.la]).setHTML(p.h);
        if (!popup.isOpen()) { popup.addTo(map); }
        var box = popup.getElement(), body = box.querySelector('.maplibregl-popup-content');
        if (body) { body.style.maxHeight = Math.max(140, h - 64) + 'px'; body.style.overflowY = 'auto'; }
        mark(selId, 'sel', false);
        selId = i;
        mark(selId, 'sel', true);
        // Glide just enough that the whole card is inside the map: room above the pin for the card's
        // height, and room on each side for half its width.
        var q = map.project([p.lo, p.la]), cw = box.offsetWidth, ch = box.offsetHeight + 16;
        var tx = cw + 24 >= w ? w / 2 : Math.max(cw / 2 + 12, Math.min(w - cw / 2 - 12, q.x));
        var ty = Math.max(ch + 12, Math.min(h - 20, q.y));
        if (Math.abs(tx - q.x) > 1 || Math.abs(ty - q.y) > 1) {
          map.easeTo({ center: map.unproject([w / 2 + (q.x - tx), h / 2 + (q.y - ty)]), duration: 450 });
        }
      }
      popup.on('close', function () { mark(selId, 'sel', false); selId = null; });
      map.on('mousemove', function (e) { hover(nearest(e)); });
      map.getCanvas().addEventListener('mouseleave', function () { hover(null); });
      map.on('click', function (e) {
        var f = nearest(e);
        if (!f) { closeSelection(false); return; }
        if (f.properties.cluster_id !== undefined) {
          closeSelection(false, true);
          map.getSource('hmap').getClusterExpansionZoom(f.properties.cluster_id).then(function (z) {
            map.easeTo({ center: f.geometry.coordinates, zoom: z + 0.5 });
          });
          return;
        }
        openCard(f.properties.i);
      });
      function apply() {
        closeSelection(false, true);
        shown = pick();
        if (map.getSource('hmap')) { map.removeFeatureState({ source: 'hmap' }); }
        hoverId = null; selId = null;
        if (map.getSource('hmap')) { map.getSource('hmap').setData(fc(shown)); }
        Array.prototype.forEach.call(controls, function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-hmap-group') === state.g ? 'true' : 'false'); });
        Array.prototype.forEach.call(hourControls, function (b) { b.setAttribute('aria-pressed', +b.getAttribute('data-hmap-hours') === state.h ? 'true' : 'false'); });
        Array.prototype.forEach.call(el.querySelectorAll('[data-hmap-select]'), function(b) {
          b.disabled = !shown.some(function(p) { return +p.id === +b.getAttribute('data-hmap-select'); });
        });
        if (select) { select.value = select.querySelector('option[value="' + CSS.escape(state.g) + '"]') ? state.g : ''; }
        if (status) { status.textContent = (shown.length === 1 ? cfg.labels.one : cfg.labels.many).replace('%d', shown.length); }
        counts();
        fit(map, shown, state.g === '' && state.h === 0 ? cfg.view : { bounds: true, fitZoom: cfg.view.fitZoom }, true);
      }
      Array.prototype.forEach.call(controls, function (b) {
        b.addEventListener('click', function () { state.g = b.getAttribute('data-hmap-group'); apply(); });
      });
      Array.prototype.forEach.call(hourControls, function (b) {
        b.addEventListener('click', function () { state.h = +b.getAttribute('data-hmap-hours'); apply(); });
      });
      if (select) { select.addEventListener('change', function () { state.g = select.value; apply(); }); }
      if (sidebar) {
        sidebar.querySelector('.hmap-sidebar__close').addEventListener('click', function() { closeSelection(true); });
        side.retry.addEventListener('click', function() { loadDetails(retryAppend ? sidePage + 1 : 1, retryAppend); });
        side.more.addEventListener('click', function() { if (sidePage < sideLastPage) { loadDetails(sidePage + 1, true); } });
        side.body.addEventListener('scroll', function() { sidebar.classList.toggle('is-scrolled', side.body.scrollTop > 4); }, { passive: true });
        el.addEventListener('keydown', function(e) { if (e.key === 'Escape' && isOpen()) { e.preventDefault(); closeSelection(true); } });
        Array.prototype.forEach.call(el.querySelectorAll('[data-hmap-select]'), function(b) {
          b.hidden = false;
          b.addEventListener('click', function() {
            var id = +b.getAttribute('data-hmap-select'), i = shown.findIndex(function(p) { return +p.id === id; });
            if (i >= 0) { openCard(i, b); }
          });
        });
      }
    }).catch(function () {
      el.classList.add('is-failed');
      var list = el.querySelector('.hmap-list');
      if (list) { list.open = true; }
    });
  }
  function boot() {
    var maps = document.querySelectorAll('.hmap[data-hmap]:not(.is-started)');
    Array.prototype.forEach.call(maps, function (el) {
      el.classList.add('is-started');
      if (!('IntersectionObserver' in window)) { init(el); return; }
      var io = new IntersectionObserver(function (entries) {
        if (entries[0].isIntersecting) { io.disconnect(); init(el); }
      }, { rootMargin: '300px' });
      io.observe(el);
    });
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
JS;
    }
}
