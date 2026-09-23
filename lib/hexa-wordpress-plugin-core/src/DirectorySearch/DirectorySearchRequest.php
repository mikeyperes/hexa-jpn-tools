<?php

namespace Hexa\PluginCore\DirectorySearch;

/**
 * Normalizes untrusted visitor input for one directory search profile.
 *
 * The same public parameter names serve the no-JavaScript GET form, crawlable
 * pagination links, shareable URLs, and the REST endpoint.
 */
final class DirectorySearchRequest {
    public const PARAM_QUERY = 'dq';
    public const PARAM_PAGE = 'dpage';
    public const PARAM_SORT = 'dsort';
    public const PARAM_FILTER = 'dfilter';

    public const MAX_QUERY_LENGTH = 200;
    public const MAX_PAGE = 1000;

    /**
     * @param array<string,mixed> $input   Unslashed parameters (wp_unslash( $_GET ) or REST query params).
     * @param array<string,mixed> $profile Normalized profile.
     * @return array{q:string,page:int,sort:string,filters:array<string,string>}
     */
    public static function from_input( array $input, array $profile ): array {
        $query = self::scalar( $input[ self::PARAM_QUERY ] ?? '' );
        $query = trim( (string) preg_replace( '/\s+/u', ' ', (string) preg_replace( '/[\x00-\x1F\x7F<>]+/u', ' ', $query ) ) );
        if ( function_exists( 'mb_substr' ) ) {
            $query = mb_substr( $query, 0, self::MAX_QUERY_LENGTH, 'UTF-8' );
        } else {
            $query = substr( $query, 0, self::MAX_QUERY_LENGTH );
        }

        $page = max( 1, min( self::MAX_PAGE, (int) self::scalar( $input[ self::PARAM_PAGE ] ?? 1 ) ) );

        $sort = DirectorySearchProfile::key( self::scalar( $input[ self::PARAM_SORT ] ?? '' ) );
        if ( ! isset( $profile['sorts'][ $sort ] ) ) {
            $sort = (string) $profile['default_sort'];
        }

        $raw_filters = $input[ self::PARAM_FILTER ] ?? [];
        $raw_filters = is_array( $raw_filters ) ? $raw_filters : [];
        $filters = [];
        foreach ( $profile['filters'] as $key => $filter ) {
            $value = trim( self::scalar( $raw_filters[ $key ] ?? '' ) );
            if ( '' === $value ) {
                continue;
            }
            if ( 'toggle' === $filter['control'] ) {
                if ( in_array( strtolower( $value ), [ '1', 'yes', 'on', 'true' ], true ) ) {
                    $filters[ $key ] = '1';
                }
                continue;
            }
            $options = self::filter_options( $filter );
            if ( array_key_exists( $value, $options ) ) {
                $filters[ $key ] = $value;
            }
        }

        return [
            'q'       => $query,
            'page'    => $page,
            'sort'    => $sort,
            'filters' => $filters,
        ];
    }

    /**
     * Resolves a select filter's options (static array or host callback) to value => label.
     *
     * @param array<string,mixed> $filter
     * @return array<string,string>
     */
    public static function filter_options( array $filter ): array {
        $options = $filter['options'] ?? [];
        if ( is_callable( $options ) ) {
            // Hosts should cache expensive option lists themselves; Core calls this once per render pass.
            $options = (array) call_user_func( $options );
        }

        $normalized = [];
        foreach ( (array) $options as $value => $label ) {
            $value = trim( (string) $value );
            if ( '' !== $value ) {
                $normalized[ $value ] = (string) $label;
            }
        }

        return $normalized;
    }

    /**
     * Builds the public query arguments for a URL or REST call.
     *
     * @param array{q:string,page:int,sort:string,filters:array<string,string>} $request
     * @return array<string,mixed>
     */
    public static function to_args( array $request, array $profile, ?int $page = null ): array {
        $args = [];
        if ( '' !== $request['q'] ) {
            $args[ self::PARAM_QUERY ] = $request['q'];
        }
        if ( $request['sort'] !== $profile['default_sort'] ) {
            $args[ self::PARAM_SORT ] = $request['sort'];
        }
        if ( [] !== $request['filters'] ) {
            $args[ self::PARAM_FILTER ] = $request['filters'];
        }
        $page = $page ?? $request['page'];
        if ( $page > 1 ) {
            $args[ self::PARAM_PAGE ] = $page;
        }

        return $args;
    }

    /** @param mixed $value */
    private static function scalar( $value ): string {
        if ( is_array( $value ) || is_object( $value ) ) {
            return '';
        }
        return (string) $value;
    }
}
