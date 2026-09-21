<?php

namespace Hexa\PluginCore\DatabaseCleanup;

final class DatabaseCleanupService {
    private const SESSION_PREFIX = 'hpc_database_cleanup_';

    private string $plugin_file;

    /**
     * @param array<string,mixed> $config
     */
    public function __construct( private array $config = [] ) {
        $this->plugin_file = (string) ( $config['wp_optimize_plugin_file'] ?? 'wp-optimize/wp-optimize.php' );
    }

    /**
     * @return array<string,mixed>
     */
    public function plugin_state(): array {
        $this->load_plugin_functions();

        $path      = $this->plugin_path();
        $installed = '' !== $path && file_exists( $path );
        $active    = $installed && function_exists( 'is_plugin_active' ) && is_plugin_active( $this->plugin_file );
        $version   = '';

        if ( $installed && function_exists( 'get_plugin_data' ) ) {
            $data    = get_plugin_data( $path, false, false );
            $version = (string) ( $data['Version'] ?? '' );
        }

        return [
            'plugin_file' => $this->plugin_file,
            'installed'   => $installed,
            'active'      => $active,
            'loaded'      => is_callable( 'WP_Optimize' ),
            'version'     => $version,
            'path'        => $path,
        ];
    }

    /**
     * @return array<int,array<string,string>>
     */
    public function cleanup_tasks(): array {
        return [
            [ 'id' => 'revisions', 'label' => 'Post revisions' ],
            [ 'id' => 'autodraft', 'label' => 'Auto drafts' ],
            [ 'id' => 'trash', 'label' => 'Trash content' ],
            [ 'id' => 'spam', 'label' => 'Spam comments' ],
            [ 'id' => 'unapproved', 'label' => 'Unapproved comments' ],
            [ 'id' => 'transient', 'label' => 'Expired transients' ],
            [ 'id' => 'pingbacks', 'label' => 'Pingbacks' ],
            [ 'id' => 'trackbacks', 'label' => 'Trackbacks' ],
            [ 'id' => 'postmeta', 'label' => 'Orphaned post meta' ],
            [ 'id' => 'commentmeta', 'label' => 'Orphaned comment meta' ],
            [ 'id' => 'usermeta', 'label' => 'Orphaned user meta' ],
            [ 'id' => 'orphandata', 'label' => 'Orphaned relationship data' ],
        ];
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function start_session(): array|\WP_Error {
        $before = $this->plugin_state();
        $log    = [
            $this->log_entry( 'info', 'Checking WP-Optimize plugin state.', $this->public_plugin_state( $before ) ),
        ];

        $activated = $this->ensure_wp_optimize_loaded();
        if ( is_wp_error( $activated ) ) {
            return $activated;
        }

        $after  = $this->plugin_state();
        $tables = $this->tables();
        $tasks  = $this->available_cleanup_tasks();

        $session_id = wp_generate_uuid4();
        $state      = [
            'session_id'       => $session_id,
            'started_at'       => time(),
            'initially_active' => (bool) $before['active'],
            'plugin_file'      => $this->plugin_file,
            'tables'           => array_map( static fn( array $table ): string => (string) $table['name'], $tables ),
            'cleanup_tasks'    => array_map( static fn( array $task ): string => (string) $task['id'], $tasks ),
        ];

        update_option( $this->session_option_name( $session_id ), $state, false );

        if ( ! $before['active'] && $after['active'] ) {
            $log[] = $this->log_entry( 'success', 'Activated WP-Optimize for this cleanup run.' );
        } elseif ( $after['active'] ) {
            $log[] = $this->log_entry( 'success', 'WP-Optimize is active.' );
        }

        $log[] = $this->log_entry( 'info', 'Prepared cleanup session.', [ 'cleanup_tasks' => count( $tasks ), 'tables' => count( $tables ) ] );

        return [
            'session_id'    => $session_id,
            'plugin_before' => $this->public_plugin_state( $before ),
            'plugin_after'  => $this->public_plugin_state( $after ),
            'cleanup_tasks' => $tasks,
            'tables'        => $tables,
            'summary'       => $this->table_summary( $tables ),
            'log'           => $log,
        ];
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function run_cleanup_task( string $session_id, string $task_id ): array|\WP_Error {
        $session = $this->session( $session_id );
        if ( is_wp_error( $session ) ) {
            return $session;
        }

        $task_id = sanitize_key( $task_id );
        if ( ! in_array( $task_id, (array) $session['cleanup_tasks'], true ) ) {
            return new \WP_Error( 'hpc_database_cleanup_unknown_task', 'Cleanup task is not part of this session.' );
        }

        $loaded = $this->ensure_wp_optimize_loaded();
        if ( is_wp_error( $loaded ) ) {
            return $loaded;
        }

        $task = $this->cleanup_task_by_id( $task_id );
        $log  = [ $this->log_entry( 'info', 'Running cleanup task: ' . (string) ( $task['label'] ?? $task_id ) . '.', [ 'task' => $task_id ] ) ];

        $result = $this->run_wp_optimize_optimization( $task_id );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $outputs = $this->result_output( $result );
        $message = [] !== $outputs ? implode( ' ', $outputs ) : ( (string) ( $task['label'] ?? $task_id ) . ' cleanup completed.' );
        $log[]   = $this->log_entry( 'success', $message, [ 'task' => $task_id ] );

        return [
            'task'    => $task,
            'message' => $message,
            'result'  => $this->public_result( $result ),
            'log'     => $log,
        ];
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function optimize_table( string $session_id, string $table_name ): array|\WP_Error {
        $session = $this->session( $session_id );
        if ( is_wp_error( $session ) ) {
            return $session;
        }

        $table_name = $this->clean_table_name( $table_name );
        if ( '' === $table_name || ! in_array( $table_name, (array) $session['tables'], true ) ) {
            return new \WP_Error( 'hpc_database_cleanup_unknown_table', 'Table is not part of this cleanup session.' );
        }

        $loaded = $this->ensure_wp_optimize_loaded();
        if ( is_wp_error( $loaded ) ) {
            return $loaded;
        }

        $before = $this->table_status( $table_name );
        $log    = [ $this->log_entry( 'info', 'Optimizing table: ' . $table_name . '.', [ 'table' => $table_name ] ) ];

        $result = $this->run_wp_optimize_optimization(
            'optimizetables',
            [
                'optimization_table'       => $table_name,
                'include_ui_elements'      => false,
                'optimization_force'       => false,
            ]
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $after   = $this->table_status( $table_name );
        $outputs = $this->result_output( $result );
        $message = [] !== $outputs ? implode( ' ', $outputs ) : 'Table optimization completed.';
        $log[]   = $this->log_entry( 'success', $message, [ 'table' => $table_name ] );

        return [
            'table'   => $this->table_payload_from_status( $after ?: $before ),
            'before'  => $this->table_payload_from_status( $before ),
            'after'   => $this->table_payload_from_status( $after ),
            'message' => $message,
            'result'  => $this->public_result( $result ),
            'log'     => $log,
        ];
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public function finish_session( string $session_id ): array|\WP_Error {
        $session = $this->session( $session_id );
        if ( is_wp_error( $session ) ) {
            return $session;
        }

        $before = $this->plugin_state();
        $log    = [];

        $this->load_plugin_functions();
        $was_initially_active = ! empty( $session['initially_active'] );
        if ( function_exists( 'deactivate_plugins' ) && ! $was_initially_active && ! empty( $before['active'] ) ) {
            deactivate_plugins( $this->plugin_file, true, false );
            $log[] = $this->log_entry( 'success', 'Restored WP-Optimize to its inactive pre-run state.' );
        } elseif ( $was_initially_active && ! empty( $before['active'] ) ) {
            $log[] = $this->log_entry( 'info', 'WP-Optimize was active before the run and remains active.' );
        } else {
            $log[] = $this->log_entry( 'info', 'WP-Optimize was already inactive after the cleanup run.' );
        }

        delete_option( $this->session_option_name( $session_id ) );

        return [
            'plugin_before_finish' => $this->public_plugin_state( $before ),
            'plugin_after_finish'  => $this->public_plugin_state( $this->plugin_state() ),
            'log'                  => $log,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function run_full_summary(): array {
        $started = $this->start_session();
        if ( is_wp_error( $started ) ) {
            return [
                'success' => false,
                'message' => $started->get_error_message(),
                'tasks'   => [],
                'tables'  => [],
                'log'     => [ $this->log_entry( 'error', $started->get_error_message() ) ],
            ];
        }

        @set_time_limit( 300 );

        $session_id = (string) $started['session_id'];
        $task_rows  = [];
        $table_rows = [];
        $log        = (array) $started['log'];

        foreach ( (array) $started['cleanup_tasks'] as $task ) {
            $task_id = (string) ( $task['id'] ?? '' );
            if ( '' === $task_id ) {
                continue;
            }

            $result = $this->run_cleanup_task( $session_id, $task_id );
            if ( is_wp_error( $result ) ) {
                $task_rows[] = [
                    'item'    => (string) ( $task['label'] ?? $task_id ),
                    'before'  => 'Queued',
                    'action'  => 'WP-Optimize cleanup task failed.',
                    'after'   => $result->get_error_message(),
                    'meaning' => 'Review this task in the Cleanup tab.',
                ];
                $log[] = $this->log_entry( 'error', $result->get_error_message(), [ 'task' => $task_id ] );
                continue;
            }

            $task_rows[] = [
                'item'    => (string) ( $task['label'] ?? $task_id ),
                'before'  => 'Queued',
                'action'  => 'Ran WP-Optimize cleanup task.',
                'after'   => (string) ( $result['message'] ?? 'Completed' ),
                'meaning' => 'The cleanup task completed through WP-Optimize.',
            ];
            $log = array_merge( $log, (array) ( $result['log'] ?? [] ) );
        }

        foreach ( (array) $started['tables'] as $table ) {
            $table_name = (string) ( $table['name'] ?? '' );
            if ( '' === $table_name ) {
                continue;
            }

            $result = $this->optimize_table( $session_id, $table_name );
            if ( is_wp_error( $result ) ) {
                $table_rows[] = [
                    'table'   => $table_name,
                    'engine'  => (string) ( $table['engine'] ?? '' ),
                    'before'  => (string) ( $table['overhead_label'] ?? '' ),
                    'after'   => $result->get_error_message(),
                    'status'  => 'Failed',
                ];
                $log[] = $this->log_entry( 'error', $result->get_error_message(), [ 'table' => $table_name ] );
                continue;
            }

            $before = is_array( $result['before'] ?? null ) ? $result['before'] : [];
            $after  = is_array( $result['after'] ?? null ) ? $result['after'] : [];
            $table_rows[] = [
                'table'   => $table_name,
                'engine'  => (string) ( $after['engine'] ?? $before['engine'] ?? '' ),
                'before'  => (string) ( $before['overhead_label'] ?? '' ),
                'after'   => (string) ( $after['overhead_label'] ?? '' ),
                'status'  => 'Optimized',
            ];
            $log = array_merge( $log, (array) ( $result['log'] ?? [] ) );
        }

        $finished = $this->finish_session( $session_id );
        if ( ! is_wp_error( $finished ) ) {
            $log = array_merge( $log, (array) ( $finished['log'] ?? [] ) );
        }

        return [
            'success'       => true,
            'message'       => 'Database cleanup and table optimization completed.',
            'tasks'         => $task_rows,
            'tables'        => $table_rows,
            'started'       => $started,
            'finished'      => is_wp_error( $finished ) ? [] : $finished,
            'log'           => $log,
            'task_count'    => count( $task_rows ),
            'table_count'   => count( $table_rows ),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function public_plugin_state( array $state ): array {
        return [
            'plugin_file' => (string) ( $state['plugin_file'] ?? $this->plugin_file ),
            'installed'   => (bool) ( $state['installed'] ?? false ),
            'active'      => (bool) ( $state['active'] ?? false ),
            'loaded'      => (bool) ( $state['loaded'] ?? false ),
            'version'     => (string) ( $state['version'] ?? '' ),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function available_cleanup_tasks(): array {
        $tasks = [];
        foreach ( $this->cleanup_tasks() as $task ) {
            $optimization = $this->wp_optimizer()->get_optimization( $task['id'] );
            if ( is_wp_error( $optimization ) ) {
                continue;
            }
            $tasks[] = $task;
        }

        return $tasks;
    }

    /**
     * @return array<string,string>
     */
    private function cleanup_task_by_id( string $task_id ): array {
        foreach ( $this->cleanup_tasks() as $task ) {
            if ( $task_id === (string) $task['id'] ) {
                return $task;
            }
        }

        return [ 'id' => $task_id, 'label' => $task_id ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function tables(): array {
        $tables = [];

        foreach ( $this->wp_optimizer()->get_tables( true ) as $table ) {
            $payload = $this->table_payload_from_status( $table );
            if ( '' !== (string) $payload['name'] ) {
                $tables[] = $payload;
            }
        }

        return $tables;
    }

    /**
     * @param object|array<string,mixed>|false|null $status
     * @return array<string,mixed>
     */
    private function table_payload_from_status( mixed $status ): array {
        if ( ! $status ) {
            return [
                'name'           => '',
                'engine'         => '',
                'rows'           => 0,
                'data_length'    => 0,
                'index_length'   => 0,
                'overhead'       => 0,
                'size_label'     => '',
                'overhead_label' => '',
                'optimizable'    => false,
                'supported'      => false,
            ];
        }

        $get = static function( string $key ) use ( $status ): mixed {
            if ( is_array( $status ) ) {
                return $status[ $key ] ?? null;
            }
            return $status->{$key} ?? null;
        };

        $data_length  = max( 0, (int) $get( 'Data_length' ) );
        $index_length = max( 0, (int) $get( 'Index_length' ) );
        $overhead     = max( 0, (int) $get( 'Data_free' ) );

        return [
            'name'           => (string) $get( 'Name' ),
            'engine'         => (string) $get( 'Engine' ),
            'rows'           => max( 0, (int) $get( 'Rows' ) ),
            'data_length'    => $data_length,
            'index_length'   => $index_length,
            'overhead'       => $overhead,
            'size_label'     => $this->format_bytes( $data_length + $index_length ),
            'overhead_label' => $this->format_bytes( $overhead ),
            'optimizable'    => (bool) $get( 'is_optimizable' ),
            'supported'      => (bool) $get( 'is_type_supported' ),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function table_summary( array $tables ): array {
        $overhead = 0;
        $size     = 0;
        foreach ( $tables as $table ) {
            $overhead += (int) ( $table['overhead'] ?? 0 );
            $size     += (int) ( $table['data_length'] ?? 0 ) + (int) ( $table['index_length'] ?? 0 );
        }

        return [
            'table_count'    => count( $tables ),
            'database_size'  => $size,
            'overhead'       => $overhead,
            'size_label'     => $this->format_bytes( $size ),
            'overhead_label' => $this->format_bytes( $overhead ),
        ];
    }

    private function table_status( string $table_name ): mixed {
        global $wpdb;

        $like = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( $table_name ) : addcslashes( $table_name, '_%' );
        $rows = $wpdb->get_results( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $like ) );

        return is_array( $rows ) && isset( $rows[0] ) ? $rows[0] : null;
    }

    /**
     * @return object|\WP_Error
     */
    private function run_wp_optimize_optimization( string $optimization_id, array $data = [] ): object {
        $optimizer    = $this->wp_optimizer();
        $optimization = $optimizer->get_optimization( $optimization_id, $data );

        if ( is_wp_error( $optimization ) ) {
            return $optimization;
        }

        if ( ! is_object( $optimization ) || ! method_exists( $optimization, 'do_optimization' ) ) {
            return new \WP_Error( 'hpc_database_cleanup_invalid_optimization', 'WP-Optimize did not return a runnable optimization object.' );
        }

        $result = $optimization->do_optimization();
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return is_object( $result ) ? $result : (object) [ 'output' => [], 'meta' => [], 'sql_commands' => [] ];
    }

    private function wp_optimizer(): object {
        $wp_optimize = \WP_Optimize();

        return $wp_optimize->get_optimizer();
    }

    /**
     * @return true|\WP_Error
     */
    private function ensure_wp_optimize_loaded(): true|\WP_Error {
        $this->load_plugin_functions();

        $state = $this->plugin_state();
        if ( ! $state['installed'] ) {
            return new \WP_Error( 'hpc_database_cleanup_wp_optimize_missing', 'WP-Optimize is not installed.' );
        }

        if ( ! $state['active'] ) {
            $result = activate_plugin( $this->plugin_file );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
        }

        if ( ! is_callable( 'WP_Optimize' ) ) {
            $path = $this->plugin_path();
            if ( '' !== $path && is_readable( $path ) ) {
                include_once $path;
            }
        }

        if ( ! is_callable( 'WP_Optimize' ) ) {
            return new \WP_Error( 'hpc_database_cleanup_wp_optimize_unloaded', 'WP-Optimize is active but its API did not load in this request.' );
        }

        return true;
    }

    private function load_plugin_functions(): void {
        if ( defined( 'ABSPATH' ) && ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }

    private function plugin_path(): string {
        if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
            return '';
        }

        return trailingslashit( WP_PLUGIN_DIR ) . $this->plugin_file;
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    private function session( string $session_id ): array|\WP_Error {
        $session_id = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $session_id ) ?: '';
        if ( '' === $session_id ) {
            return new \WP_Error( 'hpc_database_cleanup_missing_session', 'Cleanup session is missing.' );
        }

        $session = get_option( $this->session_option_name( $session_id ), false );
        if ( ! is_array( $session ) ) {
            return new \WP_Error( 'hpc_database_cleanup_expired_session', 'Cleanup session expired. Start a new run.' );
        }

        return $session;
    }

    private function session_option_name( string $session_id ): string {
        return self::SESSION_PREFIX . $session_id;
    }

    /**
     * @return array<int,string>
     */
    private function result_output( object $result ): array {
        $output = $result->output ?? [];
        if ( ! is_array( $output ) ) {
            $output = [ $output ];
        }

        return array_values(
            array_filter(
                array_map(
                    static fn( mixed $item ): string => wp_strip_all_tags( is_scalar( $item ) ? (string) $item : wp_json_encode( $item ) ),
                    $output
                ),
                static fn( string $item ): bool => '' !== trim( $item )
            )
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function public_result( object $result ): array {
        return [
            'output'       => $this->result_output( $result ),
            'meta'         => is_array( $result->meta ?? null ) ? $result->meta : [],
            'sql_commands' => array_values( array_map( 'strval', is_array( $result->sql_commands ?? null ) ? $result->sql_commands : [] ) ),
        ];
    }

    private function clean_table_name( string $table_name ): string {
        $table_name = trim( $table_name );

        return preg_match( '/^[A-Za-z0-9_$]+$/', $table_name ) ? $table_name : '';
    }

    private function format_bytes( int $bytes ): string {
        if ( function_exists( 'size_format' ) ) {
            return size_format( max( 0, $bytes ) );
        }

        return number_format( max( 0, $bytes ) ) . ' B';
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function log_entry( string $level, string $message, array $context = [] ): array {
        return [
            'time'    => function_exists( 'current_time' ) ? current_time( 'H:i:s' ) : gmdate( 'H:i:s' ),
            'level'   => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}
