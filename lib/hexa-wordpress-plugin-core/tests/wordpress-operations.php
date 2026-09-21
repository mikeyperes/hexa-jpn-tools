<?php

declare( strict_types=1 );

$GLOBALS['wo_options'] = [ 'default_comment_status' => 'open', 'default_ping_status' => 'open', 'permalink_structure' => '/%postname%/', 'rewrite_rules' => [ '^old$' => 'index.php' ] ];
$GLOBALS['wo_site_options'] = [];
$GLOBALS['wo_posts'] = [ 1 => [ 'comment_status' => 'open', 'ping_status' => 'open' ], 2 => [ 'comment_status' => 'closed', 'ping_status' => 'open' ] ];
$GLOBALS['wo_comments'] = [ 10, 11 ];
$GLOBALS['wo_undeletable_posts'] = [];
$GLOBALS['wo_undeletable_comments'] = [];
$GLOBALS['wo_plugins'] = [ 'sample/sample.php' => [], 'z-last/z-last.php' => [], 'a-first/a-first.php' => [] ];
$GLOBALS['wo_themes'] = [ 'z-theme' => new stdClass(), 'a-theme' => new stdClass() ];
function get_option( string $key, mixed $default = null ): mixed { return $GLOBALS['wo_options'][ $key ] ?? $default; }
function update_option( string $key, mixed $value, bool $autoload = false ): bool { $GLOBALS['wo_options'][ $key ] = $value; return true; }
function get_site_option( string $key, mixed $default = null ): mixed { return $GLOBALS['wo_site_options'][ $key ] ?? $default; }
function update_site_option( string $key, mixed $value ): bool { $GLOBALS['wo_site_options'][ $key ] = $value; return true; }
function get_posts( array $args ): array { $ids = []; foreach ( $GLOBALS['wo_posts'] as $id => $post ) { if ( ( isset( $args['comment_status'] ) && $post['comment_status'] === $args['comment_status'] ) || ( isset( $args['ping_status'] ) && $post['ping_status'] === $args['ping_status'] ) ) $ids[] = $id; } return array_slice( $ids, 0, max( 0, (int) $args['numberposts'] ) ); }
function wp_update_post( array $post, bool $wp_error = false ): int { $id = (int) $post['ID']; if ( in_array( $id, $GLOBALS['wo_undeletable_posts'], true ) ) return 0; $GLOBALS['wo_posts'][ $id ]['comment_status'] = $post['comment_status']; $GLOBALS['wo_posts'][ $id ]['ping_status'] = $post['ping_status']; return $id; }
function get_comments( array $args ): mixed { if ( ! empty( $args['count'] ) ) return count( $GLOBALS['wo_comments'] ); return array_slice( $GLOBALS['wo_comments'], 0, (int) ( $args['number'] ?? 100 ) ); }
function wp_delete_comment( int $id, bool $force = false ): bool { if ( in_array( $id, $GLOBALS['wo_undeletable_comments'], true ) ) return false; $key = array_search( $id, $GLOBALS['wo_comments'], true ); if ( false === $key ) return false; unset( $GLOBALS['wo_comments'][ $key ] ); $GLOBALS['wo_comments'] = array_values( $GLOBALS['wo_comments'] ); return $force; }
function get_plugins(): array { return $GLOBALS['wo_plugins']; }
function wp_get_themes(): array { return $GLOBALS['wo_themes']; }
function flush_rewrite_rules( bool $hard = true ): void { $GLOBALS['wo_options']['rewrite_rules'] = [ '^sample/?$' => 'index.php?name=sample' ]; }

$root = dirname( __DIR__ );
foreach ( [ 'DiscussionOperations', 'PermalinkOperations', 'AutoUpdatePolicy', 'UpdateOperations' ] as $class ) require $root . '/src/WordPressOperations/' . $class . '.php';

use Hexa\PluginCore\WordPressOperations\AutoUpdatePolicy;
use Hexa\PluginCore\WordPressOperations\DiscussionOperations;
use Hexa\PluginCore\WordPressOperations\PermalinkOperations;
use Hexa\PluginCore\WordPressOperations\UpdateOperations;

$discussion = new DiscussionOperations();
$closed = $discussion->close_comments_and_pings();
$deleted = $discussion->delete_comments();
$permalinks = ( new PermalinkOperations() )->repair();
$policy = ( new AutoUpdatePolicy() )->apply( [ 'core' => 'all', 'plugins' => [ 'sample/sample.php' ], 'themes' => false ] );
$callback_shape = [ 'success' => true, 'action' => 'status', 'available' => true, 'before' => [], 'after' => [], 'counts' => [], 'items' => [], 'messages' => [] ];
$updates = new UpdateOperations( [ 'status' => static fn(): array => $callback_shape ] );
$source = (string) file_get_contents( $root . '/src/WordPressOperations/UpdateOperations.php' );
if ( ! $closed['success'] || 'closed' !== get_option( 'default_comment_status' ) || ! $deleted['success'] || 2 !== $deleted['counts']['deleted'] || ! $permalinks['success'] || ! $policy['success'] || ! $updates->status()['success'] || ! str_contains( $source, 'wp_version_check' ) || ! str_contains( $source, 'Automatic_Upgrader_Skin' ) ) {
    fwrite( STDERR, "FAIL: WordPress native operation services failed.\n" );
    exit( 1 );
}

$GLOBALS['wo_posts'] = [];
foreach ( range( 1, 205 ) as $id ) {
    $GLOBALS['wo_posts'][ $id ] = [ 'comment_status' => 'open', 'ping_status' => 'open' ];
}
$explicit_posts = $discussion->close_comments_and_pings( range( 1, 205 ) );
$GLOBALS['wo_comments'] = range( 1001, 1205 );
$explicit_comments = $discussion->delete_comments( range( 1001, 1205 ) );
if ( ! $explicit_posts['success'] || 205 !== $explicit_posts['counts']['attempted'] || 205 !== $explicit_posts['counts']['updated'] || 100 !== count( $explicit_posts['items'] ) || ! $explicit_posts['items_truncated'] || ! $explicit_comments['success'] || 205 !== $explicit_comments['counts']['attempted'] || 205 !== $explicit_comments['counts']['deleted'] || 100 !== count( $explicit_comments['items'] ) || ! $explicit_comments['items_truncated'] ) {
    fwrite( STDERR, "FAIL: DiscussionOperations did not complete explicit lists larger than one batch with bounded reports.\n" );
    exit( 1 );
}

$GLOBALS['wo_posts'] = [ 999 => [ 'comment_status' => 'open', 'ping_status' => 'open' ] ];
$GLOBALS['wo_undeletable_posts'] = [ 999 ];
$stalled_posts = $discussion->close_comments_and_pings();
$GLOBALS['wo_comments'] = range( 2001, 2150 );
$GLOBALS['wo_undeletable_comments'] = $GLOBALS['wo_comments'];
$stalled_comments = $discussion->delete_comments();
if ( $stalled_posts['success'] || ! $stalled_posts['stopped_no_progress'] || 1 !== $stalled_posts['counts']['attempted'] || [ 999 ] !== $stalled_posts['unprocessed_ids'] || $stalled_comments['success'] || ! $stalled_comments['stopped_no_progress'] || 100 !== $stalled_comments['counts']['attempted'] || 100 !== count( $stalled_comments['items'] ) || 100 !== count( $stalled_comments['unprocessed_ids'] ) || ! $stalled_comments['unprocessed_ids_truncated'] ) {
    fwrite( STDERR, "FAIL: DiscussionOperations did not stop or bound reports when records could not be changed.\n" );
    exit( 1 );
}

$all_policy = ( new AutoUpdatePolicy() )->apply( [ 'core' => 'minor', 'plugins' => true, 'themes' => true ] );
if ( ! $all_policy['success'] || [ 'a-first/a-first.php', 'sample/sample.php', 'z-last/z-last.php' ] !== $all_policy['items']['plugins'] || [ 'a-theme', 'z-theme' ] !== $all_policy['items']['themes'] ) {
    fwrite( STDERR, "FAIL: AutoUpdatePolicy did not compare canonical sorted plugin/theme lists.\n" );
    exit( 1 );
}
echo "PASS: WordPressOperations safely handles update discovery, policy, discussions, comments, and permalinks.\n";
