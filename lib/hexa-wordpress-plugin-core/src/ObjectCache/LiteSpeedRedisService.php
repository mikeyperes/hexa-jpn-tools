<?php

namespace Hexa\PluginCore\ObjectCache;

final class LiteSpeedRedisService {
    private string $plugin_file = 'litespeed-cache/litespeed-cache.php';

    /** @param array<string,callable> $callbacks */
    public function __construct( private array $callbacks = [] ) {}

    /**
     * @return array<string,mixed>
     */
    public function status(): array {
        $this->load_plugin_functions();

        $installed = defined( 'WP_PLUGIN_DIR' ) && file_exists( trailingslashit( WP_PLUGIN_DIR ) . $this->plugin_file );
        $active    = $installed && function_exists( 'is_plugin_active' ) && is_plugin_active( $this->plugin_file );

        $object_enabled = $this->truthy_value( $this->conf_value( 'object', false ) );
        $object_kind    = $this->conf_value( 'object-kind', '' );
        $driver_redis   = $this->truthy_value( $object_kind );
        $host           = trim( (string) $this->conf_value( 'object-host', 'localhost' ) );
        $port           = (int) $this->conf_value( 'object-port', 6379 );
        $db_index       = (int) $this->conf_value( 'object-db_id', 0 );
        $dropin_path    = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/object-cache.php' : '';
        $dropin_present = '' !== $dropin_path && file_exists( $dropin_path );
        $dropin_lscwp   = $dropin_present && is_readable( $dropin_path ) && false !== strpos( (string) file_get_contents( $dropin_path, false, null, 0, 5000 ), 'LSCWP_OBJECT_CACHE' );

        $raw = $this->redis_connection_status( $host, $port, $db_index );
        $wp  = $this->wp_object_cache_round_trip();

        $wp_using_ext = $this->wp_using_external_object_cache();
        $enabled = $active && $object_enabled && $driver_redis && '' !== $host && $dropin_present && $dropin_lscwp;
        $active_running = $enabled && ! empty( $raw['connected'] ) && $wp_using_ext && ! empty( $wp['success'] );

        return [
            'installed'        => $installed,
            'plugin_active'    => $active,
            'enabled'          => $enabled,
            'active'           => $active_running,
            'object_enabled'   => $object_enabled,
            'driver_redis'     => $driver_redis,
            'host'             => $host,
            'port'             => $port,
            'db_index'         => $db_index,
            'dropin_present'   => $dropin_present,
            'dropin_litespeed' => $dropin_lscwp,
            'wp_using_ext'     => $wp_using_ext,
            'wp_round_trip'    => $wp,
            'redis'            => $raw,
            'message'          => $this->status_message( $installed, $active, $enabled, $active_running, $dropin_present, $dropin_lscwp, $wp_using_ext, $raw, $wp ),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function enable(): array {
        $this->load_plugin_functions();

        $before = $this->status();
        $log    = [
            $this->log_entry( 'info', 'Checking LiteSpeed Cache and Redis object-cache settings.' ),
        ];

        if ( ! $before['installed'] ) {
            return [
                'success' => false,
                'message' => 'LiteSpeed Cache is not installed.',
                'before'  => $before,
                'after'   => $before,
                'log'     => array_merge( $log, [ $this->log_entry( 'error', 'LiteSpeed Cache plugin file is missing.' ) ] ),
            ];
        }

        if ( ! $before['plugin_active'] && function_exists( 'activate_plugin' ) ) {
            $result = activate_plugin( $this->plugin_file );
            if ( is_wp_error( $result ) ) {
                return [
                    'success' => false,
                    'message' => $result->get_error_message(),
                    'before'  => $before,
                    'after'   => $this->status(),
                    'log'     => array_merge( $log, [ $this->log_entry( 'error', $result->get_error_message() ) ] ),
                ];
            }
            $log[] = $this->log_entry( 'success', 'Activated LiteSpeed Cache.' );
        }

        $host = trim( (string) $this->conf_value( 'object-host', '' ) );
        $port = (int) $this->conf_value( 'object-port', 0 );

        if ( '' === $host || 'localhost' === strtolower( $host ) || '127.0.0.1' === $host ) {
            $host = '' !== $host ? $host : 'localhost';
        }

        if ( $port <= 0 || 11211 === $port ) {
            $port = 6379;
        }

        $changes = [
            'object'            => 1,
            'object-kind'       => 1,
            'object-host'       => $host,
            'object-port'       => $port,
            'object-db_id'      => max( 0, (int) $this->conf_value( 'object-db_id', 0 ) ),
            'object-persistent' => 1,
            'object-admin'      => 1,
        ];
        $saved = $this->save_litespeed_configuration( $changes );
        if ( empty( $saved['success'] ) ) {
            return [
                'success' => false,
                'message' => (string) ( $saved['message'] ?? 'LiteSpeed configuration API is unavailable.' ),
                'before'  => $before,
                'after'   => $this->status(),
                'log'     => array_merge( $log, [ $this->log_entry( 'error', (string) ( $saved['message'] ?? 'LiteSpeed configuration API is unavailable.' ) ) ] ),
            ];
        }
        $log[] = $this->log_entry( 'success', 'Saved Redis settings through LiteSpeed Conf and refreshed its managed files.' );

        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }

        $after      = $this->status();
        $configured = ! empty( $after['enabled'] ) && ! empty( $after['redis']['connected'] );
        $active     = ! empty( $after['active'] );
        $success    = $configured;
        $message    = $active
            ? 'LiteSpeed Redis object cache is enabled and active.'
            : ( $configured
                ? 'LiteSpeed Redis is configured with its managed drop-in; verify it on the next WordPress request.'
                : (string) $after['message'] );

        return [
            'success' => $success,
            'message' => $message,
            'before'  => $before,
            'after'   => $after,
            'configured' => $configured,
            'active'     => $active,
            'requires_new_request' => $configured && ! $active,
            'log'     => $log,
        ];
    }

    /** @param array<string,mixed> $changes @return array{success:bool,message:string} */
    private function save_litespeed_configuration( array $changes ): array {
        if ( isset( $this->callbacks['save_configuration'] ) ) {
            return (array) call_user_func( $this->callbacks['save_configuration'], $changes );
        }
        if ( ! class_exists( '\LiteSpeed\Conf' ) || ! method_exists( '\LiteSpeed\Conf', 'cls' ) ) {
            return [ 'success' => false, 'message' => 'LiteSpeed configuration API is unavailable.' ];
        }
        try {
            $conf = \LiteSpeed\Conf::cls();
            if ( ! is_object( $conf ) || ! method_exists( $conf, 'update_confs' ) ) {
                return [ 'success' => false, 'message' => 'LiteSpeed configuration API is unavailable.' ];
            }
            $conf->update_confs( $changes );
            return [ 'success' => true, 'message' => 'LiteSpeed configuration saved.' ];
        } catch ( \Throwable $throwable ) {
            return [ 'success' => false, 'message' => 'LiteSpeed could not save its Redis configuration.' ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function redis_connection_status( string $host, int $port, int $db_index ): array {
        if ( isset( $this->callbacks['redis_connection_status'] ) ) {
            return array_merge(
                [ 'extension' => false, 'connected' => false, 'version' => '', 'memory' => '', 'keys' => null, 'error' => '' ],
                (array) call_user_func( $this->callbacks['redis_connection_status'], $host, $port, $db_index )
            );
        }
        $result = [
            'extension' => extension_loaded( 'redis' ),
            'connected' => false,
            'version'   => '',
            'memory'    => '',
            'keys'      => null,
            'error'     => '',
        ];

        if ( ! $result['extension'] ) {
            $result['error'] = 'Redis PHP extension is not installed.';
            return $result;
        }

        if ( '' === $host ) {
            $result['error'] = 'Redis host is empty.';
            return $result;
        }

        try {
            $redis = new \Redis();
            if ( ! @$redis->connect( $host, $port > 0 ? $port : 6379, 2.0 ) ) {
                $result['error'] = 'Redis connection failed.';
                return $result;
            }

            $password = (string) $this->conf_value( 'object-pswd', '' );
            $user     = (string) $this->conf_value( 'object-user', '' );
            if ( '' !== $password ) {
                '' !== $user ? $redis->auth( [ $user, $password ] ) : $redis->auth( $password );
            }

            if ( $db_index > 0 ) {
                $redis->select( $db_index );
            }

            $pong = $redis->rawCommand( 'PING' );
            if ( ! in_array( $pong, [ 'PONG', '+PONG', true ], true ) ) {
                $result['error'] = 'Redis PING did not return PONG.';
                return $result;
            }

            $info = @$redis->info();
            $result['connected'] = true;
            if ( is_array( $info ) ) {
                $result['version'] = (string) ( $info['redis_version'] ?? '' );
                $result['memory']  = (string) ( $info['used_memory_human'] ?? '' );
            }
            $result['keys'] = @$redis->dbSize();
            @$redis->close();
        } catch ( \Throwable $throwable ) {
            $result['error'] = $throwable->getMessage();
        }

        return $result;
    }

    /**
     * @return array<string,mixed>
     */
    private function wp_object_cache_round_trip(): array {
        if ( isset( $this->callbacks['wp_object_cache_round_trip'] ) ) {
            return (array) call_user_func( $this->callbacks['wp_object_cache_round_trip'] );
        }
        if ( ! function_exists( 'wp_cache_set' ) || ! function_exists( 'wp_cache_get' ) || ! function_exists( 'wp_cache_delete' ) ) {
            return [
                'success' => false,
                'message' => 'WordPress object-cache functions are unavailable.',
            ];
        }

        $key   = 'hpc_litespeed_redis_' . wp_generate_uuid4();
        $value = 'ok-' . microtime( true );

        $set = wp_cache_set( $key, $value, 'hpc_object_cache', 60 );
        $got = wp_cache_get( $key, 'hpc_object_cache' );
        wp_cache_delete( $key, 'hpc_object_cache' );

        $success = false !== $set && $got === $value;

        return [
            'success' => $success,
            'message' => $success ? 'WordPress object-cache set/get/delete succeeded.' : 'WordPress object-cache round trip failed.',
        ];
    }

    private function status_message( bool $installed, bool $active, bool $enabled, bool $active_running, bool $dropin_present, bool $dropin_lscwp, bool $wp_using_ext, array $raw, array $wp ): string {
        if ( ! $installed ) {
            return 'LiteSpeed Cache is not installed.';
        }
        if ( ! $active ) {
            return 'LiteSpeed Cache is installed but inactive.';
        }
        if ( $dropin_present && ! $dropin_lscwp ) {
            return 'Another object-cache drop-in is present; LiteSpeed Redis was not reported as active.';
        }
        if ( ! $enabled ) {
            return 'Redis object cache is not fully enabled in LiteSpeed.';
        }
        if ( empty( $raw['connected'] ) ) {
            return (string) ( $raw['error'] ?? 'Redis connection failed.' );
        }
        if ( ! $wp_using_ext ) {
            return 'LiteSpeed Redis is configured; a new WordPress request is required to load its managed drop-in.';
        }
        if ( empty( $wp['success'] ) ) {
            return (string) ( $wp['message'] ?? 'WordPress object-cache test failed.' );
        }

        return $active_running ? 'Redis object cache is enabled and active.' : 'Redis object cache needs attention.';
    }

    private function wp_using_external_object_cache(): bool {
        if ( isset( $this->callbacks['wp_using_ext_object_cache'] ) ) {
            return (bool) call_user_func( $this->callbacks['wp_using_ext_object_cache'] );
        }
        return function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache();
    }

    private function conf_value( string $id, mixed $default ): mixed {
        if ( isset( $this->callbacks['conf_value'] ) ) {
            return call_user_func( $this->callbacks['conf_value'], $id, $default );
        }
        if ( class_exists( '\LiteSpeed\Conf' ) && method_exists( '\LiteSpeed\Conf', 'cls' ) ) {
            try {
                $conf = \LiteSpeed\Conf::cls();
                if ( is_object( $conf ) && method_exists( $conf, 'conf' ) ) {
                    return $conf->conf( $id );
                }
            } catch ( \Throwable $throwable ) {
                // Fall back to the legacy option only for older LiteSpeed versions.
            }
        }
        return get_option( 'litespeed.conf.' . $id, $default );
    }

    private function truthy_value( mixed $value ): bool {
        if ( is_bool( $value ) ) {
            return $value;
        }
        if ( is_numeric( $value ) ) {
            return (int) $value === 1;
        }

        return in_array( strtolower( trim( (string) $value ) ), [ '1', 'true', 'yes', 'on', 'redis' ], true );
    }

    private function load_plugin_functions(): void {
        if ( defined( 'ABSPATH' ) && ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function log_entry( string $level, string $message ): array {
        return [
            'time'    => function_exists( 'current_time' ) ? current_time( 'H:i:s' ) : gmdate( 'H:i:s' ),
            'level'   => $level,
            'message' => $message,
            'context' => [],
        ];
    }
}
