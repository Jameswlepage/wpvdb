<?php
/**
 * Database abstraction layer to handle differences between MySQL and MariaDB
 *
 * @package WPVDB
 */

namespace WPVDB;

/**
 * Database class to handle database-specific operations
 */
class Database {
    /**
     * Cache for database type
     *
     * @var string|null
     */
    private $db_type = null;

    /**
     * Cache for native vector support
     *
     * @var bool|null
     */
    private $has_vector_support = null;

    /**
     * Cache for whether fallbacks are allowed
     *
     * @var bool|null
     */
    private $fallbacks_enabled = null;

    /**
     * Get the database type (mysql or mariadb)
     *
     * @return string 'mysql' or 'mariadb'
     */
    public function get_db_type() {
        try {
            if (null === $this->db_type) {
                global $wpdb;
                $version = $wpdb->get_var('SELECT VERSION()');
                error_log('[WPVDB DEBUG] Database version: ' . $version);
                
                $this->db_type = stripos($version, 'mariadb') !== false ? 'mariadb' : 'mysql';
                error_log('[WPVDB DEBUG] Detected database type: ' . $this->db_type);
            }
            return $this->db_type;
        } catch (\Exception $e) {
            error_log('[WPVDB ERROR] Failed to detect database type: ' . $e->getMessage());
            return 'unknown';
        }
    }

    /**
     * Check if fallbacks are enabled via filter
     *
     * @return bool
     */
    public function are_fallbacks_enabled() {
        if (null === $this->fallbacks_enabled) {
            /**
             * Filter to enable fallbacks for incompatible databases
             * 
             * @param bool $enabled Whether fallbacks are enabled
             */
            $this->fallbacks_enabled = apply_filters('wpvdb_enable_fallbacks', false);
        }
        return $this->fallbacks_enabled;
    }

    /**
     * Check if the database supports native vector columns
     *
     * @return bool
     */
    public function has_native_vector_support() {
        if (null === $this->has_vector_support) {
            $db_type = $this->get_db_type();
            
            try {
                global $wpdb;
                
                // Get database version
                $version = $wpdb->get_var('SELECT VERSION()');
                
                // Extract version number
                if ($db_type === 'mariadb') {
                    preg_match('/(\d+\.\d+\.\d+)/', $version, $matches);
                    if (!empty($matches[1])) {
                        $version_number = $matches[1];
                        
                        // MariaDB 11.7+ supports vector type
                        $this->has_vector_support = version_compare($version_number, '11.7', '>=');
                    } else {
                        $this->has_vector_support = false;
                    }
                } else if ($db_type === 'mysql') {
                    preg_match('/(\d+\.\d+\.\d+)/', $version, $matches);
                    if (!empty($matches[1])) {
                        $version_number = $matches[1];
                        
                        // MySQL 8.0.32+ supports vector type
                        $this->has_vector_support = version_compare($version_number, '8.0.32', '>=');
                    } else {
                        $this->has_vector_support = false;
                    }
                } else {
                    // Unknown database type
                    $this->has_vector_support = false;
                }
                
                // If we don't have vector support, check if we can create a test table
                if (!$this->has_vector_support) {
                    // Try to create a test table with vector column
                    $test_table = $wpdb->prefix . 'wpvdb_vector_test';
                    $result = $wpdb->query("CREATE TABLE IF NOT EXISTS $test_table (
                        id INT NOT NULL AUTO_INCREMENT,
                        embedding VECTOR(3) NOT NULL,
                        PRIMARY KEY (id)
                    )");
                    
                    if ($result !== false) {
                        // Table created successfully, we have vector support
                        $this->has_vector_support = true;
                        
                        // Clean up test table
                        $wpdb->query("DROP TABLE IF EXISTS $test_table");
                    }
                }
                
                // Log the result
                error_log('[WPVDB DEBUG] Database vector support: ' . ($this->has_vector_support ? 'Yes' : 'No'));
                
                // If we don't have vector support, set a warning flag
                if (!$this->has_vector_support && !$this->are_fallbacks_enabled()) {
                    update_option('wpvdb_db_vector_support_warning', 1);
                } else {
                    delete_option('wpvdb_db_vector_support_warning');
                }
                
                return $this->has_vector_support;
            } catch (\Exception $e) {
                error_log('[WPVDB ERROR] Failed to check vector support: ' . $e->getMessage());
                $this->has_vector_support = false;
                return false;
            }
        }
        
        return $this->has_vector_support;
    }

    /**
     * Get the SQL function to convert a JSON string to a vector
     *
     * @param string $json_string JSON string containing vector data
     * @return string SQL function
     */
    public function get_vector_from_string_function($json_string) {
        $db_type = $this->get_db_type();
        
        if ($this->has_native_vector_support()) {
            if ($db_type === 'mariadb') {
                // MariaDB uses JSON_VALUE to extract array elements
                return "VECTOR_FROM_JSON($json_string)";
            } else {
                // MySQL uses JSON_EXTRACT
                return "VECTOR_FROM_JSON($json_string)";
            }
        } else {
            // Fallback - just return the JSON string
            return $json_string;
        }
    }

    /**
     * Get the SQL function to calculate distance between two vectors
     *
     * @param string $vector1 First vector expression
     * @param string $vector2 Second vector expression
     * @param string $distance_type Distance type (cosine, euclidean, dot)
     * @return string SQL function
     */
    public function get_vector_distance_function($vector1, $vector2, $distance_type = 'cosine') {
        if ($this->has_native_vector_support()) {
            $db_type = $this->get_db_type();
            
            if ($db_type === 'mariadb') {
                // MariaDB has built-in vector distance functions
                switch ($distance_type) {
                    case 'cosine':
                        return "COSINE_DISTANCE($vector1, $vector2)";
                    case 'euclidean':
                        return "EUCLIDEAN_DISTANCE($vector1, $vector2)";
                    case 'dot':
                        return "DOT_PRODUCT($vector1, $vector2)";
                    default:
                        return "COSINE_DISTANCE($vector1, $vector2)";
                }
            } else {
                // MySQL has similar functions
                switch ($distance_type) {
                    case 'cosine':
                        return "COSINE_DISTANCE($vector1, $vector2)";
                    case 'euclidean':
                        return "EUCLIDEAN_DISTANCE($vector1, $vector2)";
                    case 'dot':
                        return "DOT_PRODUCT($vector1, $vector2)";
                    default:
                        return "COSINE_DISTANCE($vector1, $vector2)";
                }
            }
        } else {
            // Fallback - we can't calculate distance in SQL
            return "1.0"; // Return a constant value
        }
    }

    /**
     * Get the SQL column type for embedding storage
     *
     * @param int $dimensions Number of dimensions in the embedding
     * @return string SQL column type
     */
    public function get_embedding_column_type($dimensions = 1536) {
        if ($this->has_native_vector_support()) {
            return "VECTOR($dimensions)";
        } else {
            // Fallback to LONGTEXT for JSON storage
            return "LONGTEXT";
        }
    }

    /**
     * Run database diagnostics
     *
     * @return array Diagnostic information
     */
    public function run_diagnostics() {
        global $wpdb;
        
        $diagnostics = [];
        
        // Get database type and version
        $db_type = $this->get_db_type();
        $version_string = $wpdb->get_var('SELECT VERSION()');
        
        $diagnostics['db_type'] = $db_type;
        $diagnostics['db_version'] = $version_string;
        
        // Extract version number
        if ($db_type === 'mariadb') {
            preg_match('/(\d+\.\d+\.\d+)/', $version_string, $matches);
            if (!empty($matches[1])) {
                $version_number = $matches[1];
                $diagnostics['version_number'] = $version_number;
                $diagnostics['min_required'] = '11.7';
                $diagnostics['is_compatible'] = version_compare($version_number, '11.7', '>=');
            }
        } else if ($db_type === 'mysql') {
            preg_match('/(\d+\.\d+\.\d+)/', $version_string, $matches);
            if (!empty($matches[1])) {
                $version_number = $matches[1];
                $diagnostics['version_number'] = $version_number;
                $diagnostics['min_required'] = '8.0.32';
                $diagnostics['is_compatible'] = version_compare($version_number, '8.0.32', '>=');
            }
        }
        
        // Check if vector type is supported
        $diagnostics['has_vector_support'] = $this->has_native_vector_support();
        
        // Check if fallbacks are enabled
        $diagnostics['fallbacks_enabled'] = $this->are_fallbacks_enabled();
        
        // Test vector operations if supported
        if ($diagnostics['has_vector_support']) {
            try {
                // Create test table
                $test_table = $wpdb->prefix . 'wpvdb_vector_test';
                $wpdb->query("DROP TABLE IF EXISTS $test_table");
                $result = $wpdb->query("CREATE TABLE $test_table (
                    id INT NOT NULL AUTO_INCREMENT,
                    embedding VECTOR(3) NOT NULL,
                    PRIMARY KEY (id)
                )");
                
                $diagnostics['create_table'] = ($result !== false);
                
                if ($diagnostics['create_table']) {
                    // Insert test data
                    $result = $wpdb->query("INSERT INTO $test_table (embedding) VALUES ('[1.0, 0.0, 0.0]'), ('[0.0, 1.0, 0.0]'), ('[0.0, 0.0, 1.0]')");
                    $diagnostics['insert_data'] = ($result !== false && $result === 3);
                    
                    // Test cosine distance
                    $distance = $wpdb->get_var("SELECT COSINE_DISTANCE(embedding, '[1.0, 1.0, 0.0]') FROM $test_table WHERE id = 1");
                    $diagnostics['cosine_distance'] = ($distance !== null && is_numeric($distance));
                    
                    // Test vector indexing (MariaDB only)
                    if ($db_type === 'mariadb') {
                        $result = $wpdb->query("ALTER TABLE $test_table ADD VECTOR INDEX embedding_idx(embedding) USING HNSW");
                        $diagnostics['vector_index'] = ($result !== false);
                    } else {
                        $diagnostics['vector_index'] = 'Not supported in MySQL';
                    }
                    
                    // Clean up
                    $wpdb->query("DROP TABLE IF EXISTS $test_table");
                }
            } catch (\Exception $e) {
                $diagnostics['error'] = $e->getMessage();
            }
        }
        
        return $diagnostics;
    }

    /**
     * Initialize database hooks
     */
    public function init() {
        // Add hook to delete embeddings when a post is deleted
        add_action('delete_post', [$this, 'delete_post_embeddings']);
        
        // Add hook to delete embeddings when a post is trashed
        add_action('wp_trash_post', [$this, 'delete_post_embeddings']);
    }

    /**
     * Delete embeddings for a post
     *
     * @param int $post_id Post ID
     */
    public function delete_post_embeddings($post_id) {
        global $wpdb;
        
        // Get the embedding table name
        $table_name = $wpdb->prefix . 'wpvdb_embeddings';
        
        // Check if the table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if ($table_exists) {
            // Delete all embeddings for this post
            $wpdb->delete(
                $table_name,
                ['post_id' => $post_id],
                ['%d']
            );
            
            // Log the deletion
            error_log("[WPVDB] Deleted embeddings for post ID: $post_id");
        }
    }

    /**
     * Add vector index to a table
     *
     * @param int    $m_value      M value for HNSW index (default 16)
     * @param string $distance_type Distance type (cosine, euclidean, dot)
     * @return bool Whether the index was added successfully
     */
    public function add_vector_index($m_value = 16, $distance_type = 'cosine') {
        global $wpdb;
        
        // Only MariaDB supports vector indexes
        if ($this->get_db_type() !== 'mariadb' || !$this->has_native_vector_support()) {
            return false;
        }
        
        try {
            // Get the embedding table name
            $table_name = $wpdb->prefix . 'wpvdb_embeddings';
            
            // Check if the table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            
            if (!$table_exists) {
                return false;
            }
            
            // Check if the index already exists
            $index_exists = $wpdb->get_var("SHOW INDEX FROM $table_name WHERE Key_name = 'embedding_idx'") !== null;
            
            if ($index_exists) {
                // Index already exists
                return true;
            }
            
            // Add the vector index
            $result = $wpdb->query("ALTER TABLE $table_name ADD VECTOR INDEX embedding_idx(embedding) USING HNSW WITH (m = $m_value, ef_construction = 128, distance = '$distance_type')");
            
            return $result !== false;
        } catch (\Exception $e) {
            error_log('[WPVDB ERROR] Failed to add vector index: ' . $e->getMessage());
            return false;
        }
    }
} 