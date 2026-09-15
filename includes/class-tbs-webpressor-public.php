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
        add_filter('wp_calculate_image_srcset', array($this, 'tbswebpressor_filter_image_srcset'), 10, 5);
        add_filter('the_content', array($this, 'tbswebpressor_replace_images_with_webp'));
        add_filter('widget_text', array($this, 'tbswebpressor_replace_images_with_webp'));
        add_filter('widget_custom_html_content', array($this, 'tbswebpressor_replace_images_with_webp'));
    }

    /**
     * Whether HTML-based delivery should run on the current request.
     *
     * @return bool
     */
    private function tbswebpressor_should_alter_html_delivery() {
        if (is_admin() || is_feed() || defined('REST_REQUEST')) {
            return false;
        }

        $delivery = get_option('tbswebpressor_delivery_method', 'html');
        return $delivery !== 'rewrite';
    }

    /**
     * Browser format support flags for the current request.
     *
     * @return array{avif:bool,webp:bool}
     */
    private function tbswebpressor_get_browser_format_support() {
        $http_accept = isset($_SERVER['HTTP_ACCEPT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT'])) : '';
        $formats = get_option('tbswebpressor_target_formats', array('webp'));

        return array(
            'avif' => in_array('avif', $formats, true) && strpos($http_accept, 'image/avif') !== false,
            'webp' => in_array('webp', $formats, true) && strpos($http_accept, 'image/webp') !== false,
        );
    }

    /**
     * Resolve a JPEG/PNG URL to AVIF/WebP when a sidecar file exists.
     *
     * @param string $url Original image URL.
     * @return string
     */
    private function tbswebpressor_resolve_next_gen_url($url) {
        $original_url = $url;
        $ext = pathinfo($url, PATHINFO_EXTENSION);
        if (!in_array(strtolower($ext), array('jpg', 'jpeg', 'png'), true)) {
            return $url;
        }

        $support = $this->tbswebpressor_get_browser_format_support();
        if (!$support['avif'] && !$support['webp']) {
            return $url;
        }

        $upload_dir = wp_upload_dir();

        if ($support['avif']) {
            $avif_url = preg_replace('/\.' . preg_quote($ext, '/') . '$/i', '.avif', $original_url);
            $relative_path = str_replace($upload_dir['baseurl'], '', $avif_url);
            $avif_path = $upload_dir['basedir'] . $relative_path;
            if (file_exists($avif_path)) {
                /**
                 * Filter the resolved next-gen image URL.
                 *
                 * @param string $resolved_url Resolved URL.
                 * @param string $original_url Original JPEG/PNG URL.
                 */
                return apply_filters('tbswebpressor_resolve_variant_url', $avif_url, $original_url);
            }
        }

        if ($support['webp']) {
            $webp_url = preg_replace('/\.' . preg_quote($ext, '/') . '$/i', '.webp', $original_url);
            $relative_path = str_replace($upload_dir['baseurl'], '', $webp_url);
            $webp_path = $upload_dir['basedir'] . $relative_path;
            if (file_exists($webp_path)) {
                return apply_filters('tbswebpressor_resolve_variant_url', $webp_url, $original_url);
            }
        }

        return $original_url;
    }

    /**
     * Check if WebP version exists and use it if browser supports it
     *
     * @since    1.0.0
     * @param    string    $url    Original attachment URL
     * @return   string            Original or WebP URL
     */
    public function tbswebpressor_maybe_serve_webp_version($url) {
        if (!$this->tbswebpressor_should_alter_html_delivery()) {
            return $url;
        }

        return $this->tbswebpressor_resolve_next_gen_url($url);
    }

    /**
     * Swap srcset sources to next-gen URLs when sidecar files exist.
     *
     * @param array  $sources       Srcset sources.
     * @param array  $size_array    Requested size.
     * @param string $image_src     Image src URL.
     * @param array  $image_meta    Attachment metadata.
     * @param int    $attachment_id Attachment ID.
     * @return array
     */
    public function tbswebpressor_filter_image_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
        unset($size_array, $image_src, $image_meta, $attachment_id);

        if (!$this->tbswebpressor_should_alter_html_delivery() || !is_array($sources)) {
            return $sources;
        }

        foreach ($sources as $width => $source) {
            if (empty($source['url'])) {
                continue;
            }
            $sources[$width]['url'] = $this->tbswebpressor_resolve_next_gen_url($source['url']);
        }

        return $sources;
    }

    /**
     * Replace image URLs with WebP/AVIF versions in content
     *
     * @since    1.0.0
     * @param    string    $content    Content to process
     * @return   string                Processed content
     */
    public function tbswebpressor_replace_images_with_webp($content) {
        if (!$this->tbswebpressor_should_alter_html_delivery()) {
            return $content;
        }

        $support = $this->tbswebpressor_get_browser_format_support();
        if (!$support['avif'] && !$support['webp']) {
            return $content;
        }

        return preg_replace_callback(
            '#<img[^>]+src=["\']([^"\']+\.(jpg|jpeg|png))["\'][^>]*>#i',
            function ($matches) {
                $original_tag = $matches[0];
                $original_url = $matches[1];
                $resolved_url = $this->tbswebpressor_resolve_next_gen_url($original_url);

                if ($resolved_url === $original_url) {
                    return $original_tag;
                }

                return str_replace($original_url, $resolved_url, $original_tag);
            },
            $content
        );
    }
}
