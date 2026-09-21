<?php

namespace Hexa\PluginCore\EntitySources;

use Hexa\PluginCore\WpAdminComponents\CoreUi;

final class EntityFieldInventoryRenderer {
    /** @param array<string,mixed>|null $entity */
    public function render( ?array $entity, array $args = [] ): string {
        $args = array_replace(
            [
                'title'          => 'All available WordPress and ACF fields',
                'description'    => 'Every detected field remains available here, including empty fields. Protected credential values are never printed.',
                'empty_message'  => 'No primary author is assigned, so there are no author fields to inspect.',
                'persist_prefix' => 'primary-entity',
                'standalone'     => true,
            ],
            $args
        );

        ob_start();
        CoreUi::render_assets();
        $assets = (string) ob_get_clean() . $this->assets();

        if ( ! $entity ) {
            return $assets . $this->empty_state( $args );
        }

        $groups = ( new EntityFieldInspector() )->inspect( $entity );
        if ( empty( $args['standalone'] ) ) {
            return $assets . $this->inline_inventory( $entity, $groups, $args );
        }

        ob_start();
        ?>
        <?php echo $assets; ?>
        <div class="hpc-ui hpc-entity-field-inventory">
            <section class="hpc-card hpc-entity-field-inventory-intro">
                <h3><?php echo esc_html( (string) $args['title'] ); ?></h3>
                <p><?php echo esc_html( (string) $args['description'] ); ?></p>
                <div class="hpc-actions"><?php echo CoreUi::pill( (string) ( $entity['name'] ?? 'Selected entity' ), 'success' ); ?><?php echo CoreUi::pill( 'WordPress ID ' . (int) ( $entity['id'] ?? 0 ), 'dark' ); ?></div>
            </section>
            <div class="hpc-stack"><?php echo $this->group_cards( $entity, $groups, $args ); ?></div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $args */
    private function empty_state( array $args ): string {
        if ( empty( $args['standalone'] ) ) {
            return '';
        }
        return '<div class="hpc-ui hpc-entity-field-inventory"><section class="hpc-card hpc-entity-field-inventory-intro"><h3>'
            . esc_html( (string) $args['title'] ) . '</h3><p>' . esc_html( (string) $args['empty_message'] ) . '</p></section></div>';
    }

    /**
     * @param array<string,mixed>            $entity
     * @param array<int,array<string,mixed>> $groups
     * @param array<string,mixed>            $args
     */
    private function inline_inventory( array $entity, array $groups, array $args ): string {
        return '<div class="hpc-primary-field-groups"><h4>' . esc_html( (string) $args['title'] ) . '</h4><p class="hpc-small">'
            . esc_html( (string) $args['description'] ) . '</p>' . $this->group_cards( $entity, $groups, $args ) . '</div>';
    }

    /**
     * @param array<string,mixed>            $entity
     * @param array<int,array<string,mixed>> $groups
     * @param array<string,mixed>            $args
     */
    private function group_cards( array $entity, array $groups, array $args ): string {
        $html = '';
        $prefix = sanitize_key( (string) $args['persist_prefix'] );
        foreach ( $groups as $group ) {
            $html .= CoreUi::collapsible(
                [
                    'title'       => (string) $group['label'],
                    'meta_html'   => CoreUi::pill( count( $group['fields'] ) . ' fields', 'dark' ),
                    'body_html'   => $this->field_table( $group['fields'] ),
                    'open'        => false,
                    'persist_key' => $prefix . '-' . (int) $entity['id'] . '-' . sanitize_key( (string) $group['key'] ),
                    'query_state' => false,
                ]
            );
        }
        return $html;
    }

    /** @param array<int,array<string,mixed>> $fields */
    private function field_table( array $fields ): string {
        $html = '<div class="hpc-primary-field-table"><div class="head">Field</div><div class="head">Type</div><div class="head">Current value</div>';
        foreach ( $fields as $field ) {
            $html .= '<div><strong>' . esc_html( (string) $field['label'] ) . '</strong><small>' . esc_html( (string) $field['name'] ) . '</small></div><div>'
                . esc_html( (string) $field['type'] ) . '</div><div class="' . ( ! empty( $field['set'] ) ? '' : 'empty' ) . '">' . esc_html( (string) $field['value'] ) . '</div>';
        }
        return $html . '</div>';
    }

    private function assets(): string {
        static $done = false;
        if ( $done ) {
            return '';
        }
        $done = true;
        return <<<'HTML'
<style>
.hpc-entity-field-inventory{margin-top:16px;max-width:100%;min-width:0}.hpc-entity-field-inventory *{min-width:0}.hpc-entity-field-inventory-intro{margin-bottom:14px}.hpc-entity-field-inventory-intro h3{font-size:20px}.hpc-primary-field-groups{border-top:1px solid var(--hpc-line);margin-top:18px;padding-top:16px}.hpc-primary-field-groups>h4{font-size:13px;margin:0 0 10px}.hpc-primary-field-table{display:grid;grid-template-columns:minmax(130px,.75fr) minmax(80px,.35fr) minmax(0,1.5fr);overflow-wrap:anywhere;width:100%}.hpc-primary-field-table>div{border-bottom:1px solid #e7ebf1;padding:9px}.hpc-primary-field-table .head{background:#f5f7fa;font-size:11px;font-weight:800;text-transform:uppercase}.hpc-primary-field-table small{color:var(--hpc-muted);display:block;margin-top:3px}.hpc-primary-field-table .empty{color:var(--hpc-muted);font-style:italic}@media(max-width:760px){.hpc-primary-field-table{display:block}.hpc-primary-field-table .head{display:none}.hpc-primary-field-table>div{border-bottom:0;padding:5px 0}.hpc-primary-field-table>div:nth-child(3n){border-bottom:1px solid #e7ebf1;margin-bottom:8px;padding-bottom:10px}}
</style>
HTML;
    }
}
