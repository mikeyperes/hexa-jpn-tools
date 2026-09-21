<?php

declare(strict_types=1);

namespace Hexa\JpnTools;

final class Autoloader
{
    private const PREFIX = 'Hexa\\JpnTools\\';

    public static function register(string $sourceRoot): void
    {
        $sourceRoot = rtrim($sourceRoot, '/\\');

        spl_autoload_register(
            static function (string $className) use ($sourceRoot): void {
                if (strncmp($className, self::PREFIX, strlen(self::PREFIX)) !== 0) {
                    return;
                }

                $relative = substr($className, strlen(self::PREFIX));
                $path = $sourceRoot . DIRECTORY_SEPARATOR
                    . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

                if (is_readable($path)) {
                    require_once $path;
                }
            },
            true,
            true
        );
    }
}
