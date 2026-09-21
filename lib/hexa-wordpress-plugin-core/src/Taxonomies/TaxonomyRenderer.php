<?php

namespace Hexa\PluginCore\Taxonomies;

use Hexa\PluginCore\WpAdminComponents\CoreUi;

final class TaxonomyRenderer {
    public function render( TaxonomyRegistry $registry, array $args = [] ): string {
        ob_start();
        CoreUi::render_assets();
        $assets = (string) ob_get_clean();
        $title = (string) ( $args['title'] ?? 'Taxonomies' );
        $description = (string) ( $args['description'] ?? 'Taxonomies registered through Hexa WP Core.' );
        $persist = sanitize_key( (string) ( $args['persist_prefix'] ?? 'hexa-taxonomies' ) );
        $cards = '';
        foreach ( $registry->resolved_definitions() as $definition ) {
            $registered = function_exists( 'taxonomy_exists' ) && taxonomy_exists( $definition['taxonomy'] );
            $meta = CoreUi::pill( ! empty( $definition['enabled'] ) ? 'Enabled' : 'Disabled', ! empty( $definition['enabled'] ) ? 'success' : 'warning' )
                . CoreUi::pill( $registered ? 'Registered' : 'Not registered', $registered ? 'success' : ( ! empty( $definition['enabled'] ) ? 'danger' : 'warning' ) );
            $body = '<p>' . esc_html( $definition['description'] ) . '</p>'
                . '<dl class="hpc-taxonomy-facts"><div><dt>Owner</dt><dd>' . esc_html( $definition['owner'] ?: 'Host plugin' ) . '</dd></div>'
                . '<div><dt>Taxonomy key</dt><dd><span class="hpc-code">' . esc_html( $definition['taxonomy'] ) . '</span></dd></div>'
                . '<div><dt>Content types</dt><dd>' . esc_html( implode( ', ', $definition['object_types'] ) ?: 'None' ) . '</dd></div></dl>';
            $cards .= CoreUi::collapsible(
                [
                    'title' => $definition['label'], 'body_html' => $body, 'meta_html' => $meta,
                    'open' => false, 'persist_key' => $persist . '-' . $definition['id'], 'query_state' => false,
                ]
            );
        }
        return $assets
            . '<style>.hpc-taxonomy-intro{margin-bottom:14px}.hpc-taxonomy-facts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.hpc-taxonomy-facts div{background:#f8fafc;border:1px solid #e3e8f0;border-radius:8px;padding:9px 10px}.hpc-taxonomy-facts dt{color:#65758b;font-size:11px;font-weight:800;text-transform:uppercase}.hpc-taxonomy-facts dd{margin:4px 0 0;overflow-wrap:anywhere}@media(max-width:800px){.hpc-taxonomy-facts{grid-template-columns:1fr}}</style>'
            . '<div class="hpc-ui hpc-taxonomies"><section class="hpc-card hpc-taxonomy-intro"><h3>' . esc_html( $title ) . '</h3><p>' . esc_html( $description ) . '</p></section><div class="hpc-stack">' . $cards . '</div></div>';
    }
}
