<?php

namespace Hexa\PluginCore\SchemaTools;

/**
 * One schema graph per page, shared by every Hexa plugin.
 *
 * Each plugin registers a source: a callback that returns the nodes that
 * plugin wants on the page being viewed (or an empty list). The plugin alone
 * decides which pages it covers. SchemaPage only merges what the sources
 * return into one graph and prints it once in the page head.
 *
 * Nodes with the same @id are merged into one node. When two sources set
 * the same property differently, the source with the lower priority number
 * keeps its value; list values are combined.
 */
final class SchemaPage {
    public const SCRIPT_ID = 'hexa-schema';

    /** Linked properties keep @id and @type so validators can follow them. */
    private const LINKED_REFERENCE_PROPERTIES = [
        'author'             => [ '@id', '@type' ],
        'publisher'          => [ '@id', '@type' ],
        'copyrightHolder'    => [ '@id', '@type' ],
        'creator'            => [ '@id', '@type' ],
        'image'              => [ '@id', '@type' ],
        'logo'               => [ '@id', '@type' ],
        'primaryImageOfPage' => [ '@id', '@type' ],
        'mainEntity'         => [ '@id', '@type' ],
        'about'              => [ '@id', '@type' ],
        'isPartOf'           => [ '@id', '@type' ],
        'founder'            => [ '@id', '@type' ],
        'worksFor'           => [ '@id', '@type' ],
    ];

    /** @var array<string,array{callback:callable,priority:int,order:int}> */
    private static array $sources = [];

    private static bool $hooked = false;
    private static bool $printed = false;

    /** @var array<string,mixed>|null */
    private static ?array $document = null;

    /** @var list<string> */
    private static array $contributors = [];

    /**
     * @param callable():array $callback Returns a list of nodes, or a document with @graph.
     */
    public static function register( string $source, callable $callback, int $priority = 10 ): void {
        $source = sanitize_key( $source );
        if ( '' === $source ) {
            return;
        }
        self::$sources[ $source ] = [ 'callback' => $callback, 'priority' => $priority, 'order' => count( self::$sources ) ];
        self::$document = null;
        if ( ! self::$hooked && function_exists( 'add_action' ) ) {
            add_action( 'wp_head', [ self::class, 'print' ], 1 );
            self::$hooked = true;
        }
    }

    public static function unregister( string $source ): void {
        unset( self::$sources[ sanitize_key( $source ) ] );
        self::$document = null;
    }

    /** @return list<string> */
    public static function sources(): array {
        return array_keys( self::$sources );
    }

    /** True when any source has nodes for the page being viewed. */
    public static function has_nodes(): bool {
        return ! empty( self::document()['@graph'] );
    }

    /** @return list<string> The sources that added nodes to the current page. */
    public static function contributors(): array {
        self::document();
        return self::$contributors;
    }

    /**
     * The merged schema document for the page being viewed.
     *
     * @return array<string,mixed>
     */
    public static function document(): array {
        if ( null !== self::$document ) {
            return self::$document;
        }
        return self::build( self::collect() );
    }

    /** Forget the computed graph, for example after switching the queried post. */
    public static function reset(): void {
        self::$document = null;
        self::$contributors = [];
        self::$printed = false;
    }

    public static function print(): void {
        if ( self::$printed || is_admin() || is_feed() || ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) ) {
            return;
        }
        $document = self::document();
        if ( empty( $document['@graph'] ) ) {
            return;
        }
        self::$printed = true;
        $json = ( new SchemaDocumentRenderer() )->json( $document );
        if ( '' === $json ) {
            return;
        }
        echo "\n" . '<script type="application/ld+json" id="' . esc_attr( self::SCRIPT_ID ) . '" data-sources="' . esc_attr( implode( ' ', self::$contributors ) ) . '">' . $json . '</script>' . "\n";
    }

    /**
     * Merge node lists from several sources into one graph document and
     * keep it as the current page's document.
     *
     * @param array<string,list<array<string,mixed>>> $by_source Nodes keyed by source, highest priority first.
     * @return array<string,mixed>
     */
    public static function build( array $by_source ): array {
        $nodes = [];
        $index = [];
        self::$contributors = [];
        foreach ( $by_source as $source => $list ) {
            $added = false;
            foreach ( $list as $node ) {
                if ( ! is_array( $node ) || empty( $node['@type'] ) ) {
                    continue;
                }
                unset( $node['@context'] );
                $node = SchemaGraph::clean( $node );
                $key = isset( $node['@id'] ) && is_scalar( $node['@id'] ) ? 'id:' . (string) $node['@id'] : 'hash:' . md5( serialize( $node ) );
                if ( isset( $index[ $key ] ) ) {
                    $nodes[ $index[ $key ] ] = self::merge( $nodes[ $index[ $key ] ], $node );
                } else {
                    $index[ $key ] = count( $nodes );
                    $nodes[] = $node;
                }
                $added = true;
            }
            if ( $added ) {
                self::$contributors[] = (string) $source;
            }
        }

        $nodes = apply_filters( 'hexa_plugin_core_schema_graph', $nodes, self::$contributors );
        if ( ! is_array( $nodes ) || [] === $nodes ) {
            self::$document = [];
            return [];
        }

        $document = SchemaGraph::sanitize_urls( [ '@context' => 'https://schema.org', '@graph' => array_values( $nodes ) ] );
        self::$document = SchemaGraph::standalone_nodes( $document, self::LINKED_REFERENCE_PROPERTIES );
        return self::$document;
    }

    /**
     * Keeps every property of the first node and adds what the second adds.
     *
     * @param array<string,mixed> $first
     * @param array<string,mixed> $second
     * @return array<string,mixed>
     */
    public static function merge( array $first, array $second ): array {
        foreach ( $second as $property => $value ) {
            if ( ! array_key_exists( $property, $first ) ) {
                $first[ $property ] = $value;
                continue;
            }
            $existing = $first[ $property ];
            if ( '@type' === $property || ! is_array( $existing ) || ! is_array( $value ) ) {
                continue;
            }
            if ( self::is_list( $existing ) || self::is_list( $value ) ) {
                $merged = self::is_list( $existing ) ? $existing : [ $existing ];
                foreach ( self::is_list( $value ) ? $value : [ $value ] as $item ) {
                    if ( ! in_array( $item, $merged, true ) ) {
                        $merged[] = $item;
                    }
                }
                $first[ $property ] = $merged;
                continue;
            }
            if ( ( $existing['@id'] ?? null ) === ( $value['@id'] ?? null ) ) {
                $first[ $property ] = self::merge( $existing, $value );
            }
        }
        return $first;
    }

    /** @return array<string,list<array<string,mixed>>> */
    private static function collect(): array {
        $sources = self::$sources;
        uasort(
            $sources,
            static fn( array $a, array $b ): int => [ $a['priority'], $a['order'] ] <=> [ $b['priority'], $b['order'] ]
        );
        $by_source = [];
        foreach ( $sources as $source => $definition ) {
            try {
                $result = call_user_func( $definition['callback'] );
            } catch ( \Throwable $error ) {
                if ( function_exists( 'error_log' ) ) {
                    error_log( 'Hexa schema source ' . $source . ' failed: ' . $error->getMessage() );
                }
                continue;
            }
            if ( ! is_array( $result ) || [] === $result ) {
                continue;
            }
            if ( isset( $result['@graph'] ) && is_array( $result['@graph'] ) ) {
                $result = $result['@graph'];
            } elseif ( isset( $result['@type'] ) ) {
                $result = [ $result ];
            }
            unset( $result['@context'] );
            $by_source[ $source ] = array_values( array_filter( $result, 'is_array' ) );
        }
        return $by_source;
    }

    private static function is_list( array $value ): bool {
        return [] === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );
    }
}
