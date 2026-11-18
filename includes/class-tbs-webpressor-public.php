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
     * Per-request cache of variant lookups (url|format => variant url or '').
     *
     * @var array<string,string>
     */
    private $variant_cache = array();

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

        // <picture> delivery runs after core adds srcset/sizes to content images (priority 12).
        add_filter('the_content', array($this, 'tbswebpressor_wrap_images_in_picture'), 20);
        add_filter('widget_text', array($this, 'tbswebpressor_wrap_images_in_picture'), 20);
        add_filter('widget_custom_html_content', array($this, 'tbswebpressor_wrap_images_in_picture'), 20);
        add_filter('widget_block_content', array($this, 'tbswebpressor_wrap_images_in_picture'), 20);
        add_filter('post_thumbnail_html', array($this, 'tbswebpressor_wrap_images_in_picture'), 20);

        add_action('template_redirect', array($this, 'tbswebpressor_start_output_buffer'), 9999);
    }

    /**
     * Saved delivery method (html, picture or rewrite).
     *
     * @return string
     */
    private function tbswebpressor_get_delivery_method() {
        $delivery = get_option('tbswebpressor_delivery_method', 'html');
        return in_array($delivery, array('html', 'picture', 'rewrite'), true) ? $delivery : 'html';
    }

    /**
     * Whether HTML processing covers the whole page instead of content areas only.
     *
     * @return bool
     */
    private function tbswebpressor_is_full_page_scope() {
        return get_option('tbswebpressor_html_scope', 'content') === 'full';
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
     * Map an uploads URL to its file path.
     *
     * @param string $url Image URL (absolute, protocol-relative or root-relative).
     * @return string File path, or empty string when the URL is not inside the uploads folder.
     */
    private function tbswebpressor_url_to_upload_path($url) {
        $upload_dir = wp_upload_dir();
        $url        = html_entity_decode($url, ENT_QUOTES);
        $no_scheme  = preg_replace('#^https?:#i', '', $url);
        $base       = preg_replace('#^https?:#i', '', untrailingslashit($upload_dir['baseurl']));
        $relative   = '';

        if (stripos($no_scheme, $base . '/') === 0) {
            $relative = substr($no_scheme, strlen($base));
        } elseif (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            $base_path = untrailingslashit((string) wp_parse_url($upload_dir['baseurl'], PHP_URL_PATH));
            if ($base_path !== '' && strpos($url, $base_path . '/') === 0) {
                $relative = substr($url, strlen($base_path));
            }
        }

        if ($relative === '' || strpos($relative, '..') !== false) {
            return '';
        }

        return untrailingslashit($upload_dir['basedir']) . rawurldecode($relative);
    }

    /**
     * Variant URL for a JPEG/PNG uploads URL when the sibling file exists.
     *
     * @param string $url    Original image URL.
     * @param string $format Output format.
     * @return string Variant URL, or empty string.
     */
    private function tbswebpressor_get_variant_url($url, $format) {
        $cache_key = $url . '|' . $format;
        if (isset($this->variant_cache[$cache_key])) {
            return $this->variant_cache[$cache_key];
        }

        $variant_url = '';
        $ext = pathinfo($url, PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), array('jpg', 'jpeg', 'png'), true)) {
            $candidate = preg_replace('/\.' . preg_quote($ext, '/') . '$/i', '.' . $format, $url);
            $path      = $this->tbswebpressor_url_to_upload_path($candidate);
            if ($path !== '' && file_exists($path)) {
                $variant_url = $candidate;
            }
        }

        $this->variant_cache[$cache_key] = $variant_url;
        return $variant_url;
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

        foreach (array('avif', 'webp') as $format) {
            if (!$support[$format]) {
                continue;
            }
            $variant_url = $this->tbswebpressor_get_variant_url($original_url, $format);
            if ($variant_url !== '') {
                /**
                 * Filter the resolved next-gen image URL.
                 *
                 * @param string $resolved_url Resolved URL.
                 * @param string $original_url Original JPEG/PNG URL.
                 */
                return apply_filters('tbswebpressor_resolve_variant_url', $variant_url, $original_url);
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
        if (!$this->tbswebpressor_should_alter_html_delivery() || $this->tbswebpressor_get_delivery_method() !== 'html') {
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

        if (!$this->tbswebpressor_should_alter_html_delivery() || $this->tbswebpressor_get_delivery_method() !== 'html' || !is_array($sources)) {
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
        if (!$this->tbswebpressor_should_alter_html_delivery()
            || $this->tbswebpressor_get_delivery_method() !== 'html'
            || $this->tbswebpressor_is_full_page_scope()) {
            return $content;
        }

        $support = $this->tbswebpressor_get_browser_format_support();
        if (!$support['avif'] && !$support['webp']) {
            return $content;
        }

        $processed = preg_replace_callback(
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

        return is_string($processed) ? $processed : $content;
    }

    /**
     * Wrap content images in <picture> elements (cache-safe delivery).
     *
     * @since 2.2.0
     * @param string $content Content to process.
     * @return string
     */
    public function tbswebpressor_wrap_images_in_picture($content) {
        if (!is_string($content) || $content === ''
            || !$this->tbswebpressor_should_alter_html_delivery()
            || $this->tbswebpressor_get_delivery_method() !== 'picture'
            || $this->tbswebpressor_is_full_page_scope()) {
            return $content;
        }

        return $this->tbswebpressor_wrap_images_in_html($content);
    }

    /**
     * Wrap every eligible <img> of an HTML fragment in a <picture> element.
     *
     * Images that are already inside <picture>, <script>, <style>, <textarea> or <noscript>
     * are left untouched.
     *
     * @param string $html HTML.
     * @return string
     */
    private function tbswebpressor_wrap_images_in_html($html) {
        if (stripos($html, '<img') === false) {
            return $html;
        }

        $processed = preg_replace_callback(
            '#<(picture|script|style|textarea|noscript)\b.*?</\1\s*>|<img\b[^>]*>#is',
            function ($matches) {
                if (!empty($matches[1])) {
                    return $matches[0];
                }
                return $this->tbswebpressor_build_picture($matches[0]);
            },
            $html
        );

        return is_string($processed) ? $processed : $html;
    }

    /**
     * Read an attribute value from a single HTML tag.
     *
     * @param string $tag  HTML tag.
     * @param string $name Attribute name.
     * @return string|null Raw attribute value, or null when absent.
     */
    private function tbswebpressor_get_tag_attribute($tag, $name) {
        if (preg_match('#\s' . preg_quote($name, '#') . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\')#i', $tag, $m)) {
            return isset($m[2]) && $m[2] !== '' ? $m[2] : $m[1];
        }
        return null;
    }

    /**
     * Build a <picture> element for one <img> tag.
     *
     * @param string $tag <img> tag.
     * @return string
     */
    private function tbswebpressor_build_picture($tag) {
        if (stripos($tag, 'tbswebpressor-skip') !== false) {
            return $tag;
        }

        /**
         * Filter whether an image tag should be left unchanged by <picture> delivery.
         *
         * @param bool   $skip Whether to skip the image.
         * @param string $tag  The <img> tag.
         */
        if (apply_filters('tbswebpressor_skip_image', false, $tag)) {
            return $tag;
        }

        $src = $this->tbswebpressor_get_tag_attribute($tag, 'src');
        if ($src === null || !preg_match('/\.(jpe?g|png)$/i', $src)) {
            return $tag;
        }

        $srcset = $this->tbswebpressor_get_tag_attribute($tag, 'srcset');
        $sizes  = $this->tbswebpressor_get_tag_attribute($tag, 'sizes');
        if (($srcset !== null && strpos($srcset, '"') !== false) || ($sizes !== null && strpos($sizes, '"') !== false)) {
            return $tag;
        }

        $candidates = array();
        if ($srcset !== null && trim($srcset) !== '') {
            foreach (explode(',', $srcset) as $candidate) {
                $parts = preg_split('/\s+/', trim($candidate), 2);
                if (!empty($parts[0])) {
                    $candidates[] = array($parts[0], isset($parts[1]) ? $parts[1] : '');
                }
            }
        }
        if (empty($candidates)) {
            $candidates[] = array($src, '');
        }

        $formats = (array) get_option('tbswebpressor_target_formats', array('webp'));
        $sources = '';

        foreach (array('avif', 'webp') as $format) {
            if (!in_array($format, $formats, true)) {
                continue;
            }

            $has_variant = false;
            $entries     = array();
            foreach ($candidates as $candidate) {
                $variant = $this->tbswebpressor_get_variant_url($candidate[0], $format);
                if ($variant !== '') {
                    $has_variant = true;
                    /** This filter is documented in includes/class-tbs-webpressor-public.php */
                    $variant = apply_filters('tbswebpressor_resolve_variant_url', $variant, $candidate[0]);
                }
                $entries[] = trim(($variant !== '' ? $variant : $candidate[0]) . ' ' . $candidate[1]);
            }

            if ($has_variant) {
                $sources .= '<source type="image/' . $format . '" srcset="' . implode(', ', $entries) . '"'
                    . ($sizes !== null && $sizes !== '' ? ' sizes="' . $sizes . '"' : '') . '>';
            }
        }

        if ($sources === '') {
            return $tag;
        }

        $picture = '<picture class="tbswebpressor-picture" style="display:contents">' . $sources . $tag . '</picture>';

        /**
         * Filter the generated <picture> markup.
         *
         * @param string $picture Picture element HTML.
         * @param string $tag     Original <img> tag.
         */
        return (string) apply_filters('tbswebpressor_picture_html', $picture, $tag);
    }

    /**
     * Start buffering the full page when whole-page processing is enabled.
     *
     * @since 2.2.0
     */
    public function tbswebpressor_start_output_buffer() {
        if (!$this->tbswebpressor_is_full_page_scope()
            || !$this->tbswebpressor_should_alter_html_delivery()
            || wp_doing_ajax()
            || wp_doing_cron()
            || wp_is_json_request()
            || is_embed()
            || is_robots()
            || (defined('WP_CLI') && WP_CLI)) {
            return;
        }

        /**
         * Filter whether WebPressor buffers and processes the full page HTML.
         *
         * @param bool $enabled Whether to buffer the page.
         */
        if (!apply_filters('tbswebpressor_enable_output_buffer', true)) {
            return;
        }

        ob_start(array($this, 'tbswebpressor_process_output_buffer'));
    }

    /**
     * Whether the current response is an HTML document.
     *
     * @return bool
     */
    private function tbswebpressor_is_html_response() {
        foreach (headers_list() as $header) {
            if (stripos($header, 'content-type:') === 0) {
                return stripos($header, 'text/html') !== false;
            }
        }
        return true;
    }

    /**
     * Process the buffered page HTML.
     *
     * @since 2.2.0
     * @param string $html Page HTML.
     * @return string
     */
    public function tbswebpressor_process_output_buffer($html) {
        if (!is_string($html) || $html === '' || !$this->tbswebpressor_is_html_response()) {
            return $html;
        }

        if ($this->tbswebpressor_get_delivery_method() === 'picture') {
            return $this->tbswebpressor_wrap_images_in_html($html);
        }

        return $this->tbswebpressor_replace_urls_in_html($html);
    }

    /**
     * Replace JPEG/PNG upload URLs in image tags and inline style attributes (HTML delivery, full page).
     *
     * @param string $html Page HTML.
     * @return string
     */
    private function tbswebpressor_replace_urls_in_html($html) {
        $support = $this->tbswebpressor_get_browser_format_support();
        if (!$support['avif'] && !$support['webp']) {
            return $html;
        }

        $url_pattern = '#(?:(?:https?:)?//|/)[^\s"\'(),<>]+?\.(?:jpe?g|png)(?=$|[\s"\'),;]|&quot;|&\#039;)#i';
        $replace_urls = function ($fragment) use ($url_pattern) {
            $result = preg_replace_callback(
                $url_pattern,
                function ($m) {
                    return $this->tbswebpressor_resolve_next_gen_url($m[0]);
                },
                $fragment
            );
            return is_string($result) ? $result : $fragment;
        };

        $processed = preg_replace_callback(
            '#<(?:img|source)\b[^>]*>#i',
            function ($m) use ($replace_urls) {
                return $replace_urls($m[0]);
            },
            $html
        );
        if (!is_string($processed)) {
            return $html;
        }

        $styled = preg_replace_callback(
            '#\sstyle\s*=\s*(["\'])(.*?)\1#is',
            function ($m) use ($replace_urls) {
                if (stripos($m[2], 'url(') === false) {
                    return $m[0];
                }
                return $replace_urls($m[0]);
            },
            $processed
        );

        return is_string($styled) ? $styled : $processed;
    }
}
