<?php

namespace Hexa\PluginCore\LiteSpeedCache;

/** Generic audit/apply/verify engine for host-owned LiteSpeed profiles. */
final class LiteSpeedCacheService {
    private Profile $profile;
    private ConfigurationAdapterInterface $adapter;

    public function __construct(
        Profile|array $profile,
        ConfigurationAdapterInterface|callable|null $adapter_or_reader = null,
        ?callable $writer = null
    ) {
        $this->profile = is_array( $profile ) ? new Profile( $profile ) : $profile;

        if ( $adapter_or_reader instanceof ConfigurationAdapterInterface ) {
            $this->adapter = $adapter_or_reader;
        } elseif ( is_callable( $adapter_or_reader ) ) {
            $this->adapter = new CallbackConfigurationAdapter( $adapter_or_reader, $writer );
        } else {
            $this->adapter = new LiteSpeedConfAdapter();
        }
    }

    public static function missing_value(): MissingValue {
        return MissingValue::instance();
    }

    /** @return array<string,mixed> */
    public function audit(): array {
        $items      = [];
        $matching   = 0;
        $missing    = 0;
        $overridden = 0;

        foreach ( $this->profile->settings() as $setting ) {
            $inspection = array_merge(
                [
                    'exists'           => false,
                    'stored'           => null,
                    'effective'        => null,
                    'writable'         => false,
                    'override_sources' => [],
                    'provenance'       => [],
                    'error'            => '',
                ],
                $this->adapter->inspect( $setting )
            );
            $exists     = ! empty( $inspection['exists'] );
            $sources    = array_values( array_unique( array_map( 'strval', (array) $inspection['override_sources'] ) ) );
            $effective  = $inspection['effective'];
            $match      = $exists && $this->equivalent( $effective, $setting->expected(), $setting->cast() );

            $matching   += $match ? 1 : 0;
            $missing    += $exists ? 0 : 1;
            $overridden += [] !== $sources ? 1 : 0;

            $items[] = [
                'id'               => $setting->id(),
                'label'            => $setting->label(),
                'description'      => $setting->description(),
                'option_id'        => $setting->option_name(),
                'option_name'      => $setting->option_name(),
                'option_path'      => $setting->option_path(),
                'expected'         => $setting->expected(),
                'actual'           => $effective,
                'effective'        => $effective,
                'stored'           => $inspection['stored'],
                'exists'           => $exists,
                'missing'          => ! $exists,
                'match'            => $match,
                'writable'         => ! empty( $inspection['writable'] ),
                'override_sources' => $sources,
                'provenance'       => (array) $inspection['provenance'],
                'error'            => (string) $inspection['error'],
            ];
        }

        $total      = count( $items );
        $mismatched = $total - $matching;
        $available  = $this->adapter->available();
        $success    = $available && 0 === $mismatched;

        return [
            'success'          => $success,
            'profile'          => $this->profile->to_array(),
            'status'           => $available ? ( $success ? 'matching' : 'mismatched' ) : 'unavailable',
            'available'        => $available,
            'litespeed_active' => $this->adapter->litespeed_active(),
            'total'            => $total,
            'matching'         => $matching,
            'mismatched'       => $mismatched,
            'missing'          => $missing,
            'overridden'       => $overridden,
            'review_required'  => ! $success,
            'items'            => $items,
        ];
    }

    /** @return array<string,mixed> */
    public function apply(): array {
        $before  = $this->audit();
        $blocked = [];

        if ( ! $this->adapter->available() ) {
            return $this->apply_result( $before, $before, [], [], 'LiteSpeed configuration API is unavailable.' );
        }

        $settings = [];
        foreach ( $this->profile->settings() as $setting ) {
            $settings[ $setting->id() ] = $setting;
        }

        $changes = [];
        foreach ( (array) $before['items'] as $item ) {
            if ( ! empty( $item['match'] ) ) {
                continue;
            }

            $id      = (string) ( $item['id'] ?? '' );
            $setting = $settings[ $id ] ?? null;
            if ( ! $setting instanceof SettingDefinition ) {
                $blocked[ $id ] = 'definition_missing';
                continue;
            }
            if ( '' !== (string) ( $item['error'] ?? '' ) && 'option_missing' !== (string) $item['error'] ) {
                $blocked[ $id ] = (string) $item['error'];
                continue;
            }
            if ( empty( $item['exists'] ) && empty( $item['writable'] ) ) {
                $blocked[ $id ] = 'option_missing';
                continue;
            }
            if ( empty( $item['writable'] ) ) {
                $sources        = array_map( 'strval', (array) ( $item['override_sources'] ?? [] ) );
                $blocked[ $id ] = [] !== $sources
                    ? 'effective_override:' . implode( ',', $sources )
                    : 'read_only';
                continue;
            }

            $changes[] = $setting;
        }

        $save_error = '';
        if ( [] !== $changes ) {
            try {
                // One adapter call preserves LiteSpeed's single update_confs batch.
                $this->adapter->update( $changes );
            } catch ( \Throwable $error ) {
                $save_error = 'LiteSpeed rejected or could not synchronize the requested configuration.';
            }
        }

        $after = $this->audit();
        return $this->apply_result( $before, $after, $this->requested_option_ids( $changes ), $blocked, $save_error );
    }

    /** @return array<string,mixed> */
    public function verify(): array {
        $result           = $this->audit();
        $result['status'] = $result['success']
            ? 'verified'
            : ( $result['available'] ? 'verification_failed' : 'unavailable' );
        return $result;
    }

    /**
     * @param list<string>         $requested
     * @param array<string,string> $blocked
     * @return array<string,mixed>
     */
    private function apply_result( array $before, array $after, array $requested, array $blocked, string $save_error ): array {
        $before_items = [];
        foreach ( (array) ( $before['items'] ?? [] ) as $item ) {
            $before_items[ (string) ( $item['id'] ?? '' ) ] = $item;
        }

        $updated = 0;
        foreach ( (array) ( $after['items'] ?? [] ) as $item ) {
            $id = (string) ( $item['id'] ?? '' );
            if ( ! empty( $item['match'] ) && empty( $before_items[ $id ]['match'] ) ) {
                ++$updated;
            }
        }

        $success = '' === $save_error && ! empty( $after['success'] );
        return [
            'success'          => $success,
            'profile'          => $this->profile->to_array(),
            'status'           => $success ? 'matching' : 'failed',
            'available'        => ! empty( $after['available'] ),
            'litespeed_active' => ! empty( $after['litespeed_active'] ),
            'total'            => (int) ( $after['total'] ?? 0 ),
            'matching'         => (int) ( $after['matching'] ?? 0 ),
            'mismatched'       => (int) ( $after['mismatched'] ?? 0 ),
            'missing'          => (int) ( $after['missing'] ?? 0 ),
            'overridden'       => (int) ( $after['overridden'] ?? 0 ),
            'review_required'  => ! $success,
            'items'            => (array) ( $after['items'] ?? [] ),
            'before'           => $before,
            'after'            => $after,
            'requested'        => $requested,
            'updated'          => $updated,
            'failed'           => (int) ( $after['mismatched'] ?? 0 ),
            'blocked'          => $blocked,
            'error'            => $save_error,
        ];
    }

    private function equivalent( mixed $actual, mixed $expected, string $cast ): bool {
        if ( $actual instanceof MissingValue || $expected instanceof MissingValue ) {
            return false;
        }

        return $this->cast( $actual, $cast ) === $this->cast( $expected, $cast );
    }

    private function cast( mixed $value, string $cast ): mixed {
        return match ( strtolower( $cast ) ) {
            'bool', 'boolean' => is_string( $value )
                ? in_array( strtolower( trim( $value ) ), [ '1', 'true', 'yes', 'on', 'enabled' ], true )
                : (bool) $value,
            'int', 'integer'  => (int) $value,
            'float', 'number' => (float) $value,
            'string'          => (string) $value,
            'array'           => array_values( array_map( 'strval', (array) $value ) ),
            default           => $value,
        };
    }

    /** @param list<SettingDefinition> $settings
     *  @return list<string>
     */
    private function requested_option_ids( array $settings ): array {
        return array_values(
            array_unique(
                array_map(
                    static fn( SettingDefinition $setting ): string => $setting->option_name(),
                    $settings
                )
            )
        );
    }
}
