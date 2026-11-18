<?php
/**
 * AJAX functionality of the plugin.
 *
 * @since      1.0.0
 * @package    TBS_WebPressor
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class TBS_WebPressor_Ajax {

    /**
     * The converter instance.
     *
     * @since    1.0.0
     * @access   protected
     * @var      TBS_WebPressor_Converter    $converter    Converter instance.
     */
    protected $converter;

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
     * Register all AJAX handlers
     *
     * @since    1.0.0
     */
    public function tbswebpressor_ajax_setup_hooks() {
        add_action('wp_ajax_tbswebpressor_start_conversion', array($this, 'tbswebpressor_start_conversion'));
        add_action('wp_ajax_tbswebpressor_get_media_count', array($this, 'tbswebpressor_get_media_count'));
        add_action('wp_ajax_tbswebpressor_get_pending_media_count', array($this, 'tbswebpressor_get_pending_media_count'));
        add_action('wp_ajax_tbswebpressor_reset_conversion', array($this, 'tbswebpressor_reset_conversion'));
        add_action('wp_ajax_tbswebpressor_save_settings', array($this, 'tbswebpressor_save_settings'));
        add_action('wp_ajax_tbswebpressor_get_stats', array($this, 'tbswebpressor_get_stats'));
        add_action('wp_ajax_tbswebpressor_retry_failed', array($this, 'tbswebpressor_retry_failed'));
        add_action('wp_ajax_tbswebpressor_get_failed_items', array($this, 'tbswebpressor_get_failed_items'));
        add_action('wp_ajax_tbswebpressor_media_action', array($this, 'tbswebpressor_media_action'));
        add_action('wp_ajax_tbswebpressor_background', array($this, 'tbswebpressor_background'));
        add_action('wp_ajax_tbswebpressor_test_delivery', array($this, 'tbswebpressor_test_delivery'));
    }

    /**
     * Verify nonce and admin capability for AJAX requests.
     *
     * @since    1.0.0
     */
    private function tbswebpressor_verify_nonce() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'webpressor-webp-image-converter-optimizer')));
            exit;
        }

        if (!isset($_REQUEST['nonce']) || !check_ajax_referer('tbswebpressor-nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'webpressor-webp-image-converter-optimizer')));
            exit;
        }
    }

    /**
     * Acquire or refresh the bulk optimization lock for the current user.
     *
     * @param int $page Batch page from client (1 starts a new run).
     */
    private function tbswebpressor_acquire_bulk_lock($page) {
        $user_id = get_current_user_id();
        $lock = get_transient(TBS_WebPressor_Converter::BULK_LOCK_KEY);

        if ($lock === TBS_WebPressor_Background::LOCK_OWNER) {
            wp_send_json_error(array('message' => __('Background optimization is running. Stop it first to optimize in this window.', 'webpressor-webp-image-converter-optimizer')));
            exit;
        }

        if ((int) $page <= 1) {
            if ($lock && (int) $lock !== $user_id) {
                wp_send_json_error(array('message' => __('Bulk optimization is already running in another session.', 'webpressor-webp-image-converter-optimizer')));
                exit;
            }
            set_transient(TBS_WebPressor_Converter::BULK_LOCK_KEY, $user_id, 15 * MINUTE_IN_SECONDS);
            return;
        }

        if (!$lock || (int) $lock !== $user_id) {
            wp_send_json_error(array('message' => __('Bulk optimization session expired. Please start again.', 'webpressor-webp-image-converter-optimizer')));
            exit;
        }

        set_transient(TBS_WebPressor_Converter::BULK_LOCK_KEY, $user_id, 15 * MINUTE_IN_SECONDS);
    }

    /**
     * Start the conversion process
     *
     * @since    1.0.0
     */
    public function tbswebpressor_start_conversion() {
        $this->tbswebpressor_verify_nonce();

        $page = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;
        $this->tbswebpressor_acquire_bulk_lock($page);

        $result = TBS_WebPressor_Converter::tbswebpressor_convert_attachements_batch($page);

        $hasMorePages = $result['hasMorePages'];
        $processed = isset($result['processed']) ? $result['processed'] : array();

        TBS_WebPressor_Converter::tbswebpressor_clear_count_cache();

        wp_send_json_success(array(
            'message'      => __('Conversion started!', 'webpressor-webp-image-converter-optimizer'),
            'hasMorePages' => $hasMorePages,
            'processed'    => $processed,
        ));
    }

    /**
     * Get total media count
     *
     * The response also carries pending, failed, skipped and excluded counts.
     *
     * @since    1.0.0
     */
    public function tbswebpressor_get_media_count() {
        $this->tbswebpressor_verify_nonce();

        $force = !empty($_REQUEST['refresh']);
        $counts = TBS_WebPressor_Converter::tbswebpressor_get_media_counts($force);

        wp_send_json_success(array(
            'count'    => $counts['total'],
            'pending'  => $counts['pending'],
            'failed'   => $counts['failed'],
            'skipped'  => $counts['skipped'],
            'excluded' => $counts['excluded'],
        ));
    }

    /**
     * Get pending media count (not yet converted)
     *
     * @since    1.0.0
     */
    public function tbswebpressor_get_pending_media_count() {
        $this->tbswebpressor_verify_nonce();

        $force = !empty($_REQUEST['refresh']);
        $counts = TBS_WebPressor_Converter::tbswebpressor_get_media_counts($force);

        wp_send_json_success(array('count' => $counts['pending']));
    }

    /**
     * Reset all conversions
     *
     * @since    1.0.0
     */
    public function tbswebpressor_reset_conversion() {
        $this->tbswebpressor_verify_nonce();

        TBS_WebPressor_Background::tbswebpressor_stop();
        TBS_WebPressor_Converter::tbswebpressor_reset_all();

        wp_send_json_success(array('message' => __('Conversion reset successfully!', 'webpressor-webp-image-converter-optimizer')));
    }

    /**
     * Save settings via AJAX
     */
    public function tbswebpressor_save_settings() {
        $this->tbswebpressor_verify_nonce();

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified in tbswebpressor_verify_nonce().
        $target_formats = isset($_REQUEST['target_formats']) ? TBS_WebPressor_Converter::tbswebpressor_sanitize_target_formats(wp_unslash((array) $_REQUEST['target_formats'])) : array('webp');
        $webp_quality = isset($_REQUEST['webp_quality']) ? intval($_REQUEST['webp_quality']) : 80;
        $avif_quality = isset($_REQUEST['avif_quality']) ? intval($_REQUEST['avif_quality']) : 65;
        $delivery_method = isset($_REQUEST['delivery_method']) ? sanitize_key($_REQUEST['delivery_method']) : 'html';
        $compression_mode = isset($_REQUEST['compression_mode']) ? sanitize_key($_REQUEST['compression_mode']) : 'lossy';
        $convert_on_upload = isset($_REQUEST['convert_on_upload']) ? intval($_REQUEST['convert_on_upload']) : 0;

        $old_skip_larger   = intval(get_option('tbswebpressor_skip_larger', 1));
        $old_exclude_paths = TBS_WebPressor_Converter::tbswebpressor_get_exclude_paths();

        $skip_larger = isset($_REQUEST['skip_larger']) ? (intval($_REQUEST['skip_larger']) ? 1 : 0) : $old_skip_larger;
        $html_scope = isset($_REQUEST['html_scope']) ? sanitize_key($_REQUEST['html_scope']) : get_option('tbswebpressor_html_scope', 'content');
        $exclude_paths = isset($_REQUEST['exclude_paths'])
            ? TBS_WebPressor_Converter::tbswebpressor_sanitize_exclude_paths(sanitize_textarea_field(wp_unslash($_REQUEST['exclude_paths'])))
            : $old_exclude_paths;
        $max_dimension = isset($_REQUEST['max_image_dimension']) ? absint($_REQUEST['max_image_dimension']) : intval(get_option('tbswebpressor_max_image_dimension', 0));
        // phpcs:enable

        if (!in_array($delivery_method, array('html', 'picture', 'rewrite'), true)) {
            $delivery_method = 'html';
        }
        if (!in_array($compression_mode, array('lossy', 'lossless'), true)) {
            $compression_mode = 'lossy';
        }
        if (!in_array($html_scope, array('content', 'full'), true)) {
            $html_scope = 'content';
        }
        if ($max_dimension > 0) {
            $max_dimension = max(640, min(10000, $max_dimension));
        }

        update_option('tbswebpressor_target_formats', $target_formats);
        update_option('tbswebpressor_webp_quality', max(0, min(100, $webp_quality)));
        update_option('tbswebpressor_avif_quality', max(0, min(100, $avif_quality)));
        update_option('tbswebpressor_delivery_method', $delivery_method);
        update_option('tbswebpressor_compression_mode', $compression_mode);
        update_option('tbswebpressor_convert_on_upload', $convert_on_upload ? 1 : 0);
        update_option('tbswebpressor_skip_larger', $skip_larger);
        update_option('tbswebpressor_html_scope', $html_scope);
        update_option('tbswebpressor_exclude_paths', $exclude_paths);
        update_option('tbswebpressor_max_image_dimension', $max_dimension);

        // Images skipped as "larger" become pending again when that safeguard is turned off.
        if ($old_skip_larger && !$skip_larger) {
            TBS_WebPressor_Converter::tbswebpressor_clear_status_everywhere(TBS_WebPressor_Converter::STATUS_LARGER);
        }

        // Exclusion rules changed: re-evaluate previously excluded images on the next run.
        if ($exclude_paths !== $old_exclude_paths) {
            TBS_WebPressor_Converter::tbswebpressor_clear_status_everywhere(TBS_WebPressor_Converter::STATUS_EXCLUDED);
        }

        TBS_WebPressor_Converter::tbswebpressor_clear_count_cache();

        if (class_exists('TBS_WebPressor_Converter')) {
            TBS_WebPressor_Converter::tbswebpressor_update_htaccess();
        }

        wp_send_json_success(array(
            'message'  => __('Settings saved successfully!', 'webpressor-webp-image-converter-optimizer'),
            'settings' => array(
                'target_formats'      => $target_formats,
                'exclude_paths'       => implode("\n", $exclude_paths),
                'max_image_dimension' => $max_dimension,
            ),
        ));
    }

    /**
     * Get updated stats via AJAX
     */
    public function tbswebpressor_get_stats() {
        $this->tbswebpressor_verify_nonce();

        $total_orig = intval(get_option('tbswebpressor_total_original_size', 0));
        $total_opt  = intval(get_option('tbswebpressor_total_optimized_size', 0));

        wp_send_json_success(array(
            'total_original'  => $total_orig,
            'total_optimized' => $total_opt,
        ));
    }

    /**
     * Put failed images back into the pending queue.
     *
     * @since 2.2.0
     */
    public function tbswebpressor_retry_failed() {
        $this->tbswebpressor_verify_nonce();

        TBS_WebPressor_Converter::tbswebpressor_clear_status_everywhere(TBS_WebPressor_Converter::STATUS_FAILED);

        wp_send_json_success(array('message' => __('Failed images were queued for another attempt.', 'webpressor-webp-image-converter-optimizer')));
    }

    /**
     * List failed images with their error messages.
     *
     * @since 2.2.0
     */
    public function tbswebpressor_get_failed_items() {
        $this->tbswebpressor_verify_nonce();

        wp_send_json_success(array('items' => TBS_WebPressor_Converter::tbswebpressor_get_failed_items(20)));
    }

    /**
     * Run a per-image action from the Media Library.
     *
     * @since 2.2.0
     */
    public function tbswebpressor_media_action() {
        $this->tbswebpressor_verify_nonce();

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified in tbswebpressor_verify_nonce().
        $attachment_id = isset($_REQUEST['attachment_id']) ? absint($_REQUEST['attachment_id']) : 0;
        $operation     = isset($_REQUEST['operation']) ? sanitize_key($_REQUEST['operation']) : '';
        // phpcs:enable

        if (!$attachment_id || get_post_type($attachment_id) !== 'attachment') {
            wp_send_json_error(array('message' => __('Image not found.', 'webpressor-webp-image-converter-optimizer')));
        }

        $result = TBS_WebPressor_Media::tbswebpressor_run_action($attachment_id, $operation);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => $result,
            'html'    => TBS_WebPressor_Media::tbswebpressor_render_status($attachment_id),
        ));
    }

    /**
     * Start, stop or read the background optimization.
     *
     * @since 2.2.0
     */
    public function tbswebpressor_background() {
        $this->tbswebpressor_verify_nonce();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified in tbswebpressor_verify_nonce().
        $operation = isset($_REQUEST['operation']) ? sanitize_key($_REQUEST['operation']) : 'status';

        if ($operation === 'start') {
            $started = TBS_WebPressor_Background::tbswebpressor_start();
            if (is_wp_error($started)) {
                wp_send_json_error(array('message' => $started->get_error_message()));
            }
        } elseif ($operation === 'stop') {
            TBS_WebPressor_Background::tbswebpressor_stop();
        }

        wp_send_json_success(array('state' => TBS_WebPressor_Background::tbswebpressor_get_state()));
    }

    /**
     * Run the delivery self-test.
     *
     * @since 2.2.0
     */
    public function tbswebpressor_test_delivery() {
        $this->tbswebpressor_verify_nonce();

        wp_send_json_success(TBS_WebPressor_Diagnostics::tbswebpressor_run_delivery_test());
    }
}
