<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package TBS_WebPressor
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$upload_dir = wp_upload_dir();
$htaccess_path = trailingslashit($upload_dir['basedir']) . '.htaccess';
if (file_exists($htaccess_path)) {
    $content = file_get_contents($htaccess_path);
    $content = preg_replace('/# BEGIN WebPressor.*?# END WebPressor\s*/s', '', $content);
    file_put_contents($htaccess_path, trim($content));
}

delete_option('tbswebpressor_target_formats');
delete_option('tbswebpressor_webp_quality');
delete_option('tbswebpressor_avif_quality');
delete_option('tbswebpressor_delivery_method');
delete_option('tbswebpressor_compression_mode');
delete_option('tbswebpressor_convert_on_upload');
delete_option('tbswebpressor_total_original_size');
delete_option('tbswebpressor_total_optimized_size');
delete_option('tbswebpressor_skip_larger');
delete_option('tbswebpressor_html_scope');
delete_option('tbswebpressor_exclude_paths');
delete_option('tbswebpressor_max_image_dimension');
delete_option('tbswebpressor_background_state');

delete_transient('tbswebpressor_media_counts');
delete_transient('tbswebpressor_bulk_lock');
delete_transient('tbswebpressor_background_slice');

wp_clear_scheduled_hook('tbswebpressor_background_process');

// Per-image status data added in 2.2.0 (converted path and size meta are kept, as before).
foreach (array('tbswebpressor_webp_status', 'tbswebpressor_avif_status', 'tbswebpressor_error', 'tbswebpressor_excluded', 'tbswebpressor_processing') as $tbswebpressor_meta_key) {
    delete_metadata('post', 0, $tbswebpressor_meta_key, '', true);
}
