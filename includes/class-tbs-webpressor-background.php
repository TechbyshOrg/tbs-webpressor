<?php
/**
 * Background (WP-Cron) bulk optimization.
 *
 * @since      2.2.0
 * @package    TBS_WebPressor
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class TBS_WebPressor_Background {

    /**
     * Cron hook that processes the next slice of the queue.
     */
    const CRON_HOOK = 'tbswebpressor_background_process';

    /**
     * Option holding the background run state.
     */
    const STATE_OPTION = 'tbswebpressor_background_state';

    /**
     * Value stored in the bulk lock while the background run owns it.
     */
    const LOCK_OWNER = 'background';

    /**
     * Transient set while a slice is executing, so slices never run in parallel.
     */
    const SLICE_GUARD = 'tbswebpressor_background_slice';

    /**
     * Register hooks.
     */
    public function tbswebpressor_background_setup_hooks() {
        add_action(self::CRON_HOOK, array(__CLASS__, 'tbswebpressor_run'));
    }

    /**
     * Default/empty state.
     *
     * @return array
     */
    private static function tbswebpressor_default_state() {
        return array(
            'status'     => 'idle',
            'started_at' => 0,
            'last_run'   => 0,
            'finished_at'=> 0,
            'converted'  => 0,
            'skipped'    => 0,
            'failed'     => 0,
            'message'    => '',
        );
    }

    /**
     * Current background state.
     *
     * @return array
     */
    public static function tbswebpressor_get_state() {
        $state = get_option(self::STATE_OPTION, array());
        $state = array_merge(self::tbswebpressor_default_state(), is_array($state) ? $state : array());

        // A stalled run (no event queued, no slice active for a while) is rescheduled.
        $last_activity = max((int) $state['last_run'], (int) $state['started_at']);
        if ($state['status'] === 'running'
            && !wp_next_scheduled(self::CRON_HOOK)
            && !get_transient(self::SLICE_GUARD)
            && (time() - $last_activity) > 2 * MINUTE_IN_SECONDS) {
            wp_schedule_single_event(time(), self::CRON_HOOK);
        }

        $state['next_run'] = (int) wp_next_scheduled(self::CRON_HOOK);
        return $state;
    }

    /**
     * Save background state.
     *
     * @param array $state State.
     */
    private static function tbswebpressor_save_state($state) {
        update_option(self::STATE_OPTION, array_intersect_key($state, self::tbswebpressor_default_state()), false);
    }

    /**
     * Start a background run.
     *
     * @return true|WP_Error
     */
    public static function tbswebpressor_start() {
        $lock = get_transient(TBS_WebPressor_Converter::BULK_LOCK_KEY);
        if ($lock && $lock !== self::LOCK_OWNER) {
            return new WP_Error('tbswebpressor_locked', __('Bulk optimization is already running in another session.', 'webpressor-webp-image-converter-optimizer'));
        }

        if (TBS_WebPressor_Converter::tbswebpressor_count_pending() === 0) {
            return new WP_Error('tbswebpressor_nothing', __('There are no images waiting to be optimized.', 'webpressor-webp-image-converter-optimizer'));
        }

        set_transient(TBS_WebPressor_Converter::BULK_LOCK_KEY, self::LOCK_OWNER, 15 * MINUTE_IN_SECONDS);

        $state = self::tbswebpressor_default_state();
        $state['status']     = 'running';
        $state['started_at'] = time();
        self::tbswebpressor_save_state($state);

        wp_clear_scheduled_hook(self::CRON_HOOK);
        wp_schedule_single_event(time(), self::CRON_HOOK);
        spawn_cron();

        return true;
    }

    /**
     * Stop the background run.
     */
    public static function tbswebpressor_stop() {
        wp_clear_scheduled_hook(self::CRON_HOOK);

        $state = get_option(self::STATE_OPTION, array());
        if (is_array($state) && isset($state['status']) && $state['status'] === 'running') {
            $state['status']      = 'idle';
            $state['finished_at'] = time();
            $state['message']     = 'stopped';
            self::tbswebpressor_save_state(array_merge(self::tbswebpressor_default_state(), $state));
        }

        if (get_transient(TBS_WebPressor_Converter::BULK_LOCK_KEY) === self::LOCK_OWNER) {
            TBS_WebPressor_Converter::tbswebpressor_release_bulk_lock();
        }
    }

    /**
     * Cron callback: process batches for a limited time, then schedule the next slice.
     */
    public static function tbswebpressor_run() {
        $state = array_merge(self::tbswebpressor_default_state(), (array) get_option(self::STATE_OPTION, array()));
        if ($state['status'] !== 'running') {
            return;
        }

        if (get_transient(self::SLICE_GUARD)) {
            return;
        }

        $lock = get_transient(TBS_WebPressor_Converter::BULK_LOCK_KEY);
        if ($lock && $lock !== self::LOCK_OWNER) {
            // Another session took over (e.g. after the lock expired); try again later.
            wp_schedule_single_event(time() + MINUTE_IN_SECONDS, self::CRON_HOOK);
            return;
        }
        set_transient(TBS_WebPressor_Converter::BULK_LOCK_KEY, self::LOCK_OWNER, 15 * MINUTE_IN_SECONDS);

        /**
         * Filter the number of seconds one background slice may run.
         *
         * @param int $seconds Time budget in seconds.
         */
        $budget   = max(5, (int) apply_filters('tbswebpressor_background_time_limit', 20));
        set_transient(self::SLICE_GUARD, 1, $budget + 2 * MINUTE_IN_SECONDS);
        $state    = self::tbswebpressor_run_slice($state, $budget);
        delete_transient(self::SLICE_GUARD);

        if ($state === null) {
            return;
        }

        if ($state['status'] === 'running') {
            set_transient(TBS_WebPressor_Converter::BULK_LOCK_KEY, self::LOCK_OWNER, 15 * MINUTE_IN_SECONDS);
            wp_schedule_single_event(time(), self::CRON_HOOK);
            return;
        }

        if (get_transient(TBS_WebPressor_Converter::BULK_LOCK_KEY) === self::LOCK_OWNER) {
            TBS_WebPressor_Converter::tbswebpressor_release_bulk_lock();
        }
    }

    /**
     * Process batches until the queue is empty or the time budget is used.
     *
     * @param array $state  Current state.
     * @param int   $budget Time budget in seconds.
     * @return array|null Updated state, or null when the run was stopped meanwhile.
     */
    private static function tbswebpressor_run_slice($state, $budget) {
        $start    = time();
        $has_more = true;

        while ($has_more && (time() - $start) < $budget) {
            $result = TBS_WebPressor_Converter::tbswebpressor_convert_attachements_batch(1, 5, 'background');
            foreach ($result['processed'] as $item) {
                if ($item['result'] === 'converted') {
                    $state['converted']++;
                } elseif ($item['result'] === 'failed') {
                    $state['failed']++;
                } else {
                    $state['skipped']++;
                }
            }
            $has_more = $result['hasMorePages'];

            // Stop requested from the dashboard while this slice was running.
            wp_cache_delete(self::STATE_OPTION, 'options');
            $current = get_option(self::STATE_OPTION, array());
            if (!is_array($current) || !isset($current['status']) || $current['status'] !== 'running') {
                return null;
            }

            $state['last_run'] = time();
            self::tbswebpressor_save_state($state);
        }

        if (!$has_more) {
            $state['status']      = 'idle';
            $state['finished_at'] = time();
            $state['message']     = 'completed';
            self::tbswebpressor_save_state($state);
        }

        return $state;
    }
}
