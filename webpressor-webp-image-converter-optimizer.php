<?php
/**
 * Plugin Name: WebPressor - WebP Image Converter & Optimizer
 * Description: A WordPress plugin to convert images to WebP and AVIF formats and serve them to compatible browsers.
 * Version: 2.0.0
 * Author: Techbysh
 * Author URI: https://techbysh.com
 * Text Domain: webpressor-webp-image-converter-optimizer
 * Domain Path: /languages
 * License: GPL2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.0
 * Requires PHP: 7.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Define plugin constants
 */
define('TBSWEBPRESSOR_VERSION', '2.0.0');
define('TBSWEBPRESSOR_PLUGIN_DIR', trailingslashit(dirname(__FILE__)));
define('TBSWEBPRESSOR_PLUGIN_URL', trailingslashit(plugins_url('', __FILE__)));
define('TBSWEBPRESSOR_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Include required files
 */
require_once TBSWEBPRESSOR_PLUGIN_DIR . 'includes/class-tbs-webpressor.php';
require_once TBSWEBPRESSOR_PLUGIN_DIR . 'includes/class-tbs-webpressor-converter.php';
require_once TBSWEBPRESSOR_PLUGIN_DIR . 'includes/class-tbs-webpressor-admin.php';
require_once TBSWEBPRESSOR_PLUGIN_DIR . 'includes/class-tbs-webpressor-public.php';
require_once TBSWEBPRESSOR_PLUGIN_DIR . 'includes/class-tbs-webpressor-ajax.php';
require_once TBSWEBPRESSOR_PLUGIN_DIR . 'includes/class-tbsw-activator.php';
require_once TBSWEBPRESSOR_PLUGIN_DIR . 'includes/class-tbsw-deactivator.php';

/**
 * Begins execution of the plugin.
 */
function tbswebpressor_run() {
    register_activation_hook(__FILE__, array('TBS_WebPressor_Activator', 'tbswebpressor_activate'));
    register_deactivation_hook(__FILE__, array('TBS_WebPressor_Deactivator', 'tbswebpressor_deactivate'));

    $plugin = new TBS_WebPressor_WIC();
    $plugin->tbswebpressor_main_run();
}
tbswebpressor_run();