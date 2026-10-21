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
        if ( 'client_view' === ( $_POST['setting'] ?? '' ) ) {
            $roles = isset( $_POST['client_roles'] ) && is_array( $_POST['client_roles'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['client_roles'] ) ) : [];
            $scope = sanitize_key( (string) wp_unslash( $_POST['client_scope'] ?? '' ) );
            $setting = $this->registry->store()->save_client_view( $definition, [
                'enabled' => ! empty( $_POST['client_enabled'] ),
                'hide'    => ! empty( $_POST['client_hide'] ),
                'roles'   => 'roles' === $scope && [] !== $roles ? $roles : \Hexa\PluginCore\Fields\Audience::BELOW_ADMIN,
            ] );
            wp_send_json_success( [ 'field_group_id' => $id, 'client_view' => $setting, 'message' => 'Client view saved. Reload the profile or editor to see it.' ] );
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
