<?php

declare(strict_types=1);

function sanitize_key( mixed $value ): string {
    return trim( strtolower( preg_replace( '/[^a-z0-9_-]+/i', '-', (string) $value ) ?? '' ), '-' );
}

function sanitize_html_class( mixed $value ): string {
    return preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $value ) ?: '';
}

function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function esc_url( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function home_url( string $path = '' ): string {
    return 'https://example.test' . $path;
}

$root = dirname( __DIR__ );
require $root . '/src/SearchDisplay/SearchDisplayRenderer.php';

use Hexa\PluginCore\SearchDisplay\SearchDisplayRenderer;

$styles = SearchDisplayRenderer::styles();
$outputs = [];
foreach ( SearchDisplayRenderer::STYLES as $style ) {
    $outputs[ $style ] = SearchDisplayRenderer::render(
        [
            'style'       => $style,
            'id'          => 'test-' . $style,
            'action_url'  => 'https://example.test/',
            'placeholder' => 'Find stories',
            'label'       => 'Search site',
        ]
    );
}

$invalid = SearchDisplayRenderer::render(
    [
        'style'  => 'not-a-template',
        'accent' => 'not-a-color',
        'radius' => 'calc(10px)',
    ]
);
$custom = SearchDisplayRenderer::render(
    [
        'style'         => 'command',
        'accent'        => '#2f6df6',
        'radius'        => '18',
        'hidden_fields' => [ 'hexa_search' => '1' ],
    ]
);

$checks = [
    'Publishes exactly the five locked search templates.' => array_keys( $styles ) === SearchDisplayRenderer::STYLES,
    'Every template submits a native WordPress search query.' => ! array_filter(
        $outputs,
        static fn( string $html ): bool => ! str_contains( $html, 'method="get"' ) || ! str_contains( $html, 'name="s"' )
    ),
    'Every template exposes its canonical style identifier.' => ! array_filter(
        $outputs,
        static fn( string $html, string $style ): bool => ! str_contains( $html, 'data-style="' . $style . '"' ),
        ARRAY_FILTER_USE_BOTH
    ),
    'Icon reveal ships delegated expand, focus, Escape, and click-away behavior.' => str_contains( $outputs['icon-reveal'], 'data-sd-toggle' )
        && str_contains( $outputs['icon-reveal'], 'tabindex="-1"' )
        && str_contains( $outputs['icon-reveal'], 'closeReveal(widget)' ),
    'Overlay ships an accessible unique dialog and keyboard trigger.' => str_contains( $outputs['overlay'], 'aria-controls="test-overlay-dialog"' )
        && str_contains( $outputs['overlay'], 'id="test-overlay-dialog"' )
        && str_contains( $outputs['overlay'], 'aria-modal="true"' )
        && str_contains( implode( '', $outputs ), "event.metaKey||event.ctrlKey" ),
    'Always-visible templates keep their expected structural forms.' => str_contains( $outputs['pill'], 'class="sd-pill"' )
        && str_contains( $outputs['underline'], 'class="sd-underline"' )
        && str_contains( $outputs['command'], 'class="sd-cmd"' )
        && str_contains( $outputs['command'], 'type="submit"'),
    'Shared assets are emitted once even when several previews render.' => 1 === substr_count( implode( '', $outputs ), 'id="hexa-search-display-css"' )
        && 1 === substr_count( implode( '', $outputs ), 'id="hexa-search-display-js"' ),
    'Unknown styles safely fall back to pill.' => str_contains( $invalid, 'hexa-search--pill' )
        && ! str_contains( $invalid, 'not-a-template' ),
    'Invalid CSS values are discarded.' => ! str_contains( $invalid, 'not-a-color' )
        && ! str_contains( $invalid, 'calc(10px)' ),
    'Valid accent and radius values become scoped CSS variables.' => str_contains( $custom, '--sd-accent:#2f6df6;--sd-radius:18px;' ),
    'Host-provided request markers are included in visible and overlay forms.' => 2 === substr_count( $custom, 'type="hidden" name="hexa_search" value="1"' ),
    'Descriptions document behavior and intended placement.' => ! array_filter(
        $styles,
        static fn( array $definition ): bool => '' === $definition['label'] || '' === $definition['behavior'] || '' === $definition['description'] || '' === $definition['best_for']
    ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, 'FAIL: ' . $message . "\n" );
        exit( 1 );
    }
}

echo "PASS: Search Display renders five reusable native WordPress search templates.\n";
