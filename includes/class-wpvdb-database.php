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
    private static $db_type = null;

    /**
     * Cache for native vector support
     *
     * @var bool|null
     */
    private static $has_vector_support = null;

    /**
     * Get the database type (mysql or mariadb)
     *
     * @return string 'mysql' or 'mariadb'
     */
    public static function get_db_type() {
        try {
            if ( null === self::$db_type ) {
                global $wpdb;
                $version = $wpdb->get_var( 'SELECT VERSION()' );
                error_log('[WPVDB DEBUG] Database version: ' . $version);
                
                self::$db_type = stripos( $version, 'mariadb' ) !== false ? 'mariadb' : 'mysql';
                error_log('[WPVDB DEBUG] Detected database type: ' . self::$db_type);
            }
            return self::$db_type;
        } catch (\Exception $e) {
            error_log('[WPVDB ERROR] Error in get_db_type: ' . $e->getMessage());
            return 'mysql'; // Default fallback
        }
    }

    /**
     * Check if the database has native vector support
     *
     * @return bool True if vector support is available
     */
    public static function has_native_vector_support() {
        try {
            if ( null === self::$has_vector_support ) {
                global $wpdb;
                
                // Check if we're using MariaDB 11.7+ or MySQL 8.0.32+
                $version = $wpdb->get_var( 'SELECT VERSION()' );
                error_log('[WPVDB DEBUG] Checking vector support for version: ' . $version);
                
                if ( stripos( $version, 'mariadb' ) !== false ) {
                    // MariaDB version check (11.7+)
                    $mariadb_version = preg_replace( '/^.*?(\d+\.\d+\.\d+).*$/i', '$1', $version );
                    self::$has_vector_support = version_compare( $mariadb_version, '11.7', '>=' );
                    error_log('[WPVDB DEBUG] MariaDB version: ' . $mariadb_version . ', Vector support: ' . (self::$has_vector_support ? 'Yes' : 'No'));
                    
                    // Test vector support by creating a test table
                    if (self::$has_vector_support) {
                        $test_result = $wpdb->query("CREATE TEMPORARY TABLE IF NOT EXISTS wpvdb_vector_test (v VECTOR(4))");
                        error_log('[WPVDB DEBUG] Vector test table creation: ' . ($test_result !== false ? 'Success' : 'Failed - ' . $wpdb->last_error));
                        
                        if ($test_result === false) {
                            self::$has_vector_support = false;
                        } else {
                            // Try to execute a VEC_FromText function
                            $test_vec = $wpdb->query("INSERT INTO wpvdb_vector_test VALUES (VEC_FromText('[0.1, 0.2, 0.3, 0.4]'))");
                            error_log('[WPVDB DEBUG] VEC_FromText test: ' . ($test_vec !== false ? 'Success' : 'Failed - ' . $wpdb->last_error));
                            
                            if ($test_vec === false) {
                                error_log('[WPVDB DEBUG] Vector functions not available: ' . $wpdb->last_error);
                                self::$has_vector_support = false;
                            }
                            
                            // Clean up
                            $wpdb->query("DROP TEMPORARY TABLE IF EXISTS wpvdb_vector_test");
                        }
                    }
                } else {
                    // MySQL version check (8.0.32+)
                    $mysql_version = preg_replace( '/^.*?(\d+\.\d+\.\d+).*$/i', '$1', $version );
                    self::$has_vector_support = version_compare( $mysql_version, '8.0.32', '>=' );
                    error_log('[WPVDB DEBUG] MySQL version: ' . $mysql_version . ', Vector support: ' . (self::$has_vector_support ? 'Yes' : 'No'));
                    
                    // Test vector support for MySQL
                    if (self::$has_vector_support) {
                        $test_result = $wpdb->query("CREATE TEMPORARY TABLE IF NOT EXISTS wpvdb_vector_test (v VECTOR(4))");
                        error_log('[WPVDB DEBUG] Vector test table creation: ' . ($test_result !== false ? 'Success' : 'Failed - ' . $wpdb->last_error));
                        
                        if ($test_result === false) {
                            self::$has_vector_support = false;
                        } else {
                            // Try to execute a STRING_TO_VECTOR function
                            $test_vec = $wpdb->query("INSERT INTO wpvdb_vector_test VALUES (STRING_TO_VECTOR('[0.1, 0.2, 0.3, 0.4]'))");
                            error_log('[WPVDB DEBUG] STRING_TO_VECTOR test: ' . ($test_vec !== false ? 'Success' : 'Failed - ' . $wpdb->last_error));
                            
                            if ($test_vec === false) {
                                error_log('[WPVDB DEBUG] Vector functions not available: ' . $wpdb->last_error);
                                self::$has_vector_support = false;
                            }
                            
                            // Clean up
                            $wpdb->query("DROP TEMPORARY TABLE IF EXISTS wpvdb_vector_test");
                        }
                    }
                }
            }
            return self::$has_vector_support;
        } catch (\Exception $e) {
            error_log('[WPVDB ERROR] Error in has_native_vector_support: ' . $e->getMessage());
            return false; // Default fallback
        }
    }

    /**
     * Get the SQL function to convert a JSON string to a vector
     *
     * @param string $json_string The JSON string representing the vector
     * @return string The SQL function call
     */
    public static function get_vector_from_string_function( $json_string ) {
        try {
            $db_type = self::get_db_type();
            error_log('[WPVDB DEBUG] get_vector_from_string_function for ' . $db_type);
            
            if ( $db_type === 'mariadb' ) {
                error_log('[WPVDB DEBUG] Using VEC_FromText function');
                // For MariaDB, the json_string parameter needs to be wrapped in quotes
                // Make sure we're not double-quoting if it's already quoted
                if (strpos($json_string, "'") === 0) {
                    return "VEC_FromText(" . $json_string . ")";
                } else {
                    return "VEC_FromText('" . $json_string . "')";
                }
            } else {
                error_log('[WPVDB DEBUG] Using STRING_TO_VECTOR function');
                return "STRING_TO_VECTOR(" . $json_string . ")";
            }
        } catch (\Exception $e) {
            error_log('[WPVDB ERROR] Error in get_vector_from_string_function: ' . $e->getMessage());
            // Default to MySQL function as fallback
            return "STRING_TO_VECTOR(" . $json_string . ")";
        }
    }

    /**
     * Get the SQL function to calculate vector distance
     *
     * @param string $vector1 The first vector expression
     * @param string $vector2 The second vector expression
     * @param string $distance_type The distance type ('cosine' or 'euclidean')
     * @return string The SQL function call
     */
    public static function get_vector_distance_function( $vector1, $vector2, $distance_type = 'cosine' ) {
        try {
            $db_type = self::get_db_type();
            error_log('[WPVDB DEBUG] get_vector_distance_function for ' . $db_type . ', distance_type: ' . $distance_type);
            
            if ( $db_type === 'mariadb' ) {
                if ( $distance_type === 'cosine' ) {
                    error_log('[WPVDB DEBUG] Using MariaDB VEC_DISTANCE_COSINE function');
                    return "VEC_DISTANCE_COSINE(" . $vector1 . ", " . $vector2 . ")";
                } else {
                    error_log('[WPVDB DEBUG] Using MariaDB VEC_DISTANCE_EUCLIDEAN function');
                    return "VEC_DISTANCE_EUCLIDEAN(" . $vector1 . ", " . $vector2 . ")";
                }
            } else {
                error_log('[WPVDB DEBUG] Using MySQL DISTANCE function');
                return "DISTANCE(" . $vector1 . ", " . $vector2 . ", 'COSINE')";
            }
        } catch (\Exception $e) {
            error_log('[WPVDB ERROR] Error in get_vector_distance_function: ' . $e->getMessage());
            // Default to MySQL function as fallback
            return "DISTANCE(" . $vector1 . ", " . $vector2 . ", 'COSINE')";
        }
    }

    /**
     * Get the embedding column data type for table creation
     *
     * @param int $dimensions Number of dimensions for the vector
     * @return string SQL column type definition
     */
    public static function get_embedding_column_type( $dimensions = 1536 ) {
        try {
            error_log('[WPVDB DEBUG] get_embedding_column_type, dimensions: ' . $dimensions);
            
            if ( self::has_native_vector_support() ) {
                $db_type = self::get_db_type();
                error_log('[WPVDB DEBUG] Using VECTOR column type for ' . $db_type);
                return "VECTOR(" . intval($dimensions) . ")";
            } else {
                error_log('[WPVDB DEBUG] Using LONGTEXT column type (fallback)');
                return "LONGTEXT";  // Fallback for databases without vector support
            }
        } catch (\Exception $e) {
            error_log('[WPVDB ERROR] Error in get_embedding_column_type: ' . $e->getMessage());
            return "LONGTEXT";  // Default fallback
        }
    }
    
    /**
     * Execute a test query to verify database connectivity and functions
     * 
     * @return array Test results
     */
    public static function run_diagnostics() {
        global $wpdb;
        $results = [
            'success' => true,
            'messages' => [],
            'errors' => []
        ];
        
        try {
            // Test database connection
            $version = $wpdb->get_var("SELECT VERSION()");
            $results['messages'][] = "Database version: " . $version;
            
            // Detect database type
            $db_type = self::get_db_type();
            $results['messages'][] = "Database type: " . $db_type;
            
            // Check vector support
            $has_vector = self::has_native_vector_support();
            $results['messages'][] = "Vector support: " . ($has_vector ? "Yes" : "No");
            
            // If vector support is available, test the functions
            if ($has_vector) {
                // Create a test table
                $table_name = $wpdb->prefix . 'wpvdb_vector_test';
                $dimensions = 4;
                
                // Drop the table if it exists
                $wpdb->query("DROP TABLE IF EXISTS $table_name");
                
                // Create a new test table
                $column_type = self::get_embedding_column_type($dimensions);
                $create_sql = "CREATE TABLE $table_name (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    embedding $column_type
                )";
                
                $create_result = $wpdb->query($create_sql);
                if ($create_result === false) {
                    $results['errors'][] = "Failed to create test table: " . $wpdb->last_error;
                    $results['success'] = false;
                } else {
                    $results['messages'][] = "Test table created successfully";
                    
                    // Try to insert a vector
                    $test_vector = "[0.1, 0.2, 0.3, 0.4]";
                    $vector_function = self::get_vector_from_string_function("'" . $test_vector . "'");
                    
                    $insert_sql = "INSERT INTO $table_name (embedding) VALUES ($vector_function)";
                    $insert_result = $wpdb->query($insert_sql);
                    
                    if ($insert_result === false) {
                        $results['errors'][] = "Failed to insert test vector: " . $wpdb->last_error;
                        $results['errors'][] = "SQL used: " . $insert_sql;
                        $results['success'] = false;
                    } else {
                        $results['messages'][] = "Test vector inserted successfully";
                        
                        // Try to retrieve the vector
                        $select_sql = "SELECT id FROM $table_name LIMIT 1";
                        $select_result = $wpdb->get_var($select_sql);
                        
                        if ($select_result === null && $wpdb->last_error) {
                            $results['errors'][] = "Failed to retrieve test data: " . $wpdb->last_error;
                            $results['success'] = false;
                        } else {
                            $results['messages'][] = "Test data retrieved successfully";
                        }
                    }
                    
                    // Clean up
                    $wpdb->query("DROP TABLE IF EXISTS $table_name");
                }
            }
        } catch (\Exception $e) {
            $results['errors'][] = "Exception: " . $e->getMessage();
            $results['success'] = false;
        }
        
        // Log all results
        foreach ($results['messages'] as $message) {
            error_log('[WPVDB DIAGNOSTIC] ' . $message);
        }
        foreach ($results['errors'] as $error) {
            error_log('[WPVDB DIAGNOSTIC ERROR] ' . $error);
        }
        
        return $results;
    }
} 