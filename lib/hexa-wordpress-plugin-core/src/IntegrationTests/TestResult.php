<?php

namespace Hexa\PluginCore\IntegrationTests;

final class TestResult {
    private TestDefinition $definition;
    private bool $passed;
    private string $summary;
    private string $expected;
    private string $actual;
    private array $details;
    private float $duration_ms;

    public function __construct( TestDefinition $definition, bool $passed, array $data = [] ) {
        $this->definition = $definition;
        $this->passed     = $passed;
        $this->summary    = trim( (string) ( $data['summary'] ?? ( $passed ? 'Passed.' : 'Failed.' ) ) );
        $this->expected   = self::display_value( $data['expected'] ?? '' );
        $this->actual     = self::display_value( $data['actual'] ?? '' );
        $this->details    = self::normalize_details( $data['details'] ?? [] );
        $this->duration_ms = max( 0, (float) ( $data['duration_ms'] ?? 0 ) );
    }

    public function passed(): bool {
        return $this->passed;
    }

    public function critical(): bool {
        return $this->definition->critical();
    }

    /** @return array<string,mixed> */
    public function to_array(): array {
        return [
            'id'          => $this->definition->id(),
            'title'       => $this->definition->title(),
            'description' => $this->definition->description(),
            'group'       => $this->definition->group(),
            'host'        => $this->definition->host(),
            'critical'    => $this->definition->critical(),
            'status'      => $this->passed ? 'pass' : 'fail',
            'passed'      => $this->passed,
            'summary'     => $this->summary,
            'expected'    => $this->expected,
            'actual'      => $this->actual,
            'details'     => $this->details,
            'duration_ms' => round( $this->duration_ms, 2 ),
        ];
    }

    private static function normalize_details( mixed $details ): array {
        if ( is_scalar( $details ) && '' !== trim( (string) $details ) ) {
            return [ trim( (string) $details ) ];
        }
        if ( ! is_array( $details ) ) {
            return [];
        }

        $normalized = [];
        foreach ( $details as $key => $value ) {
            if ( ! is_scalar( $value ) && null !== $value ) {
                $value = function_exists( 'wp_json_encode' ) ? wp_json_encode( $value, JSON_UNESCAPED_SLASHES ) : json_encode( $value );
            }
            $display = self::display_value( $value );
            if ( '' === $display ) {
                continue;
            }
            $normalized[ is_string( $key ) ? $key : count( $normalized ) ] = $display;
        }
        return $normalized;
    }

    private static function display_value( mixed $value ): string {
        if ( is_bool( $value ) ) {
            return $value ? 'true' : 'false';
        }
        if ( null === $value ) {
            return 'null';
        }
        if ( is_scalar( $value ) ) {
            return trim( (string) $value );
        }
        $encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $value, JSON_UNESCAPED_SLASHES ) : json_encode( $value );
        return is_string( $encoded ) ? $encoded : '';
    }
}
