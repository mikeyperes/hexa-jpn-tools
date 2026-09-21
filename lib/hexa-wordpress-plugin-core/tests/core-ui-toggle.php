<?php

declare(strict_types=1);

function sanitize_key( mixed $value ): string {
    return preg_replace( "/[^a-z0-9_\-]/", "", strtolower( (string) $value ) ) ?: "";
}

function sanitize_html_class( mixed $value ): string {
    return preg_replace( "/[^a-zA-Z0-9_\-]/", "", (string) $value ) ?: "";
}

function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

$root = dirname( __DIR__ );
require $root . "/src/WpAdminComponents/CoreUi.php";

use Hexa\PluginCore\WpAdminComponents\CoreUi;

$html = CoreUi::toggle(
    "hidden_post_types[]",
    true,
    "Team Members",
    [
        "id"          => "hide-team-members",
        "value"       => "team-member",
        "class"       => "host-toggle additional-toggle",
        "input_class" => "host-setting-array tracked-input",
        "data"        => [
            "key"      => "hidden_post_types",
            "setting"  => "breadcrumbs",
            "ignored"  => [ "not-scalar" ],
        ],
    ]
);

$checks = [
    "Preserves sanitized wrapper classes." => str_contains( $html, 'class="hpc-toggle host-toggle additional-toggle"' ),
    "Preserves sanitized host input classes." => str_contains( $html, 'class="host-setting-array tracked-input"' ),
    "Renders host data attributes on the input." => str_contains( $html, 'data-key="hidden_post_types"' )
        && str_contains( $html, 'data-setting="breadcrumbs"' )
        && ! str_contains( $html, "data-ignored" ),
    "Retains the supplied value and checked state." => str_contains( $html, 'value="team-member"' )
        && str_contains( $html, " checked" ),
    "Keeps the shared Core toggle UI and label." => str_contains( $html, 'class="hpc-toggle-ui"' )
        && str_contains( $html, 'class="hpc-toggle-label">Team Members</span>' ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: " . $message . "\n" );
        exit( 1 );
    }
}

echo "PASS: Core toggles accept safe host save hooks without duplicated markup.\n";
