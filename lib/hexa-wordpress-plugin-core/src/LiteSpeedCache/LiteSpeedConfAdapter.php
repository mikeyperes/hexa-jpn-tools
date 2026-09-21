<?php

namespace Hexa\PluginCore\LiteSpeedCache;

use RuntimeException;

/** Official LiteSpeed Cache configuration adapter backed by LiteSpeed\Conf. */
final class LiteSpeedConfAdapter implements ConfigurationAdapterInterface {
    private ?object $configuration;
    private bool $resolved;

    public function __construct( ?object $configuration = null ) {
        $this->configuration = $configuration;
        $this->resolved      = is_object( $configuration );
    }

    public function available(): bool {
        return is_object( $this->configuration() );
    }

    public function litespeed_active(): bool {
        return $this->available()
            && ( defined( 'LSCWP_V' ) || class_exists( '\\LiteSpeed\\Core', false ) );
    }

    /** @return array<string,mixed> */
    public function inspect( SettingDefinition $setting ): array {
        $configuration = $this->configuration();
        $option_id     = $setting->option_name();

        if ( ! is_object( $configuration ) || '' === $option_id ) {
            return $this->empty_inspection( is_object( $configuration ) ? 'option_missing' : 'adapter_unavailable' );
        }

        try {
            $has_local_helper   = method_exists( $configuration, 'has_conf' );
            $has_network_helper = method_exists( $configuration, 'has_network_conf' );
            $has_local          = $has_local_helper && (bool) $configuration->has_conf( $option_id );
            $has_network        = $has_network_helper && (bool) $configuration->has_network_conf( $option_id );
            $stored             = $configuration->conf( $option_id, true );
            $effective          = $configuration->conf( $option_id );
            $has_presence_api   = $has_local_helper || $has_network_helper;
            $exists             = $has_presence_api ? ( $has_local || $has_network ) : null !== $stored;

            // Some older builds expose only one presence helper. Preserve their
            // non-null original value as the fallback existence signal.
            if ( ! $exists && ( ! $has_local_helper || ! $has_network_helper ) && null !== $stored ) {
                $exists = true;
            }

            $sources = [];
            if ( $exists && $this->overwritten( $configuration, 'const_overwritten', $option_id ) ) {
                $sources[] = 'constant';
            }
            if ( $exists && $this->overwritten( $configuration, 'primary_overwritten', $option_id ) ) {
                $sources[] = 'network_primary';
            }
            if ( $exists && $this->overwritten( $configuration, 'filter_overwritten', $option_id ) ) {
                $sources[] = 'filter';
            }
            if ( $exists && $this->overwritten( $configuration, 'deprecated_filter_overwritten', $option_id ) ) {
                $sources[] = 'legacy_filter';
            }
            if ( $exists && $this->overwritten( $configuration, 'server_overwritten', $option_id ) ) {
                $sources[] = 'server';
            }

            $network_admin = function_exists( 'is_network_admin' ) && is_network_admin();
            if ( $exists && $has_network && method_exists( $configuration, 'network_conf' ) ) {
                $network_value = $configuration->network_conf( $option_id );
                if ( $network_admin
                    || ( ! $has_local && $network_value === $effective )
                    || ( $stored !== $effective && $network_value === $effective )
                ) {
                    $sources[] = 'network';
                }
            }

            if ( $exists && [] === $sources && $stored !== $effective ) {
                $sources[] = 'external';
            }

            $sources       = array_values( array_unique( $sources ) );
            $stored_source = $network_admin && $has_network
                ? 'network'
                : ( $has_local ? 'local' : ( $has_network ? 'network' : ( $exists ? 'configuration' : 'missing' ) ) );
            $writable      = $exists && [] === $sources;

            return [
                'exists'           => $exists,
                'stored'           => $stored,
                'effective'        => $effective,
                'writable'         => $writable,
                'override_sources' => $sources,
                'provenance'       => [
                    'stored'        => $stored_source,
                    'effective'     => $sources[0] ?? $stored_source,
                    'writability'   => $writable ? 'writable' : ( $exists ? 'effective_override' : 'option_missing' ),
                    'has_local'     => $has_local,
                    'has_network'   => $has_network,
                    'network_admin' => $network_admin,
                ],
                'error'            => '',
            ];
        } catch ( \Throwable $error ) {
            return $this->empty_inspection( 'inspection_failed' );
        }
    }

    /** @param list<SettingDefinition> $settings */
    public function update( array $settings ): void {
        $configuration = $this->configuration();
        if ( ! is_object( $configuration ) ) {
            throw new RuntimeException( 'LiteSpeed configuration API is unavailable.' );
        }

        $changes = [];
        foreach ( $settings as $setting ) {
            if ( $setting instanceof SettingDefinition && '' !== $setting->option_name() ) {
                $changes[ $setting->option_name() ] = $setting->expected();
            }
        }

        if ( [] !== $changes ) {
            // LiteSpeed performs its own type normalization, purge decisions,
            // cron work, generated-file updates, and cloud synchronization here.
            $configuration->update_confs( $changes );
        }
    }

    private function configuration(): ?object {
        if ( $this->resolved ) {
            return $this->valid_configuration( $this->configuration ) ? $this->configuration : null;
        }

        $this->resolved = true;
        if ( ! class_exists( '\\LiteSpeed\\Conf' ) || ! is_callable( [ '\\LiteSpeed\\Conf', 'cls' ] ) ) {
            return null;
        }

        try {
            $configuration = \LiteSpeed\Conf::cls();
            if ( ! $this->valid_configuration( $configuration ) ) {
                return null;
            }
            $this->configuration = $configuration;
            return $this->configuration;
        } catch ( \Throwable $error ) {
            return null;
        }
    }

    private function valid_configuration( mixed $configuration ): bool {
        return is_object( $configuration )
            && method_exists( $configuration, 'conf' )
            && method_exists( $configuration, 'update_confs' );
    }

    private function overwritten( object $configuration, string $method, string $option_id ): bool {
        if ( ! method_exists( $configuration, $method ) ) {
            return false;
        }

        try {
            return null !== $configuration->{$method}( $option_id );
        } catch ( \Throwable $error ) {
            return false;
        }
    }

    /** @return array<string,mixed> */
    private function empty_inspection( string $error ): array {
        return [
            'exists'           => false,
            'stored'           => null,
            'effective'        => null,
            'writable'         => false,
            'override_sources' => [],
            'provenance'       => [
                'stored'      => 'missing',
                'effective'   => 'missing',
                'writability' => $error,
                'has_local'   => false,
                'has_network' => false,
            ],
            'error'            => $error,
        ];
    }
}
