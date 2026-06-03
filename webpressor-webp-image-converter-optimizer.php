<?php
/**
 * Plugin Name: WebPressor - WebP Image Converter & Optimizer
 * Description: A WordPress plugin to convert images to WebP format and serve them to compatible browsers.
 * Version: 1.0.1
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
define('TBSWEBPRESSOR_VERSION', '1.0.1');
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

/**
 * Begins execution of the plugin.
 */
function tbswebpressor_run() {
    $plugin = new TBS_WebPressor_WIC();
    $plugin->tbswebpressor_main_run();
}
tbswebpressor_run();