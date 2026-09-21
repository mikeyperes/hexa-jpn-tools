<?php

namespace Hexa\PluginCore\ContentCleanup;

final class ContentCleanupScanner {
    private ContentCleanupConfig $config;

    public function __construct( ContentCleanupConfig $config ) {
        $this->config = $config;
    }

    public function normalize_criteria( array $criteria ): array {
        $defaults = $this->config->default_criteria();
        $criteria = array_merge( $defaults, $criteria );

        $post_type = $this->clean_key( (string) ( $criteria['post_type'] ?? $defaults['post_type'] ) );
        if ( ! isset( $this->config->post_types()[ $post_type ] ) ) {
            $post_type = (string) $defaults['post_type'];
        }

        $status = $this->clean_key( (string) ( $criteria['status'] ?? $defaults['status'] ) );
        if ( ! isset( $this->config->statuses()[ $status ] ) ) {
            $status = (string) $defaults['status'];
        }

        return [
            'post_type'             => $post_type,
            'status'                => $status,
            'published_before_days' => max( 0, (int) ( $criteria['published_before_days'] ?? $defaults['published_before_days'] ) ),
            'modified_before_days'  => max( 0, (int) ( $criteria['modified_before_days'] ?? $defaults['modified_before_days'] ) ),
            'search'                => $this->sanitize_text( (string) ( $criteria['search'] ?? '' ) ),
            'limit'                 => min( $this->config->max_limit(), max( 1, (int) ( $criteria['limit'] ?? $defaults['limit'] ) ) ),
        ];
    }

    public function scan( array $criteria ): array {
        $criteria = $this->normalize_criteria( $criteria );
        $rules    = $this->config->detection_rules();
        $log      = [
            $this->log( 'info', 'Normalized cleanup criteria.', $criteria ),
            $this->log( 'info', [] !== $rules ? 'Building WordPress content report from detection rules.' : 'Building WordPress content query.' ),
        ];

        $args = [
            'post_type'              => $criteria['post_type'],
            'post_status'            => $this->query_statuses( $criteria['status'] ),
            'posts_per_page'         => $criteria['limit'],
            'orderby'                => 'modified',
            'order'                  => 'ASC',
            'ignore_sticky_posts'    => true,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];

        if ( '' !== $criteria['search'] && [] === $rules ) {
            $args['s'] = $criteria['search'];
        }

        $date_query = [];
        if ( $criteria['published_before_days'] > 0 && [] === $rules ) {
            $date_query[] = [
                'column' => 'post_date',
                'before' => gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * $criteria['published_before_days'] ) ),
            ];
        }
        if ( $criteria['modified_before_days'] > 0 && [] === $rules ) {
            $date_query[] = [
                'column' => 'post_modified',
                'before' => gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * $criteria['modified_before_days'] ) ),
            ];
        }
        if ( [] !== $date_query ) {
            $date_query['relation'] = 'AND';
            $args['date_query']     = $date_query;
        }

        $posts = function_exists( 'get_posts' ) ? get_posts( $args ) : [];
        $rows  = [];
        foreach ( $posts as $post ) {
            if ( ! $post instanceof \WP_Post ) {
                continue;
            }
            $row = $this->row_from_post( $post );
            if ( [] !== $rules && empty( $row['flags'] ) ) {
                continue;
            }
            if ( $this->config->exclude_protected() && ! empty( $row['protected'] ) ) {
                continue;
            }
            $rows[] = $row;
        }

        if ( [] !== $rules ) {
            usort(
                $rows,
                static fn( array $a, array $b ): int => (int) ( $b['severity'] ?? 0 ) <=> (int) ( $a['severity'] ?? 0 )
                    ?: strcmp( (string) ( $a['title'] ?? '' ), (string) ( $b['title'] ?? '' ) )
            );
        }

        $log[] = $this->log( 'success', ( [] !== $rules ? 'Reported ' : 'Detected ' ) . count( $rows ) . ' matching content records.' );

        return [
            'criteria' => $criteria,
            'mode'     => [] !== $rules ? 'rules' : 'criteria',
            'rows'     => $rows,
            'count'    => count( $rows ),
            'log'      => $log,
        ];
    }

    public function trash( int $post_id ): array|\WP_Error {
        $post = $this->editable_post_or_error( $post_id, 'trash' );
        if ( $post instanceof \WP_Error ) {
            return $post;
        }

        $result = wp_trash_post( $post_id );
        if ( ! $result ) {
            return new \WP_Error( 'trash_failed', 'WordPress could not move this item to trash.' );
        }

        return [
            'id'      => $post_id,
            'message' => 'Moved to trash: ' . get_the_title( $post_id ),
            'log'     => [
                $this->log( 'info', 'Requested trash action.', [ 'post_id' => $post_id ] ),
                $this->log( 'success', 'WordPress moved the item to trash.', [ 'post_id' => $post_id ] ),
            ],
        ];
    }

    public function delete( int $post_id ): array|\WP_Error {
        $post = $this->editable_post_or_error( $post_id, 'delete' );
        if ( $post instanceof \WP_Error ) {
            return $post;
        }

        $title  = get_the_title( $post_id );
        $result = wp_delete_post( $post_id, true );
        if ( ! $result ) {
            return new \WP_Error( 'delete_failed', 'WordPress could not permanently delete this item.' );
        }

        return [
            'id'      => $post_id,
            'message' => 'Permanently deleted: ' . $title,
            'log'     => [
                $this->log( 'warning', 'Requested permanent delete action.', [ 'post_id' => $post_id ] ),
                $this->log( 'success', 'WordPress permanently deleted the item.', [ 'post_id' => $post_id ] ),
            ],
        ];
    }

    public function row_from_post( \WP_Post $post ): array {
        $protection = $this->protection_reason( (int) $post->ID );
        $slug       = '' !== (string) $post->post_name ? (string) $post->post_name : '(no slug)';
        $title      = get_the_title( $post );
        $flags      = $this->flags_for_post( $post );

        return [
            'id'               => (int) $post->ID,
            'title'            => '' !== $title ? $title : '(untitled)',
            'slug'             => $slug,
            'status'           => (string) $post->post_status,
            'post_type'        => (string) $post->post_type,
            'published'        => (string) $post->post_date,
            'published_label'  => $this->date_label( (string) $post->post_date ),
            'modified'         => (string) $post->post_modified,
            'modified_label'   => $this->date_label( (string) $post->post_modified ),
            'edit_url'         => function_exists( 'get_edit_post_link' ) ? (string) get_edit_post_link( $post->ID, 'raw' ) : '',
            'view_url'         => function_exists( 'get_permalink' ) ? (string) get_permalink( $post ) : '',
            'protected'        => '' !== $protection,
            'protected_reason' => $protection,
            'flags'            => $flags,
            'severity'         => $this->max_severity( $flags ),
        ];
    }

    private function editable_post_or_error( int $post_id, string $action ): \WP_Post|\WP_Error {
        if ( $post_id <= 0 ) {
            return new \WP_Error( 'missing_post_id', 'Missing content ID.' );
        }

        $post = function_exists( 'get_post' ) ? get_post( $post_id ) : null;
        if ( ! $post instanceof \WP_Post ) {
            return new \WP_Error( 'content_not_found', 'Content item was not found.' );
        }

        if ( ! isset( $this->config->post_types()[ $post->post_type ] ) ) {
            return new \WP_Error( 'post_type_not_allowed', 'This content type is not managed by this cleanup tool.' );
        }

        $protection = $this->protection_reason( $post_id );
        if ( '' !== $protection ) {
            return new \WP_Error( 'protected_content', 'This item is protected: ' . $protection );
        }

        $cap = 'delete' === $action ? 'delete_post' : 'delete_post';
        if ( function_exists( 'current_user_can' ) && ! current_user_can( $cap, $post_id ) ) {
            return new \WP_Error( 'delete_permission_denied', 'You do not have permission to delete this item.' );
        }

        return $post;
    }

    private function protection_reason( int $post_id ): string {
        if ( $post_id <= 0 ) {
            return '';
        }

        $protected = array_fill_keys( $this->config->protected_post_ids(), 'Host protected page' );
        if ( function_exists( 'get_option' ) ) {
            $front = absint( get_option( 'page_on_front' ) );
            $posts = absint( get_option( 'page_for_posts' ) );
            $privacy = absint( get_option( 'wp_page_for_privacy_policy' ) );
            if ( $front > 0 ) {
                $protected[ $front ] = 'WordPress front page';
            }
            if ( $posts > 0 ) {
                $protected[ $posts ] = 'WordPress posts page';
            }
            if ( $privacy > 0 ) {
                $protected[ $privacy ] = 'WordPress privacy policy page';
            }
        }

        return isset( $protected[ $post_id ] ) ? (string) $protected[ $post_id ] : '';
    }

    private function query_statuses( string $status ): string|array {
        if ( 'any' !== $status ) {
            return $status;
        }

        return array_values( array_filter( array_keys( $this->config->statuses() ), static fn( string $item ): bool => 'any' !== $item ) );
    }

    private function flags_for_post( \WP_Post $post ): array {
        $flags = [];

        foreach ( $this->config->detection_rules() as $rule ) {
            if ( $this->rule_excludes_post( $rule, (int) $post->ID ) ) {
                continue;
            }

            $matched_term = $this->matched_rule_term( $post, $rule );
            if ( '' === $matched_term ) {
                continue;
            }

            $flags[] = [
                'id'          => (string) $rule['id'],
                'label'       => (string) $rule['label'],
                'tone'        => (string) $rule['tone'],
                'term'        => $matched_term,
                'description' => (string) $rule['description'],
            ];
        }

        return $flags;
    }

    private function matched_rule_term( \WP_Post $post, array $rule ): string {
        $haystack = $this->rule_haystack( $post, (array) ( $rule['fields'] ?? [ 'title', 'slug' ] ) );
        foreach ( (array) ( $rule['terms'] ?? [] ) as $term ) {
            $term = trim( (string) $term );
            if ( '' === $term ) {
                continue;
            }

            if ( $this->term_matches( $haystack, $term, (string) ( $rule['match'] ?? 'word' ) ) ) {
                return $term;
            }
        }

        return '';
    }

    private function rule_haystack( \WP_Post $post, array $fields ): string {
        $parts = [];
        foreach ( $fields as $field ) {
            switch ( $field ) {
                case 'title':
                    $parts[] = get_the_title( $post );
                    break;
                case 'slug':
                    $parts[] = (string) $post->post_name;
                    break;
                case 'content':
                    $parts[] = (string) $post->post_content;
                    break;
                case 'excerpt':
                    $parts[] = (string) $post->post_excerpt;
                    break;
            }
        }

        return implode( "\n", $parts );
    }

    private function term_matches( string $haystack, string $term, string $match ): bool {
        if ( 'contains' === $match ) {
            return false !== stripos( $haystack, $term );
        }

        return 1 === preg_match( '/(?<![A-Za-z0-9])' . preg_quote( $term, '/' ) . '(?![A-Za-z0-9])/i', $haystack );
    }

    private function rule_excludes_post( array $rule, int $post_id ): bool {
        if ( in_array( $post_id, (array) ( $rule['exclude_post_ids'] ?? [] ), true ) ) {
            return true;
        }

        if ( function_exists( 'get_option' ) ) {
            foreach ( (array) ( $rule['exclude_option_ids'] ?? [] ) as $option ) {
                if ( $post_id === absint( get_option( (string) $option ) ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function max_severity( array $flags ): int {
        $max = 0;
        foreach ( $flags as $flag ) {
            $max = max(
                $max,
                match ( (string) ( $flag['tone'] ?? '' ) ) {
                    'danger' => 3,
                    'warning' => 2,
                    'success' => 1,
                    default => 0,
                }
            );
        }

        return $max;
    }

    private function log( string $level, string $message, array $context = [] ): array {
        return [
            'time'    => gmdate( 'H:i:s' ),
            'level'   => $level,
            'message' => $message,
            'context' => $context,
        ];
    }

    private function date_label( string $mysql_date ): string {
        if ( '' === $mysql_date || '0000-00-00 00:00:00' === $mysql_date ) {
            return 'Not set';
        }

        return function_exists( 'mysql2date' ) ? mysql2date( 'M j, Y g:i a', $mysql_date ) : $mysql_date;
    }

    private function sanitize_text( string $value ): string {
        return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : trim( strip_tags( $value ) );
    }

    private function clean_key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : ( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?: '' );
    }
}
