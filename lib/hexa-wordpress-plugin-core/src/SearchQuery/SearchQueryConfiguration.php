<?php

namespace Hexa\PluginCore\SearchQuery;

/**
 * Normalizes the reusable public-search behavior contract.
 *
 * Host plugins own option storage and discover their available post types and
 * taxonomies. Core owns the accepted modes, limits, and normalized shape.
 */
final class SearchQueryConfiguration {
    public const TERM_LOGICS = [ 'all', 'any', 'exact' ];
    public const WORD_MATCHING = [ 'whole', 'prefix', 'contains' ];
    public const SCOPES = [ 'shortcode', 'all' ];
    public const FIELDS = [ 'title', 'content', 'excerpt', 'slug' ];
    public const ORDERING = [ 'relevance', 'newest', 'oldest', 'title' ];

    /** @return array<string,mixed> */
    public static function defaults(): array {
        return [
            'enabled'          => false,
            'scope'            => 'shortcode',
            'term_logic'       => 'all',
            'word_matching'    => 'contains',
            'post_types'       => [ 'post', 'page' ],
            'fields'           => [ 'title', 'content', 'excerpt' ],
            'taxonomies'       => [],
            'authors'          => false,
            'custom_fields'    => [],
            'results_per_page' => 0,
            'orderby'          => 'relevance',
        ];
    }

    /**
     * @param array<string,mixed> $settings
     * @param array<int|string,mixed> $available_post_types
     * @param array<int|string,mixed> $available_taxonomies
     * @return array<string,mixed>
     */
    public static function normalize( array $settings, array $available_post_types = [], array $available_taxonomies = [] ): array {
        $defaults = self::defaults();
        $post_types = self::available_keys( $available_post_types );
        $taxonomies = self::available_keys( $available_taxonomies );

        if ( [] === $post_types ) {
            $post_types = self::keys( (array) ( $settings['post_types'] ?? $defaults['post_types'] ) );
        }
        if ( [] === $post_types ) {
            $post_types = $defaults['post_types'];
        }

        $selected_post_types = self::selected_keys( $settings['post_types'] ?? [], $post_types );
        if ( [] === $selected_post_types ) {
            $selected_post_types = array_values( array_intersect( $defaults['post_types'], $post_types ) );
        }
        if ( [] === $selected_post_types ) {
            $selected_post_types = [ reset( $post_types ) ];
        }

        $selected_taxonomies = self::selected_keys( $settings['taxonomies'] ?? [], $taxonomies );
        $authors = self::boolean( $settings['authors'] ?? $defaults['authors'] );
        $custom_fields = array_slice( self::keys( (array) ( $settings['custom_fields'] ?? [] ) ), 0, 20 );
        $fields = self::selected_keys( $settings['fields'] ?? [], self::FIELDS );
        $has_advanced_source = [] !== $selected_taxonomies || $authors || [] !== $custom_fields;
        if ( [] === $fields && ( ! array_key_exists( 'fields', $settings ) || ! $has_advanced_source ) ) {
            $fields = $defaults['fields'];
        }

        $results_per_page = (int) ( $settings['results_per_page'] ?? $defaults['results_per_page'] );
        $results_per_page = max( 0, min( 100, $results_per_page ) );

        return [
            'enabled'          => self::boolean( $settings['enabled'] ?? $defaults['enabled'] ),
            'scope'            => self::choice( $settings['scope'] ?? '', self::SCOPES, $defaults['scope'] ),
            'term_logic'       => self::choice( $settings['term_logic'] ?? '', self::TERM_LOGICS, $defaults['term_logic'] ),
            'word_matching'    => self::choice( $settings['word_matching'] ?? '', self::WORD_MATCHING, $defaults['word_matching'] ),
            'post_types'       => $selected_post_types,
            'fields'           => $fields,
            'taxonomies'       => $selected_taxonomies,
            'authors'          => $authors,
            'custom_fields'    => $custom_fields,
            'results_per_page' => $results_per_page,
            'orderby'          => self::choice( $settings['orderby'] ?? '', self::ORDERING, $defaults['orderby'] ),
        ];
    }

    /** @return array<string,array{label:string,description:string}> */
    public static function term_logic_options(): array {
        return [
            'all' => [
                'label'       => 'All words',
                'description' => 'Every entered word or quoted phrase must match somewhere in the selected sources.',
            ],
            'any' => [
                'label'       => 'Any word',
                'description' => 'A result can match any one of the entered words or quoted phrases.',
            ],
            'exact' => [
                'label'       => 'Exact phrase',
                'description' => 'The complete search text must appear together and in the same order.',
            ],
        ];
    }

    /** @return array<string,array{label:string,description:string,example:string}> */
    public static function word_matching_options(): array {
        return [
            'whole' => [
                'label'       => 'Whole words',
                'description' => 'Matches complete words only.',
                'example'     => 'press finds press, not pressure',
            ],
            'prefix' => [
                'label'       => 'Prefix wildcard',
                'description' => 'Matches words that begin with each search term.',
                'example'     => 'publ finds public and publication',
            ],
            'contains' => [
                'label'       => 'Contains wildcard',
                'description' => 'Matches a term anywhere inside a word; this mirrors normal WordPress partial matching.',
                'example'     => 'press finds press and pressure',
            ],
        ];
    }

    /** @return array<string,array{label:string,description:string}> */
    public static function field_options(): array {
        return [
            'title'   => [ 'label' => 'Title', 'description' => 'Post, page, or custom post type title.' ],
            'content' => [ 'label' => 'Content', 'description' => 'The main WordPress editor content.' ],
            'excerpt' => [ 'label' => 'Excerpt', 'description' => 'The manual or generated excerpt stored with the post.' ],
            'slug'    => [ 'label' => 'Slug', 'description' => 'The post_name value used in the permalink.' ],
        ];
    }

    /** @return array<string,string> */
    public static function scope_options(): array {
        return [
            'shortcode' => 'Hexa search shortcode only',
            'all'       => 'Every public WordPress search form',
        ];
    }

    /** @return array<string,string> */
    public static function ordering_options(): array {
        return [
            'relevance' => 'Relevance',
            'newest'    => 'Newest first',
            'oldest'    => 'Oldest first',
            'title'     => 'Title A-Z',
        ];
    }

    /** @param array<int|string,mixed> $values @return string[] */
    private static function available_keys( array $values ): array {
        $keys = [];
        foreach ( $values as $key => $value ) {
            $candidate = is_string( $key ) && ! ctype_digit( $key ) ? $key : $value;
            if ( is_object( $candidate ) && isset( $candidate->name ) ) {
                $candidate = $candidate->name;
            }
            if ( is_scalar( $candidate ) ) {
                $keys[] = (string) $candidate;
            }
        }

        return self::keys( $keys );
    }

    /** @param mixed $value @param string[] $allowed @return string[] */
    private static function selected_keys( $value, array $allowed ): array {
        if ( ! is_array( $value ) || [] === $allowed ) {
            return [];
        }

        return array_values( array_intersect( self::keys( $value ), $allowed ) );
    }

    /** @param array<int,mixed> $values @return string[] */
    private static function keys( array $values ): array {
        $keys = [];
        foreach ( $values as $value ) {
            if ( ! is_scalar( $value ) ) {
                continue;
            }
            $key = strtolower( trim( (string) $value ) );
            $key = (string) preg_replace( '/[^a-z0-9_\-]/', '', $key );
            if ( '' !== $key ) {
                $keys[] = $key;
            }
        }

        return array_values( array_unique( $keys ) );
    }

    /** @param mixed $value @param string[] $allowed */
    private static function choice( $value, array $allowed, string $fallback ): string {
        $value = strtolower( trim( (string) $value ) );

        return in_array( $value, $allowed, true ) ? $value : $fallback;
    }

    /** @param mixed $value */
    private static function boolean( $value ): bool {
        if ( is_bool( $value ) ) {
            return $value;
        }

        return in_array( strtolower( trim( (string) $value ) ), [ '1', 'true', 'yes', 'on' ], true );
    }
}
