<?php

declare(strict_types=1);

require dirname( __DIR__ ) . '/src/ActivityLog/ActivityLogConfig.php';

use Hexa\PluginCore\ActivityLog\ActivityLogConfig;

$default = new ActivityLogConfig();
if ( ! $default->collapsed() ) {
    fwrite( STDERR, "FAIL: Activity logs must be collapsed by default.\n" );
    exit( 1 );
}

$open = new ActivityLogConfig( [ 'collapsed' => false ] );
if ( $open->collapsed() ) {
    fwrite( STDERR, "FAIL: Hosts must be able to explicitly open an activity log.\n" );
    exit( 1 );
}

echo "PASS: Activity logs default to collapsed and support an explicit open override.\n";
