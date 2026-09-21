<?php

namespace Hexa\PluginCore\BrandProfiles;

use Hexa\PluginCore\BrandColors\BrandColorProvider;

/**
 * Normalized public identity for a branded front-end surface.
 */
final class BrandProfile {
    private function __construct(
        private string $host,
        private string $name,
        private string $logo_url,
        private string $primary_color,
        private string $accent_color,
        private string $support_email
    ) {}

    public static function from_array( array $values ): self {
        return new self(
            self::host( (string) ( $values["host"] ?? "" ) ),
            self::text( (string) ( $values["name"] ?? "" ) ),
            self::url( (string) ( $values["logo_url"] ?? "" ) ),
            BrandColorProvider::normalize_hex( (string) ( $values["primary_color"] ?? "" ), "#163a36" ),
            BrandColorProvider::normalize_hex( (string) ( $values["accent_color"] ?? "" ), "#e36b43" ),
            self::email( (string) ( $values["support_email"] ?? "" ) )
        );
    }

    /** @return array<string,string> */
    public function to_array(): array {
        return [
            "host" => $this->host,
            "name" => $this->name,
            "logo_url" => $this->logo_url,
            "primary_color" => $this->primary_color,
            "accent_color" => $this->accent_color,
            "support_email" => $this->support_email,
        ];
    }

    /** @return array<string,string> */
    public function css_variables(): array {
        return [
            "--hexa-brand-primary" => $this->primary_color,
            "--hexa-brand-accent" => $this->accent_color,
        ];
    }

    private static function host( string $value ): string {
        $value = strtolower( rtrim( trim( preg_replace( '/:\\d+$/', '', $value ) ?? '' ), '.' ) );
        return preg_match( '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z]{2,63}$/', $value ) ? $value : "";
    }

    private static function text( string $value ): string {
        $value = trim( strip_tags( $value ) );
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 255 ) : substr( $value, 0, 255 );
    }

    private static function url( string $value ): string {
        $value = trim( $value );
        if ( "" === $value ) {
            return "";
        }
        if ( function_exists( 'esc_url_raw' ) ) {
            return (string) esc_url_raw( $value, [ 'http', 'https' ] );
        }
        return false !== filter_var( $value, FILTER_VALIDATE_URL ) && in_array( strtolower( (string) parse_url( $value, PHP_URL_SCHEME ) ), [ 'http', 'https' ], true ) ? $value : "";
    }

    private static function email( string $value ): string {
        $value = function_exists( 'sanitize_email' ) ? (string) sanitize_email( $value ) : trim( $value );
        return false !== filter_var( $value, FILTER_VALIDATE_EMAIL ) ? strtolower( $value ) : "";
    }
}
