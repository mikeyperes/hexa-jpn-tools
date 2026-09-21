<?php

namespace Hexa\PluginCore\WpAdminUiCleanup;

use Hexa\PluginCore\GettingStartedChecklist\ChecklistReportBuilder;

final class CleanupChecklistAdapter {
    private function __construct() {}

    /**
     * @param CleanupRegistry|array<string,mixed> $source
     * @param callable|string|null $callback
     * @return array<int,array<string,mixed>>
     */
    public static function subtasks_from_options( CleanupRegistry|array $source, callable|string|null $callback = null, array $args = [] ): array {
        $options  = $source instanceof CleanupRegistry ? $source->options() : $source;
        $subtasks = [];
        $type     = self::clean_key( (string) ( $args['type'] ?? 'status_check' ) );
        $action   = trim( (string) ( $args['action_label'] ?? 'Check' ) );

        foreach ( $options as $key => $definition ) {
            $attributes = self::attributes( $definition, is_string( $key ) ? $key : '', $args );
            if ( '' === $attributes['key'] ) {
                continue;
            }

            $subtask = [
                'id'           => $attributes['key'],
                'label'        => $attributes['label'],
                'type'         => '' !== $type ? $type : 'status_check',
                'action_label' => '' !== $action ? $action : 'Check',
                'description'  => self::checklist_description( $attributes, (bool) ( $args['include_attributes'] ?? true ) ),
                'context'      => [
                    'ui_cleanup_key'       => $attributes['key'],
                    'ui_cleanup_attribute' => $attributes,
                ],
            ];

            if ( is_callable( $callback ) ) {
                $subtask['callback'] = $callback;
            }

            $subtasks[] = $subtask;
        }

        return $subtasks;
    }

    /**
     * @param CleanupOptionDefinition|array<string,mixed>|mixed $definition
     * @return array<string,mixed>
     */
    public static function attributes( mixed $definition, string $fallback_key = '', array $args = [] ): array {
        if ( $definition instanceof CleanupOptionDefinition ) {
            return [
                'key'          => $definition->key,
                'label'        => $definition->label,
                'description'  => $definition->description,
                'section'      => $definition->section,
                'default'      => $definition->default,
                'mode'         => $definition->mode,
                'admin_pages'  => $definition->admin_pages,
                'selectors'    => $definition->selectors,
                'js_headers'   => $definition->js_headers,
                'js_input_ids' => $definition->js_input_ids,
                'callback'     => is_callable( $definition->callback ) ? 'callable' : '',
                'on_label'     => $definition->on_label,
                'off_label'    => $definition->off_label,
            ];
        }

        $config = is_array( $definition ) ? $definition : [];
        $key    = self::clean_key( (string) ( $config['key'] ?? $fallback_key ) );
        $mode   = self::clean_key( (string) ( $config['mode'] ?? '' ) );
        if ( '' === $mode ) {
            $mode = str_starts_with( $key, 'collapse_' ) ? 'postbox_collapse' : ( ! empty( $config['callback'] ) ? 'callback' : 'css_hide' );
        }

        $admin_pages = self::string_list( $config['admin_pages'] ?? ( $args['default_admin_pages'] ?? [] ) );

        return [
            'key'          => $key,
            'label'        => trim( (string) ( $config['label'] ?? $key ) ),
            'description'  => trim( (string) ( $config['description'] ?? '' ) ),
            'section'      => self::clean_key( (string) ( $config['section'] ?? 'general' ) ),
            'default'      => (bool) ( $config['default'] ?? false ),
            'mode'         => $mode,
            'admin_pages'  => $admin_pages,
            'selectors'    => self::string_list( $config['selectors'] ?? $config['css_selectors'] ?? [] ),
            'js_headers'   => self::string_list( $config['js_headers'] ?? $config['js_hide'] ?? [] ),
            'js_input_ids' => self::string_list( $config['js_input_ids'] ?? $config['js_input_id'] ?? [] ),
            'callback'     => ! empty( $config['callback'] ) ? ( is_string( $config['callback'] ) ? $config['callback'] : 'callable' ) : '',
            'on_label'     => trim( (string) ( $config['on_label'] ?? ( 'postbox_collapse' === $mode ? 'Collapsed' : 'Hidden' ) ) ),
            'off_label'    => trim( (string) ( $config['off_label'] ?? ( 'postbox_collapse' === $mode ? 'Expanded' : 'Visible' ) ) ),
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public static function status_report_from_payload( array $payload, bool $enabled ): array {
        $context    = is_array( $payload['context'] ?? null ) ? $payload['context'] : [];
        $attributes = is_array( $context['ui_cleanup_attribute'] ?? null ) ? $context['ui_cleanup_attribute'] : [];
        $mode       = (string) ( $attributes['mode'] ?? '' );
        $state      = $enabled ? (string) ( $attributes['on_label'] ?? ( 'postbox_collapse' === $mode ? 'Collapsed' : 'Hidden' ) ) : (string) ( $attributes['off_label'] ?? ( 'postbox_collapse' === $mode ? 'Expanded' : 'Visible' ) );

        return ChecklistReportBuilder::table(
            'ui_cleanup_attribute',
            'UI Cleanup Attribute',
            [
                [ 'attribute' => 'Key', 'value' => (string) ( $attributes['key'] ?? '' ) ],
                [ 'attribute' => 'Section', 'value' => (string) ( $attributes['section'] ?? '' ) ],
                [ 'attribute' => 'Current state', 'value' => $state ],
                [ 'attribute' => 'Mode', 'value' => $mode ],
                [ 'attribute' => 'Default', 'value' => ! empty( $attributes['default'] ) ? 'enabled' : 'disabled' ],
                [ 'attribute' => 'Selectors', 'value' => implode( ', ', (array) ( $attributes['selectors'] ?? [] ) ) ],
                [ 'attribute' => 'JS headers', 'value' => implode( ', ', (array) ( $attributes['js_headers'] ?? [] ) ) ],
                [ 'attribute' => 'JS input IDs', 'value' => implode( ', ', (array) ( $attributes['js_input_ids'] ?? [] ) ) ],
                [ 'attribute' => 'Admin pages', 'value' => implode( ', ', (array) ( $attributes['admin_pages'] ?? [] ) ) ],
            ],
            [ 'attribute' => 'Attribute', 'value' => 'Value' ],
            [ 'summary' => (string) ( $attributes['label'] ?? 'UI cleanup option' ) . ' is currently ' . $state . '.' ]
        );
    }

    /**
     * @param array<string,mixed> $attributes
     */
    private static function checklist_description( array $attributes, bool $include_attributes ): string {
        $description = trim( (string) ( $attributes['description'] ?? '' ) );
        if ( ! $include_attributes ) {
            return $description;
        }

        $parts = [];
        if ( '' !== $description ) {
            $parts[] = $description;
        }
        $parts[] = 'Key: ' . (string) ( $attributes['key'] ?? '' ) . '.';
        $parts[] = 'Section: ' . (string) ( $attributes['section'] ?? '' ) . '.';
        $parts[] = 'Mode: ' . (string) ( $attributes['mode'] ?? '' ) . '.';
        $parts[] = 'Default: ' . ( ! empty( $attributes['default'] ) ? 'enabled' : 'disabled' ) . '.';

        foreach ( [ 'selectors' => 'Selectors', 'js_headers' => 'JS headers', 'js_input_ids' => 'JS input IDs', 'admin_pages' => 'Admin pages' ] as $key => $label ) {
            $values = array_values( array_filter( (array) ( $attributes[ $key ] ?? [] ) ) );
            if ( [] !== $values ) {
                $parts[] = $label . ': ' . implode( ', ', $values ) . '.';
            }
        }

        if ( '' !== (string) ( $attributes['callback'] ?? '' ) ) {
            $parts[] = 'Callback: ' . (string) $attributes['callback'] . '.';
        }

        return implode( ' ', array_filter( $parts ) );
    }

    /**
     * @return array<int,string>
     */
    private static function string_list( mixed $value ): array {
        if ( is_string( $value ) && '' !== trim( $value ) ) {
            $value = [ $value ];
        }
        if ( ! is_array( $value ) ) {
            return [];
        }

        return array_values(
            array_filter(
                array_map( static fn( mixed $item ): string => is_scalar( $item ) ? trim( (string) $item ) : '', $value ),
                static fn( string $item ): bool => '' !== $item
            )
        );
    }

    private static function clean_key( string $value ): string {
        if ( function_exists( 'sanitize_key' ) ) {
            return sanitize_key( $value );
        }

        return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?: '';
    }
}
