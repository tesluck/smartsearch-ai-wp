<?php
/**
 * Search Engine — orchestrates dictionary search + WordPress post queries + AI fallback.
 *
 * @package SmartSearch_AI
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SSAI_Search_Engine {

    /** @var SSAI_Dictionary */
    private $dictionary;

    /** @var SSAI_OpenAI */
    private $openai;

    public function __construct() {
        $this->dictionary = new SSAI_Dictionary();
        $this->openai     = new SSAI_OpenAI();
    }

    /**
     * Perform a full search: dictionary → WP query → optional AI fallback.
     *
     * @param string $query    Raw user query.
     * @param string $location Optional location/city filter.
     * @param int    $limit    Max results.
     * @return array
     */
    public function search( $query, $location = '', $limit = 10 ) {
        $query    = sanitize_text_field( $query );
        $location = sanitize_text_field( $location );

        // Step 1: Dictionary match
        $dict_results = $this->dictionary->search( $query, $limit );

        if ( ! empty( $dict_results ) ) {
            $service_names = wp_list_pluck( array_column( $dict_results, 'service' ), 'name' );
            $categories    = array_unique( wp_list_pluck( array_column( $dict_results, 'service' ), 'category' ) );

            $posts = $this->query_posts( $service_names, $categories, $location, $limit );

            // Build matched_services with scores (deduplicated, top 3)
            $matched_services = $this->build_matched_services( $dict_results, 3 );
            $top_score        = $dict_results[0]['score'];
            $confidence       = $this->score_to_confidence( $top_score );

            // Track search for analytics (Pro)
            do_action( 'ssai_search_performed', $query, 'dictionary', $dict_results );

            return array(
                'matched_services' => $matched_services,
                'posts'            => $posts,
                'source'           => 'dictionary',
                'interpreted'      => sprintf( __( 'Showing results for: %s', 'smartsearch-ai' ), implode( ', ', array_slice( $service_names, 0, 3 ) ) ),
                'query'            => $query,
                'original_query'   => $query,
                'confidence'       => $confidence,
            );
        }

        // Step 2: AI fallback if enabled (Pro feature)
        $options = get_option( 'ssai_options', array() );
        if ( ! empty( $options['ai_fallback'] ) && ! empty( $options['openai_api_key'] ) && SSAI_Pro_Features::has_feature( 'ai_fallback' ) ) {
            $ai_result = $this->openai->interpret_query( $query );

            if ( ! is_wp_error( $ai_result ) && ! empty( $ai_result['service_names'] ) ) {
                $posts = $this->query_posts( $ai_result['service_names'], $ai_result['categories'], $location, $limit );

                do_action( 'ssai_search_performed', $query, 'ai', $ai_result );

                return array(
                    'services'    => $ai_result['services'],
                    'posts'       => $posts,
                    'source'      => 'ai',
                    'interpreted' => sprintf( __( 'AI matched: %s', 'smartsearch-ai' ), implode( ', ', $ai_result['service_names'] ) ),
                    'query'       => $query,
                );
            }
        }

        // Step 3: Basic WordPress keyword search
        $posts = $this->keyword_search( $query, $location, $limit );

        do_action( 'ssai_search_performed', $query, 'keyword', array() );

        return array(
            'matched_services' => array(),
            'posts'            => $posts,
            'source'           => 'keyword',
            'interpreted'      => sprintf( __( 'Keyword search: %s', 'smartsearch-ai' ), $query ),
            'query'            => $query,
            'original_query'   => $query,
            'confidence'       => 'none',
        );
    }

    /**
     * Autocomplete suggestions.
     *
     * @param string $query Partial user input.
     * @param int    $limit Max suggestions.
     * @return array
     */
    public function autocomplete( $query, $limit = 8 ) {
        $dict_results = $this->dictionary->search( $query, $limit );
        $suggestions  = array();
        $seen         = array();

        foreach ( $dict_results as $result ) {
            $service = $result['service'];
            if ( isset( $seen[ $service['id'] ] ) ) {
                continue;
            }
            $seen[ $service['id'] ] = true;

            $suggestions[] = array(
                'id'       => $service['id'],
                'name'     => $service['name'],
                'category' => isset( $service['category'] ) ? $service['category'] : '',
                'score'    => $result['score'],
            );
        }

        return $suggestions;
    }

    /**
     * Query WordPress posts by service names / categories / location.
     *
     * @param array  $service_names
     * @param array  $categories
     * @param string $location
     * @param int    $limit
     * @return array
     */
    private function query_posts( $service_names, $categories, $location, $limit ) {
        $options    = get_option( 'ssai_options', array() );
        $post_types = ! empty( $options['post_types'] ) ? $options['post_types'] : array( 'post', 'page' );

        $args = array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            's'              => $service_names[0],
        );

        $tax_query = array();

        if ( ! empty( $options['taxonomy'] ) && ! empty( $categories ) ) {
            $tax_query[] = array(
                'taxonomy' => $options['taxonomy'],
                'field'    => 'name',
                'terms'    => $categories,
            );
        }

        if ( ! empty( $options['location_taxonomy'] ) && ! empty( $location ) ) {
            $tax_query[] = array(
                'taxonomy' => $options['location_taxonomy'],
                'field'    => 'name',
                'terms'    => array( $location ),
            );
        }

        if ( ! empty( $tax_query ) ) {
            if ( count( $tax_query ) > 1 ) {
                $tax_query['relation'] = 'AND';
            }
            $args['tax_query'] = $tax_query;
        }

        $args      = apply_filters( 'ssai_query_args', $args, $service_names, $categories, $location );
        $query_obj = new WP_Query( $args );
        $posts     = array();

        if ( $query_obj->have_posts() ) {
            while ( $query_obj->have_posts() ) {
                $query_obj->the_post();
                $post_id = get_the_ID();
                $posts[] = array(
                    'id'        => $post_id,
                    'title'     => get_the_title(),
                    'url'       => get_permalink(),
                    'excerpt'   => get_the_excerpt(),
                    'thumbnail' => get_the_post_thumbnail_url( $post_id, 'thumbnail' ),
                );
            }
            wp_reset_postdata();
        }

        return $posts;
    }

    /**
     * Build deduplicated matched_services array from dictionary results.
     */
    private function build_matched_services( $dict_results, $max = 3 ) {
        $services = array();
        $seen     = array();

        foreach ( $dict_results as $result ) {
            $service = $result['service'];
            if ( isset( $seen[ $service['id'] ] ) ) {
                continue;
            }
            $seen[ $service['id'] ] = true;

            $services[] = array(
                'name'     => $service['name'],
                'category' => isset( $service['category'] ) ? $service['category'] : '',
                'score'    => $result['score'],
            );

            if ( count( $services ) >= $max ) {
                break;
            }
        }

        return $services;
    }

    /**
     * Convert a numeric score to a confidence level.
     */
    private function score_to_confidence( $score ) {
        if ( $score >= 100 ) {
            return 'high';
        }
        if ( $score >= 50 ) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * Basic keyword search fallback.
     *
     * @param string $query
     * @param string $location
     * @param int    $limit
     * @return array
     */
    private function keyword_search( $query, $location, $limit ) {
        $search_query = $query . ( ! empty( $location ) ? ' ' . $location : '' );
        $options      = get_option( 'ssai_options', array() );
        $post_types   = ! empty( $options['post_types'] ) ? $options['post_types'] : array( 'post', 'page' );

        $query_obj = new WP_Query( array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            's'              => $search_query,
        ) );

        $posts = array();
        if ( $query_obj->have_posts() ) {
            while ( $query_obj->have_posts() ) {
                $query_obj->the_post();
                $post_id = get_the_ID();
                $posts[] = array(
                    'id'        => $post_id,
                    'title'     => get_the_title(),
                    'url'       => get_permalink(),
                    'excerpt'   => get_the_excerpt(),
                    'thumbnail' => get_the_post_thumbnail_url( $post_id, 'thumbnail' ),
                );
            }
            wp_reset_postdata();
        }

        return $posts;
    }
}
