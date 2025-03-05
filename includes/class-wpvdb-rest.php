<?php
namespace WPVDB;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

class REST {

    /**
     * Registers custom REST routes under the namespace 'vdb/v1'.
     */
    public static function register_routes() {
        error_log('[WPVDB] Attempting to register REST routes');
        
        // Only register routes if current user has permission
        if (!self::default_permission_check()) {
            error_log('[WPVDB] Skipping route registration due to permission check failing');
            return;
        }
        
        error_log('[WPVDB] Permission check passed, registering routes');
        
        register_rest_route('vdb/v1', '/embed', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_embed'],
            'permission_callback' => [__CLASS__, 'default_permission_check'],
        ]);
        
        register_rest_route('vdb/v1', '/vectors', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_vectors'],
            'permission_callback' => [__CLASS__, 'default_permission_check'],
        ]);
        
        register_rest_route('vdb/v1', '/query', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_query'],
            'permission_callback' => [__CLASS__, 'default_permission_check'],
        ]);
        
        register_rest_route('vdb/v1', '/metadata', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'handle_metadata'],
            'permission_callback' => [__CLASS__, 'default_permission_check'],
        ]);
        
        // Add endpoint for the block editor to trigger embedding generation
        register_rest_route('wp/v2/wpvdb', '/reembed', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_reembed'],
            'permission_callback' => [__CLASS__, 'default_permission_check'],
        ]);
        
        error_log('[WPVDB] Successfully registered all REST routes');
    }

    /**
     * Basic permission check. By default, require 'edit_posts'.
     * If authentication is disabled in settings, allow public access.
     */
    public static function default_permission_check() {
        // Check if authentication is required (from individual option or from settings array)
        $require_auth = get_option('wpvdb_require_auth', 1);
        
        // Also check the settings array for compatibility with new format
        $settings = get_option('wpvdb_settings', []);
        if (isset($settings['require_auth'])) {
            $require_auth = $settings['require_auth'];
        }
        
        error_log('[WPVDB] Permission check - require_auth setting: ' . var_export($require_auth, true));
        
        if (empty($require_auth)) {
            // If authentication is disabled, allow public access
            error_log('[WPVDB] Authentication disabled, allowing public access');
            return true;
        }
        
        // If we're using application passwords, check that
        if (function_exists('wp_is_application_passwords_available') && wp_is_application_passwords_available()) {
            error_log('[WPVDB] Using application passwords for authentication');
            // Check for application password first
            if (current_user_can('edit_posts')) {
                error_log('[WPVDB] User has edit_posts capability, allowing access');
                return true;
            } else {
                // Return false explicitly to ensure proper error message
                error_log('[WPVDB] User does not have edit_posts capability, denying access');
                return new \WP_Error(
                    'rest_forbidden',
                    __('Authentication required via Application Password.', 'wpvdb'),
                    ['status' => 401]
                );
            }
        } else {
            // Fall back to regular capability check if application passwords aren't enabled
            $can_edit = current_user_can('edit_posts');
            error_log('[WPVDB] Regular capability check result: ' . var_export($can_edit, true));
            return $can_edit;
        }
    }

    /**
     * POST /vdb/v1/embed
     * 
     * Request format:
     * {
     *   "doc_id": 123,                        // Document ID (typically post ID)
     *   "text": "some long text to embed"     // Text content to chunk, summarize, and embed
     * }
     * 
     * Response format:
     * {
     *   "success": true,                     // Whether the operation was successful
     *   "doc_id": 123,                       // Document ID
     *   "count": 5,                          // Number of chunks created
     *   "chunks": [                          // Array of created chunks
     *     {
     *       "id": 456,                       // Database row ID
     *       "doc_id": 123,                   // Document ID
     *       "chunk_id": "chunk-0",           // Chunk identifier
     *       "chunk_content": "...",          // The text content of the chunk
     *       "summary": "..."                 // AI-generated summary if enabled
     *     },
     *     ...more chunks...
     *   ]
     * }
     * 
     * Notes:
     * - Text will be automatically chunked using filters
     * - Each chunk will be sent to the API for embedding generation
     * - Optional summarization if enabled in settings
     * - Embeddings are stored in the database using the insert_embedding_row method
     */
    public static function handle_embed(WP_REST_Request $request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpvdb_embeddings';

        $doc_id  = $request->get_param('doc_id');
        $text    = $request->get_param('text');
        
        // Get API key and model from admin settings instead of from the request
        $api_key = Settings::get_api_key();
        $model   = Settings::get_default_model();
        $api_base= Settings::get_api_base();

        if (!$text) {
            return new WP_Error('invalid_params', 'Missing required field: text.', ['status' => 400]);
        }
        
        if (empty($api_key)) {
            return new WP_Error('configuration_error', 'API key not configured. Please contact site administrator.', ['status' => 400]);
        }
        
        // Ensure text is a string
        if (!is_string($text)) {
            if (is_array($text) || is_object($text)) {
                $text = json_encode($text);
            } else {
                $text = strval($text);
            }
        }

        // Chunk the text
        $chunks = apply_filters('wpvdb_chunk_text', [], $text);
        if (!is_array($chunks) || empty($chunks)) {
            return new WP_Error('chunking_error', 'Failed to chunk text.', ['status' => 500]);
        }
        
        $inserted = [];

        foreach ($chunks as $index => $chunk) {
            // Skip null or empty chunks
            if ($chunk === null || $chunk === '') {
                continue;
            }
            
            // Summarize chunk if needed
            $summary = apply_filters('wpvdb_ai_summarize_chunk', '', $chunk);

            // Get embedding
            $embedding_result = Core::get_embedding($chunk, $model, $api_base, $api_key);
            if (is_wp_error($embedding_result)) {
                // We can log or partial fail. For now, let's just return the error.
                return $embedding_result;
            }

            // Insert into DB
            $res = self::insert_embedding_row($doc_id, 'chunk-' . $index, $chunk, $summary, $embedding_result);
            if (is_wp_error($res)) {
                return $res;
            }
            $inserted[] = $res;
        }

        return new WP_REST_Response([
            'success'  => true,
            'doc_id'   => $doc_id,
            'count'    => count($inserted),
            'chunks'   => $inserted,
        ], 200);
    }

    /**
     * POST /vdb/v1/vectors
     * 
     * Request format:
     * {
     *   "doc_id": 123,                                  // Document ID (typically post ID)
     *   "chunk_id": "some-chunk-id",                    // Chunk identifier (optional, defaults to "chunk-0")
     *   "chunk_content": "The text for this chunk",     // The text content of the chunk (optional)
     *   "embedding": [0.123, -0.456, ...],              // Pre-computed embedding vector array
     *   "summary": "Optional summary of the chunk"      // Summary of the chunk (optional)
     * }
     * 
     * Response format:
     * {
     *   "success": true,                                // Whether the operation was successful
     *   "row": {                                        // Information about the inserted row
     *     "id": 456,                                    // Database row ID
     *     "doc_id": 123,                                // Document ID
     *     "chunk_id": "some-chunk-id",                  // Chunk identifier
     *     "chunk_content": "The text for this chunk",   // The text content of the chunk
     *     "summary": "Optional summary of the chunk"    // Summary if provided
     *   }
     * }
     * 
     * Notes:
     * - Unlike the /embed endpoint, this accepts pre-computed embeddings
     * - Useful for client-side embedding generation or batch operations
     * - The embedding will be stored using native vector types if supported
     * - Otherwise, it will be stored as JSON in the database
     */
    public static function handle_vectors(WP_REST_Request $request) {
        $doc_id       = $request->get_param('doc_id');
        $chunk_id     = $request->get_param('chunk_id') ?: 'chunk-0';
        $chunk_content= $request->get_param('chunk_content') ?: '';
        $embedding    = $request->get_param('embedding');
        $summary      = $request->get_param('summary') ?: '';

        if (!$doc_id || !$embedding || !is_array($embedding)) {
            return new WP_Error('invalid_params', 'Missing or invalid doc_id or embedding.', ['status' => 400]);
        }

        $res = self::insert_embedding_row($doc_id, $chunk_id, $chunk_content, $summary, $embedding);
        if (is_wp_error($res)) {
            return $res;
        }

        return new WP_REST_Response([
            'success' => true,
            'row' => $res,
        ]);
    }

    /**
     * POST /vdb/v1/query
     * 
     * Request format:
     * {
     *   "query_text": "some text to embed and search with",    // Text query to find similar content (optional if embedding provided)
     *   "embedding": [0.123, -0.456, ...],                    // Pre-computed embedding vector array (optional if query_text provided)
     *   "k": 5                                                // Number of results to return (default: 5)
     * }
     * 
     * Response format:
     * [
     *   {
     *     "id": 123,                                          // Database row ID
     *     "doc_id": 456,                                      // Document ID (typically post ID)
     *     "chunk_id": "chunk-0",                              // Chunk identifier
     *     "chunk_content": "The text content of this chunk",  // The text content of the chunk
     *     "summary": "Optional summary of the chunk",         // Summary if available
     *     "distance": 0.123,                                  // Semantic distance (lower is more similar)
     *     "debug_info": {                                     // Debug information
     *       "database_type": "mysql",                         // Database type (mysql or mariadb)
     *       "has_vector_support": "yes"                       // Whether native vector support is available
     *     }
     *   },
     *   ...more results...
     * ]
     * 
     * Notes:
     * - Either query_text OR embedding must be provided
     * - If query_text is provided, a new embedding will be generated using the configured API
     * - If embedding is provided, it will be used directly for vector search
     * - Native vector operations are used if supported by the database
     * - Fallback to PHP-based cosine distance calculation otherwise
     */
    public static function handle_query(WP_REST_Request $request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpvdb_embeddings';

        error_log('[WPVDB DEBUG] handle_query REST endpoint called');

        $query_text = $request->get_param('query_text');
        $embedding  = $request->get_param('embedding');
        $k          = absint($request->get_param('k')) ?: 5;
        
        error_log('[WPVDB DEBUG] Query parameters - query_text: ' . (empty($query_text) ? 'empty' : substr($query_text, 0, 50) . '...') . 
                  ', embedding: ' . (empty($embedding) ? 'empty' : '[array]') . 
                  ', k: ' . $k);
        
        // Get API key and model from admin settings instead of from the request
        $api_key    = Settings::get_api_key();
        $model      = Settings::get_default_model();
        $api_base   = Settings::get_api_base();
        
        error_log('[WPVDB DEBUG] Using model: ' . $model . ', API base: ' . $api_base);

        // If no embedding is provided, we generate from query_text
        if (!$embedding && !$query_text) {
            error_log('[WPVDB ERROR] No query_text or embedding provided');
            return new WP_Error('invalid_params', 'Provide either "query_text" or "embedding".', ['status' => 400]);
        }
        if (!$embedding && empty($api_key)) {
            error_log('[WPVDB ERROR] No API key configured');
            return new WP_Error('configuration_error', 'API key not configured. Please contact site administrator.', ['status' => 400]);
        }

        try {
            if (!$embedding) {
                // Generate
                error_log('[WPVDB DEBUG] Generating embedding from query_text');
                $embedding_result = Core::get_embedding($query_text, $model, $api_base, $api_key);
                if (is_wp_error($embedding_result)) {
                    error_log('[WPVDB ERROR] Error generating embedding: ' . $embedding_result->get_error_message());
                    return $embedding_result;
                }
                $embedding = $embedding_result;
                error_log('[WPVDB DEBUG] Embedding generated successfully, dimensions: ' . count($embedding));
            }
    
            // Now we have an embedding array of floats. If we have native vector support, use it. Otherwise fallback.
            $has_vector = Database::has_native_vector_support();
            error_log('[WPVDB DEBUG] Vector support detected: ' . ($has_vector ? 'Yes' : 'No'));
            $results = [];
    
            if ($has_vector) {
                try {
                    // Convert the embedding array to a proper vector format
                    $embedding_json = json_encode($embedding);
                    
                    // Use Database class to get the appropriate vector function
                    $vector_function = Database::get_vector_from_string_function($embedding_json);
                    error_log('[WPVDB DEBUG] Using vector function: ' . $vector_function);
                    
                    // Use Database class to get the appropriate distance function
                    $distance_function = Database::get_vector_distance_function('embedding', $vector_function, 'cosine');
                    error_log('[WPVDB DEBUG] Using distance function: ' . $distance_function);
                    
                    // Optimized query that will use the vector index
                    // The ORDER BY + LIMIT pattern is what triggers the vector index usage
                    $sql = $wpdb->prepare("
                        SELECT 
                            id, doc_id, chunk_id, chunk_content, summary,
                            $distance_function AS distance
                        FROM $table_name
                        ORDER BY distance
                        LIMIT %d
                    ", $k);
                    
                    error_log('[WPVDB DEBUG] Vector query SQL: ' . $sql);
    
                    $rows = $wpdb->get_results($sql, ARRAY_A);
    
                    if (!empty($wpdb->last_error)) {
                        error_log('[WPVDB ERROR] Database error: ' . $wpdb->last_error);
                        
                        // Try a direct query without wpdb->prepare to see the exact SQL
                        $raw_sql = "SELECT 
                                id, doc_id, chunk_id, chunk_content, summary,
                                $distance_function AS distance
                            FROM $table_name
                            ORDER BY distance
                            LIMIT $k";
                            
                        error_log('[WPVDB DEBUG] Raw SQL query: ' . $raw_sql);
                        $raw_result = $wpdb->query($raw_sql);
                        
                        if ($raw_result === false) {
                            error_log('[WPVDB ERROR] Raw SQL also failed: ' . $wpdb->last_error);
                        } else {
                            error_log('[WPVDB DEBUG] Raw SQL succeeded, issue might be with wpdb->prepare');
                        }
                        
                        return new WP_Error('query_error', $wpdb->last_error, ['status' => 400]);
                    }
    
                    if ($rows) {
                        error_log('[WPVDB DEBUG] Found ' . count($rows) . ' results');
                    } else {
                        error_log('[WPVDB DEBUG] No results found');
                    }
                    
                    $results = $rows ?: [];
                } catch (\Exception $e) {
                    error_log('[WPVDB ERROR] Exception in vector query: ' . $e->getMessage());
                    return new WP_Error('query_error', 'Exception: ' . $e->getMessage(), ['status' => 500]);
                }
            } else {
                error_log('[WPVDB DEBUG] No vector support, using PHP fallback search');
                // Fallback: retrieve all embeddings from DB, compute distance in PHP, then sort by distance
                $all_rows = $wpdb->get_results("SELECT id, doc_id, chunk_id, chunk_content, summary, embedding FROM $table_name", ARRAY_A);
                if (!empty($wpdb->last_error)) {
                    return new WP_Error('query_error', $wpdb->last_error, ['status' => 500]);
                }

                $distances = [];
                foreach ($all_rows as $r) {
                    $stored_emb = json_decode($r['embedding'], true);
                    if (!is_array($stored_emb)) {
                        // If the row is invalid or old format, skip or handle error
                        continue;
                    }
                    $d = self::cosine_distance($embedding, $stored_emb);
                    $r['distance'] = $d;
                    $distances[] = $r;
                }
                // Sort ascending by distance:
                usort($distances, function($a, $b) {
                    return $a['distance'] <=> $b['distance'];
                });
                $results = array_slice($distances, 0, $k);
            }
    
            // Add debug info to results
            $results = array_map(function($row) {
                $row['debug_info'] = [
                    'database_type' => Database::get_db_type(),
                    'has_vector_support' => Database::has_native_vector_support() ? 'yes' : 'no'
                ];
                return $row;
            }, $results);
            
            error_log('[WPVDB DEBUG] Returning ' . count($results) . ' results from query endpoint');
            return rest_ensure_response($results);
        } catch (\Exception $e) {
            error_log('[WPVDB ERROR] Unhandled exception in handle_query: ' . $e->getMessage());
            return new WP_Error('server_error', 'Unhandled exception: ' . $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * GET /vdb/v1/metadata
     * 
     * Returns information about the vector database status and configuration
     * 
     * Request: No parameters needed
     * 
     * Response format:
     * {
     *   "plugin_version": "1.0.0",                // Current plugin version
     *   "embedding_dimension": 1536,              // Default embedding dimension
     *   "total_embeddings": 1250,                 // Total number of embeddings stored
     *   "total_documents": 75,                    // Total number of unique documents embedded
     *   "native_vector_support": true,            // Whether native vector types are supported
     *   "database_version": "MySQL 8.0.32",       // Database version information
     *   "table_exists": true                      // Whether the embeddings table exists
     * }
     * 
     * Notes:
     * - Useful for diagnostics and status checking
     * - Can be used to verify if the plugin is properly set up
     * - Returns database type and version information
     */
    public static function handle_metadata(WP_REST_Request $request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpvdb_embeddings';
        
        // Check if table exists first
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        // Default values
        $total_embeddings = 0;
        $total_docs = 0;
        
        // Get database statistics only if table exists
        if ($table_exists) {
            // Temporarily suppress errors
            $wpdb->hide_errors();
            $show_errors = $wpdb->show_errors;
            $wpdb->show_errors = false;
            
            try {
                $total_embeddings = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}") ?: 0;
                $total_docs = (int) $wpdb->get_var("SELECT COUNT(DISTINCT doc_id) FROM {$table_name}") ?: 0;
            } catch (\Exception $e) {
                // Reset to defaults in case of error
                $total_embeddings = 0;
                $total_docs = 0;
            }
            
            // Restore error display
            $wpdb->show_errors = $show_errors;
        }
        
        $has_vector = Database::has_native_vector_support();
        
        // Get database version
        $db_version = $wpdb->get_var("SELECT VERSION()");
        
        return new WP_REST_Response([
            'plugin_version' => WPVDB_VERSION,
            'embedding_dimension' => WPVDB_DEFAULT_EMBED_DIM,
            'total_embeddings' => $total_embeddings,
            'total_documents' => $total_docs,
            'native_vector_support' => $has_vector,
            'database_version' => $db_version,
            'table_exists' => $table_exists,
        ], 200);
    }

    /**
     * Insert a row into the embeddings table with the given data.
     * If vector support is available, use the native VECTOR type, otherwise fallback.
     */
    public static function insert_embedding_row($doc_id, $chunk_id, $chunk_content, $summary, $embedding) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpvdb_embeddings';

        error_log('[WPVDB DEBUG] insert_embedding_row called for doc_id: ' . $doc_id);
        
        // Check for vector support and handle storage differently
        $has_vector = Database::has_native_vector_support();
        error_log('[WPVDB DEBUG] Vector support detected: ' . ($has_vector ? 'Yes' : 'No'));

        if ($has_vector) {
            try {
                // Convert embedding to JSON
                $embedding_json = json_encode($embedding);
                error_log('[WPVDB DEBUG] Embedding JSON: ' . (strlen($embedding_json) > 100 ? substr($embedding_json, 0, 100) . '...' : $embedding_json));
                
                // Use the Database class to determine the vector function to use
                $vector_function = Database::get_vector_from_string_function($embedding_json);
                error_log('[WPVDB DEBUG] Vector function: ' . $vector_function);
                
                // For MySQL, the prepare statement handles the quoting properly
                // For MariaDB, we need to make sure the vector function is inserted as-is
                if (Database::get_db_type() === 'mariadb') {
                    // Use a direct query for MariaDB with proper quoting
                    $sql = $wpdb->prepare(
                        "INSERT INTO $table_name 
                        (doc_id, chunk_id, chunk_content, embedding, summary) 
                        VALUES (%d, %s, %s, $vector_function, %s)",
                        $doc_id, $chunk_id, $chunk_content, $summary
                    );
                } else {
                    // For MySQL, use the standard prepare statement
                    $sql = $wpdb->prepare(
                        "INSERT INTO $table_name 
                        (doc_id, chunk_id, chunk_content, embedding, summary) 
                        VALUES (%d, %s, %s, $vector_function, %s)",
                        $doc_id, $chunk_id, $chunk_content, $summary
                    );
                }
                
                error_log('[WPVDB DEBUG] SQL query: ' . $sql);
                
                $result = $wpdb->query($sql);
                
                if ($result === false) {
                    error_log('[WPVDB ERROR] Database error: ' . $wpdb->last_error);
                    
                    // Try a direct query without wpdb->prepare to see the exact SQL
                    $vector_func_parts = explode('(', $vector_function, 2);
                    $func_name = $vector_func_parts[0];
                    $vector_value = isset($vector_func_parts[1]) ? rtrim($vector_func_parts[1], ')') : '';
                    
                    $raw_sql = "INSERT INTO $table_name 
                        (doc_id, chunk_id, chunk_content, embedding, summary) 
                        VALUES ($doc_id, '" . esc_sql($chunk_id) . "', '" . esc_sql($chunk_content) . "', $func_name($vector_value), '" . esc_sql($summary) . "')";
                    
                    error_log('[WPVDB DEBUG] Raw SQL query: ' . $raw_sql);
                    $raw_result = $wpdb->query($raw_sql);
                    
                    if ($raw_result === false) {
                        error_log('[WPVDB ERROR] Raw SQL also failed: ' . $wpdb->last_error);
                    } else {
                        error_log('[WPVDB DEBUG] Raw SQL succeeded, issue might be with wpdb->prepare');
                    }
                } else {
                    error_log('[WPVDB DEBUG] Insert successful, row ID: ' . $wpdb->insert_id);
                }
            } catch (\Exception $e) {
                error_log('[WPVDB ERROR] Exception in insert_embedding_row: ' . $e->getMessage());
                $result = false;
            }
        } else {
            try {
                // Fallback: Store as serialized LONGTEXT
                // For compatibility with both types of tables and retrieval
                $embedding_json = json_encode($embedding);
                error_log('[WPVDB DEBUG] Fallback storage as LONGTEXT, JSON length: ' . strlen($embedding_json));
                
                $result = $wpdb->query($wpdb->prepare(
                    "INSERT INTO $table_name 
                    (doc_id, chunk_id, chunk_content, embedding, summary) 
                    VALUES (%d, %s, %s, %s, %s)",
                    $doc_id, $chunk_id, $chunk_content, $embedding_json, $summary
                ));
                
                if ($result === false) {
                    error_log('[WPVDB ERROR] Fallback insert failed: ' . $wpdb->last_error);
                } else {
                    error_log('[WPVDB DEBUG] Fallback insert successful, row ID: ' . $wpdb->insert_id);
                }
            } catch (\Exception $e) {
                error_log('[WPVDB ERROR] Exception in fallback insert: ' . $e->getMessage());
                $result = false;
            }
        }
        
        if ($result === false) {
            error_log('[WPVDB ERROR] Final insert result: Failed - ' . $wpdb->last_error);
            return new WP_Error('db_error', $wpdb->last_error, ['status' => 500]);
        }

        $row_id = $wpdb->insert_id;
        error_log('[WPVDB DEBUG] Final insert result: Success - ID: ' . $row_id);
        return [
            'id'            => $row_id,
            'doc_id'        => $doc_id,
            'chunk_id'      => $chunk_id,
            'chunk_content' => $chunk_content,
            'summary'       => $summary,
        ];
    }

    /**
     * Helper for cosine distance calculation in PHP (used as fallback).
     */
    public static function cosine_distance($vec1, $vec2) {
        // Validate inputs
        if (!is_array($vec1) || !is_array($vec2)) {
            Core::log_error('cosine_distance received non-array input', [
                'v1' => $vec1,
                'v2' => $vec2
            ]);
            return 1.0; // Maximum distance as a safe default
        }
        
        // Ensure arrays are of equal length, pad or truncate if needed
        $length = count($vec1);
        if (count($vec2) != $length) {
            // Either truncate or pad vec2 to match vec1's length
            $vec2 = array_slice($vec2, 0, $length);
            while (count($vec2) < $length) {
                $vec2[] = 0.0;
            }
        }
        
        $dot = 0.0;
        $mag1 = 0.0;
        $mag2 = 0.0;
        
        for ($i = 0; $i < $length; $i++) {
            // Ensure each value is a valid number
            $val1 = isset($vec1[$i]) && is_numeric($vec1[$i]) ? floatval($vec1[$i]) : 0.0;
            $val2 = isset($vec2[$i]) && is_numeric($vec2[$i]) ? floatval($vec2[$i]) : 0.0;
            
            $dot += $val1 * $val2;
            $mag1 += $val1 * $val1;
            $mag2 += $val2 * $val2;
        }
        
        $mag1 = sqrt($mag1);
        $mag2 = sqrt($mag2);
        
        if ($mag1 == 0 || $mag2 == 0) {
            return 1.0; // Maximum distance if either vector is zero
        }
        
        $similarity = $dot / ($mag1 * $mag2);
        // Clamp similarity to [-1, 1] to avoid floating point errors
        $similarity = max(-1.0, min(1.0, $similarity));
        
        return 1.0 - $similarity;
    }

    /**
     * POST /wp/v2/wpvdb/reembed
     * 
     * Triggers re-embedding of a specific post's content
     * 
     * Request format:
     * {
     *   "post_id": 123                          // Post ID to re-embed
     * }
     * 
     * Response format:
     * {
     *   "success": true,                        // Whether the operation was scheduled successfully
     *   "message": "Embedding generation started", // Status message
     *   "post_id": 123                          // Post ID being processed
     * }
     * 
     * Notes:
     * - Deletes any existing embeddings for the post
     * - Clears existing embedding metadata
     * - Queues the post for re-embedding using Action Scheduler
     * - Uses the currently active provider and model from settings
     * - In debug mode, may process the queue immediately
     */
    public static function handle_reembed(WP_REST_Request $request) {
        $post_id = absint($request->get_param('post_id'));
        
        if (!$post_id) {
            return rest_ensure_response([
                'success' => false,
                'message' => __('Invalid post ID', 'wpvdb')
            ]);
        }
        
        // Get the post
        $post = get_post($post_id);
        if (!$post) {
            return rest_ensure_response([
                'success' => false,
                'message' => __('Post not found', 'wpvdb')
            ]);
        }
        
        // Delete any existing embeddings for this post
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpvdb_embeddings';
        $wpdb->delete($table_name, ['doc_id' => $post_id], ['%d']);
        
        // Delete post meta
        delete_post_meta($post_id, '_wpvdb_embedded');
        delete_post_meta($post_id, '_wpvdb_chunks_count');
        delete_post_meta($post_id, '_wpvdb_embedded_date');
        delete_post_meta($post_id, '_wpvdb_embedded_model');
        
        // Get settings securely
        $settings = get_option('wpvdb_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }
        
        // Get active provider/model
        $provider = !empty($settings['active_provider']) ? $settings['active_provider'] : 'openai';
        $model = !empty($settings['active_model']) ? $settings['active_model'] : 'text-embedding-3-small';
        
        // Queue for processing
        $queue = new \WPVDB\WPVDB_Queue();
        $queue->push_to_queue([
            'post_id' => $post_id,
            'model' => $model,
            'provider' => $provider,
        ]);
        
        $queue->save()->dispatch();
        
        // Force Action Scheduler to run the task immediately
        if (function_exists('as_schedule_single_action') && function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action('wpvdb_run_queue_now', [], 'wpvdb');
        }
        
        // For development environments, process the queue immediately
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // Get the action ID that was just scheduled 
            $option_key = 'wpvdb_process_embedding';
            $queue_id = get_option($option_key);
            
            if ($queue_id) {
                // Run the action directly to bypass the scheduler
                do_action('wpvdb_process_embedding', $queue_id);
            }
        }
        
        return rest_ensure_response([
            'success' => true,
            'message' => __('Embedding generation started', 'wpvdb'),
            'post_id' => $post_id
        ]);
    }
}
