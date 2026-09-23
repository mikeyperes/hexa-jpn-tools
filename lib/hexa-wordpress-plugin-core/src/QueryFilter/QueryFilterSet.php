<?php

namespace Hexa\PluginCore\QueryFilter;

use Hexa\PluginCore\PublicComponents\ProfileValues;

/**
 * The one declarative filter structure shared by public components.
 *
 * A component profile declares `filters` as key => definition:
 *
 *     'area'  => [ 'type' => 'taxonomy', 'taxonomy' => 'area', 'options' => 'terms' ],
 *     'dates' => [ 'type' => 'date_range', 'meta_key' => 'start', 'format' => 'timestamp' ],
 *     'kids'  => [ 'type' => 'meta', 'meta_key' => 'kids_event', 'control' => 'toggle' ],
 *
 * This class normalizes definitions, parses untrusted visitor values, builds
 * each filter's SQL through its registered QueryFilterType, renders the
 * controls, and produces URL arguments. Components own only their parameter
 * name and CSS class prefix.
 */
final class QueryFilterSet {
    public const CONTROL_SELECT = 'select';
    public const CONTROL_TOGGLE = 'toggle';
    public const CONTROL_DATE_RANGE = 'date_range';
    public const CONTROLS = [ self::CONTROL_SELECT, self::CONTROL_TOGGLE, self::CONTROL_DATE_RANGE ];

    public const MAX_FILTERS = 12;
    /** Bound for keyword-generated option lists; host-supplied lists are the host's choice. */
    public const MAX_OPTIONS = 200;

    /** @var array<string,array<string,string>> */
    private static array $options_memo = [];

    /**
     * @param array<int|string,mixed> $filters Host definitions.
     * @param array<string,string>     $date_bounds Default `min`/`max` (Y-m-d) for date-range controls.
     * @return array<string,array<string,mixed>>
     */
    public static function normalize( array $filters, string $source, array $date_bounds = [] ): array {
        $normalized = [];
        foreach ( $filters as $key => $filter ) {
            if ( ! is_array( $filter ) || count( $normalized ) >= self::MAX_FILTERS ) {
                continue;
            }

            $key       = ProfileValues::key( (string) ( $filter['key'] ?? ( is_string( $key ) ? $key : '' ) ) );
            $type_name = ProfileValues::key( (string) ( $filter['type'] ?? 'meta' ) );
            $type      = QueryFilterTypes::get( $type_name );
            if ( '' === $key || null === $type || ! in_array( $source, $type->sources(), true ) ) {
                continue;
            }

            $specific = $type->normalize( $filter, $source );
            if ( null === $specific ) {
                continue;
            }

            // Parsing, markup, and URL arguments exist for the built-in controls only; a type offering none of them cannot be served.
            $controls = array_values( array_intersect( $type->controls(), self::CONTROLS ) );
            if ( [] === $controls ) {
                continue;
            }
            $control = ProfileValues::choice( $filter['control'] ?? $controls[0], $controls, $controls[0] );
            $label   = (string) ( $filter['label'] ?? ProfileValues::label( $key ) );

            $normalized[ $key ] = array_merge( $specific, [
                'key'        => $key,
                'type'       => $type_name,
                'control'    => $control,
                'label'      => $label,
                'all_label'  => (string) ( $filter['all_label'] ?? 'All' ),
                'from_label' => (string) ( $filter['from_label'] ?? 'From' ),
                'to_label'   => (string) ( $filter['to_label'] ?? 'To' ),
                'min'        => self::day( $filter['min'] ?? ( $date_bounds['min'] ?? '' ) ),
                'max'        => self::day( $filter['max'] ?? ( $date_bounds['max'] ?? '' ) ),
                'options'    => $filter['options'] ?? ( $specific['options'] ?? [] ),
            ] );
        }

        return $normalized;
    }

    /**
     * Component scope: what the filters run over. Used for keyword options and SQL context.
     *
     * @param array<string,mixed> $profile Normalized component profile (`id`, `source`, `post_types`).
     * @return array<string,mixed>
     */
    public static function scope( string $component, array $profile ): array {
        return [
            'component'  => $component,
            'source'     => (string) ( $profile['source'] ?? 'posts' ),
            'post_types' => (array) ( $profile['post_types'] ?? [] ),
            'profile'    => $profile,
        ];
    }

    /**
     * Validates visitor values. Select values must be declared options,
     * toggles become '1', date ranges become ['from' => 'Y-m-d', 'to' => 'Y-m-d']
     * (either may be '') clamped to `min`/`max`.
     *
     * @param array<string,array<string,mixed>> $filters Normalized filters.
     * @param mixed                             $raw     Visitor values keyed by filter key.
     * @return array<string,string|array{from:string,to:string}>
     */
    public static function parse( array $filters, $raw, array $scope ): array {
        $raw    = is_array( $raw ) ? $raw : [];
        $values = [];
        foreach ( $filters as $key => $filter ) {
            $value = self::parse_value( $filter, $raw[ $key ] ?? null, $scope );
            if ( null !== $value ) {
                $values[ $key ] = $value;
            }
        }

        return $values;
    }

    /**
     * SQL conditions for the active values, each restricting `{$table}.ID`.
     *
     * @param object $database wpdb-compatible.
     * @param array<string,array<string,mixed>> $filters
     * @param array<string,mixed>               $values Parsed values.
     * @param array<string,mixed>               $scope  From scope().
     * @return string[]
     */
    public static function where( $database, array $filters, array $values, array $scope, string $table ): array {
        $clauses = [];
        $index   = 0;
        foreach ( $values as $key => $value ) {
            $type = isset( $filters[ $key ] ) ? QueryFilterTypes::get( (string) $filters[ $key ]['type'] ) : null;
            if ( null === $type ) {
                continue;
            }

            $sql = $type->where( $database, $filters[ $key ], $value, $scope + [ 'table' => $table, 'alias' => $table . '_f' . ( ++$index ) ] );
            if ( '' !== $sql ) {
                $clauses[] = $sql;
            }
        }

        return $clauses;
    }

    /**
     * The controls for every filter, named `{$param}[key]`, with classes
     * `{$prefix}-field`, `-label`, `-select`, `-input`, `-toggle`, and `-range`.
     *
     * @param array<string,array<string,mixed>> $filters
     * @param array<string,mixed>               $values
     */
    public static function controls( array $filters, array $values, string $param, string $prefix, array $scope ): string {
        $html = '';
        foreach ( $filters as $key => $filter ) {
            $name  = $param . '[' . $key . ']';
            $value = $values[ $key ] ?? null;
            $class = $prefix . '-field ' . $prefix . '-field--' . $key;

            if ( self::CONTROL_TOGGLE === $filter['control'] ) {
                $html .= '<label class="' . esc_attr( $prefix . '-toggle ' . $prefix . '-field--' . $key ) . '"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . ( '1' === $value ? ' checked' : '' ) . '>'
                    . '<span>' . esc_html( $filter['label'] ) . '</span></label>';
                continue;
            }

            if ( self::CONTROL_DATE_RANGE === $filter['control'] ) {
                $html .= '<div class="' . esc_attr( $class . ' ' . $prefix . '-range' ) . '" role="group" aria-label="' . esc_attr( $filter['label'] ) . '">'
                    . '<span class="' . esc_attr( $prefix . '-label' ) . '" aria-hidden="true">' . esc_html( $filter['label'] ) . '</span>'
                    . '<span class="' . esc_attr( $prefix . '-range-inputs' ) . '">'
                    . self::date_input( $filter, $name . '[from]', is_array( $value ) ? $value['from'] : '', $filter['from_label'], $prefix )
                    . '<span class="' . esc_attr( $prefix . '-range-sep' ) . '" aria-hidden="true">–</span>'
                    . self::date_input( $filter, $name . '[to]', is_array( $value ) ? $value['to'] : '', $filter['to_label'], $prefix )
                    . '</span></div>';
                continue;
            }

            $html .= '<label class="' . esc_attr( $class ) . '"><span class="' . esc_attr( $prefix . '-label' ) . '">' . esc_html( $filter['label'] ) . '</span>'
                . '<select class="' . esc_attr( $prefix . '-select' ) . '" name="' . esc_attr( $name ) . '"><option value="">' . esc_html( $filter['all_label'] ) . '</option>';
            foreach ( self::options( $filter, $scope ) as $option => $label ) {
                $html .= '<option value="' . esc_attr( (string) $option ) . '"' . ( (string) $option === $value ? ' selected' : '' ) . '>' . esc_html( $label ) . '</option>';
            }
            $html .= '</select></label>';
        }

        return $html;
    }

    /**
     * URL arguments for the active values. Empty date sides are dropped; a
     * select value such as '0' is kept.
     *
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    public static function to_args( array $values ): array {
        $args = [];
        foreach ( $values as $key => $value ) {
            $value = is_array( $value ) ? array_filter( $value, static fn( $part ): bool => '' !== $part && null !== $part ) : $value;
            if ( [] !== $value && '' !== $value && null !== $value ) {
                $args[ $key ] = $value;
            }
        }

        return $args;
    }

    /**
     * Resolves a select filter's options to value => label: a static array, a
     * callable (closure, [object, method], 'function', or 'Class::method'), or
     * a keyword the filter's type declares ('terms', 'acf', 'distinct').
     * Keyword lists are bounded; host lists are kept whole. Memoized per request.
     *
     * @param array<string,mixed> $filter
     * @return array<string,string>
     */
    public static function options( array $filter, array $scope, bool $memoize = true ): array {
        $memo = ( $scope['component'] ?? '' ) . '|' . ( $scope['profile']['id'] ?? '' ) . '|' . $filter['key'];
        if ( $memoize && isset( self::$options_memo[ $memo ] ) ) {
            return self::$options_memo[ $memo ];
        }

        $source  = $filter['options'] ?? [];
        $type    = QueryFilterTypes::get( (string) $filter['type'] );
        $keyword = is_string( $source ) ? strtolower( trim( $source ) ) : '';
        $limit   = PHP_INT_MAX;
        if ( '' !== $keyword && null !== $type && in_array( $keyword, $type->keywords(), true ) ) {
            $options = $type->keyword_options( $filter, $keyword, $scope );
            $limit   = self::MAX_OPTIONS;
        } elseif ( is_callable( $source ) ) {
            $options = (array) call_user_func( $source );
        } else {
            $options = is_array( $source ) ? $source : [];
        }

        $normalized = [];
        foreach ( $options as $value => $label ) {
            $value = trim( (string) $value );
            if ( '' !== $value && ( is_scalar( $label ) || null === $label ) && count( $normalized ) < $limit ) {
                $normalized[ $value ] = (string) $label;
            }
        }

        if ( $memoize ) {
            self::$options_memo[ $memo ] = $normalized;
        }

        return $normalized;
    }

    /** Test helper: forget memoized options. */
    public static function reset(): void {
        self::$options_memo = [];
    }

    /** Key of the first active date-range filter, for components that also bound by date. */
    public static function active_date_key( array $filters, array $values ): ?string {
        foreach ( $values as $key => $value ) {
            if ( is_array( $value ) && self::CONTROL_DATE_RANGE === ( $filters[ $key ]['control'] ?? '' ) ) {
                return (string) $key;
            }
        }

        return null;
    }

    /** The first active date-range value. @return array{from:string,to:string}|null */
    public static function active_date_range( array $filters, array $values ): ?array {
        $key = self::active_date_key( $filters, $values );

        return null !== $key ? $values[ $key ] : null;
    }

    /** @param mixed $raw @return string|array{from:string,to:string}|null */
    private static function parse_value( array $filter, $raw, array $scope ) {
        if ( self::CONTROL_TOGGLE === $filter['control'] ) {
            return is_scalar( $raw ) && in_array( strtolower( trim( (string) $raw ) ), [ '1', 'yes', 'on', 'true' ], true ) ? '1' : null;
        }

        if ( self::CONTROL_DATE_RANGE === $filter['control'] ) {
            if ( ! is_array( $raw ) ) {
                return null;
            }
            $from = self::clamp( self::day( $raw['from'] ?? '' ), $filter );
            $to   = self::clamp( self::day( $raw['to'] ?? '' ), $filter );
            if ( '' === $from && '' === $to ) {
                return null;
            }
            if ( '' !== $from && '' !== $to && $from > $to ) {
                [ $from, $to ] = [ $to, $from ];
            }

            return [ 'from' => $from, 'to' => $to ];
        }

        if ( ! is_scalar( $raw ) ) {
            return null;
        }
        $value = trim( (string) $raw );

        return '' !== $value && array_key_exists( $value, self::options( $filter, $scope ) ) ? $value : null;
    }

    /** A real calendar day as 'Y-m-d', or ''. @param mixed $value */
    private static function day( $value ): string {
        $value = is_scalar( $value ) ? trim( (string) $value ) : '';
        if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) || ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ) {
            return '';
        }

        return $value;
    }

    private static function clamp( string $day, array $filter ): string {
        if ( '' === $day ) {
            return '';
        }
        if ( '' !== $filter['min'] && $day < $filter['min'] ) {
            return $filter['min'];
        }
        if ( '' !== $filter['max'] && $day > $filter['max'] ) {
            return $filter['max'];
        }

        return $day;
    }

    private static function date_input( array $filter, string $name, string $value, string $label, string $prefix ): string {
        return '<input class="' . esc_attr( $prefix . '-input' ) . '" type="date" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"'
            . ( '' !== $filter['min'] ? ' min="' . esc_attr( $filter['min'] ) . '"' : '' )
            . ( '' !== $filter['max'] ? ' max="' . esc_attr( $filter['max'] ) . '"' : '' )
            . ' aria-label="' . esc_attr( $filter['label'] . ': ' . $label ) . '">';
    }
}
