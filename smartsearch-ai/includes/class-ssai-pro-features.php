<?php
/**
 * Pro features gate and upsell system.
 *
 * Free version includes:
 *   - Full dictionary-based search with synonyms, intents, keywords
 *   - Client-side Fuse.js autocomplete
 *   - Shortcode with customization options
 *   - Home services default dictionary
 *   - Import/export dictionaries
 *   - Hooks and filters for developers
 *
 * Pro version adds:
 *   - AI-powered fallback (OpenAI integration)
 *   - Premium industry dictionaries (auto, legal, medical, etc.)
 *   - Search analytics dashboard
 *   - Custom dictionary builder UI (visual editor)
 *   - Priority email support
 *   - White-label option (remove SmartSearch AI branding)
 *   - Multi-site license
 *
 * @package SmartSearch_AI
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SSAI_Pro_Features {

    /**
     * Features that require Pro.
     *
     * @var array
     */
    const PRO_FEATURES = array(
        'ai_fallback'          => 'AI-Powered Search Fallback',
        'premium_dictionaries' => 'Premium Industry Dictionaries',
        'search_analytics'     => 'Search Analytics Dashboard',
        'visual_editor'        => 'Visual Dictionary Builder',
        'white_label'          => 'White-Label Branding',
        'priority_support'     => 'Priority Email Support',
    );

    public function __construct() {
        add_action( 'admin_notices', array( $this, 'maybe_show_review_notice' ) );
        add_action( 'wp_ajax_ssai_dismiss_notice', array( $this, 'dismiss_notice' ) );
    }

    /**
     * Check if a specific Pro feature is available.
     *
     * @param string $feature Feature slug.
     * @return bool
     */
    public static function has_feature( $feature ) {
        if ( SmartSearch_AI::is_pro() ) {
            return true;
        }

        // These features are always available in free
        $free_features = array(
            'dictionary_search',
            'autocomplete',
            'shortcode',
            'import_export',
            'home_services_dict',
            'hooks_filters',
        );

        return in_array( $feature, $free_features, true );
    }

    /**
     * Get pro features list for display.
     *
     * @return array
     */
    public static function get_pro_features() {
        return self::PRO_FEATURES;
    }

    /**
     * Show review notice after 7 days of use.
     */
    public function maybe_show_review_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( get_option( 'ssai_review_dismissed' ) ) {
            return;
        }

        $installed_at = get_option( 'ssai_installed_at', 0 );
        if ( ! $installed_at || ( time() - $installed_at ) < ( 7 * DAY_IN_SECONDS ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || 'settings_page_smartsearch-ai' !== $screen->id ) {
            return;
        }

        ?>
        <div class="notice notice-info is-dismissible ssai-review-notice" data-notice="review">
            <p>
                <strong><?php esc_html_e( '🔍 Enjoying SmartSearch AI?', 'smartsearch-ai' ); ?></strong>
                <?php esc_html_e( "You've been using SmartSearch AI for a week now! If it's helping your visitors find what they need, would you consider leaving a quick review?", 'smartsearch-ai' ); ?>
            </p>
            <p>
                <a href="https://wordpress.org/support/plugin/smartsearch-ai/reviews/#new-post" target="_blank" class="button button-primary">
                    <?php esc_html_e( 'Leave a Review', 'smartsearch-ai' ); ?>
                </a>
                <a href="<?php echo esc_url( SmartSearch_AI::pro_url( 'review-notice' ) ); ?>" target="_blank" class="button">
                    <?php esc_html_e( 'Upgrade to Pro', 'smartsearch-ai' ); ?>
                </a>
                <button type="button" class="button button-link ssai-dismiss-notice" data-notice="review">
                    <?php esc_html_e( 'Maybe later', 'smartsearch-ai' ); ?>
                </button>
            </p>
        </div>
        <script>
        jQuery(function($) {
            $('.ssai-dismiss-notice').on('click', function() {
                var notice = $(this).data('notice');
                $(this).closest('.notice').fadeOut();
                $.post(ajaxurl, { action: 'ssai_dismiss_notice', notice: notice, nonce: '<?php echo esc_js( wp_create_nonce( 'ssai_dismiss' ) ); ?>' });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler for dismissing notices.
     */
    public function dismiss_notice() {
        check_ajax_referer( 'ssai_dismiss', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }

        $notice = isset( $_POST['notice'] ) ? sanitize_key( $_POST['notice'] ) : '';

        if ( 'review' === $notice ) {
            update_option( 'ssai_review_dismissed', true );
        }

        wp_send_json_success();
    }
}

new SSAI_Pro_Features();
