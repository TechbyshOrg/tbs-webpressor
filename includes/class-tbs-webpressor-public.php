<?php
/**
 * Public-facing functionality of the plugin.
 *
 * @since      1.0.0
 * @package    TBS_WebPressor
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class TBS_WebPressor_Public {

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
     * Register the hooks for the public-facing functionality
     *
     * @since    1.0.0
     */
    public function tbswebpressor_public_setup_hooks() {
        add_filter('wp_get_attachment_url', array($this, 'tbswebpressor_maybe_serve_webp_version'), 9999);
        add_filter('the_content', array($this, 'tbswebpressor_replace_images_with_webp'));
        add_filter('widget_text', array($this, 'tbswebpressor_replace_images_with_webp'));
        add_filter('widget_custom_html_content', array($this, 'tbswebpressor_replace_images_with_webp'));
    }

    /**
     * Check if WebP version exists and use it if browser supports it
     *
     * @since    1.0.0
     * @param    string    $url    Original attachment URL
     * @return   string            Original or WebP URL
     */
    public function tbswebpressor_maybe_serve_webp_version($url) {
        // Only run for front-end (not admin or REST)
        if (is_admin() || defined('REST_REQUEST')) {
            return $url;
        }
        
        $delivery = get_option('tbswebpressor_delivery_method', 'html');
        if ($delivery === 'rewrite') {
            return $url;
        }
    
        // Check if browser supports WebP or AVIF
        $http_accept = isset($_SERVER['HTTP_ACCEPT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT'])) : '';
        $formats = get_option('tbswebpressor_target_formats', array('webp'));
        
        // Check if it's an image
        $ext = pathinfo($url, PATHINFO_EXTENSION);
        if (!in_array(strtolower($ext), array('jpg', 'jpeg', 'png'))) {
            return $url;
        }
        
        $upload_dir = wp_upload_dir();
        
        // Try AVIF first
        if (in_array('avif', $formats) && strpos($http_accept, 'image/avif') !== false) {
            $avif_url = preg_replace('/\.' . preg_quote($ext, '/') . '$/i', '.avif', $url);
            $relative_path = str_replace($upload_dir['baseurl'], '', $avif_url);
            $avif_path = $upload_dir['basedir'] . $relative_path;
            if (file_exists($avif_path)) {
                return $avif_url;
            }
        }
        
        // Try WebP second
        if (in_array('webp', $formats) && strpos($http_accept, 'image/webp') !== false) {
            $webp_url = preg_replace('/\.' . preg_quote($ext, '/') . '$/i', '.webp', $url);
            $relative_path = str_replace($upload_dir['baseurl'], '', $webp_url);
            $webp_path = $upload_dir['basedir'] . $relative_path;
            if (file_exists($webp_path)) {
                return $webp_url;
            }
        }
    
        return $url;
    }

    /**
     * Replace image URLs with WebP/AVIF versions in content
     *
     * @since    1.0.0
     * @param    string    $content    Content to process
     * @return   string                Processed content
     */
    public function tbswebpressor_replace_images_with_webp($content) {
        // Skip admin and feed
        if (is_admin() || is_feed()) {
            return $content;
        }
        
        $delivery = get_option('tbswebpressor_delivery_method', 'html');
        if ($delivery === 'rewrite') {
            return $content;
        }
        
        $http_accept = isset($_SERVER['HTTP_ACCEPT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT'])) : '';
        $formats = get_option('tbswebpressor_target_formats', array('webp'));
        
        $has_avif = (in_array('avif', $formats) && strpos($http_accept, 'image/avif') !== false);
        $has_webp = (in_array('webp', $formats) && strpos($http_accept, 'image/webp') !== false);
        
        if (!$has_avif && !$has_webp) {
            return $content;
        }
    
        // Process all <img> tags with .jpg, .jpeg, .png
        return preg_replace_callback(
            '#<img[^>]+src=["\']([^"\']+\.(jpg|jpeg|png))["\'][^>]*>#i',
            function ($matches) use ($has_avif, $has_webp) {
                $original_tag = $matches[0];
                $original_url = $matches[1];
                $ext = pathinfo($original_url, PATHINFO_EXTENSION);
                
                $upload_dir = wp_upload_dir();
                
                // Try AVIF first
                if ($has_avif) {
                    $avif_url = preg_replace('/\.' . preg_quote($ext, '/') . '$/i', '.avif', $original_url);
                    $relative_path = str_replace($upload_dir['baseurl'], '', $avif_url);
                    $avif_path = $upload_dir['basedir'] . $relative_path;
                    if (file_exists($avif_path)) {
                        return str_replace($original_url, $avif_url, $original_tag);
                    }
                }
                
                // Try WebP second
                if ($has_webp) {
                    $webp_url = preg_replace('/\.' . preg_quote($ext, '/') . '$/i', '.webp', $original_url);
                    $relative_path = str_replace($upload_dir['baseurl'], '', $webp_url);
                    $webp_path = $upload_dir['basedir'] . $relative_path;
                    if (file_exists($webp_path)) {
                        return str_replace($original_url, $webp_url, $original_tag);
                    }
                }
    
                return $original_tag;
            },
            $content
        );
    }
}