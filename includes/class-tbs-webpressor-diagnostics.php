<?php
/**
 * Delivery self-test.
 *
 * @since      2.2.0
 * @package    TBS_WebPressor
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class TBS_WebPressor_Diagnostics {

    /**
     * Find a converted image whose files exist, for testing.
     *
     * @return array|null { id, original_url, variants: format => url }
     */
    private static function tbswebpressor_find_sample() {
        $formats = TBS_WebPressor_Converter::tbswebpressor_get_active_formats();
        if (empty($formats)) {
            return null;
        }

        $meta_query = array('relation' => 'OR');
        foreach ($formats as $format) {
            $meta_query[] = array(
                'key'     => TBS_WebPressor_Converter::tbswebpressor_get_path_meta_key($format),
                'compare' => 'EXISTS',
            );
        }

        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        $query = new WP_Query(array_merge(TBS_WebPressor_Converter::tbswebpressor_get_convertible_attachment_query_args(), array(
            'posts_per_page' => 10,
            'no_found_rows'  => true,
            'post_status'    => 'inherit',
            'meta_query'     => $meta_query,
        )));

        foreach ($query->posts as $attachment_id) {
            $file = get_attached_file($attachment_id);
            $url  = wp_get_original_image_url($attachment_id);
            if (!$file || !file_exists($file)) {
                continue;
            }
            // Use the URL of the attached (possibly scaled) file, which is the one that has siblings.
            $url = trailingslashit(dirname((string) $url)) . wp_basename($file);

            $variants = array();
            foreach ($formats as $format) {
                $variant_path = TBS_WebPressor_Converter::tbswebpressor_get_variant_path($file, $format);
                if ($variant_path !== '' && file_exists($variant_path)) {
                    $variants[$format] = TBS_WebPressor_Converter::tbswebpressor_get_variant_path($url, $format);
                }
            }

            if (!empty($variants)) {
                return array(
                    'id'           => (int) $attachment_id,
                    'original_url' => $url,
                    'variants'     => $variants,
                );
            }
        }

        return null;
    }

    /**
     * Request a URL on this site.
     *
     * @param string $url    URL.
     * @param string $accept Accept header.
     * @return array|WP_Error { code, type, vary }
     */
    private static function tbswebpressor_request($url, $accept) {
        $response = wp_remote_get(add_query_arg('tbswebpressor_test', time(), $url), array(
            'timeout'             => 10,
            'redirection'         => 2,
            'limit_response_size' => 2048,
            'sslverify'           => apply_filters('https_local_ssl_verify', false),
            'headers'             => array(
                'Accept'        => $accept,
                'Cache-Control' => 'no-cache',
            ),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $type = (string) wp_remote_retrieve_header($response, 'content-type');
        return array(
            'code' => (int) wp_remote_retrieve_response_code($response),
            'type' => strtolower(trim(explode(';', $type)[0])),
            'vary' => strtolower((string) wp_remote_retrieve_header($response, 'vary')),
        );
    }

    /**
     * Build one check row.
     *
     * @param string $label   Check label.
     * @param string $status  pass|warn|fail|info.
     * @param string $message Detail.
     * @return array
     */
    private static function tbswebpressor_check($label, $status, $message) {
        return array(
            'label'   => $label,
            'status'  => $status,
            'message' => $message,
        );
    }

    /**
     * Run the delivery self-test.
     *
     * @return array { checks: array[], sample: string }
     */
    public static function tbswebpressor_run_delivery_test() {
        $checks   = array();
        $delivery = get_option('tbswebpressor_delivery_method', 'html');
        $sample   = self::tbswebpressor_find_sample();

        if ($sample === null) {
            $checks[] = self::tbswebpressor_check(
                __('Converted image available', 'webpressor-webp-image-converter-optimizer'),
                'info',
                __('No converted image was found. Run the Bulk Optimizer first, then repeat this test.', 'webpressor-webp-image-converter-optimizer')
            );
            return array('checks' => $checks, 'sample' => '');
        }

        $mime_types = array(
            'webp' => 'image/webp',
            'avif' => 'image/avif',
        );

        // 1. Each generated file is reachable and served with the right content type.
        foreach ($sample['variants'] as $format => $variant_url) {
            /* translators: %s: image format name, e.g. WEBP. */
            $label    = sprintf(__('%s file is served correctly', 'webpressor-webp-image-converter-optimizer'), strtoupper($format));
            $response = self::tbswebpressor_request($variant_url, $mime_types[$format] . ',*/*');

            if (is_wp_error($response)) {
                $checks[] = self::tbswebpressor_check($label, 'warn', sprintf(
                    /* translators: %s: error message. */
                    __('Your site could not connect to itself to run this test (%s). Your host may block loopback requests; delivery can still work for visitors.', 'webpressor-webp-image-converter-optimizer'),
                    $response->get_error_message()
                ));
                return array('checks' => $checks, 'sample' => $sample['original_url']);
            }

            if ($response['code'] !== 200) {
                $checks[] = self::tbswebpressor_check($label, 'fail', sprintf(
                    /* translators: %d: HTTP status code. */
                    __('The file returned HTTP status %d. Check that the uploads folder is publicly accessible.', 'webpressor-webp-image-converter-optimizer'),
                    $response['code']
                ));
            } elseif ($response['type'] !== $mime_types[$format]) {
                $checks[] = self::tbswebpressor_check($label, 'warn', sprintf(
                    /* translators: 1: content type returned by the server, 2: expected content type. */
                    __('The server sends this file as "%1$s" instead of "%2$s". Some browsers may refuse to display it. Ask your host to add the correct MIME type.', 'webpressor-webp-image-converter-optimizer'),
                    $response['type'] !== '' ? $response['type'] : __('unknown', 'webpressor-webp-image-converter-optimizer'),
                    $mime_types[$format]
                ));
            } else {
                $checks[] = self::tbswebpressor_check($label, 'pass', __('The file loads with the correct content type.', 'webpressor-webp-image-converter-optimizer'));
            }
        }

        // 2. Delivery method behaviour.
        if ($delivery === 'rewrite') {
            $best    = isset($sample['variants']['avif']) ? 'avif' : 'webp';
            $label   = __('Server rewrite rules', 'webpressor-webp-image-converter-optimizer');
            $request = self::tbswebpressor_request($sample['original_url'], 'image/avif,image/webp,image/*,*/*;q=0.8');

            if (is_wp_error($request)) {
                $checks[] = self::tbswebpressor_check($label, 'warn', $request->get_error_message());
            } elseif ($request['type'] === $mime_types[$best] || in_array($request['type'], array('image/avif', 'image/webp'), true)) {
                $checks[] = self::tbswebpressor_check($label, 'pass', sprintf(
                    /* translators: %s: content type. */
                    __('Requests for the JPEG/PNG URL receive %s.', 'webpressor-webp-image-converter-optimizer'),
                    $request['type']
                ));
                if (strpos($request['vary'], 'accept') === false) {
                    $checks[] = self::tbswebpressor_check(
                        __('Vary: Accept header', 'webpressor-webp-image-converter-optimizer'),
                        'warn',
                        __('The response has no "Vary: Accept" header. A CDN or proxy cache may serve WebP/AVIF to browsers that cannot display it. Enable mod_headers or switch to <picture> delivery.', 'webpressor-webp-image-converter-optimizer')
                    );
                } else {
                    $checks[] = self::tbswebpressor_check(__('Vary: Accept header', 'webpressor-webp-image-converter-optimizer'), 'pass', __('Caches are told that the response depends on browser support.', 'webpressor-webp-image-converter-optimizer'));
                }
            } else {
                $server = isset($_SERVER['SERVER_SOFTWARE']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE']))) : '';
                $hint   = strpos($server, 'nginx') !== false
                    ? __('Nginx ignores .htaccess files. Add the Nginx rules shown on the Settings tab, or switch to <picture> delivery.', 'webpressor-webp-image-converter-optimizer')
                    : __('The rewrite rules are not active. Check that mod_rewrite is enabled and that .htaccess files are allowed, or switch to <picture> delivery.', 'webpressor-webp-image-converter-optimizer');
                $checks[] = self::tbswebpressor_check($label, 'fail', sprintf(
                    /* translators: 1: content type returned, 2: hint. */
                    __('The original image was returned as "%1$s". %2$s', 'webpressor-webp-image-converter-optimizer'),
                    $request['type'],
                    $hint
                ));
            }
        } elseif ($delivery === 'picture') {
            $checks[] = self::tbswebpressor_check(
                __('<picture> delivery', 'webpressor-webp-image-converter-optimizer'),
                'pass',
                __('Browsers choose the best format themselves, so page caches and CDNs always serve a working image.', 'webpressor-webp-image-converter-optimizer')
            );
        } else {
            $cache = defined('WP_CACHE') && WP_CACHE;
            $checks[] = self::tbswebpressor_check(
                __('HTML alteration and page caching', 'webpressor-webp-image-converter-optimizer'),
                $cache ? 'warn' : 'pass',
                $cache
                    ? __('A page cache is active. HTML alteration picks the format per visitor, so a cached page can show the wrong format to other browsers. Switch to <picture> delivery for cache-safe results.', 'webpressor-webp-image-converter-optimizer')
                    : __('No page cache was detected. Image URLs are rewritten per visitor based on browser support.', 'webpressor-webp-image-converter-optimizer')
            );
        }

        return array('checks' => $checks, 'sample' => $sample['original_url']);
    }
}
