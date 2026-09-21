<?php

namespace Hexa\PluginCore\CoreRuntime;

final class CorePackageRuntime {
    /**
     * @return array<string,mixed>
     */
    public static function report(): array {
        if ( ! class_exists( '\\HexaPluginCorePackageRegistry', false ) ) {
            return [
                'managed'          => false,
                'resolved'         => false,
                'selected'         => null,
                'candidates'       => [],
                'issues'           => [
                    [
                        'type'    => 'unmanaged_runtime',
                        'message' => 'The shared Hexa Plugin Core package registry is not active.',
                    ],
                ],
                'healthy'          => false,
                'autoload_managed' => false,
            ];
        }

        return \HexaPluginCorePackageRegistry::report();
    }

    public static function selected_root(): string {
        $report = self::report();
        return (string) ( $report['selected']['root'] ?? '' );
    }

    public static function selected_version(): string {
        $report = self::report();
        return (string) ( $report['selected']['version'] ?? '' );
    }

    public static function healthy(): bool {
        return (bool) ( self::report()['healthy'] ?? false );
    }
}
