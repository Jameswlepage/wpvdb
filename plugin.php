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
    error_log('[WPVDB] Starting plugin initialization');
    
    // Initialize settings
    \WPVDB\Settings::init();
    
    // Check for incompatible database at plugin init time
    if (is_admin() && !wp_doing_ajax()) {
        wpvdb_check_database_compatibility();
    }
    
    // Initialize core logic
    \WPVDB\Core::init();
    
    // Initialize database hooks for cleanup operations
    \WPVDB\Database::init();
    
    error_log('[WPVDB] Registering REST routes action');
    
    // Register REST routes
    add_action('rest_api_init', function() {
        error_log('[WPVDB] rest_api_init hook triggered, calling REST::register_routes()');
        \WPVDB\REST::register_routes();
    });
    
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
    
    error_log('[WPVDB] Plugin initialization complete');
}
add_action('plugins_loaded', 'wpvdb_init_plugin');

/**
 * Check for database compatibility and handle accordingly
 */
function wpvdb_check_database_compatibility() {
    // Get the current screen to avoid notices on plugin activation
    if (function_exists('get_current_screen')) {
        $screen = get_current_screen();
        
        // Skip this check on the plugins page to avoid redirect loops
        if ($screen && in_array($screen->id, ['plugins', 'plugins-network'])) {
            return;
        }
    }
    
    // Check if we have an incompatible database notice pending
    if (get_transient('wpvdb_incompatible_db_notice')) {
        delete_transient('wpvdb_incompatible_db_notice');
        
        // Get the database info
        $db_type = \WPVDB\Database::get_db_type();
        $min_version = $db_type === 'mysql' ? '8.0.32' : '11.7';
        
        // Add admin notice for automatic deactivation
        add_action('admin_notices', function() use ($db_type, $min_version) {
            ?>
            <div class="notice notice-error">
                <p><strong><?php _e('WordPress Vector Database - Incompatible Database', 'wpvdb'); ?></strong></p>
                <p>
                    <?php printf(
                        __('Your %1$s database is not compatible with WordPress Vector Database. Vector features require %1$s version %2$s or newer.', 'wpvdb'),
                        ucfirst($db_type),
                        $min_version
                    ); ?>
                </p>
                <p>
                    <?php _e('For detailed compatibility information, please visit the Vector DB settings page before the plugin is deactivated.', 'wpvdb'); ?>
                </p>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=wpvdb-dashboard'); ?>" class="button button-primary">
                        <?php _e('View Compatibility Details', 'wpvdb'); ?>
                    </a>
                    <a href="<?php echo admin_url('plugins.php'); ?>" class="button">
                        <?php _e('Manage Plugins', 'wpvdb'); ?>
                    </a>
                </p>
            </div>
            <?php
        });
        
        // Schedule deactivation if the user hasn't taken action after 24 hours
        if (!wp_next_scheduled('wpvdb_maybe_deactivate_plugin')) {
            wp_schedule_single_event(time() + DAY_IN_SECONDS, 'wpvdb_maybe_deactivate_plugin');
        }
    }
}

/**
 * Auto deactivate plugin if the database is incompatible and the user hasn't enabled fallbacks
 */
function wpvdb_maybe_deactivate_plugin() {
    // Only run if incompatible flag is still set
    if (get_option('wpvdb_incompatible_db', false)) {
        // Double check compatibility
        if (!\WPVDB\Database::has_native_vector_support() && !\WPVDB\Database::are_fallbacks_enabled()) {
            // Deactivate plugin
            deactivate_plugins(plugin_basename(__FILE__));
            
            // Clear the flag
            delete_option('wpvdb_incompatible_db');
            
            // Set a transient to show a notice after deactivation
            set_transient('wpvdb_was_deactivated', true, 60 * 60);
            
            error_log('[WPVDB] Plugin deactivated due to incompatible database.');
            
            // If this is running in a CRON or admin context without a screen, we're done
            if (!function_exists('get_current_screen') || !get_current_screen()) {
                return;
            }
            
            // Redirect to plugins page if we're in the admin
            if (is_admin() && !wp_doing_ajax()) {
                wp_redirect(admin_url('plugins.php?deactivate=true'));
                exit;
            }
        } else {
            // Database now compatible or fallbacks enabled, clear the flag
            delete_option('wpvdb_incompatible_db');
        }
    }
}
add_action('wpvdb_maybe_deactivate_plugin', 'wpvdb_maybe_deactivate_plugin');

/**
 * Display notice after plugin deactivation
 */
function wpvdb_deactivated_notice() {
    if (get_transient('wpvdb_was_deactivated')) {
        delete_transient('wpvdb_was_deactivated');
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php _e('WordPress Vector Database has been deactivated', 'wpvdb'); ?></strong>
            </p>
            <p>
                <?php _e('The plugin was deactivated because your database does not meet the minimum requirements:', 'wpvdb'); ?>
            </p>
            <ul style="list-style-type: disc; margin-left: 20px;">
                <li><?php _e('MySQL 8.0.32 or newer', 'wpvdb'); ?></li>
                <li><?php _e('MariaDB 11.7 or newer', 'wpvdb'); ?></li>
            </ul>
            <p>
                <?php _e('Please upgrade your database or use our Docker development environment for testing.', 'wpvdb'); ?>
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'wpvdb_deactivated_notice');

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