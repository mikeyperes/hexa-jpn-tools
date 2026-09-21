<?php

namespace Hexa\PluginCore\SystemEnvironment;

use Throwable;

final class SystemEnvironment {
    private function __construct() {}

    public static function is_function_disabled( string $function_name ): bool {
        $disabled = explode( ',', (string) @ini_get( 'disable_functions' ) );
        $disabled = array_map( 'trim', $disabled );

        return in_array( $function_name, $disabled, true );
    }

    public static function safe_shell_exec( string $command ): ?string {
        if ( ! function_exists( 'shell_exec' ) || self::is_function_disabled( 'shell_exec' ) ) {
            return null;
        }

        try {
            $result = @shell_exec( $command );
            return null !== $result ? trim( (string) $result ) : null;
        } catch ( Throwable ) {
            return null;
        }
    }

    /**
     * @return array{output:array<int,string>,return_code:int}|null
     */
    public static function safe_exec( string $command, int $timeout = 5 ): ?array {
        if ( ! function_exists( 'exec' ) || self::is_function_disabled( 'exec' ) ) {
            return null;
        }

        try {
            if ( stripos( PHP_OS, 'WIN' ) === false ) {
                $command = 'timeout ' . max( 1, $timeout ) . ' ' . $command;
            }

            $output      = [];
            $return_code = 0;
            @exec( $command, $output, $return_code );

            return [
                'output'      => $output,
                'return_code' => $return_code,
            ];
        } catch ( Throwable ) {
            return null;
        }
    }

    public static function get_constant( string $name, mixed $default = null ): mixed {
        return defined( $name ) ? constant( $name ) : $default;
    }

    public static function get_ini( string $name, mixed $default = null ): mixed {
        $value = @ini_get( $name );

        return false !== $value ? $value : $default;
    }

    public static function parse_size( mixed $size ): int {
        $size = trim( (string) $size );

        if ( '' === $size ) {
            return 0;
        }

        $last  = strtolower( $size[ strlen( $size ) - 1 ] );
        $value = (int) $size;

        switch ( $last ) {
            case 'g':
                $value *= 1024;
                // no break
            case 'm':
                $value *= 1024;
                // no break
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    public static function read_system_file( string $path ): ?string {
        if ( ! is_readable( $path ) ) {
            return null;
        }

        $contents = @file_get_contents( $path );
        if ( false === $contents ) {
            return null;
        }

        return trim( $contents );
    }

    public static function parse_cgroup_memory_limit( ?string $value ): ?int {
        if ( null === $value || '' === $value || 'max' === $value || ! is_numeric( $value ) ) {
            return null;
        }

        $bytes = (int) $value;

        if ( $bytes <= 0 || $bytes >= 9000000000000000000 ) {
            return null;
        }

        return $bytes;
    }

    /**
     * @return array{bytes:int,current:int|null,source:string}|null
     */
    public static function get_cgroup_memory_limit(): ?array {
        $limit_files = [
            '/sys/fs/cgroup/memory.max'                   => 'cgroup v2 memory.max',
            '/sys/fs/cgroup/memory/memory.limit_in_bytes' => 'cgroup v1 memory.limit_in_bytes',
        ];

        foreach ( $limit_files as $path => $source ) {
            $bytes = self::parse_cgroup_memory_limit( self::read_system_file( $path ) );
            if ( null === $bytes ) {
                continue;
            }

            $current = false !== strpos( $path, 'memory.max' )
                ? self::read_system_file( '/sys/fs/cgroup/memory.current' )
                : self::read_system_file( '/sys/fs/cgroup/memory/memory.usage_in_bytes' );

            return [
                'bytes'   => $bytes,
                'current' => is_numeric( $current ) ? (int) $current : null,
                'source'  => $source,
            ];
        }

        return null;
    }

    public static function count_cpuset_cpus( ?string $cpuset ): ?int {
        if ( null === $cpuset || '' === trim( $cpuset ) ) {
            return null;
        }

        $count = 0;
        foreach ( explode( ',', trim( $cpuset ) ) as $part ) {
            $part = trim( $part );
            if ( preg_match( '/^(\d+)-(\d+)$/', $part, $matches ) ) {
                $start = (int) $matches[1];
                $end   = (int) $matches[2];
                if ( $end >= $start ) {
                    $count += ( $end - $start + 1 );
                }
            } elseif ( ctype_digit( $part ) ) {
                $count++;
            }
        }

        return $count > 0 ? $count : null;
    }

    /**
     * @return array{count:int|float|null,source:string,host_count:int|null}
     */
    public static function get_cpu_info(): array {
        $host_count = null;

        if ( is_readable( '/proc/cpuinfo' ) ) {
            $cpuinfo = @file_get_contents( '/proc/cpuinfo' );
            if ( $cpuinfo ) {
                $count = substr_count( $cpuinfo, 'processor' );
                if ( $count > 0 ) {
                    $host_count = $count;
                }
            }
        }

        $cpu_max = self::read_system_file( '/sys/fs/cgroup/cpu.max' );
        if ( $cpu_max && preg_match( '/^(\d+)\s+(\d+)$/', $cpu_max, $matches ) ) {
            $quota  = (int) $matches[1];
            $period = (int) $matches[2];

            if ( $quota > 0 && $period > 0 ) {
                return [
                    'count'      => round( $quota / $period, 2 ),
                    'source'     => 'cgroup v2 cpu.max',
                    'host_count' => $host_count,
                ];
            }
        }

        $quota  = self::read_system_file( '/sys/fs/cgroup/cpu/cpu.cfs_quota_us' );
        $period = self::read_system_file( '/sys/fs/cgroup/cpu/cpu.cfs_period_us' );
        if ( is_numeric( $quota ) && is_numeric( $period ) && (int) $quota > 0 && (int) $period > 0 ) {
            return [
                'count'      => round( (int) $quota / (int) $period, 2 ),
                'source'     => 'cgroup v1 cpu.cfs_quota_us',
                'host_count' => $host_count,
            ];
        }

        $cpuset_files = [
            '/sys/fs/cgroup/cpuset.cpus.effective'        => 'cgroup cpuset.cpus.effective',
            '/sys/fs/cgroup/cpuset/cpuset.cpus.effective' => 'cgroup cpuset.cpus.effective',
            '/sys/fs/cgroup/cpuset.cpus'                  => 'cgroup cpuset.cpus',
            '/sys/fs/cgroup/cpuset/cpuset.cpus'           => 'cgroup cpuset.cpus',
        ];

        foreach ( $cpuset_files as $path => $source ) {
            $count = self::count_cpuset_cpus( self::read_system_file( $path ) );
            if ( null !== $count && ( null === $host_count || $count < $host_count ) ) {
                return [
                    'count'      => $count,
                    'source'     => $source,
                    'host_count' => $host_count,
                ];
            }
        }

        $status = self::read_system_file( '/proc/self/status' );
        if ( $status && preg_match( '/^Cpus_allowed_list:\s*(.+)$/m', $status, $matches ) ) {
            $count = self::count_cpuset_cpus( $matches[1] );
            if ( null !== $count && ( null === $host_count || $count < $host_count ) ) {
                return [
                    'count'      => $count,
                    'source'     => '/proc/self/status Cpus_allowed_list',
                    'host_count' => $host_count,
                ];
            }
        }

        if ( null !== $host_count ) {
            return [
                'count'      => $host_count,
                'source'     => '/proc/cpuinfo host-visible',
                'host_count' => $host_count,
            ];
        }

        $nproc = self::safe_shell_exec( 'nproc 2>/dev/null' );
        if ( $nproc && is_numeric( $nproc ) ) {
            return [
                'count'      => (int) $nproc,
                'source'     => 'nproc host-visible',
                'host_count' => null,
            ];
        }

        return [
            'count'      => null,
            'source'     => 'unavailable',
            'host_count' => null,
        ];
    }

    /**
     * @return array{total:int|null,free:int|null,used:int|null,source:string,host_total:int|null}
     */
    public static function get_memory_info(): array {
        $info = [
            'total'      => null,
            'free'       => null,
            'used'       => null,
            'source'     => 'unavailable',
            'host_total' => null,
        ];

        $host_info = $info;

        if ( is_readable( '/proc/meminfo' ) ) {
            $meminfo = @file_get_contents( '/proc/meminfo' );
            if ( $meminfo ) {
                if ( preg_match( '/MemTotal:\s+(\d+)\s+kB/', $meminfo, $matches ) ) {
                    $host_info['total']      = (int) $matches[1] * 1024;
                    $host_info['host_total'] = $host_info['total'];
                    $host_info['source']     = '/proc/meminfo host-visible';
                }

                if ( preg_match( '/MemAvailable:\s+(\d+)\s+kB/', $meminfo, $matches ) ) {
                    $host_info['free'] = (int) $matches[1] * 1024;
                } elseif ( preg_match( '/MemFree:\s+(\d+)\s+kB/', $meminfo, $matches ) ) {
                    $host_info['free'] = (int) $matches[1] * 1024;
                }

                if ( $host_info['total'] && $host_info['free'] ) {
                    $host_info['used'] = $host_info['total'] - $host_info['free'];
                }
            }
        }

        $cgroup = self::get_cgroup_memory_limit();
        if ( $cgroup && ( ! $host_info['total'] || $cgroup['bytes'] < $host_info['total'] ) ) {
            $info['total']      = $cgroup['bytes'];
            $info['used']       = $cgroup['current'];
            $info['free']       = null !== $cgroup['current'] ? max( 0, $cgroup['bytes'] - $cgroup['current'] ) : null;
            $info['source']     = $cgroup['source'];
            $info['host_total'] = $host_info['total'];

            return $info;
        }

        if ( $host_info['total'] ) {
            return $host_info;
        }

        $total = self::safe_shell_exec( "free -b 2>/dev/null | awk '/^Mem:/{print $2}'" );
        $free  = self::safe_shell_exec( "free -b 2>/dev/null | awk '/^Mem:/{print $7}'" );

        if ( $total ) {
            $info['total']      = (int) $total;
            $info['host_total'] = $info['total'];
            $info['source']     = 'free command host-visible';
        }

        if ( $free ) {
            $info['free'] = (int) $free;
        }

        if ( $info['total'] && $info['free'] ) {
            $info['used'] = $info['total'] - $info['free'];
        }

        return $info;
    }

    public static function get_cpu_count(): int|float|null {
        $cpu = self::get_cpu_info();

        return $cpu['count'] ?? null;
    }

    public static function format_bytes( mixed $bytes, int $precision = 2 ): string {
        $units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
        $bytes = max( (float) $bytes, 0 );
        $pow   = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
        $pow   = min( $pow, count( $units ) - 1 );
        $bytes = $bytes / pow( 1024, $pow );

        return round( $bytes, $precision ) . ' ' . $units[ (int) $pow ];
    }
}
