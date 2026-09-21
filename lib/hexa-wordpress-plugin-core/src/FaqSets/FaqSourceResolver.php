<?php

namespace Hexa\PluginCore\FaqSets;

final class FaqSourceResolver {
    private FaqSetManager $manager;

    public function __construct( ?FaqSetManager $manager = null ) {
        $this->manager = $manager ?? new FaqSetManager();
    }

    /** @return array<int,array{question:string,answer:string}> */
    public function acf( string|int $context, string $field_name = 'faq', array $mapping = [] ): array {
        if ( ! function_exists( 'get_field' ) ) {
            return [];
        }
        $mapping = array_merge( [ 'question' => 'question', 'answer' => 'answer' ], $mapping );
        return $this->rows( get_field( $field_name, $context ), $mapping );
    }

    /** @return array<int,array{question:string,answer:string}> */
    public function post_meta( int $post_id, string $meta_key, array $mapping = [] ): array {
        $mapping = array_merge( [ 'question' => 'question', 'answer' => 'answer' ], $mapping );
        $rows = function_exists( 'get_post_meta' ) ? get_post_meta( $post_id, $meta_key, true ) : [];
        return $this->rows( $rows, $mapping );
    }

    /** @param mixed $rows @return array<int,array{question:string,answer:string}> */
    public function rows( mixed $rows, array $mapping = [] ): array {
        if ( ! is_array( $rows ) ) {
            return [];
        }
        $mapping = array_merge( [ 'question' => 'question', 'answer' => 'answer' ], $mapping );
        $normalized = [];
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $normalized[] = [
                'question' => $row[ $mapping['question'] ] ?? '',
                'answer' => $row[ $mapping['answer'] ] ?? '',
            ];
        }
        return $this->manager->normalizeItems( $normalized );
    }
}
