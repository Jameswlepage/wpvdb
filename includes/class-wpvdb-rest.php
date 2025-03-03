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
        // e.g. POST /vdb/v1/embed
        register_rest_route('vdb/v1', '/embed', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_embed'],
            'permission_callback' => [__CLASS__, 'default_permission_check'],
        ]);

        // e.g. POST /vdb/v1/vectors
        register_rest_route('vdb/v1', '/vectors', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_vectors'],
            'permission_callback' => [__CLASS__, 'default_permission_check'],
        ]);

        // e.g. POST /vdb/v1/query
        register_rest_route('vdb/v1', '/query', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_query'],
            'permission_callback' => [__CLASS__, 'default_permission_check'],
        ]);

        // e.g. GET /vdb/v1/metadata
        register_rest_route('vdb/v1', '/metadata', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'handle_metadata'],
            'permission_callback' => [__CLASS__, 'default_permission_check'],
        ]);
    }

    /**
     * Basic permission check. By default, require 'edit_posts'.
     */
    public static function default_permission_check() {
        return current_user_can('edit_posts');
    }

    /**
     * POST /vdb/v1/embed
     * {
     *   "doc_id": 123,
     *   "text": "some long text",
     *   "model": "text-embedding-3-small",
     *   "api_base": "https://api.openai.com/v1/",
     *   "api_key": "sk-xxx"
     * }
     * - chunk the text
     * - optional summarization
     * - embed each chunk
     * - store row in DB
     * returns JSON with inserted rows
     */
    public static function handle_embed(WP_REST_Request $request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpvdb_embeddings';

        $doc_id  = $request->get_param('doc_id');
        $text    = $request->get_param('text');
        $model   = $request->get_param('model') ?: 'text-embedding-3-small';
        $api_base= $request->get_param('api_base') ?: 'https://api.openai.com/v1/';
        $api_key = $request->get_param('api_key');

        if (!$text || !$api_key) {
            return new WP_Error('invalid_params', 'Missing required fields: text, api_key.', ['status' => 400]);
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
     * Accepts pre-computed embedding, chunk text, doc_id, chunk_id, summary
     * {
     *   "doc_id":123,
     *   "chunk_id":"some-chunk-id",
     *   "chunk_content":"The text for this chunk",
     *   "embedding":[float array],
     *   "summary":"..."
     * }
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
     * {
     *   "query_text": "some text to embed and search with",
     *   "embedding": [float array],
     *   "model": "text-embedding-3-small",
     *   "api_base": "https://api.openai.com/v1/",
     *   "api_key": "sk-xxx",
     *   "k": 5
     * }
     * - If embedding is provided, skip generating. Otherwise generate from query_text.
     * - Find top-k nearest neighbors in DB. Return them with distance.
     */
    public static function handle_query(WP_REST_Request $request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpvdb_embeddings';

        $query_text = $request->get_param('query_text');
        $embedding  = $request->get_param('embedding');
        $model      = $request->get_param('model') ?: 'text-embedding-3-small';
        $api_base   = $request->get_param('api_base') ?: 'https://api.openai.com/v1/';
        $api_key    = $request->get_param('api_key');
        $k          = absint($request->get_param('k')) ?: 5;

        // If no embedding is provided, we generate from query_text
        if (!$embedding && !$query_text) {
            return new WP_Error('invalid_params', 'Provide either "query_text" or "embedding".', ['status' => 400]);
        }
        if (!$embedding && !$api_key) {
            return new WP_Error('invalid_params', 'No embedding given, so "api_key" is required to generate it.', ['status' => 400]);
        }

        if (!$embedding) {
            // Generate
            $embedding_result = Core::get_embedding($query_text, $model, $api_base, $api_key);
            if (is_wp_error($embedding_result)) {
                return $embedding_result;
            }
            $embedding = $embedding_result;
        }

        // Now we have an embedding array of floats. If we have native vector support, use it. Otherwise fallback.
        $has_vector = Activation::has_native_vector_support();
        $results = [];

        if ($has_vector) {
            // Use MariaDB/MySQL vector functions
            // We'll rely on the fact that we used a COSINE index or EUCLIDEAN.
            // We can do something like:
            $vecHex = self::convert_array_to_vector_hex($embedding);

            // If table uses VEC_DISTANCE(...) function:
            // for example: SELECT doc_id, chunk_id, VEC_DISTANCE_COSINE(embedding, x'HEX') as distance FROM ...
            // We do a standard ordering, limit $k
            // Remember we used "embedding VECTOR(1536)" with "VECTOR INDEX (embedding) DISTANCE=cosine"
            // so VEC_DISTANCE is the same as VEC_DISTANCE_COSINE.

            $sql = $wpdb->prepare("
                SELECT 
                    id, doc_id, chunk_id, chunk_content, summary,
                    VEC_DISTANCE(embedding, x%s) AS distance
                FROM $table_name
                ORDER BY VEC_DISTANCE(embedding, x%s)
                LIMIT %d
            ", $vecHex, $vecHex, $k);

            $rows = $wpdb->get_results($sql, ARRAY_A);

            if (!empty($wpdb->last_error)) {
                return new WP_Error('query_error', $wpdb->last_error, ['status' => 500]);
            }

            $results = $rows ?: [];
        } else {
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

        return new WP_REST_Response([
            'success' => true,
            'results' => $results
        ], 200);
    }

    /**
     * GET /vdb/v1/metadata
     * Returns information about the vector database
     */
    public static function handle_metadata(WP_REST_Request $request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpvdb_embeddings';
        
        // Get database statistics
        $total_embeddings = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $total_docs = (int) $wpdb->get_var("SELECT COUNT(DISTINCT doc_id) FROM {$table_name}");
        $has_vector = Activation::has_native_vector_support();
        
        // Get database version
        $db_version = $wpdb->get_var("SELECT VERSION()");
        
        return new WP_REST_Response([
            'plugin_version' => WPVDB_VERSION,
            'embedding_dimension' => WPVDB_DEFAULT_EMBED_DIM,
            'total_embeddings' => $total_embeddings,
            'total_documents' => $total_docs,
            'native_vector_support' => $has_vector,
            'database_version' => $db_version,
        ], 200);
    }

    /**
     * Insert an embedding into the database
     * Changed from private to public so it can be called from WPVDB_Queue
     * 
     * @param int|string $doc_id The document ID
     * @param string $chunk_id The chunk ID
     * @param string $chunk_text The chunk text
     * @param string $summary Optional summary of the chunk
     * @param array $embedding The embedding vector
     * @return int|WP_Error The row ID or error
     */
    public static function insert_embedding_row($doc_id, $chunk_id, $chunk_text, $summary, $embedding) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpvdb_embeddings';
        $has_vector = Activation::has_native_vector_support();

        if ($has_vector) {
            // Convert array of floats to hex for insertion as a VECTOR column using e.g. VEC_FromText() or x'...'
            $vecHex = self::convert_array_to_vector_hex($embedding);

            // Insert:
            // embedding = x'...'  or embedding = VEC_FromText('[...]')
            // We'll do x'...' style
            $sql = $wpdb->prepare("
                INSERT INTO $table_name (doc_id, chunk_id, chunk_content, summary, embedding)
                VALUES (%d, %s, %s, %s, x%s)
            ", $doc_id, $chunk_id, $chunk_text, $summary, $vecHex);

            $res = $wpdb->query($sql);
            if ($res === false) {
                return new \WP_Error('db_error', $wpdb->last_error);
            }
            $id = $wpdb->insert_id;
            return [
                'id' => $id,
                'doc_id' => $doc_id,
                'chunk_id' => $chunk_id,
            ];
        } else {
            // Fallback: store as JSON
            $json_emb = wp_json_encode($embedding);
            $res = $wpdb->insert($table_name, [
                'doc_id'        => $doc_id,
                'chunk_id'      => $chunk_id,
                'chunk_content' => $chunk_text,
                'summary'       => $summary,
                'embedding'     => $json_emb,
            ], [
                '%d','%s','%s','%s','%s'
            ]);
            if ($res === false) {
                return new \WP_Error('db_error', $wpdb->last_error);
            }
            $id = $wpdb->insert_id;
            return [
                'id' => $id,
                'doc_id' => $doc_id,
                'chunk_id' => $chunk_id,
            ];
        }
    }

    /**
     * Convert array of floats to a binary hex string suitable for x'...' insertion in a MySQL/MariaDB VECTOR column.
     * This is a simplified approach. Production usage should ensure endianness and 32-bit float correctness.
     *
     * @param array $floats
     * @return string hex
     */
    public static function convert_array_to_vector_hex($floats) {
        if (!is_array($floats)) {
            // If not an array, return an empty hex string or log an error
            Core::log_error('convert_array_to_vector_hex received non-array input', ['input' => $floats]);
            return '';
        }
        
        $binary = '';
        foreach ($floats as $f) {
            // Ensure we have a valid float value
            if ($f === null) {
                $f = 0.0;
            }
            // pack float (32-bit single precision).
            $binary .= pack('f', floatval($f));
        }
        // return hex representation
        return bin2hex($binary);
    }

    /**
     * Simple fallback distance function for two vectors in PHP. 
     * We'll do 1 - cosine_similarity = cosine_distance.
     *
     * @param array $v1
     * @param array $v2
     * @return float
     */
    public static function cosine_distance($v1, $v2) {
        // Validate inputs
        if (!is_array($v1) || !is_array($v2)) {
            Core::log_error('cosine_distance received non-array input', [
                'v1' => $v1,
                'v2' => $v2
            ]);
            return 1.0; // Maximum distance as a safe default
        }
        
        // Ensure arrays are of equal length, pad or truncate if needed
        $length = count($v1);
        if (count($v2) != $length) {
            // Either truncate or pad v2 to match v1's length
            $v2 = array_slice($v2, 0, $length);
            while (count($v2) < $length) {
                $v2[] = 0.0;
            }
        }
        
        $dot = 0.0;
        $mag1 = 0.0;
        $mag2 = 0.0;
        
        for ($i = 0; $i < $length; $i++) {
            // Ensure each value is a valid number
            $val1 = isset($v1[$i]) && is_numeric($v1[$i]) ? floatval($v1[$i]) : 0.0;
            $val2 = isset($v2[$i]) && is_numeric($v2[$i]) ? floatval($v2[$i]) : 0.0;
            
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
}
