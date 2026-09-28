<?php
/**
 * Rector config for a PHP 7.4 release build of a Hexa plugin.
 *
 * Source stays on modern PHP; bin/build-php74-release.sh copies the plugin to
 * a temporary folder and runs this config there, so only the shipped zip is
 * rewritten. HEXA_RECTOR_PATH names the copied plugin folder.
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\DowngradeLevelSetList;

$path = (string) getenv( 'HEXA_RECTOR_PATH' );

return RectorConfig::configure()
    ->withPaths( [ $path ] )
    ->withSkip( [ $path . '/vendor', $path . '/node_modules' ] )
    ->withSets( [ DowngradeLevelSetList::DOWN_TO_PHP_74 ] );
