<?php

namespace Hexa\PluginCore\GettingStartedChecklist;

use Hexa\PluginCore\WpAdminComponents\CoreUi;
use Hexa\PluginCore\WpAdminComponents\DynamicButton;

final class GettingStartedChecklistRenderer {
    private GettingStartedChecklistConfig $config;

    public function __construct( GettingStartedChecklistConfig|array $config ) {
        $this->config = is_array( $config ) ? new GettingStartedChecklistConfig( $config ) : $config;
    }

    public function render(): void {
        CoreUi::render_assets();
        DynamicButton::render_assets();

        $root_id             = $this->config->root_id();
        $default_template_id = $this->config->default_template_id();
        $templates           = $this->config->templates();
        $steps               = $this->config->template_steps( $default_template_id );
        $nonce               = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( $this->config->nonce_action() ) : '';
        ?>
        <div id="<?php echo esc_attr( $root_id ); ?>" class="hpc-ui hpc-gsc" data-hpc-getting-started-checklist data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-run-action="<?php echo esc_attr( $this->config->run_action() ); ?>" data-status-action="<?php echo esc_attr( $this->config->status_action() ); ?>" data-reset-action="<?php echo esc_attr( $this->config->reset_action() ); ?>" data-persistence-enabled="<?php echo $this->config->persistence_enabled() ? '1' : '0'; ?>" data-nonce-field="<?php echo esc_attr( $this->config->nonce_field() ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-default-template-id="<?php echo esc_attr( $default_template_id ); ?>" data-current-template-id="<?php echo esc_attr( $default_template_id ); ?>">
            <?php echo GettingStartedChecklistAssets::render( $root_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php
            ob_start();
            ?>
                <?php if ( '' !== $this->config->description() ) : ?>
                    <p class="hpc-gsc-description"><?php echo esc_html( $this->config->description() ); ?></p>
                <?php endif; ?>

                <?php echo $this->template_picker_html( $templates, $default_template_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                <?php if ( $this->config->show_search() && [] !== $steps ) : ?>
                    <?php
                    echo CoreUi::collection_filter(
                        [
                            'id'                  => $root_id . '-search',
                            'target_id'           => $root_id . '-items',
                            'item_selector'       => '[data-gsc-filter-item]',
                            'group_selector'      => '[data-gsc-step-card]',
                            'label'               => $this->config->search_label(),
                            'placeholder'         => $this->config->search_placeholder(),
                            'item_label_singular' => 'checklist item',
                            'item_label_plural'   => 'checklist items',
                            'empty_message'       => $this->config->search_empty_message(),
                        ]
                    ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                <?php endif; ?>

                <div class="hpc-gsc-actions">
                    <?php echo DynamicButton::render( [ 'label' => 'Run Checklist', 'working_label' => 'Running...', 'success_label' => 'Checklist Finished', 'error_label' => 'Checklist Failed', 'class' => 'hpc-button', 'attrs' => [ 'data-gsc-run-all' => true ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <button type="button" class="hpc-button secondary" data-gsc-reset>Reset Checklist</button>
                    <span class="hpc-gsc-summary" data-gsc-summary aria-live="polite">Not run</span>
                </div>

                <?php if ( [] === $steps ) : ?>
                    <div class="hpc-callout"><?php echo esc_html( $this->config->empty_message() ); ?></div>
                <?php else : ?>
                    <div id="<?php echo esc_attr( $root_id . '-items' ); ?>" class="hpc-gsc-list" data-gsc-list>
                        <?php foreach ( $steps as $step ) : ?>
                            <?php echo $this->step_html( $step ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php echo $this->template_store_html( $templates ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                <details class="hpc-gsc-log">
                    <summary class="hpc-gsc-log-head">
                        <div>
                            <h3>Technical Activity Log</h3>
                            <span>Reports each AJAX request, callback result, subtask transition, and failure message.</span>
                        </div>
                        <span class="hpc-gsc-log-controls">
                            <span class="hpc-gsc-log-chevron" aria-hidden="true"><svg viewBox="0 0 512 512" focusable="false"><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"></path></svg></span>
                            <button type="button" class="hpc-button secondary" data-gsc-clear-log>Clear</button>
                        </span>
                    </summary>
                    <div class="hpc-gsc-log-body" data-gsc-log-body aria-live="polite">
                        <?php echo $this->log_row( [ 'time' => 'Ready', 'level' => 'info', 'message' => 'Checklist runner is ready.', 'context' => [] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </details>
            <?php
            $body = (string) ob_get_clean();

            echo CoreUi::collapsible(
                [
                    'title'       => $this->config->title(),
                    'open'        => true,
                    'persist_key' => $root_id . '-panel',
                    'meta_html'   => CoreUi::pill( count( $steps ) . ' steps', 'dark' ) . ( count( $templates ) > 1 ? CoreUi::pill( count( $templates ) . ' templates', 'blue' ) : '' ),
                    'body_html'   => $body,
                ]
            ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
        </div>
        <?php
    }

    /**
     * @param array<string,GettingStartedChecklistTemplate> $templates
     */
    private function template_picker_html( array $templates, string $default_template_id ): string {
        if ( ! $this->config->show_template_picker() || [] === $templates ) {
            return '';
        }

        $current = $templates[ $default_template_id ] ?? reset( $templates );
        ob_start();
        ?>
        <div class="hpc-gsc-template-picker" data-gsc-template-picker>
            <label for="<?php echo esc_attr( $this->config->root_id() . '-template' ); ?>">
                <span><?php echo esc_html( $this->config->template_label() ); ?></span>
                <select id="<?php echo esc_attr( $this->config->root_id() . '-template' ); ?>" data-gsc-template-select>
                    <?php foreach ( $templates as $template ) : ?>
                        <option value="<?php echo esc_attr( $template->id ); ?>" <?php selected( $template->id, $default_template_id ); ?> data-step-count="<?php echo esc_attr( (string) count( $template->steps ) ); ?>" data-description="<?php echo esc_attr( $template->description ); ?>"><?php echo esc_html( $template->label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php
            echo DynamicButton::render(
                [
                    'label'         => $this->config->template_load_label(),
                    'working_label' => 'Loading...',
                    'success_label' => 'Template Loaded',
                    'error_label'   => 'Load Failed',
                    'class'         => 'hpc-button secondary',
                    'render_assets' => false,
                    'attrs'         => [ 'data-gsc-load-template' => true ],
                ]
            ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
            <span class="hpc-gsc-template-status" data-gsc-template-status data-state="loaded"><?php echo esc_html( $current instanceof GettingStartedChecklistTemplate ? $current->label . ' loaded' : 'Template ready' ); ?></span>
            <?php if ( $current instanceof GettingStartedChecklistTemplate && '' !== $current->description ) : ?>
                <small data-gsc-template-description><?php echo esc_html( $current->description ); ?></small>
            <?php else : ?>
                <small data-gsc-template-description></small>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array<string,GettingStartedChecklistTemplate> $templates
     */
    private function template_store_html( array $templates ): string {
        if ( [] === $templates ) {
            return '';
        }

        ob_start();
        ?>
        <div hidden data-gsc-template-store>
            <?php foreach ( $templates as $template ) : ?>
                <template data-gsc-template-source="<?php echo esc_attr( $template->id ); ?>" data-template-label="<?php echo esc_attr( $template->label ); ?>" data-template-description="<?php echo esc_attr( $template->description ); ?>" data-step-count="<?php echo esc_attr( (string) count( $template->steps ) ); ?>">
                    <?php foreach ( $template->steps as $step ) : ?>
                        <?php echo $this->step_html( $step ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endforeach; ?>
                </template>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function step_html( GettingStartedChecklistStep $step ): string {
        $subtasks = $step->subtasks;
        $has_subtasks = [] !== $subtasks;
        $show_type_badges = $this->config->show_type_badges();

        ob_start();
        ?>
        <?php if ( $has_subtasks ) : ?>
            <details class="hpc-gsc-step hpc-gsc-step-parent" data-gsc-step-card data-step-id="<?php echo esc_attr( $step->id ); ?>" open>
                <summary class="hpc-gsc-row hpc-gsc-step-row" data-gsc-item data-gsc-step-row data-step-id="<?php echo esc_attr( $step->id ); ?>" data-subtask-id="" data-request-type="<?php echo esc_attr( $step->type ); ?>" data-mutating="<?php echo $step->mutating ? '1' : '0'; ?>" data-has-action="<?php echo $step->has_callback() ? '1' : '0'; ?>" data-has-subtasks="1" data-has-required-inputs="<?php echo [] !== $step->required_inputs ? '1' : '0'; ?>" data-batch-enabled="<?php echo $step->batch_enabled ? '1' : '0'; ?>" data-destructive="<?php echo $step->destructive ? '1' : '0'; ?>" data-status="pending">
                    <?php echo $this->status_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <div class="hpc-gsc-main">
                        <div class="hpc-gsc-title-line">
                            <strong><?php echo esc_html( $step->label ); ?></strong>
                            <?php if ( $show_type_badges ) : ?>
                                <span class="hpc-gsc-type"><?php echo esc_html( $this->type_label( $step->type ) ); ?></span>
                            <?php endif; ?>
                            <?php echo $this->safety_badges( $step->destructive, $step->batch_enabled ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <span class="hpc-gsc-state" data-gsc-state>Pending</span>
                        </div>
                        <?php if ( '' !== $step->description ) : ?>
                            <p><?php echo esc_html( $step->description ); ?></p>
                        <?php endif; ?>
                        <?php echo $this->required_inputs_html( $step->required_inputs, $step->id, '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <div class="hpc-gsc-report" data-gsc-report hidden></div>
                    </div>
                    <div class="hpc-gsc-row-action">
                        <span class="hpc-gsc-section-toggle" aria-hidden="true"><svg viewBox="0 0 512 512" focusable="false"><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"></path></svg></span>
                        <?php echo DynamicButton::render( [ 'label' => $step->action_label . ' Step', 'working_label' => 'Running...', 'success_label' => 'Done', 'error_label' => 'Failed', 'class' => 'hpc-button secondary', 'attrs' => [ 'data-gsc-run-step' => true, 'data-step-id' => $step->id ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </summary>
                <div class="hpc-gsc-subtasks" data-gsc-subtasks="<?php echo esc_attr( $step->id ); ?>">
                    <?php foreach ( $subtasks as $subtask ) : ?>
                        <div class="hpc-gsc-row hpc-gsc-subtask-row" data-gsc-item data-gsc-filter-item data-hpc-filter-text="<?php echo esc_attr( $this->filter_text( $step, $subtask ) ); ?>" data-gsc-subtask-row data-step-id="<?php echo esc_attr( $step->id ); ?>" data-subtask-id="<?php echo esc_attr( $subtask->id ); ?>" data-request-type="<?php echo esc_attr( $subtask->type ); ?>" data-mutating="<?php echo $subtask->mutating ? '1' : '0'; ?>" data-has-action="<?php echo $subtask->has_callback() ? '1' : '0'; ?>" data-has-required-inputs="<?php echo [] !== $subtask->required_inputs ? '1' : '0'; ?>" data-batch-enabled="<?php echo $subtask->batch_enabled ? '1' : '0'; ?>" data-destructive="<?php echo $subtask->destructive ? '1' : '0'; ?>" data-status="pending">
                            <?php echo $this->status_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <div class="hpc-gsc-main">
                                <div class="hpc-gsc-title-line">
                                    <strong><?php echo esc_html( $subtask->label ); ?></strong>
                                    <?php if ( $show_type_badges ) : ?>
                                        <span class="hpc-gsc-type"><?php echo esc_html( $this->type_label( $subtask->type ) ); ?></span>
                                    <?php endif; ?>
                                    <?php echo $this->safety_badges( $subtask->destructive, $subtask->batch_enabled ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    <span class="hpc-gsc-state" data-gsc-state>Pending</span>
                                </div>
                                <?php if ( '' !== $subtask->description ) : ?>
                                    <p><?php echo esc_html( $subtask->description ); ?></p>
                                <?php endif; ?>
                                <?php echo $this->required_inputs_html( $subtask->required_inputs, $step->id, $subtask->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <div class="hpc-gsc-report" data-gsc-report hidden></div>
                            </div>
                            <div class="hpc-gsc-row-action">
                                <?php echo DynamicButton::render( [ 'label' => $subtask->action_label, 'working_label' => 'Running...', 'success_label' => 'Done', 'error_label' => 'Failed', 'class' => 'hpc-button secondary', 'attrs' => [ 'data-gsc-run-item' => true, 'data-step-id' => $step->id, 'data-subtask-id' => $subtask->id ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php else : ?>
            <div class="hpc-gsc-step hpc-gsc-step-single" data-gsc-filter-item data-hpc-filter-text="<?php echo esc_attr( $this->filter_text( $step ) ); ?>" data-gsc-step-card data-step-id="<?php echo esc_attr( $step->id ); ?>">
                <div class="hpc-gsc-row hpc-gsc-step-row" data-gsc-item data-gsc-step-row data-step-id="<?php echo esc_attr( $step->id ); ?>" data-subtask-id="" data-request-type="<?php echo esc_attr( $step->type ); ?>" data-mutating="<?php echo $step->mutating ? '1' : '0'; ?>" data-has-action="<?php echo $step->has_callback() ? '1' : '0'; ?>" data-has-subtasks="0" data-has-required-inputs="<?php echo [] !== $step->required_inputs ? '1' : '0'; ?>" data-batch-enabled="<?php echo $step->batch_enabled ? '1' : '0'; ?>" data-destructive="<?php echo $step->destructive ? '1' : '0'; ?>" data-status="pending">
                    <?php echo $this->status_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <div class="hpc-gsc-main">
                        <div class="hpc-gsc-title-line">
                            <strong><?php echo esc_html( $step->label ); ?></strong>
                            <?php if ( $show_type_badges ) : ?>
                                <span class="hpc-gsc-type"><?php echo esc_html( $this->type_label( $step->type ) ); ?></span>
                            <?php endif; ?>
                            <?php echo $this->safety_badges( $step->destructive, $step->batch_enabled ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <span class="hpc-gsc-state" data-gsc-state>Pending</span>
                        </div>
                        <?php if ( '' !== $step->description ) : ?>
                            <p><?php echo esc_html( $step->description ); ?></p>
                        <?php endif; ?>
                        <?php echo $this->required_inputs_html( $step->required_inputs, $step->id, '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <div class="hpc-gsc-report" data-gsc-report hidden></div>
                    </div>
                    <div class="hpc-gsc-row-action">
                        <?php echo DynamicButton::render( [ 'label' => $step->action_label, 'working_label' => 'Running...', 'success_label' => 'Done', 'error_label' => 'Failed', 'class' => 'hpc-button secondary', 'attrs' => [ 'data-gsc-run-step' => true, 'data-step-id' => $step->id ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php
        return (string) ob_get_clean();
    }

    private function filter_text( GettingStartedChecklistStep $step, ?GettingStartedChecklistSubtask $subtask = null ): string {
        $parts = [ $step->label, $step->description, $step->id, $step->type ];

        if ( $subtask instanceof GettingStartedChecklistSubtask ) {
            $parts = array_merge( $parts, [ $subtask->label, $subtask->description, $subtask->id, $subtask->type ] );
        }

        return trim( implode( ' ', array_filter( $parts, static fn( string $part ): bool => '' !== trim( $part ) ) ) );
    }

    private function safety_badges( bool $destructive, bool $batch_enabled ): string {
        $html = '';
        if ( $destructive ) {
            $html .= '<span class="hpc-gsc-safety destructive">Destructive</span>';
        }
        if ( ! $batch_enabled ) {
            $html .= '<span class="hpc-gsc-safety individual">Individual only</span>';
        }
        return $html;
    }

    /**
     * @param array<int,array<string,mixed>> $inputs
     */
    private function required_inputs_html( array $inputs, string $step_id, string $subtask_id ): string {
        if ( [] === $inputs ) {
            return '';
        }

        ob_start();
        ?>
        <div class="hpc-gsc-inputs" data-gsc-inputs>
            <?php foreach ( $inputs as $input ) : ?>
                <?php
                $id          = (string) ( $input['id'] ?? '' );
                $label       = (string) ( $input['label'] ?? $id );
                $type           = (string) ( $input['type'] ?? 'text' );
                $field_type     = 'confirmation' === $type ? 'text' : $type;
                $required       = (bool) ( $input['required'] ?? true );
                $description    = (string) ( $input['description'] ?? '' );
                $confirm_text   = (string) ( $input['confirm_text'] ?? '' );
                $case_sensitive = (bool) ( $input['case_sensitive'] ?? true );
                $field_id       = 'hpc-gsc-input-' . $step_id . ( '' !== $subtask_id ? '-' . $subtask_id : '' ) . '-' . $id;

                if ( '' === $id ) {
                    continue;
                }
                ?>
                <label class="hpc-gsc-input-field" for="<?php echo esc_attr( $field_id ); ?>">
                    <span>
                        <?php echo esc_html( $label ); ?>
                        <?php if ( $required ) : ?>
                            <em>Required</em>
                        <?php endif; ?>
                    </span>
                    <input
                        id="<?php echo esc_attr( $field_id ); ?>"
                        type="<?php echo esc_attr( $field_type ); ?>"
                        value="<?php echo esc_attr( (string) ( $input['value'] ?? '' ) ); ?>"
                        placeholder="<?php echo esc_attr( (string) ( $input['placeholder'] ?? '' ) ); ?>"
                        <?php echo $required ? 'required' : ''; ?>
                        <?php echo '' !== (string) ( $input['pattern'] ?? '' ) ? 'pattern="' . esc_attr( (string) $input['pattern'] ) . '"' : ''; ?>
                        <?php echo '' !== (string) ( $input['min'] ?? '' ) ? 'min="' . esc_attr( (string) $input['min'] ) . '"' : ''; ?>
                        <?php echo '' !== (string) ( $input['max'] ?? '' ) ? 'max="' . esc_attr( (string) $input['max'] ) . '"' : ''; ?>
                        <?php echo '' !== (string) ( $input['step'] ?? '' ) ? 'step="' . esc_attr( (string) $input['step'] ) . '"' : ''; ?>
                        <?php echo '' !== (string) ( $input['autocomplete'] ?? '' ) ? 'autocomplete="' . esc_attr( (string) $input['autocomplete'] ) . '"' : ''; ?>
                        data-gsc-input
                        data-input-id="<?php echo esc_attr( $id ); ?>"
                        data-input-type="<?php echo esc_attr( $type ); ?>"
                        data-input-label="<?php echo esc_attr( $label ); ?>"
                        data-confirm-text="<?php echo esc_attr( $confirm_text ); ?>"
                        data-case-sensitive="<?php echo $case_sensitive ? '1' : '0'; ?>"
                    >
                    <?php if ( '' !== $description ) : ?>
                        <small><?php echo esc_html( $description ); ?></small>
                    <?php endif; ?>
                    <small class="hpc-gsc-input-error" data-gsc-input-error></small>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function status_icon(): string {
        return '<span class="hpc-gsc-status-icon" aria-hidden="true">'
            . '<span class="hpc-gsc-icon-pending"></span>'
            . '<span class="hpc-gsc-icon-spinner"></span>'
            . '<span class="hpc-gsc-icon-check"><svg viewBox="0 0 512 512" focusable="false"><path d="M470.6 105.4c12.5 12.5 12.5 32.8 0 45.3l-256 256c-12.5 12.5-32.8 12.5-45.3 0l-128-128c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0L192 338.7 425.4 105.4c12.5-12.5 32.8-12.5 45.2 0z"></path></svg></span>'
            . '<span class="hpc-gsc-icon-x"><svg viewBox="0 0 384 512" focusable="false"><path d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3l105.4 105.3c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256l105.3-105.4z"></path></svg></span>'
            . '</span>';
    }

    private function type_label( string $type ): string {
        return match ( $type ) {
            GettingStartedChecklistStep::TYPE_STATUS_CHECK => 'Status Check',
            GettingStartedChecklistStep::TYPE_SETUP_ACTION => 'Setup Action',
            GettingStartedChecklistStep::TYPE_FEATURE_TOGGLE => 'Feature Toggle',
            GettingStartedChecklistStep::TYPE_CONFIG_MUTATION => 'Config Mutation',
            GettingStartedChecklistStep::TYPE_AJAX_REQUEST => 'AJAX Request',
            GettingStartedChecklistStep::TYPE_CUSTOM => 'Custom',
            default => 'Callback',
        };
    }

    /**
     * @param array<string,mixed> $entry
     */
    private function log_row( array $entry ): string {
        $level   = strtolower( (string) ( $entry['level'] ?? 'info' ) );
        $context = isset( $entry['context'] ) && is_array( $entry['context'] ) && [] !== $entry['context']
            ? wp_json_encode( $entry['context'], JSON_PRETTY_PRINT )
            : '';

        return '<div class="hpc-gsc-log-row">'
            . '<div class="hpc-gsc-log-time">' . esc_html( (string) ( $entry['time'] ?? '' ) ) . '</div>'
            . '<div><span class="hpc-gsc-log-level ' . esc_attr( $level ) . '">' . esc_html( $level ) . '</span></div>'
            . '<div><div class="hpc-gsc-log-message">' . esc_html( (string) ( $entry['message'] ?? '' ) ) . '</div>'
            . ( '' !== $context ? '<div class="hpc-gsc-log-context">' . esc_html( $context ) . '</div>' : '' )
            . '</div></div>';
    }
}
