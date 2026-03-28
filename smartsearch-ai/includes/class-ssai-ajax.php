<?php
/**
 * AJAX handlers for frontend search requests.
 *
 * @package SmartSearch_AI
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SSAI_Ajax {

    public function __construct() {
        add_action( 'wp_ajax_ssai_autocomplete', array( $this, 'autocomplete' ) );
        add_action( 'wp_ajax_nopriv_ssai_autocomplete', array( $this, 'autocomplete' ) );

        add_action( 'wp_ajax_ssai_search', array( $this, 'search' ) );
        add_action( 'wp_ajax_nopriv_ssai_search', array( $this, 'search' ) );

        add_action( 'wp_ajax_ssai_get_index', array( $this, 'get_index' ) );
        add_action( 'wp_ajax_nopriv_ssai_get_index', array( $this, 'get_index' ) );
    }

    /**
     * Return autocomplete suggestions.
     */
    public function autocomplete() {
        check_ajax_referer( 'ssai_search_nonce', 'nonce' );

        $query = isset( $_GET['query'] ) ? sanitize_text_field( wp_unslash( $_GET['query'] ) ) : '';

        if ( strlen( $query ) < 2 ) {
            wp_send_json_success( array( 'suggestions' => array() ) );
        }

        $cache_key = 'ssai_ac_' . md5( $query );
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) {
            wp_send_json_success( array( 'suggestions' => $cached ) );
        }

        $engine      = new SSAI_Search_Engine();
        $suggestions = $engine->autocomplete( $query );

        set_transient( $cache_key, $suggestions, 5 * MINUTE_IN_SECONDS );
        wp_send_json_success( array( 'suggestions' => $suggestions ) );
    }

    /**
     * Perform full search.
     */
    public function search() {
        check_ajax_referer( 'ssai_search_nonce', 'nonce' );

        $query    = isset( $_GET['query'] ) ? sanitize_text_field( wp_unslash( $_GET['query'] ) ) : '';
        $location = isset( $_GET['location'] ) ? sanitize_text_field( wp_unslash( $_GET['location'] ) ) : '';
        $limit    = isset( $_GET['limit'] ) ? min( intval( $_GET['limit'] ), 50 ) : 10;

        if ( empty( $query ) ) {
            wp_send_json_error( array( 'message' => __( 'Search query is required.', 'smartsearch-ai' ) ) );
        }

        $engine  = new SSAI_Search_Engine();
        $results = $engine->search( $query, $location, $limit );

        wp_send_json_success( $results );
    }

    /**
     * Return full search index for client-side Fuse.js matching.
     */
    public function get_index() {
        check_ajax_referer( 'ssai_search_nonce', 'nonce' );

        $cache_key = 'ssai_search_index';
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            wp_send_json_success( array( 'index' => $cached ) );
        }

        $dictionary = new SSAI_Dictionary();
        $index      = $dictionary->get_search_index();

        set_transient( $cache_key, $index, HOUR_IN_SECONDS );
        wp_send_json_success( array( 'index' => $index ) );
    }
}

new SSAI_Ajax();
