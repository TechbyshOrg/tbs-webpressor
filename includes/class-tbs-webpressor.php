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
    }

    /**
     * Run the plugin.
     *
     * @since    1.0.0
     */
    public function tbswebpressor_main_run() {
        add_action('init', array($this, 'init'));
        
        // Run component hooks
        $this->admin->tbswebpressor_admin_setup_hooks();
        $this->public->tbswebpressor_public_setup_hooks();
        $this->ajax->tbswebpressor_ajax_setup_hooks();
    }

    /**
     * Initialize the plugin
     * 
     * @since    1.0.0
     */
    public function init() {
        wp_enqueue_style('tbswebpressor-style', TBSWEBPRESSOR_PLUGIN_URL . 'assets/css/style.css', array(), TBSWEBPRESSOR_VERSION);
        wp_enqueue_script('tbswebpressor-backend-script', TBSWEBPRESSOR_PLUGIN_URL . 'assets/js/backend.js', array('jquery'), TBSWEBPRESSOR_VERSION, true);
        
        $upload_dir = wp_upload_dir();
        
        wp_localize_script('tbswebpressor-backend-script', 'tbswData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tbswebpressor-nonce'),
            'plugin_url' => TBSWEBPRESSOR_PLUGIN_URL,
            'is_admin' => is_admin(),
            'max_upload_size' => wp_max_upload_size(),
            'version' => TBSWEBPRESSOR_VERSION,
            'settings' => array(
                'target_formats'    => get_option('tbswebpressor_target_formats', array('webp')),
                'webp_quality'      => intval(get_option('tbswebpressor_webp_quality', 80)),
                'avif_quality'      => intval(get_option('tbswebpressor_avif_quality', 65)),
                'delivery_method'   => get_option('tbswebpressor_delivery_method', 'html'),
                'compression_mode'  => get_option('tbswebpressor_compression_mode', 'lossy'),
                'convert_on_upload' => intval(get_option('tbswebpressor_convert_on_upload', 1)),
            ),
            'compatibility' => array(
                'gd_supported'   => extension_loaded('gd') ? 1 : 0,
                'webp_supported' => function_exists('imagewebp') ? 1 : 0,
                'avif_supported' => function_exists('imageavif') ? 1 : 0,
                'upload_writable'=> is_writable($upload_dir['basedir']) ? 1 : 0,
                'server_type'    => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'Unknown',
            ),
            'stats' => array(
                'total_original'  => intval(get_option('tbswebpressor_total_original_size', 0)),
                'total_optimized' => intval(get_option('tbswebpressor_total_optimized_size', 0)),
            ),
            'translations' => array(
                'converting' => __('Converting images...', 'webpressor-webp-image-converter-optimizer'),
                'success' => __('Conversion completed successfully!', 'webpressor-webp-image-converter-optimizer'),
                'error' => __('Error during conversion', 'webpressor-webp-image-converter-optimizer')
            )
        ));
    }
}