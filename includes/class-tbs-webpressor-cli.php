<?php
/**
 * WP-CLI commands.
 *
 * @since      2.2.0
 * @package    TBS_WebPressor
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Convert, inspect and reset WebPressor WebP/AVIF images.
 */
class TBS_WebPressor_CLI {

    /**
     * Value stored in the bulk lock while a CLI run owns it.
     */
    const LOCK_OWNER = 'cli';

    /**
     * Show library optimization status.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - yaml
     * ---
     *
     * ## EXAMPLES
     *
     *     wp webpressor status
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Named arguments.
     */
    public function status($args, $assoc_args) {
        $counts = TBS_WebPressor_Converter::tbswebpressor_get_media_counts(true);
        $orig   = intval(get_option('tbswebpressor_total_original_size', 0));
        $opt    = intval(get_option('tbswebpressor_total_optimized_size', 0));

        $rows = array(
            array('key' => 'active_formats', 'value' => implode(',', TBS_WebPressor_Converter::tbswebpressor_get_active_formats())),
            array('key' => 'total', 'value' => $counts['total']),
            array('key' => 'pending', 'value' => $counts['pending']),
            array('key' => 'failed', 'value' => $counts['failed']),
            array('key' => 'skipped_larger', 'value' => $counts['skipped']),
            array('key' => 'excluded', 'value' => $counts['excluded']),
            array('key' => 'bytes_saved', 'value' => max(0, $orig - $opt)),
        );

        \WP_CLI\Utils\format_items(\WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table'), $rows, array('key', 'value'));
    }

    /**
     * Convert pending images (or the given attachment IDs) to WebP/AVIF.
     *
     * ## OPTIONS
     *
     * [<id>...]
     * : Attachment IDs to convert. When omitted, all pending images are converted.
     *
     * [--limit=<number>]
     * : Stop after this many images.
     *
     * [--retry-failed]
     * : Queue previously failed images again before converting.
     *
     * ## EXAMPLES
     *
     *     wp webpressor convert
     *     wp webpressor convert --limit=100
     *     wp webpressor convert 42 57
     *     wp webpressor convert --retry-failed
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Named arguments.
     */
    public function convert($args, $assoc_args) {
        if (empty(TBS_WebPressor_Converter::tbswebpressor_get_active_formats())) {
            WP_CLI::error('None of the selected output formats can be created on this server.');
        }

        if (!empty($args)) {
            $this->convert_ids(array_map('absint', $args));
            return;
        }

        $lock = get_transient(TBS_WebPressor_Converter::BULK_LOCK_KEY);
        if ($lock && $lock !== self::LOCK_OWNER) {
            WP_CLI::error('Bulk optimization is already running in another session (dashboard or background). Stop it first.');
        }

        if (\WP_CLI\Utils\get_flag_value($assoc_args, 'retry-failed', false)) {
            TBS_WebPressor_Converter::tbswebpressor_clear_status_everywhere(TBS_WebPressor_Converter::STATUS_FAILED);
        }

        $limit   = absint(\WP_CLI\Utils\get_flag_value($assoc_args, 'limit', 0));
        $pending = TBS_WebPressor_Converter::tbswebpressor_count_pending();
        if ($pending === 0) {
            WP_CLI::success('Nothing to convert.');
            return;
        }

        $total    = $limit > 0 ? min($limit, $pending) : $pending;
        $progress = \WP_CLI\Utils\make_progress_bar('Converting images', $total);
        $summary  = array('converted' => 0, 'skipped' => 0, 'excluded' => 0, 'failed' => 0);
        $done     = 0;

        set_transient(TBS_WebPressor_Converter::BULK_LOCK_KEY, self::LOCK_OWNER, 15 * MINUTE_IN_SECONDS);

        do {
            $batch_size = $limit > 0 ? min(5, $limit - $done) : 5;
            $result     = TBS_WebPressor_Converter::tbswebpressor_convert_attachements_batch(1, $batch_size, 'cli');
            foreach ($result['processed'] as $item) {
                $this->record($item, $summary);
                $progress->tick();
                $done++;
            }
            set_transient(TBS_WebPressor_Converter::BULK_LOCK_KEY, self::LOCK_OWNER, 15 * MINUTE_IN_SECONDS);
        } while ($result['hasMorePages'] && ($limit === 0 || $done < $limit));

        $progress->finish();
        if (get_transient(TBS_WebPressor_Converter::BULK_LOCK_KEY) === self::LOCK_OWNER) {
            TBS_WebPressor_Converter::tbswebpressor_release_bulk_lock();
        }

        $this->report($summary);
    }

    /**
     * Delete all generated WebP/AVIF files and conversion data. Originals are not touched.
     *
     * ## OPTIONS
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * ## EXAMPLES
     *
     *     wp webpressor reset --yes
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Named arguments.
     */
    public function reset($args, $assoc_args) {
        WP_CLI::confirm('Delete all WebP/AVIF files generated by WebPressor?', $assoc_args);
        TBS_WebPressor_Background::tbswebpressor_stop();
        TBS_WebPressor_Converter::tbswebpressor_reset_all();
        WP_CLI::success('All generated files and conversion data were removed.');
    }

    /**
     * Convert specific attachments.
     *
     * @param int[] $ids Attachment IDs.
     */
    private function convert_ids($ids) {
        $summary = array('converted' => 0, 'skipped' => 0, 'excluded' => 0, 'failed' => 0);
        foreach (array_filter($ids) as $id) {
            if (get_post_type($id) !== 'attachment') {
                WP_CLI::warning(sprintf('#%d is not an attachment.', $id));
                continue;
            }
            delete_post_meta($id, TBS_WebPressor_Converter::PROCESSING_META_KEY);
            $item = TBS_WebPressor_Converter::tbswebpressor_process_attachment($id, null, 'cli');
            $this->record($item, $summary);
        }
        $this->report($summary);
    }

    /**
     * Record one result and print problems.
     *
     * @param array $item    Result.
     * @param array $summary Summary counters.
     */
    private function record($item, &$summary) {
        $key = isset($summary[$item['result']]) ? $item['result'] : 'skipped';
        $summary[$key]++;
        if ($item['result'] === 'failed') {
            WP_CLI::warning(sprintf('#%d %s: %s', $item['id'], $item['filename'], $item['message']));
        } else {
            WP_CLI::debug(sprintf('#%d %s: %s', $item['id'], $item['filename'], $item['result']), 'webpressor');
        }
    }

    /**
     * Print the summary.
     *
     * @param array $summary Summary counters.
     */
    private function report($summary) {
        $message = sprintf(
            'Converted: %d, kept original (not smaller): %d, excluded: %d, failed: %d.',
            $summary['converted'],
            $summary['skipped'],
            $summary['excluded'],
            $summary['failed']
        );
        if ($summary['failed'] > 0) {
            WP_CLI::warning($message);
        } else {
            WP_CLI::success($message);
        }
    }
}
