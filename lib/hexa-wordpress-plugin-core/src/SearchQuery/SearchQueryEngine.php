<?php

namespace Hexa\PluginCore\SearchQuery;

/**
 * Applies a host-provided search configuration to one exact frontend query.
 *
 * One permanent SQL dispatcher consumes weakly stored state for exact query
 * objects, so duplicate preparation cannot stack query-capturing closures.
 */
final class SearchQueryEngine {
    public const EXPLICIT_QUERY_VAR = 'hexa_search_query_explicit';

    /** @var callable */
    private $settings_provider;

    private string $marker_key;

    private bool $registered = false;

    private bool $search_dispatcher_registered = false;

    /** @var \WeakMap<object,array{raw_query:string,settings:array<string,mixed>,meta_constraints:array<string|int,mixed>}>|null */
    private ?\WeakMap $prepared_queries = null;

    public function __construct( callable $settings_provider, string $marker_key = 'hexa_search' ) {
        $this->settings_provider = $settings_provider;
        $this->marker_key = self::key( $marker_key );

        if ( '' === $this->marker_key ) {
            $this->marker_key = 'hexa_search';
        }
    }

    public function register(): void {
        if ( $this->registered ) {
            return;
        }

        add_filter( 'query_vars', [ $this, 'register_query_var' ] );
        add_action( 'pre_get_posts', [ $this, 'prepare_query' ], 20 );
        $this->register_search_dispatcher();
        $this->registered = true;
    }

    /**
     * Registers only the exact-query SQL dispatcher. Trusted component
     * adapters use this without attaching the native main-query hooks.
     */
    public function register_search_dispatcher(): void {
        if ( $this->search_dispatcher_registered ) {
            return;
        }

        add_filter( 'posts_search', [ $this, 'filter_search_sql' ], 999, 2 );
        $this->search_dispatcher_registered = true;
    }

    /** @param string[] $query_vars @return string[] */
    public function register_query_var( array $query_vars ): array {
        if ( ! in_array( $this->marker_key, $query_vars, true ) ) {
            $query_vars[] = $this->marker_key;
        }

        return $query_vars;
    }

    /** @param object $query */
    public function prepare_query( $query ): void {
        $this->forget_prepared_query( $query );

        if ( ! $this->is_candidate_query( $query ) ) {
            return;
        }

        $this->prepare_allowed_query( $query, false );
    }

    /**
     * Prepares one exact query already authenticated by a trusted component
     * adapter. REST/AJAX is allowed here because the adapter, rather than a
     * visitor query variable, proves the query provenance.
     *
     * @param object $query
     * @return array<string,mixed>|null The normalized settings when prepared.
     */
    public function prepare_explicit_query( $query ): ?array {
        $this->forget_prepared_query( $query );

        if ( ! $this->is_explicit_candidate_query( $query ) ) {
            return null;
        }

        return $this->prepare_allowed_query( $query, true );
    }

    /** @param object $query @return array<string,mixed>|null */
    private function prepare_allowed_query( $query, bool $explicit ): ?array {

        $provided = call_user_func( $this->settings_provider );
        if ( ! is_array( $provided ) ) {
            return null;
        }

        $settings = SearchQueryConfiguration::normalize(
            $provided,
            (array) ( $provided['post_types'] ?? [] ),
            (array) ( $provided['taxonomies'] ?? [] )
        );

        if ( ! $this->configuration_allows_query( $query, $settings, $explicit ) ) {
            return null;
        }

        $query->set( 'post_type', $settings['post_types'] );
        if ( $explicit ) {
            $query->set( 'post_status', 'publish' );
            $query->set( 'has_password', false );
            $query->set( 'ignore_sticky_posts', true );
        }
        if ( $settings['results_per_page'] > 0 ) {
            $query->set( 'posts_per_page', $settings['results_per_page'] );
        }
        $this->apply_ordering( $query, (string) $settings['orderby'] );
        $query->set( 'hexa_search_query_active', 1 );

        if ( ! $this->prepared_queries instanceof \WeakMap ) {
            $this->prepared_queries = new \WeakMap();
        }
        $this->prepared_queries[ $query ] = [
            'raw_query'        => trim( (string) $query->get( 's' ) ),
            'settings'         => $settings,
            'meta_constraints' => [],
        ];

        return $settings;
    }

    /**
     * Adds trusted, bounded post-meta constraints to one already prepared query.
     * The constraints are consumed with the same exact-object search state and
     * never accepted from visitor query variables.
     *
     * @param object $query
     * @param array<string|int,mixed> $constraints
     */
    public function set_meta_constraints( $query, array $constraints ): void {
        if ( ! is_object( $query )
            || ! $this->prepared_queries instanceof \WeakMap
            || ! isset( $this->prepared_queries[ $query ] )
        ) {
            return;
        }

        $prepared = $this->prepared_queries[ $query ];
        $prepared['meta_constraints'] = $constraints;
        $this->prepared_queries[ $query ] = $prepared;
    }

    /** @param mixed $search_sql @param mixed $query */
    public function filter_search_sql( $search_sql, $query ): string {
        if ( ! is_object( $query )
            || ! $this->prepared_queries instanceof \WeakMap
            || ! isset( $this->prepared_queries[ $query ] )
        ) {
            return (string) $search_sql;
        }

        $prepared = $this->prepared_queries[ $query ];
        unset( $this->prepared_queries[ $query ] );

        return $this->build_search_sql(
            $prepared['raw_query'],
            $prepared['settings'],
            null,
            $prepared['meta_constraints']
        );
    }

    /**
     * @param array<string,mixed> $settings
     * @param object|null $database wpdb-compatible object; injectable for tests.
     * @param array<string|int,mixed> $meta_constraints
     */
    public function build_search_sql( string $raw_query, array $settings, $database = null, array $meta_constraints = [] ): string {
        if ( null === $database ) {
            global $wpdb;
            $database = $wpdb;
        }

        if ( ! is_object( $database ) || ! method_exists( $database, 'prepare' ) || ! method_exists( $database, 'esc_like' ) ) {
            return '';
        }

        $settings = SearchQueryConfiguration::normalize(
            $settings,
            (array) ( $settings['post_types'] ?? [] ),
            (array) ( $settings['taxonomies'] ?? [] )
        );
        $terms = SearchTermParser::parse( $raw_query, (string) $settings['term_logic'] );
        if ( [] === $terms ) {
            return '';
        }

        $groups = [];
        foreach ( $terms as $term ) {
            $sources = $this->source_conditions( $database, $term, $settings );
            if ( [] !== $sources ) {
                $groups[] = '(' . implode( ' OR ', $sources ) . ')';
            }
        }

        if ( [] === $groups ) {
            return '';
        }

        $relation = 'any' === $settings['term_logic'] ? ' OR ' : ' AND ';
        $sql = ' AND (' . implode( $relation, $groups ) . ')';
        if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() ) {
            $sql .= ' AND (' . $database->posts . ".post_password = '')";
        }
        $constraint_sql = MetaConstraintSql::compile( $database, $meta_constraints );
        if ( '' !== $constraint_sql ) {
            $sql .= ' AND (' . $constraint_sql . ')';
        }

        return $sql . ' ';
    }

    /** @param object $query */
    private function is_candidate_query( $query ): bool {
        if ( ! is_object( $query ) || ! method_exists( $query, 'get' ) || ! method_exists( $query, 'set' ) ) {
            return false;
        }
        if ( function_exists( 'is_admin' ) && is_admin() ) {
            return false;
        }
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            return false;
        }
        if ( ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
            return false;
        }
        if ( ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
            return false;
        }
        if ( ( function_exists( 'wp_is_serving_rest_request' ) && wp_is_serving_rest_request() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return false;
        }
        if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
            return false;
        }
        $is_main_query = method_exists( $query, 'is_main_query' ) && $query->is_main_query();
        $is_explicit_query = '1' === (string) $query->get( self::EXPLICIT_QUERY_VAR );
        if ( ! $is_main_query && ! $is_explicit_query ) {
            return false;
        }
        if ( ! method_exists( $query, 'is_search' ) || ! $query->is_search() ) {
            return false;
        }
        if ( method_exists( $query, 'is_feed' ) && $query->is_feed() ) {
            return false;
        }
        if ( '' === trim( (string) $query->get( 's' ) ) || $query->get( 'suppress_filters' ) || $query->get( 'hexa_search_query_disabled' ) ) {
            return false;
        }

        return true;
    }

    /** @param object $query @param array<string,mixed> $settings */
    private function configuration_allows_query( $query, array $settings, bool $explicit = false ): bool {
        if ( ! $settings['enabled'] ) {
            return false;
        }
        if ( ! $explicit && 'shortcode' === $settings['scope'] && '1' !== (string) $query->get( $this->marker_key ) ) {
            return false;
        }

        if ( function_exists( 'apply_filters' ) ) {
            return (bool) apply_filters( 'hexa_plugin_core_search_query_should_handle', true, $query, $settings );
        }

        return true;
    }

    /** @param mixed $query */
    private function is_explicit_candidate_query( $query ): bool {
        if ( ! is_object( $query ) || ! method_exists( $query, 'get' ) || ! method_exists( $query, 'set' ) ) {
            return false;
        }
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            return false;
        }
        if ( ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
            return false;
        }
        if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
            return false;
        }
        if ( '' === trim( (string) $query->get( 's' ) ) || $query->get( 'suppress_filters' ) || $query->get( 'hexa_search_query_disabled' ) ) {
            return false;
        }

        return true;
    }

    /** @param object $query */
    private function apply_ordering( $query, string $orderby ): void {
        switch ( $orderby ) {
            case 'newest':
                $query->set( 'orderby', 'date' );
                $query->set( 'order', 'DESC' );
                break;
            case 'oldest':
                $query->set( 'orderby', 'date' );
                $query->set( 'order', 'ASC' );
                break;
            case 'title':
                $query->set( 'orderby', 'title' );
                $query->set( 'order', 'ASC' );
                break;
            case 'relevance':
            default:
                $query->set( 'orderby', 'relevance' );
                break;
        }
    }

    /** @param object $database @param array<string,mixed> $settings @return string[] */
    private function source_conditions( $database, string $term, array $settings ): array {
        $field_columns = [
            'title'   => $database->posts . '.post_title',
            'content' => $database->posts . '.post_content',
            'excerpt' => $database->posts . '.post_excerpt',
            'slug'    => $database->posts . '.post_name',
        ];
        $matching = 'exact' === $settings['term_logic'] ? 'contains' : (string) $settings['word_matching'];
        $conditions = [];

        foreach ( $settings['fields'] as $field ) {
            if ( isset( $field_columns[ $field ] ) ) {
                $conditions[] = $this->match_condition( $database, $field_columns[ $field ], $term, $matching );
            }
        }

        if ( ! empty( $settings['taxonomies'] ) ) {
            $taxonomy_conditions = [];
            foreach ( $settings['taxonomies'] as $taxonomy ) {
                $taxonomy_conditions[] = $database->prepare( 'hexa_sq_tt.taxonomy = %s', $taxonomy );
            }
            $conditions[] = 'EXISTS (SELECT 1 FROM ' . $database->term_relationships . ' hexa_sq_tr'
                . ' INNER JOIN ' . $database->term_taxonomy . ' hexa_sq_tt ON hexa_sq_tt.term_taxonomy_id = hexa_sq_tr.term_taxonomy_id'
                . ' INNER JOIN ' . $database->terms . ' hexa_sq_t ON hexa_sq_t.term_id = hexa_sq_tt.term_id'
                . ' WHERE hexa_sq_tr.object_id = ' . $database->posts . '.ID'
                . ' AND (' . implode( ' OR ', $taxonomy_conditions ) . ')'
                . ' AND ' . $this->match_condition( $database, 'hexa_sq_t.name', $term, $matching ) . ')';
        }

        if ( ! empty( $settings['authors'] ) ) {
            $conditions[] = 'EXISTS (SELECT 1 FROM ' . $database->users . ' hexa_sq_u'
                . ' WHERE hexa_sq_u.ID = ' . $database->posts . '.post_author'
                . ' AND ' . $this->match_condition( $database, 'hexa_sq_u.display_name', $term, $matching ) . ')';
        }

        if ( ! empty( $settings['custom_fields'] ) ) {
            $meta_conditions = [];
            foreach ( $settings['custom_fields'] as $meta_key ) {
                $meta_conditions[] = $database->prepare( 'hexa_sq_pm.meta_key = %s', $meta_key );
            }
            $conditions[] = 'EXISTS (SELECT 1 FROM ' . $database->postmeta . ' hexa_sq_pm'
                . ' WHERE hexa_sq_pm.post_id = ' . $database->posts . '.ID'
                . ' AND (' . implode( ' OR ', $meta_conditions ) . ')'
                . ' AND ' . $this->match_condition( $database, 'hexa_sq_pm.meta_value', $term, $matching ) . ')';
        }

        if ( ! empty( $settings['user_reference_fields'] ) ) {
            $reference_conditions = [];
            foreach ( $settings['user_reference_fields'] as $meta_key ) {
                $reference_conditions[] = $database->prepare( 'hexa_sq_ur.meta_key = %s', $meta_key );
            }
            $conditions[] = 'EXISTS (SELECT 1 FROM ' . $database->postmeta . ' hexa_sq_ur'
                . ' INNER JOIN ' . $database->users . ' hexa_sq_ru ON hexa_sq_ru.ID = CAST(hexa_sq_ur.meta_value AS UNSIGNED)'
                . ' WHERE hexa_sq_ur.post_id = ' . $database->posts . '.ID'
                . ' AND (' . implode( ' OR ', $reference_conditions ) . ')'
                . ' AND ' . $this->match_condition( $database, 'hexa_sq_ru.display_name', $term, $matching ) . ')';
        }

        return array_values( array_filter( $conditions ) );
    }

    /** @param object $database */
    private function match_condition( $database, string $column, string $term, string $matching ): string {
        return SearchMatchSql::condition( $database, $column, $term, $matching );
    }

    private static function key( string $value ): string {
        $value = strtolower( trim( $value ) );

        return (string) preg_replace( '/[^a-z0-9_\-]/', '', $value );
    }

    /** @param mixed $query */
    private function forget_prepared_query( $query ): void {
        if ( is_object( $query )
            && $this->prepared_queries instanceof \WeakMap
            && isset( $this->prepared_queries[ $query ] )
        ) {
            unset( $this->prepared_queries[ $query ] );
        }
    }
}
