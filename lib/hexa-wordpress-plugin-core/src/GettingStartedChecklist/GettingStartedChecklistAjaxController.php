<?php

namespace Hexa\PluginCore\GettingStartedChecklist;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxFailure;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;

final class GettingStartedChecklistAjaxController implements ModuleInterface {
    private GettingStartedChecklistConfig $config;

    private GettingStartedChecklistRunner $runner;

    private GettingStartedChecklistStateStore $state_store;

    public function __construct( GettingStartedChecklistConfig|array $config ) {
        $this->config = is_array( $config ) ? new GettingStartedChecklistConfig( $config ) : $config;
        $this->runner = new GettingStartedChecklistRunner( $this->config );
        $this->state_store = new GettingStartedChecklistStateStore( $this->config );
    }

    public function register(): void {
        AjaxActionRegistry::create(
            [
                'capability'   => $this->config->capability(),
                'nonce_action' => $this->config->nonce_action(),
                'nonce_field'  => $this->config->nonce_field(),
            ]
        )->register(
            [
                $this->config->run_action() => [
                    'callback' => [ $this, 'run_item' ],
                ],
                $this->config->status_action() => [
                    'callback' => [ $this, 'status' ],
                ],
                $this->config->reset_action() => [
                    'callback' => [ $this, 'reset' ],
                ],
            ]
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function run_item( AjaxRequest $request ): array {
        $inputs      = $request->raw( 'inputs', [], 'post' );
        $step_id     = $this->raw_identifier( $request, 'step_id' );
        $subtask_id  = $this->raw_identifier( $request, 'subtask_id' );
        $template_id = $this->raw_identifier( $request, 'template_id' );
        $authorization = $this->runner->authorize_item( $step_id, $subtask_id, $template_id );

        if ( ! $authorization['allowed'] ) {
            throw new AjaxFailure(
                (string) $authorization['message'],
                (int) $authorization['status_code'],
                (string) $authorization['code']
            );
        }

        return $this->runner->run_item(
            $step_id,
            $subtask_id,
            is_array( $inputs ) ? $inputs : [],
            $template_id
        );
    }

    /** @return array<string,mixed> */
    public function status( AjaxRequest $request ): array {
        return $this->state_store->status( $this->matched_template_id( $request ) );
    }

    /** @return array<string,mixed> */
    public function reset( AjaxRequest $request ): array {
        return $this->state_store->reset( $this->matched_template_id( $request ) );
    }

    private function matched_template_id( AjaxRequest $request ): string {
        $template_id = $this->raw_identifier( $request, 'template_id' );
        $matched     = $this->config->match_template_id( $template_id );
        if ( null === $matched ) {
            throw AjaxFailure::not_found( 'Unknown checklist template.', 'unknown_checklist_template' );
        }

        return $matched;
    }

    private function raw_identifier( AjaxRequest $request, string $key ): string {
        $value = $request->raw( $key, '', 'post' );
        return is_scalar( $value ) ? (string) $value : "\0invalid";
    }
}
