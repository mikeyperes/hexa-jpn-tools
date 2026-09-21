<?php

namespace Hexa\PluginCore\GettingStartedChecklist;

final class GettingStartedChecklistConfig {
    /**
     * @var array<string,mixed>
     */
    private array $values;

    /**
     * @var array<int,GettingStartedChecklistStep>
     */
    private array $steps;

    /**
     * @var array<string,GettingStartedChecklistTemplate>
     */
    private array $templates;

    private string $default_template_id;

    /**
     * @param array<string,mixed> $values
     */
    public function __construct( array $values = [] ) {
        $defaults = [
            'root_id'              => 'hpc-getting-started-checklist',
            'title'                => 'Getting Started Checklist',
            'description'          => 'Run plugin setup checks and startup actions one step at a time through guarded AJAX requests.',
            'capability'           => 'manage_options',
            'nonce_action'         => 'hpc_getting_started_checklist',
            'nonce_field'          => 'nonce',
            'run_action'           => 'hpc_getting_started_checklist_run_item',
            'status_action'        => 'hpc_getting_started_checklist_status',
            'reset_action'         => 'hpc_getting_started_checklist_reset',
            'persistence_enabled'  => true,
            'state_option'         => '',
            'empty_message'        => 'No getting started steps have been registered.',
            'template_id'          => 'default',
            'template_label'       => 'Checklist Template',
            'template_load_label'  => 'Load Template',
            'show_template_picker' => false,
            'show_type_badges'     => true,
            'show_search'           => false,
            'search_label'          => 'Search checklist',
            'search_placeholder'    => 'Search checklist items...',
            'search_empty_message'  => 'No matching checklist items.',
            'templates'            => [],
            'steps'                => [],
        ];

        $values                 = array_merge( $defaults, $values );
        $values['root_id']      = $this->clean_html_id( (string) $values['root_id'] );
        $values['nonce_field']  = $this->clean_key( (string) $values['nonce_field'] );
        $values['run_action']   = $this->clean_key( (string) $values['run_action'] );
        $values['status_action'] = $this->clean_key( (string) $values['status_action'] );
        $values['reset_action']  = $this->clean_key( (string) $values['reset_action'] );
        $values['capability']   = trim( (string) $values['capability'] );
        $values['nonce_action'] = trim( (string) $values['nonce_action'] );
        $values['template_id']  = $this->clean_key( (string) $values['template_id'] ) ?: 'default';
        $values['state_option'] = $this->clean_key( (string) $values['state_option'] );
        if ( '' === $values['state_option'] ) {
            $values['state_option'] = 'hpc_gsc_state_' . md5( $values['root_id'] . '|' . $values['run_action'] );
        }

        $this->templates           = $this->normalize_templates( (array) $values['templates'], (array) $values['steps'], $values['template_id'] );
        $this->default_template_id = $this->resolve_template_id( $values['template_id'] );
        $this->steps               = $this->template_steps( $this->default_template_id );

        unset( $values['steps'], $values['templates'] );

        $this->values = $values;
    }

    public function get( string $key, mixed $default = null ): mixed {
        return array_key_exists( $key, $this->values ) ? $this->values[ $key ] : $default;
    }

    public function root_id(): string {
        return (string) $this->get( 'root_id' );
    }

    public function title(): string {
        return (string) $this->get( 'title' );
    }

    public function description(): string {
        return (string) $this->get( 'description' );
    }

    public function capability(): string {
        return (string) $this->get( 'capability', 'manage_options' );
    }

    public function nonce_action(): string {
        return (string) $this->get( 'nonce_action' );
    }

    public function nonce_field(): string {
        return (string) $this->get( 'nonce_field', 'nonce' );
    }

    public function run_action(): string {
        return (string) $this->get( 'run_action' );
    }

    public function status_action(): string {
        return (string) $this->get( 'status_action' );
    }

    public function reset_action(): string {
        return (string) $this->get( 'reset_action' );
    }

    public function persistence_enabled(): bool {
        return (bool) $this->get( 'persistence_enabled', true );
    }

    public function state_option(): string {
        return (string) $this->get( 'state_option' );
    }

    public function empty_message(): string {
        return (string) $this->get( 'empty_message' );
    }

    public function template_label(): string {
        return (string) $this->get( 'template_label', 'Checklist Template' );
    }

    public function template_load_label(): string {
        return (string) $this->get( 'template_load_label', 'Load Template' );
    }

    public function show_template_picker(): bool {
        return (bool) $this->get( 'show_template_picker', false ) || count( $this->templates ) > 1;
    }

    public function show_type_badges(): bool {
        return (bool) $this->get( 'show_type_badges', true );
    }

    public function show_search(): bool {
        return (bool) $this->get( 'show_search', false );
    }

    public function search_label(): string {
        return (string) $this->get( 'search_label', 'Search checklist' );
    }

    public function search_placeholder(): string {
        return (string) $this->get( 'search_placeholder', 'Search checklist items...' );
    }

    public function search_empty_message(): string {
        return (string) $this->get( 'search_empty_message', 'No matching checklist items.' );
    }

    public function default_template_id(): string {
        return $this->default_template_id;
    }

    public function resolve_template_id( string $template_id ): string {
        $template_id = $this->clean_key( $template_id );
        if ( '' !== $template_id && isset( $this->templates[ $template_id ] ) ) {
            return $template_id;
        }

        if ( isset( $this->templates['default'] ) ) {
            return 'default';
        }

        $first = array_key_first( $this->templates );
        return is_string( $first ) && '' !== $first ? $first : 'default';
    }

    /**
     * Resolve an operator-supplied template ID without normalizing an invalid
     * value into a different registered template.
     */
    public function match_template_id( string $template_id = '' ): ?string {
        if ( '' === $template_id ) {
            return isset( $this->templates[ $this->default_template_id ] ) ? $this->default_template_id : null;
        }

        $normalized = $this->clean_key( $template_id );
        if ( $normalized !== $template_id || ! isset( $this->templates[ $normalized ] ) ) {
            return null;
        }

        return $normalized;
    }

    /**
     * @return array<string,GettingStartedChecklistTemplate>
     */
    public function templates(): array {
        return $this->templates;
    }

    /**
     * @return array<int,GettingStartedChecklistStep>
     */
    public function steps(): array {
        return $this->steps;
    }

    /**
     * @return array<int,GettingStartedChecklistStep>
     */
    public function template_steps( string $template_id = '' ): array {
        $template_id = $this->match_template_id( $template_id );
        return null !== $template_id ? $this->templates[ $template_id ]->steps : [];
    }

    public function find_step( string $step_id, string $template_id = '' ): ?GettingStartedChecklistStep {
        $step_id = $this->clean_key( $step_id );
        $template_id = $this->match_template_id( $template_id );

        return null !== $template_id ? $this->templates[ $template_id ]->find_step( $step_id ) : null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function public_steps(): array {
        return array_map(
            static fn( GettingStartedChecklistStep $step ): array => $step->to_public_array(),
            $this->steps
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function public_templates(): array {
        return array_map(
            static fn( GettingStartedChecklistTemplate $template ): array => $template->to_public_array(),
            array_values( $this->templates )
        );
    }

    /**
     * @param array<int|string,mixed> $templates
     * @param array<int|string,mixed> $fallback_steps
     * @return array<string,GettingStartedChecklistTemplate>
     */
    private function normalize_templates( array $templates, array $fallback_steps, string $default_template_id ): array {
        $normalized = [];
        $seen       = [];

        foreach ( $templates as $key => $definition ) {
            if ( ! is_array( $definition ) && ! $definition instanceof GettingStartedChecklistTemplate ) {
                continue;
            }

            if ( is_array( $definition ) && is_string( $key ) && ! isset( $definition['id'] ) ) {
                $definition['id'] = $key;
            }

            $template = GettingStartedChecklistTemplate::from( $definition );
            if ( '' === $template->id || isset( $seen[ $template->id ] ) ) {
                continue;
            }

            $normalized[ $template->id ] = $template;
            $seen[ $template->id ] = true;
        }

        if ( [] === $normalized && [] !== $fallback_steps ) {
            $template_id = $this->clean_key( $default_template_id ) ?: 'default';
            $normalized[ $template_id ] = new GettingStartedChecklistTemplate(
                [
                    'id'          => $template_id,
                    'label'       => 'Default',
                    'description' => 'Default checklist template.',
                    'steps'       => $fallback_steps,
                ]
            );
        }

        return $normalized;
    }

    private function clean_key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : ( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?: '' );
    }

    private function clean_html_id( string $value ): string {
        $value = trim( $value );
        if ( '' === $value ) {
            return 'hpc-getting-started-checklist';
        }

        return function_exists( 'sanitize_html_class' ) ? sanitize_html_class( $value ) : ( preg_replace( '/[^a-zA-Z0-9_\-]/', '-', $value ) ?: 'hpc-getting-started-checklist' );
    }
}
