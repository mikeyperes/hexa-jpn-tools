<?php

namespace Hexa\PluginCore\SchemaDetection;

use Hexa\PluginCore\SchemaTools\SchemaGraph;

final class SchemaPageScanner {
    public function scanUrl( string $url, array $args = [] ): array {
        $title = isset( $args["title"] ) ? (string) $args["title"] : $url;

        if ( "" === trim( $url ) ) {
            return $this->errorResult( $url, $title, "Missing URL.", 0 );
        }

        $headers = isset( $args["headers"] ) && is_array( $args["headers"] ) ? $args["headers"] : [];
        $headers = array_merge(
            [
                "Cache-Control" => "no-cache, no-store, must-revalidate",
                "Pragma"        => "no-cache",
            ],
            $headers
        );

        $timeout    = isset( $args["timeout"] ) ? (int) $args["timeout"] : 15;
        $sslverify  = array_key_exists( "sslverify", $args ) ? (bool) $args["sslverify"] : false;
        $cache_bust = array_key_exists( "cache_bust", $args ) ? (bool) $args["cache_bust"] : true;
        $fetch_url  = $cache_bust ? add_query_arg( "hpc_schema_nocache", time() . "_" . wp_rand( 1000, 9999 ), $url ) : $url;
        $start      = microtime( true );

        $response = wp_remote_get(
            $fetch_url,
            [
                "timeout"   => max( 1, $timeout ),
                "sslverify" => $sslverify,
                "headers"   => $headers,
            ]
        );

        $time_ms = (int) round( ( microtime( true ) - $start ) * 1000 );

        if ( is_wp_error( $response ) ) {
            return $this->errorResult( $url, $title, $response->get_error_message(), $time_ms );
        }

        return $this->scanBody(
            (string) wp_remote_retrieve_body( $response ),
            [
                "url"       => $url,
                "title"     => $title,
                "status"    => (int) wp_remote_retrieve_response_code( $response ),
                "time_ms"   => $time_ms,
                "fetch_url" => $fetch_url,
            ]
        );
    }

    public function scanBody( string $body, array $meta = [] ): array {
        $url    = isset( $meta["url"] ) ? (string) $meta["url"] : "";
        $title  = isset( $meta["title"] ) ? (string) $meta["title"] : $url;
        $status = isset( $meta["status"] ) ? (int) $meta["status"] : 0;

        preg_match_all( "/(.{0,240})<script[^>]*type=[\\x22\\x27]application\\/ld\\+json[\\x22\\x27][^>]*>(.*?)<\\/script>/si", $body, $matches, PREG_SET_ORDER );
        preg_match_all( "/<script[^>]*>/si", $body, $script_matches );

        $blocks          = [];
        $invalid_blocks  = [];
        $semantic_issues = [];
        $all_types       = [];
        $types_by_source = [];

        foreach ( $matches as $index => $match ) {
            $context = isset( $match[1] ) ? (string) $match[1] : "";
            $json    = isset( $match[2] ) ? trim( (string) $match[2] ) : "";
            $schema  = json_decode( $json, true );

            if ( ! is_array( $schema ) ) {
                $invalid_blocks[] = [
                    "index" => $index + 1,
                    "error" => json_last_error_msg(),
                    "json"  => $json,
                ];
                continue;
            }

            $source = $this->detectSource( $json, $context, $schema );
            $types  = $this->extractTypes( $schema );
            $issues = SchemaGraph::validation_issues( $schema );

            foreach ( $issues as $issue ) {
                $semantic_issues[] = $issue + [ 'block' => $index + 1 ];
            }

            foreach ( $types as $type ) {
                $all_types[] = $type;
                $types_by_source[ $source["name"] ][] = $type;
            }

            $blocks[] = [
                "index"  => $index + 1,
                "source" => $source,
                "types"  => $types,
                "issues" => $issues,
                "schema" => $schema,
                "json"   => $json,
            ];
        }

        $conflicts = $this->detectConflicts( $all_types, $types_by_source );

        return [
            "url"             => $url,
            "title"           => $title,
            "status"          => $status,
            "time_ms"         => isset( $meta["time_ms"] ) ? (int) $meta["time_ms"] : 0,
            "fetch_url"       => isset( $meta["fetch_url"] ) ? (string) $meta["fetch_url"] : $url,
            "body_size"       => strlen( $body ),
            "script_count"    => isset( $script_matches[0] ) ? count( $script_matches[0] ) : 0,
            "block_count"     => count( $blocks ),
            "invalid_blocks"  => $invalid_blocks,
            "semantic_issues" => $semantic_issues,
            "blocks"          => $blocks,
            "types"           => array_values( array_unique( $all_types ) ),
            "types_by_source" => $this->uniqueMapValues( $types_by_source ),
            "conflicts"       => $conflicts,
            "faq_issues"      => $this->faqIssues( array_column( $blocks, "schema" ) ),
            "has_schema"      => count( $blocks ) > 0,
            "error"           => "",
        ];
    }

    public function detectSource( string $json, string $context = "", array $schema = [] ): array {
        $haystack = strtolower( $json . " " . $context );

        if ( false !== strpos( $haystack, "sfpf" ) ) {
            return [ "name" => "SFPF Plugin", "tone" => "violet", "color" => "#a78bfa" ];
        }

        if ( false !== strpos( $haystack, "smp" ) || false !== strpos( $haystack, "publication" ) ) {
            return [ "name" => "SMP Plugin", "tone" => "blue", "color" => "#60a5fa" ];
        }

        if ( false !== strpos( $haystack, "rank-math" ) || false !== strpos( $haystack, "rankmath" ) || false !== strpos( $haystack, "rank_math" ) ) {
            return [ "name" => "RankMath", "tone" => "red", "color" => "#f472b6" ];
        }

        if ( false !== strpos( $haystack, "yoast" ) ) {
            return [ "name" => "Yoast SEO", "tone" => "brown", "color" => "#c084fc" ];
        }

        $types = implode( ",", $this->extractTypes( $schema ) );
        if ( false !== strpos( $types, "WebSite" ) && false !== strpos( $json, "SearchAction" ) ) {
            return [ "name" => "RankMath", "tone" => "red", "color" => "#f472b6" ];
        }
        if ( false !== strpos( $types, "WebPage" ) && false !== strpos( $types, "Article" ) ) {
            return [ "name" => "RankMath", "tone" => "red", "color" => "#f472b6" ];
        }

        return [ "name" => "Unknown", "tone" => "muted", "color" => "#9ca3af" ];
    }

    public function extractTypes( array $schema ): array {
        $types = [];
        $append = static function ( $value ) use ( &$types ): void {
            if ( is_array( $value ) ) {
                foreach ( $value as $item ) {
                    if ( is_scalar( $item ) ) {
                        $types[] = (string) $item;
                    }
                }
                return;
            }
            if ( is_scalar( $value ) ) {
                $types[] = (string) $value;
            }
        };

        if ( isset( $schema["@type"] ) ) {
            $append( $schema["@type"] );
        }

        if ( isset( $schema["@graph"] ) && is_array( $schema["@graph"] ) ) {
            foreach ( $schema["@graph"] as $node ) {
                if ( is_array( $node ) && isset( $node["@type"] ) ) {
                    $append( $node["@type"] );
                }
            }
        }

        return array_values( array_unique( array_filter( $types ) ) );
    }

    public function faqIssues( array $schemas ): array {
        $issues = [];

        foreach ( $schemas as $schema ) {
            if ( ! is_array( $schema ) ) {
                continue;
            }

            $nodes = [ $schema ];
            if ( isset( $schema["@graph"] ) && is_array( $schema["@graph"] ) ) {
                $nodes = array_merge( $nodes, $schema["@graph"] );
            }

            foreach ( $nodes as $node ) {
                if ( ! is_array( $node ) ) {
                    continue;
                }
                $types = $this->extractTypes( $node );
                if ( ! in_array( "FAQPage", $types, true ) ) {
                    continue;
                }

                $questions = isset( $node["mainEntity"] ) && is_array( $node["mainEntity"] ) ? $node["mainEntity"] : [];
                if ( empty( $questions ) ) {
                    $issues[] = "FAQPage is missing mainEntity questions";
                    continue;
                }

                foreach ( $questions as $position => $question ) {
                    if ( ! is_array( $question ) ) {
                        $issues[] = "FAQ question " . ( $position + 1 ) . " is not an object";
                        continue;
                    }
                    if ( empty( $question["name"] ) ) {
                        $issues[] = "FAQ question " . ( $position + 1 ) . " is missing name";
                    }
                    if ( empty( $question["acceptedAnswer"] ) || ! is_array( $question["acceptedAnswer"] ) ) {
                        $issues[] = "FAQ question " . ( $position + 1 ) . " is missing acceptedAnswer";
                        continue;
                    }
                    if ( empty( $question["acceptedAnswer"]["text"] ) ) {
                        $issues[] = "FAQ question " . ( $position + 1 ) . " acceptedAnswer is missing text";
                    }
                }
            }
        }

        return array_values( array_unique( $issues ) );
    }

    private function detectConflicts( array $types, array $types_by_source ): array {
        $counts    = array_count_values( $types );
        $conflicts = [];

        foreach ( [ "Person", "ProfilePage", "Organization", "WebSite", "FAQPage", "Article", "Book" ] as $type ) {
            if ( ( $counts[ $type ] ?? 0 ) < 2 ) {
                continue;
            }

            $sources = [];
            foreach ( $types_by_source as $source => $source_types ) {
                if ( in_array( $type, $source_types, true ) ) {
                    $sources[] = (string) $source;
                }
            }

            $conflicts[] = [
                "type"    => $type,
                "count"   => (int) $counts[ $type ],
                "sources" => array_values( array_unique( $sources ) ),
            ];
        }

        return $conflicts;
    }

    private function uniqueMapValues( array $map ): array {
        foreach ( $map as $key => $values ) {
            $map[ $key ] = array_values( array_unique( array_map( "strval", (array) $values ) ) );
        }
        return $map;
    }

    private function errorResult( string $url, string $title, string $error, int $time_ms ): array {
        return [
            "url"             => $url,
            "title"           => $title,
            "status"          => 0,
            "time_ms"         => $time_ms,
            "fetch_url"       => $url,
            "body_size"       => 0,
            "script_count"    => 0,
            "block_count"     => 0,
            "invalid_blocks"  => [],
            "semantic_issues" => [],
            "blocks"          => [],
            "types"           => [],
            "types_by_source" => [],
            "conflicts"       => [],
            "faq_issues"      => [],
            "has_schema"      => false,
            "error"           => $error,
        ];
    }
}
