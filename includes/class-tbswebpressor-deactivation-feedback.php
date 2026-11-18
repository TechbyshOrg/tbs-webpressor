<?php
/**
 * Optional deactivation feedback survey.
 *
 * When an admin clicks "Deactivate" for this plugin on the Plugins screen, a modal asks
 * why. Nothing is sent unless the admin clicks "Submit & Deactivate". The site URL and
 * admin email are only included when the admin ticks the opt-in checkbox.
 *
 * The answer is posted (form-encoded) from the server to a Google Apps Script web app,
 * which appends it to a Google Sheet. Until a valid https endpoint is configured the
 * feature sends nothing and deactivation works normally.
 *
 * Configuration (any of):
 * - Edit ENDPOINT_URL / SECRET_TOKEN below.
 * - Define TBSWEBPRESSOR_FEEDBACK_URL / TBSWEBPRESSOR_FEEDBACK_TOKEN (e.g. in wp-config.php).
 * - Filter tbswebpressor_deactivation_feedback_url / tbswebpressor_deactivation_feedback_token.
 *
 * Note: this plugin's code is public, so SECRET_TOKEN only deters casual abuse of the
 * endpoint. Treat incoming rows as untrusted and rotate the token if needed.
 *
 * @package TBS_WebPressor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TBSWEBPRESSOR_Deactivation_Feedback {

    /**
     * Google Apps Script web app URL (ends with /exec).
     *
     * @var string
     */
    const ENDPOINT_URL = 'https://script.google.com/macros/s/AKfycbxH4AIQgQpxVqcGsIutXYaWL5Nx-Wg5AVsaLbfo2_I5MhWrjUzrK9V0Rjam5M077X7b/exec';

    /**
     * Shared secret checked by the Apps Script.
     *
     * @var string
     */
    const SECRET_TOKEN = 'RObugXJsyrGIlwX4UEpXS591iPqcrkNftrZZBoWn';

    /**
     * AJAX action name.
     *
     * @var string
     */
    const AJAX_ACTION = 'tbswebpressor_deactivation_feedback';

    /**
     * Maximum length of the free-text details field.
     *
     * @var int
     */
    const DETAILS_MAX_LENGTH = 1000;

    /**
     * Milliseconds the browser waits for the AJAX request before deactivating anyway.
     *
     * @var int
     */
    const CLIENT_TIMEOUT_MS = 3000;

    /**
     * Register hooks. Call once, in the admin only.
     */
    public static function init() {
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'admin_footer', array( __CLASS__, 'render_modal' ) );
        add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'handle_ajax' ) );
    }

    /**
     * Allowed reasons and their labels.
     *
     * Each detail key is 'none', 'input' (single line) or 'textarea'.
     *
     * @return array<string, array{label: string, detail: string, placeholder: string}>
     */
    private static function get_reasons() {
        return array(
            'no_longer_needed' => array(
                'label'       => __( 'I no longer need the plugin', 'webpressor-webp-image-converter-optimizer' ),
                'detail'      => 'none',
                'placeholder' => '',
            ),
            'better_plugin'    => array(
                'label'       => __( 'I found a better plugin', 'webpressor-webp-image-converter-optimizer' ),
                'detail'      => 'input',
                'placeholder' => __( 'Which plugin? (optional)', 'webpressor-webp-image-converter-optimizer' ),
            ),
            'not_working'      => array(
                'label'       => __( "The plugin didn't work as expected", 'webpressor-webp-image-converter-optimizer' ),
                'detail'      => 'textarea',
                'placeholder' => __( 'What went wrong? (optional)', 'webpressor-webp-image-converter-optimizer' ),
            ),
            'broke_site'       => array(
                'label'       => __( 'It broke my site or conflicts with another plugin', 'webpressor-webp-image-converter-optimizer' ),
                'detail'      => 'textarea',
                'placeholder' => __( 'Which plugin or theme conflicts, or what broke? (optional)', 'webpressor-webp-image-converter-optimizer' ),
            ),
            'missing_feature'  => array(
                'label'       => __( "It's missing a feature I need", 'webpressor-webp-image-converter-optimizer' ),
                'detail'      => 'textarea',
                'placeholder' => __( 'Which feature do you need? (optional)', 'webpressor-webp-image-converter-optimizer' ),
            ),
            'too_complicated'  => array(
                'label'       => __( "It's too complicated to use", 'webpressor-webp-image-converter-optimizer' ),
                'detail'      => 'textarea',
                'placeholder' => __( 'What was confusing? (optional)', 'webpressor-webp-image-converter-optimizer' ),
            ),
            'temporary'        => array(
                'label'       => __( "It's a temporary deactivation, I'm just debugging", 'webpressor-webp-image-converter-optimizer' ),
                'detail'      => 'none',
                'placeholder' => '',
            ),
            'other'            => array(
                'label'       => __( 'Other', 'webpressor-webp-image-converter-optimizer' ),
                'detail'      => 'textarea',
                'placeholder' => __( 'Please tell us more (optional)', 'webpressor-webp-image-converter-optimizer' ),
            ),
        );
    }

    /**
     * Plugin basename (folder/main-file.php), computed from the main file.
     *
     * @return string
     */
    private static function get_basename() {
        return plugin_basename( TBSWEBPRESSOR_PLUGIN_FILE );
    }

    /**
     * Plugin slug (folder name).
     *
     * @return string
     */
    private static function get_slug() {
        return dirname( self::get_basename() );
    }

    /**
     * Plugin version, from the existing constant or the plugin header.
     *
     * @return string
     */
    private static function get_version() {
        static $version = null;
        if ( null === $version ) {
            if ( defined( 'TBSWEBPRESSOR_VERSION' ) ) {
                $version = TBSWEBPRESSOR_VERSION;
            } else {
                $data    = get_file_data( TBSWEBPRESSOR_PLUGIN_FILE, array( 'Version' => 'Version' ) );
                $version = isset( $data['Version'] ) ? (string) $data['Version'] : '';
            }
        }
        return $version;
    }

    /**
     * Translated plugin name from the plugin header.
     *
     * @return string
     */
    private static function get_plugin_name() {
        static $name = null;
        if ( null === $name ) {
            $data = get_file_data( TBSWEBPRESSOR_PLUGIN_FILE, array( 'Name' => 'Plugin Name' ) );
            $name = ! empty( $data['Name'] )
                ? translate( $data['Name'], 'webpressor-webp-image-converter-optimizer' ) // phpcs:ignore WordPress.WP.I18n.LowLevelTranslationFunction,WordPress.WP.I18n.NonSingularStringLiteralText
                : self::get_slug();
        }
        return $name;
    }

    /**
     * Endpoint URL: constant override, then class constant, then filter.
     *
     * @return string
     */
    private static function get_endpoint_url() {
        $url = defined( 'TBSWEBPRESSOR_FEEDBACK_URL' ) ? (string) TBSWEBPRESSOR_FEEDBACK_URL : self::ENDPOINT_URL;

        /**
         * Filter the deactivation feedback endpoint URL.
         *
         * @param string $url Google Apps Script web app URL.
         */
        return (string) apply_filters( 'tbswebpressor_deactivation_feedback_url', $url );
    }

    /**
     * Shared secret token: constant override, then class constant, then filter.
     *
     * @return string
     */
    private static function get_token() {
        $token = defined( 'TBSWEBPRESSOR_FEEDBACK_TOKEN' ) ? (string) TBSWEBPRESSOR_FEEDBACK_TOKEN : self::SECRET_TOKEN;

        /**
         * Filter the deactivation feedback secret token.
         *
         * @param string $token Shared secret.
         */
        return (string) apply_filters( 'tbswebpressor_deactivation_feedback_token', $token );
    }

    /**
     * Whether a usable endpoint is configured (https and not the placeholder).
     *
     * @param string $url Endpoint URL.
     * @return bool
     */
    private static function is_valid_endpoint( $url ) {
        if ( '' === $url || false !== strpos( $url, 'PASTE_YOUR' ) ) {
            return false;
        }
        return 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) && false !== wp_http_validate_url( $url );
    }

    /**
     * Whether the current screen is the (network) Plugins screen.
     *
     * The hook suffix is "plugins.php" on both the site and network admin Plugins screens.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     * @return bool
     */
    private static function is_plugins_screen( $hook_suffix ) {
        return 'plugins.php' === $hook_suffix && current_user_can( 'activate_plugins' );
    }

    /**
     * Enqueue the modal script and styles on the Plugins screen only.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     */
    public static function enqueue_assets( $hook_suffix ) {
        if ( ! self::is_plugins_screen( $hook_suffix ) ) {
            return;
        }

        $version = self::get_version();

        wp_enqueue_style(
            'tbswebpressor-deactivation-feedback',
            TBSWEBPRESSOR_PLUGIN_URL . 'assets/css/deactivation-feedback.css',
            array(),
            $version
        );

        wp_enqueue_script(
            'tbswebpressor-deactivation-feedback',
            TBSWEBPRESSOR_PLUGIN_URL . 'assets/js/deactivation-feedback.js',
            array( 'jquery' ),
            $version,
            true
        );

        wp_localize_script(
            'tbswebpressor-deactivation-feedback',
            'tbswebpressorDeactivationFeedback',
            array(
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'action'    => self::AJAX_ACTION,
                'nonce'     => wp_create_nonce( self::AJAX_ACTION ),
                'basename'  => self::get_basename(),
                'timeout'   => self::CLIENT_TIMEOUT_MS,
                'maxLength' => self::DETAILS_MAX_LENGTH,
                'i18n'      => array(
                    'deactivating' => __( 'Deactivating...', 'webpressor-webp-image-converter-optimizer' ),
                    'submit'       => __( 'Submit & Deactivate', 'webpressor-webp-image-converter-optimizer' ),
                ),
            )
        );
    }

    /**
     * Print the (hidden) modal markup in the Plugins screen footer.
     */
    public static function render_modal() {
        global $hook_suffix;
        if ( ! self::is_plugins_screen( (string) $hook_suffix ) ) {
            return;
        }

        $title = sprintf(
            /* translators: %s: plugin name */
            __( 'Quick feedback - why are you deactivating %s?', 'webpressor-webp-image-converter-optimizer' ),
            self::get_plugin_name()
        );
        ?>
        <div id="tbswebpressor-df-modal" class="tbswebpressor-df-modal" hidden>
            <div class="tbswebpressor-df-backdrop" data-tbswebpressor-df-close="1"></div>
            <div class="tbswebpressor-df-dialog" role="dialog" aria-modal="true" aria-labelledby="tbswebpressor-df-title" aria-describedby="tbswebpressor-df-subtitle">
                <div class="tbswebpressor-df-header">
                    <span class="tbswebpressor-df-icon dashicons dashicons-feedback" aria-hidden="true"></span>
                    <div class="tbswebpressor-df-heading">
                        <h2 id="tbswebpressor-df-title" class="tbswebpressor-df-title"><?php echo esc_html( $title ); ?></h2>
                        <p id="tbswebpressor-df-subtitle" class="tbswebpressor-df-subtitle"><?php esc_html_e( 'Your answer helps us improve. It takes 5 seconds.', 'webpressor-webp-image-converter-optimizer' ); ?></p>
                    </div>
                    <button type="button" class="tbswebpressor-df-close" data-tbswebpressor-df-close="1" aria-label="<?php esc_attr_e( 'Close and keep the plugin active', 'webpressor-webp-image-converter-optimizer' ); ?>">
                        <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                    </button>
                </div>

                <form id="tbswebpressor-df-form" class="tbswebpressor-df-form" novalidate>
                    <div class="tbswebpressor-df-body">
                        <fieldset class="tbswebpressor-df-reasons">
                            <legend class="screen-reader-text"><?php esc_html_e( 'Reason for deactivating', 'webpressor-webp-image-converter-optimizer' ); ?></legend>
                            <?php foreach ( self::get_reasons() as $value => $reason ) : ?>
                                <?php $input_id = 'tbswebpressor-df-reason-' . $value; ?>
                                <div class="tbswebpressor-df-reason">
                                    <label for="<?php echo esc_attr( $input_id ); ?>" class="tbswebpressor-df-reason-label">
                                        <input type="radio" id="<?php echo esc_attr( $input_id ); ?>" name="tbswebpressor_df_reason" value="<?php echo esc_attr( $value ); ?>" />
                                        <span><?php echo esc_html( $reason['label'] ); ?></span>
                                    </label>
                                    <?php if ( 'none' !== $reason['detail'] ) : ?>
                                        <?php $detail_id = 'tbswebpressor-df-detail-' . $value; ?>
                                        <div class="tbswebpressor-df-detail" data-tbswebpressor-df-detail="<?php echo esc_attr( $value ); ?>" hidden>
                                            <label for="<?php echo esc_attr( $detail_id ); ?>" class="tbswebpressor-df-detail-label"><?php echo esc_html( $reason['placeholder'] ); ?></label>
                                            <?php if ( 'input' === $reason['detail'] ) : ?>
                                                <input type="text" id="<?php echo esc_attr( $detail_id ); ?>" class="tbswebpressor-df-field" maxlength="<?php echo esc_attr( self::DETAILS_MAX_LENGTH ); ?>" placeholder="<?php esc_attr_e( 'Plugin name', 'webpressor-webp-image-converter-optimizer' ); ?>" />
                                            <?php else : ?>
                                                <textarea id="<?php echo esc_attr( $detail_id ); ?>" class="tbswebpressor-df-field" rows="3" maxlength="<?php echo esc_attr( self::DETAILS_MAX_LENGTH ); ?>" placeholder="<?php esc_attr_e( 'Type your answer here…', 'webpressor-webp-image-converter-optimizer' ); ?>"></textarea>
                                            <?php endif; ?>
                                            <?php $max_label = number_format_i18n( self::DETAILS_MAX_LENGTH ); ?>
                                            <span class="tbswebpressor-df-count" data-max="<?php echo esc_attr( $max_label ); ?>" aria-hidden="true">0 / <?php echo esc_html( $max_label ); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </fieldset>

                        <div class="tbswebpressor-df-consent">
                            <label for="tbswebpressor-df-consent">
                                <input type="checkbox" id="tbswebpressor-df-consent" name="tbswebpressor_df_consent" value="1" />
                                <span><?php esc_html_e( 'Share my site URL and admin email so you can contact me about this issue.', 'webpressor-webp-image-converter-optimizer' ); ?></span>
                            </label>
                        </div>
                    </div>

                    <div class="tbswebpressor-df-footer">
                        <button type="button" class="button tbswebpressor-df-skip"><?php esc_html_e( 'Skip & Deactivate', 'webpressor-webp-image-converter-optimizer' ); ?></button>
                        <span class="tbswebpressor-df-status" aria-live="polite">
                            <span class="spinner tbswebpressor-df-spinner"></span>
                            <span class="tbswebpressor-df-status-text"></span>
                        </span>
                        <button type="submit" class="button button-primary tbswebpressor-df-submit" disabled><?php esc_html_e( 'Submit & Deactivate', 'webpressor-webp-image-converter-optimizer' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: validate the answer and forward it to the endpoint.
     *
     * Always responds with success so the browser proceeds to deactivate,
     * whatever happens with the remote request. Nothing is stored locally.
     */
    public static function handle_ajax() {
        check_ajax_referer( self::AJAX_ACTION, 'nonce' );

        if ( ! current_user_can( 'activate_plugins' ) ) {
            wp_send_json_error( null, 403 );
        }

        $url = self::get_endpoint_url();
        if ( ! self::is_valid_endpoint( $url ) ) {
            // Not configured yet: send nothing, let deactivation continue.
            wp_send_json_success();
        }

        $reasons = self::get_reasons();
        $reason  = isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : '';
        if ( ! isset( $reasons[ $reason ] ) ) {
            $reason = 'other';
        }

        $details = isset( $_POST['details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['details'] ) ) : '';
        $details = trim( function_exists( 'mb_substr' ) ? mb_substr( $details, 0, self::DETAILS_MAX_LENGTH ) : substr( $details, 0, self::DETAILS_MAX_LENGTH ) );

        $consent = isset( $_POST['consent'] ) && in_array( wp_unslash( $_POST['consent'] ), array( '1', 'true', 'yes' ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared against a fixed list.

        $theme = wp_get_theme();

        $payload = array(
            'token'          => self::get_token(),
            'plugin'         => self::get_slug(),
            'plugin_version' => self::get_version(),
            'reason'         => $reason,
            'details'        => $details,
            'wp_version'     => get_bloginfo( 'version' ),
            'php_version'    => PHP_VERSION,
            'locale'         => get_locale(),
            'multisite'      => is_multisite() ? 'yes' : 'no',
            'active_theme'   => $theme ? (string) $theme->get( 'Name' ) : '',
            'site_url'       => $consent ? home_url() : '',
            'admin_email'    => $consent ? (string) get_option( 'admin_email' ) : '',
        );

        /**
         * Filter the deactivation feedback payload before it is sent.
         *
         * @param array $payload Form fields posted to the endpoint.
         */
        $payload = apply_filters( 'tbswebpressor_deactivation_feedback_payload', $payload );

        // The browser may move on to the deactivation URL before this finishes; keep going.
        ignore_user_abort( true );

        wp_remote_post(
            $url,
            array(
                'body'        => is_array( $payload ) ? $payload : array(),
                'timeout'     => 5,
                'blocking'    => false,
                'redirection' => 0,
            )
        );

        wp_send_json_success();
    }
}
