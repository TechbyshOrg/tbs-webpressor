<?php
/**
 * The core plugin class.
 *
 * @since      1.0.0
 * @package    TBS_WebPressor
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class TBS_WebPressor_WIC {

    /**
     * The loader that's responsible for maintaining and registering all hooks.
     *
     * @since    1.0.0
     * @access   protected
     * @var      TBS_WebPressor_Admin    $admin    Handles admin hooks.
     */
    protected $admin;

    /**
     * The loader that's responsible for maintaining and registering all hooks.
     *
     * @since    1.0.0
     * @access   protected
     * @var      TBS_WebPressor_Public    $public    Handles public hooks.
     */
    protected $public;

    /**
     * The loader that's responsible for maintaining and registering all hooks.
     *
     * @since    1.0.0
     * @access   protected
     * @var      TBS_WebPressor_Converter    $converter    Handles image conversion.
     */
    protected $converter;

    /**
     * The loader that's responsible for maintaining and registering all hooks.
     *
     * @since    1.0.0
     * @access   protected
     * @var      TBS_WebPressor_Ajax    $ajax    Handles ajax requests.
     */
    protected $ajax;

    /**
     * Media Library integration.
     *
     * @since    2.2.0
     * @access   protected
     * @var      TBS_WebPressor_Media    $media    Handles Media Library status and actions.
     */
    protected $media;

    /**
     * Background processing.
     *
     * @since    2.2.0
     * @access   protected
     * @var      TBS_WebPressor_Background    $background    Handles WP-Cron bulk optimization.
     */
    protected $background;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->tbswebpressor_load_dependencies();
        $this->tbswebpressor_setup_components();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function tbswebpressor_load_dependencies() {
        // Dependencies are already loaded in main plugin file
    }

    /**
     * Create instances of all plugin components.
     *
     * @since    1.0.0
     * @access   private
     */
    private function tbswebpressor_setup_components() {
        $this->converter = new TBS_WebPressor_Converter();
        $this->admin = new TBS_WebPressor_Admin($this->converter);
        $this->public = new TBS_WebPressor_Public($this->converter);
        $this->ajax = new TBS_WebPressor_Ajax($this->converter);
        $this->media = new TBS_WebPressor_Media();
        $this->background = new TBS_WebPressor_Background();
    }

    /**
     * Run the plugin.
     *
     * @since    1.0.0
     */
    public function tbswebpressor_main_run() {
        $this->admin->tbswebpressor_admin_setup_hooks();
        $this->public->tbswebpressor_public_setup_hooks();
        $this->ajax->tbswebpressor_ajax_setup_hooks();
        $this->media->tbswebpressor_media_setup_hooks();
        $this->background->tbswebpressor_background_setup_hooks();
    }
}
