<?php
namespace WPVDB;

use wpdb;

defined('ABSPATH') || exit;

class Activation {
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
     * Plugin activation routine.
     *  - Checks DB capabilities.
     *  - Creates or upgrades our custom table schema using dbDelta.
     */
    public static function activate() {
        global $wpdb;

        // Initialize database
        self::init_database();

        // Silence errors during activation to prevent "headers already sent"
        $wpdb->hide_errors();
        $show_errors = $wpdb->show_errors;
        $wpdb->show_errors = false;
        $old_error_reporting = error_reporting(0);
        
        // Check database version
        self::check_db_version_or_warn();
        
        // Check if database is compatible - if not and fallbacks aren't enabled, set a transient
        // to display a notice about compatibility and possible auto-deactivation
        $is_compatible = self::$database->has_native_vector_support();
        $fallbacks_enabled = self::$database->are_fallbacks_enabled();
        
        if (!$is_compatible && !$fallbacks_enabled) {
            // Set transient for admin notice
            set_transient('wpvdb_incompatible_db_notice', true, 0);
            
            // Set a flag to possibly deactivate the plugin later
            update_option('wpvdb_incompatible_db', true);
            
            // Log the incompatible activation
            error_log('[WPVDB] Activated on incompatible database. Vector features require MySQL 8.0.32+ or MariaDB 11.7+.');
            
            // Restore error reporting and return early (we'll show the warning later)
            error_reporting($old_error_reporting);
            $wpdb->show_errors = $show_errors;
            return;
        } else {
            // Database is compatible or fallbacks are enabled, remove any flags
            delete_option('wpvdb_incompatible_db');
            delete_transient('wpvdb_incompatible_db_notice');
        }
        
        // Get the SQL for creating tables
        $sql = self::get_schema_sql();
        
        // Apply schema changes (create/update tables)
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Create index on name column (improves lookup performance)
        self::add_vector_index_to_existing_table();
        
        // Create Meta tables
        self::create_meta_tables();
        
        // Store the current DB version
        update_option('wpvdb_db_version', WPVDB_VERSION);
        
        // Restore error reporting
        error_reporting($old_error_reporting);
        $wpdb->show_errors = $show_errors;
    }
    
    /**
     * Check database version and set a warning flag if necessary
     */
    public static function check_db_version_or_warn() {
        self::init_database();
        
        $has_vector = self::$database->has_native_vector_support();
        
        if ($has_vector || self::$database->are_fallbacks_enabled()) {
            // Compatible database or fallbacks enabled, no warning needed
            update_option('wpvdb_db_vector_support_warning', 0);
        } else {
            // Incompatible database, set warning flag
            update_option('wpvdb_db_vector_support_warning', 1);
        }
    }
    
    /**
     * Generate the SQL for creating the embedding table
     */
    private static function get_schema_sql() {
        global $wpdb;
        self::init_database();
        
        $collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'wpvdb_embeddings';
        
        // Determine the embedding column type (vector or longtext fallback)
        $has_vector = self::$database->has_native_vector_support();
        $embed_dim = defined('WPVDB_DEFAULT_EMBED_DIM') ? WPVDB_DEFAULT_EMBED_DIM : 1536;
        $embedding_type = self::$database->get_embedding_column_type($embed_dim);
        
        $has_meta_column = false;
        $check_meta_column = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'meta'");
        if ($check_meta_column) {
            $has_meta_column = true;
        }
        
        // Build the SQL for creating the table
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            doc_id bigint(20) unsigned NOT NULL,
            doc_type varchar(20) NOT NULL DEFAULT 'post',
            model varchar(64) NOT NULL,
            chunk_index int(11) NOT NULL DEFAULT 0,
            chunk_text longtext NOT NULL,
            embedding {$embedding_type} NOT NULL,
            embedding_date datetime DEFAULT NULL,
            meta longtext DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY doc_id (doc_id),
            KEY model (model),
            KEY doc_type (doc_type)
        ) $collate;\n";

        if (!$has_meta_column) {
            // Add meta column if it doesn't exist (for upgrade from older versions)
            $sql .= "ALTER TABLE {$table_name} ADD COLUMN meta longtext DEFAULT NULL;\n";
        }
        
        return $sql;
    }
    
    /**
     * Add a vector index to the embeddings table if using MariaDB
     */
    public static function add_vector_index_to_existing_table() {
        global $wpdb;
        
        try {
            self::init_database();
            
            // Only proceed if database is ready and we've initialized properly
            if (!self::$database) {
                error_log('[WPVDB] Database not initialized, skipping vector index creation');
                return false;
            }
            
            $table_name = $wpdb->prefix . 'wpvdb_embeddings';
            
            // Check if table exists before proceeding
            try {
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            } catch (\Exception $e) {
                error_log('[WPVDB] Error checking if table exists: ' . $e->getMessage());
                return false;
            }
            
            // Only proceed if we have MariaDB with vector support
            $has_vector_support = false;
            $is_mariadb = false;
            
            try {
                $is_mariadb = self::$database->get_db_type() === 'mariadb';
                $has_vector_support = $is_mariadb && self::$database->has_native_vector_support();
            } catch (\Exception $e) {
                error_log('[WPVDB] Error checking database type or vector support: ' . $e->getMessage());
                return false;
            }
            
            if ($table_exists && $is_mariadb && $has_vector_support) {
                try {
                    // Check if the index already exists to avoid errors
                    $index_exists = false;
                    try {
                        $index_check = $wpdb->get_results("SHOW INDEX FROM $table_name WHERE Key_name = 'embedding_idx'");
                        $index_exists = !empty($index_check);
                    } catch (\Exception $e) {
                        error_log('[WPVDB] Error checking for existing index: ' . $e->getMessage());
                    }
                    
                    if (!$index_exists) {
                        // Use M=12 for better performance balance based on our testing
                        $result = $wpdb->query("
                            ALTER TABLE $table_name 
                            ADD VECTOR INDEX embedding_idx(embedding) M=12 DISTANCE=cosine
                        ");
                        
                        if ($result === false) {
                            error_log('[WPVDB] Failed to add vector index using new syntax: ' . $wpdb->last_error);
                            
                            // Try with simpler syntax as fallback
                            $result = $wpdb->query("
                                ALTER TABLE $table_name 
                                ADD VECTOR INDEX embedding_idx(embedding)
                            ");
                            
                            if ($result !== false) {
                                error_log('[WPVDB] Added vector index with simplified syntax');
                            } else {
                                error_log('[WPVDB] Failed to add vector index with simplified syntax: ' . $wpdb->last_error);
                            }
                        } else {
                            error_log('[WPVDB] Added optimized vector index to embeddings table');
                        }
                    } else {
                        error_log('[WPVDB] Vector index already exists, skipping creation');
                    }
                    
                    // After creating the main vector index, add supporting indexes if needed
                    try {
                        $supporting_indexes = [
                            'doc_id_idx' => "CREATE INDEX IF NOT EXISTS doc_id_idx ON $table_name(doc_id)",
                            'doc_type_idx' => "CREATE INDEX IF NOT EXISTS doc_type_idx ON $table_name(doc_type)"
                        ];
                        
                        foreach ($supporting_indexes as $index_name => $create_sql) {
                            try {
                                // Check if index exists first
                                $index_check = $wpdb->get_results("SHOW INDEX FROM $table_name WHERE Key_name = '$index_name'");
                                if (empty($index_check)) {
                                    $wpdb->query($create_sql);
                                }
                            } catch (\Exception $e) {
                                // Ignore errors for supporting indexes, they're not critical
                                error_log("[WPVDB] Error creating supporting index $index_name: " . $e->getMessage());
                            }
                        }
                    } catch (\Exception $e) {
                        // Ignore errors for supporting indexes, they're not critical
                        error_log('[WPVDB] Error creating supporting indexes: ' . $e->getMessage());
                    }
                } catch (\Exception $e) {
                    // Log error but don't let it crash the activation
                    error_log('[WPVDB] Error adding vector index: ' . $e->getMessage());
                }
            }
            
            return true;
        } catch (\Exception $e) {
            // Catch all exceptions to prevent activation failure
            error_log('[WPVDB] Fatal error in add_vector_index_to_existing_table: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Recreate the tables with up-to-date schema and vector support if available
     */
    public static function recreate_tables() {
        global $wpdb;
        self::init_database();
        
        $table_name = $wpdb->prefix . 'wpvdb_embeddings';
        
        // Drop the existing table
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        
        // Create the table with the current schema
        self::activate();
        
        // Add vector index with optimized parameters for MariaDB
        if (self::$database->get_db_type() === 'mariadb') {
            try {
                if (self::$database->has_native_vector_support()) {
                    // Create optimized vector index with parameters determined from our performance testing
                    // M=12 provided better performance while maintaining good accuracy
                    $wpdb->query("
                        ALTER TABLE $table_name 
                        ADD VECTOR INDEX embedding_idx(embedding) M=12 DISTANCE=cosine
                    ");
                    
                    // Add additional supporting indexes for improved join performance
                    $wpdb->query("CREATE INDEX doc_id_idx ON $table_name(doc_id)");
                    $wpdb->query("CREATE INDEX doc_type_idx ON $table_name(doc_type)");
                    $wpdb->query("CREATE INDEX model_idx ON $table_name(model)");
                    
                    // Update table statistics for optimal query planning
                    $wpdb->query("ANALYZE TABLE $table_name");
                    
                    error_log('[WPVDB] Added optimized vector index and supporting indexes to embeddings table');
                }
            } catch (\Exception $e) {
                // Ignore errors
                error_log('[WPVDB] Error adding vector index during recreation: ' . $e->getMessage());
            }
        }
        
        return true;
    }
    
    /**
     * Create meta tables for storing embedding-related metadata
     */
    private static function create_meta_tables() {
        // Reserved for future use if needed
    }
}
