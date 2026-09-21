<?php

namespace Hexa\PluginCore\SnippetRegistry;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxFailure;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;

final class SnippetAjaxController implements ModuleInterface {
    private SnippetRegistry $registry;
    private array $config;

    public function __construct( SnippetRegistry $registry, array $config = [] ) {
        $this->registry = $registry;
        $this->config   = array_merge(
            [
                "capability"    => "manage_options",
                "nonce_action"  => "",
                "nonce_field"   => "nonce",
                "toggle_action" => "hexa_snippet_toggle",
                "test_action"   => "hexa_snippet_test",
            ],
            $config
        );
    }

    public function register(): void {
        if ( ! class_exists( AjaxActionRegistry::class ) ) {
            return;
        }

        ( new AjaxActionRegistry(
            [
                "capability"   => (string) $this->config["capability"],
                "nonce_action" => (string) $this->config["nonce_action"],
                "nonce_field"  => (string) $this->config["nonce_field"],
            ]
        ) )->register(
            [
                (string) $this->config["toggle_action"] => [
                    "callback" => [ $this, "toggle" ],
                ],
                (string) $this->config["test_action"] => [
                    "callback" => [ $this, "test" ],
                ],
            ]
        );
    }

    public function toggle( AjaxRequest $request ): array {
        $snippet_id = $request->text( "snippet_id", "", "post" );
        $enabled    = $request->bool( "enable", false, "post" );

        if ( "" === $snippet_id || ! $this->registry->has( $snippet_id ) ) {
            throw AjaxFailure::bad_request( "Invalid snippet ID." );
        }

        if ( ! $this->registry->set_enabled( $snippet_id, $enabled ) ) {
            throw AjaxFailure::bad_request( "Snippet could not be updated." );
        }

        return [
            "snippet_id" => $snippet_id,
            "enabled"    => $enabled,
            "message"    => $enabled ? "Snippet enabled." : "Snippet disabled.",
            "test"       => $this->registry->test( $snippet_id ),
        ];
    }

    public function test( AjaxRequest $request ): array {
        $snippet_id = $request->text( "snippet_id", "", "post" );

        if ( "" === $snippet_id || ! $this->registry->has( $snippet_id ) ) {
            throw AjaxFailure::bad_request( "Invalid snippet ID." );
        }

        return $this->registry->test( $snippet_id );
    }
}
