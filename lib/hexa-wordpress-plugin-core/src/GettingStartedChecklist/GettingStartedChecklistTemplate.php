<?php

namespace Hexa\PluginCore\GettingStartedChecklist;

final class GettingStartedChecklistTemplate {
    public string $id;
    public string $label;
    public string $description;

    /**
     * @var array<int,GettingStartedChecklistStep>
     */
    public array $steps;

    /**
     * @param array<string,mixed> $definition
     */
    public function __construct( array $definition = [] ) {
        $this->id          = self::clean_key( (string) ( $definition['id'] ?? $definition['key'] ?? $definition['label'] ?? '' ) );
        $this->label       = trim( (string) ( $definition['label'] ?? $this->id ) );
        $this->description = trim( (string) ( $definition['description'] ?? '' ) );
        $this->steps       = $this->normalize_steps( (array) ( $definition['steps'] ?? [] ) );

        if ( '' === $this->id ) {
            $this->id = 'default';
        }

        if ( '' === $this->label ) {
            $this->label = ucwords( str_replace( [ '-', '_' ], ' ', $this->id ) );
        }
    }

    /**
     * @param array<string,mixed>|self $definition
     */
    public static function from( array|self $definition ): self {
        return $definition instanceof self ? $definition : new self( $definition );
    }

    public function find_step( string $step_id ): ?GettingStartedChecklistStep {
        $step_id = self::clean_key( $step_id );

        foreach ( $this->steps as $step ) {
            if ( $step->id === $step_id ) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>
     */
    public function to_public_array(): array {
        return [
            'id'          => $this->id,
            'label'       => $this->label,
            'description' => $this->description,
            'step_count'  => count( $this->steps ),
            'steps'       => array_map(
                static fn( GettingStartedChecklistStep $step ): array => $step->to_public_array(),
                $this->steps
            ),
        ];
    }

    /**
     * @param array<int|string,mixed> $steps
     * @return array<int,GettingStartedChecklistStep>
     */
    private function normalize_steps( array $steps ): array {
        $normalized = [];
        $seen       = [];

        foreach ( $steps as $key => $definition ) {
            if ( ! is_array( $definition ) && ! $definition instanceof GettingStartedChecklistStep ) {
                continue;
            }

            if ( is_array( $definition ) && is_string( $key ) && ! isset( $definition['id'] ) ) {
                $definition['id'] = $key;
            }

            $step = GettingStartedChecklistStep::from( $definition );
            if ( isset( $seen[ $step->id ] ) ) {
                continue;
            }

            $normalized[]      = $step;
            $seen[ $step->id ] = true;
        }

        return $normalized;
    }

    private static function clean_key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : ( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?: '' );
    }
}
