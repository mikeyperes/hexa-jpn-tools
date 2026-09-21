<?php

namespace Hexa\PluginCore\FieldStructures;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\WpAdminComponents\CoreUi;

final class AcfSettingsPanel implements ModuleInterface {
    private array $config;

    public function __construct( array $config ) {
        $this->config = array_merge(
            [
                'page_slug' => '', 'tab' => '', 'post_id' => 'option', 'field_groups' => [],
                'title' => 'Settings', 'description' => '', 'submit_value' => 'Save Settings',
                'updated_message' => 'Settings saved.', 'persist_key' => '', 'open' => true,
            ],
            $config
        );
    }

    public function register(): void {
        add_action( 'admin_init', [ $this, 'prepare_form' ], 1 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    public function prepare_form(): void {
        if ( $this->is_host_page() && function_exists( 'acf_form_head' ) ) {
            acf_form_head();
        }
    }

    public function enqueue(): void {
        if ( $this->is_host_page() && function_exists( 'acf_enqueue_scripts' ) ) {
            acf_enqueue_scripts();
        }
    }

    public function render(): string {
        ob_start();
        CoreUi::render_assets();
        $assets = (string) ob_get_clean();
        if ( ! function_exists( 'acf_form' ) ) {
            return $assets . CoreUi::card( [ 'title' => (string) $this->config['title'], 'body_html' => '<p>Advanced Custom Fields Pro is required for these settings.</p>' ] );
        }
        ob_start();
        acf_form(
            [
                'post_id' => $this->config['post_id'], 'field_groups' => array_values( (array) $this->config['field_groups'] ),
                'form' => true, 'submit_value' => (string) $this->config['submit_value'],
                'updated_message' => (string) $this->config['updated_message'], 'html_submit_button' => '<input type="submit" class="button button-primary" value="%s">',
            ]
        );
        $form = (string) ob_get_clean();
        $body = ( '' !== (string) $this->config['description'] ? '<p>' . esc_html( (string) $this->config['description'] ) . '</p>' : '' ) . $form . $this->append_script();
        return $assets . CoreUi::collapsible(
            [
                'title' => (string) $this->config['title'], 'body_html' => $body, 'open' => ! empty( $this->config['open'] ),
                'persist_key' => (string) $this->config['persist_key'], 'query_state' => false,
            ]
        );
    }

    private function is_host_page(): bool {
        if ( ! is_admin() ) {
            return false;
        }

        $page = isset( $_REQUEST['page'] ) ? sanitize_key( (string) wp_unslash( $_REQUEST['page'] ) ) : '';
        if ( '' === (string) $this->config['page_slug'] || $page !== (string) $this->config['page_slug'] ) {
            return false;
        }

        $configured_tab = sanitize_key( (string) $this->config['tab'] );
        if ( '' === $configured_tab ) {
            return true;
        }

        $requested_tab = isset( $_REQUEST['tab'] ) ? sanitize_key( (string) wp_unslash( $_REQUEST['tab'] ) ) : '';
        return $requested_tab === $configured_tab;
    }

    private function append_script(): string {
        return '<script>(function(){if(!window.acf||!window.jQuery)return;var panel=document.currentScript.closest(".hpc-section-body");if(panel)window.acf.doAction("append",window.jQuery(panel));})();</script>';
    }
}
