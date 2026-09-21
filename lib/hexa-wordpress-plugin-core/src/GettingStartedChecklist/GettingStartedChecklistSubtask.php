<?php

namespace Hexa\PluginCore\GettingStartedChecklist;

final class GettingStartedChecklistSubtask {
    public string $id;

    public string $label;

    public string $description;

    public string $type;

    /**
     * @var callable|null
     */
    public mixed $callback;

    public string $action_label;

    public bool $batch_enabled;

    public bool $destructive;

    public bool $mutating;

    public string $capability;

    /**
     * @var array<string,mixed>
     */
    public array $request;

    /**
     * @var array<int,array<string,mixed>>
     */
    public array $required_inputs;

    /**
     * @var array<string,mixed>
     */
    public array $context;

    /**
     * @param array<string,mixed> $definition
     */
    public function __construct( array $definition = [] ) {
        $this->id          = self::clean_key( (string) ( $definition['id'] ?? $definition['key'] ?? $definition['label'] ?? '' ) );
        $this->label       = trim( (string) ( $definition['label'] ?? $this->id ) );
        $this->description = trim( (string) ( $definition['description'] ?? '' ) );
        $this->type        = GettingStartedChecklistStep::normalize_type( (string) ( $definition['type'] ?? $definition['kind'] ?? $definition['mode'] ?? GettingStartedChecklistStep::TYPE_CALLBACK ) );
        $this->callback    = isset( $definition['callback'] ) && is_callable( $definition['callback'] ) ? $definition['callback'] : null;
        $this->action_label = trim( (string) ( $definition['action_label'] ?? GettingStartedChecklistStep::default_action_label( $this->type ) ) );
        $this->batch_enabled = (bool) ( $definition['batch_enabled'] ?? true );
        $this->destructive   = (bool) ( $definition['destructive'] ?? false );
        $this->mutating      = $this->destructive || ( array_key_exists( 'mutating', $definition )
            ? (bool) $definition['mutating']
            : GettingStartedChecklistStep::type_is_mutating( $this->type ) );
        $this->capability    = trim( (string) ( $definition['capability'] ?? '' ) );
        $this->request     = is_array( $definition['request'] ?? null ) ? $definition['request'] : [];
        $this->required_inputs = GettingStartedChecklistStep::normalize_required_inputs( (array) ( $definition['required_inputs'] ?? $definition['inputs'] ?? [] ) );
        $this->context     = is_array( $definition['context'] ?? null ) ? $definition['context'] : [];

        if ( '' === $this->id ) {
            $this->id = 'subtask';
        }

        if ( '' === $this->label ) {
            $this->label = $this->id;
        }

        if ( '' === $this->action_label ) {
            $this->action_label = GettingStartedChecklistStep::default_action_label( $this->type );
        }
    }

    /**
     * @param array<string,mixed>|self $definition
     */
    public static function from( array|self $definition ): self {
        return $definition instanceof self ? $definition : new self( $definition );
    }

    public function has_callback(): bool {
        return is_callable( $this->callback );
    }

    /**
     * @return array<string,mixed>
     */
    public function to_public_array(): array {
        return [
            'id'           => $this->id,
            'label'        => $this->label,
            'description'  => $this->description,
            'type'         => $this->type,
            'action_label' => $this->action_label,
            'batch_enabled'=> $this->batch_enabled,
            'destructive'  => $this->destructive,
            'mutating'     => $this->mutating,
            'capability'   => $this->capability,
            'request'      => $this->public_request(),
            'required_inputs' => $this->required_inputs,
            'has_callback' => $this->has_callback(),
            'context'      => $this->context,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function to_callback_array(): array {
        $payload            = $this->to_public_array();
        $payload['request'] = $this->request;

        return $payload;
    }

    private static function clean_key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : ( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?: '' );
    }

    /**
     * @return array<string,mixed>
     */
    private function public_request(): array {
        if ( [] === $this->request ) {
            return [];
        }

        $public = [];
        foreach ( $this->request as $key => $value ) {
            $key_string = is_string( $key ) ? $key : (string) $key;
            if ( preg_match( '/(secret|password|token|key|nonce)/i', $key_string ) ) {
                $public[ $key_string ] = '[redacted]';
                continue;
            }

            $public[ $key_string ] = is_scalar( $value ) || null === $value ? $value : '[' . gettype( $value ) . ']';
        }

        return $public;
    }
}
