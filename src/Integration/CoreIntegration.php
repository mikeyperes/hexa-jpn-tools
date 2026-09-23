<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Integration;

use Hexa\PluginCore\CoreBootstrap\CoreBootstrap;
use Hexa\PluginCore\CorePackageUpdates\CorePackageAjaxController;
use Hexa\PluginCore\CorePackageUpdates\CorePackageConfig;
use Hexa\PluginCore\CoreRuntime\PluginContext;
use Hexa\PluginCore\DirectorySearch\DirectorySearchModule;
use Hexa\PluginCore\PluginUpdates\GitHubPluginUpdater;
use Hexa\PluginCore\PluginUpdates\UpdaterAjaxController;
use Hexa\PluginCore\PluginUpdates\UpdaterConfig;

final class CoreIntegration
{
    private static ?CoreBootstrap $bootstrap = null;
    private static ?UpdaterConfig $updater = null;
    private static ?CorePackageConfig $corePackage = null;

    public static function boot(): void
    {
        if (self::$bootstrap instanceof CoreBootstrap) {
            return;
        }

        if (!class_exists(PluginContext::class) || !class_exists(CoreBootstrap::class)) {
            return;
        }

        $context = new PluginContext([
            'slug'        => 'hexa-jpn-tools',
            'basename'    => HEXA_JPN_TOOLS_PLUGIN_BASENAME,
            'version'     => HEXA_JPN_TOOLS_VERSION,
            'path'        => HEXA_JPN_TOOLS_PLUGIN_DIR,
            'url'         => plugin_dir_url(HEXA_JPN_TOOLS_PLUGIN_FILE),
            'github_repo' => 'mikeyperes/hexa-jpn-tools',
            'admin_page'  => admin_url('admin.php?page=notifications-dashboard'),
            'capability'  => 'manage_options',
        ]);

        self::$bootstrap = (new CoreBootstrap($context))
            ->add_module(new GitHubPluginUpdater(self::updaterConfig()))
            ->add_module(new UpdaterAjaxController(self::updaterConfig()));

        if (class_exists(DirectorySearchModule::class)) {
            self::$bootstrap->add_module(new DirectorySearchModule());
        }

        if (is_admin() || (function_exists('wp_doing_ajax') && wp_doing_ajax())) {
            self::$bootstrap->add_module(new CorePackageAjaxController(self::corePackageConfig()));
        }

        self::$bootstrap->boot();
    }

    public static function updaterConfig(): UpdaterConfig
    {
        if (self::$updater instanceof UpdaterConfig) {
            return self::$updater;
        }

        self::$updater = UpdaterConfig::from_plugin_file(
            HEXA_JPN_TOOLS_PLUGIN_FILE,
            'mikeyperes/hexa-jpn-tools',
            [
                'plugin_slug' => 'hexa-jpn-tools',
                'proper_folder_name' => 'hexa-jpn-tools',
                'runtime_folder_name' => dirname(HEXA_JPN_TOOLS_PLUGIN_BASENAME),
                'plugin_basename' => HEXA_JPN_TOOLS_PLUGIN_BASENAME,
                'canonical_plugin_basename' => 'hexa-jpn-tools/hexa-jpn-tools.php',
                'plugin_starter_file' => 'hexa-jpn-tools.php',
                'github_branch' => 'main',
                'requires' => '6.0',
                'tested' => get_bloginfo('version'),
                'requires_php' => '8.1',
                'nonce_action' => 'hexa_jpn_tools_updater',
                'nonce_param' => 'nonce',
                'ajax_action_prefix' => 'hexa_jpn_tools_updater',
                'progress_key' => 'hexa_jpn_tools_update_progress',
            ]
        );

        return self::$updater;
    }

    public static function corePackageConfig(): CorePackageConfig
    {
        if (self::$corePackage instanceof CorePackageConfig) {
            return self::$corePackage;
        }

        self::$corePackage = CorePackageConfig::from_core_root(
            HEXA_JPN_TOOLS_PLUGIN_DIR . 'lib/hexa-wordpress-plugin-core',
            [
                'github_repo' => 'mikeyperes/hexa-wordpress-plugin-core',
                'github_branch' => 'main',
                'nonce_action' => 'hexa_jpn_tools_core',
                'nonce_param' => 'nonce',
                'ajax_action_prefix' => 'hexa_jpn_tools_core',
                'cache_key' => 'hexa_jpn_tools_core_package',
                'progress_key' => 'hexa_jpn_tools_core_update_progress',
            ]
        );

        return self::$corePackage;
    }

    public static function status(): array
    {
        if (!class_exists('Hexa\\PluginCore\\CoreRuntime\\CorePackageRuntime')) {
            return ['available' => false, 'healthy' => false, 'version' => null];
        }

        $runtime = 'Hexa\\PluginCore\\CoreRuntime\\CorePackageRuntime';

        return [
            'available' => true,
            'healthy'   => (bool) $runtime::healthy(),
            'version'   => (string) $runtime::selected_version(),
        ];
    }
}
