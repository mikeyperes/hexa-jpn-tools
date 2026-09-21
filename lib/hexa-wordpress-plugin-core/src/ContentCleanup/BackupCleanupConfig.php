<?php

namespace Hexa\PluginCore\ContentCleanup;

final class BackupCleanupConfig {
    private array $values;

    public function __construct( array $values = [] ) {
        $defaults = [
            'root_id'       => 'hpc-backup-cleanup',
            'title'         => 'Backup Files',
            'description'   => 'Detect backup files and delete them through guarded AJAX actions.',
            'capability'    => 'manage_options',
            'nonce_action'  => 'hpc_backup_cleanup',
            'nonce_field'   => 'nonce',
            'scan_action'   => 'hpc_backup_cleanup_scan',
            'delete_action' => 'hpc_backup_cleanup_delete',
            'auto_scan'     => false,
            'locations'     => [],
            'empty_message' => 'No backup files were detected.',
        ];

        $values = array_merge( $defaults, $values );
        $values['root_id']       = $this->clean_html_id( (string) $values['root_id'] );
        $values['nonce_field']   = $this->clean_key( (string) $values['nonce_field'] );
        $values['scan_action']   = $this->clean_key( (string) $values['scan_action'] );
        $values['delete_action'] = $this->clean_key( (string) $values['delete_action'] );
        $values['locations']     = $this->normalize_locations( (array) $values['locations'] );

        $this->values = $values;
    }

    public function get( string $key, mixed $default = null ): mixed {
        return array_key_exists( $key, $this->values ) ? $this->values[ $key ] : $default;
    }

    public function root_id(): string {
        return (string) $this->get( 'root_id' );
    }

    public function capability(): string {
        return (string) $this->get( 'capability', 'manage_options' );
    }

    public function nonce_action(): string {
        return (string) $this->get( 'nonce_action' );
    }

    public function nonce_field(): string {
        return (string) $this->get( 'nonce_field', 'nonce' );
    }

    public function scan_action(): string {
        return (string) $this->get( 'scan_action' );
    }

    public function delete_action(): string {
        return (string) $this->get( 'delete_action' );
    }

    public function locations(): array {
        return (array) $this->get( 'locations', [] );
    }

    public function auto_scan(): bool {
        return (bool) $this->get( 'auto_scan', false );
    }

    private function normalize_locations( array $locations ): array {
        $normalized = [];

        foreach ( $locations as $key => $location ) {
            if ( ! is_array( $location ) ) {
                continue;
            }

            $id         = $this->clean_key( is_string( $key ) ? $key : (string) ( $location['id'] ?? $location['name'] ?? '' ) );
            $name       = trim( (string) ( $location['name'] ?? $id ) );
            $path       = rtrim( (string) ( $location['path'] ?? '' ), '/\\' );
            $extensions = array_values(
                array_filter(
                    array_map( [ $this, 'clean_key' ], (array) ( $location['extensions'] ?? [] ) )
                )
            );

            if ( '' === $id || '' === $name || '' === $path || [] === $extensions ) {
                continue;
            }

            $normalized[ $id ] = [
                'id'         => $id,
                'name'       => $name,
                'path'       => $path,
                'extensions' => $extensions,
            ];
        }

        return $normalized;
    }

    private function clean_key( string $value ): string {
        return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : ( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?: '' );
    }

    private function clean_html_id( string $value ): string {
        $value = trim( $value );
        if ( '' === $value ) {
            return 'hpc-backup-cleanup';
        }

        return function_exists( 'sanitize_html_class' ) ? sanitize_html_class( $value ) : ( preg_replace( '/[^a-zA-Z0-9_\-]/', '-', $value ) ?: 'hpc-backup-cleanup' );
    }
}
