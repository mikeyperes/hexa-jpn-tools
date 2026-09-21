<?php

namespace Hexa\PluginCore\WordPressOperations;

/** Native permalink status and hard rewrite-rule repair. */
final class PermalinkOperations {
    /** @param array<string,callable> $callbacks */
    public function __construct( private array $callbacks = [] ) {}

    /** @return array<string,mixed> */
    public function status(): array {
        if ( isset( $this->callbacks['status'] ) ) {
            return (array) call_user_func( $this->callbacks['status'] );
        }
        $structure = function_exists( 'get_option' ) ? (string) get_option( 'permalink_structure', '' ) : '';
        $rules = function_exists( 'get_option' ) ? get_option( 'rewrite_rules', [] ) : [];
        $rules = is_array( $rules ) ? $rules : [];
        return [ 'success' => true, 'action' => 'status', 'available' => function_exists( 'flush_rewrite_rules' ), 'before' => [], 'after' => [], 'counts' => [ 'rules' => count( $rules ) ], 'items' => [ 'structure' => $structure, 'pretty_permalinks' => '' !== $structure, 'rules' => $rules ], 'messages' => [] ];
    }

    /** @return array<string,mixed> */
    public function repair( string $structure = '' ): array {
        if ( isset( $this->callbacks['repair'] ) ) {
            return (array) call_user_func( $this->callbacks['repair'], $structure );
        }
        $before = $this->status();
        $target = '' !== trim( $structure ) ? trim( $structure ) : (string) ( $before['items']['structure'] ?? '' );
        if ( '' === $target ) {
            return [ 'success' => false, 'action' => 'repair', 'before' => $before, 'after' => $before, 'counts' => [ 'rules' => 0, 'updated' => 0, 'failed' => 1 ], 'items' => [ 'structure' => '' ], 'messages' => [ 'A non-empty permalink structure is required for hard rewrite-rule repair.' ] ];
        }
        global $wp_rewrite;
        if ( $target !== (string) ( $before['items']['structure'] ?? '' ) ) {
            if ( is_object( $wp_rewrite ) && is_callable( [ $wp_rewrite, 'set_permalink_structure' ] ) ) {
                $wp_rewrite->set_permalink_structure( $target );
            } elseif ( function_exists( 'update_option' ) ) {
                update_option( 'permalink_structure', $target );
            }
        }
        if ( function_exists( 'flush_rewrite_rules' ) ) {
            flush_rewrite_rules( true );
        }
        if ( is_object( $wp_rewrite ) && is_callable( [ $wp_rewrite, 'wp_rewrite_rules' ] ) ) {
            $wp_rewrite->wp_rewrite_rules();
        }
        $after = $this->status();
        $success = $target === (string) ( $after['items']['structure'] ?? '' ) && (int) ( $after['counts']['rules'] ?? 0 ) > 0;
        return [ 'success' => $success, 'action' => 'repair', 'before' => $before, 'after' => $after, 'counts' => [ 'rules' => (int) ( $after['counts']['rules'] ?? 0 ), 'updated' => $success ? 1 : 0, 'failed' => $success ? 0 : 1 ], 'items' => $after['items'], 'messages' => [ $success ? 'Permalink structure and non-empty rewrite rules verified.' : 'Permalink repair did not produce non-empty rewrite rules.' ] ];
    }
}
