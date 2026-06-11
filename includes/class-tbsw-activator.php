<?php

class TBS_WebPressor_Activator {

    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function tbswebpressor_activate() {
        // Set default options
        add_option('tbswebpressor_target_formats', array('webp'));
        add_option('tbswebpressor_webp_quality', 80);
        add_option('tbswebpressor_avif_quality', 65);
        add_option('tbswebpressor_delivery_method', 'html');
        add_option('tbswebpressor_compression_mode', 'lossy');
        add_option('tbswebpressor_convert_on_upload', 1);

        // Track stats
        add_option('tbswebpressor_total_original_size', 0);
        add_option('tbswebpressor_total_optimized_size', 0);
    }
}