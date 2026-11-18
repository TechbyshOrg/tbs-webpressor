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
     * Per-format status: the generated file was not smaller than the source and was discarded.
     */
    const STATUS_LARGER = 'larger';

    /**
     * Per-format status: conversion failed.
     */
    const STATUS_FAILED = 'failed';

    /**
     * Per-format status: attachment is excluded from optimization.
     */
    const STATUS_EXCLUDED = 'excluded';

    /**
     * Post meta key holding the last conversion error message.
     */
    const ERROR_META_KEY = 'tbswebpressor_error';

    /**
     * Post meta key flagging an attachment that was manually excluded.
     */
    const EXCLUDED_META_KEY = 'tbswebpressor_excluded';

    /**
     * Post meta key set while an attachment is being converted (used to detect crashed attempts).
     */
    const PROCESSING_META_KEY = 'tbswebpressor_processing';

    /**
     * Initialize the converter.
     *
     * @since    1.0.0
     */
    public function __construct() {
        // Constructor code
    }

    /**
     * All output formats the plugin knows about.
     *
     * @return string[]
     */
    public static function tbswebpressor_get_all_formats() {
        return array('webp', 'avif');
    }

    /**
     * Whether the server can encode a given output format.
     *
     * @param string $format Output format.
     * @return bool
     */
    public static function tbswebpressor_is_format_supported($format) {
        if ($format === 'webp') {
            return function_exists('imagewebp');
        }
        if ($format === 'avif') {
            return function_exists('imageavif');
        }
        return false;
    }

    /**
     * Sanitize a list of target formats to known values (at least one format is always returned).
     *
     * @param mixed $formats Raw formats.
     * @return string[]
     */
    public static function tbswebpressor_sanitize_target_formats($formats) {
        $formats = array_map('sanitize_key', (array) $formats);
        $formats = array_values(array_intersect(self::tbswebpressor_get_all_formats(), $formats));
        return empty($formats) ? array('webp') : $formats;
    }

    /**
     * Formats that are selected in settings AND can be produced on this server.
     *
     * @param string[]|null $target_formats Optional formats list; defaults to saved option.
     * @return string[]
     */
    public static function tbswebpressor_get_active_formats($target_formats = null) {
        if ($target_formats === null) {
            $target_formats = get_option('tbswebpressor_target_formats', array('webp'));
        }
        $target_formats = array_map('sanitize_key', (array) $target_formats);

        $active = array();
        foreach (self::tbswebpressor_get_all_formats() as $format) {
            if (in_array($format, $target_formats, true) && self::tbswebpressor_is_format_supported($format)) {
                $active[] = $format;
            }
        }

        /**
         * Filter the output formats that will be generated.
         *
         * @param string[] $active Active formats.
         */
        $active = (array) apply_filters('tbswebpressor_active_formats', $active);

        return array_values(array_intersect(self::tbswebpressor_get_all_formats(), $active));
    }

    /**
     * Post meta key that stores the converted original path for a format.
     *
     * @param string $format Output format.
     * @return string
     */
    public static function tbswebpressor_get_path_meta_key($format) {
        return 'tbswebpressor_' . $format . '_path';
    }

    /**
     * Post meta key that stores the processing status for a format.
     *
     * @param string $format Output format.
     * @return string
     */
    public static function tbswebpressor_get_status_meta_key($format) {
        return 'tbswebpressor_' . $format . '_status';
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
     * An attachment is pending for a format when it has neither a converted path nor a
     * recorded status (skipped as larger, failed, or excluded) for that format.
     *
     * @param string[]|null $target_formats Optional formats list; defaults to saved option.
     * @return array|null Meta query array, or null if nothing is pending by definition.
     */
    public static function tbswebpressor_get_pending_meta_query($target_formats = null) {
        $formats = self::tbswebpressor_get_active_formats($target_formats);

        $clauses = array();
        foreach ($formats as $format) {
            $path_key = self::tbswebpressor_get_path_meta_key($format);
            $clauses[] = array(
                'relation' => 'AND',
                array(
                    'relation' => 'OR',
                    array(
                        'key'     => $path_key,
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key'     => $path_key,
                        'value'   => '',
                        'compare' => '=',
                    ),
                ),
                array(
                    'key'     => self::tbswebpressor_get_status_meta_key($format),
                    'compare' => 'NOT EXISTS',
                ),
            );
        }

        if (empty($clauses)) {
            return null;
        }

        return array_merge(array('relation' => 'OR'), $clauses);
    }

    /**
     * Build meta_query matching attachments with a given status for any active format.
     *
     * @param string $status Status value.
     * @return array|null
     */
    public static function tbswebpressor_get_status_meta_query($status) {
        $clauses = array();
        foreach (self::tbswebpressor_get_active_formats() as $format) {
            $clauses[] = array(
                'key'   => self::tbswebpressor_get_status_meta_key($format),
                'value' => $status,
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
     * Build the sibling path for a next-gen variant of an image file.
     *
     * @param string $path   Source file path.
     * @param string $format Output format.
     * @return string Variant path, or empty string when the path has no extension.
     */
    public static function tbswebpressor_get_variant_path($path, $format) {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if ($ext === '') {
            return '';
        }
        return preg_replace('/\.' . preg_quote($ext, '/') . '$/', '.' . $format, $path);
    }

    /**
     * Absolute paths of the registered size files of an attachment (excluding the main file).
     *
     * @param string     $file     Main attached file path.
     * @param array|bool $metadata Attachment metadata.
     * @return string[]
     */
    public static function tbswebpressor_get_size_files($file, $metadata) {
        $paths = array();
        if (!is_array($metadata) || empty($metadata['sizes']) || !is_array($metadata['sizes'])) {
            return $paths;
        }

        $dir = trailingslashit(dirname($file));
        foreach ($metadata['sizes'] as $size_info) {
            if (empty($size_info['file'])) {
                continue;
            }
            $path = $dir . wp_basename($size_info['file']);
            if ($path !== $file) {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * All source image files of an attachment that may have next-gen siblings.
     *
     * @param int        $attachment_id   Attachment ID.
     * @param array|null $metadata        Optional metadata.
     * @param bool       $include_backups Include files kept by the WordPress image editor.
     * @return string[]
     */
    public static function tbswebpressor_get_attachment_files($attachment_id, $metadata = null, $include_backups = false) {
        $file = get_attached_file($attachment_id);
        if (!$file) {
            return array();
        }

        if ($metadata === null) {
            $metadata = wp_get_attachment_metadata($attachment_id);
        }

        $files = array_merge(array($file), self::tbswebpressor_get_size_files($file, $metadata));

        if ($include_backups) {
            $backups = get_post_meta($attachment_id, '_wp_attachment_backup_sizes', true);
            if (is_array($backups)) {
                $dir = trailingslashit(dirname($file));
                foreach ($backups as $backup) {
                    if (!empty($backup['file'])) {
                        $files[] = $dir . wp_basename($backup['file']);
                    }
                }
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * Delete all generated WebP/AVIF siblings of an attachment's files.
     *
     * @param int        $attachment_id   Attachment ID.
     * @param bool       $include_backups Include files kept by the WordPress image editor.
     * @param array|null $metadata        Optional metadata.
     * @return int Number of deleted files.
     */
    public static function tbswebpressor_delete_attachment_variants($attachment_id, $include_backups = false, $metadata = null) {
        $deleted = 0;
        foreach (self::tbswebpressor_get_attachment_files($attachment_id, $metadata, $include_backups) as $source) {
            foreach (self::tbswebpressor_get_all_formats() as $format) {
                $variant = self::tbswebpressor_get_variant_path($source, $format);
                if ($variant !== '' && $variant !== $source && file_exists($variant)) {
                    wp_delete_file($variant);
                    $deleted++;
                }
            }
        }
        return $deleted;
    }

    /**
     * Apply a change to the global savings totals.
     *
     * @param int $original_delta  Change in original bytes.
     * @param int $optimized_delta Change in optimized bytes.
     */
    private static function tbswebpressor_adjust_global_stats($original_delta, $optimized_delta) {
        if ($original_delta === 0 && $optimized_delta === 0) {
            return;
        }
        $global_orig = intval(get_option('tbswebpressor_total_original_size', 0));
        $global_opt  = intval(get_option('tbswebpressor_total_optimized_size', 0));

        update_option('tbswebpressor_total_original_size', max(0, $global_orig + $original_delta));
        update_option('tbswebpressor_total_optimized_size', max(0, $global_opt + $optimized_delta));
    }

    /**
     * Remove all conversion meta of an attachment and subtract its savings from the totals.
     *
     * The manual exclusion flag is preserved.
     *
     * @param int $attachment_id Attachment ID.
     */
    public static function tbswebpressor_clear_attachment_state($attachment_id) {
        $prev_original  = intval(get_post_meta($attachment_id, 'tbswebpressor_original_size', true));
        $prev_optimized = intval(get_post_meta($attachment_id, 'tbswebpressor_optimized_size', true));
        if ($prev_optimized > 0) {
            self::tbswebpressor_adjust_global_stats(-$prev_original, -$prev_optimized);
        }

        foreach (self::tbswebpressor_get_all_formats() as $format) {
            delete_post_meta($attachment_id, self::tbswebpressor_get_path_meta_key($format));
            delete_post_meta($attachment_id, self::tbswebpressor_get_status_meta_key($format));
        }
        delete_post_meta($attachment_id, 'tbswebpressor_original_size');
        delete_post_meta($attachment_id, 'tbswebpressor_optimized_size');
        delete_post_meta($attachment_id, self::ERROR_META_KEY);
        delete_post_meta($attachment_id, self::PROCESSING_META_KEY);
    }

    /**
     * Saved folder exclusion patterns.
     *
     * @return string[]
     */
    public static function tbswebpressor_get_exclude_paths() {
        $paths = get_option('tbswebpressor_exclude_paths', array());
        return is_array($paths) ? array_values(array_filter(array_map('strval', $paths))) : array();
    }

    /**
     * Sanitize folder exclusion patterns entered as text (one per line) or as an array.
     *
     * @param string|array $raw Raw input.
     * @return string[]
     */
    public static function tbswebpressor_sanitize_exclude_paths($raw) {
        $lines = is_array($raw) ? $raw : preg_split('/\r\n|\r|\n/', (string) $raw);
        $clean = array();
        foreach ((array) $lines as $line) {
            $line = str_replace('\\', '/', sanitize_text_field((string) $line));
            $line = str_replace('..', '', $line);
            // Attachment paths are stored relative to the uploads folder, without a leading slash.
            $line = ltrim(trim(preg_replace('#/+#', '/', $line)), '/');
            if ($line === '') {
                continue;
            }
            $clean[] = substr($line, 0, 200);
        }
        return array_slice(array_values(array_unique($clean)), 0, 100);
    }

    /**
     * Whether an attachment is excluded from optimization.
     *
     * @param int    $attachment_id Attachment ID.
     * @param string $file          Optional attached file path.
     * @return bool
     */
    public static function tbswebpressor_is_excluded($attachment_id, $file = '') {
        $excluded = (bool) get_post_meta($attachment_id, self::EXCLUDED_META_KEY, true);

        if (!$excluded) {
            $relative = (string) get_post_meta($attachment_id, '_wp_attached_file', true);
            foreach (self::tbswebpressor_get_exclude_paths() as $pattern) {
                if ($relative !== '' && stripos($relative, $pattern) !== false) {
                    $excluded = true;
                    break;
                }
            }
        }

        /**
         * Filter whether an attachment is excluded from WebP/AVIF conversion.
         *
         * @param bool   $excluded      Whether the attachment is excluded.
         * @param int    $attachment_id Attachment ID.
         * @param string $file          Attached file path (may be empty).
         */
        return (bool) apply_filters('tbswebpressor_is_excluded', $excluded, $attachment_id, $file);
    }

    /**
     * Record a status for several formats of an attachment.
     *
     * @param int      $attachment_id Attachment ID.
     * @param string[] $formats       Formats.
     * @param string   $status        Status value.
     */
    private static function tbswebpressor_set_status($attachment_id, $formats, $status) {
        foreach ($formats as $format) {
            delete_post_meta($attachment_id, self::tbswebpressor_get_path_meta_key($format));
            update_post_meta($attachment_id, self::tbswebpressor_get_status_meta_key($format), $status);
        }
    }

    /**
     * Mark an attachment as excluded for all active formats (it leaves the pending queue).
     *
     * @param int $attachment_id Attachment ID.
     */
    public static function tbswebpressor_mark_excluded($attachment_id) {
        self::tbswebpressor_set_status($attachment_id, self::tbswebpressor_get_active_formats(), self::STATUS_EXCLUDED);
        self::tbswebpressor_clear_count_cache();
    }

    /**
     * Delete a status value from every attachment so those items become pending again.
     *
     * @param string $status Status value.
     */
    public static function tbswebpressor_clear_status_everywhere($status) {
        foreach (self::tbswebpressor_get_all_formats() as $format) {
            delete_metadata('post', 0, self::tbswebpressor_get_status_meta_key($format), $status, true);
        }
        if ($status === self::STATUS_FAILED) {
            delete_metadata('post', 0, self::ERROR_META_KEY, '', true);
            delete_metadata('post', 0, self::PROCESSING_META_KEY, '', true);
        }
        self::tbswebpressor_clear_count_cache();
    }

    /**
     * Convert a file without letting an engine error abort the request.
     *
     * @param string $source      Source path.
     * @param string $destination Destination path.
     * @param string $format      Output format.
     * @param int    $quality     Quality.
     * @param string $mode        Compression mode.
     * @return bool
     */
    private static function tbswebpressor_safe_convert($source, $destination, $format, $quality, $mode) {
        try {
            return (bool) self::tbswebpressor_create_format_file($source, $destination, $format, $quality, $mode);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Convert one attachment for all active formats and record a detailed result.
     *
     * Every call leaves each active format either converted or with a recorded status, so an
     * attachment never stays in the pending queue after it has been processed.
     *
     * @since 2.2.0
     * @param int        $attachment_id Attachment ID.
     * @param array|null $metadata      Optional attachment metadata (e.g. from upload filter).
     * @param string     $context       'bulk', 'upload', 'single', 'cli' or 'background'.
     * @return array {
     *     @type int    $id             Attachment ID.
     *     @type string $filename       File name.
     *     @type string $result         converted|skipped|failed|excluded.
     *     @type string $message        Human readable message.
     *     @type array  $formats        Format => converted|larger|failed|excluded.
     *     @type int    $count          Number of files generated.
     *     @type string $webp_path      Converted WebP path of the main file.
     *     @type string $avif_path      Converted AVIF path of the main file.
     *     @type int    $original_size  Source bytes of the kept files.
     *     @type int    $optimized_size Bytes of the kept files.
     * }
     */
    public static function tbswebpressor_process_attachment($attachment_id, $metadata = null, $context = 'bulk') {
        $attachment_id = (int) $attachment_id;
        $result = array(
            'id'             => $attachment_id,
            'filename'       => '',
            'result'         => 'failed',
            'message'        => '',
            'formats'        => array(),
            'count'          => 0,
            'webp_path'      => '',
            'avif_path'      => '',
            'original_size'  => 0,
            'optimized_size' => 0,
        );

        $mime = get_post_mime_type($attachment_id);
        if (!in_array($mime, self::tbswebpressor_get_convertible_mime_types(), true)) {
            $result['result']  = 'skipped';
            $result['message'] = __('Only JPEG and PNG images can be converted.', 'webpressor-webp-image-converter-optimizer');
            return $result;
        }

        $formats = self::tbswebpressor_get_active_formats();
        if (empty($formats)) {
            $result['message'] = __('None of the selected output formats can be created on this server.', 'webpressor-webp-image-converter-optimizer');
            return $result;
        }

        $file = get_attached_file($attachment_id);
        $result['filename'] = $file ? wp_basename($file) : '';

        if (!$file || !file_exists($file)) {
            $result['message'] = __('The original image file could not be found on the server.', 'webpressor-webp-image-converter-optimizer');
            return self::tbswebpressor_finish_failed($attachment_id, $formats, $result);
        }

        if (self::tbswebpressor_is_excluded($attachment_id, $file)) {
            self::tbswebpressor_mark_excluded($attachment_id);
            delete_post_meta($attachment_id, self::ERROR_META_KEY);
            $result['result']  = 'excluded';
            $result['message'] = __('Excluded from optimization.', 'webpressor-webp-image-converter-optimizer');
            foreach ($formats as $format) {
                $result['formats'][$format] = self::STATUS_EXCLUDED;
            }
            return $result;
        }

        if (!wp_is_writable(dirname($file))) {
            $result['message'] = __('The folder that contains this image is not writable.', 'webpressor-webp-image-converter-optimizer');
            return self::tbswebpressor_finish_failed($attachment_id, $formats, $result);
        }

        if ($context !== 'upload' && get_post_meta($attachment_id, self::PROCESSING_META_KEY, true) !== '') {
            delete_post_meta($attachment_id, self::PROCESSING_META_KEY);
            $result['message'] = __('A previous conversion attempt did not finish (the server may have run out of memory or time). Retry it or exclude this image.', 'webpressor-webp-image-converter-optimizer');
            return self::tbswebpressor_finish_failed($attachment_id, $formats, $result);
        }

        update_post_meta($attachment_id, self::PROCESSING_META_KEY, time());
        wp_raise_memory_limit('image');

        if ($metadata === null) {
            $metadata = wp_get_attachment_metadata($attachment_id);
        }

        $thumbs           = self::tbswebpressor_get_size_files($file, $metadata);
        $compression_mode = get_option('tbswebpressor_compression_mode', 'lossy');
        $skip_larger      = (bool) intval(get_option('tbswebpressor_skip_larger', 1));
        $qualities        = array(
            'webp' => intval(get_option('tbswebpressor_webp_quality', 80)),
            'avif' => intval(get_option('tbswebpressor_avif_quality', 65)),
        );

        $total_original_size  = 0;
        $total_optimized_size = 0;
        $errors               = array();

        foreach ($formats as $format) {
            $variant = self::tbswebpressor_get_variant_path($file, $format);
            $label   = strtoupper($format);

            if (self::tbswebpressor_safe_convert($file, $variant, $format, $qualities[$format], $compression_mode)) {
                clearstatcache(true, $variant);
                $source_size  = (int) filesize($file);
                $variant_size = (int) filesize($variant);

                if ($skip_larger && $variant_size >= $source_size) {
                    wp_delete_file($variant);
                    self::tbswebpressor_set_status($attachment_id, array($format), self::STATUS_LARGER);
                    $result['formats'][$format] = self::STATUS_LARGER;
                } else {
                    $normalized_path = str_replace('\\', '/', $variant);
                    update_post_meta($attachment_id, self::tbswebpressor_get_path_meta_key($format), $normalized_path);
                    delete_post_meta($attachment_id, self::tbswebpressor_get_status_meta_key($format));
                    $result[$format . '_path']   = $variant;
                    $result['formats'][$format] = 'converted';
                    $total_original_size        += $source_size;
                    $total_optimized_size       += $variant_size;
                    $result['count']++;
                }
            } else {
                if (file_exists($variant)) {
                    wp_delete_file($variant);
                }
                self::tbswebpressor_set_status($attachment_id, array($format), self::STATUS_FAILED);
                $result['formats'][$format] = self::STATUS_FAILED;
                /* translators: %s: image format name, e.g. WebP or AVIF. */
                $errors[] = sprintf(__('Could not create the %s file. The image may be corrupt or too large for the available server memory.', 'webpressor-webp-image-converter-optimizer'), $label);
            }

            foreach ($thumbs as $thumb_path) {
                if (!file_exists($thumb_path)) {
                    continue;
                }
                $thumb_variant = self::tbswebpressor_get_variant_path($thumb_path, $format);
                if (!self::tbswebpressor_safe_convert($thumb_path, $thumb_variant, $format, $qualities[$format], $compression_mode)) {
                    if (file_exists($thumb_variant)) {
                        wp_delete_file($thumb_variant);
                    }
                    continue;
                }
                clearstatcache(true, $thumb_variant);
                $thumb_size   = (int) filesize($thumb_path);
                $thumb_v_size = (int) filesize($thumb_variant);
                if ($skip_larger && $thumb_v_size >= $thumb_size) {
                    wp_delete_file($thumb_variant);
                    continue;
                }
                $total_original_size  += $thumb_size;
                $total_optimized_size += $thumb_v_size;
                $result['count']++;
            }
        }

        if (!empty($errors)) {
            update_post_meta($attachment_id, self::ERROR_META_KEY, implode(' ', $errors));
        } else {
            delete_post_meta($attachment_id, self::ERROR_META_KEY);
        }
        delete_post_meta($attachment_id, self::PROCESSING_META_KEY);

        $prev_original_size  = intval(get_post_meta($attachment_id, 'tbswebpressor_original_size', true));
        $prev_optimized_size = intval(get_post_meta($attachment_id, 'tbswebpressor_optimized_size', true));
        if ($prev_optimized_size <= 0) {
            $prev_original_size = 0;
        }

        if ($total_optimized_size > 0) {
            update_post_meta($attachment_id, 'tbswebpressor_original_size', $total_original_size);
            update_post_meta($attachment_id, 'tbswebpressor_optimized_size', $total_optimized_size);
        } else {
            delete_post_meta($attachment_id, 'tbswebpressor_original_size');
            delete_post_meta($attachment_id, 'tbswebpressor_optimized_size');
            $total_original_size = 0;
        }

        self::tbswebpressor_adjust_global_stats(
            $total_original_size - $prev_original_size,
            $total_optimized_size - $prev_optimized_size
        );

        $result['original_size']  = $total_original_size;
        $result['optimized_size'] = $total_optimized_size;

        if (in_array('converted', $result['formats'], true)) {
            $result['result']  = 'converted';
            $result['message'] = implode(' ', $errors);
        } elseif (in_array(self::STATUS_FAILED, $result['formats'], true)) {
            $result['result']  = 'failed';
            $result['message'] = implode(' ', $errors);
        } else {
            $result['result']  = 'skipped';
            $result['message'] = __('Kept the original because the converted file was not smaller.', 'webpressor-webp-image-converter-optimizer');
        }

        self::tbswebpressor_clear_count_cache();

        /**
         * Fires after an attachment has been processed.
         *
         * @param int    $attachment_id Attachment ID.
         * @param array  $result        Processing result.
         * @param string $context       Processing context.
         */
        do_action('tbswebpressor_attachment_processed', $attachment_id, $result, $context);

        return $result;
    }

    /**
     * Record a failure for all formats of an attachment.
     *
     * @param int      $attachment_id Attachment ID.
     * @param string[] $formats       Formats.
     * @param array    $result        Result being built.
     * @return array
     */
    private static function tbswebpressor_finish_failed($attachment_id, $formats, $result) {
        self::tbswebpressor_set_status($attachment_id, $formats, self::STATUS_FAILED);
        update_post_meta($attachment_id, self::ERROR_META_KEY, $result['message']);
        foreach ($formats as $format) {
            $result['formats'][$format] = self::STATUS_FAILED;
        }
        $result['result'] = 'failed';
        self::tbswebpressor_clear_count_cache();
        return $result;
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
        $result = self::tbswebpressor_process_attachment($attachment_id, $metadata, 'single');
        if ($result['result'] !== 'converted') {
            return false;
        }

        return array(
            'count'          => $result['count'],
            'webp_path'      => $result['webp_path'],
            'avif_path'      => $result['avif_path'],
            'original_size'  => $result['original_size'],
            'optimized_size' => $result['optimized_size'],
            'filename'       => $result['filename'],
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
     * Number of attachments still pending conversion (uncached).
     *
     * @return int
     */
    public static function tbswebpressor_count_pending() {
        $pending_meta = self::tbswebpressor_get_pending_meta_query();
        if ($pending_meta === null) {
            return 0;
        }

        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        $query = new WP_Query(array_merge(self::tbswebpressor_get_convertible_attachment_query_args(), array(
            'posts_per_page' => 1,
            'meta_query'     => $pending_meta,
        )));

        return (int) $query->found_posts;
    }

    /**
     * Convert the next batch of pending attachments (page argument retained for API compatibility).
     *
     * @param int    $page       Unused; each batch always processes the next pending items.
     * @param int    $batch_size Number of attachments to process.
     * @param string $context    Processing context.
     * @return array
     */
    public static function tbswebpressor_convert_attachements_batch($page, $batch_size = 5, $context = 'bulk') {
        unset($page);

        $pending_meta = self::tbswebpressor_get_pending_meta_query();
        if ($pending_meta === null) {
            self::tbswebpressor_release_bulk_lock();
            return array(
                'hasMorePages' => false,
                'processed'    => array(),
            );
        }

        /**
         * Filter the number of attachments converted per batch.
         *
         * @param int    $batch_size Batch size.
         * @param string $context    Processing context.
         */
        $batch_size = max(1, (int) apply_filters('tbswebpressor_batch_size', (int) $batch_size, $context));

        $args = array_merge(
            self::tbswebpressor_get_convertible_attachment_query_args(),
            array(
                'posts_per_page' => $batch_size,
                'paged'          => 1,
                'no_found_rows'  => true,
                'orderby'        => 'ID',
                'order'          => 'DESC',
                'meta_query'     => $pending_meta,
            )
        );

        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        $attachments = new WP_Query($args);
        $processed = array();

        foreach ($attachments->posts as $attachment_id) {
            $processed[] = self::tbswebpressor_process_attachment((int) $attachment_id, null, $context);
        }

        $has_more = !empty($processed) && self::tbswebpressor_count_pending() > 0;

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
     * @return array{total:int,pending:int,failed:int,skipped:int,excluded:int}
     */
    public static function tbswebpressor_get_media_counts($force_refresh = false) {
        $keys = array('total', 'pending', 'failed', 'skipped', 'excluded');

        if (!$force_refresh) {
            $cached = get_transient(self::COUNT_CACHE_KEY);
            if (is_array($cached) && count(array_intersect_key($cached, array_flip($keys))) === count($keys)) {
                return array_map('intval', array_intersect_key($cached, array_flip($keys)));
            }
        }

        $base_args = self::tbswebpressor_get_convertible_attachment_query_args();

        $total_query = new WP_Query(array_merge($base_args, array(
            'posts_per_page' => 1,
        )));

        $counts = array(
            'total'    => (int) $total_query->found_posts,
            'pending'  => self::tbswebpressor_count_pending(),
            'failed'   => 0,
            'skipped'  => 0,
            'excluded' => 0,
        );

        $status_map = array(
            'failed'   => self::STATUS_FAILED,
            'skipped'  => self::STATUS_LARGER,
            'excluded' => self::STATUS_EXCLUDED,
        );

        foreach ($status_map as $count_key => $status) {
            $status_meta = self::tbswebpressor_get_status_meta_query($status);
            if ($status_meta === null) {
                continue;
            }
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- status counts for admin dashboard.
            $status_query = new WP_Query(array_merge($base_args, array(
                'posts_per_page' => 1,
                'meta_query'     => $status_meta,
            )));
            $counts[$count_key] = (int) $status_query->found_posts;
        }

        set_transient(self::COUNT_CACHE_KEY, $counts, 5 * MINUTE_IN_SECONDS);

        return $counts;
    }

    /**
     * Attachments whose conversion failed, with the recorded reason.
     *
     * @param int $limit Maximum number of items.
     * @return array[] List of { id, filename, message }.
     */
    public static function tbswebpressor_get_failed_items($limit = 20) {
        $status_meta = self::tbswebpressor_get_status_meta_query(self::STATUS_FAILED);
        if ($status_meta === null) {
            return array();
        }

        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        $query = new WP_Query(array_merge(self::tbswebpressor_get_convertible_attachment_query_args(), array(
            'posts_per_page' => max(1, (int) $limit),
            'no_found_rows'  => true,
            'meta_query'     => $status_meta,
        )));

        $items = array();
        foreach ($query->posts as $attachment_id) {
            $file    = get_attached_file($attachment_id);
            $message = (string) get_post_meta($attachment_id, self::ERROR_META_KEY, true);
            $items[] = array(
                'id'       => (int) $attachment_id,
                'filename' => $file ? wp_basename($file) : get_the_title($attachment_id),
                'message'  => $message !== '' ? $message : __('Conversion failed.', 'webpressor-webp-image-converter-optimizer'),
            );
        }

        return $items;
    }

    /**
     * Delete every generated file and all conversion data (manual exclusions are kept).
     */
    public static function tbswebpressor_reset_all() {
        self::tbswebpressor_release_bulk_lock();

        $args = array_merge(
            self::tbswebpressor_get_convertible_attachment_query_args(),
            array(
                'posts_per_page' => -1,
                'no_found_rows'  => true,
            )
        );

        $attachments = new WP_Query($args);

        foreach ($attachments->posts as $attachment_id) {
            $attachment_id = (int) $attachment_id;
            self::tbswebpressor_delete_attachment_variants($attachment_id, true);

            foreach (self::tbswebpressor_get_all_formats() as $format) {
                delete_post_meta($attachment_id, self::tbswebpressor_get_path_meta_key($format));
                delete_post_meta($attachment_id, self::tbswebpressor_get_status_meta_key($format));
            }
            delete_post_meta($attachment_id, 'tbswebpressor_original_size');
            delete_post_meta($attachment_id, 'tbswebpressor_optimized_size');
            delete_post_meta($attachment_id, self::ERROR_META_KEY);
            delete_post_meta($attachment_id, self::PROCESSING_META_KEY);
        }

        update_option('tbswebpressor_total_original_size', 0);
        update_option('tbswebpressor_total_optimized_size', 0);
        self::tbswebpressor_clear_count_cache();
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
