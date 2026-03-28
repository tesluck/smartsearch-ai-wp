<?php
/**
 * Shortcodes for embedding SmartSearch AI anywhere.
 *
 * Usage:
 *   [smartsearch]                                  — Basic search bar
 *   [smartsearch location="true"]                  — With location field
 *   [smartsearch placeholder="Find a service..."]  — Custom placeholder
 *   [smartsearch show_results="true"]              — Results inline below search
 *   [smartsearch button_text="Search"]             — Custom button text
 *
 * @package SmartSearch_AI
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SSAI_Shortcodes {

    public function __construct() {
        add_shortcode( 'smartsearch', array( $this, 'render_search' ) );
        add_shortcode( 'smartsearch_ai', array( $this, 'render_search' ) );
    }

    /**
     * Render the search bar.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_search( $atts ) {
        $atts = shortcode_atts( array(
            'placeholder'          => '',
            'location'             => 'false',
            'location_placeholder' => __( 'City or ZIP code', 'smartsearch-ai' ),
            'show_results'         => 'true',
            'button_text'          => __( 'Search', 'smartsearch-ai' ),
            'class'                => '',
            'results_page'         => '',
        ), $atts, 'smartsearch' );

        $options       = get_option( 'ssai_options', array() );
        $placeholder   = ! empty( $atts['placeholder'] ) ? $atts['placeholder'] : ( isset( $options['placeholder'] ) ? $options['placeholder'] : __( 'Describe what you need help with...', 'smartsearch-ai' ) );
        $show_location = filter_var( $atts['location'], FILTER_VALIDATE_BOOLEAN );
        $show_results  = filter_var( $atts['show_results'], FILTER_VALIDATE_BOOLEAN );
        $extra_class   = ! empty( $atts['class'] ) ? ' ' . sanitize_html_class( $atts['class'] ) : '';

        ob_start();
        ?>
        <div class="ssai-search-wrapper<?php echo esc_attr( $extra_class ); ?>" data-ssai-search>
            <div class="ssai-search-row<?php echo $show_location ? ' ssai-has-location' : ''; ?>">
                <div class="ssai-input-wrap ssai-has-icon">
                    <svg class="ssai-search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input
                        type="text"
                        class="ssai-search-input"
                        placeholder="<?php echo esc_attr( $placeholder ); ?>"
                        autocomplete="off"
                        aria-label="<?php echo esc_attr( $placeholder ); ?>"
                        role="combobox"
                        aria-expanded="false"
                        aria-haspopup="listbox"
                    />
                    <div class="ssai-suggestions" role="listbox"></div>
                </div>

                <?php if ( $show_location ) : ?>
                <input
                    type="text"
                    class="ssai-location-input"
                    placeholder="<?php echo esc_attr( $atts['location_placeholder'] ); ?>"
                    autocomplete="off"
                    aria-label="<?php echo esc_attr( $atts['location_placeholder'] ); ?>"
                />
                <?php endif; ?>

                <button type="button" class="ssai-search-btn">
                    <?php echo esc_html( $atts['button_text'] ); ?>
                </button>
            </div>

            <?php if ( $show_results ) : ?>
            <div class="ssai-results"></div>
            <?php endif; ?>

            <?php if ( ! SmartSearch_AI::is_pro() ) : ?>
            <div class="ssai-powered-by">
                <small>Powered by <a href="https://timtesluck.com/smartsearch-ai/?utm_source=widget" target="_blank" rel="noopener">SmartSearch AI</a></small>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

new SSAI_Shortcodes();
