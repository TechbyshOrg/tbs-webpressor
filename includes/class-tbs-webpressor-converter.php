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
     * Initialize the converter.
     *
     * @since    1.0.0
     */
    public function __construct() {
        // Constructor code
    }
    
    /**
     * Convert an attachment to WebP and/or AVIF format
     *
     * @since    1.0.0
     * @param    int    $attachment_id    The attachment ID to convert
     * @return   array|bool               Conversion stats or false on failure
     */
    public static function tbswebpressor_create_webp($attachment_id) {
        $count = 0;
        $file = get_attached_file($attachment_id);
    
        if (!$file || !file_exists($file)) {
            return false;
        }
    
        $mime = get_post_mime_type($attachment_id);
        if (strpos($mime, 'image/') !== 0 || $mime === 'image/webp' || $mime === 'image/avif') {
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
        if (in_array('webp', $formats) && function_exists('imagewebp')) {
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
        if (in_array('avif', $formats) && function_exists('imageavif')) {
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
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!empty($metadata['sizes'])) {
            $upload_dir = wp_upload_dir();
            $base_dir = trailingslashit($upload_dir['basedir']);
            $subdir = trailingslashit(dirname($metadata['file']));
    
            foreach ($metadata['sizes'] as $size_name => $size_info) {
                $thumb_path = $base_dir . $subdir . $size_info['file'];
    
                if (!file_exists($thumb_path)) continue;
    
                $thumb_ext = pathinfo($thumb_path, PATHINFO_EXTENSION);
                
                if (in_array('webp', $formats) && function_exists('imagewebp')) {
                    $thumb_webp_path = preg_replace('/\.' . preg_quote($thumb_ext, '/') . '$/', '.webp', $thumb_path);
                    if (self::tbswebpressor_create_format_file($thumb_path, $thumb_webp_path, 'webp', $webp_quality, $compression_mode)) {
                        $total_original_size += filesize($thumb_path);
                        $total_optimized_size += filesize($thumb_webp_path);
                        $count++;
                    }
                }
                
                if (in_array('avif', $formats) && function_exists('imageavif')) {
                    $thumb_avif_path = preg_replace('/\.' . preg_quote($thumb_ext, '/') . '$/', '.avif', $thumb_path);
                    if (self::tbswebpressor_create_format_file($thumb_path, $thumb_avif_path, 'avif', $avif_quality, $compression_mode)) {
                        $total_original_size += filesize($thumb_path);
                        $total_optimized_size += filesize($thumb_avif_path);
                        $count++;
                    }
                }
            }
        }
        
        // Save stats
        update_post_meta($attachment_id, 'tbswebpressor_original_size', $total_original_size);
        if ($total_optimized_size > 0) {
            update_post_meta($attachment_id, 'tbswebpressor_optimized_size', $total_optimized_size);
            
            $global_orig = intval(get_option('tbswebpressor_total_original_size', 0));
            $global_opt  = intval(get_option('tbswebpressor_total_optimized_size', 0));
            
            update_option('tbswebpressor_total_original_size', $global_orig + $total_original_size);
            update_option('tbswebpressor_total_optimized_size', $global_opt + $total_optimized_size);
        }
    
        return array(
            'count' => $count, 
            'webp_path' => $webp_path, 
            'avif_path' => $avif_path,
            'original_size' => $total_original_size,
            'optimized_size' => $total_optimized_size,
            'filename' => basename($file)
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
     * Convert attachments in batches (optimized to return stats)
     */
    public static function tbswebpressor_convert_attachements_batch($page) {
        $hasMorePages = true;
        // Batch size of 5 for faster user interface response
        $args = array(
            'post_type'      => 'attachment',
            'posts_per_page' => 5,
            'post_status'    => array('publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash'),
            'paged'          => $page,
        );
        
        $attachments = new WP_Query($args);
        $processed = array();
        
        if ($attachments->have_posts()) {
            while ($attachments->have_posts()) {
                $attachments->the_post();
                $attachment_id = get_the_ID();
                $res = self::tbswebpressor_create_webp($attachment_id);
                if ($res) {
                    $processed[] = $res;
                }
            }
            wp_reset_postdata();
        } else {
            $hasMorePages = false;
        }
        
        return array('hasMorePages' => $hasMorePages, 'processed' => $processed);
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
            
            $rules = "\n# BEGIN WebPressor\n";
            $rules .= "<IfModule mod_rewrite.c>\n";
            $rules .= "  RewriteEngine On\n";
            $rules .= "  RewriteBase " . esc_url(parse_url($upload_dir['baseurl'], PHP_URL_PATH)) . "/\n\n";
            
            if (in_array('avif', $formats)) {
                $rules .= "  # Serve AVIF\n";
                $rules .= "  RewriteCond %{HTTP_ACCEPT} image/avif\n";
                $rules .= "  RewriteCond %{REQUEST_FILENAME} ^(.*)\.(jpe?g|png)$ [NC]\n";
                $rules .= "  RewriteCond %1.avif -f\n";
                $rules .= "  RewriteRule ^(.*)\.(jpe?g|png)$ \$1.avif [T=image/avif,L]\n\n";
            }
            
            if (in_array('webp', $formats)) {
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
            
            if (in_array('webp', $formats)) {
                $rules .= "AddType image/webp .webp\n";
            }
            if (in_array('avif', $formats)) {
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