<?php

namespace Hexa\PluginCore\DirectorySearch;

use Hexa\PluginCore\QueryFilter\QueryFilterSet;

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
     * @return array{q:string,page:int,sort:string,filters:array<string,string|array{from:string,to:string}>}
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

        return [
            'q'       => $query,
            'page'    => $page,
            'sort'    => $sort,
            'filters' => QueryFilterSet::parse( $profile['filters'], $input[ self::PARAM_FILTER ] ?? [], QueryFilterSet::scope( 'directory', $profile ) ),
        ];
    }

    /**
     * Resolves a select filter's options to value => label.
     *
     * @deprecated 3.2.0 Use QueryFilterSet::options().
     * @param array<string,mixed> $filter Normalized filter.
     * @return array<string,string>
     */
    public static function filter_options( array $filter ): array {
        return QueryFilterSet::options( $filter + [ 'key' => '', 'type' => 'meta' ], [ 'component' => 'directory' ], false );
    }

    /**
     * Builds the public query arguments for a URL or REST call.
     *
     * @param array{q:string,page:int,sort:string,filters:array<string,mixed>} $request
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
            $args[ self::PARAM_FILTER ] = QueryFilterSet::to_args( $request['filters'] );
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
