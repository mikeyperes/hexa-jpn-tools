<?php

namespace Hexa\PluginCore\WpAdminUiCleanup;

use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxFailure;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;

final class CleanupAjaxController {
    private CleanupRegistry $registry;

    public function __construct( CleanupRegistry $registry ) {
        $this->registry = $registry;
    }

    public function register(): void {
        ( new AjaxActionRegistry(
            [
                "capability" => $this->registry->capability(),
                "nonce_action" => $this->registry->nonce_action(),
                "nonce_field" => $this->registry->nonce_field(),
            ]
        ) )->register(
            [
                $this->registry->ajax_action() => [
                    "action" => $this->registry->ajax_action(),
                    "callback" => [ $this, "toggle" ],
                ],
            ]
        );
    }

    public function toggle( AjaxRequest $request ): array {
        $option_key = $request->key( "option" );
        $enabled = $request->bool( "enabled" );
        $option = $this->registry->option( $option_key );

        if ( ! $option ) {
            throw AjaxFailure::bad_request( "Invalid cleanup option.", "invalid_option" );
        }

        if ( ! $this->registry->update_enabled( $option->key, $enabled ) ) {
            throw AjaxFailure::server_error( "Unable to save cleanup option.", "save_failed" );
        }

        return [
            "option" => $option->key,
            "enabled" => $enabled,
            "status" => $enabled ? $option->on_label : $option->off_label,
        ];
    }
}
