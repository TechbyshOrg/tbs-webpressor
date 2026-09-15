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

delete_transient('tbswebpressor_media_counts');
delete_transient('tbswebpressor_bulk_lock');
