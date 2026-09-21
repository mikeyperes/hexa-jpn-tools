<?php

namespace Hexa\PluginCore\WpAdminUiCleanup;

final class CleanupOptionDefinition {
    public string $key;
    public string $label;
    public string $description;
    public string $section;
    public bool $default;
    public string $mode;
    public array $admin_pages;
    public array $selectors;
    public array $js_headers;
    public array $js_input_ids;
    public mixed $callback;
    public string $on_label;
    public string $off_label;
    public array $footer_patterns;
    public bool $hide_update_footer;

    public function __construct( array $config ) {
        $this->key                = self::clean_key( (string) ( $config["key"] ?? "" ) );
        $this->label              = (string) ( $config["label"] ?? $this->key );
        $this->description        = (string) ( $config["description"] ?? "" );
        $this->section            = self::clean_key( (string) ( $config["section"] ?? "general" ) );
        $this->default            = (bool) ( $config["default"] ?? false );
        $this->mode               = self::clean_key( (string) ( $config["mode"] ?? "css_hide" ) );
        $this->admin_pages        = self::string_list( $config["admin_pages"] ?? [] );
        $this->selectors          = self::string_list( $config["selectors"] ?? [] );
        $this->js_headers         = self::string_list( $config["js_headers"] ?? [] );
        $this->js_input_ids       = self::string_list( $config["js_input_ids"] ?? [] );
        $this->callback           = $config["callback"] ?? null;
        $this->on_label           = (string) ( $config["on_label"] ?? self::default_on_label( $this->mode ) );
        $this->off_label          = (string) ( $config["off_label"] ?? self::default_off_label( $this->mode ) );
        $this->footer_patterns    = self::string_list( $config["footer_patterns"] ?? [] );
        $this->hide_update_footer = (bool) ( $config["hide_update_footer"] ?? false );
    }

    public static function from_array( array $config ): self {
        return new self( $config );
    }

    public function applies_to_admin_page( string $pagenow ): bool {
        if ( [] === $this->admin_pages ) {
            return true;
        }

        return in_array( $pagenow, $this->admin_pages, true );
    }

    private static function clean_key( string $value ): string {
        if ( function_exists( "sanitize_key" ) ) {
            return sanitize_key( $value );
        }

        return preg_replace( "/[^a-z0-9_\-]/", "", strtolower( $value ) ) ?: "";
    }

    private static function string_list( mixed $value ): array {
        if ( is_string( $value ) && "" !== trim( $value ) ) {
            $value = [ $value ];
        }

        if ( ! is_array( $value ) ) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    static fn( mixed $item ): string => is_scalar( $item ) ? trim( (string) $item ) : "",
                    $value
                ),
                static fn( string $item ): bool => "" !== $item
            )
        );
    }

    private static function default_on_label( string $mode ): string {
        return "postbox_collapse" === $mode ? "Collapsed" : "Hidden";
    }

    private static function default_off_label( string $mode ): string {
        return "postbox_collapse" === $mode ? "Expanded" : "Visible";
    }
}
