<?php
namespace WPVDB;

defined('ABSPATH') || exit;

class Admin {
    
    /**
     * Initialize admin hooks and pages
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_admin_pages']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        add_action('wp_ajax_wpvdb_delete_embedding', [__CLASS__, 'ajax_delete_embedding']);
        add_action('wp_ajax_wpvdb_bulk_embed', [__CLASS__, 'ajax_bulk_embed']);
    }
    
    /**
     * Register admin pages
     */
    public static function register_admin_pages() {
        add_menu_page(
            __('Vector Database', 'wpvdb'),
            __('Vector DB', 'wpvdb'),
            'manage_options',
            'wpvdb-dashboard',
            [__CLASS__, 'render_dashboard_page'],
            'dashicons-database',
            30
        );
        
        add_submenu_page(
            'wpvdb-dashboard',
            __('Settings', 'wpvdb'),
            __('Settings', 'wpvdb'),
            'manage_options',
            'wpvdb-settings',
            [__CLASS__, 'render_settings_page']
        );
        
        add_submenu_page(
            'wpvdb-dashboard',
            __('Embeddings', 'wpvdb'),
            __('Embeddings', 'wpvdb'),
            'manage_options',
            'wpvdb-embeddings',
            [__CLASS__, 'render_embeddings_page']
        );
    }
    
    /**
     * Render dashboard page with statistics
     */
    public static function render_dashboard_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpvdb_embeddings';
        
        // Get statistics
        $total_embeddings = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $total_docs = $wpdb->get_var("SELECT COUNT(DISTINCT doc_id) FROM {$table_name}");
        $storage_used = $wpdb->get_var("SELECT SUM(LENGTH(embedding)) FROM {$table_name}");
        $storage_used = size_format($storage_used ?: 0);
        
        include WPVDB_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    
    /**
     * Render settings page
     */
    public static function render_settings_page() {
        include WPVDB_PLUGIN_DIR . 'admin/views/settings.php';
    }
    
    /**
     * Render embeddings management page
     */
    public static function render_embeddings_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpvdb_embeddings';
        
        // Get paginated embeddings
        $page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $embeddings = $wpdb->get_results($wpdb->prepare(
            "SELECT id, doc_id, chunk_id, LEFT(chunk_content, 150) as preview, summary 
             FROM {$table_name} ORDER BY id DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        ));
        
        $total_embeddings = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $total_pages = ceil($total_embeddings / $per_page);
        
        include WPVDB_PLUGIN_DIR . 'admin/views/embeddings.php';
    }
    
    /**
     * Enqueue admin assets
     */
    public static function enqueue_admin_assets($hook) {
        if (strpos($hook, 'wpvdb') === false) {
            return;
        }
        
        wp_enqueue_style(
            'wpvdb-admin-styles',
            WPVDB_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WPVDB_VERSION
        );
        
        wp_enqueue_script(
            'wpvdb-admin-scripts',
            WPVDB_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            WPVDB_VERSION,
            true
        );
        
        wp_localize_script('wpvdb-admin-scripts', 'wpvdb', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpvdb-admin'),
            'i18n' => [
                'confirm_delete' => __('Are you sure you want to delete this embedding?', 'wpvdb'),
                'processing' => __('Processing...', 'wpvdb'),
            ],
        ]);
    }
    
    /**
     * Ajax handler for deleting embeddings
     */
    public static function ajax_delete_embedding() {
        check_ajax_referer('wpvdb-admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'wpvdb')]);
        }
        
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id) {
            wp_send_json_error(['message' => __('Invalid embedding ID', 'wpvdb')]);
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpvdb_embeddings';
        $result = $wpdb->delete($table_name, ['id' => $id], ['%d']);
        
        if ($result) {
            wp_send_json_success(['message' => __('Embedding deleted successfully', 'wpvdb')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete embedding', 'wpvdb')]);
        }
    }
    
    /**
     * Ajax handler for bulk embedding
     */
    public static function ajax_bulk_embed() {
        check_ajax_referer('wpvdb-admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'wpvdb')]);
        }
        
        $post_ids = isset($_POST['post_ids']) ? array_map('absint', $_POST['post_ids']) : [];
        $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : 'text-embedding-3-small';
        
        if (empty($post_ids)) {
            wp_send_json_error(['message' => __('No posts selected', 'wpvdb')]);
        }
        
        // Queue posts for background processing
        $queue = new WPVDB_Queue();
        
        foreach ($post_ids as $post_id) {
            $queue->push_to_queue([
                'post_id' => $post_id,
                'model' => $model,
            ]);
        }
        
        $queue->save()->dispatch();
        
        wp_send_json_success([
            'message' => sprintf(
                __('Queued %d posts for embedding generation', 'wpvdb'),
                count($post_ids)
            ),
        ]);
    }
} 