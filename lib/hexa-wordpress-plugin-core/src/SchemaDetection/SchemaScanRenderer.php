<?php

namespace Hexa\PluginCore\SchemaDetection;

use Hexa\PluginCore\WpAdminComponents\CoreUi;

final class SchemaScanRenderer {
    public function renderReport( array $scans, array $args = [] ): string {
        ob_start();
        CoreUi::render_assets();
        $core_assets = (string) ob_get_clean();

        $title    = isset( $args["title"] ) ? (string) $args["title"] : "Schema Detection Results";
        $subtitle = isset( $args["subtitle"] ) ? (string) $args["subtitle"] : "";
        $expected = isset( $args["expected"] ) && is_array( $args["expected"] ) ? $args["expected"] : [];
        $debug    = ! empty( $args["debug"] );

        $total_blocks = 0;
        $conflicts    = 0;
        $invalid      = 0;
        $semantic     = 0;
        $errors       = 0;

        foreach ( $scans as $scan ) {
            $total_blocks += (int) ( $scan["block_count"] ?? 0 );
            $conflicts    += count( (array) ( $scan["conflicts"] ?? [] ) );
            $invalid      += count( (array) ( $scan["invalid_blocks"] ?? [] ) );
            $semantic     += count( (array) ( $scan["semantic_issues"] ?? [] ) );
            $errors       += "" !== (string) ( $scan["error"] ?? "" ) ? 1 : 0;
        }

        ob_start();
        ?>
        <div class="hpc-ui hpc-schema-report">
            <?php echo $core_assets; ?>
            <?php echo $this->styles(); ?>
            <div class="hpc-schema-report-head">
                <div>
                    <h3><?php echo esc_html( $title ); ?></h3>
                    <?php if ( "" !== $subtitle ) : ?>
                        <p><?php echo esc_html( $subtitle ); ?></p>
                    <?php endif; ?>
                </div>
                <div class="hpc-schema-summary">
                    <span><?php echo esc_html( (string) count( $scans ) ); ?> page(s)</span>
                    <span><?php echo esc_html( (string) $total_blocks ); ?> JSON-LD block(s)</span>
                    <?php if ( $conflicts > 0 ) : ?><span class="is-warn"><?php echo esc_html( (string) $conflicts ); ?> conflict(s)</span><?php endif; ?>
                    <?php if ( $invalid > 0 ) : ?><span class="is-bad"><?php echo esc_html( (string) $invalid ); ?> invalid</span><?php endif; ?>
                    <?php if ( $semantic > 0 ) : ?><span class="is-bad"><?php echo esc_html( (string) $semantic ); ?> semantic issue(s)</span><?php endif; ?>
                    <?php if ( $errors > 0 ) : ?><span class="is-bad"><?php echo esc_html( (string) $errors ); ?> HTTP error(s)</span><?php endif; ?>
                </div>
            </div>
            <?php if ( ! empty( $expected ) ) : ?>
                <div class="hpc-schema-expected">
                    <strong>Expected</strong>
                    <?php foreach ( $expected as $line ) : ?>
                        <div><?php echo wp_kses_post( (string) $line ); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="hpc-schema-scans">
                <?php foreach ( $scans as $scan ) : ?>
                    <?php echo $this->renderScan( $scan, [ "debug" => $debug ] ); ?>
                <?php endforeach; ?>
            </div>
            <div class="hpc-schema-tip">Tip: use debug mode when you need HTTP details, body size, and total script count.</div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public function renderScan( array $scan, array $args = [] ): string {
        $debug = ! empty( $args["debug"] );
        $error = (string) ( $scan["error"] ?? "" );
        $types = (array) ( $scan["types"] ?? [] );

        ob_start();
        ?>
        <section class="hpc-schema-scan">
            <div class="hpc-schema-scan-head">
                <div>
                    <h4><?php echo esc_html( (string) ( $scan["title"] ?? "Untitled" ) ); ?></h4>
                    <?php if ( ! empty( $scan["url"] ) ) : ?>
                        <a href="<?php echo esc_url( (string) $scan["url"] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) $scan["url"] ); ?></a>
                    <?php endif; ?>
                </div>
                <div class="hpc-schema-meta">
                    <?php if ( ! empty( $scan["status"] ) ) : ?><span>HTTP <?php echo esc_html( (string) $scan["status"] ); ?></span><?php endif; ?>
                    <span><?php echo esc_html( (string) ( $scan["time_ms"] ?? 0 ) ); ?>ms</span>
                    <?php if ( $debug ) : ?>
                        <span><?php echo esc_html( number_format( (int) ( $scan["body_size"] ?? 0 ) ) ); ?> bytes</span>
                        <span><?php echo esc_html( (string) ( $scan["script_count"] ?? 0 ) ); ?> script tag(s)</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ( "" !== $error ) : ?>
                <div class="hpc-schema-alert is-bad">HTTP error: <?php echo esc_html( $error ); ?></div>
            <?php elseif ( empty( $scan["blocks"] ) && empty( $scan["invalid_blocks"] ) ) : ?>
                <div class="hpc-schema-alert is-warn">No JSON-LD schema found on this page.</div>
            <?php else : ?>
                <div class="hpc-schema-types">
                    <strong>Types:</strong>
                    <?php echo esc_html( empty( $types ) ? "None detected" : implode( ", ", $types ) ); ?>
                </div>

                <?php foreach ( (array) ( $scan["blocks"] ?? [] ) as $block ) : ?>
                    <?php echo $this->renderBlock( $block ); ?>
                <?php endforeach; ?>

                <?php foreach ( (array) ( $scan["invalid_blocks"] ?? [] ) as $block ) : ?>
                    <div class="hpc-schema-alert is-bad">Block <?php echo esc_html( (string) ( $block["index"] ?? "" ) ); ?> invalid JSON: <?php echo esc_html( (string) ( $block["error"] ?? "Unknown error" ) ); ?></div>
                <?php endforeach; ?>

                <?php if ( ! empty( $scan["semantic_issues"] ) ) : ?>
                    <div class="hpc-schema-alert is-bad">
                        <strong>Invalid schema property values</strong>
                        <?php foreach ( (array) $scan["semantic_issues"] as $issue ) : ?>
                            <div>Block <?php echo esc_html( (string) ( $issue["block"] ?? "" ) ); ?>, <code><?php echo esc_html( (string) ( $issue["path"] ?? "unknown" ) ); ?></code>: <?php echo esc_html( (string) ( $issue["message"] ?? "Invalid value." ) ); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ( ! empty( $scan["conflicts"] ) ) : ?>
                    <div class="hpc-schema-alert is-bad">
                        <strong>Schema conflicts detected</strong>
                        <?php foreach ( (array) $scan["conflicts"] as $conflict ) : ?>
                            <div><?php echo esc_html( (string) ( $conflict["count"] ?? "" ) ); ?>x <?php echo esc_html( (string) ( $conflict["type"] ?? "" ) ); ?> from <?php echo esc_html( implode( " + ", (array) ( $conflict["sources"] ?? [] ) ) ); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ( ! empty( $scan["faq_issues"] ) ) : ?>
                    <div class="hpc-schema-alert is-warn">FAQ schema issues: <?php echo esc_html( implode( "; ", (array) $scan["faq_issues"] ) ); ?></div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function renderBlock( array $block ): string {
        $schema = isset( $block["schema"] ) && is_array( $block["schema"] ) ? $block["schema"] : [];
        $source = isset( $block["source"] ) && is_array( $block["source"] ) ? $block["source"] : [];
        $color  = isset( $source["color"] ) ? (string) $source["color"] : "#9ca3af";
        $types  = (array) ( $block["types"] ?? [] );

        ob_start();
        ?>
        <article class="hpc-schema-block" style="border-left-color:<?php echo esc_attr( $color ); ?>">
            <div class="hpc-schema-block-head">
                <strong>Block <?php echo esc_html( (string) ( $block["index"] ?? "" ) ); ?></strong>
                <span style="color:<?php echo esc_attr( $color ); ?>"><?php echo esc_html( (string) ( $source["name"] ?? "Unknown" ) ); ?></span>
                <em><?php echo esc_html( empty( $types ) ? "No @type" : implode( ", ", $types ) ); ?></em>
            </div>
            <?php echo $this->renderSchemaSummary( $schema ); ?>
            <details class="hpc-schema-json">
                <summary>View full JSON</summary>
                <pre><?php echo esc_html( wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
            </details>
        </article>
        <?php
        return (string) ob_get_clean();
    }

    private function renderSchemaSummary( array $schema ): string {
        $nodes = [];
        if ( isset( $schema["@graph"] ) && is_array( $schema["@graph"] ) ) {
            $nodes = $schema["@graph"];
        } else {
            $nodes = [ $schema ];
        }

        $properties = [
            "@id",
            "@type",
            "name",
            "url",
            "description",
            "image",
            "sameAs",
            "datePublished",
            "dateModified",
            "author",
            "publisher",
            "headline",
            "audio",
            "associatedArticle",
            "encodesCreativeWork",
            "contentUrl",
            "duration",
            "encodingFormat",
            "uploadDate",
            "transcript",
            "mainEntityOfPage",
            "foundingDate",
            "founder",
            "jobTitle",
            "alumniOf",
            "knowsAbout",
            "email",
            "telephone",
            "mainEntity",
        ];

        ob_start();
        ?>
        <div class="hpc-schema-nodes">
            <?php foreach ( $nodes as $node ) : ?>
                <?php if ( ! is_array( $node ) ) { continue; } ?>
                <div class="hpc-schema-node">
                    <strong><?php echo esc_html( $this->formatValue( $node["@type"] ?? "Schema node" ) ); ?></strong>
                    <?php foreach ( $properties as $property ) : ?>
                        <?php if ( "@type" === $property || ! array_key_exists( $property, $node ) ) { continue; } ?>
                        <div><span><?php echo esc_html( $property ); ?>:</span> <?php echo esc_html( $this->formatValue( $node[ $property ] ) ); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function formatValue( $value ): string {
        if ( is_array( $value ) ) {
            if ( isset( $value["@type"] ) ) {
                return "{" . $this->formatValue( $value["@type"] ) . "}";
            }
            if ( isset( $value["@id"] ) ) {
                return (string) $value["@id"];
            }
            if ( isset( $value[0] ) ) {
                $parts = [];
                foreach ( array_slice( $value, 0, 3 ) as $item ) {
                    $parts[] = is_scalar( $item ) ? (string) $item : "{" . count( (array) $item ) . " fields}";
                }
                return implode( ", ", $parts ) . ( count( $value ) > 3 ? " ... +" . ( count( $value ) - 3 ) . " more" : "" );
            }
            return wp_json_encode( $value );
        }

        if ( is_bool( $value ) ) {
            return $value ? "true" : "false";
        }

        $string = is_scalar( $value ) ? (string) $value : "";
        return strlen( $string ) > 140 ? substr( $string, 0, 140 ) . "..." : $string;
    }

    private function styles(): string {
        ob_start();
        ?>
        <style>
            .hpc-schema-report{background:#1e1e2e;border:1px solid #313244;border-radius:8px;color:#cdd6f4;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;line-height:1.45;max-height:520px;overflow:auto;padding:16px;text-align:left}
            .hpc-schema-report a{color:#93c5fd}.hpc-schema-report-head{align-items:flex-start;border-bottom:1px solid #313244;display:flex;gap:16px;justify-content:space-between;margin-bottom:14px;padding-bottom:12px}.hpc-schema-report h3{color:#86efac;font-size:16px;margin:0 0 5px}.hpc-schema-report-head p{color:#8b93a7;font-size:12px;margin:0}.hpc-schema-summary{align-items:center;display:flex;flex-wrap:wrap;gap:7px;justify-content:flex-end}.hpc-schema-summary span{background:#111827;border:1px solid #263244;border-radius:999px;color:#bfdbfe;font-size:11px;padding:4px 8px}.hpc-schema-summary .is-warn{color:#facc15}.hpc-schema-summary .is-bad{color:#fca5a5}.hpc-schema-expected{background:#0f172a;border:1px solid #334155;border-radius:7px;color:#cbd5e1;font-size:12px;line-height:1.65;margin-bottom:12px;padding:10px 12px}.hpc-schema-expected strong{color:#fbbf24;display:block;margin-bottom:4px}.hpc-schema-scan{border-bottom:1px solid #313244;margin:0 0 14px;padding:0 0 14px}.hpc-schema-scan:last-child{border-bottom:0;margin-bottom:0;padding-bottom:0}.hpc-schema-scan-head{align-items:flex-start;display:flex;gap:14px;justify-content:space-between;margin-bottom:9px}.hpc-schema-scan h4{color:#60a5fa;font-size:13px;margin:0 0 3px}.hpc-schema-scan-head a{font-size:11px;text-decoration:none;word-break:break-all}.hpc-schema-meta{align-items:center;display:flex;flex-wrap:wrap;gap:6px;justify-content:flex-end}.hpc-schema-meta span{background:#111827;border-radius:4px;color:#9ca3af;font-size:10px;padding:3px 6px}.hpc-schema-types{color:#fbbf24;font-size:12px;margin:0 0 8px}.hpc-schema-alert{border-radius:6px;font-size:12px;margin:8px 0;padding:8px 10px}.hpc-schema-alert.is-bad{background:#451a1a;border:1px solid #dc2626;color:#fca5a5}.hpc-schema-alert.is-warn{background:#422006;border:1px solid #d97706;color:#fde68a}.hpc-schema-block{background:#0d1117;border-left:3px solid #9ca3af;border-radius:5px;margin:8px 0;padding:10px}.hpc-schema-block-head{align-items:center;display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px}.hpc-schema-block-head strong{color:#c4b5fd}.hpc-schema-block-head span{background:#1e1e2e;border-radius:3px;font-size:11px;padding:2px 6px}.hpc-schema-block-head em{color:#fbbf24;font-size:11px;font-style:normal}.hpc-schema-nodes{display:grid;gap:7px}.hpc-schema-node{background:#161b22;border-left:2px solid #374151;border-radius:4px;color:#9ca3af;font-size:11px;line-height:1.6;padding:8px}.hpc-schema-node strong{color:#60a5fa;display:block;margin-bottom:4px}.hpc-schema-node span{color:#6b7280}.hpc-schema-json summary{color:#60a5fa;cursor:pointer;font-size:11px;margin-top:8px}.hpc-schema-json pre{background:#161b22;border-radius:4px;color:#cdd6f4;font-size:10px;max-height:300px;overflow:auto;padding:10px;white-space:pre-wrap}.hpc-schema-tip{border-top:1px solid #313244;color:#8b93a7;font-size:11px;margin-top:12px;padding-top:10px}
        </style>
        <?php
        return (string) ob_get_clean();
    }
}
