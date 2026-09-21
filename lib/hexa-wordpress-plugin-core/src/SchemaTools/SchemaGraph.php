<?php

namespace Hexa\PluginCore\SchemaTools;

final class SchemaGraph {
    private const URL_PROPERTIES = [
        'url',
        'contentUrl',
        'thumbnailUrl',
        'discussionUrl',
        'sameAs',
        'publishingPrinciples',
        'verificationFactCheckingPolicy',
        'correctionsPolicy',
        'ethicsPolicy',
        'diversityPolicy',
        'diversityStaffingReport',
        'masthead',
        'missionCoveragePrioritiesPolicy',
        'noBylinesPolicy',
        'unnamedSourcesPolicy',
        'actionableFeedbackPolicy',
    ];

    private const CREATIVE_WORK_OR_URL_PROPERTIES = [
        'publishingPrinciples',
        'verificationFactCheckingPolicy',
        'correctionsPolicy',
        'ethicsPolicy',
        'diversityPolicy',
        'diversityStaffingReport',
        'masthead',
        'missionCoveragePrioritiesPolicy',
        'noBylinesPolicy',
        'unnamedSourcesPolicy',
        'actionableFeedbackPolicy',
    ];

    private const STANDALONE_TYPED_REFERENCE_PROPERTIES = [
        'author'             => [ '@type', 'name', 'url', 'image', 'sameAs' ],
        'publisher'          => [ '@type', 'name', 'legalName', 'url', 'logo' ],
        'copyrightHolder'    => [ '@type', 'name', 'legalName', 'url', 'logo' ],
        'image'              => [ '@type', 'url', 'contentUrl', 'thumbnailUrl', 'width', 'height', 'name', 'caption' ],
        'logo'               => [ '@type', 'url', 'contentUrl', 'thumbnailUrl', 'width', 'height', 'name', 'caption' ],
        'primaryImageOfPage' => [ '@type', 'url', 'contentUrl', 'thumbnailUrl', 'width', 'height', 'name', 'caption' ],
    ];

    public static function clean( array $schema ): array {
        foreach ( $schema as $key => $value ) {
            if ( is_array( $value ) ) {
                $value = self::clean( $value );
            }

            if ( null === $value || false === $value || '' === $value || [] === $value ) {
                unset( $schema[ $key ] );
                continue;
            }

            $schema[ $key ] = $value;
        }

        return $schema;
    }

    public static function types( array $schema ): array {
        $nodes = isset( $schema['@graph'] ) && is_array( $schema['@graph'] ) ? $schema['@graph'] : [ $schema ];
        $types = [];

        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) || ! isset( $node['@type'] ) ) {
                continue;
            }

            foreach ( (array) $node['@type'] as $type ) {
                if ( is_scalar( $type ) && '' !== (string) $type ) {
                    $types[] = (string) $type;
                }
            }
        }

        return array_values( array_unique( $types ) );
    }

    public static function ref( string $id ): array {
        return '' === trim( $id ) ? [] : [ '@id' => $id ];
    }

    public static function refs( array $ids ): array {
        $refs = [];
        foreach ( $ids as $id ) {
            if ( ! is_scalar( $id ) || '' === trim( (string) $id ) ) {
                continue;
            }
            $refs[] = [ '@id' => (string) $id ];
        }

        return $refs;
    }

    /**
     * Keeps every @graph node independently detectable while retaining typed
     * summaries for relationship properties that require a concrete entity.
     *
     * @param array<string,mixed>              $schema
     * @param array<string,array<int,string>>  $typed_properties
     * @return array<string,mixed>
     */
    public static function standalone_nodes( array $schema, array $typed_properties = [] ): array {
        if ( ! isset( $schema['@graph'] ) || ! is_array( $schema['@graph'] ) ) {
            return $schema;
        }

        $nodes_by_id = [];
        foreach ( $schema['@graph'] as $node ) {
            if ( ! is_array( $node ) || ! isset( $node['@id'] ) || ! is_scalar( $node['@id'] ) ) {
                continue;
            }
            $id = trim( (string) $node['@id'] );
            if ( '' !== $id ) {
                $nodes_by_id[ $id ] = $node;
            }
        }

        $typed_properties = array_replace( self::STANDALONE_TYPED_REFERENCE_PROPERTIES, $typed_properties );
        foreach ( $schema['@graph'] as $index => $node ) {
            if ( is_array( $node ) ) {
                $schema['@graph'][ $index ] = self::standalone_value( $node, $nodes_by_id, $typed_properties );
            }
        }

        return self::clean( $schema );
    }

    public static function web_url( $value, string $fallback = '' ): string {
        $url = self::normalize_web_url( $value );
        return '' !== $url ? $url : self::normalize_web_url( $fallback );
    }

    public static function sanitize_urls( array $schema ): array {
        foreach ( $schema as $property => $value ) {
            if ( in_array( (string) $property, self::URL_PROPERTIES, true ) ) {
                $value = self::sanitize_url_property( (string) $property, $value );
                if ( null === $value || '' === $value || [] === $value ) {
                    unset( $schema[ $property ] );
                    continue;
                }
                $schema[ $property ] = $value;
                continue;
            }

            if ( is_array( $value ) ) {
                $schema[ $property ] = self::sanitize_urls( $value );
            }
        }

        return self::clean( $schema );
    }

    /**
     * @return array<int,array{path:string,property:string,code:string,message:string}>
     */
    public static function validation_issues( array $schema ): array {
        $issues = [];
        self::collect_validation_issues( $schema, '', $issues );
        return $issues;
    }

    public static function duration_from_seconds( int $seconds ): string {
        if ( $seconds <= 0 ) {
            return '';
        }

        $hours = intdiv( $seconds, HOUR_IN_SECONDS );
        $seconds -= $hours * HOUR_IN_SECONDS;
        $minutes = intdiv( $seconds, MINUTE_IN_SECONDS );
        $seconds -= $minutes * MINUTE_IN_SECONDS;

        $duration = 'PT';
        if ( $hours > 0 ) {
            $duration .= $hours . 'H';
        }
        if ( $minutes > 0 ) {
            $duration .= $minutes . 'M';
        }
        if ( $seconds > 0 || 'PT' === $duration ) {
            $duration .= $seconds . 'S';
        }

        return $duration;
    }

    public static function validator_url( string $url ): string {
        return '' === trim( $url ) ? '' : 'https://validator.schema.org/#url=' . rawurlencode( $url );
    }

    private static function normalize_web_url( $value ): string {
        if ( ! is_scalar( $value ) || is_bool( $value ) ) {
            return '';
        }

        $url = trim( (string) $value );
        if ( '' === $url || false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return '';
        }

        $scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
        return in_array( $scheme, [ 'http', 'https' ], true ) ? $url : '';
    }

    /** @param array<string,array<string,mixed>> $nodes_by_id @param array<string,array<int,string>> $typed_properties */
    private static function standalone_value( $value, array $nodes_by_id, array $typed_properties, string $property = '' ) {
        if ( ! is_array( $value ) ) {
            return $value;
        }

        if ( self::is_reference_only( $value ) ) {
            $id = trim( (string) $value['@id'] );
            if ( isset( $typed_properties[ $property ], $nodes_by_id[ $id ] ) ) {
                $summary = self::reference_summary( $nodes_by_id[ $id ], $typed_properties[ $property ] );
                if ( [] !== $summary ) {
                    return self::standalone_value( $summary, $nodes_by_id, $typed_properties, $property );
                }
            }
            return $id;
        }

        foreach ( $value as $key => $item ) {
            $value[ $key ] = self::standalone_value( $item, $nodes_by_id, $typed_properties, (string) $key );
        }
        return $value;
    }

    /** @param array<string,mixed> $node @param array<int,string> $properties @return array<string,mixed> */
    private static function reference_summary( array $node, array $properties ): array {
        $summary = [];
        foreach ( $properties as $property ) {
            if ( ! array_key_exists( $property, $node ) ) {
                continue;
            }
            $summary[ $property ] = $node[ $property ];
        }
        return self::clean( $summary );
    }

    private static function is_reference_only( array $value ): bool {
        return 1 === count( $value )
            && isset( $value['@id'] )
            && is_scalar( $value['@id'] )
            && '' !== trim( (string) $value['@id'] );
    }

    private static function sanitize_url_property( string $property, $value ) {
        if ( is_array( $value ) && self::is_list( $value ) ) {
            $items = [];
            $seen  = [];
            foreach ( $value as $item ) {
                $item = self::sanitize_url_item( $property, $item );
                if ( null === $item || '' === $item || [] === $item ) {
                    continue;
                }

                $key = is_array( $item ) ? serialize( $item ) : (string) $item;
                if ( isset( $seen[ $key ] ) ) {
                    continue;
                }

                $seen[ $key ] = true;
                $items[]      = $item;
            }
            return $items;
        }

        return self::sanitize_url_item( $property, $value );
    }

    /**
     * @param array<int,array{path:string,property:string,code:string,message:string}> $issues
     */
    private static function collect_validation_issues( array $value, string $path, array &$issues ): void {
        foreach ( $value as $property => $item ) {
            $property_path = self::path( $path, $property );

            if ( '@id' === $property && ( ! is_scalar( $item ) || '' === trim( (string) $item ) ) ) {
                $issues[] = [
                    'path'     => $property_path,
                    'property' => '@id',
                    'code'     => 'invalid_id_type',
                    'message'  => '@id must be a non-empty scalar identifier.',
                ];
            }

            if ( in_array( (string) $property, self::URL_PROPERTIES, true ) && ! self::valid_url_property( (string) $property, $item ) ) {
                $issues[] = [
                    'path'     => $property_path,
                    'property' => (string) $property,
                    'code'     => 'invalid_url_value',
                    'message'  => (string) $property . ' must contain an HTTP(S) URL, a valid URL list, or an allowed structured schema value.',
                ];
            }

            if ( is_array( $item ) ) {
                self::collect_validation_issues( $item, $property_path, $issues );
            }
        }
    }

    private static function valid_url_property( string $property, $value ): bool {
        if ( is_array( $value ) && self::is_list( $value ) ) {
            if ( [] === $value ) {
                return false;
            }
            foreach ( $value as $item ) {
                if ( ! self::valid_url_item( $property, $item ) ) {
                    return false;
                }
            }
            return true;
        }

        return self::valid_url_item( $property, $value );
    }

    private static function sanitize_url_item( string $property, $value ) {
        if ( ! is_array( $value ) ) {
            return self::normalize_web_url( $value );
        }

        if ( self::is_list( $value ) ) {
            return null;
        }

        if ( 'url' === $property && self::is_role_url( $value ) ) {
            return self::sanitize_urls( $value );
        }

        if ( in_array( $property, self::CREATIVE_WORK_OR_URL_PROPERTIES, true ) && self::is_schema_node_reference( $value ) ) {
            return self::sanitize_urls( $value );
        }

        return null;
    }

    private static function valid_url_item( string $property, $value ): bool {
        if ( ! is_array( $value ) ) {
            return '' !== self::normalize_web_url( $value );
        }

        if ( self::is_list( $value ) ) {
            return false;
        }

        if ( 'url' === $property ) {
            return self::is_role_url( $value );
        }

        return in_array( $property, self::CREATIVE_WORK_OR_URL_PROPERTIES, true )
            && self::is_schema_node_reference( $value );
    }

    private static function is_role_url( array $value ): bool {
        $types = isset( $value['@type'] ) ? (array) $value['@type'] : [];
        if ( ! in_array( 'Role', $types, true ) || ! array_key_exists( 'url', $value ) ) {
            return false;
        }
        return '' !== self::normalize_web_url( $value['url'] );
    }

    private static function is_schema_node_reference( array $value ): bool {
        if ( isset( $value['@id'] ) && is_scalar( $value['@id'] ) && '' !== trim( (string) $value['@id'] ) ) {
            return true;
        }

        if ( ! isset( $value['@type'] ) ) {
            return false;
        }

        foreach ( (array) $value['@type'] as $type ) {
            if ( is_scalar( $type ) && '' !== trim( (string) $type ) ) {
                return true;
            }
        }

        return false;
    }

    private static function is_list( array $value ): bool {
        return [] === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );
    }

    private static function path( string $parent, $property ): string {
        if ( is_int( $property ) || ctype_digit( (string) $property ) ) {
            return $parent . '[' . (string) $property . ']';
        }
        return '' === $parent ? (string) $property : $parent . '.' . (string) $property;
    }
}
