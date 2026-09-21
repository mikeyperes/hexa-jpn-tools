<?php
/**
 * Plugin Name: Hexa JPN Tools
 * Plugin URI: https://github.com/mikeyperes/hexa-jpn-tools
 * Description: JPN event management, host tools, and the authenticated Code.Hexa integration contract.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Michael Peres
 * Author URI: https://michaelperes.com
 * License: GPL-2.0-or-later
 * Text Domain: hexa-jpn-tools
 * GitHub Plugin URI: https://github.com/mikeyperes/hexa-jpn-tools
 * GitHub Branch: main
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('HEXA_JPN_TOOLS_VERSION', '1.0.0');
define('HEXA_JPN_TOOLS_PLUGIN_FILE', __FILE__);
define('HEXA_JPN_TOOLS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HEXA_JPN_TOOLS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HEXA_JPN_TOOLS_PLUGIN_BASENAME', plugin_basename(__FILE__));

$hexaJpnCoreRoot = HEXA_JPN_TOOLS_PLUGIN_DIR . 'lib/hexa-wordpress-plugin-core';
require_once $hexaJpnCoreRoot . '/bootstrap.php';
\hexa_plugin_core_register_package('hexa-jpn-tools', $hexaJpnCoreRoot);

require_once HEXA_JPN_TOOLS_PLUGIN_DIR . 'src/Autoloader.php';
\Hexa\JpnTools\Autoloader::register(HEXA_JPN_TOOLS_PLUGIN_DIR . 'src');

register_activation_hook(HEXA_JPN_TOOLS_PLUGIN_FILE, [\Hexa\JpnTools\Migrations\Migration::class, 'activate']);
register_deactivation_hook(HEXA_JPN_TOOLS_PLUGIN_FILE, [\Hexa\JpnTools\Migrations\Migration::class, 'deactivate']);

\Hexa\JpnTools\Plugin::register();
