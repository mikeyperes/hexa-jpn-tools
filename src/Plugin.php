<?php

declare(strict_types=1);

namespace Hexa\JpnTools;

use Hexa\JpnTools\Admin\EventAdmin;
use Hexa\JpnTools\Admin\EventExports;
use Hexa\JpnTools\Admin\HostRole;
use Hexa\JpnTools\Admin\NotificationDashboard;
use Hexa\JpnTools\Content\AcfFields;
use Hexa\JpnTools\Content\ContentTypes;
use Hexa\JpnTools\Cli\MigrationCommand;
use Hexa\JpnTools\Events\EventDates;
use Hexa\JpnTools\Events\EventQueries;
use Hexa\JpnTools\Events\EventRelations;
use Hexa\JpnTools\Frontend\Privacy;
use Hexa\JpnTools\Frontend\Shortcodes;
use Hexa\JpnTools\Integration\CoreIntegration;
use Hexa\JpnTools\Migrations\Migration;
use Hexa\JpnTools\Rest\EventBindings;
use Hexa\JpnTools\Rest\EventController;
use Hexa\JpnTools\Rest\HostController;
use Hexa\JpnTools\Rest\OperationController;
use Hexa\JpnTools\Rest\SystemController;

final class Plugin
{
    private static bool $registered = false;
    private static bool $booted = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;
        if (did_action('plugins_loaded')) {
            self::boot();
            return;
        }
        add_action('plugins_loaded', [self::class, 'boot'], 20);
    }

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;
        CoreIntegration::boot();

        $dates = new EventDates();
        $queries = new EventQueries($dates);
        $relations = new EventRelations($queries);

        (new ContentTypes())->register();
        Migration::register();
        (new AcfFields())->register();
        (new HostRole())->register();
        (new EventAdmin($dates))->register();
        (new EventExports($queries))->register();
        (new NotificationDashboard($queries, $dates))->register();
        $relations->register();
        (new Shortcodes($queries, $dates))->register();
        (new Privacy())->register();

        $bindings = new EventBindings();
        (new EventController($bindings, $dates, $relations))->register();
        (new SystemController($bindings, $dates))->register();
        (new OperationController($bindings))->register();
        (new HostController())->register();
        MigrationCommand::register();

        do_action('hexa_jpn_tools_booted');
    }
}
