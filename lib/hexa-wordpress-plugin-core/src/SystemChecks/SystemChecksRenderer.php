<?php

namespace Hexa\PluginCore\SystemChecks;

use Hexa\PluginCore\WpAdminComponents\CoreUi;

final class SystemChecksRenderer {
    public function render( array $items, array $args = [] ): string {
        CoreUi::render_assets();

        $id            = isset( $args["id"] ) ? sanitize_html_class( (string) $args["id"] ) : "hpc-system-checks";
        $title         = isset( $args["title"] ) ? (string) $args["title"] : "System Checks";
        $class         = isset( $args["class"] ) ? sanitize_html_class( (string) $args["class"] ) : "";
        $category_meta = isset( $args["category_meta"] ) && is_array( $args["category_meta"] ) ? $args["category_meta"] : [];
        $show_progress = array_key_exists( "show_progress", $args ) ? (bool) $args["show_progress"] : true;

        $pass  = $this->count_status( $items, "pass" );
        $fail  = $this->count_status( $items, "fail" );
        $warn  = $this->count_status( $items, "warn" );
        $total = count( $items );
        $pct   = $total > 0 ? (int) round( ( $pass / $total ) * 100 ) : 0;
        $bar   = 100 === $pct ? "#16a34a" : ( $pct >= 70 ? "#f59e0b" : "#dc2626" );
        $groups = $this->group_items( $items );

        ob_start();
        ?>
        <div id="<?php echo esc_attr( $id ); ?>" class="hpc-ui hpc-system-checks <?php echo esc_attr( $class ); ?>">
            <div class="hpc-system-checks-head">
                <span class="dashicons dashicons-yes-alt" style="color:<?php echo esc_attr( $fail > 0 ? "#dc2626" : "#10b981" ); ?>"></span>
                <h3><?php echo esc_html( $title ); ?></h3>
                <span class="hpc-system-checks-count"><?php echo esc_html( (string) $pass ); ?>/<?php echo esc_html( (string) $total ); ?> passed<?php if ( $fail > 0 ) : ?> <strong>(<?php echo esc_html( (string) $fail ); ?> issues)</strong><?php endif; ?></span>
            </div>
            <?php if ( $show_progress ) : ?>
                <div class="hpc-system-checks-progress"><span style="width:<?php echo esc_attr( (string) $pct ); ?>%;background:<?php echo esc_attr( $bar ); ?>"></span></div>
            <?php endif; ?>
            <?php foreach ( $groups as $category => $group_items ) :
                $meta = $this->category_meta( (string) $category, $category_meta );
                $group_pass = $this->count_status( $group_items, "pass" );
                $group_fail = $this->count_status( $group_items, "fail" );
                ?>
                <section class="hpc-system-check-group">
                    <div class="hpc-system-check-group-head">
                        <span class="dashicons <?php echo esc_attr( $meta["icon"] ); ?>" style="color:<?php echo esc_attr( $meta["color"] ); ?>"></span>
                        <strong><?php echo esc_html( (string) $category ); ?></strong>
                        <span><?php echo esc_html( (string) $group_pass ); ?>/<?php echo esc_html( (string) count( $group_items ) ); ?><?php if ( $group_fail > 0 ) : ?> <em>(<?php echo esc_html( (string) $group_fail ); ?> x)</em><?php endif; ?></span>
                    </div>
                    <?php foreach ( $group_items as $item ) :
                        $status = isset( $item["status"] ) ? strtolower( (string) $item["status"] ) : "info";
                        $tone   = $this->status_tone( $status );
                        ?>
                        <div class="hpc-system-check-row hpc-system-check-row-<?php echo esc_attr( $tone ); ?>">
                            <span class="hpc-system-check-icon"><?php echo esc_html( $this->status_icon( $status ) ); ?></span>
                            <span class="hpc-system-check-label"><?php echo esc_html( (string) ( $item["label"] ?? "" ) ); ?></span>
                            <span class="hpc-system-check-detail"><?php echo esc_html( (string) ( $item["detail"] ?? "" ) ); ?></span>
                            <?php if ( ! empty( $item["action_url"] ) && "pass" !== $status ) : ?>
                                <a href="<?php echo esc_url( (string) $item["action_url"] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) ( $item["action_label"] ?? "Fix" ) ); ?> -></a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
            <?php if ( $total > 0 && 0 === $fail && 0 === $warn ) : ?>
                <div class="hpc-system-checks-good"><strong>All checks passed.</strong> The site is configured.</div>
            <?php elseif ( $fail > 0 ) : ?>
                <div class="hpc-system-checks-bad"><strong><?php echo esc_html( (string) $fail ); ?> issue(s) need attention.</strong> Review the linked rows.</div>
            <?php endif; ?>
        </div>
        <style>
            .hpc-system-checks{background:#fff;border:1px solid #d9e0ea;border-radius:8px;padding:16px;margin:16px 0}
            .hpc-system-checks-head{align-items:center;display:flex;gap:10px;border-bottom:1px solid #eef0f2;padding-bottom:12px;margin-bottom:12px}
            .hpc-system-checks-head h3{font-size:16px;margin:0}.hpc-system-checks-count{color:#65758b;font-size:13px;margin-left:auto}.hpc-system-checks-count strong{color:#dc2626}
            .hpc-system-checks-progress{height:6px;background:#e5e7eb;border-radius:999px;overflow:hidden;margin-bottom:16px}.hpc-system-checks-progress span{display:block;height:100%;border-radius:999px}
            .hpc-system-check-group{margin:0 0 16px}.hpc-system-check-group-head{align-items:center;border-bottom:1px solid #e5e7eb;display:flex;gap:8px;margin-bottom:8px;padding-bottom:6px}.hpc-system-check-group-head strong{font-size:13px}.hpc-system-check-group-head span:last-child{color:#9ca3af;font-size:11px;margin-left:auto}.hpc-system-check-group-head em{color:#dc2626;font-style:normal}
            .hpc-system-check-row{align-items:center;border-radius:5px;display:flex;gap:8px;margin-bottom:2px;padding:6px 8px}.hpc-system-check-row-fail{background:#fef2f2}.hpc-system-check-row-warn{background:#fffbeb}.hpc-system-check-icon{font-size:12px;text-align:center;width:14px}.hpc-system-check-row-pass .hpc-system-check-icon{color:#059669}.hpc-system-check-row-fail .hpc-system-check-icon{color:#dc2626}.hpc-system-check-row-warn .hpc-system-check-icon{color:#d97706}.hpc-system-check-label{color:#374151;font-size:12px;min-width:160px}.hpc-system-check-detail{color:#6b7280;flex:1;font-size:11px}.hpc-system-check-row a{color:#2563eb;font-size:10px;text-decoration:none;white-space:nowrap}.hpc-system-checks-good,.hpc-system-checks-bad{border-radius:6px;font-size:13px;margin-top:8px;padding:10px 14px}.hpc-system-checks-good{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}.hpc-system-checks-bad{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
        </style>
        <?php
        return (string) ob_get_clean();
    }

    private function group_items( array $items ): array {
        $groups = [];
        foreach ( $items as $item ) {
            $category = isset( $item["category"] ) && "" !== (string) $item["category"] ? (string) $item["category"] : "General";
            $groups[ $category ][] = $item;
        }
        return $groups;
    }

    private function count_status( array $items, string $status ): int {
        $count = 0;
        foreach ( $items as $item ) {
            if ( strtolower( (string) ( $item["status"] ?? "" ) ) === $status ) {
                $count++;
            }
        }
        return $count;
    }

    private function status_tone( string $status ): string {
        return in_array( $status, [ "pass", "fail", "warn" ], true ) ? $status : "info";
    }

    private function status_icon( string $status ): string {
        return match ( $status ) {
            "pass" => "+",
            "fail" => "x",
            "warn" => "!",
            default => "i",
        };
    }

    private function category_meta( string $category, array $meta ): array {
        $defaults = [ "icon" => "dashicons-marker", "color" => "#666" ];
        if ( isset( $meta[ $category ] ) && is_array( $meta[ $category ] ) ) {
            return array_merge( $defaults, $meta[ $category ] );
        }
        return $defaults;
    }
}
