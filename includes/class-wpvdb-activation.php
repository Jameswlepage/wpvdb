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

        self::check_db_version_or_warn();

        // Prepare table schema.
        $table_name = $wpdb->prefix . 'wpvdb_embeddings';
        $charset_collate = $wpdb->get_charset_collate();

        // Determine if we have native vector support.
        if (self::has_native_vector_support()) {
            // Use VECTOR(...) column if MariaDB ≥ 11.7 or MySQL ≥ 9.0 supports it.
            $dimensions = WPVDB_DEFAULT_EMBED_DIM; // Example dimension.
            // For demonstration, we use COSINE index (or EUCLIDEAN).
            // Add 'VECTOR INDEX' with distance=cosine or euclidean. M=8 is a typical HNSW param.
            $sql = "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                doc_id BIGINT UNSIGNED NOT NULL,
                chunk_id VARCHAR(100) NOT NULL,
                chunk_content LONGTEXT NOT NULL,
                embedding VECTOR($dimensions) NOT NULL,
                summary LONGTEXT NULL,
                PRIMARY KEY (id),
                VECTOR INDEX (embedding) M=8 DISTANCE=cosine
            ) $charset_collate;";

        } else {
            // Fallback: store embedding as LONGTEXT or MEDIUMTEXT (JSON or similar).
            // We'll do a LONGTEXT for demonstration.
            $sql = "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                doc_id BIGINT UNSIGNED NOT NULL,
                chunk_id VARCHAR(100) NOT NULL,
                chunk_content LONGTEXT NOT NULL,
                embedding LONGTEXT NOT NULL,
                summary LONGTEXT NULL,
                PRIMARY KEY (id)
            ) $charset_collate;";
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Check if the DB is MariaDB ≥ 11.7 or MySQL ≥ 9.0 with vector support.
     * Returns boolean true if supported, false if not.
     */
    public static function has_native_vector_support() {
        global $wpdb;
        // Get version from server:
        $version = $wpdb->get_var("SELECT VERSION()");
        if (!$version) {
            return false;
        }

        // Basic parse logic for MariaDB or MySQL:
        // If the string has 'MariaDB' in it, parse that version, else parse MySQL version.
        if (stripos($version, 'MariaDB') !== false) {
            // Extract X.Y or X.Y.Z
            preg_match('/(\d+\.\d+)/', $version, $matches);
            if (!empty($matches[1])) {
                $ver = floatval($matches[1]);
                // MariaDB 11.7 or higher needed
                return ($ver >= 11.7);
            }
        } else {
            // MySQL branch
            preg_match('/(\d+\.\d+)/', $version, $matches);
            if (!empty($matches[1])) {
                $ver = floatval($matches[1]);
                // MySQL 9.0 or higher is hypothetical reference
                return ($ver >= 9.0);
            }
        }
        return false;
    }

    /**
     * If the DB does not have vector support, show an admin_notice warning that performance
     * will degrade or some features won't be available.
     * (We can't actually do that in activation hook alone, we can store an option.)
     */
    public static function check_db_version_or_warn() {
        if (!self::has_native_vector_support()) {
            // Record an admin notice to be displayed or log something.
            update_option('wpvdb_db_vector_support_warning', 1, true);
        } else {
            delete_option('wpvdb_db_vector_support_warning');
        }
    }
}
