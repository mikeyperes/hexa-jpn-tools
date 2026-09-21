<?php

namespace Hexa\PluginCore\ContentCleanup;

final class ArticleMediaCleanupScanner {
    private ArticleMediaCleanupConfig $config;

    public function __construct( ArticleMediaCleanupConfig $config ) {
        $this->config = $config;
    }

    public function scan( array $criteria ): array {
        $criteria = $this->normalize_criteria( $criteria );
        $log      = [
            $this->log( 'info', 'Normalized article cleanup criteria.', $criteria ),
            $this->log( 'info', 'Building WordPress article cleanup query.' ),
        ];

        $args = [
            'post_type'              => $criteria['post_type'],
            'post_status'            => $this->query_statuses( $criteria['status'] ),
            'posts_per_page'         => $criteria['limit'],
            'offset'                 => $criteria['keep_recent'],
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'ignore_sticky_posts'    => true,
            'no_found_rows'          => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
        ];

        if ( '' !== $criteria['search'] ) {
            $args['s'] = $criteria['search'];
        }

        $posts = function_exists( 'get_posts' ) ? get_posts( $args ) : [];
        $rows  = [];
        foreach ( $posts as $post ) {
            if ( $post instanceof \WP_Post ) {
                $rows[] = $this->row_from_post( $post );
            }
        }

        $log[] = $this->log( 'success', 'Reported ' . count( $rows ) . ' article cleanup candidate(s).' );

        return [
            'criteria' => $criteria,
            'rows'     => $rows,
            'count'    => count( $rows ),
            'log'      => $log,
        ];
    }

    public function scan_deletion_plan( array $criteria, int $keep_recent, int $limit ): array {
        $criteria                = $this->normalize_criteria( $criteria );
        $criteria['keep_recent'] = 0;
        $keep_recent             = max( 0, $keep_recent );
        $limit                   = min( $this->config->max_limit(), max( 1, $limit ) );
        $ids                     = $this->matching_ids( $criteria, $limit + 1, [] );
        $has_more                = count( $ids ) > $limit;
        $ids                     = array_slice( $ids, 0, $limit );
        $posts_by_id             = $this->posts_by_id( $ids, $criteria );
        $rows                    = [];
        $preserved_ids           = [];
        $delete_ids              = [];

        foreach ( $ids as $index => $post_id ) {
            if ( ! isset( $posts_by_id[ $post_id ] ) || ! $posts_by_id[ $post_id ] instanceof \WP_Post ) {
                continue;
            }

            $row = $this->row_from_post( $posts_by_id[ $post_id ] );
            if ( $index < $keep_recent ) {
                $row['cleanup_status'] = 'preserved';
                $row['cleanup_label']  = 'Kept';
                $preserved_ids[]       = (int) $post_id;
            } else {
                $row['cleanup_status'] = 'queued';
                $row['cleanup_label']  = 'Will delete';
                $delete_ids[]          = (int) $post_id;
            }

            $rows[] = $row;
        }

        return [
            'criteria'      => array_merge( $criteria, [ 'keep_recent' => $keep_recent ] ),
            'rows'          => $rows,
            'count'         => count( $rows ),
            'preserved_ids' => $preserved_ids,
            'delete_ids'    => $delete_ids,
            'delete_count'  => count( $delete_ids ),
            'media_count'   => array_sum( array_map( static fn( array $row ): int => (int) ( $row['media_count'] ?? 0 ), $rows ) ),
            'delete_media_count' => array_sum( array_map( static fn( array $row ): int => 'queued' === (string) ( $row['cleanup_status'] ?? '' ) ? (int) ( $row['media_count'] ?? 0 ) : 0, $rows ) ),
            'has_more'      => $has_more,
            'log'           => [
                $this->log( 'info', 'Loaded article cleanup deletion plan.', [ 'keep_recent' => $keep_recent, 'rows' => count( $rows ), 'delete_count' => count( $delete_ids ), 'has_more' => $has_more ? 'yes' : 'no' ] ),
            ],
        ];
    }

    public function delete_post( int $post_id, bool $delete_media ): array|\WP_Error {
        $post = $this->editable_post_or_error( $post_id );
        if ( $post instanceof \WP_Error ) {
            return $post;
        }

        $title = get_the_title( $post_id );
        $media = $this->associated_media( $post );
        $log   = [
            $this->log( 'warning', 'Requested article deletion.', [ 'post_id' => $post_id, 'delete_media' => $delete_media ? 'yes' : 'no' ] ),
            $this->log( 'info', 'Detected associated media before deleting article.', [ 'media_count' => count( $media ), 'media_ids' => array_column( $media, 'id' ) ] ),
        ];

        $deleted_post = wp_delete_post( $post_id, true );
        if ( ! $deleted_post ) {
            return new \WP_Error( 'article_delete_failed', 'WordPress could not permanently delete this article.' );
        }

        if ( function_exists( 'get_post' ) && get_post( $post_id ) instanceof \WP_Post ) {
            return new \WP_Error( 'article_delete_not_verified', 'WordPress reported deletion, but the article still exists.' );
        }

        $deleted_media = [];
        $media_errors  = [];
        $media_status  = [];

        if ( $delete_media ) {
            foreach ( $media as $item ) {
                $attachment_id = (int) $item['id'];
                if ( $attachment_id <= 0 ) {
                    continue;
                }

                if ( ! function_exists( 'wp_delete_attachment' ) ) {
                    $media_errors[] = 'wp_delete_attachment is unavailable.';
                    $media_status[] = array_merge(
                        $item,
                        [
                            'status'  => 'failed',
                            'message' => 'wp_delete_attachment is unavailable.',
                        ]
                    );
                    continue;
                }

                $deleted = wp_delete_attachment( $attachment_id, true );
                $verified = $deleted && ( ! function_exists( 'get_post' ) || ! ( get_post( $attachment_id ) instanceof \WP_Post ) );
                if ( $verified ) {
                    $deleted_media[] = $item;
                    $media_status[]  = array_merge(
                        $item,
                        [
                            'status'  => 'deleted',
                            'message' => 'Deleted and verified.',
                        ]
                    );
                    $log[] = $this->log( 'success', 'Deleted associated media.', [ 'attachment_id' => $attachment_id, 'title' => $item['title'], 'source' => $item['source'] ] );
                } else {
                    $media_errors[] = $deleted ? 'Media ID ' . $attachment_id . ' still exists after deletion.' : 'Failed to delete media ID ' . $attachment_id;
                    $media_status[] = array_merge(
                        $item,
                        [
                            'status'  => 'failed',
                            'message' => $deleted ? 'Delete was not verified.' : 'Failed to delete media.',
                        ]
                    );
                    $log[] = $this->log( 'error', 'Associated media delete failed.', [ 'attachment_id' => $attachment_id, 'title' => $item['title'] ] );
                }
            }
        } else {
            foreach ( $media as $item ) {
                $media_status[] = array_merge(
                    $item,
                    [
                        'status'  => 'skipped',
                        'message' => 'Media cleanup was not enabled.',
                    ]
                );
            }
            $log[] = $this->log( 'info', 'Associated media deletion was not enabled. Media was left untouched.', [ 'media_count' => count( $media ) ] );
        }

        $log[] = $this->log( 'success', 'Permanently deleted and verified article.', [ 'post_id' => $post_id, 'title' => $title ] );

        return [
            'id'            => $post_id,
            'title'         => $title,
            'status'        => 'deleted',
            'message'       => 'Deleted and verified article: ' . $title,
            'media'         => $media,
            'deleted_media' => $deleted_media,
            'media_status'  => $media_status,
            'media_errors'  => $media_errors,
            'log'           => $log,
        ];
    }

    public function delete_batch( array $criteria, bool $delete_media, string $mode, int $batch_size = 0, array $exclude_ids = [] ): array|\WP_Error {
        $criteria   = $this->normalize_criteria( $criteria );
        $mode       = $this->clean_key( $mode );
        $batch_size = min( $this->config->max_batch_size(), max( 1, $batch_size > 0 ? $batch_size : $this->config->default_batch_size() ) );

        if ( ! in_array( $mode, [ 'all_matching', 'all_except_keep_recent' ], true ) ) {
            return new \WP_Error( 'invalid_batch_delete_mode', 'Invalid article batch delete mode.' );
        }

        $exclude_ids  = $this->normalize_ids( $exclude_ids );
        $preserve_ids = [];
        $log          = [
            $this->log(
                'warning',
                'Requested article batch deletion.',
                [
                    'mode'         => $mode,
                    'criteria'     => $criteria,
                    'batch_size'   => $batch_size,
                    'delete_media' => $delete_media ? 'yes' : 'no',
                    'exclude_ids'  => $exclude_ids,
                ]
            ),
        ];

        if ( 'all_matching' === $mode ) {
            $criteria['keep_recent'] = 0;
            $log[] = $this->log( 'info', 'Delete all matching mode ignores Keep Most Recent and Preview Limit.' );
        } else {
            $preserve_ids = $criteria['keep_recent'] > 0 ? $this->matching_ids( $criteria, $criteria['keep_recent'], [] ) : [];
            $log[]        = $this->log(
                'info',
                'Newest matching posts protected from this batch.',
                [
                    'keep_recent'  => $criteria['keep_recent'],
                    'preserve_ids' => $preserve_ids,
                ]
            );
        }

        $query_exclude_ids = array_values( array_unique( array_merge( $preserve_ids, $exclude_ids ) ) );
        $candidate_ids     = $this->matching_ids( $criteria, $batch_size + 1, $query_exclude_ids );
        $has_more          = count( $candidate_ids ) > $batch_size;
        $candidate_ids     = array_slice( $candidate_ids, 0, $batch_size );

        $log[] = $this->log(
            'info',
            'Loaded deletion batch IDs.',
            [
                'candidate_ids' => $candidate_ids,
                'has_more'      => $has_more ? 'yes' : 'no',
            ]
        );

        if ( [] === $candidate_ids ) {
            $log[] = $this->log( 'success', 'No more matching posts remain for this batch request.' );

            return [
                'mode'                => $mode,
                'criteria'            => $criteria,
                'batch_size'          => $batch_size,
                'deleted_ids'         => [],
                'failed_ids'          => [],
                'preserved_ids'       => $preserve_ids,
                'exclude_ids'         => $exclude_ids,
                'post_results'        => [],
                'deleted_count'       => 0,
                'failed_count'        => 0,
                'deleted_media_count' => 0,
                'has_more'            => false,
                'log'                 => $log,
            ];
        }

        $deleted_ids         = [];
        $failed_ids          = [];
        $deleted_media_count = 0;
        $post_results        = [];

        foreach ( $candidate_ids as $post_id ) {
            $result = $this->delete_post( (int) $post_id, $delete_media );
            if ( $result instanceof \WP_Error ) {
                $failed_ids[] = (int) $post_id;
                $post_results[] = [
                    'id'           => (int) $post_id,
                    'title'        => function_exists( 'get_the_title' ) ? (string) get_the_title( (int) $post_id ) : '',
                    'status'       => 'failed',
                    'message'      => $result->get_error_message(),
                    'media_status' => [],
                ];
                $log[]        = $this->log(
                    'error',
                    'Batch article deletion failed.',
                    [
                        'post_id' => (int) $post_id,
                        'error'   => $result->get_error_message(),
                    ]
                );
                continue;
            }

            $deleted_ids[]         = (int) $post_id;
            $deleted_media_count  += count( (array) ( $result['deleted_media'] ?? [] ) );
            $post_results[]        = [
                'id'           => (int) $post_id,
                'title'        => (string) ( $result['title'] ?? '' ),
                'status'       => 'deleted',
                'message'      => (string) ( $result['message'] ?? 'Deleted.' ),
                'media_status' => (array) ( $result['media_status'] ?? [] ),
            ];
            $log                   = array_merge( $log, (array) ( $result['log'] ?? [] ) );
        }

        if ( [] !== $failed_ids ) {
            $exclude_ids = array_values( array_unique( array_merge( $exclude_ids, $failed_ids ) ) );
            $log[]       = $this->log(
                'warning',
                'Failed post IDs will be excluded from follow-up batches to prevent a retry loop.',
                [ 'failed_ids' => $failed_ids ]
            );
        }

        $log[] = $this->log(
            'success',
            'Completed article deletion batch.',
            [
                'deleted_count'       => count( $deleted_ids ),
                'failed_count'        => count( $failed_ids ),
                'deleted_media_count' => $deleted_media_count,
                'has_more'            => $has_more ? 'yes' : 'no',
            ]
        );

        return [
            'mode'                => $mode,
            'criteria'            => $criteria,
            'batch_size'          => $batch_size,
            'deleted_ids'         => $deleted_ids,
            'failed_ids'          => $failed_ids,
            'preserved_ids'       => $preserve_ids,
            'exclude_ids'         => $exclude_ids,
            'post_results'        => $post_results,
            'deleted_count'       => count( $deleted_ids ),
            'failed_count'        => count( $failed_ids ),
            'deleted_media_count' => $deleted_media_count,
            'has_more'            => $has_more,
            'log'                 => $log,
        ];
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
            'post_type'   => $post_type,
            'status'      => $status,
            'keep_recent' => max( 0, (int) ( $criteria['keep_recent'] ?? $defaults['keep_recent'] ) ),
            'search'      => $this->sanitize_text( (string) ( $criteria['search'] ?? '' ) ),
            'limit'       => min( $this->config->max_limit(), max( 1, (int) ( $criteria['limit'] ?? $defaults['limit'] ) ) ),
        ];
    }

    private function row_from_post( \WP_Post $post ): array {
        $media = $this->associated_media( $post );
        $slug  = '' !== (string) $post->post_name ? (string) $post->post_name : '(no slug)';
        $title = get_the_title( $post );

        return [
            'id'              => (int) $post->ID,
            'title'           => '' !== $title ? $title : '(untitled)',
            'slug'            => $slug,
            'status'          => (string) $post->post_status,
            'post_type'       => (string) $post->post_type,
            'author'          => function_exists( 'get_the_author_meta' ) ? (string) get_the_author_meta( 'display_name', (int) $post->post_author ) : (string) $post->post_author,
            'published'       => (string) $post->post_date,
            'published_label' => $this->date_label( (string) $post->post_date ),
            'edit_url'        => function_exists( 'get_edit_post_link' ) ? (string) get_edit_post_link( $post->ID, 'raw' ) : '',
            'view_url'        => function_exists( 'get_permalink' ) ? (string) get_permalink( $post ) : '',
            'media'           => $media,
            'media_count'     => count( $media ),
        ];
    }

    private function associated_media( \WP_Post $post ): array {
        $items = [];
        $seen  = [];

        $add = function ( int $attachment_id, string $source ) use ( &$items, &$seen ): void {
            if ( $attachment_id <= 0 || isset( $seen[ $attachment_id ] ) ) {
                return;
            }

            $attachment = get_post( $attachment_id );
            if ( ! $attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type ) {
                return;
            }

            $seen[ $attachment_id ] = true;
            $items[] = [
                'id'     => $attachment_id,
                'title'  => get_the_title( $attachment_id ) ?: '(untitled media)',
                'source' => $source,
                'url'    => function_exists( 'wp_get_attachment_url' ) ? (string) wp_get_attachment_url( $attachment_id ) : '',
            ];
        };

        if ( function_exists( 'get_post_thumbnail_id' ) ) {
            $add( (int) get_post_thumbnail_id( $post->ID ), 'featured image' );
        }

        $content = (string) $post->post_content;
        if ( preg_match_all( '/wp-image-([0-9]+)/i', $content, $matches ) ) {
            foreach ( $matches[1] as $id ) {
                $add( (int) $id, 'inline image' );
            }
        }

        if ( preg_match_all( '/<img[^>]+src=[\"\']([^\"\']+)[\"\']/i', $content, $matches ) && function_exists( 'attachment_url_to_postid' ) ) {
            foreach ( $matches[1] as $url ) {
                $add( (int) attachment_url_to_postid( html_entity_decode( (string) $url ) ), 'inline image' );
            }
        }

        if ( preg_match_all( '/\[gallery[^\]]*ids=[\"\']?([^\"\'\]]+)/i', $content, $matches ) ) {
            foreach ( $matches[1] as $ids ) {
                foreach ( preg_split( '/\s*,\s*/', (string) $ids ) ?: [] as $id ) {
                    $add( (int) $id, 'gallery image' );
                }
            }
        }

        return $items;
    }

    private function matching_ids( array $criteria, int $limit, array $exclude_ids ): array {
        if ( $limit <= 0 || ! function_exists( 'get_posts' ) ) {
            return [];
        }

        $args = [
            'post_type'              => $criteria['post_type'],
            'post_status'            => $this->query_statuses( $criteria['status'] ),
            'posts_per_page'         => $limit,
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'fields'                 => 'ids',
            'ignore_sticky_posts'    => true,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];

        if ( [] !== $exclude_ids ) {
            $args['post__not_in'] = $exclude_ids;
        }

        if ( '' !== $criteria['search'] ) {
            $args['s'] = $criteria['search'];
        }

        return $this->normalize_ids( get_posts( $args ) );
    }

    /**
     * @param array<int,int> $ids
     * @return array<int,\WP_Post>
     */
    private function posts_by_id( array $ids, array $criteria ): array {
        if ( [] === $ids || ! function_exists( 'get_posts' ) ) {
            return [];
        }

        $posts = get_posts(
            [
                'post_type'              => $criteria['post_type'],
                'post_status'            => $this->query_statuses( $criteria['status'] ),
                'posts_per_page'         => count( $ids ),
                'post__in'               => $ids,
                'orderby'                => 'post__in',
                'ignore_sticky_posts'    => true,
                'no_found_rows'          => true,
                'update_post_meta_cache' => true,
                'update_post_term_cache' => false,
            ]
        );

        $by_id = [];
        foreach ( $posts as $post ) {
            if ( $post instanceof \WP_Post ) {
                $by_id[ (int) $post->ID ] = $post;
            }
        }

        return $by_id;
    }

    private function editable_post_or_error( int $post_id ): \WP_Post|\WP_Error {
        if ( $post_id <= 0 ) {
            return new \WP_Error( 'missing_post_id', 'Missing article ID.' );
        }

        $post = function_exists( 'get_post' ) ? get_post( $post_id ) : null;
        if ( ! $post instanceof \WP_Post ) {
            return new \WP_Error( 'article_not_found', 'Article was not found.' );
        }

        if ( ! isset( $this->config->post_types()[ $post->post_type ] ) ) {
            return new \WP_Error( 'post_type_not_allowed', 'This post type is not managed by this cleanup tool.' );
        }

        if ( function_exists( 'current_user_can' ) && ! current_user_can( 'delete_post', $post_id ) ) {
            return new \WP_Error( 'delete_permission_denied', 'You do not have permission to delete this article.' );
        }

        return $post;
    }

    private function query_statuses( string $status ): string|array {
        if ( 'any' !== $status ) {
            return $status;
        }

        return array_values( array_filter( array_keys( $this->config->statuses() ), static fn( string $item ): bool => 'any' !== $item ) );
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

    private function normalize_ids( array $ids ): array {
        $clean = [];
        foreach ( $ids as $id ) {
            $id = function_exists( 'absint' ) ? absint( $id ) : abs( (int) $id );
            if ( $id > 0 ) {
                $clean[] = $id;
            }
        }

        return array_values( array_unique( $clean ) );
    }

    private function clean_key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : ( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?: '' );
    }
}
