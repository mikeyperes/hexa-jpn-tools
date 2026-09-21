<?php

declare(strict_types=1);

function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function sanitize_html_class( mixed $value ): string {
    return preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $value ) ?: '';
}

$root = dirname( __DIR__ );
require $root . '/src/WpAdminComponents/CoreUi.php';
require $root . '/src/WpAdminComponents/ScopedCssOverride.php';

use Hexa\PluginCore\WpAdminComponents\ScopedCssOverride;

$selector = 'body.page-id-123 .example-scope';
$html_example = "<div class=\"example-scope\">\n  <span>Example</span>\n</div>";
$css_example = "body.page-id-123 .example-scope {\n  color: #ffffff;\n}";
$html = ScopedCssOverride::render(
    [
        'title'        => 'Page CSS override',
        'selector'     => $selector,
        'instructions' => [
            'Keep every rule scoped.',
            'Use one page body class.',
        ],
        'html_example' => $html_example,
        'css_example'  => $css_example,
        'setting_key' => 'example_css_override',
        'value'       => $css_example,
        'input_class' => 'example-setting',
        'status_html' => '<span class="save-state"></span>',
    ]
);

$checks = [
    'Uses the shared nested Core collapsible.' => str_contains( $html, 'hpc-detail-card hpc-scoped-css-override' )
        && ! preg_match( '/<details[^>]*\sopen(?:\s|>)/', $html ),
    'Exposes the configured selector without changing it.' => str_contains( $html, 'data-hpc-scope-selector="' . esc_attr( $selector ) . '"' )
        && str_contains( $html, '<code>' . esc_html( $selector ) . '</code>' ),
    'Renders concise ordered instructions.' => str_contains( $html, '<li>Keep every rule scoped.</li>' )
        && str_contains( $html, '<li>Use one page body class.</li>' ),
    'Renders escaped, pretty HTML and CSS code blocks.' => str_contains( $html, esc_html( $html_example ) )
        && str_contains( $html, esc_html( $css_example ) )
        && str_contains( $html, '<strong>HTML structure</strong>' )
        && str_contains( $html, '<strong>CSS override</strong>' ),
    'Renders an editable saved CSS field when a setting key is supplied.' => str_contains( $html, 'data-hpc-scoped-css-input' )
        && str_contains( $html, 'data-key="example_css_override"' )
        && str_contains( $html, 'example-setting' )
        && str_contains( $html, esc_html( $css_example ) ),
    'Provides copy actions for selector, HTML, and CSS.' => 3 === substr_count( $html, 'data-hpc-copy=' ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, 'FAIL: ' . $message . "\n" );
        exit( 1 );
    }
}

$source = (string) file_get_contents( $root . '/src/WpAdminComponents/ScopedCssOverride.php' );
foreach ( [ 'smpi-', 'smp_', 'hws_', 'blockeditorial' ] as $host_term ) {
    if ( str_contains( strtolower( $source ), $host_term ) ) {
        fwrite( STDERR, 'FAIL: Shared renderer contains host-specific term ' . $host_term . ".\n" );
        exit( 1 );
    }
}

echo "PASS: Scoped CSS override panel is host-neutral, formatted, copyable, and closed by default.\n";
