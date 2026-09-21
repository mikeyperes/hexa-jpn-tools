<?php

namespace Hexa\PluginCore\SchemaTools;

final class SchemaDocumentRenderer {
    /** @param array<string,mixed> $schema @return array<string,mixed> */
    public function normalize( array $schema ): array {
        $schema = SchemaGraph::clean( $schema );
        if ( empty( $schema ) ) {
            return [];
        }
        if ( isset( $schema['@graph'] ) && is_array( $schema['@graph'] ) ) {
            $schema['@context'] = $schema['@context'] ?? 'https://schema.org';
            $schema['@graph']   = $this->unique_nodes( $schema['@graph'] );
            return $schema;
        }
        $schema['@context'] = $schema['@context'] ?? 'https://schema.org';
        return $schema;
    }

    /** @param array<string,mixed> $schema */
    public function json( array $schema ): string {
        $schema = $this->normalize( $schema );
        if ( empty( $schema ) ) {
            return '';
        }
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        return function_exists( 'wp_json_encode' ) ? (string) wp_json_encode( $schema, $flags ) : (string) json_encode( $schema, $flags );
    }

    /** @param array<string,mixed> $schema */
    public function script( array $schema, string $id = '', string $class = '' ): string {
        $json = $this->json( $schema );
        if ( '' === $json ) {
            return '';
        }
        $id_attr = '' !== $id ? ' id="' . esc_attr( sanitize_html_class( $id ) ) . '"' : '';
        $class_attr = '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '';
        return '<script type="application/ld+json"' . $id_attr . $class_attr . '>' . $json . '</script>';
    }

    /** @param array<int,mixed> $nodes @return array<int,array<string,mixed>> */
    private function unique_nodes( array $nodes ): array {
        $result = [];
        $seen   = [];
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) || empty( $node ) ) {
                continue;
            }
            $key = isset( $node['@id'] ) ? 'id:' . (string) $node['@id'] : 'hash:' . md5( serialize( SchemaGraph::clean( $node ) ) );
            if ( isset( $seen[ $key ] ) ) {
                $result[ $seen[ $key ] ] = array_replace_recursive( $result[ $seen[ $key ] ], $node );
                continue;
            }
            $seen[ $key ] = count( $result );
            $result[] = SchemaGraph::clean( $node );
        }
        return $result;
    }
}
