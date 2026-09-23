<?php

namespace Hexa\PluginCore\Calendar;

use Hexa\PluginCore\PublicComponents\ProfileValues;
use Hexa\PluginCore\PublicComponents\PublicComponent;
use Hexa\PluginCore\QueryFilter\DateRangeFilterType;
use Hexa\PluginCore\QueryFilter\QueryFilterSet;

/**
 * Renders a calendar component and its month fragments.
 *
 * The current month is always rendered on the server, so the calendar works
 * without JavaScript and every item is a real link. Month links carry
 * rel="nofollow" and stay inside the profile's month range, so crawlers
 * cannot walk an endless calendar. When JavaScript runs, month changes and
 * filters swap only the month fragment through the REST endpoint. Days are
 * not interactive; each item links to its own URL.
 */
final class CalendarRenderer {
    /** Month-cache generation option, bumped by CalendarModule when calendar content changes. */
    public const GENERATION_OPTION = 'hexa_plugin_core_calendar_generation';

    /** LiteSpeed Cache tag on every page that renders a public calendar; purged when calendar content changes. */
    public const CACHE_TAG = 'hexa_calendar';

    private static bool $assets_printed = false;

    /** @param array<string,mixed>|null $input Raw, unslashed request parameters; defaults to $_GET. */
    public function render( string $profile_id, ?array $input = null, ?int $now = null ): string {
        $profile = CalendarRegistry::get( $profile_id );
        if ( null === $profile || ! PublicComponent::can_view( $profile['public'] ) ) {
            return '';
        }

        $input = PublicComponent::input( $input );
        if ( isset( $input[ CalendarRequest::PARAM_CALENDAR ] ) && PublicComponent::scalar( $input[ CalendarRequest::PARAM_CALENDAR ] ) !== $profile['id'] ) {
            $input = []; // URL state belongs to another calendar on this page.
        }

        $request = CalendarRequest::from_input( $input, $profile, $now );
        $base    = self::sanitize_base( PublicComponent::request_uri() );
        $payload = $this->month( $profile, $request, $base, $now );
        $timezone = ProfileValues::resolve_timezone( $profile['timezone'] );
        $range    = CalendarGrid::range( $timezone, $profile['months_back'], $profile['months_ahead'], $now );
        $today    = ( new \DateTimeImmutable( '@' . (string) ( $now ?? time() ) ) )->setTimezone( $timezone );
        $labels   = $profile['labels'];

        // A cached page must not show yesterday as today: cap LiteSpeed's page lifetime at the next local midnight,
        // and tag the page so calendar content changes purge it (both no-ops without LiteSpeed Cache).
        if ( $profile['public'] && function_exists( 'do_action' ) ) {
            do_action( 'litespeed_control_set_ttl', max( 60, $today->modify( 'tomorrow' )->getTimestamp() - $today->getTimestamp() ) );
            do_action( 'litespeed_tag_add', self::CACHE_TAG );
        }

        $html = '<div class="hcal ' . esc_attr( $profile['class'] ) . '" id="' . esc_attr( 'hcal-' . $profile['id'] ) . '"'
            . PublicComponent::live_attributes( 'hcal', 'calendar/' . $profile['id'], $profile['public'] )
            . ' data-hcal-id="' . esc_attr( $profile['id'] ) . '"'
            . ' data-hcal-base="' . esc_attr( $base ) . '"'
            . ' data-hcal-month="' . esc_attr( $payload['month'] ) . '"'
            . ' data-hcal-home="' . esc_attr( $range['current'] ) . '"'
            . ' data-hcal-today="' . esc_attr( $today->format( 'Y-m-d' ) ) . '"'
            . ' data-hcal-tz="' . esc_attr( $timezone->getName() ) . '"'
            . ' data-hcal-error="' . esc_attr( $labels['error'] ) . '">';

        if ( [] !== $profile['filters'] ) {
            [ $path, $own ] = PublicComponent::split_base( $base );
            $clear = PublicComponent::url( $base, [ CalendarRequest::PARAM_CALENDAR => $profile['id'], CalendarRequest::PARAM_MONTH => $request['month'] ] );
            $html .= '<form class="hcal-filters" method="get" action="' . esc_url( $path ) . '">'
                . PublicComponent::hidden_inputs( $own )
                . '<input type="hidden" name="' . esc_attr( CalendarRequest::PARAM_CALENDAR ) . '" value="' . esc_attr( $profile['id'] ) . '">'
                . '<input type="hidden" name="' . esc_attr( CalendarRequest::PARAM_MONTH ) . '" value="' . esc_attr( $request['month'] ) . '">'
                . QueryFilterSet::controls( CalendarRequest::filters( $profile, $now ), $request['filters'], CalendarRequest::PARAM_FILTER, 'hcal', QueryFilterSet::scope( 'calendar', $profile ) )
                . '<button class="hcal-apply" type="submit">' . esc_html( $labels['apply'] ) . '</button>'
                . '<a class="hcal-reset" href="' . esc_url( $clear ) . '" rel="nofollow"' . ( [] === $request['filters'] ? ' hidden' : '' ) . '>' . esc_html( $labels['reset'] ) . '</a>'
                . '</form>';
        }

        // The live region stays in place while the month fragment is swapped, so every change is announced.
        $html .= '<p class="hcal-status" role="status" aria-live="polite" aria-atomic="true">' . esc_html( $payload['status'] ) . '</p>'
            . '<div class="hcal-body">' . $payload['html'] . '</div></div>';

        // Titles and visitor dates are echoed; keep them inert to a later do_shortcode() pass.
        return $this->assets() . PublicComponent::inert( $html );
    }

    /**
     * One month fragment plus metadata; shared by the shortcode and REST endpoint.
     *
     * Item lists are cached only for the unfiltered view of an in-range month,
     * keyed by profile, cache version, content generation, and month, so
     * visitors cannot grow the cache. Markup is rebuilt per request.
     *
     * @param array<string,mixed> $profile
     * @param array{month:string,filters:array<string,mixed>} $request
     * @return array{html:string,month:string,label:string,total:int,status:string}
     */
    public function month( array $profile, array $request, string $base, ?int $now = null ): array {
        $timezone = ProfileValues::resolve_timezone( $profile['timezone'] );
        $grid     = CalendarGrid::build( $request['month'], $timezone, $profile['week_start'], $now );
        $items    = $this->items( $profile, $request, $grid );
        $placed   = CalendarGrid::place( $items, $grid, $timezone, $profile['max_span_days'], $profile['end_midnight'] );
        $label    = $this->date( 'F Y', $grid['days'][ (int) floor( count( $grid['days'] ) / 2 ) ]['start'], $timezone );

        $in_month = [];
        foreach ( $grid['days'] as $day ) {
            if ( $day['in_month'] ) {
                foreach ( $placed[ $day['date'] ] ?? [] as $item ) {
                    $in_month[ (string) $item['id'] ] = true;
                }
            }
        }
        $total = count( $in_month );

        $html  = $this->head( $profile, $request, $base, $grid, $label, $now );
        $html .= $this->days( $profile, $request, $grid, $placed, $timezone );
        if ( 0 === $total ) {
            $html .= '<p class="hcal-empty">' . esc_html( sprintf( $profile['labels']['empty'], $label ) ) . '</p>';
        }

        return [
            'html'   => PublicComponent::inert( $html ),
            'month'  => $grid['month'],
            'label'  => $label,
            'total'  => $total,
            'status' => $label . ' · ' . $this->count_label( $profile['labels'], $total ),
        ];
    }

    public static function sanitize_base( string $url ): string {
        return PublicComponent::sanitize_base( $url, [ CalendarRequest::PARAM_MONTH, CalendarRequest::PARAM_FILTER, CalendarRequest::PARAM_CALENDAR ] );
    }

    /** @return list<array<string,mixed>> */
    private function items( array $profile, array $request, array $grid ): array {
        $cache_key = '';
        if ( $profile['cache_ttl'] > 0 && [] === $request['filters'] && function_exists( 'get_transient' ) ) {
            $generation = function_exists( 'get_option' ) ? (string) get_option( self::GENERATION_OPTION, '0' ) : '0';
            $cache_key  = 'hcal_' . md5( (string) wp_json_encode( [ $profile['id'], $profile['cache_version'], $generation, $grid['month'], $grid['start'] ] ) );
            $cached     = get_transient( $cache_key );
            if ( is_array( $cached ) ) {
                return $cached;
            }
        }

        $items = ( new CalendarQuery() )->items( $profile, $request, $grid['start'], $grid['end'] );
        if ( '' !== $cache_key ) {
            set_transient( $cache_key, $items, $profile['cache_ttl'] );
        }

        return $items;
    }

    private function head( array $profile, array $request, string $base, array $grid, string $label, ?int $now ): string {
        $labels  = $profile['labels'];
        $range   = CalendarRequest::bounds( $profile, $request['filters'], $now );
        $level   = (int) $profile['heading_level'];
        $month   = $grid['month'];
        $nav     = [
            [ 'hcal-prev', CalendarGrid::shift( $month, -1 ), '‹', $labels['previous'] ],
            [ 'hcal-today', $range['current'], $labels['today'], $labels['today'] ],
            [ 'hcal-next', CalendarGrid::shift( $month, 1 ), '›', $labels['next'] ],
        ];

        $html = '<div class="hcal-head"><h' . $level . ' class="hcal-title" tabindex="-1">' . esc_html( $label ) . '</h' . $level . '>'
            . '<nav class="hcal-nav" aria-label="' . esc_attr( $labels['months'] ) . '">';
        foreach ( $nav as [ $class, $target, $text, $name ] ) {
            if ( $target < $range['min'] || $target > $range['max'] ) {
                $html .= '<span class="hcal-btn ' . esc_attr( $class ) . '" aria-disabled="true" title="' . esc_attr( $name ) . '">' . esc_html( $text ) . '</span>';
                continue;
            }
            $url   = PublicComponent::url( $base, CalendarRequest::to_args( $request, $profile, $target ) );
            $html .= '<a class="hcal-btn ' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '" rel="nofollow" data-hcal-month="' . esc_attr( $target ) . '"'
                . ( $text !== $name ? ' aria-label="' . esc_attr( $name ) . '" title="' . esc_attr( $name ) . '"' : '' )
                . ( 'hcal-today' === $class && $target === $month ? ' aria-current="date"' : '' ) . '>' . esc_html( $text ) . '</a>';
        }

        return $html . '</nav></div>';
    }

    /** @param array<string,list<array<string,mixed>>> $placed */
    private function days( array $profile, array $request, array $grid, array $placed, \DateTimeZone $timezone ): string {
        $date_key = QueryFilterSet::active_date_key( $profile['filters'], $request['filters'] );
        [ $range_from, $range_to ] = null === $date_key ? [ null, null ] : DateRangeFilterType::bounds(
            $request['filters'][ $date_key ],
            ProfileValues::resolve_timezone( (string) ( $profile['filters'][ $date_key ]['timezone'] ?? $profile['timezone'] ) )
        );

        $html = '<div class="hcal-grid"><ol class="hcal-weekdays" aria-hidden="true">';
        foreach ( array_slice( $grid['days'], 0, 7 ) as $day ) {
            $html .= '<li>' . esc_html( $this->date( 'D', $day['start'], $timezone ) ) . '</li>';
        }
        $html .= '</ol><ol class="hcal-days">';

        foreach ( $grid['days'] as $day ) {
            $items   = $placed[ $day['date'] ] ?? [];
            $classes = 'hcal-day'
                . ( $day['in_month'] ? '' : ' is-outside' )
                . ( $day['today'] ? ' is-today' : '' )
                . ( $day['past'] ? ' is-past' : '' )
                . ( [] !== $items ? ' has-items' : '' )
                . ( ( null !== $range_from && $day['start'] < $range_from->getTimestamp() ) || ( null !== $range_to && $day['start'] >= $range_to->getTimestamp() ) ? ' is-out-of-range' : '' );

            $html .= '<li class="' . $classes . '"' . ( $day['today'] ? ' aria-current="date"' : '' ) . '>'
                . '<div class="hcal-date"><span class="hcal-dnum" aria-hidden="true">' . esc_html( (string) $day['day'] ) . '</span>'
                . '<span class="hcal-dlabel">' . esc_html( $this->date( 'D, M j', $day['start'], $timezone ) ) . '</span></div>';

            if ( [] !== $items ) {
                $visible = array_slice( $items, 0, $profile['max_per_day'] );
                $hidden  = array_slice( $items, $profile['max_per_day'] );
                $html   .= $this->list( $profile, $request, $visible, $timezone );
                if ( [] !== $hidden ) {
                    $html .= '<details class="hcal-more"><summary>' . esc_html( sprintf( $profile['labels']['more'], count( $hidden ) ) ) . '</summary>'
                        . $this->list( $profile, $request, $hidden, $timezone ) . '</details>';
                }
            }

            $html .= '</li>';
        }

        return $html . '</ol></div>';
    }

    /** @param list<array<string,mixed>> $items */
    private function list( array $profile, array $request, array $items, \DateTimeZone $timezone ): string {
        $html = '<ul class="hcal-items">';
        foreach ( $items as $item ) {
            $html .= '<li>' . $this->item( $profile, $request, $item, $timezone ) . '</li>';
        }

        return $html . '</ul>';
    }

    private function item( array $profile, array $request, array $item, \DateTimeZone $timezone ): string {
        $labels = $profile['labels'];
        $when   = $labels['all_day'];
        if ( $item['long'] ) {
            // A long item appears once, so it always states when it ends.
            $until = \DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $item['until'], $timezone );
            $when  = $until instanceof \DateTimeImmutable ? sprintf( $labels['until'], $this->date( 'M j', $until->getTimestamp(), $timezone ) ) : '';
        } elseif ( $item['continued'] ) {
            $when = $labels['continues'];
        } elseif ( ! $item['all_day'] ) {
            $when = $this->date( $profile['time_format'], (int) $item['start'], $timezone );
        }
        $item['when'] = $when;

        $classes = 'hcal-item' . ( $item['continued'] ? ' is-continued' : '' ) . ( $item['long'] ? ' is-long' : '' );
        if ( null !== $profile['item_class'] ) {
            $extra = ProfileValues::classes( (string) call_user_func( $profile['item_class'], $item, $item['data'] ) );
            $classes .= '' !== $extra ? ' ' . $extra : '';
        }

        // Hosts return the item's inner markup (escaped, without links); Core owns the one link to the item.
        $inner = null !== $profile['render_item']
            ? (string) call_user_func( $profile['render_item'], $item, $item['data'], $request )
            : ( '' !== $when ? '<span class="hcal-time">' . esc_html( $when ) . '</span>' : '' ) . '<span class="hcal-name">' . esc_html( (string) $item['title'] ) . '</span>';

        if ( '' === $item['url'] ) {
            return '<span class="' . esc_attr( $classes ) . '">' . $inner . '</span>';
        }

        return '<a class="' . esc_attr( $classes ) . '" href="' . esc_url( (string) $item['url'] ) . '" title="' . esc_attr( wp_strip_all_tags( (string) $item['title'] ) ) . '"'
            . ( '_blank' === $profile['link_target'] ? ' target="_blank" rel="noopener"' : '' ) . '>' . $inner . '</a>';
    }

    private function count_label( array $labels, int $total ): string {
        if ( 0 === $total ) {
            return $labels['count_none'];
        }

        return sprintf( 1 === $total ? $labels['count_one'] : $labels['count_many'], $total );
    }

    /** Localized date in the profile timezone. */
    private function date( string $format, int $timestamp, \DateTimeZone $timezone ): string {
        if ( function_exists( 'wp_date' ) ) {
            return (string) wp_date( $format, $timestamp, $timezone );
        }

        return ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone )->format( $format );
    }

    private function assets(): string {
        if ( self::$assets_printed ) {
            return '';
        }
        self::$assets_printed = true;

        return '<style id="hexa-calendar-css">' . self::css() . '</style><script id="hexa-calendar-js">' . self::js() . '</script>';
    }

    public static function css(): string {
        // :where() keeps only the custom-property defaults at zero specificity; structural rules are class selectors,
        // so hosts restyle through the variables or scope overrides with the profile class.
        return ':where(.hcal){--hcal-muted:#4b5563;--hcal-border:rgba(127,127,127,.25);--hcal-outside-bg:rgba(127,127,127,.05);--hcal-accent:#2563eb;--hcal-accent-fg:#fff;'
            . '--hcal-item-bg:rgba(37,99,235,.08);--hcal-item-hover:rgba(37,99,235,.16);--hcal-item-fg:inherit;--hcal-field-bg:transparent;--hcal-radius:8px;--hcal-gap:12px;--hcal-cell-h:118px}'
            . '.hcal-head{display:flex;align-items:center;justify-content:space-between;gap:var(--hcal-gap);margin:0 0 4px}'
            . '.hcal-title{margin:0;font-size:clamp(20px,2.4vw,28px);line-height:1.2;color:inherit}'
            . '.hcal-nav{display:flex;gap:6px}'
            . '.hcal-btn{display:inline-flex;align-items:center;justify-content:center;min-width:40px;height:40px;padding:0 12px;border:1px solid var(--hcal-border);border-radius:var(--hcal-radius);color:inherit;font-size:14px;line-height:1;text-decoration:none;transition:border-color .15s,color .15s}'
            . 'a.hcal-btn:hover{border-color:var(--hcal-accent);color:var(--hcal-accent)}.hcal-btn[aria-disabled=true]{opacity:.35}'
            . '.hcal-prev,.hcal-next{font-size:20px}'
            . '.hcal-status{margin:0 0 10px;color:var(--hcal-muted);font-size:13px}.hcal-title:focus{outline:none}.hcal-title:focus-visible{outline:2px solid var(--hcal-accent)}'
            . '.hcal-filters{display:flex;flex-wrap:wrap;align-items:flex-end;gap:var(--hcal-gap);margin:0 0 20px}'
            . '.hcal-field{display:flex;flex-direction:column;gap:6px;min-width:150px;margin:0}'
            . '.hcal-label{font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:var(--hcal-muted)}'
            . '.hcal-select,.hcal-input{box-sizing:border-box;height:40px;padding:0 10px;border:1px solid var(--hcal-border);border-radius:var(--hcal-radius);background:var(--hcal-field-bg);color:inherit;font:inherit;font-size:14px}'
            . '.hcal-range-inputs{display:flex;align-items:center;gap:6px}.hcal-range-inputs .hcal-input{flex:1 1 0;min-width:0;width:100%}.hcal-range-sep{color:var(--hcal-muted)}'
            . '.hcal-toggle{display:inline-flex;align-items:center;gap:8px;height:40px;margin:0;font-size:14px;cursor:pointer}'
            . '.hcal-toggle input{width:16px;height:16px;margin:0;accent-color:var(--hcal-accent)}'
            . '.hcal-apply{height:40px;padding:0 18px;border:0;border-radius:var(--hcal-radius);background:var(--hcal-accent);color:var(--hcal-accent-fg);font:inherit;font-weight:600;cursor:pointer}'
            . '.hcal.is-live .hcal-apply{display:none}'
            . '.hcal-reset{align-self:center;color:var(--hcal-muted);font-size:13px}.hcal-reset[hidden]{display:none}'
            . '.hcal-weekdays,.hcal-days{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));margin:0;padding:0;list-style:none}'
            . '.hcal-weekdays li{margin:0;padding:0 8px 8px;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:var(--hcal-muted)}'
            . '.hcal-days{border-top:1px solid var(--hcal-border);border-left:1px solid var(--hcal-border);border-radius:var(--hcal-radius);overflow:hidden}'
            . '.hcal-day{display:flex;flex-direction:column;gap:4px;min-width:0;min-height:var(--hcal-cell-h);margin:0;padding:8px;border-right:1px solid var(--hcal-border);border-bottom:1px solid var(--hcal-border)}'
            . '.hcal-day.is-outside,.hcal-day.is-out-of-range{background:var(--hcal-outside-bg)}.hcal-day.is-outside .hcal-date,.hcal-day.is-past .hcal-dnum,.hcal-day.is-out-of-range .hcal-date{opacity:.5}'
            . '.hcal-date{display:flex;align-items:center}'
            . '.hcal-dnum{display:inline-flex;align-items:center;justify-content:center;min-width:26px;height:26px;border-radius:999px;font-size:13px;font-weight:600;font-variant-numeric:tabular-nums}'
            . '.hcal-day.is-today .hcal-dnum{background:var(--hcal-accent);color:var(--hcal-accent-fg);opacity:1}'
            . '.hcal-dlabel{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}'
            . '.hcal-items{display:flex;flex-direction:column;gap:3px;margin:0;padding:0;list-style:none}.hcal-items li{min-width:0;margin:0}'
            . '.hcal-item{display:block;padding:4px 6px;border-left:2px solid var(--hcal-accent);border-radius:4px;background:var(--hcal-item-bg);color:var(--hcal-item-fg);font-size:12px;line-height:1.3;text-decoration:none;transition:background .15s}'
            . 'a.hcal-item:hover,a.hcal-item:focus-visible{background:var(--hcal-item-hover);color:var(--hcal-item-fg)}'
            . '.hcal-item.is-continued{border-left-style:dashed}'
            . '.hcal-time{display:block;color:var(--hcal-muted);font-size:11px;font-variant-numeric:tabular-nums}'
            . '.hcal-name{display:-webkit-box;overflow:hidden;font-weight:600;-webkit-line-clamp:2;-webkit-box-orient:vertical}'
            . '.hcal-more summary{padding:2px 6px;color:var(--hcal-muted);font-size:12px;cursor:pointer;list-style:none}.hcal-more summary::-webkit-details-marker{display:none}'
            . '.hcal-more .hcal-items{margin-top:3px}'
            . '.hcal-empty{margin:14px 0 0;padding:20px;border:1px dashed var(--hcal-border);border-radius:var(--hcal-radius);color:var(--hcal-muted);text-align:center}'
            . '.hcal-body{transition:opacity .15s}.hcal-body.is-loading{opacity:.55}'
            . '.hcal :focus-visible{outline:2px solid var(--hcal-accent);outline-offset:2px}'
            . '@media(max-width:720px){.hcal-weekdays{display:none}.hcal-days{display:block;border:0;border-radius:0}'
            . '.hcal-day{display:grid;grid-template-columns:76px minmax(0,1fr);gap:3px 12px;min-height:0;padding:12px 0;border:0;border-bottom:1px solid var(--hcal-border)}'
            . '.hcal-day>.hcal-date{grid-row:1/span 2;align-self:start}.hcal-day>.hcal-items,.hcal-day>.hcal-more{grid-column:2}.hcal-field.hcal-range{flex-basis:100%}'
            . '.hcal-day:not(.has-items),.hcal-day.is-outside{display:none}.hcal-dnum{display:none}'
            . '.hcal-dlabel{position:static;width:auto;height:auto;overflow:visible;clip:auto;white-space:normal;color:var(--hcal-muted);font-size:12px;font-weight:600;letter-spacing:.04em;line-height:1.35;text-transform:uppercase}'
            . '.hcal-day.is-today .hcal-dlabel{color:var(--hcal-accent)}.hcal-item{padding:8px 10px;font-size:14px}.hcal-field{flex:1 1 140px;min-width:0}}'
            . '@media(prefers-reduced-motion:reduce){.hcal-body,.hcal-item,.hcal-btn{transition:none}}';
    }

    public static function js(): string {
        return <<<'JS'
(function(){
function init(root){
if(root.getAttribute('data-hcal-ready'))return;root.setAttribute('data-hcal-ready','1');
var body=root.querySelector('.hcal-body'),form=root.querySelector('.hcal-filters'),status=root.querySelector('.hcal-status'),endpoint=root.getAttribute('data-hcal-endpoint');
if(!body||!endpoint||!window.fetch||!window.URLSearchParams)return;
try{var eu=new URL(endpoint,location.href);if(eu.origin!==location.origin||eu.href.indexOf('hexa-plugin-core/v1/calendar/')<0)return;}catch(e){return;}
var id=root.getAttribute('data-hcal-id'),base=root.getAttribute('data-hcal-base')||location.pathname,nonce=root.getAttribute('data-hcal-nonce');
var month=root.getAttribute('data-hcal-month'),reset=root.querySelector('.hcal-reset'),seq=0,controller=null;
root.classList.add('is-live');
function each(fn){if(form)Array.prototype.forEach.call(form.elements,fn);}
function filters(){var p=new URLSearchParams();each(function(el){if(!el.name||el.disabled||el.type==='hidden'||el.type==='submit')return;if(el.type==='checkbox'&&!el.checked)return;var v=(el.value||'').trim();if(v!=='')p.append(el.name,v);});return p;}
function pageUrl(p){var q=base.indexOf('?'),path=q>-1?base.slice(0,q):base,own=new URLSearchParams(q>-1?base.slice(q+1):'');own.set('cal',id);p.forEach(function(v,k){own.append(k,v);});return path+'?'+own.toString();}
function restore(sp){each(function(el){if(!el.name||el.type==='hidden'||el.type==='submit')return;if(el.type==='checkbox'){el.checked=sp.get(el.name)===el.value;}else if(el.tagName==='SELECT'||el.type==='date'){el.value=sp.get(el.name)||'';}});}
function run(m,push){var f=filters(),p=new URLSearchParams(f.toString()),n=++seq;p.set('cmonth',m||month);
var fa=document.activeElement,focus=null;if(fa&&body.contains(fa)){var fm=(fa.className||'').match(/hcal-(prev|today|next)/);focus=fm?fm[0]:'hcal-title';}
if(controller)controller.abort();controller=window.AbortController?new AbortController():null;
root.setAttribute('aria-busy','true');body.classList.add('is-loading');
var rp=new URLSearchParams(p.toString());rp.set('base',base);var h={'Accept':'application/json'};if(nonce)h['X-WP-Nonce']=nonce;
fetch(endpoint+(endpoint.indexOf('?')>-1?'&':'?')+rp.toString(),{credentials:nonce?'same-origin':'omit',headers:h,signal:controller?controller.signal:undefined}).then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json();}).then(function(d){if(n!==seq)return;
body.innerHTML=d.html||'';month=d.month||m||month;root.setAttribute('data-hcal-month',month);if(status)status.textContent=d.status||'';
var mi=form&&form.querySelector('input[name="cmonth"]');if(mi)mi.value=month;if(reset)reset.hidden=f.toString()==='';
if(focus){var t=body.querySelector('a.'+focus)||body.querySelector('.hcal-title');if(t)t.focus();}
if(push!==null&&window.history&&history.replaceState){p.set('cmonth',month);var u=pageUrl(p);if(push){history.pushState({hcal:id},'',u);}else{history.replaceState({hcal:id},'',u);}}
}).catch(function(e){if(n!==seq||(e&&e.name==='AbortError'))return;if(status)status.textContent=root.getAttribute('data-hcal-error')||'';}).then(function(){if(n!==seq)return;root.removeAttribute('aria-busy');body.classList.remove('is-loading');});}
body.addEventListener('click',function(e){var a=e.target&&e.target.closest?e.target.closest('a[data-hcal-month]'):null;if(!a||e.metaKey||e.ctrlKey||e.shiftKey||e.altKey||e.button)return;e.preventDefault();run(a.getAttribute('data-hcal-month'),true);});
if(form){form.addEventListener('submit',function(e){e.preventDefault();run(month,false);});form.addEventListener('change',function(){run(month,false);});}
if(reset){reset.addEventListener('click',function(e){e.preventDefault();restore(new URLSearchParams());run(month,false);});}
var today=root.getAttribute('data-hcal-today'),now='';
try{now=new Intl.DateTimeFormat('en-CA',{timeZone:root.getAttribute('data-hcal-tz'),year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());}catch(e){}
if(/^\d{4}-\d{2}-\d{2}$/.test(now)&&now!==today){var sp0=new URLSearchParams(location.search),mine=!sp0.get('cal')||sp0.get('cal')===id;root.setAttribute('data-hcal-home',now.slice(0,7));run(mine&&sp0.get('cmonth')?month:now.slice(0,7),null);}
window.addEventListener('popstate',function(){var sp=new URLSearchParams(location.search),owner=sp.get('cal');if(owner&&owner!==id){sp=new URLSearchParams();}
restore(sp);run(sp.get('cmonth')||root.getAttribute('data-hcal-home'),null);});
}
function boot(){Array.prototype.forEach.call(document.querySelectorAll('.hcal[data-hcal-endpoint]'),init);}
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',boot);}else{boot();}
})();
JS;
    }
}
