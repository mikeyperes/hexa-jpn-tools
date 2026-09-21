<?php

namespace Hexa\PluginCore\SchemaTools;

use Hexa\PluginCore\WpAdminComponents\CoreUi;

final class SchemaDashboardRenderer {
    public function render( array $args ): string {
        ob_start();
        CoreUi::render_assets();
        $assets = (string) ob_get_clean();

        $title       = (string) ( $args['title'] ?? 'Schema' );
        $kicker      = (string) ( $args['kicker'] ?? 'Schema' );
        $description = (string) ( $args['description'] ?? '' );
        $cards       = isset( $args['status_cards'] ) && is_array( $args['status_cards'] ) ? $args['status_cards'] : [];
        $actions     = isset( $args['actions'] ) && is_array( $args['actions'] ) ? $args['actions'] : [];
        $sections    = isset( $args['integrity_sections'] ) && is_array( $args['integrity_sections'] ) ? $args['integrity_sections'] : [];
        $graphs      = isset( $args['graphs'] ) && is_array( $args['graphs'] ) ? $args['graphs'] : [];
        $shortcode   = isset( $args['shortcode_card'] ) && is_array( $args['shortcode_card'] ) ? $args['shortcode_card'] : [];
        $html_sections = isset( $args['html_sections'] ) && is_array( $args['html_sections'] ) ? $args['html_sections'] : [];
        $scan_html   = (string) ( $args['scan_report_html'] ?? '' );

        ob_start();
        ?>
        <div class="hpc-ui hpc-schema-dashboard">
            <?php echo $assets; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <section class="hpc-hero">
                <div>
                    <p class="hpc-schema-kicker"><?php echo esc_html( $kicker ); ?></p>
                    <h2><?php echo esc_html( $title ); ?></h2>
                    <?php if ( '' !== $description ) : ?><p><?php echo esc_html( $description ); ?></p><?php endif; ?>
                </div>
                <?php if ( $actions ) : ?>
                    <div class="hpc-actions">
                        <?php foreach ( $actions as $action ) : ?>
                            <?php
                            $url   = (string) ( $action['url'] ?? '' );
                            $label = (string) ( $action['label'] ?? 'Open' );
                            ?>
                            <?php if ( '' !== $url ) : ?><?php echo CoreUi::external_link( $url, $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ( $cards ) : ?>
                <div class="hpc-grid">
                    <?php foreach ( $cards as $card ) : ?>
                        <?php echo $this->status_card( $card ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ( $shortcode ) : ?>
                <?php echo $this->shortcode_card( $shortcode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>

            <?php foreach ( $html_sections as $html_section ) : ?>
                <section class="hpc-card hpc-schema-dashboard-section">
                    <?php echo (string) $html_section; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </section>
            <?php endforeach; ?>

            <?php foreach ( $sections as $section ) : ?>
                <?php echo $this->integrity_section( $section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endforeach; ?>

            <?php if ( $graphs ) : ?>
                <div class="hpc-grid two">
                    <?php foreach ( $graphs as $graph ) : ?>
                        <?php echo $this->graph_panel( $graph ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ( '' !== $scan_html ) : ?>
                <section class="hpc-schema-dashboard-section">
                    <?php echo $scan_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </section>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function status_card( array $card ): string {
        $ok    = ! empty( $card['ok'] );
        $title = (string) ( $card['title'] ?? '' );
        $body  = (string) ( $card['body'] ?? '' );

        return CoreUi::card(
            [
                'title'     => $title,
                'body_html' => '<p>' . esc_html( $body ) . '</p>',
                'meta_html' => CoreUi::pill( $ok ? 'PASS' : 'CHECK', $ok ? 'success' : 'warning' ),
            ]
        );
    }

    private function shortcode_card( array $card ): string {
        $title       = (string) ( $card['title'] ?? 'Shortcode' );
        $description = (string) ( $card['description'] ?? '' );
        $shortcode   = (string) ( $card['shortcode'] ?? '' );
        $body        = '';

        if ( '' !== $description ) {
            $body .= '<p>' . esc_html( $description ) . '</p>';
        }
        if ( '' !== $shortcode ) {
            $body .= '<div class="hpc-schema-shortcode-row"><code>' . esc_html( $shortcode ) . '</code>' . CoreUi::copy_button( $shortcode ) . '</div>';
        }

        return CoreUi::collapsible(
            [
                'title'     => $title,
                'body_html' => $body,
                'open'      => ! empty( $card['open'] ),
            ]
        );
    }

    private function integrity_section( array $section ): string {
        $title  = (string) ( $section['title'] ?? 'Integrity' );
        $checks = isset( $section['checks'] ) && is_array( $section['checks'] ) ? $section['checks'] : [];

        $rows = '';
        foreach ( $checks as $check ) {
            $status = (string) ( $check['status'] ?? 'yellow' );
            $label  = (string) ( $check['label'] ?? '' );
            $class  = 'green' === $status ? 'is-ok' : ( 'red' === $status ? 'is-bad' : 'is-warn' );
            $rows  .= '<tr><th>' . esc_html( $label ) . '</th><td><span class="' . esc_attr( $class ) . '">' . esc_html( strtoupper( $status ) ) . '</span></td></tr>';
        }

        return '<section class="hpc-card hpc-schema-dashboard-section"><h3>' . esc_html( $title ) . '</h3><table class="widefat striped"><tbody>' . $rows . '</tbody></table></section>';
    }

    private function graph_panel( array $graph ): string {
        $title  = (string) ( $graph['title'] ?? 'Schema graph' );
        $schema = isset( $graph['schema'] ) && is_array( $graph['schema'] ) ? $graph['schema'] : [];

        return '<section class="hpc-card hpc-schema-graph-panel"><h3>' . esc_html( $title ) . '</h3><pre class="hpc-readme">' . esc_html( wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) . '</pre></section>';
    }

    private function styles(): string {
        return '<style>.hpc-schema-dashboard{display:grid;gap:16px}.hpc-schema-dashboard .hpc-hero{margin-bottom:0}.hpc-schema-kicker{font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.hpc-schema-dashboard-section table{margin-top:8px}.hpc-schema-dashboard-section th{text-align:left}.hpc-schema-dashboard .is-ok{color:var(--hpc-green);font-weight:900}.hpc-schema-dashboard .is-warn{color:var(--hpc-amber);font-weight:900}.hpc-schema-dashboard .is-bad{color:var(--hpc-red);font-weight:900}.hpc-schema-shortcode-row{align-items:center;background:#f8fafc;border:1px solid var(--hpc-line);border-radius:8px;display:flex;gap:10px;justify-content:space-between;padding:10px 12px}.hpc-schema-shortcode-row code{white-space:normal;word-break:break-word}.hpc-schema-graph-panel pre{max-height:440px}</style>';
    }
}
