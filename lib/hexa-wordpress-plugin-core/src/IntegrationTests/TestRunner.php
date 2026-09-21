<?php

namespace Hexa\PluginCore\IntegrationTests;

use Throwable;

final class TestRunner {
    /** @return array<string,mixed> */
    public function run( array $filters = [] ): array {
        $registry = IntegrationTestRuntime::registry();
        if ( function_exists( 'do_action' ) ) {
            do_action( 'hexa_plugin_core_register_integration_tests', $registry );
        }

        $host_filter = self::key( (string) ( $filters['host'] ?? '' ) );
        $test_filter = strtolower( trim( (string) ( $filters['test'] ?? '' ) ) );
        $started      = microtime( true );
        $results      = [];

        foreach ( $registry->all() as $definition ) {
            if ( '' !== $host_filter && $host_filter !== $definition->host() ) {
                continue;
            }
            if ( '' !== $test_filter && false === strpos( strtolower( $definition->id() . ' ' . $definition->title() . ' ' . $definition->group() ), $test_filter ) ) {
                continue;
            }
            $results[] = $this->execute( $definition )->to_array();
        }

        $passed = count( array_filter( $results, static fn( array $result ): bool => ! empty( $result['passed'] ) ) );
        $failed = count( $results ) - $passed;
        $critical_failed = count( array_filter( $results, static fn( array $result ): bool => empty( $result['passed'] ) && ! empty( $result['critical'] ) ) );

        return [
            'status'       => 0 === $critical_failed && 0 === $failed ? 'pass' : 'fail',
            'passed'       => $passed,
            'failed'       => $failed,
            'total'        => count( $results ),
            'duration_ms'  => round( ( microtime( true ) - $started ) * 1000, 2 ),
            'generated_at' => function_exists( 'current_time' ) ? current_time( 'c' ) : gmdate( 'c' ),
            'filters'      => [ 'host' => $host_filter, 'test' => $test_filter ],
            'results'      => $results,
        ];
    }

    private function execute( TestDefinition $definition ): TestResult {
        $started = microtime( true );
        try {
            $raw = $definition->execute();
            if ( $raw instanceof TestResult ) {
                return $raw;
            }
            if ( is_bool( $raw ) ) {
                $raw = [ 'passed' => $raw ];
            }
            if ( ! is_array( $raw ) ) {
                $raw = [ 'passed' => false, 'summary' => 'The test callback returned an unsupported result.', 'actual' => get_debug_type( $raw ) ];
            }
            $passed = ! empty( $raw['passed'] );
            $raw['duration_ms'] = ( microtime( true ) - $started ) * 1000;
            return new TestResult( $definition, $passed, $raw );
        } catch ( Throwable $error ) {
            return new TestResult(
                $definition,
                false,
                [
                    'summary'     => 'The test threw ' . get_class( $error ) . '.',
                    'expected'    => 'Callback completes without an exception',
                    'actual'      => $error->getMessage(),
                    'details'     => [ 'file' => $error->getFile() . ':' . $error->getLine() ],
                    'duration_ms' => ( microtime( true ) - $started ) * 1000,
                ]
            );
        }
    }

    private static function key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( (string) preg_replace( '/[^a-z0-9_-]/i', '', $value ) );
    }
}
