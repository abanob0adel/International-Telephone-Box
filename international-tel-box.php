<?php

/**
 * Plugin Name: International Telephone Box
 * Plugin URI: https://wordpress.org/plugins/international-telephone-box/
 * Description: A WordPress plugin for handling international telephone numbers with geo location.
 * Version: 1.0.0
 * Author: Abanob Adel
 * Author URI: https://abanob.co
 * Text Domain: international-telephone-box
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package International_Tel_Box
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Included Libraries:
 * - intl-tel-input (v17.0.19) - MIT License
 *   Source: https://github.com/jackocnr/intl-tel-input
 *   Copyright (c) 2013-2023 Jack O'Connor
 *
 * - jQuery - MIT License
 *   Source: https://jquery.org/
 *   Copyright (c) OpenJS Foundation and other contributors
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('ITB_VERSION', '1.0.0');
define('ITB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ITB_PLUGIN_URL', plugin_dir_url(__FILE__));

// Create languages directory if it doesn't exist
$languages_dir = ITB_PLUGIN_DIR . 'languages';
if (!file_exists($languages_dir)) {
    wp_mkdir_p($languages_dir);
}

// Include required files
require_once ITB_PLUGIN_DIR . 'includes/class-international-tel-box.php';

// Initialize the plugin
function run_international_tel_box()
{
    $plugin = new International_Tel_Box();
    $plugin->run();
}
run_international_tel_box();
