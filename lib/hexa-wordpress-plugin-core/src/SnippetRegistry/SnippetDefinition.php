<?php

namespace Hexa\PluginCore\SnippetRegistry;

final class SnippetDefinition {
    public string $id;
    public string $name;
    public string $description;
    public string $category;
    public string $option_key;
    public string $function;
    public string $scope;
    public string $info;
    public string $readme;
    public bool $default_enabled;
    public bool $deprecated;
    public bool $scope_admin_only;
    public array $snippets;
    public array $shortcodes;
    public array $test_rules;
    public mixed $test_callback;

    public function __construct( array $config ) {
        $this->id               = self::clean_key( (string) ( $config["id"] ?? $config["key"] ?? "" ) );
        $this->name             = (string) ( $config["name"] ?? $config["label"] ?? $this->id );
        $this->description      = (string) ( $config["description"] ?? "" );
        $this->category         = self::clean_key( (string) ( $config["category"] ?? $config["type"] ?? "general" ) );
        $this->option_key       = (string) ( $config["option_key"] ?? $this->id );
        $this->function         = (string) ( $config["function"] ?? $config["callable"] ?? "" );
        $this->scope            = self::clean_key( (string) ( $config["scope"] ?? $this->category ) );
        $this->info             = self::string_or_callable( $config["info"] ?? "" );
        $this->readme           = self::readme_text( $config["readme"] ?? $config["readme_html"] ?? "" );
        $this->default_enabled  = (bool) ( $config["default_enabled"] ?? $config["default"] ?? false );
        $this->deprecated       = (bool) ( $config["deprecated"] ?? false );
        $this->scope_admin_only = (bool) ( $config["scope_admin_only"] ?? false );
        $this->snippets         = self::normalize_list( $config["snippets"] ?? [] );
        $this->shortcodes       = self::normalize_list( $config["shortcodes"] ?? [] );
        $this->test_rules       = self::normalize_list( $config["test_rules"] ?? $config["testing"] ?? [] );
        $this->test_callback    = $config["test_callback"] ?? null;

        if ( [] === $this->snippets && "" !== $this->function ) {
            $this->snippets[] = [
                "label"       => "Activation function",
                "value"       => $this->function,
                "description" => "Function called when this snippet option is enabled.",
            ];
        }
    }

    public static function from_array( array $config ): self {
        return new self( $config );
    }

    public function to_array(): array {
        return [
            "id"               => $this->id,
            "name"             => $this->name,
            "description"      => $this->description,
            "category"         => $this->category,
            "option_key"       => $this->option_key,
            "function"         => $this->function,
            "scope"            => $this->scope,
            "info"             => $this->info,
            "readme"           => $this->readme,
            "default_enabled"  => $this->default_enabled,
            "deprecated"       => $this->deprecated,
            "scope_admin_only" => $this->scope_admin_only,
            "snippets"         => $this->snippets,
            "shortcodes"       => $this->shortcodes,
            "test_rules"       => $this->test_rules,
        ];
    }

    private static function clean_key( string $value ): string {
        if ( function_exists( "sanitize_key" ) ) {
            return sanitize_key( $value );
        }

        return preg_replace( "/[^a-z0-9_\-]/", "", strtolower( $value ) ) ?: "";
    }

    private static function string_or_callable( mixed $value ): string {
        if ( is_callable( $value ) ) {
            $value = call_user_func( $value );
        }

        return is_scalar( $value ) ? (string) $value : "";
    }

    private static function readme_text( mixed $value ): string {
        if ( is_array( $value ) ) {
            $parts = [];
            foreach ( [ "summary", "body", "usage", "notes" ] as $key ) {
                if ( ! empty( $value[ $key ] ) && is_scalar( $value[ $key ] ) ) {
                    $parts[] = (string) $value[ $key ];
                }
            }

            return trim( implode( "\n\n", $parts ) );
        }

        return is_scalar( $value ) ? trim( (string) $value ) : "";
    }

    private static function normalize_list( mixed $items ): array {
        if ( is_string( $items ) && "" !== trim( $items ) ) {
            $items = [ $items ];
        }

        if ( ! is_array( $items ) ) {
            return [];
        }

        $normalized = [];
        foreach ( $items as $key => $item ) {
            if ( is_string( $item ) ) {
                $normalized[] = [
                    "id"          => is_string( $key ) ? self::clean_key( $key ) : self::clean_key( $item ),
                    "label"       => is_string( $key ) ? (string) $key : $item,
                    "value"       => $item,
                    "description" => "",
                ];
                continue;
            }

            if ( is_array( $item ) ) {
                $id = isset( $item["id"] ) && is_scalar( $item["id"] )
                    ? self::clean_key( (string) $item["id"] )
                    : ( is_string( $key ) ? self::clean_key( $key ) : "" );

                $normalized[] = array_merge(
                    [
                        "id"          => $id,
                        "label"       => isset( $item["label"] ) && is_scalar( $item["label"] ) ? (string) $item["label"] : ( "" !== $id ? $id : "Item" ),
                        "value"       => isset( $item["value"] ) && is_scalar( $item["value"] ) ? (string) $item["value"] : "",
                        "description" => isset( $item["description"] ) && is_scalar( $item["description"] ) ? (string) $item["description"] : "",
                    ],
                    $item
                );
            }
        }

        return $normalized;
    }
}
