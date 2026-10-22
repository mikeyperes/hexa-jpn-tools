<?php

namespace Hexa\PluginCore\Search;

use Hexa\PluginCore\PublicComponents\ProfileValues;
use Hexa\PluginCore\PublicComponents\PublicComponent;
use Hexa\PluginCore\QueryFilter\QueryFilterSet;
use Hexa\PluginCore\QueryFilter\QuerySql;

/**
 * Renders the one search box, its own result cards when the profile has
 * them, and the header search box.
 *
 * The box is a plain GET form, so every state is a URL and works without
 * JavaScript. One script upgrades every box: typing and ticking fetch the
 * same page address and swap the results in place (the page's list, a
 * marked Elementor loop, or the box's own cards), with one loading look:
 * the results dim and a spinner shows until the new ones arrive. Load more
 * appends the next page of cards; on scroll when the profile asks.
 *
 * Markup, classes (`hls-*`), states and custom properties (`--hls-*`) are
 * documented in HWS Base Tools' docs/hws-search.md.
 */
final class SearchBox {
    public const STYLES = [ 'none', 'default-1', 'default-2' ];
    public const HEADER_STYLES = [ 'icon-reveal', 'field' ];
    /** Box option => custom property it sets on this one box. */
    public const LOOK = [
        'accent'       => '--hls-accent',
        'border'       => '--hls-border',
        'radius'       => '--hls-radius',
        'panel_bg'     => '--hls-panel-bg',
        'field_width'  => '--hls-field-width',
        'field_height' => '--hls-field-height',
        'font_size'    => '--hls-font-size',
    ];

    /** @var string[] Profiles rendered on this page, so a host can skip adding a second box. */
    private static array $rendered = [];

    private static int $heads = 0;

    /**
     * @param array<string,mixed> $options style (none|default-1|default-2), placeholder, filters (keys to show),
     *                                     target, class, hidden (extra hidden fields), results_one, results_many, empty,
     *                                     clear, post_type, category, tag, author (what a box on an ordinary page
     *                                     searches) and the LOOK keys.
     */
    public static function render( string $profile_id, array $options = [] ): string {
        $scope = array_intersect_key( array_map( static fn( $value ): string => is_scalar( $value ) ? (string) $value : '', $options ), array_flip( [ 'post_type', 'category', 'tag', 'author' ] ) );
        $state = Search::state( $profile_id, null, $scope );
        if ( null === $state || ! PublicComponent::can_view( $state['profile']['public'] ) ) {
            return '';
        }

        $profile = $state['profile'];
        $context = $state['context'];
        $cards   = 'cards' === $profile['results'];
        $site    = ! $cards && null !== $context && 'search' === $context['type'];
        $style   = in_array( $options['style'] ?? '', self::STYLES, true ) ? (string) $options['style'] : 'default-1';
        $labels  = array_merge( $profile['labels'], array_filter( array_intersect_key( $options, array_flip( [ 'results_one', 'results_many', 'empty', 'clear' ] ) ), static fn( $value ): bool => is_string( $value ) && '' !== trim( $value ) ) );
        $dom_id  = 'hls-' . $profile['id'];
        $target  = $cards ? '#' . $dom_id . '-results' : SearchProfile::selector( (string) ( $options['target'] ?? $profile['target'] ) );

        $placeholder = trim( (string) ( $options['placeholder'] ?? '' ) );
        if ( '' === $placeholder ) {
            $title       = (string) ( $context['title'] ?? '' );
            $placeholder = '' !== $title ? sprintf( $labels['placeholder_context'], $title ) : $labels['placeholder'];
        }

        $shown   = array_filter( array_map( 'trim', is_array( $options['filters'] ?? null ) ? $options['filters'] : explode( ',', (string) ( $options['filters'] ?? '' ) ) ) );
        $filters = [] !== $shown ? array_intersect_key( $state['filters'], array_flip( $shown ) ) : $state['filters'];

        global $wpdb;
        $counts = [];
        if ( is_object( $wpdb ) && [] !== $filters ) {
            $counts = QueryFilterSet::counts( $wpdb, $filters, $state['values'], $state['scope'], static fn( array $values ): string => Search::ids_sql( $state, $values ) );
        }

        $base = PublicComponent::sanitize_base( self::page_url(), array_merge( Search::PARAMS, [ 's', 'paged' ] ) );
        [ $base_path, $base_args ] = PublicComponent::split_base( $base );
        $hidden = array_merge( $base_args, array_filter( (array) ( $options['hidden'] ?? [] ), 'is_scalar' ) );

        $results = '';
        $active  = $state['active'] || ( $site && '' !== $state['text'] );
        if ( $cards ) {
            $payload = self::cards( $state, $base, $labels );
            $results = '<div class="hls-results" id="' . esc_attr( $dom_id ) . '-results" aria-live="polite">' . $payload['html'] . '</div>';
            $status  = $payload['summary'];
        } else {
            $total  = $active && is_object( $wpdb ) ? QuerySql::count( $wpdb, Search::ids_sql( $state, $state['values'] ) ) : 0;
            $status = $active ? ( 0 === $total ? $labels['empty'] : sprintf( 1 === $total ? $labels['results_one'] : $labels['results_many'], $total ) ) : '';
        }

        $look  = self::look( $options );
        // A box with its own cards wraps form and cards in one element that carries the profile's class.
        $own   = trim( $profile['class'] . ' ' . ProfileValues::classes( (string) ( $options['class'] ?? '' ) ) );
        $class = trim( 'hls hls--' . $style . ' hls--' . $profile['results'] . ( $cards ? '' : ' ' . $own ) . ( '' !== $look ? ' ' . $look['class'] : '' ) );

        $html = '<form class="' . esc_attr( $class ) . '" id="' . esc_attr( $dom_id ) . '" role="search" method="get" action="' . esc_url( $base_path ) . '"'
            . ' data-hls-target="' . esc_attr( $target ) . '" data-hls-min="' . esc_attr( (string) $profile['min_chars'] ) . '" data-hls-error="' . esc_attr( $labels['error'] ) . '"'
            . ( $cards && 'infinite' === $profile['load_mode'] ? ' data-hls-auto="1"' : '' ) . '>'
            . PublicComponent::hidden_inputs( array_map( 'strval', $hidden ) )
            . '<input type="hidden" name="' . esc_attr( Search::PARAM_PROFILE ) . '" value="' . esc_attr( $profile['id'] ) . '">'
            . '<div class="hls-bar">'
            . '<label class="hls-field hls-field--text"><span class="hls-visually-hidden">' . esc_html( $labels['search'] ) . '</span>'
            . self::icon( 'hls-icon' )
            . '<input class="hls-input" type="search" name="' . esc_attr( $site ? 's' : Search::PARAM_TEXT ) . '" value="' . esc_attr( $state['text'] ) . '" placeholder="' . esc_attr( $placeholder ) . '" autocomplete="off" enterkeyhint="search"></label>'
            . '<div class="hls-filters">' . QueryFilterSet::controls( $filters, $state['values'], Search::PARAM_FILTER, 'hls', $state['scope'], $counts ) . '</div>'
            . self::sort( $state, $labels )
            . '<a class="hls-clear" href="' . esc_url( $site ? PublicComponent::url( $base, [ 's' => '' ] ) : $base ) . '"' . ( $active ? '' : ' hidden' ) . '>' . esc_html( $labels['clear'] ) . '</a>'
            . '<button class="hls-submit hls-visually-hidden" type="submit">' . esc_html( $labels['search'] ) . '</button>'
            . '</div>'
            . '<p class="hls-status" role="status" aria-live="polite"' . ( '' === $status ? ' hidden' : '' ) . '>' . esc_html( $status ) . '</p>'
            . '</form>' . $results;
        if ( $cards ) {
            $html = '<div class="' . esc_attr( trim( 'hls-wrap ' . $own ) ) . '">' . $html . '</div>';
        }

        self::$rendered[] = $profile['id'];

        // Visitor text is echoed back; keep it inert to a later do_shortcode() pass.
        return self::assets( $style ) . ( '' !== $look ? '<style>.' . $look['class'] . '.hls{' . $look['css'] . '}</style>' : '' ) . PublicComponent::inert( $html );
    }

    /**
     * The header search: a magnifying glass that opens a field in place
     * (`icon-reveal`) or an always-open field (`field`). It submits to the
     * site's search page; it never opens a popup.
     *
     * @param array<string,mixed> $options style, placeholder, label, accent, id, hidden (extra hidden fields).
     */
    public static function header( array $options = [] ): string {
        $style       = in_array( $options['style'] ?? '', self::HEADER_STYLES, true ) ? (string) $options['style'] : 'icon-reveal';
        $placeholder = trim( (string) ( $options['placeholder'] ?? '' ) ) ?: 'Search…';
        $label       = trim( (string) ( $options['label'] ?? '' ) ) ?: 'Search';
        $accent      = preg_match( '/^#[0-9a-fA-F]{3,8}$/', (string) ( $options['accent'] ?? '' ) ) ? (string) $options['accent'] : '';
        $id          = 'hls-head-' . ( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) ( $options['id'] ?? '' ) ) ) ?: (string) ( ++self::$heads ) );
        $action      = function_exists( 'home_url' ) ? home_url( '/' ) : '/';
        $value       = function_exists( 'get_search_query' ) && function_exists( 'is_search' ) && is_search() ? (string) get_search_query( false ) : '';

        $html = '<form class="hls-head hls-head--' . esc_attr( $style ) . '" id="' . esc_attr( $id ) . '" role="search" method="get" action="' . esc_url( $action ) . '"' . ( '' !== $accent ? ' style="--hls-accent:' . esc_attr( $accent ) . '"' : '' ) . '>'
            . ( 'icon-reveal' === $style ? '<button class="hls-head-toggle" type="button" aria-expanded="false" aria-controls="' . esc_attr( $id ) . '-field" aria-label="' . esc_attr( $label ) . '">' . self::icon( 'hls-head-icon' ) . '</button>' : '' )
            . '<label class="hls-head-field" id="' . esc_attr( $id ) . '-field"><span class="hls-visually-hidden">' . esc_html( $label ) . '</span>'
            . ( 'field' === $style ? self::icon( 'hls-head-icon' ) : '' )
            . '<input class="hls-head-input" type="search" name="s" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" autocomplete="off" enterkeyhint="search"></label>'
            . PublicComponent::hidden_inputs( array_map( 'strval', array_filter( (array) ( $options['hidden'] ?? [] ), 'is_scalar' ) ) )
            . '<button class="hls-visually-hidden" type="submit">' . esc_html( $label ) . '</button></form>';

        return self::assets( 'none' ) . PublicComponent::inert( $html );
    }

    /**
     * A still picture of the box in one look, for settings screens: the real
     * markup and CSS with sample filters (one ticked), never connected to a
     * query or the live script.
     */
    public static function preview( string $style ): string {
        $style = in_array( $style, self::STYLES, true ) ? $style : 'default-1';
        $html  = '<form class="hls hls--' . esc_attr( $style ) . '" data-hls-ready="1" onsubmit="return false" style="margin:0">'
            . '<div class="hls-bar"><label class="hls-field hls-field--text"><span class="hls-visually-hidden">Search</span>' . self::icon( 'hls-icon' )
            . '<input class="hls-input" type="search" value="" placeholder="Search Business" tabindex="-1"></label><div class="hls-filters">';
        foreach ( [ 'Category' => 1, 'Tag' => 0, 'Author' => 0 ] as $label => $ticked ) {
            $html .= '<details class="hls-filter' . ( $ticked ? ' is-active' : '' ) . '"><summary class="hls-filter-toggle" tabindex="-1"><span class="hls-filter-label">' . $label . '</span>'
                . ( $ticked ? '<span class="hls-filter-badge">1</span>' : '' ) . '</summary></details>';
        }

        return $html . '</div><a class="hls-clear" href="#" tabindex="-1">Clear</a></div><p class="hls-status">12 stories</p></form>'
            . '<style>' . self::css( $style ) . '</style>';
    }

    /** Whether a profile's box was already printed on this page. */
    public static function rendered( string $profile_id ): bool {
        return in_array( $profile_id, self::$rendered, true );
    }

    /**
     * The box's own cards for one state, plus its summary line. Only default
     * views (no text, no filters) are cached, so visitors cannot grow the
     * cache with arbitrary parameters.
     *
     * @return array{html:string,summary:string}
     */
    public static function cards( array $state, string $base, array $labels ): array {
        $profile   = $state['profile'];
        $cache_key = '';
        if ( $profile['cache_ttl'] > 0 && ! $state['searched'] && [] === $state['values'] && function_exists( 'get_transient' ) ) {
            $cache_key = 'hls_' . md5( (string) wp_json_encode( [ $profile['id'], $profile['cache_version'], $state['sort'], $state['page'], $state['context']['token'] ?? '' ] ) );
            $cached    = get_transient( $cache_key );
        }
        if ( isset( $cached ) && is_array( $cached ) && isset( $cached['result'], $cached['items'] ) ) {
            $result = (array) $cached['result'];
            $items  = (string) $cached['items'];
        } else {
            $result = Search::results( $state );
            $data   = [] !== $result['ids'] && null !== $profile['prepare'] ? (array) call_user_func( $profile['prepare'], $result['ids'], $state ) : [];
            $items  = '';
            foreach ( $result['ids'] as $id ) {
                $items .= '<li class="hls-item">' . (string) call_user_func( $profile['render_item'], $id, (array) ( $data[ $id ] ?? [] ), $state ) . '</li>';
            }
            if ( '' !== $cache_key && $result['page'] === $state['page'] ) {
                set_transient( $cache_key, [ 'result' => $result, 'items' => $items ], $profile['cache_ttl'] );
            }
        }

        $html = '' === $items
            ? '<p class="hls-empty">' . esc_html( $labels['empty'] ) . '</p>'
            : '<ol class="hls-list" start="' . esc_attr( (string) ( ( $result['page'] - 1 ) * $result['per_page'] + 1 ) ) . '">' . $items . '</ol>' . self::pagination( $state, $result, $base, $labels );

        $summary = sprintf( 1 === (int) $result['total'] ? $labels['results_one'] : $labels['results_many'], (int) $result['total'] );
        $length  = function_exists( 'mb_strlen' ) ? mb_strlen( $state['text'], 'UTF-8' ) : strlen( $state['text'] );
        if ( $state['searched'] ) {
            $summary .= sprintf( $labels['results_for'], $state['text'] );
        } elseif ( '' !== $state['text'] && $length < (int) $profile['min_chars'] ) {
            $summary .= ' · ' . sprintf( $labels['min_chars'], $profile['min_chars'] );
        }

        return [ 'html' => $html, 'summary' => $summary ];
    }

    /**
     * The box's CSS for one look: `none` (only what the dropdowns, loader and
     * cards need to work), `default-1` (compact boxed field and filter
     * buttons) or `default-2` (underlined field, text filters). Rules are
     * scoped `.hls.hls …` so a theme's general field and checkbox styles
     * cannot reshape the box; a site restyles it by setting the --hls-*
     * custom properties on `.hls`, or picks `none` and styles it from scratch.
     */
    public static function css( string $style = 'default-1' ): string {
        $css = ':where(.hls,.hls-head,.hls-results,.hexa-listing-results){--hls-accent:currentColor;--hls-border:rgba(127,127,127,.35);--hls-radius:6px;--hls-panel-bg:Canvas;--hls-field-height:36px;--hls-field-width:360px;--hls-font-size:14px;--hls-small:13px;--hls-loading-opacity:.4;--hls-loader-size:40px;--hls-loader-surface:#fff;--hls-loader-offset:64px}'
            . '.hls.hls{margin:0 0 20px;font:inherit;color:inherit}'
            . '.hls.hls .hls-bar{display:flex;flex-wrap:wrap;align-items:center;gap:8px}'
            . '.hls.hls .hls-field--text{position:relative;margin:0}'
            . '.hls.hls .hls-filters{display:contents}'
            . '.hls.hls .hls-filter{position:relative}'
            . '.hls.hls .hls-filter-toggle{display:flex;align-items:center;gap:6px;cursor:pointer;list-style:none;white-space:nowrap;margin:0;line-height:1.2;font-weight:inherit;background:none}'
            . '.hls.hls .hls-filter-toggle::-webkit-details-marker{display:none}'
            . '.hls.hls .hls-filter-panel{position:absolute;z-index:50;top:calc(100% + 6px);left:0;min-width:200px;max-width:min(320px,90vw);max-height:300px;overflow:auto;padding:6px;background:var(--hls-panel-bg);border:1px solid var(--hls-border);border-radius:var(--hls-radius);box-shadow:0 10px 28px rgba(0,0,0,.12)}'
            . '.hls.hls .hls-option{display:flex;width:auto;margin:0;font-weight:inherit;line-height:1.3;text-transform:none;letter-spacing:normal;align-items:center;gap:8px;min-height:34px;padding:0 8px 0 calc(8px + var(--hls-depth,0) * 14px);cursor:pointer;font-size:var(--hls-small)}'
            . '.hls.hls .hls-option input{appearance:auto;-webkit-appearance:auto;flex:none;width:15px;height:15px;margin:0;padding:0;border:0;box-shadow:none;accent-color:var(--hls-accent)}'
            . '.hls.hls .hls-option-label{flex:1}.hls.hls .hls-count{opacity:.6;font-size:12px}'
            . '.hls.hls .hls-clear[hidden],.hls.hls .hls-status[hidden]{display:none}'
            . '.hls.hls .hls-input::-webkit-search-cancel-button{-webkit-appearance:none;appearance:none}'
            . '.hls.hls .hls-label{display:none}'
            . '.hls.hls .hls-field{margin:0}'
            . '.hls.hls .hls-toggle{display:inline-flex;align-items:center;gap:8px;margin:0;cursor:pointer;white-space:nowrap;font-weight:inherit}'
            . '.hls.hls .hls-toggle input{appearance:auto;-webkit-appearance:auto;flex:none;width:15px;height:15px;margin:0;accent-color:var(--hls-accent)}'
            . '.hls.hls .hls-range,.hls.hls .hls-range-inputs{display:inline-flex;align-items:center;gap:6px;margin:0}'
            . '.hls.hls .hls-select,.hls.hls .hls-range input{box-sizing:border-box;margin:0;max-width:100%}'
            . '.hls-visually-hidden{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}'
            // The one loading look: the results dim and a spinner shows until the new ones arrive.
            . '.hls-loading{position:relative;pointer-events:none}'
            . '.hls-loading>*{opacity:var(--hls-loading-opacity);transition:opacity .2s}'
            . '.hls-loading::after{content:"";position:absolute;z-index:5;top:min(var(--hls-loader-offset),40%);left:50%;width:var(--hls-loader-size);height:var(--hls-loader-size);margin-left:calc(var(--hls-loader-size) / -2);box-sizing:border-box;border-radius:50%;border:3px solid rgba(127,127,127,.25);border-top-color:var(--hls-accent);background:var(--hls-loader-surface);box-shadow:0 0 0 6px var(--hls-loader-surface),0 10px 24px -10px rgba(0,0,0,.45);animation:hls-spin .7s linear infinite}'
            . '@keyframes hls-spin{to{transform:rotate(360deg)}}'
            . '.hls-results .hls-list{list-style:none;margin:0;padding:0;display:grid;gap:16px}'
            . '.hls-results .hls-item{margin:0}'
            . '.hls-results .hls-pages{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-top:24px}'
            . '.hls-results .hls-page{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 10px;border:1px solid var(--hls-border);border-radius:var(--hls-radius);color:inherit;text-decoration:none}'
            . '.hls-results .hls-page[aria-current]{border-color:var(--hls-accent);font-weight:600}'
            . '.hls-results .hls-more{display:inline-flex;align-items:center;height:30px;margin-top:24px;padding:0 12px;border:1px solid var(--hls-border);border-radius:var(--hls-radius);color:inherit;font-size:11.5px;text-decoration:none}'
            . '.hls-head{position:relative;display:inline-flex;align-items:center;margin:0}'
            . '.hls-head .hls-head-toggle{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;padding:0;border:1px solid var(--hls-border);border-radius:50%;background:transparent;color:inherit;cursor:pointer}'
            . '.hls-head .hls-head-field{position:relative;display:inline-flex;align-items:center;margin:0}'
            . '.hls-head .hls-head-input{box-sizing:border-box;height:38px;margin:0;padding:0 14px;border:1px solid var(--hls-border);border-radius:19px;background:transparent;color:inherit;font:inherit;font-size:14px}'
            . '.hls-head .hls-head-input:focus{outline:none;border-color:var(--hls-accent)}'
            . '.hls-head--field .hls-head-icon{position:absolute;left:12px;opacity:.55;pointer-events:none}.hls-head--field .hls-head-input{padding-left:34px;width:220px}'
            . '.hls-head--icon-reveal{flex-direction:row-reverse}.hls-head--icon-reveal .hls-head-field{width:0;overflow:hidden;transition:width .25s ease}.hls-head--icon-reveal.is-open .hls-head-field{width:220px;margin-right:8px}.hls-head--icon-reveal .hls-head-input{width:220px}'
            . '@media(max-width:767px){.hls.hls .hls-field--text{flex-basis:100%}.hls-head--icon-reveal.is-open .hls-head-field{width:min(60vw,220px)}}'
            . '@media(prefers-reduced-motion:reduce){.hls-loading>*,.hls-head--icon-reveal .hls-head-field{transition:none}.hls-loading::after{animation-duration:2s}}';

        if ( 'default-1' === $style ) {
            $css .= '.hls.hls.hls--default-1 .hls-field--text{flex:0 1 var(--hls-field-width)}'
                . '.hls.hls.hls--default-1 .hls-icon{position:absolute;left:11px;top:50%;transform:translateY(-50%);opacity:.55;pointer-events:none}'
                . '.hls.hls.hls--default-1 .hls-input{box-sizing:border-box;width:100%;height:var(--hls-field-height);margin:0;padding:0 12px 0 34px;border:1px solid var(--hls-border);border-radius:var(--hls-radius);background:transparent;color:inherit;font:inherit;font-size:var(--hls-font-size);box-shadow:none}'
                . '.hls.hls.hls--default-1 .hls-input:focus{outline:none;border-color:var(--hls-accent)}'
                . '.hls.hls.hls--default-1 .hls-filter-toggle{height:var(--hls-field-height);padding:0 12px;border:1px solid var(--hls-border);border-radius:var(--hls-radius);font-size:var(--hls-small);transition:border-color .2s}'
                . '.hls.hls.hls--default-1 .hls-filter[open] .hls-filter-toggle,.hls.hls.hls--default-1 .hls-filter.is-active .hls-filter-toggle,.hls.hls.hls--default-1 .hls-filter-toggle:hover{border-color:var(--hls-accent)}'
                . '.hls.hls.hls--default-1 .hls-filter-toggle::after{content:"";width:5px;height:5px;border-right:1.5px solid currentColor;border-bottom:1.5px solid currentColor;transform:translateY(-2px) rotate(45deg);opacity:.55}'
                . '.hls.hls.hls--default-1 .hls-filter-badge{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;border:1px solid currentColor;border-radius:9px;background:none;color:inherit;font-size:11px;font-weight:600;line-height:1}'
                . '.hls.hls.hls--default-1 .hls-select{height:var(--hls-field-height);padding:0 10px;border:1px solid var(--hls-border);border-radius:var(--hls-radius);background:transparent;color:inherit;font:inherit;font-size:var(--hls-small)}'
                . '.hls.hls.hls--default-1 .hls-toggle{height:var(--hls-field-height);padding:0 12px;border:1px solid var(--hls-border);border-radius:var(--hls-radius);font-size:var(--hls-small);transition:border-color .2s}'
                . '.hls.hls.hls--default-1 .hls-toggle:hover,.hls.hls.hls--default-1 .hls-toggle:has(input:checked){border-color:var(--hls-accent)}'
                . '.hls.hls.hls--default-1 .hls-range input{height:var(--hls-field-height);padding:0 10px;border:1px solid var(--hls-border);border-radius:var(--hls-radius);background:transparent;color:inherit;font:inherit;font-size:var(--hls-small)}'
                . '.hls.hls.hls--default-1 .hls-clear{font-size:var(--hls-small);color:inherit;opacity:.7;text-decoration:underline;text-underline-offset:3px}'
                . '.hls.hls.hls--default-1 .hls-status{margin:10px 0 0;font-size:var(--hls-small);opacity:.7}'
                . '.hls.hls.hls--default-1 .hls-option:hover{background:rgba(127,127,127,.1);border-radius:4px}';
        } elseif ( 'default-2' === $style ) {
            $css .= '.hls.hls.hls--default-2 .hls-bar{gap:18px}'
                . '.hls.hls.hls--default-2 .hls-field--text{flex:0 1 var(--hls-field-width)}'
                . '.hls.hls.hls--default-2 .hls-icon{position:absolute;left:0;top:50%;transform:translateY(-50%);opacity:.6;pointer-events:none}'
                . '.hls.hls.hls--default-2 .hls-input{box-sizing:border-box;width:100%;height:var(--hls-field-height);margin:0;padding:0 0 0 26px;border:0;border-bottom:1px solid var(--hls-border);border-radius:0;background:transparent;color:inherit;font:inherit;font-size:var(--hls-font-size);box-shadow:none}'
                . '.hls.hls.hls--default-2 .hls-input:focus{outline:none;border-bottom-color:var(--hls-accent)}'
                . '.hls.hls.hls--default-2 .hls-filter-toggle{height:var(--hls-field-height);font-size:12px;letter-spacing:.08em;text-transform:uppercase}'
                . '.hls.hls.hls--default-2 .hls-filter-toggle::after{content:"+";opacity:.6}'
                . '.hls.hls.hls--default-2 .hls-filter[open] .hls-filter-toggle::after{content:"−"}'
                . '.hls.hls.hls--default-2 .hls-filter.is-active .hls-filter-label{text-decoration:underline;text-underline-offset:4px}'
                . '.hls.hls.hls--default-2 .hls-filter-badge{font-size:11px;opacity:.7}'
                . '.hls.hls.hls--default-2 .hls-filter-badge::before{content:"("}.hls.hls.hls--default-2 .hls-filter-badge::after{content:")"}'
                . '.hls.hls.hls--default-2 .hls-select{height:var(--hls-field-height);border:0;border-bottom:1px solid var(--hls-border);background:transparent;color:inherit;font:inherit;font-size:12px}'
                . '.hls.hls.hls--default-2 .hls-toggle{height:var(--hls-field-height);font-size:12px;letter-spacing:.08em;text-transform:uppercase}'
                . '.hls.hls.hls--default-2 .hls-range input{height:var(--hls-field-height);padding:0 4px;border:0;border-bottom:1px solid var(--hls-border);background:transparent;color:inherit;font:inherit;font-size:12px}'
                . '.hls.hls.hls--default-2 .hls-clear{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:inherit;opacity:.7}'
                . '.hls.hls.hls--default-2 .hls-status{margin:10px 0 0;font-size:12px;letter-spacing:.06em;text-transform:uppercase;opacity:.6}';
        } else {
            $css .= '.hls.hls.hls--none .hls-icon{display:none}';
        }

        return $css;
    }

    /** The one script: every search box, its refresh, the loader, Load more and the header box. */
    public static function js(): string {
        return <<<'JS'
(function(){
if(window.hexaSearch)return;window.hexaSearch=1;
var each=function(list,fn){Array.prototype.forEach.call(list,fn);};
function restart(el){if(!window.jQuery||!window.elementorFrontend||!elementorFrontend.elementsHandler)return;var $=window.jQuery;$(el).find('.elementor-element').addBack('.elementor-element').each(function(){try{elementorFrontend.elementsHandler.runReadyTrigger($(this));}catch(e){}});}
function init(form){
if(form.getAttribute('data-hls-ready'))return;form.setAttribute('data-hls-ready','1');
if(!window.fetch||!window.DOMParser||!window.URLSearchParams)return;
var sel=form.getAttribute('data-hls-target')||'.hexa-listing-results',min=parseInt(form.getAttribute('data-hls-min')||'2',10);
var text=form.querySelector('.hls-input'),timer=null,controller=null,seq=0,last=location.pathname+location.search;
function url(page){var p=new URLSearchParams(),on=false;each(form.elements,function(el){if(!el.name||el.disabled||el.type==='submit')return;if((el.type==='checkbox'||el.type==='radio')&&!el.checked)return;var v=(el.value||'').trim();if(el.tagName==='SELECT'&&el.getAttribute('data-hls-default')===v)return;if(v!==''||el.name==='s'){p.append(el.name,v);if(el.type!=='hidden'&&v!=='')on=true;}});if(page>1){p.set('hp',page);on=true;}if(!on)p.delete('hs');var a=form.getAttribute('action')||location.pathname,q=p.toString();return a+(q?(a.indexOf('?')>-1?'&':'?')+q:'');}
function busy(on){form.setAttribute('aria-busy',on?'true':'false');each(document.querySelectorAll(sel),function(t){t.classList.toggle('hls-loading',on);t.setAttribute('aria-busy',on?'true':'false');});}
function sync(doc){var nf=doc.getElementById(form.id);if(!nf)return;var box=form.querySelector('.hls-filters'),nb=nf.querySelector('.hls-filters');if(box&&nb){var open=[];each(box.querySelectorAll('details[open]'),function(d){open.push(d.getAttribute('data-hls-key'));});box.innerHTML=nb.innerHTML;each(box.querySelectorAll('details'),function(d){if(open.indexOf(d.getAttribute('data-hls-key'))>-1)d.open=true;});}
['.hls-status','.hls-clear'].forEach(function(s){var a=form.querySelector(s),b=nf.querySelector(s);if(a&&b){a.textContent=b.textContent;if(b.hasAttribute('hidden'))a.setAttribute('hidden','');else a.removeAttribute('hidden');}});}
function go(u,append){if(!append&&u===last)return;if(!append)last=u;var id=++seq;if(controller)controller.abort();controller=window.AbortController?new AbortController():null;var current=document.querySelectorAll(sel);if(!current.length){location.assign(u);return;}busy(true);
fetch(u,{credentials:'same-origin',headers:{'Accept':'text/html'},signal:controller?controller.signal:undefined}).then(function(r){if(!r.ok&&r.status!==404)throw new Error('HTTP '+r.status);return r.text();}).then(function(html){if(id!==seq)return;var doc=new DOMParser().parseFromString(html,'text/html');var fresh=doc.querySelectorAll(sel);
each(current,function(t,i){var n=fresh[i];if(!n){if(!append)t.innerHTML='';return;}if(append){var list=t.querySelector('.hls-list'),nl=n.querySelector('.hls-list');if(list&&nl){each(nl.children,function(c){list.appendChild(document.importNode(c,true));});var pg=t.querySelector('.hls-pages,.hls-more'),np=n.querySelector('.hls-pages,.hls-more');if(pg)pg.parentNode.removeChild(pg);if(np)t.appendChild(document.importNode(np,true));restart(t);watch();return;}}var m=document.importNode(n,true);t.parentNode.replaceChild(m,t);restart(m);});
if(!append)sync(doc);if(window.history&&history.replaceState)history.replaceState(history.state,'',u);watch();
document.dispatchEvent(new CustomEvent('hexa-search:updated',{detail:{form:form,url:u}}));document.dispatchEvent(new CustomEvent('hexa-listing:updated',{detail:{form:form,url:u}}));
}).catch(function(e){if(e&&e.name==='AbortError')return;if(id!==seq)return;last=null;var s=form.querySelector('.hls-status');if(s){s.textContent=form.getAttribute('data-hls-error')||'';s.removeAttribute('hidden');}}).then(function(){if(id===seq)busy(false);});}
var io=window.IntersectionObserver&&form.getAttribute('data-hls-auto')?new IntersectionObserver(function(es){es.forEach(function(en){if(en.isIntersecting&&form.getAttribute('aria-busy')!=='true'){io.unobserve(en.target);en.target.click();}});},{rootMargin:'400px 0px'}):null;
function watch(){if(!io)return;each(document.querySelectorAll(sel+' .hls-more'),function(a){io.observe(a);});}
document.addEventListener('click',function(e){var a=e.target.closest?e.target.closest('.hls-more,.hls-page'):null;if(!a||!a.href)return;var t=a.closest(sel);if(!t)return;e.preventDefault();go(a.href,a.classList.contains('hls-more'));});
form.addEventListener('submit',function(e){e.preventDefault();window.clearTimeout(timer);go(url());});
if(text){text.addEventListener('input',function(){window.clearTimeout(timer);var v=text.value.trim();if(v&&v.length<min)return;timer=window.setTimeout(function(){go(url());},350);});}
form.addEventListener('change',function(e){if(e.target===text)return;window.clearTimeout(timer);go(url());});
var clear=form.querySelector('.hls-clear');if(clear){clear.addEventListener('click',function(e){e.preventDefault();if(text)text.value='';each(form.querySelectorAll('.hls-filters input,.hls-filters select'),function(el){if(el.type==='checkbox')el.checked=false;else el.value='';});each(form.querySelectorAll('details[open]'),function(d){d.open=false;});go(url());});}
document.addEventListener('click',function(e){each(form.querySelectorAll('details[open]'),function(d){if(!d.contains(e.target))d.open=false;});});
form.addEventListener('toggle',function(e){var d=e.target;if(!d||d.tagName!=='DETAILS'||!d.open)return;var p=d.querySelector('.hls-filter-panel');if(!p)return;p.style.left='';p.style.right='';if(p.getBoundingClientRect().right>document.documentElement.clientWidth-8){p.style.left='auto';p.style.right='0';}},true);
form.addEventListener('keydown',function(e){if(e.key==='Escape'){var d=e.target.closest?e.target.closest('details'):null;if(d){d.open=false;var s=d.querySelector('summary');if(s)s.focus();}}});
watch();
}
function head(form){if(form.getAttribute('data-hls-ready'))return;form.setAttribute('data-hls-ready','1');var b=form.querySelector('.hls-head-toggle'),i=form.querySelector('.hls-head-input');if(!b||!i)return;
b.addEventListener('click',function(){if(form.classList.contains('is-open')&&i.value.trim()){form.submit();return;}var open=!form.classList.contains('is-open');form.classList.toggle('is-open',open);b.setAttribute('aria-expanded',open?'true':'false');if(open)i.focus();});
i.addEventListener('keydown',function(e){if(e.key==='Escape'){form.classList.remove('is-open');b.setAttribute('aria-expanded','false');b.focus();}});
document.addEventListener('click',function(e){if(!form.contains(e.target)&&!i.value.trim()){form.classList.remove('is-open');b.setAttribute('aria-expanded','false');}});}
function boot(){each(document.querySelectorAll('form.hls'),init);each(document.querySelectorAll('form.hls-head'),head);}
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',boot);}else{boot();}
})();
JS;
    }

    /** @return array{class:string,css:string}|'' One box's own custom properties from its LOOK options. */
    private static function look( array $options ) {
        $css = '';
        foreach ( self::LOOK as $key => $property ) {
            $value = trim( is_scalar( $options[ $key ] ?? null ) ? (string) $options[ $key ] : '' );
            if ( '' === $value || ! preg_match( '/^[#a-zA-Z0-9.,%()\s-]{1,40}$/', $value ) ) {
                continue;
            }
            $unitless = preg_match( '/^\d+(\.\d+)?$/', $value ) && ! in_array( $key, [ 'accent', 'border', 'panel_bg' ], true );
            $css     .= $property . ':' . $value . ( $unitless ? 'px' : '' ) . ';';
        }

        return '' !== $css ? [ 'class' => 'hls-look-' . substr( md5( $css ), 0, 8 ), 'css' => $css ] : '';
    }

    private static function sort( array $state, array $labels ): string {
        $sorts = $state['profile']['sorts'];
        if ( 'cards' !== $state['profile']['results'] || count( $sorts ) < 2 ) {
            return '';
        }
        $html = '<label class="hls-field hls-field--sort"><span class="hls-visually-hidden">' . esc_html( $labels['sort'] ) . '</span>'
            . '<select class="hls-select" name="' . esc_attr( Search::PARAM_SORT ) . '" data-hls-default="' . esc_attr( (string) $state['profile']['default_sort'] ) . '">';
        foreach ( $sorts as $key => $sort ) {
            $html .= '<option value="' . esc_attr( $key ) . '"' . ( $key === $state['sort'] ? ' selected' : '' ) . '>' . esc_html( $sort['label'] ) . '</option>';
        }

        return $html . '</select></label>';
    }

    private static function pagination( array $state, array $result, string $base, array $labels ): string {
        if ( $result['pages'] <= 1 ) {
            return '';
        }
        $page = (int) $result['page'];
        if ( 'numbered' !== $state['profile']['load_mode'] ) {
            return $page < $result['pages']
                ? '<a class="hls-more" href="' . esc_url( PublicComponent::url( $base, Search::args( $state, $page + 1 ) ) ) . '">' . esc_html( $labels['load_more'] ) . '</a>'
                : '';
        }

        $html = '<nav class="hls-pages" aria-label="' . esc_attr( $labels['pagination'] ) . '">';
        if ( $page > 1 ) {
            $html .= '<a class="hls-page" rel="prev" href="' . esc_url( PublicComponent::url( $base, Search::args( $state, $page - 1 ) ) ) . '">' . esc_html( $labels['previous'] ) . '</a>';
        }
        for ( $i = max( 1, $page - 2 ); $i <= min( (int) $result['pages'], $page + 2 ); $i++ ) {
            $html .= $i === $page
                ? '<span class="hls-page" aria-current="page">' . $i . '</span>'
                : '<a class="hls-page" href="' . esc_url( PublicComponent::url( $base, Search::args( $state, $i ) ) ) . '">' . $i . '</a>';
        }
        if ( $page < $result['pages'] ) {
            $html .= '<a class="hls-page" rel="next" href="' . esc_url( PublicComponent::url( $base, Search::args( $state, $page + 1 ) ) ) . '">' . esc_html( $labels['next'] ) . '</a>';
        }

        return $html . '</nav>';
    }

    private static function icon( string $class ): string {
        return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="M20 20l-3.5-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
    }

    /** The current page's address without its page number. */
    private static function page_url(): string {
        $uri   = PublicComponent::request_uri();
        $path  = (string) preg_replace( '#/page/\d+/?$#', '/', (string) parse_url( $uri, PHP_URL_PATH ) );
        $query = (string) parse_url( $uri, PHP_URL_QUERY );

        return $path . ( '' !== $query ? '?' . $query : '' );
    }

    /**
     * Style and script travel with every box: another plugin may render the
     * page content once and discard it (a description or excerpt builder), so
     * "print once per request" could leave the visible box bare. The script
     * runs once per page.
     */
    private static function assets( string $style ): string {
        return '<style class="hexa-search-css">' . self::css( $style ) . '</style><script class="hexa-search-js" data-cfasync="false">' . self::js() . '</script>';
    }
}
