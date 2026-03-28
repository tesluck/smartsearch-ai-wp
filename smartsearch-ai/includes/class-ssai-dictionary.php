<?php
/**
 * Dictionary manager — loads, saves, and queries the synonym/intent dictionaries.
 *
 * Dictionary format (JSON):
 * {
 *   "meta": { "industry": "home-services", "version": "1.0.0" },
 *   "services": [
 *     {
 *       "id": "pipe-repair",
 *       "name": "Pipe Repair",
 *       "category": "Plumbing",
 *       "synonyms": ["pipe fix", "broken pipe"],
 *       "intents": ["water leaking from wall", "pipe froze and burst"],
 *       "keywords": ["pipe", "leak", "burst"]
 *     }
 *   ]
 * }
 *
 * @package SmartSearch_AI
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SSAI_Dictionary {

    const OPTION_KEY        = 'ssai_dictionary';
    const CUSTOM_OPTION_KEY = 'ssai_dictionary_custom';

    /**
     * Get the full merged dictionary (defaults + custom entries).
     *
     * @return array
     */
    public function get_dictionary() {
        $base   = $this->load_dictionary_file();
        $custom = get_option( self::CUSTOM_OPTION_KEY, array() );

        if ( ! empty( $custom ) && isset( $custom['services'] ) ) {
            $base['services'] = array_merge( $base['services'], $custom['services'] );
        }

        return apply_filters( 'ssai_dictionary', $base );
    }

    /**
     * Get a flat array of all searchable terms mapped to service IDs.
     * Sent to frontend for Fuse.js matching.
     *
     * @return array [ { term, service_id, service_name, category, type } ]
     */
    public function get_search_index() {
        $dictionary = $this->get_dictionary();
        $index      = array();

        if ( empty( $dictionary['services'] ) ) {
            return $index;
        }

        foreach ( $dictionary['services'] as $service ) {
            $base = array(
                'service_id'   => $service['id'],
                'service_name' => $service['name'],
                'category'     => isset( $service['category'] ) ? $service['category'] : '',
            );

            // Service name
            $index[] = array_merge( $base, array( 'term' => $service['name'], 'type' => 'name' ) );

            // Synonyms
            if ( ! empty( $service['synonyms'] ) ) {
                foreach ( $service['synonyms'] as $synonym ) {
                    $index[] = array_merge( $base, array( 'term' => $synonym, 'type' => 'synonym' ) );
                }
            }

            // Intents
            if ( ! empty( $service['intents'] ) ) {
                foreach ( $service['intents'] as $intent ) {
                    $index[] = array_merge( $base, array( 'term' => $intent, 'type' => 'intent' ) );
                }
            }

            // Keywords
            if ( ! empty( $service['keywords'] ) ) {
                foreach ( $service['keywords'] as $keyword ) {
                    $index[] = array_merge( $base, array( 'term' => $keyword, 'type' => 'keyword' ) );
                }
            }
        }

        return apply_filters( 'ssai_search_index', $index );
    }

    /**
     * Server-side search through the dictionary.
     *
     * @param string $query User's search query.
     * @param int    $limit Max results.
     * @return array Matched services with scores.
     */
    public function search( $query, $limit = 10 ) {
        $dictionary  = $this->get_dictionary();
        $query_lower = strtolower( trim( $query ) );
        $query_words = preg_split( '/\s+/', $query_lower );
        $results     = array();

        if ( empty( $dictionary['services'] ) || empty( $query_lower ) ) {
            return $results;
        }

        foreach ( $dictionary['services'] as $service ) {
            $score = $this->score_service( $service, $query_lower, $query_words );
            if ( $score > 0 ) {
                $results[] = array( 'service' => $service, 'score' => $score );
            }
        }

        usort( $results, function ( $a, $b ) {
            return $b['score'] <=> $a['score'];
        } );

        return array_slice( $results, 0, $limit );
    }

    /**
     * Score how well a service matches a query.
     *
     * @param array  $service
     * @param string $query_lower
     * @param array  $query_words
     * @return float
     */
    private function score_service( $service, $query_lower, $query_words ) {
        $score = 0;

        // Exact name match
        if ( strtolower( $service['name'] ) === $query_lower ) {
            return 100;
        }

        // Name contains query
        if ( strpos( strtolower( $service['name'] ), $query_lower ) !== false ) {
            $score += 50;
        }

        // Query contains name
        if ( strpos( $query_lower, strtolower( $service['name'] ) ) !== false ) {
            $score += 40;
        }

        // Synonyms — score best-matching synonym, not sum of all
        if ( ! empty( $service['synonyms'] ) ) {
            $best_syn_score = 0;
            foreach ( $service['synonyms'] as $synonym ) {
                $syn_score = 0;
                $syn_lower = strtolower( $synonym );
                if ( $syn_lower === $query_lower ) {
                    $best_syn_score = 90;
                    break;
                }
                if ( strpos( $syn_lower, $query_lower ) !== false || strpos( $query_lower, $syn_lower ) !== false ) {
                    $syn_score += 35;
                }
                $overlap = count( array_intersect( $query_words, preg_split( '/\s+/', $syn_lower ) ) );
                if ( $overlap > 0 ) {
                    $syn_score += $overlap * 10;
                }
                $best_syn_score = max( $best_syn_score, $syn_score );
            }
            $score += $best_syn_score;
        }

        // Intents — score best-matching intent, not sum of all
        if ( ! empty( $service['intents'] ) ) {
            $best_intent_score = 0;

            foreach ( $service['intents'] as $intent ) {
                $intent_score = 0;
                $intent_lower = strtolower( $intent );
                $intent_words = preg_split( '/\s+/', $intent_lower );

                if ( $intent_lower === $query_lower ) {
                    $best_intent_score = max( $best_intent_score, 85 );
                    break;
                }

                if ( strpos( $intent_lower, $query_lower ) !== false || strpos( $query_lower, $intent_lower ) !== false ) {
                    $intent_score += 30;
                }

                // Word overlap (with fuzzy matching for misspellings)
                $overlap_count = 0;
                foreach ( $query_words as $qw ) {
                    if ( in_array( $qw, $intent_words, true ) ) {
                        $overlap_count++;
                    } elseif ( strlen( $qw ) >= 4 ) {
                        foreach ( $intent_words as $iw ) {
                            if ( strlen( $iw ) >= 4 && levenshtein( $qw, $iw ) <= 2 ) {
                                $overlap_count++;
                                break;
                            }
                        }
                    }
                }
                if ( $overlap_count > 0 ) {
                    $intent_score += ( $overlap_count / count( $query_words ) ) * 25;
                }

                // Bigram matching
                $query_bigrams  = $this->get_bigrams( $query_lower );
                $intent_bigrams = $this->get_bigrams( $intent_lower );
                $bigram_overlap = count( array_intersect( $query_bigrams, $intent_bigrams ) );
                if ( $bigram_overlap > 0 ) {
                    $intent_score += $bigram_overlap * 8;
                }

                $best_intent_score = max( $best_intent_score, $intent_score );
            }

            $score += $best_intent_score;
        }

        // Keywords (with fuzzy matching)
        if ( ! empty( $service['keywords'] ) ) {
            foreach ( $service['keywords'] as $keyword ) {
                $kw_lower = strtolower( $keyword );
                $kw_words = preg_split( '/\s+/', $kw_lower );
                foreach ( $query_words as $word ) {
                    if ( $word === $kw_lower ) {
                        $score += 15;
                    } elseif ( strpos( $word, $kw_lower ) !== false || strpos( $kw_lower, $word ) !== false ) {
                        $score += 5;
                    } else {
                        // Fuzzy match: check each keyword word
                        foreach ( $kw_words as $kw_word ) {
                            if ( strlen( $word ) >= 4 && strlen( $kw_word ) >= 4 ) {
                                $dist = levenshtein( $word, $kw_word );
                                if ( $dist <= 2 ) {
                                    $score += ( $dist === 1 ) ? 12 : 8;
                                    break;
                                }
                                if ( soundex( $word ) === soundex( $kw_word ) ) {
                                    $score += 10;
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        // Synonyms fuzzy matching (catch misspellings of service-specific terms)
        if ( ! empty( $service['synonyms'] ) ) {
            foreach ( $service['synonyms'] as $synonym ) {
                $syn_words = preg_split( '/\s+/', strtolower( $synonym ) );
                foreach ( $query_words as $word ) {
                    if ( strlen( $word ) >= 4 ) {
                        foreach ( $syn_words as $syn_word ) {
                            if ( strlen( $syn_word ) >= 4 ) {
                                $dist = levenshtein( $word, $syn_word );
                                if ( $dist > 0 && $dist <= 2 ) {
                                    $score += ( $dist === 1 ) ? 20 : 12;
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }

        // Category match
        if ( ! empty( $service['category'] ) && strpos( $query_lower, strtolower( $service['category'] ) ) !== false ) {
            $score += 20;
        }

        return $score;
    }

    /**
     * Generate bigrams from a string.
     *
     * @param string $string
     * @return array
     */
    private function get_bigrams( $string ) {
        $words   = preg_split( '/\s+/', $string );
        $bigrams = array();
        for ( $i = 0; $i < count( $words ) - 1; $i++ ) {
            $bigrams[] = $words[ $i ] . ' ' . $words[ $i + 1 ];
        }
        return $bigrams;
    }

    /**
     * Load dictionary from JSON file.
     *
     * @return array
     */
    public function load_dictionary_file() {
        $options     = get_option( 'ssai_options', array() );
        $active_dict = isset( $options['active_dictionary'] ) ? $options['active_dictionary'] : 'home-services';

        $file = SSAI_PLUGIN_DIR . 'dictionaries/' . sanitize_file_name( $active_dict ) . '.json';

        if ( ! file_exists( $file ) ) {
            $file = SSAI_PLUGIN_DIR . 'dictionaries/home-services.json';
        }

        if ( ! file_exists( $file ) ) {
            return array( 'meta' => array(), 'services' => array() );
        }

        $json = file_get_contents( $file );
        $data = json_decode( $json, true );

        return is_array( $data ) ? $data : array( 'meta' => array(), 'services' => array() );
    }

    /**
     * Install default dictionary on activation.
     */
    public function maybe_install_defaults() {
        $file = SSAI_PLUGIN_DIR . 'dictionaries/home-services.json';
        if ( ! file_exists( $file ) ) {
            error_log( 'SmartSearch AI: Default dictionary file missing at ' . $file );
        }
    }

    /**
     * Save custom dictionary entries.
     *
     * @param array $entries
     * @return bool
     */
    public function save_custom_entries( $entries ) {
        delete_transient( 'ssai_search_index' );
        return update_option( self::CUSTOM_OPTION_KEY, $entries );
    }

    /**
     * Get available dictionary files.
     *
     * @return array
     */
    public function get_available_dictionaries() {
        $dir         = SSAI_PLUGIN_DIR . 'dictionaries/';
        $files       = glob( $dir . '*.json' );
        $dictionaries = array();

        foreach ( $files as $file ) {
            $json = file_get_contents( $file );
            $data = json_decode( $json, true );
            $name = basename( $file, '.json' );

            $dictionaries[ $name ] = array(
                'name'     => $name,
                'label'    => isset( $data['meta']['industry'] ) ? $data['meta']['industry'] : ucwords( str_replace( '-', ' ', $name ) ),
                'version'  => isset( $data['meta']['version'] ) ? $data['meta']['version'] : '1.0.0',
                'services' => isset( $data['services'] ) ? count( $data['services'] ) : 0,
            );
        }

        return $dictionaries;
    }

    /**
     * Export the full merged dictionary as JSON.
     *
     * @return string
     */
    public function export_json() {
        return wp_json_encode( $this->get_dictionary(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    }

    /**
     * Import a dictionary from JSON string.
     *
     * @param string $json_string
     * @param string $filename
     * @return true|WP_Error
     */
    public function import_json( $json_string, $filename = 'custom' ) {
        $data = json_decode( $json_string, true );

        if ( ! is_array( $data ) || empty( $data['services'] ) ) {
            return new WP_Error( 'invalid_json', __( 'Invalid dictionary format. Must contain a "services" array.', 'smartsearch-ai' ) );
        }

        $file    = SSAI_PLUGIN_DIR . 'dictionaries/' . sanitize_file_name( $filename ) . '.json';
        $written = file_put_contents( $file, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

        if ( false === $written ) {
            return new WP_Error( 'write_error', __( 'Could not write dictionary file.', 'smartsearch-ai' ) );
        }

        delete_transient( 'ssai_search_index' );
        return true;
    }
}
