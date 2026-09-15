<?php
/**
 * Admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 * @package    TBS_WebPressor
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class TBS_WebPressor_Admin {

    /**
     * The converter instance.
     *
     * @since    1.0.0
     * @access   protected
     * @var      TBS_WebPressor_Converter    $converter    Converter instance.
     */
    protected $converter;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    TBS_WebPressor_Converter    $converter    Converter instance.
     */
    public function __construct($converter) {
        $this->converter = $converter;
    }

    /**
     * Register the hooks for the admin area
     *
     * @since    1.0.0
     */
    public function tbswebpressor_admin_setup_hooks() {
        add_action('admin_enqueue_scripts', array($this, 'tbswebpressor_enqueue_admin_assets'));
        add_action('admin_menu', array($this, 'tbswebpressor_register_admin_menu'));
        add_filter('wp_generate_attachment_metadata', array($this, 'tbswebpressor_convert_on_upload'), 99, 2);
    }

    /**
     * Enqueue admin scripts and styles on WebPressor screens only.
     *
     * @since    1.0.0
     * @param    string $hook_suffix Current admin page hook suffix.
     */
    public function tbswebpressor_enqueue_admin_assets($hook_suffix) {
        if (strpos($hook_suffix, 'tbswebpressor') === false) {
            return;
        }

        wp_enqueue_style(
            'tbswebpressor-admin-style',
            TBSWEBPRESSOR_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            TBSWEBPRESSOR_VERSION
        );

        wp_enqueue_script(
            'tbswebpressor-backend-script',
            TBSWEBPRESSOR_PLUGIN_URL . 'assets/js/backend.js',
            array('jquery'),
            TBSWEBPRESSOR_VERSION,
            true
        );

        $upload_dir = wp_upload_dir();

        wp_localize_script(
            'tbswebpressor-backend-script',
            'tbswData',
            array(
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
                    'error' => __('Error during conversion', 'webpressor-webp-image-converter-optimizer'),
                ),
            )
        );
    }

    /**
     * Register admin menu
     *
     * @since    1.0.0
     */
    public function tbswebpressor_register_admin_menu() {
        // Main menu item
        add_menu_page(
            'WebPressor Settings',        // Page title
            'WebPressor',                 // Menu title
            'manage_options',            // Capability
            'tbswebpressor-dashboard',            // Menu slug
            array($this, 'tbswebpressor_dashboard_page'),       // Callback function
            'dashicons-admin-generic',   // Icon
            25                           // Position
        );

        // Submenu item 1 (repeats main menu)
        add_submenu_page(
            'tbswebpressor-dashboard',   // Parent slug - connects to the main menu item
            'Dashboard',        // Page title - shown in browser title bar
            'Dashboard',        // Menu title - text shown in the menu
            'manage_options',   // Capability required for access (admin level)
            'tbswebpressor-dashboard',   // Menu slug - unique identifier for this page
            array($this, 'tbswebpressor_dashboard_page') // Callback function that displays the page
        );

        // Submenu item 2
        add_submenu_page(
            'tbswebpressor-dashboard', // Parent slug - connects to the main menu item
            'Settings', // Page title - shown in browser title bar
            'Settings', // Menu title - text shown in the menu
            'manage_options', // Capability required for access (admin level)
            'tbswebpressor-settings', // Menu slug - unique identifier for this page
            array($this, 'tbswebpressor_settings_page') // Callback function that displays the page
        );
    }

    /**
     * Display the dashboard page
     *
     * @since    1.0.0
     */
    public function tbswebpressor_dashboard_page() {
        include TBSWEBPRESSOR_PLUGIN_DIR . 'admin/dashboard.php';
    }
    
    /**
     * Display the settings page
     *
     * @since    1.0.0
     */
    public function tbswebpressor_settings_page() {
        include TBSWEBPRESSOR_PLUGIN_DIR . 'admin/settings.php';
    }

    /**
     * Convert image on upload
     *
     * @since    1.0.0
     * @param    int    $attachment_id    Attachment ID
     */
    public function tbswebpressor_convert_on_upload($metadata, $attachment_id) {
        $file_type = get_post_mime_type($attachment_id);

        if (!in_array($file_type, TBS_WebPressor_Converter::tbswebpressor_get_convertible_mime_types(), true)) {
            return $metadata;
        }

        $convert_on_upload = intval(get_option('tbswebpressor_convert_on_upload', 1));
        if ($convert_on_upload) {
            TBS_WebPressor_Converter::tbswebpressor_create_webp($attachment_id, $metadata);
        }

        return $metadata;
    }
    
}