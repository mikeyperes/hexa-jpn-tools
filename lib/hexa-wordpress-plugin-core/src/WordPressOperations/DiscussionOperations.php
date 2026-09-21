<?php

namespace Hexa\PluginCore\WordPressOperations;

/** Native, bounded discussion-closing and permanent comment-deletion actions. */
final class DiscussionOperations {
    private const BATCH_SIZE = 100;
    private const MAX_BATCHES = 1000;
    private const REPORT_LIMIT = 100;

    /** @param array<string,callable> $callbacks */
    public function __construct( private array $callbacks = [] ) {}

    /** @return array<string,mixed> */
    public function status(): array {
        if ( isset( $this->callbacks['status'] ) ) {
            return (array) call_user_func( $this->callbacks['status'] );
        }
        $open = $this->open_post_ids( self::BATCH_SIZE );
        $comments = function_exists( 'get_comments' ) ? (int) get_comments( [ 'count' => true, 'status' => 'all' ] ) : 0;
        $default_comments = function_exists( 'get_option' ) ? (string) get_option( 'default_comment_status', 'open' ) : '';
        $default_pings = function_exists( 'get_option' ) ? (string) get_option( 'default_ping_status', 'open' ) : '';
        return [ 'success' => true, 'action' => 'status', 'available' => function_exists( 'wp_update_post' ) && function_exists( 'wp_delete_comment' ), 'before' => [], 'after' => [], 'counts' => [ 'open_posts' => count( $open ), 'comments' => $comments ], 'items' => [ 'open_post_ids' => $open, 'open_posts_bounded' => count( $open ) >= self::BATCH_SIZE, 'default_comment_status' => $default_comments, 'default_ping_status' => $default_pings ], 'messages' => [] ];
    }

    /** @return array<string,mixed> */
    public function close_comments_and_pings( array $post_ids = [] ): array {
        if ( isset( $this->callbacks['close_comments_and_pings'] ) ) {
            return (array) call_user_func( $this->callbacks['close_comments_and_pings'], $post_ids );
        }
        $before = $this->status();
        if ( function_exists( 'update_option' ) ) {
            update_option( 'default_comment_status', 'closed' );
            update_option( 'default_ping_status', 'closed' );
        }
        $all = [] === $post_ids;
        $requested_ids = $this->normalize_ids( $post_ids );
        $updated = 0;
        $failed = 0;
        $attempted = 0;
        $items = [];
        $stopped_no_progress = false;
        for ( $batch = 0; $batch < self::MAX_BATCHES; $batch++ ) {
            $targets = $all ? $this->open_post_ids( self::BATCH_SIZE ) : array_slice( $requested_ids, $batch * self::BATCH_SIZE, self::BATCH_SIZE );
            if ( [] === $targets ) {
                break;
            }
            $batch_updated = 0;
            foreach ( $targets as $post_id ) {
                $attempted++;
                $result = function_exists( 'wp_update_post' ) ? wp_update_post( [ 'ID' => $post_id, 'comment_status' => 'closed', 'ping_status' => 'closed' ], true ) : false;
                $ok = ! ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) && false !== $result && 0 !== $result;
                $updated += $ok ? 1 : 0;
                $failed += $ok ? 0 : 1;
                $batch_updated += $ok ? 1 : 0;
                $this->append_report_item( $items, [ 'post_id' => $post_id, 'success' => $ok ] );
            }

            if ( $all ) {
                $remaining = $this->open_post_ids( self::BATCH_SIZE );
                if ( [] === $remaining ) {
                    break;
                }
                if ( 0 === $batch_updated || $remaining === $targets ) {
                    $stopped_no_progress = true;
                    break;
                }
                continue;
            }

            if ( count( $targets ) < self::BATCH_SIZE ) {
                break;
            }
        }
        $after = $this->status();
        $remaining = $all
            ? $this->open_post_ids( self::REPORT_LIMIT + 1 )
            : array_slice( $requested_ids, $attempted, self::REPORT_LIMIT + 1 );
        $unprocessed_ids = array_slice( $remaining, 0, self::REPORT_LIMIT );
        $unprocessed_truncated = count( $remaining ) > self::REPORT_LIMIT;
        $success = 0 === $failed
            && 0 === (int) ( $after['counts']['open_posts'] ?? 0 )
            && 'closed' === (string) ( $after['items']['default_comment_status'] ?? '' )
            && 'closed' === (string) ( $after['items']['default_ping_status'] ?? '' );
        return [
            'success'                   => $success,
            'action'                    => 'close_comments_and_pings',
            'before'                    => $before,
            'after'                     => $after,
            'counts'                    => [
                'requested'            => $all ? $attempted : count( $requested_ids ),
                'attempted'            => $attempted,
                'updated'              => $updated,
                'failed'               => $failed,
                'reported'             => count( $items ),
                'unprocessed_reported' => count( $unprocessed_ids ),
            ],
            'items'                     => $items,
            'items_truncated'           => $attempted > count( $items ),
            'unprocessed_ids'           => $unprocessed_ids,
            'unprocessed_ids_truncated' => $unprocessed_truncated,
            'stopped_no_progress'       => $stopped_no_progress,
            'messages'                  => [
                $success
                    ? 'Comments and pings were closed.'
                    : ( $stopped_no_progress ? 'Processing stopped because the remaining posts made no progress.' : 'Some posts remain open or failed to update.' ),
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function delete_comments( array $comment_ids = [] ): array {
        if ( isset( $this->callbacks['delete_comments'] ) ) {
            return (array) call_user_func( $this->callbacks['delete_comments'], $comment_ids );
        }
        $before = $this->status();
        $all = [] === $comment_ids;
        $requested_ids = $this->normalize_ids( $comment_ids );
        $deleted = 0;
        $failed = 0;
        $attempted = 0;
        $items = [];
        $stopped_no_progress = false;
        for ( $batch = 0; $batch < self::MAX_BATCHES; $batch++ ) {
            $targets = $all ? $this->comment_ids( self::BATCH_SIZE ) : array_slice( $requested_ids, $batch * self::BATCH_SIZE, self::BATCH_SIZE );
            if ( [] === $targets ) {
                break;
            }
            $batch_deleted = 0;
            foreach ( $targets as $comment_id ) {
                $attempted++;
                $ok = function_exists( 'wp_delete_comment' ) && (bool) wp_delete_comment( $comment_id, true );
                $deleted += $ok ? 1 : 0;
                $failed += $ok ? 0 : 1;
                $batch_deleted += $ok ? 1 : 0;
                $this->append_report_item( $items, [ 'comment_id' => $comment_id, 'success' => $ok, 'permanent' => true ] );
            }

            if ( $all ) {
                $remaining = $this->comment_ids( self::BATCH_SIZE );
                if ( [] === $remaining ) {
                    break;
                }
                if ( 0 === $batch_deleted || $remaining === $targets ) {
                    $stopped_no_progress = true;
                    break;
                }
                continue;
            }

            if ( count( $targets ) < self::BATCH_SIZE ) {
                break;
            }
        }
        $after = $this->status();
        $remaining = $all
            ? $this->comment_ids( self::REPORT_LIMIT + 1 )
            : array_slice( $requested_ids, $attempted, self::REPORT_LIMIT + 1 );
        $unprocessed_ids = array_slice( $remaining, 0, self::REPORT_LIMIT );
        $unprocessed_truncated = count( $remaining ) > self::REPORT_LIMIT;
        $success = 0 === $failed && ( ! $all || 0 === (int) ( $after['counts']['comments'] ?? 0 ) );
        return [
            'success'                   => $success,
            'action'                    => 'delete_comments',
            'before'                    => $before,
            'after'                     => $after,
            'counts'                    => [
                'requested'            => $all ? $attempted : count( $requested_ids ),
                'attempted'            => $attempted,
                'deleted'              => $deleted,
                'failed'               => $failed,
                'reported'             => count( $items ),
                'unprocessed_reported' => count( $unprocessed_ids ),
            ],
            'items'                     => $items,
            'items_truncated'           => $attempted > count( $items ),
            'unprocessed_ids'           => $unprocessed_ids,
            'unprocessed_ids_truncated' => $unprocessed_truncated,
            'stopped_no_progress'       => $stopped_no_progress,
            'messages'                  => [
                $success
                    ? 'Comments were permanently deleted through WordPress APIs.'
                    : ( $stopped_no_progress ? 'Processing stopped because the remaining comments made no progress.' : 'Some comments remain or failed to delete.' ),
            ],
        ];
    }

    /** @return list<int> */
    private function normalize_ids( array $ids ): array {
        return array_values( array_unique( array_filter( array_map( 'intval', $ids ), static fn( int $id ): bool => $id > 0 ) ) );
    }

    /** @param array<int,array<string,mixed>> $items
     *  @param array<string,mixed> $item
     */
    private function append_report_item( array &$items, array $item ): void {
        if ( count( $items ) < self::REPORT_LIMIT ) {
            $items[] = $item;
        }
    }

    /** @return list<int> */
    private function comment_ids( int $limit ): array {
        if ( ! function_exists( 'get_comments' ) ) {
            return [];
        }

        return $this->normalize_ids(
            (array) get_comments(
                [
                    'number'  => $limit,
                    'status'  => 'all',
                    'fields'  => 'ids',
                    'orderby' => 'comment_ID',
                    'order'   => 'ASC',
                ]
            )
        );
    }

    /** @return list<int> */
    private function open_post_ids( int $limit = -1 ): array {
        if ( ! function_exists( 'get_posts' ) ) {
            return [];
        }
        $post_types = function_exists( 'get_post_types' ) ? array_values( get_post_types( [], 'names' ) ) : 'any';
        $post_stati = function_exists( 'get_post_stati' ) ? array_values( get_post_stati( [], 'names' ) ) : 'any';
        $args = [ 'numberposts' => $limit, 'post_type' => $post_types, 'post_status' => $post_stati, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => false ];
        $comments = get_posts( array_merge( $args, [ 'comment_status' => 'open' ] ) );
        $pings = get_posts( array_merge( $args, [ 'ping_status' => 'open' ] ) );
        $ids = array_values( array_unique( array_map( 'intval', array_merge( (array) $comments, (array) $pings ) ) ) );
        return $limit > 0 ? array_slice( $ids, 0, $limit ) : $ids;
    }
}
