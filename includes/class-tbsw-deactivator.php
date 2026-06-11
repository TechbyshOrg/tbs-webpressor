<?php

class TBS_WebPressor_Deactivator {

    /**
     * Clean up any plugin data and remove .htaccess rules on deactivation.
     *
     * @since    1.0.0
     */
    public static function tbswebpressor_deactivate() {
        // Remove htaccess rules
        update_option('tbswebpressor_delivery_method', 'html');
        if (class_exists('TBS_WebPressor_Converter')) {
            TBS_WebPressor_Converter::tbswebpressor_update_htaccess();
        } else {
            // Fallback: manually clean up .htaccess in uploads directory if converter class is not loaded
            $upload_dir = wp_upload_dir();
            $htaccess_path = trailingslashit($upload_dir['basedir']) . '.htaccess';
            if (file_exists($htaccess_path)) {
                $content = file_get_contents($htaccess_path);
                $content = preg_replace('/# BEGIN WebPressor.*?# END WebPressor\s*/s', '', $content);
                file_put_contents($htaccess_path, trim($content));
            }
        }
        
        // Clean up settings
        delete_option('tbswebpressor_target_formats');
        delete_option('tbswebpressor_webp_quality');
        delete_option('tbswebpressor_avif_quality');
        delete_option('tbswebpressor_delivery_method');
        delete_option('tbswebpressor_compression_mode');
        delete_option('tbswebpressor_convert_on_upload');
        delete_option('tbswebpressor_total_original_size');
        delete_option('tbswebpressor_total_optimized_size');
    }
}