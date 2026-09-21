<?php

namespace Hexa\PluginCore\LiteSpeedCache;

use RuntimeException;

/** Compatibility adapter for hosts that already inject setting callbacks. */
final class CallbackConfigurationAdapter implements ConfigurationAdapterInterface {
    /** @var callable */
    private mixed $reader;
    /** @var callable|null */
    private mixed $writer;

    public function __construct( callable $reader, ?callable $writer = null ) {
        $this->reader = $reader;
        $this->writer = $writer;
    }

    public function available(): bool {
        return is_callable( $this->reader );
    }

    public function litespeed_active(): bool {
        return defined( 'LSCWP_V' ) || class_exists( '\\LiteSpeed\\Core', false );
    }

    /** @return array<string,mixed> */
    public function inspect( SettingDefinition $setting ): array {
        try {
            $value    = call_user_func( $this->reader, $setting );
            $exists   = ! $value instanceof MissingValue;
            $writable = is_callable( $this->writer );

            return [
                'exists'           => $exists,
                'stored'           => $value,
                'effective'        => $value,
                'writable'         => $writable,
                'override_sources' => [],
                'provenance'       => [
                    'stored'      => $exists ? 'callback' : 'missing',
                    'effective'   => $exists ? 'callback' : 'missing',
                    'writability' => $writable ? 'writable' : 'read_only',
                ],
                'error'            => '',
            ];
        } catch ( \Throwable $error ) {
            return [
                'exists'           => false,
                'stored'           => LiteSpeedCacheService::missing_value(),
                'effective'        => LiteSpeedCacheService::missing_value(),
                'writable'         => false,
                'override_sources' => [],
                'provenance'       => [ 'stored' => 'missing', 'effective' => 'missing', 'writability' => 'inspection_failed' ],
                'error'            => 'inspection_failed',
            ];
        }
    }

    /** @param list<SettingDefinition> $settings */
    public function update( array $settings ): void {
        if ( ! is_callable( $this->writer ) ) {
            throw new RuntimeException( 'The injected LiteSpeed configuration adapter is read-only.' );
        }

        foreach ( $settings as $setting ) {
            if ( ! $setting instanceof SettingDefinition
                || ! (bool) call_user_func( $this->writer, $setting, $setting->expected() )
            ) {
                throw new RuntimeException( 'The injected LiteSpeed configuration writer rejected a setting.' );
            }
        }
    }
}
