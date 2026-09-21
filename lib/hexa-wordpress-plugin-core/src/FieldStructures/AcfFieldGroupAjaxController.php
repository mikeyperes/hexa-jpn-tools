<?php

namespace Hexa\PluginCore\FieldStructures;

final class AcfFieldGroupAjaxController {
    public function __construct( private AcfFieldGroupRegistry $registry, private array $config ) {
    }

    public function register(): void {
        $action = sanitize_key( (string) ( $this->config['ajax_action'] ?? '' ) );
        if ( '' !== $action ) {
            add_action( 'wp_ajax_' . $action, [ $this, 'save' ] );
        }
    }

    public function save(): void {
        if ( ! current_user_can( (string) ( $this->config['capability'] ?? 'manage_options' ) ) ) {
            wp_send_json_error( [ 'message' => 'You do not have permission to change ACF structures.' ], 403 );
        }
        check_ajax_referer( (string) $this->config['nonce_action'], (string) ( $this->config['nonce_field'] ?? 'nonce' ) );
        $id = isset( $_POST['field_group_id'] ) ? sanitize_key( (string) wp_unslash( $_POST['field_group_id'] ) ) : '';
        $definition = $this->registry->definition( $id );
        if ( ! $definition ) {
            wp_send_json_error( [ 'message' => 'Unknown ACF field group.' ], 404 );
        }
        $enabled = ! empty( $_POST['enabled'] );
        $this->registry->store()->save( $definition, $enabled );
        $after_save = $this->registry->config( 'after_save' );
        if ( is_callable( $after_save ) ) {
            call_user_func( $after_save, $definition, $enabled, $this->registry );
        }
        wp_send_json_success( [ 'field_group_id' => $id, 'enabled' => $enabled, 'message' => 'ACF structure saved. Reload the target editor to apply the change.' ] );
    }
}
