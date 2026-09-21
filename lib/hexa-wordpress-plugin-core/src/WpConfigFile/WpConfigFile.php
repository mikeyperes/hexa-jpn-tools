<?php

namespace Hexa\PluginCore\WpConfigFile;

final class WpConfigFile {
    public const DEFAULT_MIN_BYTES = 500;

    private const SENSITIVE_CONSTANTS = [
        'DB_NAME',
        'DB_USER',
        'DB_PASSWORD',
        'DB_HOST',
        'DB_CHARSET',
        'DB_COLLATE',
        'AUTH_KEY',
        'SECURE_AUTH_KEY',
        'LOGGED_IN_KEY',
        'NONCE_KEY',
        'AUTH_SALT',
        'SECURE_AUTH_SALT',
        'LOGGED_IN_SALT',
        'NONCE_SALT',
    ];

    private function __construct() {}

    public static function default_path(): string {
        return defined( 'ABSPATH' ) ? ABSPATH . 'wp-config.php' : '';
    }

    /**
     * @param array<string,mixed> $constants_to_update
     * @param array<string,mixed> $args
     * @return array{status:bool,message:string}
     */
    public static function modify_constants( array $constants_to_update, ?string $path = null, array $args = [] ): array {
        $wp_config_path = $path ?: self::default_path();
        $minimum_bytes  = isset( $args['minimum_bytes'] ) ? max( 1, (int) $args['minimum_bytes'] ) : self::DEFAULT_MIN_BYTES;

        if ( $wp_config_path === '' || ! file_exists( $wp_config_path ) || ! is_writable( $wp_config_path ) ) {
            return [
                'status'  => false,
                'message' => 'wp-config.php does not exist or is not writable.',
            ];
        }

        $config_content = file_get_contents( $wp_config_path );
        if ( false === $config_content || '' === $config_content ) {
            return [
                'status'  => false,
                'message' => 'Failed to read wp-config.php or file is empty.',
            ];
        }

        $original_length = strlen( $config_content );
        $validation      = self::validate_content( $config_content, $minimum_bytes, 'wp-config.php' );
        if ( true !== $validation ) {
            return $validation;
        }

        $backup_path = isset( $args['backup_path'] ) && is_string( $args['backup_path'] )
            ? $args['backup_path']
            : $wp_config_path . '.hexa-backup-' . time();

        if ( false === file_put_contents( $backup_path, $config_content ) ) {
            return [
                'status'  => false,
                'message' => 'Failed to create backup. Aborting modification for safety.',
            ];
        }

        $modified_content = $config_content;

        foreach ( $constants_to_update as $constant => $raw_value ) {
            $result = self::apply_update( $modified_content, (string) $constant, $raw_value );

            if ( is_array( $result ) && isset( $result['status'] ) ) {
                @unlink( $backup_path );
                return $result;
            }

            $modified_content = (string) $result;
        }

        $final_length = strlen( $modified_content );
        if ( $final_length < ( $original_length * 0.7 ) ) {
            @unlink( $backup_path );
            return [
                'status'  => false,
                'message' => 'Modified content shrank too much (' . $final_length . ' vs ' . $original_length . ' bytes). Possible corruption. Aborting.',
            ];
        }

        $validation = self::validate_content( $modified_content, $minimum_bytes, 'Modified content' );
        if ( true !== $validation ) {
            @unlink( $backup_path );
            return $validation;
        }

        $write_result = file_put_contents( $wp_config_path, $modified_content );
        if ( false === $write_result ) {
            if ( file_exists( $backup_path ) ) {
                @copy( $backup_path, $wp_config_path );
            }

            @unlink( $backup_path );
            return [
                'status'  => false,
                'message' => 'Failed to write wp-config.php. Backup restored if possible.',
            ];
        }

        $permanent_backup = isset( $args['permanent_backup_path'] ) && is_string( $args['permanent_backup_path'] )
            ? $args['permanent_backup_path']
            : $wp_config_path . '.hexa-last-backup';

        @rename( $backup_path, $permanent_backup );

        return [
            'status'  => true,
            'message' => 'Constants updated successfully. Backup saved as ' . basename( $permanent_backup ),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function defined_constants( array $exclude = [] ): array {
        $exclude       = $exclude ?: self::SENSITIVE_CONSTANTS;
        $all_constants = get_defined_constants( true );
        $user          = isset( $all_constants['user'] ) && is_array( $all_constants['user'] ) ? $all_constants['user'] : [];

        return array_filter(
            $user,
            static fn( $key ) => ! in_array( $key, $exclude, true ),
            ARRAY_FILTER_USE_KEY
        );
    }

    public static function constant_status( string $constant_name ): mixed {
        if ( ! defined( $constant_name ) ) {
            return 'undefined';
        }

        $constant_value = constant( $constant_name );
        if ( is_bool( $constant_value ) ) {
            return $constant_value ? 'true' : 'false';
        }

        return $constant_value;
    }

    public static function get_php_ini_value( string $setting_name, ?string $path = null, ?callable $logger = null ): string {
        $value          = ini_get( $setting_name );
        $wp_config_path = $path ?: self::default_path();

        if ( $wp_config_path && file_exists( $wp_config_path ) ) {
            $config_content = file_get_contents( $wp_config_path );

            if ( is_string( $config_content ) ) {
                $pattern = "/ini_set\(\s*['\"]" . preg_quote( $setting_name, '/' ) . "['\"]\s*,\s*['\"](.*?)['\"]\s*\);/";

                if ( preg_match( $pattern, $config_content, $matches ) ) {
                    $value = $matches[1];

                    if ( $logger ) {
                        call_user_func( $logger, 'Overriding ini_get with value from wp-config.php for ' . $setting_name . ': ' . var_export( $value, true ), false );
                    }
                }
            }
        }

        return false !== $value ? (string) $value : 'unknown';
    }

    /**
     * @param array<string,mixed> $args
     */
    public static function toggle_php_ini_value( string $setting_name, string $new_value, ?string $path = null, ?callable $logger = null, array $args = [] ): string {
        $result = ini_set( $setting_name, $new_value );

        if ( false === $result ) {
            if ( $logger ) {
                call_user_func( $logger, "Error: Failed to update {$setting_name} to {$new_value}.", true );
            }

            return 'fail';
        }

        $modified = self::modify_constants(
            [
                'ini_' . $setting_name => [
                    'type'  => 'ini',
                    'value' => $new_value,
                ],
            ],
            $path,
            $args
        );

        if ( ! empty( $modified['status'] ) ) {
            if ( $logger ) {
                call_user_func( $logger, "Success: Changes to {$setting_name} have been persisted in wp-config.php.", true );
            }

            return 'success';
        }

        if ( $logger ) {
            call_user_func( $logger, 'Error: ' . ( $modified['message'] ?? 'Failed to write changes to wp-config.php.' ), true );
        }

        return 'fail';
    }

    /**
     * @return string|array{status:bool,message:string}
     */
    private static function apply_update( string $content, string $constant, mixed $raw_value ): string|array {
        $type  = 'define';
        $value = $raw_value;

        if ( is_array( $raw_value ) && isset( $raw_value['type'], $raw_value['value'] ) ) {
            $type  = (string) $raw_value['type'];
            $value = $raw_value['value'];
        }

        if ( 0 === stripos( $constant, 'ini_' ) ) {
            $type     = 'ini';
            $constant = substr( $constant, 4 );
        }

        if ( is_string( $value ) ) {
            if ( 'true' === strtolower( $value ) ) {
                $value = true;
            } elseif ( 'false' === strtolower( $value ) ) {
                $value = false;
            }
        }

        if ( 'ini' === $type ) {
            return self::apply_ini_update( $content, $constant, $value );
        }

        return self::apply_define_update( $content, $constant, $value );
    }

    /**
     * @return string|array{status:bool,message:string}
     */
    private static function apply_ini_update( string $content, string $setting_name, mixed $value ): string|array {
        $escaped_value = str_replace( "'", "\\'", (string) $value );
        $new_line      = "ini_set( '{$setting_name}', '{$escaped_value}' );";
        $pattern       = "/ini_set\s*\(\s*['\"]" . preg_quote( $setting_name, '/' ) . "['\"]\s*,\s*['\"].*?['\"]\s*\)\s*;\s*/i";
        $result        = preg_replace( $pattern, '', $content );

        if ( null === $result ) {
            return [
                'status'  => false,
                'message' => "Regex error processing ini_set for {$setting_name}. Aborting.",
            ];
        }

        return self::insert_after_php_open( $result, $new_line );
    }

    /**
     * @return string|array{status:bool,message:string}
     */
    private static function apply_define_update( string $content, string $constant, mixed $value ): string|array {
        $constant = strtoupper( $constant );

        if ( is_bool( $value ) ) {
            $new_constant = $value
                ? "define( '{$constant}', true );"
                : "define( '{$constant}', false );";
        } elseif ( is_numeric( $value ) ) {
            $new_constant = "define( '{$constant}', {$value} );";
        } else {
            $escaped      = str_replace( "'", "\\'", (string) $value );
            $new_constant = "define( '{$constant}', '{$escaped}' );";
        }

        $pattern = "/define\s*\(\s*['\"]" . preg_quote( $constant, '/' ) . "['\"]\s*,\s*.*?\)\s*;\s*/i";
        $result  = preg_replace( $pattern, '', $content );

        if ( null === $result ) {
            return [
                'status'  => false,
                'message' => "Regex error removing old define for {$constant}. Aborting.",
            ];
        }

        return self::insert_after_php_open( $result, $new_constant );
    }

    private static function insert_after_php_open( string $content, string $line ): string {
        $php_pos = strpos( $content, '<?php' );
        if ( false === $php_pos ) {
            return $content;
        }

        $insert_pos = $php_pos + 5;
        while (
            isset( $content[ $insert_pos ] )
            && ( $content[ $insert_pos ] === ' ' || $content[ $insert_pos ] === "\t" )
        ) {
            $insert_pos++;
        }

        return substr( $content, 0, $insert_pos ) . "\n{$line}" . substr( $content, $insert_pos );
    }

    /**
     * @return true|array{status:bool,message:string}
     */
    private static function validate_content( string $content, int $minimum_bytes, string $label ) {
        $length = strlen( $content );

        if ( $length < $minimum_bytes ) {
            return [
                'status'  => false,
                'message' => $label . ' appears too small (' . $length . ' bytes). Aborting to prevent corruption.',
            ];
        }

        if ( false === strpos( $content, '<?php' ) ) {
            return [
                'status'  => false,
                'message' => $label . ' missing <?php tag. File may be corrupted.',
            ];
        }

        if ( false === strpos( $content, 'DB_NAME' ) && false === strpos( $content, 'wp-settings.php' ) ) {
            return [
                'status'  => false,
                'message' => $label . ' missing DB_NAME or wp-settings.php reference. File may be corrupted.',
            ];
        }

        return true;
    }
}
