<?php
/**
 * Plugin Name: SmartSearch AI
 * Plugin URI: https://timtesluck.com/smartsearch-ai
 * Description: Intelligent natural language search for WordPress. Your visitors describe their problem — SmartSearch AI understands what they need and shows the right results. Ships with 40+ home service categories out of the box, fully configurable for any industry. Optional AI-powered fallback for unmatched queries.
 * Version: 1.0.2
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Tim Tesluck
 * Author URI: https://timtesluck.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: smartsearch-ai
 * Domain Path: /languages
 *
 * @package SmartSearch_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SSAI_VERSION', '1.0.2' );
define( 'SSAI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SSAI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SSAI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SSAI_PLUGIN_FILE', __FILE__ );

/**
 * Main SmartSearch AI plugin class.
 *
 * @since 1.0.0
 */
final class SmartSearch_AI {

    /**
     * Plugin instance.
     *
     * @var SmartSearch_AI|null
     */
    private static $instance = null;

    /**
     * Get single instance.
     *
     * @return SmartSearch_AI
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files.
     */
    private function includes() {
        require_once SSAI_PLUGIN_DIR . 'includes/class-ssai-dictionary.php';
        require_once SSAI_PLUGIN_DIR . 'includes/class-ssai-search-engine.php';
        require_once SSAI_PLUGIN_DIR . 'includes/class-ssai-ajax.php';
        require_once SSAI_PLUGIN_DIR . 'includes/class-ssai-shortcodes.php';
        require_once SSAI_PLUGIN_DIR . 'includes/class-ssai-openai.php';
        require_once SSAI_PLUGIN_DIR . 'includes/class-ssai-pro-features.php';

        if ( is_admin() ) {
            require_once SSAI_PLUGIN_DIR . 'admin/class-ssai-admin.php';
        }
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'init', array( $this, 'load_textdomain' ) );

        register_activation_hook( SSAI_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( SSAI_PLUGIN_FILE, array( $this, 'deactivate' ) );
    }

    /**
     * Load plugin textdomain.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'smartsearch-ai', false, dirname( SSAI_PLUGIN_BASENAME ) . '/languages' );
    }

    /**
     * Enqueue frontend assets.
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'ssai-search',
            SSAI_PLUGIN_URL . 'assets/css/search.css',
            array(),
            SSAI_VERSION
        );

        wp_enqueue_script(
            'ssai-fuse',
            SSAI_PLUGIN_URL . 'assets/js/fuse.min.js',
            array(),
            '7.0.0',
            true
        );

        wp_enqueue_script(
            'ssai-search',
            SSAI_PLUGIN_URL . 'assets/js/search.js',
            array( 'jquery', 'ssai-fuse' ),
            SSAI_VERSION,
            true
        );

        $options = get_option( 'ssai_options', array() );
        wp_localize_script( 'ssai-search', 'ssaiConfig', array(
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'ssai_search_nonce' ),
            'minChars'       => isset( $options['min_chars'] ) ? intval( $options['min_chars'] ) : 2,
            'maxSuggestions' => isset( $options['max_suggestions'] ) ? intval( $options['max_suggestions'] ) : 8,
            'placeholder'    => isset( $options['placeholder'] ) ? $options['placeholder'] : __( 'Describe what you need help with...', 'smartsearch-ai' ),
            'noResultsText'  => isset( $options['no_results_text'] ) ? $options['no_results_text'] : __( 'No matching services found. Try describing your problem differently.', 'smartsearch-ai' ),
            'searchingText'  => __( 'Searching...', 'smartsearch-ai' ),
            'exampleQueries' => array(
                __( 'my toilet won\'t stop running', 'smartsearch-ai' ),
                __( 'no hot water', 'smartsearch-ai' ),
                __( 'pipe is leaking', 'smartsearch-ai' ),
                __( 'drain is clogged', 'smartsearch-ai' ),
                __( 'need emergency plumber', 'smartsearch-ai' ),
            ),
            'aiEnabled'      => ! empty( $options['openai_api_key'] ) && ! empty( $options['ai_fallback'] ),
            'debounceMs'     => 200,
            'isPro'          => self::is_pro(),
        ) );
    }

    /**
     * Plugin activation.
     */
    public function activate() {
        $dictionary = new SSAI_Dictionary();
        $dictionary->maybe_install_defaults();

        $defaults = array(
            'min_chars'         => 2,
            'max_suggestions'   => 8,
            'placeholder'       => 'Describe what you need help with...',
            'no_results_text'   => 'No matching services found. Try describing your problem differently.',
            'openai_api_key'    => '',
            'openai_model'      => 'gpt-4o-mini',
            'ai_fallback'       => false,
            'post_types'        => array( 'post', 'page' ),
            'taxonomy'          => '',
            'location_taxonomy' => '',
            'results_page'      => '',
            'active_dictionary' => 'home-services',
        );

        if ( ! get_option( 'ssai_options' ) ) {
            add_option( 'ssai_options', $defaults );
        }

        // Track install date for review prompt
        if ( ! get_option( 'ssai_installed_at' ) ) {
            add_option( 'ssai_installed_at', time() );
        }

        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Check if Pro version is active.
     *
     * @return bool
     */
    public static function is_pro() {
        return apply_filters( 'ssai_is_pro', false );
    }

    /**
     * Get pro upgrade URL.
     *
     * @param string $utm_source
     * @return string
     */
    public static function pro_url( $utm_source = 'plugin' ) {
        return add_query_arg( array(
            'utm_source'   => $utm_source,
            'utm_medium'   => 'plugin',
            'utm_campaign' => 'upgrade',
        ), 'https://timtesluck.com/smartsearch-ai/pro/' );
    }
}

/**
 * Returns the main SmartSearch AI instance.
 *
 * @since  1.0.0
 * @return SmartSearch_AI
 */
function smartsearch_ai() {
    return SmartSearch_AI::instance();
}
add_action( 'plugins_loaded', 'smartsearch_ai' );
