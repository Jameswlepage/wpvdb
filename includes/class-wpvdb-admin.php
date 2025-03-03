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
        add_action('wp_ajax_wpvdb_validate_provider_change', [__CLASS__, 'ajax_validate_provider_change']);
        add_action('wp_ajax_wpvdb_confirm_provider_change', [__CLASS__, 'ajax_confirm_provider_change']);
        add_action('wp_ajax_wpvdb_get_posts_for_indexing', [__CLASS__, 'ajax_get_posts_for_indexing']);
        add_action('wp_ajax_wpvdb_automattic_connect', [__CLASS__, 'ajax_automattic_connect']);
        add_action('wp_ajax_wpvdb_reembed_post', [__CLASS__, 'ajax_reembed_post']);
        
        // Register settings
        add_action('admin_init', [__CLASS__, 'register_settings']);
        
        // Add settings success message
        add_action('admin_notices', [__CLASS__, 'connection_success_notice']);
        
        // Add meta box to post edit screens
        add_action('add_meta_boxes', [__CLASS__, 'register_meta_boxes']);
        
        // Add column to post list tables
        add_action('admin_init', [__CLASS__, 'register_post_columns']);
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
            [__CLASS__, 'render_admin_page'],
            'dashicons-database',
            30
        );
        
        // Replace individual submenu pages with a single page with tabs
        add_submenu_page(
            'wpvdb-dashboard',
            __('Dashboard', 'wpvdb'),
            __('Dashboard', 'wpvdb'),
            'manage_options',
            'wpvdb-dashboard',
            [__CLASS__, 'render_admin_page']
        );
        
        add_submenu_page(
            'wpvdb-dashboard',
            __('Embeddings', 'wpvdb'),
            __('Embeddings', 'wpvdb'),
            'manage_options',
            'wpvdb-embeddings',
            [__CLASS__, 'render_admin_page']
        );
        
        add_submenu_page(
            'wpvdb-dashboard',
            __('Settings', 'wpvdb'),
            __('Settings', 'wpvdb'),
            'manage_options',
            'wpvdb-settings',
            [__CLASS__, 'render_admin_page']
        );
        
        // Add new Status page
        add_submenu_page(
            'wpvdb-dashboard',
            __('Status', 'wpvdb'),
            __('Status', 'wpvdb'),
            'manage_options',
            'wpvdb-status',
            [__CLASS__, 'render_admin_page']
        );
        
        // Add hidden Automattic connection page
        add_submenu_page(
            null, // Don't show in menu
            __('Connect to Automattic AI', 'wpvdb'),
            __('Connect to Automattic AI', 'wpvdb'),
            'manage_options',
            'wpvdb-automattic-connect',
            [__CLASS__, 'render_automattic_connect_page']
        );
    }
    
    /**
     * Register plugin settings
     */
    public static function register_settings() {
        register_setting(
            'wpvdb_settings', 
            'wpvdb_settings',
            [__CLASS__, 'validate_settings']
        );
        
        // Initialize default settings if they don't exist
        if (false === get_option('wpvdb_settings')) {
            $default_settings = [
                'provider' => 'openai',
                'openai' => [
                    'api_key' => '',
                    'default_model' => 'text-embedding-3-small',
                ],
                'automattic' => [
                    'api_key' => '',
                    'default_model' => 'automattic-embeddings-001',
                ],
                'chunk_size' => 1000,
                'chunk_overlap' => 200,
                'auto_embed' => false,
                'post_types' => ['post', 'page'],
                'active_provider' => '',
                'active_model' => '',
                'pending_provider' => '',
                'pending_model' => '',
            ];
            
            add_option('wpvdb_settings', $default_settings);
        }
        
        // Migrate existing settings to new structure if needed
        self::maybe_migrate_settings();
    }
    
    /**
     * Validate settings and handle provider/model changes
     */
    public static function validate_settings($input) {
        // Get the current settings
        $current_settings = get_option('wpvdb_settings');
        
        // Make sure post_types is always an array
        if (isset($input['post_types']) && !is_array($input['post_types'])) {
            $input['post_types'] = [$input['post_types']];
        }
        
        // Special handling for Automattic connection/disconnection
        if (isset($input['automattic']['api_key'])) {
            // If API key changed from empty to non-empty or vice versa, 
            // this is either a new connection or a disconnection
            $was_connected = !empty($current_settings['automattic']['api_key']);
            $is_connected = !empty($input['automattic']['api_key']);
            
            if ($was_connected !== $is_connected) {
                // Connection status changed
                if ($is_connected) {
                    // New connection - if provider is automattic, make it active immediately
                    if ($input['provider'] === 'automattic') {
                        $input['active_provider'] = 'automattic';
                        $input['active_model'] = $input['automattic']['default_model'];
                        $input['pending_provider'] = '';
                        $input['pending_model'] = '';
                    }
                } else {
                    // Disconnection - if active provider is automattic, switch to OpenAI if available
                    if ($current_settings['active_provider'] === 'automattic') {
                        // Check if OpenAI is configured
                        if (!empty($current_settings['openai']['api_key'])) {
                            $input['provider'] = 'openai';
                            $input['active_provider'] = 'openai';
                            $input['active_model'] = $current_settings['openai']['default_model'];
                            $input['pending_provider'] = '';
                            $input['pending_model'] = '';
                        } else {
                            // Neither provider is configured, clear active
                            $input['active_provider'] = '';
                            $input['active_model'] = '';
                            $input['pending_provider'] = '';
                            $input['pending_model'] = '';
                        }
                    }
                }
                
                // Skip the rest of provider change validation
                return $input;
            }
        }
        
        // Check if we have active provider/model defined yet
        if (empty($current_settings['active_provider']) && empty($current_settings['active_model'])) {
            // This is the first-time setup - set active and pending to the current selection
            $input['active_provider'] = $input['provider'];
            $input['active_model'] = $input['provider'] === 'openai' 
                ? $input['openai']['default_model'] 
                : $input['automattic']['default_model'];
            
            // Clear pending values
            $input['pending_provider'] = '';
            $input['pending_model'] = '';
        } else {
            // Check if provider/model changed
            $current_provider = $current_settings['active_provider'];
            $current_model = $current_settings['active_model'];
            
            $new_provider = $input['provider'];
            $new_model = $new_provider === 'openai' 
                ? $input['openai']['default_model'] 
                : $input['automattic']['default_model'];
            
            // If provider or model changed, set pending values
            if ($current_provider !== $new_provider || $current_model !== $new_model) {
                // Keep the old provider/model as active
                $input['active_provider'] = $current_settings['active_provider'];
                $input['active_model'] = $current_settings['active_model'];
                
                // Store the new provider/model as pending
                $input['pending_provider'] = $new_provider;
                $input['pending_model'] = $new_model;
                
                // Add admin notice about pending change
                add_action('admin_notices', [__CLASS__, 'show_pending_change_notice']);
            }
        }
        
        return $input;
    }
    
    /**
     * Show admin notice about pending provider/model change
     */
    public static function show_pending_change_notice() {
        $settings = get_option('wpvdb_settings');
        
        if (!empty($settings['pending_provider']) || !empty($settings['pending_model'])) {
            $active_provider_name = $settings['active_provider'] === 'openai' ? 'OpenAI' : 'Automattic AI';
            $active_model = $settings['active_model'];
            
            $pending_provider_name = $settings['pending_provider'] === 'openai' ? 'OpenAI' : 'Automattic AI';
            $pending_model = $settings['pending_model'];
            
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php esc_html_e('Vector Database: Provider/Model Change Pending', 'wpvdb'); ?></strong>
                </p>
                <p>
                    <?php printf(
                        esc_html__('You\'ve requested to change the embedding provider/model from %1$s (%2$s) to %3$s (%4$s). This change requires re-indexing all content.', 'wpvdb'),
                        '<strong>' . esc_html($active_provider_name) . '</strong>',
                        '<code>' . esc_html($active_model) . '</code>',
                        '<strong>' . esc_html($pending_provider_name) . '</strong>',
                        '<code>' . esc_html($pending_model) . '</code>'
                    ); ?>
                </p>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wpvdb-status')); ?>" class="button button-primary">
                        <?php esc_html_e('Re-index Content Now', 'wpvdb'); ?>
                    </a>
                    <a href="#" class="button" id="wpvdb-cancel-provider-change">
                        <?php esc_html_e('Cancel Change', 'wpvdb'); ?>
                    </a>
                </p>
            </div>
            <script>
            jQuery(document).ready(function($) {
                $('#wpvdb-cancel-provider-change').on('click', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'wpvdb_confirm_provider_change',
                            nonce: '<?php echo wp_create_nonce('wpvdb-admin'); ?>',
                            cancel: true
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            }
                        }
                    });
                });
            });
            </script>
            <?php
        }
    }
    
    /**
     * Migrate old settings structure to new one with provider support
     */
    private static function maybe_migrate_settings() {
        $settings = get_option('wpvdb_settings');
        
        if (isset($settings['api_key']) && !isset($settings['provider'])) {
            // This is an old settings structure, migrate it
            $new_settings = [
                'provider' => 'openai',
                'openai' => [
                    'api_key' => $settings['api_key'] ?? '',
                    'default_model' => $settings['default_model'] ?? 'text-embedding-3-small',
                ],
                'automattic' => [
                    'api_key' => '',
                    'default_model' => 'automattic-embeddings-001',
                ],
                'chunk_size' => $settings['chunk_size'] ?? 1000,
                'chunk_overlap' => $settings['chunk_overlap'] ?? 200,
                'auto_embed' => $settings['auto_embed'] ?? false,
                'post_types' => $settings['post_types'] ?? ['post', 'page'],
                // Set active provider to match the old settings
                'active_provider' => 'openai',
                'active_model' => $settings['default_model'] ?? 'text-embedding-3-small',
                'pending_provider' => '',
                'pending_model' => '',
            ];
            
            update_option('wpvdb_settings', $new_settings);
        } elseif (!isset($settings['active_provider'])) {
            // Add active provider fields if they don't exist yet
            $settings['active_provider'] = $settings['provider'];
            $settings['active_model'] = $settings['provider'] === 'openai' 
                ? $settings['openai']['default_model'] 
                : $settings['automattic']['default_model'];
            $settings['pending_provider'] = '';
            $settings['pending_model'] = '';
            
            update_option('wpvdb_settings', $settings);
        }
    }
    
    /**
     * Main function to render admin pages with tabs
     */
    public static function render_admin_page() {
        $current_tab = self::get_current_tab();
        $tabs = self::get_admin_tabs();
        
        echo '<div class="wrap wpvdb-admin">';
        echo '<h1>' . esc_html__('Vector Database', 'wpvdb') . '</h1>';
        
        echo '<nav class="nav-tab-wrapper woo-nav-tab-wrapper">';
        foreach ($tabs as $tab_id => $tab_label) {
            $active = $current_tab === $tab_id ? ' nav-tab-active' : '';
            $url = admin_url('admin.php?page=wpvdb-' . $tab_id);
            echo '<a href="' . esc_url($url) . '" class="nav-tab' . $active . '">' . esc_html($tab_label) . '</a>';
        }
        echo '</nav>';
        
        // Load the appropriate view based on the current tab
        self::load_tab_content($current_tab);
        
        echo '</div>';
    }
    
    /**
     * Get the current tab from the page parameter
     */
    private static function get_current_tab() {
        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'wpvdb-dashboard';
        $tab = str_starts_with($page, 'wpvdb-') ? substr($page, 6) : 'dashboard';
        
        return $tab;
    }
    
    /**
     * Get the current section from the section parameter
     */
    private static function get_current_section() {
        return isset($_GET['section']) ? sanitize_text_field($_GET['section']) : '';
    }
    
    /**
     * Define available admin tabs
     */
    private static function get_admin_tabs() {
        return [
            'dashboard' => __('Dashboard', 'wpvdb'),
            'embeddings' => __('Embeddings', 'wpvdb'),
            'settings' => __('Settings', 'wpvdb'),
            'status' => __('Status', 'wpvdb'),
        ];
    }
    
    /**
     * Load the view file for the current tab
     */
    private static function load_tab_content($tab) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpvdb_embeddings';
        $settings = get_option('wpvdb_settings');
        $section = self::get_current_section();
        
        switch ($tab) {
            case 'dashboard':
                // Get statistics
                $total_embeddings = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
                $total_docs = $wpdb->get_var("SELECT COUNT(DISTINCT doc_id) FROM {$table_name}");
                $storage_used = $wpdb->get_var("SELECT SUM(LENGTH(embedding)) FROM {$table_name}");
                $storage_used = size_format($storage_used ?: 0);
                
                include WPVDB_PLUGIN_DIR . 'admin/views/dashboard.php';
                break;
                
            case 'settings':
                // Get settings
                $provider = $settings['provider'] ?? 'openai';
                
                // Check if we have a pending provider change
                $has_pending_change = !empty($settings['pending_provider']) || !empty($settings['pending_model']);
                
                include WPVDB_PLUGIN_DIR . 'admin/views/settings.php';
                break;
                
            case 'embeddings':
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
                break;
                
            case 'status':
                // Check if we need to perform a re-index
                $has_pending_change = !empty($settings['pending_provider']) || !empty($settings['pending_model']);
                
                // Database information 
                $db_info = [
                    'db_version' => $wpdb->db_version(),
                    'prefix' => $wpdb->prefix,
                    'charset' => $wpdb->charset,
                    'collate' => $wpdb->collate,
                    'table_exists' => $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name,
                    // Add these fields that are expected in the template
                    'table_version' => get_option('wpvdb_db_version', '1.0'),
                    'total_embeddings' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}"),
                    'total_documents' => $wpdb->get_var("SELECT COUNT(DISTINCT doc_id) FROM {$table_name}"),
                    'storage_used' => size_format($wpdb->get_var("SELECT SUM(LENGTH(embedding)) FROM {$table_name}") ?: 0),
                ];
                
                // Table structure
                $table_structure = [];
                if ($db_info['table_exists']) {
                    $table_structure = $wpdb->get_results("DESCRIBE {$table_name}");
                }
                
                // Database statistics
                $db_stats = [
                    'total_embeddings' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}"),
                    'total_docs' => $wpdb->get_var("SELECT COUNT(DISTINCT doc_id) FROM {$table_name}"),
                    'storage_used' => size_format($wpdb->get_var("SELECT SUM(LENGTH(embedding)) FROM {$table_name}") ?: 0),
                    'avg_embedding_size' => size_format($wpdb->get_var("SELECT AVG(LENGTH(embedding)) FROM {$table_name}") ?: 0),
                    'largest_embedding' => size_format($wpdb->get_var("SELECT MAX(LENGTH(embedding)) FROM {$table_name}") ?: 0),
                    'avg_chunk_content_size' => size_format($wpdb->get_var("SELECT AVG(LENGTH(chunk_content)) FROM {$table_name}") ?: 0),
                ];
                
                // Embedding provider information
                $embedding_info = [
                    'active_provider' => $settings['active_provider'] ?? '',
                    'active_model' => $settings['active_model'] ?? '',
                    'pending_provider' => $settings['pending_provider'] ?? '',
                    'pending_model' => $settings['pending_model'] ?? '',
                ];
                
                // System information
                $system_info = [
                    'php_version' => phpversion(),
                    'wp_version' => get_bloginfo('version'),
                    'wp_memory_limit' => WP_MEMORY_LIMIT,
                    'max_execution_time' => ini_get('max_execution_time'),
                    'post_max_size' => ini_get('post_max_size'),
                    'max_input_vars' => ini_get('max_input_vars'),
                    'mysql_version' => $wpdb->db_version(),
                    'curl_version' => function_exists('curl_version') ? curl_version()['version'] : __('Not available', 'wpvdb'),
                    'openai_api_key_set' => !empty($settings['openai']['api_key'] ?? ''),
                    'automattic_api_key_set' => !empty($settings['automattic']['api_key'] ?? ''),
                    'vector_db_support' => \WPVDB\Activation::has_native_vector_support(),
                ];
                
                include WPVDB_PLUGIN_DIR . 'admin/views/status.php';
                break;
                
            default:
                // Default to dashboard
                include WPVDB_PLUGIN_DIR . 'admin/views/dashboard.php';
                break;
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public static function enqueue_admin_assets($hook) {
        // Add our own WooCommerce-like styles
        wp_enqueue_style(
            'wpvdb-admin-styles',
            WPVDB_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WPVDB_VERSION
        );
        
        // Add inline styles for the embedding column icon
        $custom_css = "
            .column-wpvdb_embedded {
                width: 60px;
                text-align: center;
            }
            .column-wpvdb_embedded .dashicons {
                margin-top: 3px;
            }
            .wpvdb-status-container {
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100%;
            }
            .wpvdb-status-dot {
                display: inline-block;
                width: 12px;
                height: 12px;
                border-radius: 50%;
            }
            .wpvdb-status-dot.embedded {
                background-color: #46b450;
            }
            .wpvdb-status-dot.not-embedded {
                background-color: #dc3232;
            }
        ";
        wp_add_inline_style('wpvdb-admin-styles', $custom_css);
        
        // Only load JS on plugin admin pages
        if ($hook && str_contains($hook, 'wpvdb')) {
            wp_enqueue_script(
                'wpvdb-admin-scripts',
                WPVDB_PLUGIN_URL . 'assets/js/admin.js',
                ['jquery'],
                WPVDB_VERSION,
                true
            );
            
            // Pass AJAX URL and nonce to script
            wp_localize_script('wpvdb-admin-scripts', 'wpvdb', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wpvdb-admin'),
                'i18n' => [
                    'confirm_delete' => __('Are you sure you want to delete this embedding?', 'wpvdb'),
                    'processing' => __('Processing...', 'wpvdb'),
                    'confirm_provider_change' => __('WARNING: Changing the embedding provider or model requires re-indexing ALL content. This will delete all existing embeddings. Are you sure you want to continue?', 'wpvdb'),
                ]
            ]);
        }
    }
    
    /**
     * Ajax handler for validating provider/model changes
     */
    public static function ajax_validate_provider_change() {
        check_ajax_referer('wpvdb-admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'wpvdb')]);
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpvdb_embeddings';
        
        // Check if we have existing embeddings
        $total_embeddings = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        
        if ($total_embeddings > 0) {
            // We have embeddings, changing provider requires re-index
            wp_send_json_success([
                'requires_reindex' => true,
                'embedding_count' => $total_embeddings,
            ]);
        } else {
            // No embeddings, we can change provider directly
            wp_send_json_success([
                'requires_reindex' => false,
            ]);
        }
    }
    
    /**
     * Ajax handler for confirming provider/model changes
     */
    public static function ajax_confirm_provider_change() {
        check_ajax_referer('wpvdb-admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'wpvdb')]);
        }
        
        $cancel = isset($_POST['cancel']) && $_POST['cancel'] === 'true';
        $settings = get_option('wpvdb_settings');
        
        if ($cancel) {
            // User wants to cancel the pending change
            $settings['provider'] = $settings['active_provider'];
            if ($settings['active_provider'] === 'openai') {
                $settings['openai']['default_model'] = $settings['active_model'];
            } else {
                $settings['automattic']['default_model'] = $settings['active_model'];
            }
            
            // Clear pending provider/model
            $settings['pending_provider'] = '';
            $settings['pending_model'] = '';
            
            update_option('wpvdb_settings', $settings);
            
            wp_send_json_success([
                'message' => __('Provider change cancelled', 'wpvdb'),
            ]);
        } else {
            // User confirms the provider change
            global $wpdb;
            $table_name = $wpdb->prefix . 'wpvdb_embeddings';
            
            // Delete all existing embeddings
            $wpdb->query("TRUNCATE TABLE {$table_name}");
            
            // Activate the pending provider/model
            if (!empty($settings['pending_provider']) && !empty($settings['pending_model'])) {
                $settings['active_provider'] = $settings['pending_provider'];
                $settings['active_model'] = $settings['pending_model'];
                $settings['pending_provider'] = '';
                $settings['pending_model'] = '';
                
                update_option('wpvdb_settings', $settings);
                
                wp_send_json_success([
                    'message' => __('Provider changed successfully. All embeddings have been deleted. Please re-index your content.', 'wpvdb'),
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('No pending provider change found.', 'wpvdb'),
                ]);
            }
        }
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
        $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : '';
        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : 'openai';
        
        if (empty($post_ids)) {
            wp_send_json_error(['message' => __('No posts selected', 'wpvdb')]);
        }
        
        // Get settings
        $settings = get_option('wpvdb_settings');
        
        // Check if we're using the active provider/model or if we're re-indexing for a pending change
        $using_pending = false;
        if (!empty($settings['pending_provider']) && $provider === $settings['pending_provider']) {
            if ($provider === 'openai' && $model === $settings['pending_model']) {
                $using_pending = true;
            } elseif ($provider === 'automattic' && $model === $settings['pending_model']) {
                $using_pending = true;
            }
        }
        
        // If no pending change or not using the pending provider/model, use active one
        if (!$using_pending) {
            $provider = $settings['active_provider'] ?: $provider;
            
            // Get the default model if not specified
            if (empty($model)) {
                if ($provider === 'automattic') {
                    $model = $settings['active_model'] ?: $settings['automattic']['default_model'] ?: 'automattic-embeddings-001';
                } else {
                    $model = $settings['active_model'] ?: $settings['openai']['default_model'] ?: 'text-embedding-3-small';
                }
            }
        }
        
        // Queue posts for background processing
        $queue = new WPVDB_Queue();
        
        foreach ($post_ids as $post_id) {
            $queue->push_to_queue([
                'post_id' => $post_id,
                'model' => $model,
                'provider' => $provider,
            ]);
        }
        
        $queue->save()->dispatch();
        
        wp_send_json_success([
            'message' => sprintf(
                __('Queued %d posts for embedding generation', 'wpvdb'),
                count($post_ids)
            ),
            'using_pending' => $using_pending,
        ]);
    }
    
    /**
     * Ajax handler to get posts for indexing
     */
    public static function ajax_get_posts_for_indexing() {
        check_ajax_referer('wpvdb-admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'wpvdb')]);
        }
        
        $settings = get_option('wpvdb_settings');
        
        // Get post_type from request or use default from settings
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : null;
        $post_types = $post_type ? [$post_type] : ($settings['post_types'] ?? ['post', 'page']);
        
        // Get limit from request or use default of 10
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 10;
        
        $args = [
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'fields' => 'ids',
        ];
        
        $query = new \WP_Query($args);
        $post_ids = $query->posts;
        
        $posts = [];
        foreach ($post_ids as $post_id) {
            $posts[] = [
                'id' => $post_id,
                'title' => get_the_title($post_id),
            ];
        }
        
        wp_send_json_success([
            'posts' => $posts,
            'count' => count($posts),
        ]);
    }
    
    /**
     * Show connection success notice
     */
    public static function connection_success_notice() {
        if (isset($_GET['page']) && $_GET['page'] === 'wpvdb-settings' && isset($_GET['automattic_connected'])) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php esc_html_e('Success!', 'wpvdb'); ?></strong> <?php esc_html_e('Your Automattic AI account has been connected successfully.', 'wpvdb'); ?></p>
            </div>
            <?php
        }
    }
    
    /**
     * Render Automattic connection page
     */
    public static function render_automattic_connect_page() {
        $settings = get_option('wpvdb_settings');
        include WPVDB_PLUGIN_DIR . 'admin/views/automattic-connect.php';
    }
    
    /**
     * AJAX handler for Automattic connection
     */
    public static function ajax_automattic_connect() {
        check_ajax_referer('wpvdb-admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'wpvdb')]);
        }
        
        $connect_method = isset($_POST['connect_method']) ? sanitize_text_field($_POST['connect_method']) : '';
        
        // Mock connection process
        if ($connect_method === 'one_click') {
            // Simulate getting API key from Automattic
            $mock_api_key = 'auto_' . wp_generate_password(32, false);
            
            // Update settings
            $settings = get_option('wpvdb_settings');
            $settings['provider'] = 'automattic';
            $settings['automattic']['api_key'] = $mock_api_key;
            $settings['automattic']['default_model'] = 'automattic-embeddings-001';
            update_option('wpvdb_settings', $settings);
            
            wp_send_json_success([
                'message' => __('Connected successfully to Automattic AI', 'wpvdb'),
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Invalid connection method', 'wpvdb'),
            ]);
        }
    }
    
    /**
     * Register meta boxes for post edit screens
     */
    public static function register_meta_boxes() {
        // Get supported post types from settings
        $post_types = Settings::get_auto_embed_post_types();
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'wpvdb-embedding-status',
                __('Vector Database Embeddings', 'wpvdb'),
                [__CLASS__, 'render_embedding_meta_box'],
                $post_type,
                'side',
                'default'
            );
        }
    }
    
    /**
     * Render the embedding status meta box
     * 
     * @param WP_Post $post
     */
    public static function render_embedding_meta_box($post) {
        // Check if post has embeddings
        $is_embedded = get_post_meta($post->ID, '_wpvdb_embedded', true);
        $chunks_count = get_post_meta($post->ID, '_wpvdb_chunks_count', true);
        $embedded_date = get_post_meta($post->ID, '_wpvdb_embedded_date', true);
        $embedded_model = get_post_meta($post->ID, '_wpvdb_embedded_model', true);
        
        wp_nonce_field('wpvdb_post_meta_box', 'wpvdb_post_meta_box_nonce');
        
        ?>
        <div class="wpvdb-meta-box">
            <?php if ($is_embedded) : ?>
                <div class="wpvdb-status-indicator embedded">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e('This post has embeddings', 'wpvdb'); ?>
                </div>
                
                <div class="wpvdb-embedding-info">
                    <p><strong><?php esc_html_e('Chunks:', 'wpvdb'); ?></strong> <?php echo esc_html($chunks_count); ?></p>
                    <p><strong><?php esc_html_e('Date:', 'wpvdb'); ?></strong> <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($embedded_date))); ?></p>
                    <?php if ($embedded_model) : ?>
                        <p><strong><?php esc_html_e('Model:', 'wpvdb'); ?></strong> <?php echo esc_html($embedded_model); ?></p>
                    <?php endif; ?>
                </div>
                
                <button id="wpvdb-reembed-post" class="button" data-post-id="<?php echo esc_attr($post->ID); ?>">
                    <?php esc_html_e('Re-generate Embeddings', 'wpvdb'); ?>
                </button>
                
                <div id="wpvdb-reembed-status" style="display:none; margin-top: 10px;"></div>
            <?php else : ?>
                <div class="wpvdb-status-indicator not-embedded">
                    <span class="dashicons dashicons-no-alt"></span>
                    <?php esc_html_e('This post has no embeddings', 'wpvdb'); ?>
                </div>
                
                <button id="wpvdb-reembed-post" class="button" data-post-id="<?php echo esc_attr($post->ID); ?>">
                    <?php esc_html_e('Generate Embeddings', 'wpvdb'); ?>
                </button>
                
                <div id="wpvdb-reembed-status" style="display:none; margin-top: 10px;"></div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#wpvdb-reembed-post').on('click', function(e) {
                e.preventDefault();
                
                var postId = $(this).data('post-id');
                var statusDiv = $('#wpvdb-reembed-status');
                
                // Show status
                statusDiv.show().html('<p><em><?php esc_html_e('Processing...', 'wpvdb'); ?></em></p>');
                
                // Disable button
                $(this).prop('disabled', true);
                
                // Make AJAX call
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpvdb_reembed_post',
                        nonce: '<?php echo wp_create_nonce('wpvdb-admin'); ?>',
                        post_id: postId
                    },
                    success: function(response) {
                        if (response.success) {
                            statusDiv.html('<p class="wpvdb-success">' + response.data.message + '</p>');
                            // Reload page after 2 seconds to show updated meta
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        } else {
                            statusDiv.html('<p class="wpvdb-error">Error: ' + response.data.message + '</p>');
                            // Re-enable button
                            $('#wpvdb-reembed-post').prop('disabled', false);
                        }
                    },
                    error: function() {
                        statusDiv.html('<p class="wpvdb-error"><?php esc_html_e('An unexpected error occurred. Please try again.', 'wpvdb'); ?></p>');
                        // Re-enable button
                        $('#wpvdb-reembed-post').prop('disabled', false);
                    }
                });
            });
        });
        </script>
        
        <style>
        .wpvdb-meta-box {
            margin-bottom: 15px;
        }
        .wpvdb-status-indicator {
            margin-bottom: 15px;
            font-weight: 600;
        }
        .wpvdb-status-indicator.embedded {
            color: #46b450;
        }
        .wpvdb-status-indicator.not-embedded {
            color: #dc3232;
        }
        .wpvdb-embedding-info {
            margin-bottom: 15px;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #e5e5e5;
        }
        .wpvdb-embedding-info p {
            margin: 5px 0;
        }
        .wpvdb-success {
            color: #46b450;
        }
        .wpvdb-error {
            color: #dc3232;
        }
        </style>
        <?php
    }
    
    /**
     * Register columns for post list tables
     */
    public static function register_post_columns() {
        // Get supported post types from settings
        $post_types = Settings::get_auto_embed_post_types();
        
        foreach ($post_types as $post_type) {
            add_filter("manage_{$post_type}_posts_columns", [__CLASS__, 'add_embedding_column']);
            add_action("manage_{$post_type}_posts_custom_column", [__CLASS__, 'render_embedding_column'], 10, 2);
        }
    }
    
    /**
     * Add embedding column to post list tables
     * 
     * @param array $columns
     * @return array
     */
    public static function add_embedding_column($columns) {
        // Add the embeddings column at the end
        $columns['wpvdb_embedded'] = '<span class="dashicons dashicons-database" title="' . esc_attr__('Embeddings', 'wpvdb') . '"></span><span class="screen-reader-text">' . __('Embeddings', 'wpvdb') . '</span>';
        
        return $columns;
    }
    
    /**
     * Render the embedding column content
     * 
     * @param string $column_name
     * @param int $post_id
     */
    public static function render_embedding_column($column_name, $post_id) {
        if ($column_name !== 'wpvdb_embedded') {
            return;
        }
        
        $is_embedded = get_post_meta($post_id, '_wpvdb_embedded', true);
        $chunks_count = get_post_meta($post_id, '_wpvdb_chunks_count', true);
        
        if ($is_embedded) {
            echo '<div class="wpvdb-status-container"><span class="wpvdb-status-dot embedded" title="' . 
                esc_attr(sprintf(__('Embedded (%d chunks)', 'wpvdb'), $chunks_count)) . 
                '"></span></div>';
        } else {
            echo '<div class="wpvdb-status-container"><span class="wpvdb-status-dot not-embedded" title="' . 
                esc_attr(__('Not embedded', 'wpvdb')) . 
                '"></span></div>';
        }
    }
    
    /**
     * AJAX handler for re-embedding a post
     */
    public static function ajax_reembed_post() {
        check_ajax_referer('wpvdb-admin', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied', 'wpvdb')]);
        }
        
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID', 'wpvdb')]);
        }
        
        // Get the post
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(['message' => __('Post not found', 'wpvdb')]);
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
        
        // Queue for re-embedding
        $queue = new WPVDB_Queue();
        $queue->push_to_queue([
            'post_id' => $post_id,
            'model' => Settings::get_default_model(),
        ]);
        $queue->save()->dispatch();
        
        wp_send_json_success([
            'message' => __('Post queued for embedding generation', 'wpvdb'),
        ]);
    }
} 