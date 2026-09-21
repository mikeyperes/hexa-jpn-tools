<?php

namespace Hexa\PluginCore\PluginUpdates;

final class UpdateProgressStore {
    private string $key;

    public function __construct( string $key ) {
        $this->key = $key;
    }

    public function get(): array {
        $data = get_transient( $this->key );

        if ( ! is_array( $data ) ) {
            return [
                'started_at' => 0,
                'updated_at' => 0,
                'state'      => 'idle',
                'message'    => '',
                'steps'      => [],
            ];
        }

        return $data;
    }

    public function reset(): void {
        $this->save(
            [
                'started_at' => time(),
                'state'      => 'running',
                'message'    => '',
                'steps'      => [],
            ]
        );
    }

    public function step( string $message, string $status = 'running' ): void {
        $data = $this->get();

        foreach ( $data['steps'] as &$step ) {
            if ( isset( $step['status'] ) && 'running' === $step['status'] ) {
                $step['status'] = 'done';
            }
        }
        unset( $step );

        $data['steps'][] = [
            't'       => time(),
            'message' => $message,
            'status'  => $status,
        ];

        $this->save( $data );
    }

    public function finish( string $state, string $message ): void {
        $data = $this->get();

        foreach ( $data['steps'] as &$step ) {
            if ( isset( $step['status'] ) && 'running' === $step['status'] ) {
                $step['status'] = 'error' === $state ? 'error' : 'done';
            }
        }
        unset( $step );

        $data['state']   = $state;
        $data['message'] = $message;

        $this->save( $data );
    }

    private function save( array $data ): void {
        $data['updated_at'] = time();
        set_transient( $this->key, $data, 10 * MINUTE_IN_SECONDS );
    }
}
