<?php

namespace Hexa\PluginCore\SnippetRegistry;

final class SnippetRegistry {
    /**
     * @var array<string,SnippetDefinition>
     */
    private array $definitions = [];

    public function add( SnippetDefinition|array $definition ): self {
        $definition = is_array( $definition ) ? SnippetDefinition::from_array( $definition ) : $definition;

        if ( "" !== $definition->id ) {
            $this->definitions[ $definition->id ] = $definition;
        }

        return $this;
    }

    public function add_many( array $definitions ): self {
        foreach ( $definitions as $definition ) {
            if ( $definition instanceof SnippetDefinition || is_array( $definition ) ) {
                $this->add( $definition );
            }
        }

        return $this;
    }

    public function get( string $id ): ?SnippetDefinition {
        $id = $this->clean_key( $id );

        return $this->definitions[ $id ] ?? null;
    }

    public function has( string $id ): bool {
        return $this->get( $id ) instanceof SnippetDefinition;
    }

    /**
     * @return array<string,SnippetDefinition>
     */
    public function all(): array {
        return $this->definitions;
    }

    /**
     * @return array<string,array<int,SnippetDefinition>>
     */
    public function grouped(): array {
        $groups = [];
        foreach ( $this->definitions as $definition ) {
            $category = "" !== $definition->category ? $definition->category : "general";
            $groups[ $category ][] = $definition;
        }

        return $groups;
    }

    public function is_enabled( SnippetDefinition|string $definition ): bool {
        $definition = is_string( $definition ) ? $this->get( $definition ) : $definition;
        if ( ! $definition instanceof SnippetDefinition ) {
            return false;
        }

        if ( "" === $definition->option_key || ! function_exists( "get_option" ) ) {
            return $definition->default_enabled;
        }

        return (bool) get_option( $definition->option_key, $definition->default_enabled );
    }

    public function set_enabled( string $id, bool $enabled ): bool {
        $definition = $this->get( $id );
        if ( ! $definition instanceof SnippetDefinition || "" === $definition->option_key || ! function_exists( "update_option" ) ) {
            return false;
        }

        update_option( $definition->option_key, $enabled );

        return true;
    }

    public function summary(): array {
        $enabled = 0;
        foreach ( $this->definitions as $definition ) {
            if ( $this->is_enabled( $definition ) ) {
                $enabled++;
            }
        }

        return [
            "total"    => count( $this->definitions ),
            "enabled"  => $enabled,
            "disabled" => max( 0, count( $this->definitions ) - $enabled ),
        ];
    }

    public function test( string $id ): array {
        $definition = $this->get( $id );
        if ( ! $definition instanceof SnippetDefinition ) {
            return [
                "snippet_id" => $id,
                "status"     => "fail",
                "message"    => "Snippet is not registered.",
                "rules"      => [],
            ];
        }

        $rules = $this->rules_for( $definition );
        $results = [];
        $required_failed = false;
        $optional_failed = false;

        foreach ( $rules as $rule ) {
            $result = $this->evaluate_rule( $definition, $rule );
            $results[] = $result;

            if ( ! $result["passed"] && ! empty( $result["required"] ) ) {
                $required_failed = true;
            } elseif ( ! $result["passed"] ) {
                $optional_failed = true;
            }
        }

        if ( is_callable( $definition->test_callback ) ) {
            $callback_result = call_user_func( $definition->test_callback, $definition, $this );
            if ( is_array( $callback_result ) ) {
                $callback_result = array_merge(
                    [
                        "id"          => "custom_callback",
                        "label"       => "Custom callback",
                        "description" => "",
                        "required"    => true,
                        "passed"      => ! empty( $callback_result["passed"] ),
                        "message"     => "",
                    ],
                    $callback_result
                );
                $results[] = $callback_result;
                if ( ! $callback_result["passed"] && ! empty( $callback_result["required"] ) ) {
                    $required_failed = true;
                }
            }
        }

        $status = $required_failed ? "fail" : ( $optional_failed ? "warn" : "pass" );

        return [
            "snippet_id" => $definition->id,
            "status"     => $status,
            "message"    => match ( $status ) {
                "pass" => "All required snippet checks passed.",
                "warn" => "Required checks passed with optional warnings.",
                default => "One or more required snippet checks failed.",
            },
            "rules"      => $results,
        ];
    }

    private function rules_for( SnippetDefinition $definition ): array {
        if ( [] !== $definition->test_rules ) {
            return $definition->test_rules;
        }

        $rules = [
            [
                "id"          => "option_enabled",
                "label"       => "Snippet option is enabled",
                "description" => "Confirms the WordPress option that controls this snippet is active.",
                "type"        => "option_enabled",
                "required"    => true,
            ],
        ];

        if ( "" !== $definition->function ) {
            $rules[] = [
                "id"          => "function_exists",
                "label"       => "Activation function exists",
                "description" => "Confirms the host plugin function configured for this snippet is loaded.",
                "type"        => "function_exists",
                "function"    => $definition->function,
                "required"    => true,
            ];
        }

        foreach ( $definition->shortcodes as $shortcode ) {
            $tag = isset( $shortcode["tag"] ) && is_scalar( $shortcode["tag"] )
                ? (string) $shortcode["tag"]
                : ( isset( $shortcode["value"] ) && is_scalar( $shortcode["value"] ) ? trim( (string) $shortcode["value"], "[]" ) : "" );

            if ( "" !== $tag ) {
                $rules[] = [
                    "id"          => "shortcode_" . $this->clean_key( $tag ),
                    "label"       => "Shortcode [" . $tag . "] exists",
                    "description" => "Confirms WordPress currently has this shortcode registered.",
                    "type"        => "shortcode_exists",
                    "tag"         => $tag,
                    "required"    => false,
                ];
            }
        }

        return $rules;
    }

    private function evaluate_rule( SnippetDefinition $definition, array $rule ): array {
        $type     = isset( $rule["type"] ) && is_scalar( $rule["type"] ) ? $this->clean_key( (string) $rule["type"] ) : "truthy";
        $required = array_key_exists( "required", $rule ) ? (bool) $rule["required"] : true;
        $passed   = false;

        switch ( $type ) {
            case "option_enabled":
                $passed = $this->is_enabled( $definition );
                break;

            case "function_exists":
                $function = isset( $rule["function"] ) && is_scalar( $rule["function"] ) ? (string) $rule["function"] : $definition->function;
                $passed = "" !== $function && function_exists( $function );
                break;

            case "callable":
            case "callback":
                $callback = $rule["callback"] ?? null;
                $passed = is_callable( $callback ) ? (bool) call_user_func( $callback, $definition, $rule, $this ) : false;
                break;

            case "shortcode_exists":
                $tag = isset( $rule["tag"] ) && is_scalar( $rule["tag"] ) ? (string) $rule["tag"] : "";
                $passed = "" !== $tag && function_exists( "shortcode_exists" ) && shortcode_exists( $tag );
                break;

            default:
                $passed = ! empty( $rule["passed"] );
                break;
        }

        return [
            "id"          => isset( $rule["id"] ) && is_scalar( $rule["id"] ) ? $this->clean_key( (string) $rule["id"] ) : $type,
            "label"       => isset( $rule["label"] ) && is_scalar( $rule["label"] ) ? (string) $rule["label"] : ucwords( str_replace( "_", " ", $type ) ),
            "description" => isset( $rule["description"] ) && is_scalar( $rule["description"] ) ? (string) $rule["description"] : "",
            "required"    => $required,
            "passed"      => $passed,
            "message"     => $passed
                ? (string) ( $rule["pass_message"] ?? "Passed." )
                : (string) ( $rule["fail_message"] ?? "Failed." ),
        ];
    }

    private function clean_key( string $value ): string {
        if ( function_exists( "sanitize_key" ) ) {
            return sanitize_key( $value );
        }

        return preg_replace( "/[^a-z0-9_\-]/", "", strtolower( $value ) ) ?: "";
    }
}
