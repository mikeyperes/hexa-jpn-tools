<?php

declare(strict_types=1);

// ImageZoom: markup contract for the delayed hover preview, hint, countdown assets and detail side panel.

set_error_handler( static function ( int $severity, string $message, string $file, int $line ): bool {
    throw new ErrorException( $message, 0, $severity, $file, $line );
} );

function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function esc_url( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function __( string $text ): string {
    return $text;
}
function get_post_meta( int $id, string $key, bool $single ): string {
    return 'Poster';
}
function wp_get_attachment_image_src( int $id, string $size ): array|false {
    return $id > 0 ? [ 'https://example.test/full-' . $size . '.jpg', 1200, 1600 ] : false;
}
function wp_get_attachment_image( int $id, string $size, bool $icon, array $attr ): string {
    return '<img class="' . esc_attr( $attr['class'] ?? '' ) . '" alt="' . esc_attr( $attr['alt'] ?? '' ) . '" src="https://example.test/' . $size . '.jpg">';
}

require dirname( __DIR__ ) . '/src/PublicComponents/ImageZoom.php';

use Hexa\PluginCore\PublicComponents\ImageZoom;

$assertions = 0;
$expect     = static function ( bool $condition, string $message ) use ( &$assertions ): void {
    $assertions++;
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

// Default: no delay, no hint, no detail; the previous behavior and markup are unchanged.
$plain = ImageZoom::html( 7 );
// The first call also prints the one-time script, which mentions the attributes; check the thumbnail markup only.
$plain_thumb = (string) strstr( $plain, '</a>', true );
$expect( str_contains( $plain_thumb, 'data-hiz' ) && ! str_contains( $plain_thumb, 'data-hiz-delay' ), 'Without a delay the thumbnail has no delay attribute.' );
$expect( ! str_contains( $plain_thumb, 'data-hiz-hint' ) && ! str_contains( $plain, '<template' ), 'Without options there is no hint and no detail panel.' );
$expect( str_contains( $plain, 'id="hexa-image-zoom-js"' ), 'The first thumbnail prints the style and script.' );
$expect( ! str_contains( ImageZoom::html( 7 ), 'hexa-image-zoom-js' ), 'The style and script print once per page.' );
$expect( '' === ImageZoom::html( 0 ), 'A missing attachment prints nothing.' );

// A delay turns on the countdown and the default explainer; the delay is clamped.
$timed = ImageZoom::html( 7, [ 'delay' => 1500 ] );
$expect( str_contains( $timed, 'data-hiz-delay="1500"' ), 'The delay is written on the thumbnail in milliseconds.' );
$expect( str_contains( $timed, 'data-hiz-hint="You&#039;re seeing this preview because you hovered over it.' ), 'A delayed preview carries the default explainer.' );
$expect( str_contains( ImageZoom::html( 7, [ 'delay' => 99999 ] ), 'data-hiz-delay="5000"' ), 'The delay is capped at five seconds.' );
$expect( ! str_contains( ImageZoom::html( 7, [ 'delay' => -5 ] ), 'data-hiz-delay' ), 'A negative delay means no delay.' );
$expect( str_contains( ImageZoom::html( 7, [ 'delay' => 1500, 'hint' => 'Custom "words"' ] ), 'data-hiz-hint="Custom &quot;words&quot;"' ), 'A custom hint replaces the default and is escaped.' );
$expect( ! str_contains( ImageZoom::html( 7, [ 'delay' => 1500, 'hint' => '' ] ), 'data-hiz-hint' ), 'An empty hint hides the explainer.' );

// The detail panel is escaped, skips empty parts and sits right after the thumbnail link.
$detail = [
    'title' => 'Hip <b>Hop</b> Night',
    'meta'  => [ 'When' => 'Wed, Oct 21', 'Where' => '', 'Host' => 'A & B' ],
    'text'  => 'Dance <script>alert(1)</script> all night.',
    'links' => [
        [ 'label' => 'RSVP', 'url' => 'https://www.example.org/hip-hop/', 'primary' => true ],
        [ 'label' => 'Event page', 'url' => 'https://example.test/e/1' ],
        [ 'label' => '', 'url' => 'https://skipped.test' ],
        [ 'label' => 'No url', 'url' => '' ],
    ],
];
$html = ImageZoom::html( 7, [ 'detail' => $detail ] );
$expect( 1 === preg_match( '#</a><template class="hiz-detail"><div class="hiz-d">.*</div></template>#s', $html ), 'The detail panel is a template right after the thumbnail link.' );
$expect( ! str_contains( $html, '<b>Hop</b>' ) && ! str_contains( $html, '<script>alert' ), 'Title and text are escaped.' );
$expect( str_contains( $html, '<dt>When</dt><dd>Wed, Oct 21</dd>' ) && str_contains( $html, 'A &amp; B' ), 'Facts print label and value, escaped.' );
$expect( ! str_contains( $html, '<dt>Where</dt>' ), 'A fact with no value is skipped.' );
$expect( 1 === preg_match( '#<li class="is-primary"><a href="[^"]*example\.org/hip-hop/?"[^>]*><b>RSVP</b><span>example\.org/hip-hop</span></a></li>#', $html ), 'The primary link is a real link showing its label and a tidy address.' );
$expect( str_contains( $html, '<b>Event page</b><span>example.test/e/1</span>' ) && ! str_contains( $html, 'skipped.test' ) && ! str_contains( $html, 'No url' ), 'Links missing a label or an address are skipped.' );
$expect( '' === ImageZoom::detail( [] ) && '' === ImageZoom::detail( [ 'title' => '  ', 'meta' => [ 'A' => '' ] ] ), 'An empty detail renders nothing.' );

// Script and style carry the behavior.
$js  = ImageZoom::js();
$css = ImageZoom::css();
$expect( str_contains( $js, "data-hiz-delay" ) && str_contains( $js, 'startRing' ) && str_contains( $js, 'stopRing' ), 'The script runs the countdown ring.' );
$expect( str_contains( $js, 'function hidePop(){clearTimeout(popTimer);stopRing();' ), 'Leaving the thumbnail cancels the wait and the countdown.' );
$expect( str_contains( $js, "template.hiz-detail" ) && str_contains( $js, 'has-detail' ), 'The script fills the side panel from the template.' );
$expect( str_contains( $css, '.hiz-timer' ) && str_contains( $css, '.hiz-pop__note' ) && str_contains( $css, '--hiz-accent' ), 'The style covers the ring, the hint line and the accent colour.' );
$expect( str_contains( $css, 'pointer-events:none' ) && str_contains( $css, '.hiz-timer{animation:none}' ), 'The preview never captures the pointer and reduced motion removes the ring fade.' );

if ( ( $argv[1] ?? '' ) === '--harness' ) {
    // Browser harness for the interaction test: two flyers, one with detail.
    $page = '<!doctype html><meta charset=utf-8><body style="background:#0a150d;margin:0;padding:40px;--hiz-accent:#25D366">'
        . '<div style="width:260px;height:340px;margin-bottom:40px" id="a">' . ImageZoom::html( 7, [ 'delay' => 1500, 'fit' => 'contain', 'detail' => $detail ] ) . '</div>'
        . '<div style="width:260px;height:340px" id="b">' . ImageZoom::html( 8, [ 'fit' => 'contain' ] ) . '</div>';
    file_put_contents( $argv[2] ?? 'php://stdout', $page );
    exit( 0 );
}

echo "PASS: image zoom delay, hint and detail panel ({$assertions} assertions).\n";
