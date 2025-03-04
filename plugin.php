<?php
/**
 * Plugin Name: WPVDB - WordPress Vector Database
 * Plugin URI:  https://wordpress.com/blog/wordpress-as-a-vector-database
 * Description: Transform WordPress into a vector database with native or fallback support for vector columns, chunking, embedding, and REST endpoints.
 * Version:     1.0.0
 * Author:      Automattic AI, James LePage
 * Author URI:  https://automattic.ai
 * Text Domain: wpvdb
 * Domain Path: /languages/
 *
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package WPVDB
 */

defined('ABSPATH') || exit; // No direct access.

// Define plugin version and constants.
define('WPVDB_VERSION', '1.0.1');
define('WPVDB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPVDB_PLUGIN_URL', plugin_dir_url(__FILE__));

// Optionally define a default dimension for your embeddings (e.g., 1536).
if (!defined('WPVDB_DEFAULT_EMBED_DIM')) {
    define('WPVDB_DEFAULT_EMBED_DIM', 1536);
}

// API Keys can be defined in wp-config.php for better security and environment-specific configuration
// Example:
// define('WPVDB_OPENAI_API_KEY', 'your-openai-api-key');
// define('WPVDB_AUTOMATTIC_API_KEY', 'your-automattic-api-key');

// Include the Composer autoloader
if (file_exists(WPVDB_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once WPVDB_PLUGIN_DIR . 'vendor/autoload.php';
}

// Initialize Action Scheduler
if (file_exists(WPVDB_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php')) {
    require_once WPVDB_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
}

/**
 * Check if Action Scheduler is available
 * 
 * @return bool Whether Action Scheduler is available
 */
function wpvdb_has_action_scheduler() {
    return class_exists('ActionScheduler') && function_exists('as_schedule_single_action');
}

// Include class files.
require_once WPVDB_PLUGIN_DIR . 'includes/class-wpvdb-database.php';
require_once WPVDB_PLUGIN_DIR . 'includes/class-wpvdb-activation.php';
require_once WPVDB_PLUGIN_DIR . 'includes/class-wpvdb-core.php';
require_once WPVDB_PLUGIN_DIR . 'includes/class-wpvdb-rest.php';
require_once WPVDB_PLUGIN_DIR . 'includes/class-wpvdb-query.php';
require_once WPVDB_PLUGIN_DIR . 'includes/class-wpvdb-settings.php';
require_once WPVDB_PLUGIN_DIR . 'includes/class-wpvdb-queue.php';
require_once WPVDB_PLUGIN_DIR . 'includes/class-wpvdb-admin.php';

/**
 * Activation hook.
 */
function wpvdb_activate_plugin() {
    \WPVDB\Activation::activate();
}
register_activation_hook(__FILE__, 'wpvdb_activate_plugin');

/**
 * Deactivation hook (optional: you might not want to drop tables immediately).
 */
function wpvdb_deactivate_plugin() {
    // Deactivation logic if needed (e.g., remove scheduled events).
}
register_deactivation_hook(__FILE__, 'wpvdb_deactivate_plugin');

/**
 * Plugin init: bootstrap the core and REST APIs. 
 */
function wpvdb_init_plugin() {
    // Initialize settings
    \WPVDB\Settings::init();
    
    // Initialize core logic
    \WPVDB\Core::init();
    
    // Register REST routes
    add_action('rest_api_init', ['\\WPVDB\\REST', 'register_routes']);
    
    // Extend WP_Query
    \WPVDB\Query::init();
    
    // Initialize admin interface
    if (is_admin()) {
        \WPVDB\Admin::init();
        
        // Show admin notice if Action Scheduler is missing
        if (!wpvdb_has_action_scheduler()) {
            add_action('admin_notices', 'wpvdb_action_scheduler_missing_notice');
        }
    }
    
    // Hook into post saving for auto-embedding
    add_action('wp_insert_post', ['\\WPVDB\\Core', 'auto_embed_post'], 10, 3);
    
    // Enhanced chunking filter (override default chunking)
    add_filter('wpvdb_chunk_text', ['\\WPVDB\\Core', 'enhanced_chunking'], 10, 2);
    
    // Register Action Scheduler handler (if available)
    if (wpvdb_has_action_scheduler()) {
        add_action('wpvdb_process_embedding', ['\\WPVDB\\WPVDB_Queue', 'process_item']);
        
        // Add more frequent runner for Action Scheduler
        add_action('init', 'wpvdb_maybe_run_action_scheduler');
    }
}
add_action('plugins_loaded', 'wpvdb_init_plugin');

/**
 * Run Action Scheduler more frequently in the admin
 * This helps ensure queued jobs run even without proper WP-Cron in development
 */
function wpvdb_maybe_run_action_scheduler() {
    if (is_admin() && function_exists('as_has_scheduled_action') && !wp_doing_ajax()) {
        // Check if we have any pending wpvdb actions
        if (as_has_scheduled_action('wpvdb_process_embedding', null, 'wpvdb')) {
            // Make sure scheduler runs
            if (function_exists('as_schedule_cron_action')) {
                as_schedule_cron_action(time(), '* * * * *', 'action_scheduler_run_queue', [], 'action-scheduler');
            }
        }
    }
}

/**
 * Display admin notice when Action Scheduler is missing
 */
function wpvdb_action_scheduler_missing_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    echo '<div class="notice notice-warning">';
    echo '<p><strong>WPVDB Plugin:</strong> Action Scheduler is not installed. Background processing will use a less reliable method.</p>';
    echo '<p>Please install Action Scheduler by following the instructions at <a href="https://actionscheduler.org/" target="_blank">actionscheduler.org</a>.</p>';
    echo '</div>';
}

/**
 * Process items in the fallback queue via WP Cron
 */
function wpvdb_process_fallback_queue() {
    $queue = new \WPVDB\WPVDB_Queue();
    $queue->process_fallback_queue(10); // Process 10 items at a time
}
add_action('wpvdb_process_fallback_queue', 'wpvdb_process_fallback_queue');