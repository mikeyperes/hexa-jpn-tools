<?php

declare(strict_types=1);

// Item link behavior (page / new tab / lightbox) and the lightbox endpoint contract.

set_error_handler( static function ( int $severity, string $message, string $file, int $line ): bool {
    throw new ErrorException( $message, 0, $severity, $file, $line );
} );

final class WP_Post {
    public string $post_password = '';
    public function __construct( public int $ID, public string $post_type, public string $post_status = 'publish', public string $post_title = '' ) {}
}

final class ItemLinkTestStore {
    /** @var array<int,WP_Post> */
    public static array $posts = [];
    /** @var array<string,int> */
    public static array $urls = [];
    public static int $lookups = 0;
    public static bool $logged_in = false;
}

function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function esc_url( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function wp_json_encode( mixed $value ): string {
    return (string) json_encode( $value );
}
function wp_strip_all_tags( string $value ): string {
    return strip_tags( $value );
}
function wp_trim_words( string $text, int $words ): string {
    return implode( ' ', array_slice( preg_split( '/\s+/', trim( $text ) ) ?: [], 0, $words ) );
}
function rest_url( string $path ): string {
    return 'https://example.test/wp-json/' . $path;
}
function get_post( int $id ): ?WP_Post {
    return ItemLinkTestStore::$posts[ $id ] ?? null;
}
function setup_postdata( mixed $post ): bool {
    return true;
}
function url_to_postid( string $url ): int {
    ItemLinkTestStore::$lookups++;
    return ItemLinkTestStore::$urls[ $url ] ?? 0;
}
function get_the_title( int $id ): string {
    return ItemLinkTestStore::$posts[ $id ]->post_title ?? '';
}
function get_permalink( int $id ): string {
    return 'https://example.test/event/' . $id . '/';
}
function has_post_thumbnail( int $id ): bool {
    return 1 === $id;
}
function get_the_post_thumbnail( int $id, string $size, array $attr ): string {
    return '<img class="' . $attr['class'] . '" src="https://example.test/' . $id . '-' . $size . '.jpg" alt="">';
}
function get_the_excerpt( int $id ): string {
    return 'A <b>short</b> description.';
}
function current_user_can( string $capability ): bool {
    return ItemLinkTestStore::$logged_in;
}
function wp_create_nonce( string $action ): string {
    return 'nonce-' . $action;
}

$root = dirname( __DIR__ );
foreach ( [ 'ProfileValues', 'ProfileStore', 'PublicComponent', 'ItemLink', 'ItemLightbox' ] as $class ) {
    require $root . '/src/PublicComponents/' . $class . '.php';
}

use Hexa\PluginCore\PublicComponents\ItemLightbox;
use Hexa\PluginCore\PublicComponents\ItemLink;

$assertions = 0;
$expect = static function ( bool $condition, string $message ) use ( &$assertions ): void {
    $assertions++;
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

ItemLinkTestStore::$posts = [
    1 => new WP_Post( 1, 'event', 'publish', 'Lunch &amp; Learn' ),
    2 => new WP_Post( 2, 'event', 'draft', 'Draft event' ),
    3 => new WP_Post( 3, 'page', 'publish', 'About' ),
    4 => new WP_Post( 4, 'event', 'publish', 'Members only' ),
];
ItemLinkTestStore::$posts[4]->post_password = 'secret';
ItemLinkTestStore::$urls = [ 'https://example.test/event/1/' => 1 ];

$profile = static fn( array $config, bool $public = true ): array => [
    'id'            => 'events',
    'public'        => $public,
    'labels'        => ItemLink::LABELS,
    'link_behavior' => ItemLink::normalize( $config, [ 'event' ] ),
];

// Modes and defaults.
$expect( 'page' === ItemLink::normalize( [] )['mode'], 'The default behavior follows the link.' );
$expect( 'new_tab' === ItemLink::normalize( [ 'link_target' => '_blank' ] )['mode'], 'The legacy link_target _blank still opens a new tab.' );
$expect( 'page' === ItemLink::normalize( [ 'link_target' => '_blank', 'link_behavior' => 'page' ] )['mode'], 'An explicit link_behavior wins over the legacy target.' );
$expect( 'page' === ItemLink::normalize( [ 'link_behavior' => 'popup' ] )['mode'], 'An unknown behavior falls back to the page.' );
$expect( 'page' === ItemLink::normalize( [ 'link_behavior' => 'lightbox' ] )['mode'], 'A lightbox with no post types to show falls back to the page.' );
$expect( [ 'event' ] === ItemLink::normalize( [ 'link_behavior' => 'lightbox' ], [ 'event' ] )['post_types'], 'The lightbox shows the component post types by default.' );
$expect( [ 'event', 'venue' ] === ItemLink::normalize( [ 'link_behavior' => 'lightbox', 'lightbox' => [ 'post_types' => [ 'event', 'Venue!' ] ] ] )['post_types'], 'Declared lightbox post types are sanitized keys.' );

// Link attributes per mode.
$expect( '' === ItemLink::attributes( $profile( [] ), 'calendar', 1 ), 'Page mode adds nothing to the link.' );
$expect( ' target="_blank" rel="noopener"' === ItemLink::attributes( $profile( [ 'link_behavior' => 'new_tab' ] ), 'calendar', 1 ), 'New-tab mode opens a new tab safely.' );
$lightbox = $profile( [ 'link_behavior' => 'lightbox' ] );
$expect( ' data-hlb="https://example.test/wp-json/hexa-plugin-core/v1/lightbox/calendar/events/1"' === ItemLink::attributes( $lightbox, 'calendar', 1 ), 'Lightbox mode points an eligible link at its item endpoint.' );
$expect( '' === ItemLink::attributes( $lightbox, 'calendar', 2 ), 'A draft keeps following its link.' );
$expect( '' === ItemLink::attributes( $lightbox, 'calendar', 3 ), 'Another post type keeps following its link.' );
$expect( '' === ItemLink::attributes( $lightbox, 'calendar', 4 ), 'A password-protected post keeps following its link.' );
$expect( '' === ItemLink::attributes( $lightbox, 'map', 0 ), 'A link without a post (a user, an outside URL) keeps following its link.' );
$expect( '' === ItemLink::attributes( $lightbox, 'calendar', 99 ), 'A missing post keeps following its link.' );

// Root attributes carry the dialog wording, and a nonce only for a private profile.
$root_attrs = ItemLink::root_attributes( $lightbox );
$expect( str_contains( $root_attrs, 'data-hlb-labels=' ) && str_contains( $root_attrs, '&quot;close&quot;:&quot;Close&quot;' ), 'The component root carries the dialog labels.' );
$expect( ! str_contains( $root_attrs, 'data-hlb-nonce' ), 'A public profile sends no nonce.' );
$expect( str_contains( ItemLink::root_attributes( $profile( [ 'link_behavior' => 'lightbox' ], false ) ), 'data-hlb-nonce="nonce-wp_rest"' ), 'A private profile sends a REST nonce.' );
$expect( '' === ItemLink::root_attributes( $profile( [] ) ), 'Page mode adds no root attributes.' );
$expect( str_contains( $root_attrs, '&quot;page&quot;:true' ), 'The dialog shows its full-page link by default.' );
$expect( str_contains( ItemLink::root_attributes( $profile( [ 'link_behavior' => 'lightbox', 'lightbox' => [ 'page_link' => false ] ] ) ), '&quot;page&quot;:false' ), 'A profile can hide the full-page link when its own markup links to the page.' );
$expect( str_contains( ItemLightbox::js(), 'l.page===false' ), 'The dialog script honors the full-page link option.' );

// URL lookup.
$expect( 1 === ItemLink::post_id( 'https://example.test/event/1/' ) && 0 === ItemLink::post_id( '' ), 'A same-site URL resolves to its post; an empty URL does not look anything up.' );

// Dialog payload: default preview and host render.
$item = ItemLightbox::item( $lightbox['link_behavior'], 1 );
$expect( str_contains( $item['html'], 'class="hlb-image"' ) && str_contains( $item['html'], '<h2 class="hlb-title">Lunch &amp; Learn</h2>' ), 'The default dialog shows the image and the decoded, escaped title.' );
$expect( str_contains( $item['html'], 'A short description.' ) && ! str_contains( $item['html'], '<b>' ), 'The default excerpt is plain text.' );
$expect( 'Lunch & Learn' === $item['title'] && 'https://example.test/event/1/' === $item['url'], 'The payload names the post and its page.' );
$hosted = ItemLink::normalize( [ 'link_behavior' => 'lightbox', 'lightbox' => [ 'render' => static function ( int $id ): string {
    global $post;
    return '<div class="host-card" data-global="' . ( $post instanceof WP_Post ? $post->ID : 0 ) . '">' . $id . '</div>';
} ] ], [ 'event' ] );
$GLOBALS['post'] = ItemLinkTestStore::$posts[3];
$expect( '<div class="host-card" data-global="1">1</div>' === ItemLightbox::item( $hosted, 1 )['html'], 'The host render receives the post ID while the post is the global post.' );
$expect( 3 === $GLOBALS['post']->ID, 'The previous global post is restored after rendering.' );

// Endpoint: permission follows the profile's visibility; only lightbox profiles and eligible posts answer.
final class WP_Error {
    public function __construct( public string $code = '', public string $message = '', public array $data = [] ) {}
}
function rest_ensure_response( mixed $value ): object {
    return new class( $value ) {
        public array $headers = [];
        public function __construct( public mixed $data ) {}
        public function header( string $name, string $value ): void {
            $this->headers[ $name ] = $value;
        }
    };
}
function is_user_logged_in(): bool {
    return ItemLinkTestStore::$logged_in;
}
function do_action( string $hook, mixed ...$args ): void {}
$profiles = [
    'events'  => $lightbox,
    'members' => $profile( [ 'link_behavior' => 'lightbox' ], false ),
    'plain'   => $profile( [] ),
];
ItemLightbox::component( 'calendar', static fn( string $id ): ?array => $profiles[ $id ] ?? null );
$request = static fn( string $component, string $id, int $post ): array => [ 'component' => $component, 'profile' => $id, 'id' => (string) $post ];
$expect( true === ItemLightbox::rest_permission( $request( 'calendar', 'events', 1 ) ), 'A public lightbox profile is readable by anyone.' );
$expect( false === ItemLightbox::rest_permission( $request( 'calendar', 'members', 1 ) ), 'A private profile needs a logged-in reader.' );
$expect( false === ItemLightbox::rest_permission( $request( 'map', 'events', 1 ) ) && false === ItemLightbox::rest_permission( $request( 'calendar', 'missing', 1 ) ), 'An unknown component or profile is refused.' );
$ok = ItemLightbox::rest_item( $request( 'calendar', 'events', 1 ) );
$expect( ! $ok instanceof WP_Error && 'Lunch & Learn' === $ok->data['title'] && 'public, max-age=60' === ( $ok->headers['Cache-Control'] ?? '' ), 'An eligible post returns its dialog payload, publicly cacheable.' );
$expect( ItemLightbox::rest_item( $request( 'calendar', 'events', 3 ) ) instanceof WP_Error, 'A post of another type is not served.' );
$expect( ItemLightbox::rest_item( $request( 'calendar', 'events', 2 ) ) instanceof WP_Error, 'A draft is not served.' );
$expect( ItemLightbox::rest_item( $request( 'calendar', 'plain', 1 ) ) instanceof WP_Error, 'A profile that does not use the lightbox serves nothing.' );

// Assets print once and only in lightbox mode; the host asset callback runs on every render.
$asset_calls = 0;
$with_assets = ItemLink::normalize( [ 'link_behavior' => 'lightbox', 'lightbox' => [ 'assets' => static function () use ( &$asset_calls ): void {
    $asset_calls++;
} ] ], [ 'event' ] );
$expect( '' === ItemLightbox::assets( ItemLink::normalize( [] ) ), 'Page mode prints no dialog assets.' );
$first = ItemLightbox::assets( $with_assets );
$expect( str_contains( $first, 'id="hexa-lightbox-css"' ) && str_contains( $first, 'id="hexa-lightbox-js"' ), 'Lightbox mode prints the dialog style and script.' );
$expect( '' === ItemLightbox::assets( $with_assets ) && 2 === $asset_calls, 'The dialog assets print once per page; host assets are requested each time.' );

// The script only follows same-origin lightbox endpoints and leaves modified clicks alone.
$js = ItemLightbox::js();
$expect( str_contains( $js, "x.origin===location.origin" ) && str_contains( $js, 'hexa-plugin-core/v1/lightbox/' ), 'The script only fetches same-origin lightbox endpoints.' );
$expect( str_contains( $js, 'e.metaKey||e.ctrlKey||e.shiftKey||e.altKey' ), 'Modified clicks open the real link.' );
$expect( str_contains( $js, 'showModal' ) && str_contains( $js, 'popstate' ), 'The dialog is modal and the Back button closes it.' );
$expect( str_contains( $js, 'function shut(){pushed=false;dlg.close();finish();}' ), 'Closing unlocks the page and restores focus at once, so a quick reopen is never undone.' );
$expect( str_contains( ItemLightbox::css(), '@media(max-width:600px)' ), 'Phones get a bottom-sheet dialog.' );

echo "PASS: item link contract ({$assertions} assertions).\n";
