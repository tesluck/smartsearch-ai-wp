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

    /** @var array|null Search context stored during pre_get_posts for use in wp_footer. */
    private static $search_context = null;

    public function __construct() {
        add_action( 'wp_ajax_ssai_autocomplete', array( $this, 'autocomplete' ) );
        add_action( 'wp_ajax_nopriv_ssai_autocomplete', array( $this, 'autocomplete' ) );

        add_action( 'wp_ajax_ssai_search', array( $this, 'search' ) );
        add_action( 'wp_ajax_nopriv_ssai_search', array( $this, 'search' ) );

        add_action( 'wp_ajax_ssai_get_index', array( $this, 'get_index' ) );
        add_action( 'wp_ajax_nopriv_ssai_get_index', array( $this, 'get_index' ) );

        // Override theme live_search with SmartSearch AI results (priority 0 = before theme handler)
        add_action( 'wp_ajax_live_search', array( $this, 'theme_live_search' ), 0 );
        add_action( 'wp_ajax_nopriv_live_search', array( $this, 'theme_live_search' ), 0 );

        // Intercept WordPress search queries to use dictionary matching (priority 20 to run after theme hooks)
        add_action( 'pre_get_posts', array( $this, 'intercept_search_query' ), 20 );

        // Output search context data on search pages for JS to read
        add_action( 'wp_footer', array( $this, 'output_search_context' ), 5 );
    }

    /**
     * Return autocomplete suggestions.
     */
    public function autocomplete() {
        check_ajax_referer( 'ssai_search_nonce', 'nonce' );

        $query = isset( $_REQUEST['query'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['query'] ) ) : '';

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

        $query    = isset( $_REQUEST['query'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['query'] ) ) : '';
        $location = isset( $_REQUEST['location'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['location'] ) ) : '';
        $limit    = isset( $_REQUEST['limit'] ) ? min( intval( $_REQUEST['limit'] ), 50 ) : 10;

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

    /**
     * Intercept the main WordPress search query and rewrite the search term
     * using SmartSearch AI dictionary matching.
     *
     * @param WP_Query $query The main query object.
     */
    public function intercept_search_query( $query ) {
        if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
            return;
        }

        $search_term = $query->get( 's' );
        if ( empty( $search_term ) ) {
            return;
        }

        // Prevent running more than once
        if ( ! empty( $query->get( 'ssai_processed' ) ) ) {
            return;
        }
        $query->set( 'ssai_processed', true );

        $dictionary   = new SSAI_Dictionary();
        $dict_results = $dictionary->search( $search_term, 5 );

        // Store context for the search results page banner (even if no match)
        $matched = array();
        $confidence = 'none';

        if ( ! empty( $dict_results ) ) {
            // Get unique matched service names
            $service_names = array();
            $seen          = array();
            foreach ( $dict_results as $result ) {
                $name = $result['service']['name'];
                if ( ! isset( $seen[ $name ] ) ) {
                    $service_names[] = $name;
                    $matched[] = array(
                        'name'  => $name,
                        'score' => round( $result['score'] ),
                    );
                    $seen[ $name ] = true;
                }
            }

            $top_score  = $dict_results[0]['score'];
            $confidence = $top_score >= 100 ? 'high' : ( $top_score >= 50 ? 'medium' : 'low' );

            // Keep s set to top match (prevents redirect loop, shows in breadcrumb)
            $query->set( 's', $service_names[0] );
            $query->set( 'post_type', 'company' );

            // Add subtrade taxonomy query to find companies offering these services
            $query->set( 'tax_query', array(
                array(
                    'taxonomy' => 'subtrade',
                    'field'    => 'name',
                    'terms'    => array_slice( $service_names, 0, 3 ),
                ),
            ) );

            // Suppress the keyword search SQL so only tax_query matters
            add_filter( 'posts_search', array( $this, 'suppress_search_sql' ), 10, 2 );
        }

        // Store context for the banner
        self::$search_context = array(
            'original_query'   => $search_term,
            'matched_services' => array_slice( $matched, 0, 3 ),
            'confidence'       => $confidence,
        );
    }

    /**
     * Remove the keyword search WHERE clause when we're using taxonomy matching.
     */
    public function suppress_search_sql( $search, $query ) {
        if ( ! empty( $query->get( 'ssai_processed' ) ) ) {
            remove_filter( 'posts_search', array( $this, 'suppress_search_sql' ), 10 );
            return '';
        }
        return $search;
    }

    /**
     * Output search context as a hidden JSON div for JS to read on search pages.
     */
    public function output_search_context() {
        if ( ! is_search() || null === self::$search_context ) {
            return;
        }

        echo '<div id="ssai-search-context" style="display:none;" data-context="'
            . esc_attr( wp_json_encode( self::$search_context ) )
            . '"></div>';
    }

    /**
     * Override theme's live_search AJAX action with SmartSearch AI results.
     * Returns HTML links matching the theme's expected format.
     */
    public function theme_live_search() {
        $term = isset( $_REQUEST['term'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['term'] ) ) : '';

        if ( strlen( $term ) < 2 ) {
            wp_die();
        }

        $engine  = new SSAI_Search_Engine();
        $results = $engine->search( $term, '', 10 );

        if ( ! empty( $results['posts'] ) ) {
            foreach ( $results['posts'] as $post ) {
                echo '<a href="' . esc_url( $post['url'] ) . '">' . esc_html( $post['title'] ) . '</a>';
            }
        } else {
            echo '<span style="display:block;padding:10px;">Nothing found</span>';
        }

        wp_die();
    }
}

new SSAI_Ajax();
