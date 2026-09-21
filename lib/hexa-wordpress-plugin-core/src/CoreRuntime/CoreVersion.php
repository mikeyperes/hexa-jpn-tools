<?php

namespace Hexa\PluginCore\CoreRuntime;

final class CoreVersion {
    public const PACKAGE_NAME = 'Hexa WordPress Plugin Core';
    public const GITHUB_REPO = 'mikeyperes/hexa-wordpress-plugin-core';
    public const VERSION_FILE = 'VERSION';

    public static function root_path(): string {
        return dirname( __DIR__, 2 );
    }

    public static function current( ?string $core_root = null ): string {
        $core_root = $core_root ?: self::root_path();
        $file      = rtrim( $core_root, '/\\' ) . DIRECTORY_SEPARATOR . self::VERSION_FILE;

        if ( ! is_readable( $file ) ) {
            return '0.0.0';
        }

        $version = trim( (string) file_get_contents( $file ) );

        return '' !== $version ? $version : '0.0.0';
    }
}
