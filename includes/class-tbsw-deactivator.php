<?php

class TBS_WebPressor_Deactivator {

    /**
     * Remove rewrite rules on deactivation while preserving plugin settings.
     *
     * @since    1.0.0
     */
    public static function tbswebpressor_deactivate() {
        update_option('tbswebpressor_delivery_method', 'html');
        if (class_exists('TBS_WebPressor_Converter')) {
            TBS_WebPressor_Converter::tbswebpressor_update_htaccess();
            TBS_WebPressor_Converter::tbswebpressor_release_bulk_lock();
            TBS_WebPressor_Converter::tbswebpressor_clear_count_cache();
        } else {
            $upload_dir = wp_upload_dir();
            $htaccess_path = trailingslashit($upload_dir['basedir']) . '.htaccess';
            if (file_exists($htaccess_path)) {
                $content = file_get_contents($htaccess_path);
                $content = preg_replace('/# BEGIN WebPressor.*?# END WebPressor\s*/s', '', $content);
                file_put_contents($htaccess_path, trim($content));
            }
        }
    }
}
