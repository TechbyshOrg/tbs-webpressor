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
     * Attachments converted during this request by the metadata generation filter.
     *
     * @var int[]
     */
    private $converted_in_request = array();

    /**
     * Previously processed attachments whose files changed during this request (ID => old signature).
     *
     * @var array<int,string>
     */
    private $changed_attachments = array();

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
        add_filter('wp_update_attachment_metadata', array($this, 'tbswebpressor_handle_metadata_update'), 99, 2);
        add_action('delete_attachment', array($this, 'tbswebpressor_delete_attachment_variants'), 10, 1);
        add_filter('big_image_size_threshold', array($this, 'tbswebpressor_filter_big_image_threshold'), 20, 1);
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
            array('jquery', 'wp-element', 'wp-i18n'),
            TBSWEBPRESSOR_VERSION,
            true
        );

        wp_set_script_translations(
            'tbswebpressor-backend-script',
            'webpressor-webp-image-converter-optimizer',
            TBSWEBPRESSOR_PLUGIN_DIR . 'languages'
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
                'media_library_url' => admin_url('upload.php?mode=list'),
                'upload_url' => admin_url('media-new.php'),
                'locale' => str_replace('_', '-', get_user_locale()),
                'pages' => $this->tbswebpressor_get_tab_pages(),
                'settings' => array(
                    'target_formats'      => get_option('tbswebpressor_target_formats', array('webp')),
                    'webp_quality'        => intval(get_option('tbswebpressor_webp_quality', 80)),
                    'avif_quality'        => intval(get_option('tbswebpressor_avif_quality', 65)),
                    'delivery_method'     => get_option('tbswebpressor_delivery_method', 'html'),
                    'compression_mode'    => get_option('tbswebpressor_compression_mode', 'lossy'),
                    'convert_on_upload'   => intval(get_option('tbswebpressor_convert_on_upload', 1)),
                    'skip_larger'         => intval(get_option('tbswebpressor_skip_larger', 1)),
                    'html_scope'          => get_option('tbswebpressor_html_scope', 'content'),
                    'exclude_paths'       => implode("\n", TBS_WebPressor_Converter::tbswebpressor_get_exclude_paths()),
                    'max_image_dimension' => intval(get_option('tbswebpressor_max_image_dimension', 0)),
                ),
                'compatibility' => array(
                    'gd_supported'   => extension_loaded('gd') ? 1 : 0,
                    'webp_supported' => function_exists('imagewebp') ? 1 : 0,
                    'avif_supported' => function_exists('imageavif') ? 1 : 0,
                    'upload_writable'=> is_writable($upload_dir['basedir']) ? 1 : 0,
                    'server_type'    => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'Unknown',
                    'page_cache'     => (defined('WP_CACHE') && WP_CACHE) ? 1 : 0,
                    'cron_disabled'  => (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) ? 1 : 0,
                ),
                'stats' => array(
                    'total_original'  => intval(get_option('tbswebpressor_total_original_size', 0)),
                    'total_optimized' => intval(get_option('tbswebpressor_total_optimized_size', 0)),
                ),
                'background' => TBS_WebPressor_Background::tbswebpressor_get_state(),
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
            __('WebPressor', 'webpressor-webp-image-converter-optimizer'), // Page title
            __('WebPressor', 'webpressor-webp-image-converter-optimizer'), // Menu title
            'manage_options',            // Capability
            'tbswebpressor-dashboard',            // Menu slug
            array($this, 'tbswebpressor_dashboard_page'),       // Callback function
            'dashicons-format-image',    // Icon (distinct from the core Settings gear)
            25                           // Position
        );

        // Submenu item 1 (repeats main menu)
        add_submenu_page(
            'tbswebpressor-dashboard',   // Parent slug - connects to the main menu item
            __('Dashboard', 'webpressor-webp-image-converter-optimizer'), // Page title - shown in browser title bar
            __('Dashboard', 'webpressor-webp-image-converter-optimizer'), // Menu title - text shown in the menu
            'manage_options',   // Capability required for access (admin level)
            'tbswebpressor-dashboard',   // Menu slug - unique identifier for this page
            array($this, 'tbswebpressor_dashboard_page') // Callback function that displays the page
        );

        // Submenu item 2
        add_submenu_page(
            'tbswebpressor-dashboard', // Parent slug - connects to the main menu item
            __('Settings', 'webpressor-webp-image-converter-optimizer'), // Page title - shown in browser title bar
            __('Settings', 'webpressor-webp-image-converter-optimizer'), // Menu title - text shown in the menu
            'manage_options', // Capability required for access (admin level)
            'tbswebpressor-settings', // Menu slug - unique identifier for this page
            array($this, 'tbswebpressor_settings_page') // Callback function that displays the page
        );

        // Submenu item 3: gives the System Status tab its own menu entry and linkable URL.
        add_submenu_page(
            'tbswebpressor-dashboard',
            __('System Status', 'webpressor-webp-image-converter-optimizer'),
            __('System Status', 'webpressor-webp-image-converter-optimizer'),
            'manage_options',
            'tbswebpressor-status',
            array($this, 'tbswebpressor_status_page')
        );
    }

    /**
     * URL and browser title of every WebPressor tab, so the app can switch tabs in place
     * while keeping the address bar, menu highlight and window title in sync.
     *
     * @since 2.2.0
     * @return array<string,array{slug:string,url:string,title:string}>
     */
    private function tbswebpressor_get_tab_pages() {
        $tabs = array(
            'dashboard' => array('tbswebpressor-dashboard', __('Dashboard', 'webpressor-webp-image-converter-optimizer')),
            'settings'  => array('tbswebpressor-settings', __('Settings', 'webpressor-webp-image-converter-optimizer')),
            'status'    => array('tbswebpressor-status', __('System Status', 'webpressor-webp-image-converter-optimizer')),
        );

        $site  = get_bloginfo('name');
        $pages = array();
        foreach ($tabs as $tab => $info) {
            $pages[$tab] = array(
                'slug'  => $info[0],
                'url'   => admin_url('admin.php?page=' . $info[0]),
                // Same format (and core translation) that wp-admin/admin-header.php uses for the <title>.
                'title' => wp_strip_all_tags(html_entity_decode(sprintf(__('%1$s &lsaquo; %2$s &#8212; WordPress'), $info[1], $site), ENT_QUOTES, 'UTF-8')), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- intentionally reuses the core string.
            );
        }

        return $pages;
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
     * Display the system status page
     *
     * @since    2.2.0
     */
    public function tbswebpressor_status_page() {
        include TBSWEBPRESSOR_PLUGIN_DIR . 'admin/status.php';
    }

    /**
     * Convert image on upload
     *
     * @since    1.0.0
     * @param    array  $metadata         Attachment metadata.
     * @param    int    $attachment_id    Attachment ID
     * @return   array
     */
    public function tbswebpressor_convert_on_upload($metadata, $attachment_id) {
        $file_type = get_post_mime_type($attachment_id);

        if (!in_array($file_type, TBS_WebPressor_Converter::tbswebpressor_get_convertible_mime_types(), true)) {
            return $metadata;
        }

        $convert_on_upload = intval(get_option('tbswebpressor_convert_on_upload', 1));
        if ($convert_on_upload) {
            TBS_WebPressor_Converter::tbswebpressor_process_attachment($attachment_id, $metadata, 'upload');
            $this->converted_in_request[] = (int) $attachment_id;
        }

        return $metadata;
    }

    /**
     * Signature of the files referenced by attachment metadata (main file and sizes).
     *
     * @param mixed $metadata Attachment metadata.
     * @return string
     */
    private function tbswebpressor_get_files_signature($metadata) {
        if (!is_array($metadata) || empty($metadata['file'])) {
            return '';
        }
        $files = array((string) $metadata['file']);
        if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size) {
                if (!empty($size['file'])) {
                    $files[] = (string) $size['file'];
                }
            }
        }
        sort($files);
        return md5(implode('|', array_unique($files)));
    }

    /**
     * Re-process images whose files changed after an edit in the WordPress image editor
     * (crop, rotate, scale, or restore original).
     *
     * @since 2.2.0
     * @param array $data          New attachment metadata.
     * @param int   $attachment_id Attachment ID.
     * @return array
     */
    public function tbswebpressor_handle_metadata_update($data, $attachment_id) {
        $attachment_id = (int) $attachment_id;

        if (in_array($attachment_id, $this->converted_in_request, true)) {
            return $data;
        }

        if (!in_array(get_post_mime_type($attachment_id), TBS_WebPressor_Converter::tbswebpressor_get_convertible_mime_types(), true)) {
            return $data;
        }

        $old = wp_get_attachment_metadata($attachment_id, true);
        if (empty($old) || !is_array($data)) {
            return $data;
        }

        if ($this->tbswebpressor_get_files_signature($old) === $this->tbswebpressor_get_files_signature($data)) {
            return $data;
        }

        $was_processed = false;
        foreach (TBS_WebPressor_Converter::tbswebpressor_get_all_formats() as $format) {
            if (get_post_meta($attachment_id, TBS_WebPressor_Converter::tbswebpressor_get_path_meta_key($format), true) !== ''
                || get_post_meta($attachment_id, TBS_WebPressor_Converter::tbswebpressor_get_status_meta_key($format), true) !== '') {
                $was_processed = true;
                break;
            }
        }

        if (!$was_processed || isset($this->changed_attachments[$attachment_id])) {
            return $data;
        }

        // Core may update metadata several times in one request (once per generated size),
        // so the attachment is re-processed once, after the request has finished its changes.
        $this->changed_attachments[$attachment_id] = $this->tbswebpressor_get_files_signature($old);
        if (!has_action('shutdown', array($this, 'tbswebpressor_process_changed_attachments'))) {
            add_action('shutdown', array($this, 'tbswebpressor_process_changed_attachments'), 0);
        }

        return $data;
    }

    /**
     * Re-process attachments whose files changed during this request.
     *
     * @since 2.2.0
     */
    public function tbswebpressor_process_changed_attachments() {
        foreach ($this->changed_attachments as $attachment_id => $old_signature) {
            if (in_array($attachment_id, $this->converted_in_request, true)) {
                continue;
            }

            $metadata = wp_get_attachment_metadata($attachment_id, true);
            if (!is_array($metadata) || $this->tbswebpressor_get_files_signature($metadata) === $old_signature) {
                continue;
            }

            TBS_WebPressor_Converter::tbswebpressor_clear_attachment_state($attachment_id);

            if (intval(get_option('tbswebpressor_convert_on_upload', 1))) {
                TBS_WebPressor_Converter::tbswebpressor_process_attachment($attachment_id, $metadata, 'upload');
            } else {
                TBS_WebPressor_Converter::tbswebpressor_clear_count_cache();
            }
        }
        $this->changed_attachments = array();
    }

    /**
     * Delete generated WebP/AVIF files when an attachment is permanently deleted.
     *
     * @since 2.2.0
     * @param int $attachment_id Attachment ID.
     */
    public function tbswebpressor_delete_attachment_variants($attachment_id) {
        $attachment_id = (int) $attachment_id;
        if (!in_array(get_post_mime_type($attachment_id), TBS_WebPressor_Converter::tbswebpressor_get_convertible_mime_types(), true)) {
            return;
        }

        TBS_WebPressor_Converter::tbswebpressor_delete_attachment_variants($attachment_id, true);
        TBS_WebPressor_Converter::tbswebpressor_clear_attachment_state($attachment_id);
        TBS_WebPressor_Converter::tbswebpressor_clear_count_cache();
    }

    /**
     * Apply the configured maximum image dimension to new uploads.
     *
     * Uses the WordPress core "big image" scaling, so the full original is kept as the
     * original_image and the scaled copy becomes the main image.
     *
     * @since 2.2.0
     * @param int|bool $threshold Current threshold.
     * @return int|bool
     */
    public function tbswebpressor_filter_big_image_threshold($threshold) {
        $max = intval(get_option('tbswebpressor_max_image_dimension', 0));
        return $max > 0 ? $max : $threshold;
    }
}
