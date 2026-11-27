<?php

/**
 * Plugin Name: International Telephone Box
 * Plugin URI: https://wordpress.org/plugins/international-telephone-box/
 * Description: A WordPress plugin for handling international telephone numbers with geo location.
 * Version: 1.2.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Abanob Adel
 * Author URI: https://abanob.co
 * Text Domain: international-telephone-box
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package International_Telephone_Box
 */

if (! defined('WPINC')) {
    die;
}

// Define plugin constants
define('INTELEBOX_VERSION', '1.2.0');
define('INTELEBOX_INTL_TEL_INPUT_VERSION', '29.5.12');
define('INTELEBOX_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('INTELEBOX_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include main class
require_once INTELEBOX_PLUGIN_DIR . 'includes/class-intelebox-main.php';

function intelebox_run_plugin()
{
    $plugin = new \International_Telephone_Box\Intelebox_Main();
    $plugin->run();
}
intelebox_run_plugin();
