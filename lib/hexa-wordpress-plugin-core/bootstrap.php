<?php

/**
 * Registers vendored Hexa Plugin Core packages before any namespaced class loads.
 *
 * This file intentionally stays in the global namespace. Every host plugin can
 * require its own vendored copy without declaring the registry more than once.
 */
if ( ! class_exists( 'HexaPluginCorePackageRegistry', false ) ) {
    final class HexaPluginCorePackageRegistry {
        private const CLASS_PREFIX = 'Hexa\\PluginCore\\';

        /** @var array<string,array<string,mixed>> */
        private static array $candidates = [];

        /** @var array<string,mixed>|null */
        private static ?array $selected = null;

        /** @var array<int,array<string,mixed>> */
        private static array $issues = [];

        private static bool $resolve_hook_registered = false;
        private static bool $autoload_registered = false;

        /**
         * @param array<string,mixed> $requirements
         */
        public static function register_candidate( string $host, string $core_root, array $requirements = [] ): void {
            $host      = trim( $host );
            $core_root = rtrim( $core_root, '/\\' );
            $real_root = realpath( $core_root );

            if ( '' === $host || false === $real_root || ! is_dir( $real_root . DIRECTORY_SEPARATOR . 'src' ) ) {
                self::$issues[] = [
                    'type'      => 'invalid_candidate',
                    'host'      => $host,
                    'core_root' => $core_root,
                    'message'   => 'The registered Hexa Plugin Core package root is invalid.',
                ];
                return;
            }

            $version = self::read_file_value( $real_root . DIRECTORY_SEPARATOR . 'VERSION', '0.0.0' );
            $hash    = self::read_file_value( $real_root . DIRECTORY_SEPARATOR . 'PACKAGE_HASH', '' );

            if ( '' === $hash ) {
                $hash = self::calculate_source_hash( $real_root );
            }

            $candidate_key = $host . '|' . $real_root;
            self::$candidates[ $candidate_key ] = [
                'host'            => $host,
                'root'            => $real_root,
                'version'         => $version,
                'hash'            => $hash,
                'minimum_version' => self::normalize_version( $requirements['minimum_version'] ?? $version ),
                'maximum_version' => self::normalize_version( $requirements['maximum_version'] ?? '' ),
                'priority'        => (int) ( $requirements['priority'] ?? 0 ),
            ];

            if ( self::$selected ) {
                self::record_late_candidate_issue( self::$candidates[ $candidate_key ] );
                return;
            }

            self::register_resolve_hook();
        }

        /**
         * @return array<string,mixed>
         */
        public static function resolve(): array {
            if ( self::$selected ) {
                return self::$selected;
            }

            if ( [] === self::$candidates ) {
                self::$issues[] = [
                    'type'    => 'missing_candidate',
                    'message' => 'No Hexa Plugin Core package candidate was registered.',
                ];
                return [];
            }

            $candidates = array_values( self::$candidates );
            usort( $candidates, [ self::class, 'compare_candidates' ] );

            foreach ( $candidates as $candidate ) {
                if ( self::satisfies_all_hosts( (string) $candidate['version'] ) ) {
                    self::$selected = $candidate;
                    break;
                }
            }

            if ( ! self::$selected ) {
                self::$selected = $candidates[0];
                self::$issues[] = [
                    'type'             => 'incompatible_requirements',
                    'selected_version' => self::$selected['version'],
                    'message'          => 'No registered Core package satisfies every host version constraint; the newest package was selected.',
                ];
            }

            self::record_package_integrity_issues();
            self::register_autoloader();

            if ( ! defined( 'HEXA_PLUGIN_CORE_SELECTED_ROOT' ) ) {
                define( 'HEXA_PLUGIN_CORE_SELECTED_ROOT', (string) self::$selected['root'] );
            }
            if ( ! defined( 'HEXA_PLUGIN_CORE_SELECTED_VERSION' ) ) {
                define( 'HEXA_PLUGIN_CORE_SELECTED_VERSION', (string) self::$selected['version'] );
            }

            if ( function_exists( 'do_action' ) ) {
                do_action( 'hexa_plugin_core_package_selected', self::$selected, self::report() );
            }

            return self::$selected;
        }

        public static function autoload( string $class_name ): void {
            if ( 0 !== strncmp( $class_name, self::CLASS_PREFIX, strlen( self::CLASS_PREFIX ) ) ) {
                return;
            }

            $selected = self::resolve();
            if ( empty( $selected['root'] ) ) {
                return;
            }

            $relative_class = substr( $class_name, strlen( self::CLASS_PREFIX ) );
            $file = (string) $selected['root'] . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR
                . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';

            if ( is_readable( $file ) ) {
                require_once $file;
            }
        }

        public static function source_hash( string $core_root ): string {
            $real_root = realpath( $core_root );
            if ( false === $real_root ) {
                return '';
            }

            return self::calculate_source_hash( $real_root );
        }

        /**
         * @return array<string,mixed>
         */
        public static function report(): array {
            self::record_preloaded_class_issues();

            return [
                'managed'          => true,
                'resolved'         => null !== self::$selected,
                'selected'         => self::$selected,
                'candidates'       => array_values( self::$candidates ),
                'issues'           => self::unique_issues( self::$issues ),
                'healthy'          => null !== self::$selected && [] === self::unique_issues( self::$issues ),
                'autoload_managed' => self::$autoload_registered,
            ];
        }

        private static function register_resolve_hook(): void {
            if ( self::$resolve_hook_registered ) {
                return;
            }

            if ( function_exists( 'did_action' ) && did_action( 'plugins_loaded' ) ) {
                self::resolve();
                return;
            }

            if ( function_exists( 'add_action' ) ) {
                add_action( 'plugins_loaded', [ self::class, 'resolve' ], -PHP_INT_MAX );
                self::$resolve_hook_registered = true;
            }
        }

        private static function register_autoloader(): void {
            if ( self::$autoload_registered ) {
                return;
            }

            spl_autoload_register( [ self::class, 'autoload' ], true, true );
            self::$autoload_registered = true;
        }

        /**
         * @param array<string,mixed> $left
         * @param array<string,mixed> $right
         */
        private static function compare_candidates( array $left, array $right ): int {
            $version_order = version_compare( (string) $right['version'], (string) $left['version'] );
            if ( 0 !== $version_order ) {
                return $version_order;
            }

            $priority_order = (int) $right['priority'] <=> (int) $left['priority'];
            if ( 0 !== $priority_order ) {
                return $priority_order;
            }

            $host_order = strcmp( (string) $left['host'], (string) $right['host'] );
            if ( 0 !== $host_order ) {
                return $host_order;
            }

            return strcmp( (string) $left['root'], (string) $right['root'] );
        }

        private static function satisfies_all_hosts( string $version ): bool {
            foreach ( self::$candidates as $candidate ) {
                $minimum = (string) $candidate['minimum_version'];
                $maximum = (string) $candidate['maximum_version'];

                if ( '' !== $minimum && version_compare( $version, $minimum, '<' ) ) {
                    return false;
                }
                if ( '' !== $maximum && version_compare( $version, $maximum, '>' ) ) {
                    return false;
                }
            }

            return true;
        }

        private static function record_package_integrity_issues(): void {
            $versions = [];
            foreach ( self::$candidates as $candidate ) {
                $version = (string) $candidate['version'];
                $hash    = (string) $candidate['hash'];
                $versions[ $version ][ $hash ][] = (string) $candidate['host'];
            }

            foreach ( $versions as $version => $hashes ) {
                if ( count( $hashes ) < 2 ) {
                    continue;
                }

                self::$issues[] = [
                    'type'      => 'same_version_hash_mismatch',
                    'version'   => $version,
                    'packages'  => $hashes,
                    'message'   => 'Multiple Core packages claim the same version but contain different executable source.',
                ];
            }
        }

        /**
         * @param array<string,mixed> $candidate
         */
        private static function record_late_candidate_issue( array $candidate ): void {
            if ( ! self::$selected ) {
                return;
            }

            if ( (string) $candidate['version'] !== (string) self::$selected['version'] || (string) $candidate['hash'] !== (string) self::$selected['hash'] ) {
                self::$issues[] = [
                    'type'          => 'late_candidate',
                    'host'          => $candidate['host'],
                    'version'       => $candidate['version'],
                    'selected_host' => self::$selected['host'],
                    'message'       => 'A different Core package was registered after runtime selection.',
                ];
            }
        }

        private static function record_preloaded_class_issues(): void {
            if ( ! self::$selected ) {
                return;
            }

            $selected_source = self::normalize_path( (string) self::$selected['root'] . DIRECTORY_SEPARATOR . 'src' ) . '/';
            foreach ( get_declared_classes() as $class_name ) {
                if ( 0 !== strncmp( $class_name, self::CLASS_PREFIX, strlen( self::CLASS_PREFIX ) ) ) {
                    continue;
                }

                try {
                    $reflection = new ReflectionClass( $class_name );
                    $file       = $reflection->getFileName();
                } catch ( ReflectionException $exception ) {
                    continue;
                }

                if ( false === $file || 0 === strpos( self::normalize_path( $file ), $selected_source ) ) {
                    continue;
                }

                self::$issues[] = [
                    'type'          => 'class_outside_selected_root',
                    'class'         => $class_name,
                    'file'          => $file,
                    'selected_root' => self::$selected['root'],
                    'message'       => 'A Hexa Plugin Core class was loaded outside the selected package root.',
                ];
            }
        }

        private static function read_file_value( string $file, string $fallback ): string {
            if ( ! is_readable( $file ) ) {
                return $fallback;
            }

            $value = trim( (string) file_get_contents( $file ) );
            return '' !== $value ? $value : $fallback;
        }

        private static function normalize_version( mixed $version ): string {
            return is_scalar( $version ) ? trim( (string) $version ) : '';
        }

        private static function calculate_source_hash( string $core_root ): string {
            $files = [];
            foreach ( [ 'bootstrap.php', 'src' ] as $relative_path ) {
                $path = $core_root . DIRECTORY_SEPARATOR . $relative_path;
                if ( is_file( $path ) ) {
                    $files[] = $path;
                    continue;
                }
                if ( ! is_dir( $path ) ) {
                    continue;
                }

                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS )
                );
                foreach ( $iterator as $file ) {
                    if ( $file instanceof SplFileInfo && $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
                        $files[] = $file->getPathname();
                    }
                }
            }

            sort( $files, SORT_STRING );
            $context = hash_init( 'sha256' );
            foreach ( $files as $file ) {
                hash_update( $context, self::normalize_path( substr( $file, strlen( $core_root ) ) ) );
                hash_update_file( $context, $file );
            }

            return hash_final( $context );
        }

        private static function normalize_path( string $path ): string {
            return str_replace( '\\', '/', $path );
        }

        /**
         * @param array<int,array<string,mixed>> $issues
         * @return array<int,array<string,mixed>>
         */
        private static function unique_issues( array $issues ): array {
            $unique = [];
            foreach ( $issues as $issue ) {
                $key = md5( serialize( $issue ) );
                $unique[ $key ] = $issue;
            }

            return array_values( $unique );
        }
    }
}

if ( ! function_exists( 'hexa_plugin_core_register_package' ) ) {
    /**
     * @param array<string,mixed> $requirements
     */
    function hexa_plugin_core_register_package( string $host, string $core_root, array $requirements = [] ): void {
        HexaPluginCorePackageRegistry::register_candidate( $host, $core_root, $requirements );
    }
}
