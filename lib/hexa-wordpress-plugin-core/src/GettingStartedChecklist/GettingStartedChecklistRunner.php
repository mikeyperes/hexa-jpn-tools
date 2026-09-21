<?php

namespace Hexa\PluginCore\GettingStartedChecklist;

use Throwable;

final class GettingStartedChecklistRunner {
    private GettingStartedChecklistConfig $config;

    private GettingStartedChecklistStateStore $state_store;

    public function __construct( GettingStartedChecklistConfig|array $config ) {
        $this->config = is_array( $config ) ? new GettingStartedChecklistConfig( $config ) : $config;
        $this->state_store = new GettingStartedChecklistStateStore( $this->config );
    }

    /**
     * @return array<string,mixed>
     */
    public function run_item( string $step_id, string $subtask_id = '', array $inputs = [], string $template_id = '' ): array {
        $authorization = $this->authorize_item( $step_id, $subtask_id, $template_id );
        if ( ! $authorization['allowed'] ) {
            $result = $this->failure_payload(
                (string) $authorization['step_id'],
                (string) $authorization['subtask_id'],
                (string) $authorization['message'],
                [
                    $this->log_entry(
                        'error',
                        (string) $authorization['message'],
                        [
                            'template_id' => (string) $authorization['template_id'],
                            'step_id'     => (string) $authorization['step_id'],
                            'subtask_id'  => (string) $authorization['subtask_id'],
                            'code'        => (string) $authorization['code'],
                        ]
                    ),
                ],
                [
                    'code'        => (string) $authorization['code'],
                    'status_code' => (int) $authorization['status_code'],
                ]
            );
            $result['mutating'] = (bool) $authorization['mutating'];
            return $result;
        }

        $template_id       = (string) $authorization['template_id'];
        $step_id           = (string) $authorization['step_id'];
        $subtask_id        = (string) $authorization['subtask_id'];
        $result            = $this->execute_item( $step_id, $subtask_id, $inputs, $template_id );
        $result['mutating'] = (bool) $authorization['mutating'];
        if ( $this->config->find_step( $step_id, $template_id ) instanceof GettingStartedChecklistStep ) {
            $result['state'] = $this->state_store->record( $result, $template_id );
            $result['summary'] = $result['state']['summary'];
        }
        return $result;
    }

    /**
     * Resolve and enforce every item-level capability. The host capability is
     * still enforced independently by the AJAX action registry.
     *
     * @return array<string,mixed>
     */
    public function authorize_item( string $step_id, string $subtask_id = '', string $template_id = '' ): array {
        $requested_template_id = $template_id;
        $template_id = $this->config->match_template_id( $requested_template_id );
        if ( null === $template_id ) {
            return $this->authorization_failure( 'Unknown checklist template.', 'unknown_checklist_template', 404, $requested_template_id, $step_id, $subtask_id );
        }

        $normalized_step_id = $this->clean_key( $step_id );
        if ( '' === $normalized_step_id || $normalized_step_id !== $step_id ) {
            return $this->authorization_failure( 'Unknown checklist step.', 'unknown_checklist_step', 404, $template_id, $step_id, $subtask_id );
        }

        $step = $this->config->find_step( $normalized_step_id, $template_id );
        if ( ! $step instanceof GettingStartedChecklistStep ) {
            return $this->authorization_failure( 'Unknown checklist step.', 'unknown_checklist_step', 404, $template_id, $normalized_step_id, $subtask_id );
        }

        $subtask = null;
        if ( '' !== $subtask_id ) {
            $normalized_subtask_id = $this->clean_key( $subtask_id );
            if ( '' === $normalized_subtask_id || $normalized_subtask_id !== $subtask_id ) {
                return $this->authorization_failure( 'Unknown checklist subtask.', 'unknown_checklist_subtask', 404, $template_id, $step->id, $subtask_id );
            }
            $subtask = $step->find_subtask( $normalized_subtask_id );
            if ( ! $subtask instanceof GettingStartedChecklistSubtask ) {
                return $this->authorization_failure( 'Unknown checklist subtask.', 'unknown_checklist_subtask', 404, $template_id, $step->id, $normalized_subtask_id );
            }
        }

        $capabilities = array_values(
            array_unique(
                array_filter(
                    [
                        trim( $step->capability ),
                        $subtask instanceof GettingStartedChecklistSubtask ? trim( $subtask->capability ) : '',
                    ],
                    static fn( string $capability ): bool => '' !== $capability
                )
            )
        );

        foreach ( $capabilities as $capability ) {
            if ( ! function_exists( 'current_user_can' ) || ! current_user_can( $capability ) ) {
                return $this->authorization_failure(
                    'Permission denied for this checklist item.',
                    'checklist_item_unauthorized',
                    403,
                    $template_id,
                    $step->id,
                    $subtask instanceof GettingStartedChecklistSubtask ? $subtask->id : '',
                    $subtask instanceof GettingStartedChecklistSubtask ? $subtask->mutating : $step->mutating
                );
            }
        }

        return [
            'allowed'      => true,
            'status_code'  => 200,
            'code'         => '',
            'message'      => '',
            'template_id'  => $template_id,
            'step_id'      => $step->id,
            'subtask_id'   => $subtask instanceof GettingStartedChecklistSubtask ? $subtask->id : '',
            'capabilities' => $capabilities,
            'mutating'     => $subtask instanceof GettingStartedChecklistSubtask ? $subtask->mutating : $step->mutating,
        ];
    }

    /** @return array<string,mixed> */
    private function authorization_failure( string $message, string $code, int $status_code, string $template_id, string $step_id, string $subtask_id, bool $mutating = true ): array {
        return [
            'allowed'      => false,
            'status_code'  => $status_code,
            'code'         => $code,
            'message'      => $message,
            'template_id'  => $template_id,
            'step_id'      => $step_id,
            'subtask_id'   => $subtask_id,
            'capabilities' => [],
            'mutating'     => $mutating,
        ];
    }

    /** @return array<string,mixed> */
    private function execute_item( string $step_id, string $subtask_id, array $inputs, string $template_id ): array {
        $step        = $this->config->find_step( $step_id, $template_id );
        if ( ! $step instanceof GettingStartedChecklistStep ) {
            return $this->failure_payload(
                $step_id,
                $subtask_id,
                'Unknown checklist step.',
                [
                    $this->log_entry( 'error', 'Requested checklist step was not registered.', [ 'template_id' => $template_id, 'step_id' => $step_id ] ),
                ]
            );
        }

        $subtask = '' !== $subtask_id ? $step->find_subtask( $subtask_id ) : null;
        if ( '' !== $subtask_id && ! $subtask instanceof GettingStartedChecklistSubtask ) {
            return $this->failure_payload(
                $step->id,
                $subtask_id,
                'Unknown checklist subtask.',
                [
                    $this->log_entry( 'error', 'Requested checklist subtask was not registered.', [ 'template_id' => $template_id, 'step_id' => $step->id, 'subtask_id' => $subtask_id ] ),
                ]
            );
        }

        $callback = $subtask instanceof GettingStartedChecklistSubtask ? $subtask->callback : $step->callback;
        $label    = $subtask instanceof GettingStartedChecklistSubtask ? $subtask->label : $step->label;
        $type     = $subtask instanceof GettingStartedChecklistSubtask ? $subtask->type : $step->type;
        $request  = $subtask instanceof GettingStartedChecklistSubtask ? $subtask->request : $step->request;
        $context  = $subtask instanceof GettingStartedChecklistSubtask ? array_merge( $step->context, $subtask->context ) : $step->context;
        $required_inputs = $subtask instanceof GettingStartedChecklistSubtask ? $subtask->required_inputs : $step->required_inputs;
        $logs     = [
            $this->log_entry(
                'info',
                'Starting checklist item.',
                [
                    'template_id' => $template_id,
                    'step_id'     => $step->id,
                    'subtask_id'  => $subtask instanceof GettingStartedChecklistSubtask ? $subtask->id : '',
                    'label'       => $label,
                    'type'       => $type,
                ]
            ),
        ];

        $input_validation = $this->validate_required_inputs( $required_inputs, $inputs );
        if ( ! $input_validation['success'] ) {
            $logs[] = $this->log_entry( 'error', $input_validation['message'], [ 'label' => $label, 'missing_inputs' => $input_validation['missing'] ] );

            return $this->failure_payload(
                $step->id,
                $subtask instanceof GettingStartedChecklistSubtask ? $subtask->id : '',
                $input_validation['message'],
                $logs,
                [
                    'missing_inputs' => $input_validation['missing'],
                    'inputs'         => $input_validation['values'],
                ]
            );
        }

        if ( ! is_callable( $callback ) ) {
            if ( $this->requires_callback( $type, $request ) ) {
                $message = 'No callback registered for this checklist item type.';
                $logs[]  = $this->log_entry( 'error', $message, [ 'label' => $label, 'type' => $type ] );

                return $this->failure_payload( $step->id, $subtask instanceof GettingStartedChecklistSubtask ? $subtask->id : '', $message, $logs );
            }

            $message = 'No callback registered; item marked complete.';
            $logs[]  = $this->log_entry( 'success', $message, [ 'label' => $label, 'type' => $type ] );

            return $this->success_payload( $step, $subtask, $message, $logs );
        }

        try {
            $result = call_user_func(
                $callback,
                [
                    'template_id'  => $template_id,
                    'step'         => $step->to_callback_array(),
                    'subtask'      => $subtask instanceof GettingStartedChecklistSubtask ? $subtask->to_callback_array() : null,
                    'context'      => array_merge( [ 'template_id' => $template_id ], $context ),
                    'request'      => $request,
                    'inputs'       => $input_validation['values'],
                    'request_type' => $type,
                    'is_subtask'   => $subtask instanceof GettingStartedChecklistSubtask,
                    'item_id'      => $subtask instanceof GettingStartedChecklistSubtask ? $step->id . ':' . $subtask->id : $step->id,
                ]
            );
        } catch ( Throwable $throwable ) {
            $message = 'Checklist item failed: ' . $throwable->getMessage();
            $logs[]  = $this->log_entry(
                'error',
                $message,
                [
                    'template_id' => $template_id,
                    'step_id'     => $step->id,
                    'subtask_id'  => $subtask instanceof GettingStartedChecklistSubtask ? $subtask->id : '',
                    'exception'   => get_class( $throwable ),
                ]
            );

            return $this->failure_payload( $step->id, $subtask instanceof GettingStartedChecklistSubtask ? $subtask->id : '', $message, $logs );
        }

        $normalized = $this->normalize_result( $result, $label );
        $logs       = array_merge( $logs, $normalized['logs'] );

        if ( $normalized['success'] ) {
            $logs[] = $this->log_entry( 'success', $normalized['message'], [ 'label' => $label, 'type' => $type ] );

            return $this->success_payload( $step, $subtask, $normalized['message'], $logs, $normalized['data'] );
        }

        $logs[] = $this->log_entry( 'error', $normalized['message'], [ 'label' => $label, 'type' => $type ] );

        return $this->failure_payload( $step->id, $subtask instanceof GettingStartedChecklistSubtask ? $subtask->id : '', $normalized['message'], $logs, $normalized['data'] );
    }

    /**
     * @param mixed $result
     * @return array{success:bool,message:string,logs:array<int,array<string,mixed>>,data:array<string,mixed>}
     */
    private function normalize_result( mixed $result, string $label ): array {
        if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) {
            return [
                'success' => false,
                'message' => $result->get_error_message(),
                'logs'    => [],
                'data'    => [ 'code' => $result->get_error_code() ],
            ];
        }

        if ( is_bool( $result ) ) {
            return [
                'success' => $result,
                'message' => $result ? $label . ' completed.' : $label . ' failed.',
                'logs'    => [],
                'data'    => [],
            ];
        }

        if ( is_string( $result ) ) {
            return [
                'success' => true,
                'message' => '' !== trim( $result ) ? trim( $result ) : $label . ' completed.',
                'logs'    => [],
                'data'    => [],
            ];
        }

        if ( is_array( $result ) ) {
            $success = true;
            if ( array_key_exists( 'success', $result ) ) {
                $success = (bool) $result['success'];
            } elseif ( isset( $result['status'] ) ) {
                $status  = strtolower( (string) $result['status'] );
                $success = ! in_array( $status, [ 'failed', 'fail', 'error', 'false' ], true );
            }

            $message = trim( (string) ( $result['message'] ?? '' ) );
            if ( '' === $message ) {
                $message = $success ? $label . ' completed.' : $label . ' failed.';
            }

            $logs = [];
            foreach ( (array) ( $result['logs'] ?? $result['log'] ?? [] ) as $entry ) {
                $logs[] = $this->normalize_log_entry( $entry );
            }

            $data = is_array( $result['data'] ?? null ) ? $result['data'] : [];

            return [
                'success' => $success,
                'message' => $message,
                'logs'    => $logs,
                'data'    => $data,
            ];
        }

        return [
            'success' => true,
            'message' => $label . ' completed.',
            'logs'    => [],
            'data'    => [],
        ];
    }

    /**
     * @param array<string,mixed> $request
     */
    private function requires_callback( string $type, array $request ): bool {
        if ( [] !== $request ) {
            return true;
        }

        return in_array(
            $type,
            [
                GettingStartedChecklistStep::TYPE_SETUP_ACTION,
                GettingStartedChecklistStep::TYPE_FEATURE_TOGGLE,
                GettingStartedChecklistStep::TYPE_CONFIG_MUTATION,
                GettingStartedChecklistStep::TYPE_AJAX_REQUEST,
            ],
            true
        );
    }

    /**
     * @param array<int,array<string,mixed>> $definitions
     * @param array<string,mixed> $inputs
     * @return array{success:bool,message:string,values:array<string,string>,missing:array<int,string>}
     */
    private function validate_required_inputs( array $definitions, array $inputs ): array {
        $values  = [];
        $missing = [];

        foreach ( $definitions as $definition ) {
            if ( ! is_array( $definition ) ) {
                continue;
            }

            $id = (string) ( $definition['id'] ?? '' );
            if ( '' === $id ) {
                continue;
            }

            $raw   = $inputs[ $id ] ?? '';
            $value = is_scalar( $raw ) ? trim( (string) $raw ) : '';
            $type  = (string) ( $definition['type'] ?? 'text' );

            if ( 'email' === $type && '' !== $value ) {
                $value = function_exists( 'sanitize_email' ) ? sanitize_email( $value ) : filter_var( $value, FILTER_SANITIZE_EMAIL );
            } elseif ( 'url' === $type && '' !== $value ) {
                $value = function_exists( 'esc_url_raw' ) ? esc_url_raw( $value ) : filter_var( $value, FILTER_SANITIZE_URL );
            } else {
                $value = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : trim( strip_tags( $value ) );
            }

            $values[ $id ] = $value;

            if ( ! (bool) ( $definition['required'] ?? true ) && '' === $value ) {
                continue;
            }

            if ( '' === $value ) {
                $missing[] = (string) ( $definition['label'] ?? $id );
                continue;
            }

            if ( 'email' === $type && function_exists( 'is_email' ) && ! is_email( $value ) ) {
                $missing[] = (string) ( $definition['label'] ?? $id );
                continue;
            }

            if ( 'number' === $type ) {
                if ( ! is_numeric( $value ) ) {
                    $missing[] = (string) ( $definition['label'] ?? $id );
                    continue;
                }

                $numeric = (float) $value;
                if ( '' !== (string) ( $definition['min'] ?? '' ) && $numeric < (float) $definition['min'] ) {
                    $missing[] = (string) ( $definition['label'] ?? $id );
                    continue;
                }

                if ( '' !== (string) ( $definition['max'] ?? '' ) && $numeric > (float) $definition['max'] ) {
                    $missing[] = (string) ( $definition['label'] ?? $id );
                    continue;
                }
            }

            if ( 'confirmation' === $type ) {
                $expected = (string) ( $definition['confirm_text'] ?? $definition['expected_value'] ?? $definition['expected'] ?? '' );
                if ( '' !== $expected ) {
                    $case_sensitive = (bool) ( $definition['case_sensitive'] ?? true );
                    $matches        = $case_sensitive ? hash_equals( $expected, $value ) : 0 === strcasecmp( $expected, $value );
                    if ( ! $matches ) {
                        $missing[] = (string) ( $definition['label'] ?? $id );
                    }
                }
            }
        }

        if ( [] !== $missing ) {
            return [
                'success' => false,
                'message' => 'Required input missing or invalid: ' . implode( ', ', $missing ),
                'values'  => $values,
                'missing' => $missing,
            ];
        }

        return [
            'success' => true,
            'message' => '',
            'values'  => $values,
            'missing' => [],
        ];
    }

    /**
     * @param GettingStartedChecklistSubtask|null $subtask
     * @param array<int,array<string,mixed>> $logs
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function success_payload( GettingStartedChecklistStep $step, ?GettingStartedChecklistSubtask $subtask, string $message, array $logs, array $data = [] ): array {
        return [
            'success'    => true,
            'status'     => 'success',
            'step_id'    => $step->id,
            'subtask_id' => $subtask instanceof GettingStartedChecklistSubtask ? $subtask->id : '',
            'message'    => $message,
            'logs'       => $logs,
            'data'       => $data,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $logs
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function failure_payload( string $step_id, string $subtask_id, string $message, array $logs = [], array $data = [] ): array {
        return [
            'success'    => false,
            'status'     => 'failed',
            'step_id'    => $step_id,
            'subtask_id' => $subtask_id,
            'message'    => $message,
            'logs'       => $logs,
            'data'       => $data,
        ];
    }

    /**
     * @param mixed $entry
     * @return array<string,mixed>
     */
    private function normalize_log_entry( mixed $entry ): array {
        if ( is_array( $entry ) ) {
            return $this->log_entry(
                (string) ( $entry['level'] ?? 'info' ),
                (string) ( $entry['message'] ?? '' ),
                is_array( $entry['context'] ?? null ) ? $entry['context'] : []
            );
        }

        return $this->log_entry( 'info', is_scalar( $entry ) ? (string) $entry : 'Checklist reported an event.' );
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function log_entry( string $level, string $message, array $context = [] ): array {
        $allowed = [ 'info', 'success', 'warning', 'error' ];
        $level   = strtolower( $level );
        if ( ! in_array( $level, $allowed, true ) ) {
            $level = 'info';
        }

        return [
            'time'    => function_exists( 'current_time' ) ? current_time( 'H:i:s' ) : gmdate( 'H:i:s' ),
            'level'   => $level,
            'message' => $message,
            'context' => $context,
        ];
    }

    private function clean_key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : ( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?: '' );
    }
}
