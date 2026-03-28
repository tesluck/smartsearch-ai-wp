<?php
/**
 * OpenAI integration for AI-powered query interpretation.
 *
 * Pro feature — only active when:
 * 1. Dictionary search returns no results
 * 2. AI fallback is enabled in settings
 * 3. OpenAI API key is configured
 * 4. Pro license is valid
 *
 * @package SmartSearch_AI
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SSAI_OpenAI {

    /**
     * Interpret a natural language query using OpenAI.
     *
     * @param string $query User's search query.
     * @return array|WP_Error
     */
    public function interpret_query( $query ) {
        $options = get_option( 'ssai_options', array() );
        $api_key = isset( $options['openai_api_key'] ) ? $options['openai_api_key'] : '';
        $model   = isset( $options['openai_model'] ) ? $options['openai_model'] : 'gpt-4o-mini';

        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', __( 'OpenAI API key not configured.', 'smartsearch-ai' ) );
        }

        // Build context from dictionary
        $dictionary   = new SSAI_Dictionary();
        $dict_data    = $dictionary->get_dictionary();
        $service_list = array();

        if ( ! empty( $dict_data['services'] ) ) {
            foreach ( $dict_data['services'] as $service ) {
                $cat = isset( $service['category'] ) ? $service['category'] : 'General';
                $service_list[] = $service['name'] . ' (' . $cat . ')';
            }
        }

        $services_context = implode( ', ', $service_list );

        $system_prompt = "You are a search query interpreter for a service directory website. Understand what service a user needs based on their natural language description.

Available services: {$services_context}

Respond with JSON only:
{\"service_names\": [\"Service 1\", \"Service 2\"], \"categories\": [\"Category\"], \"confidence\": 0.9, \"interpretation\": \"Brief explanation\"}";

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( array(
                'model'       => $model,
                'messages'    => array(
                    array( 'role' => 'system', 'content' => $system_prompt ),
                    array( 'role' => 'user', 'content' => $query ),
                ),
                'temperature' => 0.1,
                'max_tokens'  => 200,
            ) ),
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code !== 200 ) {
            return new WP_Error( 'api_error', sprintf(
                __( 'OpenAI API returned status %d', 'smartsearch-ai' ),
                $status_code
            ) );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['choices'][0]['message']['content'] ) ) {
            return new WP_Error( 'empty_response', __( 'OpenAI returned an empty response.', 'smartsearch-ai' ) );
        }

        $parsed = json_decode( $body['choices'][0]['message']['content'], true );

        if ( ! is_array( $parsed ) || empty( $parsed['service_names'] ) ) {
            return new WP_Error( 'parse_error', __( 'Could not parse OpenAI response.', 'smartsearch-ai' ) );
        }

        // Map back to dictionary services
        $matched_services = array();
        if ( ! empty( $dict_data['services'] ) ) {
            foreach ( $dict_data['services'] as $service ) {
                foreach ( $parsed['service_names'] as $name ) {
                    if ( strtolower( $service['name'] ) === strtolower( $name ) ) {
                        $matched_services[] = $service;
                    }
                }
            }
        }

        return array(
            'service_names'  => $parsed['service_names'],
            'categories'     => isset( $parsed['categories'] ) ? $parsed['categories'] : array(),
            'services'       => $matched_services,
            'confidence'     => isset( $parsed['confidence'] ) ? $parsed['confidence'] : 0,
            'interpretation' => isset( $parsed['interpretation'] ) ? $parsed['interpretation'] : '',
        );
    }

    /**
     * Test the API connection.
     *
     * @return true|WP_Error
     */
    public function test_connection() {
        $options = get_option( 'ssai_options', array() );
        $api_key = isset( $options['openai_api_key'] ) ? $options['openai_api_key'] : '';

        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', __( 'No API key configured.', 'smartsearch-ai' ) );
        }

        $response = wp_remote_get( 'https://api.openai.com/v1/models', array(
            'headers' => array( 'Authorization' => 'Bearer ' . $api_key ),
            'timeout' => 5,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return wp_remote_retrieve_response_code( $response ) === 200
            ? true
            : new WP_Error( 'auth_failed', __( 'API key is invalid or expired.', 'smartsearch-ai' ) );
    }
}
