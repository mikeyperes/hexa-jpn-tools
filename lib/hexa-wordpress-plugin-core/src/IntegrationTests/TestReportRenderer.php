<?php

namespace Hexa\PluginCore\IntegrationTests;

use Hexa\PluginCore\WpAdminComponents\CoreUi;

final class TestReportRenderer {
    /** @param array<string,mixed> $report */
    public function render( array $report, array $args = [] ): string {
        ob_start();
        CoreUi::render_assets();
        $assets = (string) ob_get_clean();
        $groups = [];
        foreach ( (array) ( $report['results'] ?? [] ) as $result ) {
            if ( is_array( $result ) ) {
                $groups[ (string) ( $result['group'] ?? 'General' ) ][] = $result;
            }
        }
        $url      = (string) ( $args['url'] ?? TestEndpointController::url() );
        $json_url = function_exists( 'add_query_arg' ) ? add_query_arg( 'format', 'json', $url ) : $url . '&format=json';
        $passed   = (int) ( $report['passed'] ?? 0 );
        $failed   = (int) ( $report['failed'] ?? 0 );
        $status   = 'pass' === (string) ( $report['status'] ?? '' );

        ob_start();
        ?>
        <?php echo $assets; ?>
        <?php echo $this->assets(); ?>
        <div class="wrap hpc-ui hpc-test-report" data-hpc-test-report>
            <div class="hpc-test-report-heading">
                <div>
                    <h1>Hexa Integration Tests</h1>
                    <p>Read-only release checks for the selected Hexa WP Core package and every active host plugin registered with it.</p>
                </div>
                <div class="hpc-actions">
                    <a class="hpc-button" href="<?php echo esc_url( $url ); ?>">Run tests again</a>
                    <a class="hpc-button secondary hpc-external" href="<?php echo esc_url( $json_url ); ?>" target="_blank" rel="noopener noreferrer">Open JSON report</a>
                </div>
            </div>

            <section class="hpc-test-summary <?php echo $status ? 'is-pass' : 'is-fail'; ?>">
                <?php echo $this->status_icon( $status ); ?>
                <div><strong><?php echo $status ? 'All integration tests passed' : 'Integration tests failed'; ?></strong><span><?php echo esc_html( $passed . ' passed, ' . $failed . ' failed, ' . (int) ( $report['total'] ?? 0 ) . ' total in ' . (string) ( $report['duration_ms'] ?? 0 ) . ' ms' ); ?></span></div>
                <time><?php echo esc_html( (string) ( $report['generated_at'] ?? '' ) ); ?></time>
            </section>

            <div class="hpc-test-endpoint"><strong>Report URL</strong><code><?php echo esc_html( $url ); ?></code></div>

            <?php if ( ! $groups ) : ?>
                <section class="hpc-card"><h3>No tests matched</h3><p>Remove the current host or test filter and run the report again.</p></section>
            <?php endif; ?>

            <?php foreach ( $groups as $group => $results ) : ?>
                <?php
                $group_failed = count( array_filter( $results, static fn( array $result ): bool => empty( $result['passed'] ) ) );
                $body = '<div class="hpc-test-list">';
                foreach ( $results as $result ) {
                    $body .= $this->result_html( $result );
                }
                $body .= '</div>';
                echo CoreUi::collapsible(
                    [
                        'title'       => $group,
                        'body_html'   => $body,
                        'meta_html'   => CoreUi::pill( $group_failed ? $group_failed . ' failed' : count( $results ) . ' passed', $group_failed ? 'danger' : 'success' ),
                        'open'        => true,
                        'persist_key' => 'integration-tests-' . sanitize_key( $group ),
                        'query_state' => false,
                    ]
                );
                ?>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $result */
    private function result_html( array $result ): string {
        $passed = ! empty( $result['passed'] );
        $details = '';
        if ( '' !== (string) ( $result['expected'] ?? '' ) || '' !== (string) ( $result['actual'] ?? '' ) ) {
            $details .= '<dl><div><dt>Expected</dt><dd>' . esc_html( (string) ( $result['expected'] ?? '' ) ) . '</dd></div><div><dt>Actual</dt><dd>' . esc_html( (string) ( $result['actual'] ?? '' ) ) . '</dd></div></dl>';
        }
        $extra = (array) ( $result['details'] ?? [] );
        if ( $extra ) {
            $details .= '<dl class="hpc-test-extra">';
            foreach ( $extra as $key => $value ) {
                $label = is_string( $key ) ? ucwords( str_replace( [ '-', '_' ], ' ', $key ) ) : 'Detail';
                $details .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( (string) $value ) . '</dd></div>';
            }
            $details .= '</dl>';
        }

        return '<article class="hpc-test-result ' . ( $passed ? 'is-pass' : 'is-fail' ) . '">'
            . $this->status_icon( $passed )
            . '<div class="hpc-test-result-main"><div class="hpc-test-result-title"><strong>' . esc_html( (string) ( $result['title'] ?? '' ) ) . '</strong><code>' . esc_html( (string) ( $result['id'] ?? '' ) ) . '</code></div>'
            . ( '' !== (string) ( $result['description'] ?? '' ) ? '<p>' . esc_html( (string) $result['description'] ) . '</p>' : '' )
            . '<p class="hpc-test-result-summary">' . esc_html( (string) ( $result['summary'] ?? '' ) ) . '</p>'
            . ( '' !== $details ? CoreUi::inline_details( 'Test details', $details, ! $passed ) : '' )
            . '</div><span class="hpc-test-duration">' . esc_html( (string) ( $result['duration_ms'] ?? 0 ) ) . ' ms</span></article>';
    }

    private function status_icon( bool $passed ): string {
        if ( $passed ) {
            return '<span class="hpc-test-icon" aria-label="Passed"><svg viewBox="0 0 512 512" aria-hidden="true" focusable="false"><path d="M256 48a208 208 0 1 0 0 416 208 208 0 0 0 0-416zm97 151L236 330a20 20 0 0 1-29 1l-53-53a20 20 0 1 1 28-28l38 38 103-115a20 20 0 1 1 30 26z"></path></svg></span>';
        }
        return '<span class="hpc-test-icon" aria-label="Failed"><svg viewBox="0 0 512 512" aria-hidden="true" focusable="false"><path d="M256 48a208 208 0 1 0 0 416 208 208 0 0 0 0-416zm74 254a20 20 0 0 1-28 28l-46-46-46 46a20 20 0 0 1-28-28l46-46-46-46a20 20 0 1 1 28-28l46 46 46-46a20 20 0 1 1 28 28l-46 46 46 46z"></path></svg></span>';
    }

    private function assets(): string {
        return <<<'HTML'
<style>
.hpc-test-report{max-width:1180px}.hpc-test-report-heading{align-items:flex-start;display:flex;gap:20px;justify-content:space-between;margin:22px 0 18px}.hpc-test-report-heading h1{font-size:24px;letter-spacing:0;margin:0 0 6px}.hpc-test-report-heading p{color:var(--hpc-muted);font-size:13px;margin:0}.hpc-test-summary{align-items:center;background:#eefaf2;border:1px solid #bfe5ca;border-left:4px solid var(--hpc-green);border-radius:8px;display:grid;gap:12px;grid-template-columns:auto minmax(0,1fr) auto;margin-bottom:12px;padding:14px 16px}.hpc-test-summary.is-fail{background:#fff1f3;border-color:#f0bec7;border-left-color:var(--hpc-red)}.hpc-test-summary strong,.hpc-test-summary span{display:block}.hpc-test-summary span{color:var(--hpc-muted);font-size:12px;margin-top:3px}.hpc-test-summary time{color:var(--hpc-muted);font-size:11px}.hpc-test-endpoint{align-items:center;background:#fff;border:1px solid var(--hpc-line);border-radius:8px;display:grid;gap:5px;grid-template-columns:auto minmax(0,1fr);margin:0 0 14px;padding:10px 12px}.hpc-test-endpoint strong{font-size:12px}.hpc-test-endpoint code{overflow-wrap:anywhere}.hpc-test-list{display:grid;gap:9px}.hpc-test-result{align-items:flex-start;background:#fbfcfe;border:1px solid #dfe5ed;border-left:4px solid var(--hpc-green);border-radius:7px;display:grid;gap:11px;grid-template-columns:auto minmax(0,1fr) auto;padding:12px}.hpc-test-result.is-fail{background:#fffafb;border-left-color:var(--hpc-red)}.hpc-test-icon{color:var(--hpc-green);display:inline-flex;height:22px;width:22px}.is-fail>.hpc-test-icon,.hpc-test-result.is-fail .hpc-test-icon{color:var(--hpc-red)}.hpc-test-icon svg{fill:currentColor;height:100%;width:100%}.hpc-test-result-title{align-items:baseline;display:flex;flex-wrap:wrap;gap:7px}.hpc-test-result-title strong{font-size:13px}.hpc-test-result-title code{color:var(--hpc-muted);font-size:10px}.hpc-test-result-main>p{font-size:12px;margin:4px 0 0}.hpc-test-result-summary{color:var(--hpc-ink)!important;font-weight:650}.hpc-test-duration{color:var(--hpc-muted);font-size:11px;white-space:nowrap}.hpc-test-result dl{display:grid;gap:7px;margin:8px 0 0}.hpc-test-result dl>div{display:grid;gap:6px;grid-template-columns:90px minmax(0,1fr)}.hpc-test-result dt{font-weight:700}.hpc-test-result dd{margin:0;overflow-wrap:anywhere}@media(max-width:700px){.hpc-test-report-heading{display:grid}.hpc-test-summary{grid-template-columns:auto minmax(0,1fr)}.hpc-test-summary time{grid-column:2}.hpc-test-endpoint{grid-template-columns:1fr}.hpc-test-result{grid-template-columns:auto minmax(0,1fr)}.hpc-test-duration{grid-column:2}}
</style>
HTML;
    }
}
