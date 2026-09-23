<?php

namespace Hexa\PluginCore\PublicComponents;

/**
 * Holds one component's profiles for the current request.
 *
 * Hosts register profiles from their own boot code, or late through the
 * component's registration action, which fires once the first time a profile
 * is resolved. Profiles are normalized lazily at that first resolution, so
 * custom filter types and profiles may register in any order. A profile that
 * cannot be served safely is reported (`_doing_it_wrong`) and skipped instead
 * of breaking the page.
 */
final class ProfileStore {
    /** @var array<string,array<string,mixed>> */
    private array $profiles = [];

    /** @var array<string,array{0:string,1:array<string,mixed>}> Raw registrations awaiting normalization. */
    private array $pending = [];

    private bool $late_registration_done = false;

    private string $late_action;

    /** @var callable(string,array<string,mixed>):array<string,mixed> */
    private $normalizer;

    /** @param callable(string,array<string,mixed>):array<string,mixed> $normalizer Throws when a profile cannot be served. */
    public function __construct( string $late_action, callable $normalizer ) {
        $this->late_action = $late_action;
        $this->normalizer  = $normalizer;
    }

    /** @param array<string,mixed> $config */
    public function register( string $id, array $config ): void {
        $key = ProfileValues::key( $id );
        unset( $this->profiles[ $key ] );
        $this->pending[ $key ] = [ $id, $config ];
    }

    /** @return array<string,mixed>|null */
    public function get( string $id ): ?array {
        $this->resolve();

        return $this->profiles[ ProfileValues::key( $id ) ] ?? null;
    }

    /** @return array<string,array<string,mixed>> */
    public function all(): array {
        $this->resolve();

        return $this->profiles;
    }

    /** Test helper: forget every registered profile. */
    public function reset(): void {
        $this->profiles = [];
        $this->pending  = [];
        $this->late_registration_done = false;
    }

    private function resolve(): void {
        if ( ! $this->late_registration_done ) {
            $this->late_registration_done = true;
            if ( function_exists( 'do_action' ) ) {
                do_action( $this->late_action );
            }
        }

        foreach ( $this->pending as $key => [ $id, $config ] ) {
            unset( $this->pending[ $key ] );
            try {
                $profile = call_user_func( $this->normalizer, $id, $config );
                $this->profiles[ $profile['id'] ] = $profile;
            } catch ( \InvalidArgumentException $exception ) {
                if ( function_exists( '_doing_it_wrong' ) ) {
                    _doing_it_wrong( self::class, esc_html( $exception->getMessage() ), '3.2.0' );
                }
            }
        }
    }
}
