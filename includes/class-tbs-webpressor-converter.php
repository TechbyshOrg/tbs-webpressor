<?php
/**
 * WebP Converter Class.
 *
 * @since      1.0.0
 * @package    TBS_WebPressor
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class TBS_WebPressor_Converter {

    /**
     * Transient key for cached media library counts.
     */
    const COUNT_CACHE_KEY = 'tbswebpressor_media_counts';

    /**
     * Transient key for bulk optimization lock.
     */
    const BULK_LOCK_KEY = 'tbswebpressor_bulk_lock';

    /**
     * Initialize the converter.
     *
     * @since    1.0.0
     */
    public function __construct() {
        // Constructor code
    }

    /**
     * MIME types eligible for JPEG/PNG conversion.
     *
     * @return string[]
     */
    public static function tbswebpressor_get_convertible_mime_types() {
        return array(
            'image/jpeg',
            'image/jpg',
            'image/png',
        );
    }

    /**
     * Post statuses included in media library queries (unchanged from legacy behavior).
     *
     * @return string[]
     */
    public static function tbswebpressor_get_attachment_post_statuses() {
        return array(
            'publish',
            'pending',
            'draft',
            'auto-draft',
            'future',
            'private',
            'inherit',
            'trash',
        );
    }

    /**
     * Build meta_query for attachments that still need conversion for active target formats.
     *
     * @param string[]|null $target_formats Optional formats list; defaults to saved option.
     * @return array|null Meta query array, or null if nothing is pending by definition.
     */
    public static function tbswebpressor_get_pending_meta_query($target_formats = null) {
        if ($target_formats === null) {
            $target_formats = get_option('tbswebpressor_target_formats', array('webp'));
        }
        $target_formats = array_map('sanitize_key', (array) $target_formats);

        $clauses = array();

        if (in_array('webp', $target_formats, true)) {
            $clauses[] = array(
                'relation' => 'OR',
                array(
                    'key'     => 'tbswebpressor_webp_path',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => 'tbswebpressor_webp_path',
                    'value'   => '',
                    'compare' => '=',
                ),
            );
        }

        if (in_array('avif', $target_formats, true)) {
            $clauses[] = array(
                'relation' => 'OR',
                array(
                    'key'     => 'tbswebpressor_avif_path',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => 'tbswebpressor_avif_path',
                    'value'   => '',
                    'compare' => '=',
                ),
            );
        }

        if (empty($clauses)) {
            return null;
        }

        return array_merge(array('relation' => 'OR'), $clauses);
    }

    /**
     * Base WP_Query arguments for convertible attachments.
     *
     * @return array
     */
    public static function tbswebpressor_get_convertible_attachment_query_args() {
        return array(
            'post_type'              => 'attachment',
            'post_status'            => self::tbswebpressor_get_attachment_post_statuses(),
            'post_mime_type'         => self::tbswebpressor_get_convertible_mime_types(),
            'fields'                 => 'ids',
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        );
    }

    /**
     * Clear cached dashboard media counts.
     */
    public static function tbswebpressor_clear_count_cache() {
        delete_transient(self::COUNT_CACHE_KEY);
    }

    /**
     * Release bulk optimization lock.
     */
    public static function tbswebpressor_release_bulk_lock() {
        delete_transient(self::BULK_LOCK_KEY);
    }

    /**
     * Convert an attachment to WebP and/or AVIF format
     *
     * @since    1.0.0
     * @param    int        $attachment_id    The attachment ID to convert
     * @param    array|null $metadata         Optional attachment metadata (e.g. from upload filter).
     * @return   array|bool                   Conversion stats or false on failure
     */
    public static function tbswebpressor_create_webp($attachment_id, $metadata = null) {
        $count = 0;
        $file = get_attached_file($attachment_id);

        if (!$file || !file_exists($file)) {
            return false;
        }

        $mime = get_post_mime_type($attachment_id);
        if (!in_array($mime, self::tbswebpressor_get_convertible_mime_types(), true)) {
            return false;
        }

        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $formats = get_option('tbswebpressor_target_formats', array('webp'));
        $webp_quality = intval(get_option('tbswebpressor_webp_quality', 80));
        $avif_quality = intval(get_option('tbswebpressor_avif_quality', 65));
        $compression_mode = get_option('tbswebpressor_compression_mode', 'lossy');

        $total_original_size = 0;
        $total_optimized_size = 0;
        $webp_path = '';
        $avif_path = '';

        // Convert original image to WebP
        if (in_array('webp', $formats, true) && function_exists('imagewebp')) {
            $webp_path = preg_replace('/\.' . preg_quote($ext, '/') . '$/', '.webp', $file);
            if (self::tbswebpressor_create_format_file($file, $webp_path, 'webp', $webp_quality, $compression_mode)) {
                $normalized_path = str_replace('\\', '/', $webp_path);
                update_post_meta($attachment_id, 'tbswebpressor_webp_path', $normalized_path);
                $total_original_size += filesize($file);
                $total_optimized_size += filesize($webp_path);
                $count++;
            }
        }

        // Convert original image to AVIF
        if (in_array('avif', $formats, true) && function_exists('imageavif')) {
            $avif_path = preg_replace('/\.' . preg_quote($ext, '/') . '$/', '.avif', $file);
            if (self::tbswebpressor_create_format_file($file, $avif_path, 'avif', $avif_quality, $compression_mode)) {
                $normalized_path = str_replace('\\', '/', $avif_path);
                update_post_meta($attachment_id, 'tbswebpressor_avif_path', $normalized_path);
                $total_original_size += filesize($file);
                $total_optimized_size += filesize($avif_path);
                $count++;
            }
        }

        // Convert thumbnails
        if ($metadata === null) {
            $metadata = wp_get_attachment_metadata($attachment_id);
        }
        if (!empty($metadata['sizes']) && !empty($metadata['file'])) {
            $upload_dir = wp_upload_dir();
            $base_dir = trailingslashit($upload_dir['basedir']);
            $subdir = trailingslashit(dirname($metadata['file']));

            foreach ($metadata['sizes'] as $size_name => $size_info) {
                $thumb_path = $base_dir . $subdir . $size_info['file'];

                if (!file_exists($thumb_path)) {
                    continue;
                }

                $thumb_ext = pathinfo($thumb_path, PATHINFO_EXTENSION);

                if (in_array('webp', $formats, true) && function_exists('imagewebp')) {
                    $thumb_webp_path = preg_replace('/\.' . preg_quote($thumb_ext, '/') . '$/', '.webp', $thumb_path);
                    if (self::tbswebpressor_create_format_file($thumb_path, $thumb_webp_path, 'webp', $webp_quality, $compression_mode)) {
                        $total_original_size += filesize($thumb_path);
                        $total_optimized_size += filesize($thumb_webp_path);
                        $count++;
                    }
                }

                if (in_array('avif', $formats, true) && function_exists('imageavif')) {
                    $thumb_avif_path = preg_replace('/\.' . preg_quote($thumb_ext, '/') . '$/', '.avif', $thumb_path);
                    if (self::tbswebpressor_create_format_file($thumb_path, $thumb_avif_path, 'avif', $avif_quality, $compression_mode)) {
                        $total_original_size += filesize($thumb_path);
                        $total_optimized_size += filesize($thumb_avif_path);
                        $count++;
                    }
                }
            }
        }

        $prev_original_size = intval(get_post_meta($attachment_id, 'tbswebpressor_original_size', true));
        $prev_optimized_size = intval(get_post_meta($attachment_id, 'tbswebpressor_optimized_size', true));

        // Save stats
        update_post_meta($attachment_id, 'tbswebpressor_original_size', $total_original_size);
        if ($total_optimized_size > 0) {
            update_post_meta($attachment_id, 'tbswebpressor_optimized_size', $total_optimized_size);

            $global_orig = intval(get_option('tbswebpressor_total_original_size', 0));
            $global_opt  = intval(get_option('tbswebpressor_total_optimized_size', 0));

            $global_orig = max(0, $global_orig - $prev_original_size + $total_original_size);
            $global_opt  = max(0, $global_opt - $prev_optimized_size + $total_optimized_size);

            update_option('tbswebpressor_total_original_size', $global_orig);
            update_option('tbswebpressor_total_optimized_size', $global_opt);
        }

        self::tbswebpressor_clear_count_cache();

        return array(
            'count' => $count,
            'webp_path' => $webp_path,
            'avif_path' => $avif_path,
            'original_size' => $total_original_size,
            'optimized_size' => $total_optimized_size,
            'filename' => basename($file),
        );
    }

    /**
     * Create a WebP image from a source file (legacy function)
     */
    public static function tbswebpressor_create_webp_file($source, $destination, $quality = 80) {
        return self::tbswebpressor_create_format_file($source, $destination, 'webp', $quality, 'lossy');
    }

    /**
     * General conversion function to create WebP/AVIF images
     */
    public static function tbswebpressor_create_format_file($source, $destination, $format, $quality = 80, $compression_mode = 'lossy') {
        if ($format === 'webp' && !function_exists('imagewebp')) {
            return false;
        }
        if ($format === 'avif' && !function_exists('imageavif')) {
            return false;
        }
        if (!file_exists($source)) {
            return false;
        }

        $info = getimagesize($source);
        if (!$info || !isset($info['mime'])) {
            return false;
        }

        switch ($info['mime']) {
            case 'image/jpeg':
            case 'image/jpg':
                $image = imagecreatefromjpeg($source);
                break;
            case 'image/png':
                $image = imagecreatefrompng($source);
                if ($image) {
                    imagepalettetotruecolor($image);
                    imagealphablending($image, true);
                    imagesavealpha($image, true);
                }
                break;
            default:
                return false;
        }

        if (!$image) {
            return false;
        }

        if ($compression_mode === 'lossless') {
            $quality = 100;
        }

        if ($format === 'webp') {
            $success = imagewebp($image, $destination, $quality);
        } elseif ($format === 'avif') {
            $success = imageavif($image, $destination, $quality);
        } else {
            $success = false;
        }

        imagedestroy($image);
        return $success;
    }

    /**
     * Convert the next batch of pending attachments (page argument retained for API compatibility).
     *
     * @param int $page Unused; each batch always processes the next pending items.
     * @return array
     */
    public static function tbswebpressor_convert_attachements_batch($page) {
        unset($page);

        $pending_meta = self::tbswebpressor_get_pending_meta_query();
        if ($pending_meta === null) {
            self::tbswebpressor_release_bulk_lock();
            return array(
                'hasMorePages' => false,
                'processed'    => array(),
            );
        }

        $args = array_merge(
            self::tbswebpressor_get_convertible_attachment_query_args(),
            array(
                'posts_per_page' => 5,
                'paged'          => 1,
                'meta_query'     => $pending_meta,
            )
        );

        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        $attachments = new WP_Query($args);
        $processed = array();

        if ($attachments->have_posts()) {
            foreach ($attachments->posts as $attachment_id) {
                $res = self::tbswebpressor_create_webp((int) $attachment_id);
                if ($res) {
                    $processed[] = $res;
                }
            }
        }

        $has_more = false;
        if ($attachments->found_posts > 5) {
            $has_more = true;
        } elseif ($attachments->found_posts > 0 && count($processed) > 0) {
            $remaining_args = array_merge(
                self::tbswebpressor_get_convertible_attachment_query_args(),
                array(
                    'posts_per_page' => 1,
                    'paged'          => 1,
                    'meta_query'     => $pending_meta,
                )
            );
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            $remaining = new WP_Query($remaining_args);
            $has_more = $remaining->found_posts > 0;
        }

        if (!$has_more) {
            self::tbswebpressor_release_bulk_lock();
        }

        return array(
            'hasMorePages' => $has_more,
            'processed'    => $processed,
        );
    }

    /**
     * Count convertible attachments and pending items (cached briefly for dashboard performance).
     *
     * @param bool $force_refresh Skip transient cache.
     * @return array{total:int,pending:int}
     */
    public static function tbswebpressor_get_media_counts($force_refresh = false) {
        if (!$force_refresh) {
            $cached = get_transient(self::COUNT_CACHE_KEY);
            if (is_array($cached) && isset($cached['total'], $cached['pending'])) {
                return array(
                    'total'   => (int) $cached['total'],
                    'pending' => (int) $cached['pending'],
                );
            }
        }

        $base_args = self::tbswebpressor_get_convertible_attachment_query_args();

        $total_query = new WP_Query(array_merge($base_args, array(
            'posts_per_page' => 1,
        )));
        $total = (int) $total_query->found_posts;

        $pending_meta = self::tbswebpressor_get_pending_meta_query();
        $pending = 0;
        if ($pending_meta !== null) {
            $pending_query = new WP_Query(array_merge($base_args, array(
                'posts_per_page' => 1,
                'meta_query'     => $pending_meta,
            )));
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- pending count for admin dashboard.
            $pending = (int) $pending_query->found_posts;
        }

        $counts = array(
            'total'   => $total,
            'pending' => $pending,
        );

        set_transient(self::COUNT_CACHE_KEY, $counts, 5 * MINUTE_IN_SECONDS);

        return $counts;
    }

    /**
     * Update .htaccess rewrite rules in the uploads directory
     */
    public static function tbswebpressor_update_htaccess() {
        $upload_dir = wp_upload_dir();
        $htaccess_path = trailingslashit($upload_dir['basedir']) . '.htaccess';

        $delivery = get_option('tbswebpressor_delivery_method', 'html');

        if ($delivery === 'rewrite') {
            $formats = get_option('tbswebpressor_target_formats', array('webp'));

            $base_path = parse_url($upload_dir['baseurl'], PHP_URL_PATH);
            if (!is_string($base_path) || $base_path === '') {
                $base_path = '/';
            }
            $base_path = trailingslashit($base_path);

            $rules = "\n# BEGIN WebPressor\n";
            $rules .= "<IfModule mod_rewrite.c>\n";
            $rules .= "  RewriteEngine On\n";
            $rules .= '  RewriteBase ' . $base_path . "\n\n";

            if (in_array('avif', $formats, true)) {
                $rules .= "  # Serve AVIF\n";
                $rules .= "  RewriteCond %{HTTP_ACCEPT} image/avif\n";
                $rules .= "  RewriteCond %{REQUEST_FILENAME} ^(.*)\.(jpe?g|png)$ [NC]\n";
                $rules .= "  RewriteCond %1.avif -f\n";
                $rules .= "  RewriteRule ^(.*)\.(jpe?g|png)$ \$1.avif [T=image/avif,L]\n\n";
            }

            if (in_array('webp', $formats, true)) {
                $rules .= "  # Serve WebP\n";
                $rules .= "  RewriteCond %{HTTP_ACCEPT} image/webp\n";
                $rules .= "  RewriteCond %{REQUEST_FILENAME} ^(.*)\.(jpe?g|png)$ [NC]\n";
                $rules .= "  RewriteCond %1.webp -f\n";
                $rules .= "  RewriteRule ^(.*)\.(jpe?g|png)$ \$1.webp [T=image/webp,L]\n\n";
            }

            $rules .= "</IfModule>\n";

            $rules .= "<IfModule mod_headers.c>\n";
            $rules .= "  Header append Vary Accept env=REDIRECT_image\n";
            $rules .= "</IfModule>\n";

            if (in_array('webp', $formats, true)) {
                $rules .= "AddType image/webp .webp\n";
            }
            if (in_array('avif', $formats, true)) {
                $rules .= "AddType image/avif .avif\n";
            }
            $rules .= "# END WebPressor\n";

            if (file_exists($htaccess_path)) {
                $content = file_get_contents($htaccess_path);
                $content = preg_replace('/# BEGIN WebPressor.*?# END WebPressor\s*/s', '', $content);
                $content = trim($content) . "\n" . $rules;
                file_put_contents($htaccess_path, $content);
            } else {
                file_put_contents($htaccess_path, $rules);
            }
        } else {
            if (file_exists($htaccess_path)) {
                $content = file_get_contents($htaccess_path);
                $content = preg_replace('/# BEGIN WebPressor.*?# END WebPressor\s*/s', '', $content);
                file_put_contents($htaccess_path, trim($content));
            }
        }
    }
}
