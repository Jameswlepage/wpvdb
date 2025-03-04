<?php
namespace WPVDB;

use wpdb;

defined('ABSPATH') || exit;

class Activation {

    /**
     * Plugin activation routine.
     *  - Checks DB capabilities.
     *  - Creates or upgrades our custom table schema using dbDelta.
     */
    public static function activate() {
        global $wpdb;

        // Silence errors during activation to prevent "headers already sent"
        $wpdb->hide_errors();
        $show_errors = $wpdb->show_errors;
        $wpdb->show_errors = false;
        $old_error_reporting = error_reporting(0);
        
        // Check database version
        self::check_db_version_or_warn();

        // Prepare table schema.
        $table_name = $wpdb->prefix . 'wpvdb_embeddings';
        $charset_collate = $wpdb->get_charset_collate();

        // Determine if we have native vector support.
        if (Database::has_native_vector_support()) {
            // Use VECTOR(...) column if MariaDB ≥ 11.7 or MySQL ≥ 9.0 supports it.
            $dimensions = WPVDB_DEFAULT_EMBED_DIM; // Example dimension.
            
            try {
                // Get the appropriate column type from Database class
                $vector_column_type = Database::get_embedding_column_type($dimensions);
                
                // Create table with VECTOR type but without VECTOR INDEX (not supported in MySQL 9.2)
                $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    doc_id BIGINT UNSIGNED NOT NULL,
                    chunk_id VARCHAR(100) NOT NULL,
                    chunk_content LONGTEXT NOT NULL,
                    embedding $vector_column_type NOT NULL,
                    summary LONGTEXT NULL,
                    PRIMARY KEY (id),
                    INDEX (doc_id)
                ) $charset_collate;";
                
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                dbDelta($sql);
                
                // Check if table was created successfully
                if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                    // If failed with VECTOR syntax, fall back to non-vector version
                    self::create_fallback_table($table_name, $charset_collate);
                }
            } catch (\Exception $e) {
                // If any error occurs, fall back to non-vector version
                self::create_fallback_table($table_name, $charset_collate);
            }
        } else {
            // Fallback: store embedding as LONGTEXT
            self::create_fallback_table($table_name, $charset_collate);
        }
        
        // Restore error reporting
        error_reporting($old_error_reporting);
        $wpdb->show_errors = $show_errors;
    }
    
    /**
     * Create a fallback non-vector table for compatibility
     * 
     * @param string $table_name Table name
     * @param string $charset_collate Charset and collation
     */
    private static function create_fallback_table($table_name, $charset_collate) {
        global $wpdb;
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            doc_id BIGINT UNSIGNED NOT NULL,
            chunk_id VARCHAR(100) NOT NULL,
            chunk_content LONGTEXT NOT NULL,
            embedding LONGTEXT NOT NULL,
            summary LONGTEXT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        
        // Set flag that we're using fallback storage
        update_option('wpvdb_using_fallback_storage', 1, true);
    }

    /**
     * If the DB does not have vector support, show an admin_notice warning that performance
     * will degrade or some features won't be available.
     * (We can't actually do that in activation hook alone, we can store an option.)
     */
    public static function check_db_version_or_warn() {
        if (!Database::has_native_vector_support()) {
            // Record an admin notice to be displayed or log something.
            update_option('wpvdb_db_vector_support_warning', 1, true);
        } else {
            delete_option('wpvdb_db_vector_support_warning');
        }
    }

    /**
     * Create tables directly using DROP/CREATE approach 
     * This is more aggressive than the normal activation but can help when tables fail to create
     */
    public static function recreate_tables_force() {
        global $wpdb;
        
        // Silence errors during activation to prevent "headers already sent"
        $wpdb->hide_errors();
        $show_errors = $wpdb->show_errors;
        $wpdb->show_errors = false;
        $old_error_reporting = error_reporting(0);
        $old_display_errors = ini_get('display_errors');
        ini_set('display_errors', 0);
        
        // Check database version
        self::check_db_version_or_warn();
        
        // Table name
        $table_name = $wpdb->prefix . 'wpvdb_embeddings';
        $charset_collate = $wpdb->get_charset_collate();
        
        // First drop the table to ensure clean creation
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        
        // Check if we have vector support
        $has_vector = Database::has_native_vector_support();
        $vector_table_created = false;
        
        if ($has_vector) {
            $dimensions = WPVDB_DEFAULT_EMBED_DIM;
            
            try {
                // Get the appropriate column type from Database class
                $vector_column_type = Database::get_embedding_column_type($dimensions);
                
                // Create table with VECTOR type but without VECTOR INDEX (not supported in MySQL 9.2)
                $sql = "CREATE TABLE {$table_name} (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    doc_id BIGINT UNSIGNED NOT NULL,
                    chunk_id VARCHAR(100) NOT NULL,
                    chunk_content LONGTEXT NOT NULL,
                    embedding {$vector_column_type} NOT NULL,
                    summary LONGTEXT NULL,
                    PRIMARY KEY (id),
                    INDEX (doc_id)
                ) {$charset_collate};";
                
                $result = $wpdb->query($sql);
                
                // Check if the table was created
                if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
                    $vector_table_created = true;
                    delete_option('wpvdb_using_fallback_storage');
                }
            } catch (\Exception $e) {
                // If error, we'll fall back to non-vector version below
                $vector_table_created = false;
            }
        }
        
        // If vector table creation failed or is not supported, create fallback
        if (!$vector_table_created) {
            // Create the fallback table directly (not using dbDelta)
            $sql = "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                doc_id BIGINT UNSIGNED NOT NULL,
                chunk_id VARCHAR(100) NOT NULL,
                chunk_content LONGTEXT NOT NULL,
                embedding LONGTEXT NOT NULL,
                summary LONGTEXT NULL,
                PRIMARY KEY (id)
            ) {$charset_collate};";
            
            $wpdb->query($sql);
            
            // Set flag that we're using fallback storage
            update_option('wpvdb_using_fallback_storage', 1, true);
        }
        
        // Restore error reporting
        error_reporting($old_error_reporting);
        $wpdb->show_errors = $show_errors;
        ini_set('display_errors', $old_display_errors);
        
        return ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name);
    }
}
