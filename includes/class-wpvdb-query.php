<?php
namespace WPVDB;

defined('ABSPATH') || exit;

class Query {
    /**
     * Database handler
     *
     * @var Database
     */
    private static $database;

    /**
     * Initialize the database instance
     */
    private static function init_database() {
        if (null === self::$database) {
            self::$database = new Database();
        }
    }

    /**
     * Hook into 'pre_get_posts' or a similar filter to do custom vector searching if requested.
     */
    public static function init() {
        add_filter('pre_get_posts', [__CLASS__, 'maybe_vector_search']);
    }

    /**
     * If query->get('vdb_vector_query') is set, we do a custom vector-based search.
     * This is a demonstration: a real implementation would combine or replace the standard search logic.
     *
     * For example, a developer might do:
     * $query = new WP_Query(['vdb_vector_query' => 'some text', 'posts_per_page' => 10]);
     *
     * We'll find the top matching doc_ids from pivot table, then limit WP_Query to those posts.
     */
    public static function maybe_vector_search($query) {
        // Initialize database
        self::init_database();

        // Only run in front-end or REST contexts, and only if vdb_vector_query is set.
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }
        if (!$query->is_main_query()) {
            return;
        }
        $vdb_query = $query->get('vdb_vector_query');
        if (empty($vdb_query)) {
            return;
        }

        error_log('[WPVDB DEBUG] maybe_vector_search triggered with query: ' . $vdb_query);

        // For simplicity, embed and do a fallback search. Then get the doc_ids, presumably post_id was stored as doc_id.
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpvdb_embeddings';

        // We'll do a direct call to the REST method or replicate logic from REST::handle_query.
        $api_key = apply_filters('wpvdb_default_api_key', '');
        if (!$api_key) {
            // If there's no stored key, we can't generate embeddings. We skip.
            error_log('[WPVDB ERROR] No API key found, skipping vector search');
            return;
        }

        // Make sure we have a valid model name
        $model = Settings::get_default_model();
        if (empty($model)) {
            $model = 'text-embedding-3-small'; // Default fallback
        }
        error_log('[WPVDB DEBUG] Using embedding model: ' . $model);
        
        // Get API base URL with fallback
        $api_base = Settings::get_api_base();
        if (empty($api_base)) {
            $api_base = 'https://api.openai.com/v1/';
        }
        error_log('[WPVDB DEBUG] Using API base: ' . $api_base);

        try {
            $embedding_result = Core::get_embedding($vdb_query, $model, $api_base, $api_key);
            if (is_wp_error($embedding_result)) {
                error_log('[WPVDB ERROR] Error generating embedding: ' . $embedding_result->get_error_message());
                return; // skip
            }

            error_log('[WPVDB DEBUG] Embedding generated successfully, dimensions: ' . count($embedding_result));
            
            $embedding = $embedding_result;
            $has_vector = self::$database->has_native_vector_support();
            error_log('[WPVDB DEBUG] Vector support detected: ' . ($has_vector ? 'Yes' : 'No'));

            $limit = $query->get('posts_per_page') ?: 10;
            error_log('[WPVDB DEBUG] Posts per page limit: ' . $limit);

            $doc_ids = [];

            if ($has_vector) {
                try {
                    // Convert the embedding array to JSON
                    $embedding_json = json_encode($embedding);
                    
                    // Use Database class to get the appropriate vector function
                    $vector_function = self::$database->get_vector_from_string_function($embedding_json);
                    error_log('[WPVDB DEBUG] Using vector function: ' . $vector_function);
                    
                    // Use Database class to get the appropriate distance function
                    $distance_function = self::$database->get_vector_distance_function('embedding', $vector_function, 'cosine');
                    error_log('[WPVDB DEBUG] Using distance function: ' . $distance_function);
                    
                    // Optimized query that will use the vector index
                    // The ORDER BY + LIMIT pattern is what triggers the vector index usage
                    $sql = $wpdb->prepare("
                        SELECT doc_id,
                            $distance_function as distance
                        FROM $table_name
                        ORDER BY distance
                        LIMIT %d
                    ", $limit * 3 // fetch more candidates than needed
                    );
                    
                    error_log('[WPVDB DEBUG] Vector search SQL: ' . $sql);
                    
                    $rows = $wpdb->get_results($sql, ARRAY_A);
                    
                    if ($wpdb->last_error) {
                        error_log('[WPVDB ERROR] Database error in vector search: ' . $wpdb->last_error);
                    }
                    
                    if ($rows) {
                        error_log('[WPVDB DEBUG] Found ' . count($rows) . ' results from vector search');
                        foreach ($rows as $r) {
                            $doc_ids[] = (int) $r['doc_id'];
                            error_log('[WPVDB DEBUG] Added doc_id: ' . $r['doc_id'] . ' with distance: ' . $r['distance']);
                        }
                    } else {
                        error_log('[WPVDB DEBUG] No results found from vector search');
                    }
                } catch (\Exception $e) {
                    error_log('[WPVDB ERROR] Exception in vector search: ' . $e->getMessage());
                }
            } else {
                error_log('[WPVDB DEBUG] No vector support, using PHP fallback search');
                // Fallback: do in PHP
                $all_rows = $wpdb->get_results("SELECT doc_id, embedding FROM $table_name", ARRAY_A);
                $distances = [];
                foreach ($all_rows as $r) {
                    $stored_emb = json_decode($r['embedding'], true);
                    if (!is_array($stored_emb)) {
                        continue;
                    }
                    $d = REST::cosine_distance($embedding, $stored_emb);
                    $distances[] = [
                        'doc_id'   => (int) $r['doc_id'],
                        'distance' => $d,
                    ];
                }
                usort($distances, function($a, $b){return $a['distance'] <=> $b['distance'];});
                $distances = array_slice($distances, 0, $limit * 3);
                $doc_ids   = wp_list_pluck($distances, 'doc_id');
            }

            if (empty($doc_ids)) {
                // No matches, so force query to return no posts
                $query->set('post__in', [0]);
                return;
            }

            // If there are doc_ids, limit the WP query to only those
            $doc_ids = array_unique($doc_ids);
            $query->set('post__in', $doc_ids);
            $query->set('orderby', 'post__in');
        } catch (\Exception $e) {
            error_log('[WPVDB ERROR] Unhandled exception in maybe_vector_search: ' . $e->getMessage());
        }
    }

    /**
     * Modify SQL query to include vector search
     * 
     * @param string   $sql  SQL query string
     * @param WP_Query $query Query instance
     * @return string Modified SQL query
     */
    public function posts_request($sql, $query) {
        if (empty($query->query_vars['wpvdb_vector_query'])) {
            return $sql;
        }
        
        global $wpdb;
        
        // Get vector query from query vars
        $vector_query = $query->query_vars['wpvdb_vector_query'];
        
        // Get database handler
        $database = new Database();
        
        // Check if we have vector support
        $has_vector = $database->has_native_vector_support();
        
        // Get embedding table
        $embedding_table = $wpdb->prefix . 'wpvdb_embeddings';
        
        // Check if embedding table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$embedding_table'") === $embedding_table;
        
        if (!$table_exists) {
            return $sql;
        }
        
        return $sql;
    }
}