<?php

declare(strict_types=1);

function esc_attr( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES );
}

$root = dirname( __DIR__ );
require $root . "/src/BrandColors/BrandColorProvider.php";
require $root . "/src/WpAdminComponents/CoreUi.php";
require $root . "/src/WpAdminComponents/ColorControl.php";

use Hexa\PluginCore\WpAdminComponents\ColorControl;

$inherited = ColorControl::render(
    [
        "key"               => "context_color",
        "label"             => "Context color",
        "value"             => "",
        "default"           => "#123456",
        "allow_inherit"     => true,
        "inherited_value"   => "#123456",
        "value_input_class" => "host-setting",
        "import_brand"      => true,
    ]
);

$custom = ColorControl::render(
    [
        "key"               => "custom_context_color",
        "label"             => "Custom context color",
        "value"             => "#abcdef",
        "default"           => "#123456",
        "allow_inherit"     => true,
        "inherited_value"   => "#123456",
        "value_input_class" => "host-setting",
    ]
);

$checks = [
    "Inherited state retains an empty persisted value." => str_contains( $inherited, 'data-hpc-color-inherited="true"' )
        && preg_match( '/class="hpc-color-value-input host-setting"[^>]*data-hpc-color-value-input[^>]*value=""/s', $inherited ),
    "Inherited state exposes the effective color in picker and editable hex controls." => str_contains( $inherited, 'data-hpc-color-effective="#123456"' )
        && str_contains( $inherited, 'data-hpc-color-picker' )
        && preg_match( '/data-hpc-color-hex-input[^>]*value="#123456"/s', $inherited ),
    "Inherited mode renders clear state and action controls." => str_contains( $inherited, 'data-hpc-color-inherit-state' )
        && str_contains( $inherited, '>Inherited</span>' )
        && str_contains( $inherited, 'data-hpc-color-inherit' ),
    "Brand import remains available through the shared hook." => str_contains( $inherited, 'data-hpc-brand-color-import' )
        && str_contains( $inherited, 'data-smpi-import-brand-color' ),
    "Shared JavaScript persists explicit edits and clears inherited overrides." => str_contains( $inherited, 'data-hpc-color-value-input' )
        && str_contains( $inherited, 'valueInput.value=""' )
        && str_contains( $inherited, 'dispatchChange(valueInput)' ),
    "Custom state retains and displays its explicit value." => str_contains( $custom, 'data-hpc-color-inherited="false"' )
        && str_contains( $custom, 'data-hpc-color-effective="#abcdef"' )
        && preg_match( '/data-hpc-color-value-input[^>]*value="#abcdef"/s', $custom )
        && str_contains( $custom, '>Custom</span>' ),
];

foreach ( $checks as $message => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: " . $message . "\n" );
        exit( 1 );
    }
}

echo "PASS: ColorControl supports shared editable inherited and custom color states.\n";
