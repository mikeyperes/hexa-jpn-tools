<?php

namespace Hexa\PluginCore\GettingStartedChecklist;

/**
 * Persists host-scoped checklist outcomes through the WordPress options API.
 */
final class GettingStartedChecklistStateStore {
    private GettingStartedChecklistConfig $config;

    /** @var array<string,mixed> */
    private static array $memory = [];

    public function __construct( GettingStartedChecklistConfig|array $config ) {
        $this->config = is_array( $config ) ? new GettingStartedChecklistConfig( $config ) : $config;
    }

    /** @return array<string,mixed> */
    public function status( string $template_id = '' ): array {
        $requested_template_id = $template_id;
        $template_id = $this->config->match_template_id( $requested_template_id );
        if ( null === $template_id ) {
            return $this->invalid_template_status( $requested_template_id );
        }

        $saved       = $this->read();
        $stored      = is_array( $saved['templates'][ $template_id ] ?? null ) ? $saved['templates'][ $template_id ] : [];
        $items       = [];

        foreach ( $this->config->template_steps( $template_id ) as $step ) {
            $child_statuses = [];
            foreach ( $step->subtasks as $subtask ) {
                $item_id = $step->id . ':' . $subtask->id;
                $items[ $item_id ] = $this->normalize_item( $stored[ $item_id ] ?? [], $step->id, $subtask->id );
                $child_statuses[] = $items[ $item_id ]['status'];
            }

            $step_item = $this->normalize_item( $stored[ $step->id ] ?? [], $step->id, '' );
            if ( 'pending' === $step_item['status'] && [] !== $child_statuses ) {
                if ( in_array( 'failed', $child_statuses, true ) ) {
                    $step_item['status'] = 'failed';
                    $step_item['message'] = 'One or more subtasks failed.';
                } elseif ( ! in_array( 'pending', $child_statuses, true ) && ! in_array( 'running', $child_statuses, true ) ) {
                    $step_item['status'] = 'success';
                    $step_item['message'] = 'All subtasks complete.';
                }
            }
            $items[ $step->id ] = $step_item;
        }

        return [
            'template_id' => $template_id,
            'items'       => $items,
            'summary'     => $this->summarize_items( $items ),
            'updated_at'  => (string) ( $saved['updated_at'] ?? '' ),
            'persistent'  => $this->config->persistence_enabled(),
            'valid_template' => true,
        ];
    }

    /** @return array<string,int|bool|string> */
    public function summary( string $template_id = '' ): array {
        return $this->status( $template_id )['summary'];
    }

    /** @param array<string,mixed> $result
     *  @return array<string,mixed>
     */
    public function record( array $result, string $template_id = '' ): array {
        $requested_template_id = $template_id;
        $template_id = $this->config->match_template_id( $requested_template_id );
        if ( null === $template_id ) {
            return $this->invalid_template_status( $requested_template_id );
        }

        if ( ! $this->config->persistence_enabled() ) {
            return $this->status( $template_id );
        }

        $raw_step_id    = (string) ( $result['step_id'] ?? '' );
        $raw_subtask_id = (string) ( $result['subtask_id'] ?? '' );
        $step_id        = $this->clean_key( $raw_step_id );
        $subtask_id     = $this->clean_key( $raw_subtask_id );
        $step           = $this->config->find_step( $step_id, $template_id );
        if ( '' === $step_id || $step_id !== $raw_step_id || ! $step instanceof GettingStartedChecklistStep ) {
            return $this->status( $template_id );
        }
        if ( '' !== $subtask_id && ( $subtask_id !== $raw_subtask_id || ! $step->find_subtask( $subtask_id ) instanceof GettingStartedChecklistSubtask ) ) {
            return $this->status( $template_id );
        }

        $item_id = '' !== $subtask_id ? $step_id . ':' . $subtask_id : $step_id;
        $state = $this->read();
        $state['version'] = 1;
        $state['templates'] = is_array( $state['templates'] ?? null ) ? $state['templates'] : [];
        $state['templates'][ $template_id ] = is_array( $state['templates'][ $template_id ] ?? null ) ? $state['templates'][ $template_id ] : [];
        $state['templates'][ $template_id ][ $item_id ] = [
            'status'     => ! empty( $result['success'] ) ? 'success' : 'failed',
            'message'    => trim( (string) ( $result['message'] ?? '' ) ),
            'updated_at' => $this->now(),
        ];
        $state['updated_at'] = $this->now();
        $this->write( $state );

        return $this->status( $template_id );
    }

    /** @return array<string,mixed> */
    public function reset( string $template_id = '' ): array {
        $requested_template_id = $template_id;
        $matched_template_id   = '' === $template_id ? null : $this->config->match_template_id( $template_id );
        if ( '' !== $template_id && null === $matched_template_id ) {
            return $this->invalid_template_status( $requested_template_id );
        }

        if ( $this->config->persistence_enabled() ) {
            $state = $this->read();
            if ( '' === trim( $template_id ) ) {
                $this->delete();
            } else {
                $template_id = (string) $matched_template_id;
                unset( $state['templates'][ $template_id ] );
                $state['updated_at'] = $this->now();
                $this->write( $state );
            }
        }

        return $this->status( $template_id );
    }

    /** @return array<string,mixed> */
    private function invalid_template_status( string $template_id ): array {
        return [
            'template_id'    => $template_id,
            'items'          => [],
            'summary'        => $this->summarize_items( [] ),
            'updated_at'     => '',
            'persistent'     => $this->config->persistence_enabled(),
            'valid_template' => false,
        ];
    }

    /** @return array<string,mixed> */
    private function read(): array {
        $value = function_exists( 'get_option' )
            ? get_option( $this->config->state_option(), [] )
            : ( self::$memory[ $this->config->state_option() ] ?? [] );
        return is_array( $value ) ? $value : [];
    }

    /** @param array<string,mixed> $value */
    private function write( array $value ): void {
        if ( function_exists( 'update_option' ) ) {
            update_option( $this->config->state_option(), $value, false );
            return;
        }
        self::$memory[ $this->config->state_option() ] = $value;
    }

    private function delete(): void {
        if ( function_exists( 'delete_option' ) ) {
            delete_option( $this->config->state_option() );
            return;
        }
        unset( self::$memory[ $this->config->state_option() ] );
    }

    /** @return array{status:string,message:string,updated_at:string,step_id:string,subtask_id:string} */
    private function normalize_item( mixed $item, string $step_id, string $subtask_id ): array {
        $item = is_array( $item ) ? $item : [];
        $status = strtolower( (string) ( $item['status'] ?? 'pending' ) );
        if ( ! in_array( $status, [ 'pending', 'running', 'success', 'failed' ], true ) ) {
            $status = 'pending';
        }
        return [
            'status'     => $status,
            'message'    => trim( (string) ( $item['message'] ?? '' ) ),
            'updated_at' => trim( (string) ( $item['updated_at'] ?? '' ) ),
            'step_id'    => $step_id,
            'subtask_id' => $subtask_id,
        ];
    }

    /** @param array<string,array<string,mixed>> $items
     *  @return array<string,int|bool|string>
     */
    private function summarize_items( array $items ): array {
        $summary = [ 'total' => count( $items ), 'pending' => 0, 'running' => 0, 'success' => 0, 'failed' => 0 ];
        foreach ( $items as $item ) {
            $status = (string) ( $item['status'] ?? 'pending' );
            $summary[ isset( $summary[ $status ] ) ? $status : 'pending' ]++;
        }
        $summary['complete'] = $summary['total'] > 0 && $summary['success'] === $summary['total'];
        $summary['status'] = $summary['failed'] > 0 ? 'failed' : ( $summary['complete'] ? 'success' : ( $summary['running'] > 0 ? 'running' : 'pending' ) );
        return $summary;
    }

    private function now(): string {
        return function_exists( 'current_time' ) ? (string) current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s' );
    }

    private function clean_key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : ( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?: '' );
    }
}
