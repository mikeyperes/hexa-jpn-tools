<?php

namespace Hexa\PluginCore\ActivityLog;

final class ActivityLogRenderer {
    private ActivityLogConfig $config;

    public function __construct( ActivityLogConfig $config ) {
        $this->config = $config;
    }

    /**
     * @param ActivityLogEntry[] $entries
     */
    public function render( array $entries ): void {
        $id        = $this->config->id();
        $collapsed = $this->config->collapsed();
        $count     = count( $entries );
        ?>
        <section id="<?php echo esc_attr( $id ); ?>" class="hexa-activity-log <?php echo $this->config->dark() ? 'is-dark' : 'is-light'; ?>" data-collapsed="<?php echo $collapsed ? '1' : '0'; ?>">
            <style>
                #<?php echo esc_attr( $id ); ?>{border-radius:8px;overflow:hidden;border:1px solid #263241;background:#0f1720;color:#dbe7f3;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace}
                #<?php echo esc_attr( $id ); ?> .hexa-log-head{align-items:center;background:#111c2a;border-bottom:1px solid #263241;display:flex;gap:12px;justify-content:space-between;padding:14px 16px}
                #<?php echo esc_attr( $id ); ?> .hexa-log-title{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:15px;font-weight:700;margin:0}
                #<?php echo esc_attr( $id ); ?> .hexa-log-meta{align-items:center;display:flex;flex-wrap:wrap;gap:8px}
                #<?php echo esc_attr( $id ); ?> .hexa-log-pill{background:#1f2f44;border:1px solid #34465d;border-radius:999px;color:#b9c7d8;font-size:12px;padding:4px 9px}
                #<?php echo esc_attr( $id ); ?> .hexa-log-toggle{background:#24364c;border:1px solid #405872;border-radius:6px;color:#f7fbff;cursor:pointer;font-weight:700;padding:7px 11px}
                #<?php echo esc_attr( $id ); ?> .hexa-log-body{display:block;max-height:460px;overflow:auto}
                #<?php echo esc_attr( $id ); ?>[data-collapsed="1"] .hexa-log-body{display:none}
                #<?php echo esc_attr( $id ); ?> .hexa-log-empty{color:#8ca1b8;padding:18px}
                #<?php echo esc_attr( $id ); ?> .hexa-log-row{border-top:1px solid #1f2f44;display:grid;gap:10px;grid-template-columns:92px 82px 1fr;padding:12px 16px}
                #<?php echo esc_attr( $id ); ?> .hexa-log-time{color:#8ca1b8;font-size:12px;white-space:nowrap}
                #<?php echo esc_attr( $id ); ?> .hexa-log-level{border-radius:5px;font-size:11px;font-weight:800;letter-spacing:.04em;padding:3px 7px;text-align:center;text-transform:uppercase}
                #<?php echo esc_attr( $id ); ?> .hexa-log-level.info{background:#16324f;color:#9bd0ff}
                #<?php echo esc_attr( $id ); ?> .hexa-log-level.success{background:#14391f;color:#9cf0b0}
                #<?php echo esc_attr( $id ); ?> .hexa-log-level.warning{background:#493813;color:#ffd37a}
                #<?php echo esc_attr( $id ); ?> .hexa-log-level.error{background:#4c1720;color:#ff9cac}
                #<?php echo esc_attr( $id ); ?> .hexa-log-message{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:13px;font-weight:650;margin-bottom:4px}
                #<?php echo esc_attr( $id ); ?> .hexa-log-sub{color:#9fb1c6;font-size:12px}
                #<?php echo esc_attr( $id ); ?> details{margin-top:8px}
                #<?php echo esc_attr( $id ); ?> summary{color:#c9d8ea;cursor:pointer;font-size:12px}
                #<?php echo esc_attr( $id ); ?> pre{background:#0a111a;border:1px solid #1d2b3d;border-radius:6px;color:#dbe7f3;max-height:240px;overflow:auto;padding:10px;white-space:pre-wrap}
                @media(max-width:760px){#<?php echo esc_attr( $id ); ?> .hexa-log-row{grid-template-columns:1fr}#<?php echo esc_attr( $id ); ?> .hexa-log-level{text-align:left;width:max-content}}
            </style>
            <div class="hexa-log-head">
                <div>
                    <h3 class="hexa-log-title"><?php echo esc_html( $this->config->title() ); ?></h3>
                    <div class="hexa-log-meta">
                        <span class="hexa-log-pill"><?php echo esc_html( $count ); ?> entries</span>
                        <span class="hexa-log-pill">Storage: <?php echo esc_html( $this->config->storage_label() ); ?></span>
                        <span class="hexa-log-pill">Max: <?php echo esc_html( (string) $this->config->max_entries() ); ?></span>
                    </div>
                </div>
                <button type="button" class="hexa-log-toggle" data-role="toggle"><?php echo $collapsed ? 'Expand' : 'Collapse'; ?></button>
            </div>
            <div class="hexa-log-body">
                <?php if ( [] === $entries ) : ?>
                    <div class="hexa-log-empty">No activity has been recorded.</div>
                <?php else : ?>
                    <?php foreach ( array_reverse( $entries ) as $entry ) : $data = $entry->to_array(); ?>
                        <article class="hexa-log-row">
                            <div class="hexa-log-time"><?php echo esc_html( $this->time_label( (string) $data['timestamp'] ) ); ?></div>
                            <div><span class="hexa-log-level <?php echo esc_attr( sanitize_html_class( (string) $data['level'] ) ); ?>"><?php echo esc_html( (string) $data['level'] ); ?></span></div>
                            <div>
                                <div class="hexa-log-message"><?php echo esc_html( (string) $data['message'] ); ?></div>
                                <div class="hexa-log-sub">
                                    <?php if ( $data['source'] ) : ?>Source: <?php echo esc_html( (string) $data['source'] ); ?><?php endif; ?>
                                    <?php if ( $data['actor'] ) : ?> Actor: <?php echo esc_html( (string) $data['actor'] ); ?><?php endif; ?>
                                </div>
                                <?php if ( $data['detail'] || ! empty( $data['context'] ) ) : ?>
                                    <details>
                                        <summary>Details</summary>
                                        <?php if ( $data['detail'] ) : ?><pre><?php echo esc_html( (string) $data['detail'] ); ?></pre><?php endif; ?>
                                        <?php if ( ! empty( $data['context'] ) ) : ?><pre><?php echo esc_html( wp_json_encode( $data['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre><?php endif; ?>
                                    </details>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <script>
            (function(){
                var root = document.getElementById('<?php echo esc_js( $id ); ?>');
                if (!root) return;
                var button = root.querySelector('[data-role="toggle"]');
                if (!button) return;
                button.addEventListener('click', function(){
                    var collapsed = root.getAttribute('data-collapsed') === '1';
                    root.setAttribute('data-collapsed', collapsed ? '0' : '1');
                    button.textContent = collapsed ? 'Collapse' : 'Expand';
                });
            })();
            </script>
        </section>
        <?php
    }

    private function time_label( string $timestamp ): string {
        $time = strtotime( $timestamp );

        if ( ! $time ) {
            return $timestamp;
        }

        return gmdate( 'H:i:s', $time );
    }
}
