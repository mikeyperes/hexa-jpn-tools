<?php

namespace Hexa\PluginCore\CredentialVault;

use Hexa\PluginCore\WpAdminComponents\CoreUi;

final class CredentialFieldRenderer {
    public function render_example( array $args = [] ): string {
        $slug      = isset( $args['slug'] ) ? (string) $args['slug'] : 'openai';
        $key_name  = isset( $args['key_name'] ) ? (string) $args['key_name'] : 'api_key';
        $label     = isset( $args['label'] ) ? (string) $args['label'] : 'OpenAI API Key';
        $provider  = isset( $args['provider'] ) ? (string) $args['provider'] : 'OpenAI';
        $masked    = isset( $args['masked'] ) ? (string) $args['masked'] : '********A1b2';
        $store     = new CredentialStore();
        $option    = $store->option_key( $slug, $key_name );
        $steps     = isset( $args['steps'] ) && is_array( $args['steps'] ) ? $args['steps'] : [
            'Open the provider dashboard.',
            'Create or copy an API key.',
            'Paste the key into this field and save.',
            'Run a package-specific test before enabling automation.',
        ];

        $step_html = '<ol class="hpc-steps">';
        foreach ( $steps as $step ) {
            $step_html .= '<li>' . esc_html( (string) $step ) . '</li>';
        }
        $step_html .= '</ol>';

        return '<div class="hpc-credential-demo">'
            . '<div class="hpc-credential-head">'
            . '<div><h4>' . esc_html( $label ) . '</h4><p>Encrypted credential for ' . esc_html( $provider ) . '.</p></div>'
            . CoreUi::pill( 'Configured', 'success' )
            . '</div>'
            . '<label class="hpc-field"><span>Credential value</span><input type="password" value="' . esc_attr( $masked ) . '" readonly></label>'
            . '<div class="hpc-actions"><button type="button" class="hpc-button">Save key</button><button type="button" class="hpc-button secondary">Test key</button><button type="button" class="hpc-button danger">Delete</button></div>'
            . '<p class="hpc-small">Option key: <span class="hpc-code">' . esc_html( $option ) . '</span></p>'
            . '<div class="hpc-callout"><strong>Setup steps</strong>' . $step_html . '</div>'
            . '</div>';
    }
}
