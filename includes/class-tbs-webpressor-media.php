<?php
/**
 * Media Library integration: per-image status and actions.
 *
 * @since      2.2.0
 * @package    TBS_WebPressor
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class TBS_WebPressor_Media {

    /**
     * Register hooks.
     */
    public function tbswebpressor_media_setup_hooks() {
        add_filter('manage_media_columns', array($this, 'tbswebpressor_add_media_column'));
        add_action('manage_media_custom_column', array($this, 'tbswebpressor_render_media_column'), 10, 2);
        add_filter('attachment_fields_to_edit', array($this, 'tbswebpressor_attachment_fields'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'tbswebpressor_enqueue_media_assets'));
        add_action('wp_enqueue_media', array($this, 'tbswebpressor_enqueue_media_modal_assets'));
    }

    /**
     * Whether the current user may see and use the per-image controls.
     *
     * @return bool
     */
    private static function tbswebpressor_user_can_manage() {
        return current_user_can('manage_options');
    }

    /**
     * Add the WebPressor column to the Media Library list view.
     *
     * @param array $columns Columns.
     * @return array
     */
    public function tbswebpressor_add_media_column($columns) {
        if (self::tbswebpressor_user_can_manage()) {
            $columns['tbswebpressor'] = __('WebPressor', 'webpressor-webp-image-converter-optimizer');
        }
        return $columns;
    }

    /**
     * Render the WebPressor column.
     *
     * @param string $column_name Column name.
     * @param int    $post_id     Attachment ID.
     */
    public function tbswebpressor_render_media_column($column_name, $post_id) {
        if ($column_name !== 'tbswebpressor' || !self::tbswebpressor_user_can_manage()) {
            return;
        }
        echo self::tbswebpressor_render_status($post_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped while rendering.
    }

    /**
     * Show the status panel in the attachment details (grid view modal and edit screen).
     *
     * @param array   $fields Fields.
     * @param WP_Post $post   Attachment.
     * @return array
     */
    public function tbswebpressor_attachment_fields($fields, $post) {
        if (!self::tbswebpressor_user_can_manage() || !self::tbswebpressor_is_convertible($post->ID)) {
            return $fields;
        }

        $fields['tbswebpressor'] = array(
            'label' => __('WebPressor', 'webpressor-webp-image-converter-optimizer'),
            'input' => 'html',
            'html'  => self::tbswebpressor_render_status($post->ID),
        );

        return $fields;
    }

    /**
     * Load the media script on the Media Library and attachment edit screens.
     *
     * @param string $hook_suffix Admin page hook suffix.
     */
    public function tbswebpressor_enqueue_media_assets($hook_suffix) {
        if (!self::tbswebpressor_user_can_manage()) {
            return;
        }
        if ($hook_suffix === 'upload.php' || ($hook_suffix === 'post.php' && get_post_type() === 'attachment')) {
            self::tbswebpressor_enqueue_script();
        }
    }

    /**
     * Load the media script wherever the media modal is used.
     */
    public function tbswebpressor_enqueue_media_modal_assets() {
        if (is_admin() && self::tbswebpressor_user_can_manage()) {
            self::tbswebpressor_enqueue_script();
        }
    }

    /**
     * Enqueue the media script and styles once.
     */
    private static function tbswebpressor_enqueue_script() {
        if (wp_script_is('tbswebpressor-media', 'enqueued')) {
            return;
        }

        wp_enqueue_style(
            'tbswebpressor-media',
            TBSWEBPRESSOR_PLUGIN_URL . 'assets/css/media.css',
            array(),
            TBSWEBPRESSOR_VERSION
        );

        wp_enqueue_script(
            'tbswebpressor-media',
            TBSWEBPRESSOR_PLUGIN_URL . 'assets/js/media.js',
            array('jquery', 'wp-a11y'),
            TBSWEBPRESSOR_VERSION,
            true
        );

        wp_localize_script('tbswebpressor-media', 'tbswMedia', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('tbswebpressor-nonce'),
            'i18n'     => array(
                'working'       => __('Working…', 'webpressor-webp-image-converter-optimizer'),
                'error'         => __('Something went wrong. Please try again.', 'webpressor-webp-image-converter-optimizer'),
                'confirmRemove' => __('Delete the WebP/AVIF versions of this image? The original image is not affected.', 'webpressor-webp-image-converter-optimizer'),
                'confirmExclude' => __('Exclude this image from optimization? Its WebP/AVIF versions will be deleted, and it will not be converted again until you include it. The original image is not affected.', 'webpressor-webp-image-converter-optimizer'),
            ),
        ));
    }

    /**
     * Whether an attachment is a JPEG/PNG image.
     *
     * @param int $attachment_id Attachment ID.
     * @return bool
     */
    private static function tbswebpressor_is_convertible($attachment_id) {
        return in_array(get_post_mime_type($attachment_id), TBS_WebPressor_Converter::tbswebpressor_get_convertible_mime_types(), true);
    }

    /**
     * Human readable per-format status of an attachment.
     *
     * @param int $attachment_id Attachment ID.
     * @return array {
     *     @type bool   $convertible Whether the attachment is a JPEG/PNG.
     *     @type bool   $excluded    Manually excluded.
     *     @type array  $formats     Format => converted|larger|failed|excluded|pending.
     *     @type string $error       Last error.
     *     @type int    $original    Original bytes.
     *     @type int    $optimized   Optimized bytes.
     * }
     */
    public static function tbswebpressor_get_status($attachment_id) {
        $status = array(
            'convertible' => self::tbswebpressor_is_convertible($attachment_id),
            'excluded'    => (bool) get_post_meta($attachment_id, TBS_WebPressor_Converter::EXCLUDED_META_KEY, true),
            'formats'     => array(),
            'error'       => (string) get_post_meta($attachment_id, TBS_WebPressor_Converter::ERROR_META_KEY, true),
            'original'    => intval(get_post_meta($attachment_id, 'tbswebpressor_original_size', true)),
            'optimized'   => intval(get_post_meta($attachment_id, 'tbswebpressor_optimized_size', true)),
        );

        foreach (TBS_WebPressor_Converter::tbswebpressor_get_active_formats() as $format) {
            $path = (string) get_post_meta($attachment_id, TBS_WebPressor_Converter::tbswebpressor_get_path_meta_key($format), true);
            $flag = (string) get_post_meta($attachment_id, TBS_WebPressor_Converter::tbswebpressor_get_status_meta_key($format), true);
            if ($path !== '') {
                $status['formats'][$format] = 'converted';
            } elseif ($flag !== '') {
                $status['formats'][$format] = $flag;
            } else {
                $status['formats'][$format] = 'pending';
            }
        }

        return $status;
    }

    /**
     * Render the status panel with action buttons.
     *
     * @param int $attachment_id Attachment ID.
     * @return string Escaped HTML.
     */
    public static function tbswebpressor_render_status($attachment_id) {
        $attachment_id = (int) $attachment_id;
        $status        = self::tbswebpressor_get_status($attachment_id);

        if (!$status['convertible']) {
            return '<div class="tbswebpressor-media-status"><span class="tbswebpressor-media-muted">'
                . esc_html__('Not a JPEG or PNG image', 'webpressor-webp-image-converter-optimizer') . '</span></div>';
        }

        $labels = array(
            'converted' => __('Optimized', 'webpressor-webp-image-converter-optimizer'),
            'pending'   => __('Not optimized yet', 'webpressor-webp-image-converter-optimizer'),
            'larger'    => __('Original kept (smaller)', 'webpressor-webp-image-converter-optimizer'),
            'failed'    => __('Failed', 'webpressor-webp-image-converter-optimizer'),
            'excluded'  => __('Excluded', 'webpressor-webp-image-converter-optimizer'),
        );

        $html = '<div class="tbswebpressor-media-status" data-attachment-id="' . esc_attr($attachment_id) . '">';

        if (empty($status['formats'])) {
            $html .= '<span class="tbswebpressor-media-muted">' . esc_html__('No output format is available on this server.', 'webpressor-webp-image-converter-optimizer') . '</span>';
        } else {
            $html .= '<ul class="tbswebpressor-media-formats">';
            foreach ($status['formats'] as $format => $state) {
                $label = isset($labels[$state]) ? $labels[$state] : $state;
                $html .= '<li><strong>' . esc_html(strtoupper($format)) . ':</strong> '
                    . '<span class="tbswebpressor-media-state tbswebpressor-media-state-' . esc_attr($state) . '">' . esc_html($label) . '</span></li>';
            }
            $html .= '</ul>';
        }

        if ($status['optimized'] > 0 && $status['original'] > 0) {
            $saved   = max(0, $status['original'] - $status['optimized']);
            $percent = (int) round(($saved / $status['original']) * 100);
            /* translators: 1: saved size, e.g. 120 KB. 2: percentage saved. */
            $html .= '<p class="tbswebpressor-media-saving">' . esc_html(sprintf(__('Saved %1$s (%2$d%%)', 'webpressor-webp-image-converter-optimizer'), size_format($saved, 1), $percent)) . '</p>';
        }

        if ($status['error'] !== '' && in_array('failed', $status['formats'], true)) {
            $html .= '<p class="tbswebpressor-media-error">' . esc_html($status['error']) . '</p>';
        }

        $actions = array();
        if ($status['excluded']) {
            $actions['include'] = __('Include in optimization', 'webpressor-webp-image-converter-optimizer');
        } else {
            $has_converted = in_array('converted', $status['formats'], true);
            if (!empty($status['formats'])) {
                $actions['convert'] = $has_converted
                    ? __('Re-optimize', 'webpressor-webp-image-converter-optimizer')
                    : __('Optimize now', 'webpressor-webp-image-converter-optimizer');
            }
            if ($has_converted) {
                $actions['remove'] = __('Remove WebP/AVIF', 'webpressor-webp-image-converter-optimizer');
            }
            $actions['exclude'] = __('Exclude', 'webpressor-webp-image-converter-optimizer');
        }

        $html .= '<div class="tbswebpressor-media-actions">';
        foreach ($actions as $operation => $label) {
            $html .= '<button type="button" class="button button-small tbswebpressor-media-action" data-operation="' . esc_attr($operation) . '" data-attachment-id="' . esc_attr($attachment_id) . '">'
                . esc_html($label) . '</button>';
        }
        $html .= '</div>';
        $html .= '<span class="tbswebpressor-media-feedback" aria-live="polite"></span>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Run a per-image operation.
     *
     * @param int    $attachment_id Attachment ID.
     * @param string $operation     convert|remove|exclude|include.
     * @return string|WP_Error Success message or error.
     */
    public static function tbswebpressor_run_action($attachment_id, $operation) {
        if (!self::tbswebpressor_is_convertible($attachment_id)) {
            return new WP_Error('tbswebpressor_not_convertible', __('Only JPEG and PNG images can be optimized.', 'webpressor-webp-image-converter-optimizer'));
        }

        switch ($operation) {
            case 'convert':
                if (get_post_meta($attachment_id, TBS_WebPressor_Converter::EXCLUDED_META_KEY, true)) {
                    return new WP_Error('tbswebpressor_excluded', __('This image is excluded. Include it first.', 'webpressor-webp-image-converter-optimizer'));
                }
                delete_post_meta($attachment_id, TBS_WebPressor_Converter::PROCESSING_META_KEY);
                $result = TBS_WebPressor_Converter::tbswebpressor_process_attachment($attachment_id, null, 'single');
                if ($result['result'] === 'failed') {
                    return new WP_Error('tbswebpressor_failed', $result['message']);
                }
                if ($result['result'] === 'converted') {
                    return __('Image optimized.', 'webpressor-webp-image-converter-optimizer');
                }
                return $result['message'];

            case 'remove':
                TBS_WebPressor_Converter::tbswebpressor_delete_attachment_variants($attachment_id, true);
                TBS_WebPressor_Converter::tbswebpressor_clear_attachment_state($attachment_id);
                TBS_WebPressor_Converter::tbswebpressor_clear_count_cache();
                return __('WebP/AVIF versions removed.', 'webpressor-webp-image-converter-optimizer');

            case 'exclude':
                TBS_WebPressor_Converter::tbswebpressor_delete_attachment_variants($attachment_id, true);
                TBS_WebPressor_Converter::tbswebpressor_clear_attachment_state($attachment_id);
                update_post_meta($attachment_id, TBS_WebPressor_Converter::EXCLUDED_META_KEY, 1);
                TBS_WebPressor_Converter::tbswebpressor_mark_excluded($attachment_id);
                return __('Image excluded from optimization.', 'webpressor-webp-image-converter-optimizer');

            case 'include':
                delete_post_meta($attachment_id, TBS_WebPressor_Converter::EXCLUDED_META_KEY);
                foreach (TBS_WebPressor_Converter::tbswebpressor_get_all_formats() as $format) {
                    delete_post_meta($attachment_id, TBS_WebPressor_Converter::tbswebpressor_get_status_meta_key($format), TBS_WebPressor_Converter::STATUS_EXCLUDED);
                }
                TBS_WebPressor_Converter::tbswebpressor_clear_count_cache();
                return __('Image included. It will be optimized by the next bulk run.', 'webpressor-webp-image-converter-optimizer');
        }

        return new WP_Error('tbswebpressor_unknown_operation', __('Unknown action.', 'webpressor-webp-image-converter-optimizer'));
    }
}
