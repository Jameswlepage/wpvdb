<?php
namespace WPVDB;

defined('ABSPATH') || exit;

class Admin {
    
    /**
     * Initialize admin hooks and pages
     */
    public static function init() {
        // Handle admin actions first, before any output occurs
        add_action('admin_init', [__CLASS__, 'handle_admin_actions']);
        
        add_action('admin_menu', [__CLASS__, 'register_admin_pages']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        add_action('wp_ajax_wpvdb_delete_embedding', [__CLASS__, 'ajax_delete_embedding']);
        add_action('wp_ajax_wpvdb_bulk_embed', [__CLASS__, 'ajax_bulk_embed']);
        add_action('wp_ajax_wpvdb_validate_provider_change', [__CLASS__, 'ajax_validate_provider_change']);
        add_action('wp_ajax_wpvdb_confirm_provider_change', [__CLASS__, 'ajax_confirm_provider_change']);
        add_action('wp_ajax_wpvdb_get_posts_for_indexing', [__CLASS__, 'ajax_get_posts_for_indexing']);
        add_action('wp_ajax_wpvdb_automattic_connect', [__CLASS__, 'ajax_automattic_connect']);
        add_action('wp_ajax_wpvdb_reembed_post', [__CLASS__, 'ajax_reembed_post']);
        add_action('wp_ajax_wpvdb_test_embedding', [__CLASS__, 'ajax_test_embedding']);
        add_action('wp_ajax_wpvdb_get_embedding_content', [__CLASS__, 'ajax_get_embedding_content']);
        
        // Register settings
        add_action('admin_init', [__CLASS__, 'register_settings']);
        
        // Add settings success message
        add_action('admin_notices', [__CLASS__, 'connection_success_notice']);
        
        // Add admin notices for action results
        add_action('admin_notices', [__CLASS__, 'admin_notices']);
        
        // Add database compatibility notice
        add_action('admin_notices', [__CLASS__, 'database_compatibility_notice']);
        
        // Add meta box to post edit screens
        add_action('add_meta_boxes', [__CLASS__, 'register_meta_boxes']);
        
        // Add column to post list tables
        add_action('admin_init', [__CLASS__, 'register_post_columns']);
        
        // Register bulk actions for embedding posts
        add_action('admin_init', [__CLASS__, 'register_bulk_embed_actions']);
        
        // Register post meta for block editor
        add_action('init', [__CLASS__, 'register_post_meta']);
        
        // Enqueue block editor assets
        add_action('enqueue_block_editor_assets', [__CLASS__, 'enqueue_editor_assets']);
    }
    
    /**
     * Check if database is compatible with vector features
     * 
     * @return bool Whether the database is compatible
     */
    public static function is_database_compatible() {
        return \WPVDB\Database::has_native_vector_support();
    }
    
    /**
     * Check if fallbacks are explicitly enabled
     * 
     * @return bool Whether fallbacks are enabled
     */
    public static function are_fallbacks_enabled() {
        return \WPVDB\Database::are_fallbacks_enabled();
    }

    /**
     * Display admin notice for incompatible databases
     */
    public static function database_compatibility_notice() {
        // Skip this notice if database is compatible or we're already on the WPVDB pages
        if (self::is_database_compatible() || self::are_fallbacks_enabled() || isset($_GET['page']) && strpos($_GET['page'], 'wpvdb-') === 0) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $db_type = \WPVDB\Database::get_db_type();
        $min_version = $db_type === 'mysql' ? '8.0.32' : '11.7';
        
        echo '<div class="notice notice-error">';
        echo '<p><strong>' . __('WordPress Vector Database - Incompatible Database', 'wpvdb') . '</strong></p>';
        echo '<p>' . sprintf(
            __('Your %1$s database is not compatible with WordPress Vector Database. Vector features require %1$s version %2$s or newer.', 'wpvdb'),
            ucfirst($db_type),
            $min_version
        ) . '</p>';
        echo '<p><a href="' . admin_url('admin.php?page=wpvdb-dashboard') . '" class="button button-primary">' . __('View Compatibility Details', 'wpvdb') . '</a></p>';
        echo '</div>';
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
        
        // If database is compatible or fallbacks are enabled, show all admin pages
        if (self::is_database_compatible() || self::are_fallbacks_enabled()) {
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
        } else {
            // Only show a single page for incompatible databases
            add_submenu_page(
                'wpvdb-dashboard',
                __('Database Compatibility', 'wpvdb'),
                __('Database Compatibility', 'wpvdb'),
                'manage_options',
                'wpvdb-dashboard',
                [__CLASS__, 'render_admin_page']
            );
        }
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
                    'default_model' => 'a8cai-embeddings-small-1',
                ],
                'chunk_size' => 1000,
                'chunk_overlap' => 200,
                'auto_embed' => false,
                'post_types' => ['post', 'page'],
                'active_provider' => '',
                'active_model' => '',
                'pending_provider' => '',
                'pending_model' => '',
                'require_auth' => 1, // Require authentication by default
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
        error_log('WPVDB Settings Input: ' . print_r($input, true));
        
        // Get the current settings
        $current_settings = get_option('wpvdb_settings', []);
        
        // If we're receiving individual wpvdb_* fields, convert them to the expected structure
        if (isset($_POST['wpvdb_openai_api_key']) || isset($_POST['wpvdb_provider'])) {
            // This is the old format with individual fields, convert to new structure
            $input = [
                'provider' => isset($_POST['wpvdb_provider']) ? sanitize_text_field($_POST['wpvdb_provider']) : 'openai',
                'openai' => [
                    'api_key' => isset($_POST['wpvdb_openai_api_key']) ? sanitize_text_field($_POST['wpvdb_openai_api_key']) : '',
                    'default_model' => isset($_POST['wpvdb_openai_model']) ? sanitize_text_field($_POST['wpvdb_openai_model']) : 'text-embedding-3-small',
                ],
                'automattic' => [
                    'api_key' => isset($_POST['wpvdb_automattic_api_key']) ? sanitize_text_field($_POST['wpvdb_automattic_api_key']) : '',
                    'default_model' => isset($_POST['wpvdb_automattic_model']) ? sanitize_text_field($_POST['wpvdb_automattic_model']) : 'a8cai-embeddings-small-1',
                ],
                'chunk_size' => isset($_POST['wpvdb_chunk_size']) ? intval($_POST['wpvdb_chunk_size']) : 1000,
                'chunk_overlap' => isset($_POST['wpvdb_chunk_overlap']) ? intval($_POST['wpvdb_chunk_overlap']) : 200,
                'auto_embed' => isset($_POST['wpvdb_auto_embed']) ? 1 : 0,
                'post_types' => isset($_POST['wpvdb_auto_embed_post_types']) && is_array($_POST['wpvdb_auto_embed_post_types']) ? $_POST['wpvdb_auto_embed_post_types'] : [],
                'enable_summarization' => isset($_POST['wpvdb_summarize_chunks']) ? 1 : 0,
                'require_auth' => isset($_POST['wpvdb_require_auth']) ? intval($_POST['wpvdb_require_auth']) : 1,
            ];
            
            // Also update individual options for backwards compatibility
            update_option('wpvdb_provider', $input['provider']);
            update_option('wpvdb_openai_api_key', $input['openai']['api_key']);
            update_option('wpvdb_openai_model', $input['openai']['default_model']);
            update_option('wpvdb_automattic_api_key', $input['automattic']['api_key']);
            update_option('wpvdb_automattic_model', $input['automattic']['default_model']);
            update_option('wpvdb_chunk_size', $input['chunk_size']);
            update_option('wpvdb_chunk_overlap', $input['chunk_overlap']);
            update_option('wpvdb_auto_embed_post_types', $input['post_types']);
            update_option('wpvdb_summarize_chunks', $input['enable_summarization']);
            update_option('wpvdb_require_auth', $input['require_auth']);
            
            error_log('WPVDB Converted Settings: ' . print_r($input, true));
        }
        
        // Ensure `$input` is an array at all
        if (!is_array($input)) {
            $input = [];
        }
        
        // Make sure each sub-array (openai, automattic) actually exists
        if (!isset($input['openai']) || !is_array($input['openai'])) {
            $input['openai'] = [];
        }
        if (!isset($input['automattic']) || !is_array($input['automattic'])) {
            $input['automattic'] = [];
        }
        
        // Make sure api_key and default_model at least exist (even if empty)
        if (!isset($input['openai']['api_key'])) {
            $input['openai']['api_key'] = '';
        }
        if (!isset($input['openai']['default_model'])) {
            $input['openai']['default_model'] = 'text-embedding-3-small';
        }
        if (!isset($input['automattic']['api_key'])) {
            $input['automattic']['api_key'] = '';
        }
        if (!isset($input['automattic']['default_model'])) {
            $input['automattic']['default_model'] = 'a8cai-embeddings-small-1';
        }
        
        // Make sure post_types is always an array
        if (isset($input['post_types']) && !is_array($input['post_types'])) {
            $input['post_types'] = [$input['post_types']];
        }

        // Explicitly handle checkboxes
        // If not set in input, they were unchecked
        $input['auto_embed'] = isset($input['auto_embed']) ? 1 : 0;
        $input['enable_summarization'] = isset($input['enable_summarization']) ? 1 : 0;
        
        // Ensure chunk_size and chunk_overlap have values
        $input['chunk_size'] = isset($input['chunk_size']) ? intval($input['chunk_size']) : 1000;
        $input['chunk_overlap'] = isset($input['chunk_overlap']) ? intval($input['chunk_overlap']) : 200;
        
        // Ensure post_types exists
        if (!isset($input['post_types']) || !is_array($input['post_types'])) {
            $input['post_types'] = isset($current_settings['post_types']) ? $current_settings['post_types'] : ['post', 'page'];
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
        $settings = get_option('wpvdb_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }
        
        // Set default values for any needed keys
        if (!isset($settings['active_provider'])) {
            $settings['active_provider'] = '';
        }
        if (!isset($settings['active_model'])) {
            $settings['active_model'] = '';
        }
        if (!isset($settings['pending_provider'])) {
            $settings['pending_provider'] = '';
        }
        if (!isset($settings['pending_model'])) {
            $settings['pending_model'] = '';
        }
        
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
        $settings = get_option('wpvdb_settings', []);
        
        // Ensure settings is an array
        if (!is_array($settings)) {
            $settings = [];
            return;
        }
        
        if (isset($settings['api_key']) && !isset($settings['provider'])) {
            // This is an old settings structure, migrate it
            $new_settings = [
                'provider' => 'openai',
                'openai' => [
                    'api_key' => isset($settings['api_key']) ? $settings['api_key'] : '',
                    'default_model' => isset($settings['default_model']) ? $settings['default_model'] : 'text-embedding-3-small',
                ],
                'automattic' => [
                    'api_key' => '',
                    'default_model' => 'automattic-embeddings-001',
                ],
                'chunk_size' => isset($settings['chunk_size']) ? $settings['chunk_size'] : 1000,
                'chunk_overlap' => isset($settings['chunk_overlap']) ? $settings['chunk_overlap'] : 200,
                'auto_embed' => isset($settings['auto_embed']) ? $settings['auto_embed'] : false,
                'post_types' => isset($settings['post_types']) ? $settings['post_types'] : ['post', 'page'],
                // Set active provider to match the old settings
                'active_provider' => 'openai',
                'active_model' => isset($settings['default_model']) ? $settings['default_model'] : 'text-embedding-3-small',
                'pending_provider' => '',
                'pending_model' => '',
            ];
            
            update_option('wpvdb_settings', $new_settings);
        } elseif (!isset($settings['active_provider'])) {
            // Ensure provider exists
            if (!isset($settings['provider'])) {
                $settings['provider'] = 'openai';
            }
            
            // Ensure provider arrays exist
            if (!isset($settings['openai']) || !is_array($settings['openai'])) {
                $settings['openai'] = [
                    'api_key' => '',
                    'default_model' => 'text-embedding-3-small'
                ];
            }
            if (!isset($settings['automattic']) || !is_array($settings['automattic'])) {
                $settings['automattic'] = [
                    'api_key' => '',
                    'default_model' => 'automattic-embeddings-001'
                ];
            }
            
            // Add active provider fields if they don't exist yet
            $settings['active_provider'] = $settings['provider'];
            $settings['active_model'] = $settings['provider'] === 'openai' 
                ? (isset($settings['openai']['default_model']) ? $settings['openai']['default_model'] : 'text-embedding-3-small') 
                : (isset($settings['automattic']['default_model']) ? $settings['automattic']['default_model'] : 'automattic-embeddings-001');
            $settings['pending_provider'] = '';
            $settings['pending_model'] = '';
            
            update_option('wpvdb_settings', $settings);
        }
    }
    
    /**
     * Render the admin page content
     */
    public static function render_admin_page() {
        // If database is not compatible and fallbacks are not enabled, show the incompatible database warning
        if (!self::is_database_compatible() && !self::are_fallbacks_enabled()) {
            include WPVDB_PLUGIN_DIR . 'admin/views/incompatible-db-warning.php';
            return;
        }

        // For compatible databases, show the regular admin pages
        $tab = self::get_current_tab();
        $section = self::get_current_section();
        
        // Default to dashboard if no tab is specified
        if (empty($tab)) {
            $tab = 'dashboard';
        }
        
        $tabs = self::get_admin_tabs();
                
        // Render admin header
        include WPVDB_PLUGIN_DIR . 'admin/views/header.php';
        
        // Render tab content
        $view_file = WPVDB_PLUGIN_DIR . 'admin/views/' . $tab . '.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="notice notice-error"><p>' . __('View file not found.', 'wpvdb') . '</p></div>';
        }
        
        // Render admin footer
        include WPVDB_PLUGIN_DIR . 'admin/views/footer.php';
    }
    
    /**
     * Get the current tab from the page parameter
     */
    private static function get_current_tab() {
        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'wpvdb-dashboard';
        // Use str_starts_with safely by ensuring $page is a string
        $tab = (is_string($page) && str_starts_with($page, 'wpvdb-')) ? substr($page, 6) : 'dashboard';
        
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
     * Enqueue assets for the admin screens
     */
    public static function enqueue_admin_assets($hook) {
        $admin_pages = ['toplevel_page_wpvdb-dashboard', 'wpvdb_page_wpvdb-embeddings', 'wpvdb_page_wpvdb-settings'];
        
        // Only load our assets on our admin pages
        if (!in_array($hook, $admin_pages)) {
            return;
        }
        
        // Core WordPress admin styles are already loaded
        
        // Enqueue custom admin styles - make these minimal and use core styles where possible
        wp_enqueue_style(
            'wpvdb-admin',
            WPVDB_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WPVDB_VERSION
        );
        
        // Main admin script
        wp_enqueue_script(
            'wpvdb-admin',
            WPVDB_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            WPVDB_VERSION,
            true
        );
        
        // Additional scripts for specific pages
        if ($hook === 'wpvdb_page_wpvdb-embeddings') {
            // Embeddings page specific script
            wp_enqueue_script(
                'wpvdb-embeddings',
                WPVDB_PLUGIN_URL . 'assets/js/embeddings.js',
                ['jquery', 'wpvdb-admin'],
                WPVDB_VERSION,
                true
            );
        }
        
        // Common data for admin scripts
        wp_localize_script('wpvdb-admin', 'wpvdb', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpvdb_ajax_nonce'),
            'strings' => [
                'confirmDelete' => __('Are you sure you want to delete this embedding?', 'wpvdb'),
                'processing' => __('Processing...', 'wpvdb'),
                'complete' => __('Complete!', 'wpvdb'),
                'error' => __('Error:', 'wpvdb'),
            ]
        ]);
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
        $settings = get_option('wpvdb_settings', []);
        
        // Ensure settings is an array
        if (!is_array($settings)) {
            $settings = [];
        }
        
        // Ensure provider arrays exist
        if (!isset($settings['openai']) || !is_array($settings['openai'])) {
            $settings['openai'] = [
                'api_key' => '', 
                'default_model' => 'text-embedding-3-small'
            ];
        }
        if (!isset($settings['automattic']) || !is_array($settings['automattic'])) {
            $settings['automattic'] = [
                'api_key' => '',
                'default_model' => 'a8cai-embeddings-small-1'
            ];
        }
        
        // Ensure active/pending provider fields exist
        if (!isset($settings['active_provider'])) {
            $settings['active_provider'] = '';
        }
        if (!isset($settings['active_model'])) {
            $settings['active_model'] = '';
        }
        if (!isset($settings['pending_provider'])) {
            $settings['pending_provider'] = '';
        }
        if (!isset($settings['pending_model'])) {
            $settings['pending_model'] = '';
        }
        
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
        
        $post_ids = isset($_POST['post_ids']) && is_array($_POST['post_ids']) ? array_map('absint', $_POST['post_ids']) : [];
        $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : '';
        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : 'openai';
        
        if (empty($post_ids)) {
            wp_send_json_error(['message' => __('No posts selected', 'wpvdb')]);
        }
        
        // Get settings
        $settings = get_option('wpvdb_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }
        
        // Ensure provider arrays exist
        if (!isset($settings['openai']) || !is_array($settings['openai'])) {
            $settings['openai'] = ['default_model' => 'text-embedding-3-small', 'api_key' => ''];
        }
        if (!isset($settings['automattic']) || !is_array($settings['automattic'])) {
            $settings['automattic'] = ['default_model' => 'a8cai-embeddings-small-1', 'api_key' => ''];
        }
        
        // Ensure active/pending provider fields exist
        if (!isset($settings['active_provider']) || !is_string($settings['active_provider'])) {
            $settings['active_provider'] = '';
        }
        if (!isset($settings['active_model']) || !is_string($settings['active_model'])) {
            $settings['active_model'] = '';
        }
        if (!isset($settings['pending_provider']) || !is_string($settings['pending_provider'])) {
            $settings['pending_provider'] = '';
        }
        if (!isset($settings['pending_model']) || !is_string($settings['pending_model'])) {
            $settings['pending_model'] = '';
        }
        
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
            $provider = !empty($settings['active_provider']) ? $settings['active_provider'] : $provider;
            
            // Get the default model if not specified
            if (empty($model)) {
                if ($provider === 'automattic') {
                    $model = !empty($settings['active_model']) ? 
                        $settings['active_model'] : 
                        (!empty($settings['automattic']['default_model']) ? 
                            $settings['automattic']['default_model'] : 
                            'a8cai-embeddings-small-1');
                } else {
                    $model = !empty($settings['active_model']) ? 
                        $settings['active_model'] : 
                        (!empty($settings['openai']['default_model']) ? 
                            $settings['openai']['default_model'] : 
                            'text-embedding-3-small');
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
        
        $settings = get_option('wpvdb_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }
        
        // Ensure post_types is an array
        if (!isset($settings['post_types']) || !is_array($settings['post_types'])) {
            $settings['post_types'] = ['post', 'page'];
        }
        
        // Get post_type from request or use default from settings
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : null;
        $post_types = $post_type ? [$post_type] : $settings['post_types'];
        
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
            $title = get_the_title($post_id);
            $posts[] = [
                'id' => $post_id,
                'title' => is_string($title) ? $title : sprintf(__('Post %d', 'wpvdb'), $post_id),
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
            $settings['automattic']['default_model'] = 'a8cai-embeddings-small-1';
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
                <div class="wpvdb-status-row">
                    <span class="wpvdb-status-label"><?php esc_html_e('Embedding', 'wpvdb'); ?></span>
                    <span class="wpvdb-status-value">
                        <span class="wpvdb-status-dot embedded"></span>
                        <span class="wpvdb-status-text"><?php echo sprintf(esc_html__('Embedded (%s chunks)', 'wpvdb'), $chunks_count); ?></span>
                    </span>
                </div>
                
                <?php if ($embedded_model) : ?>
                <div class="wpvdb-embedding-model">
                    <small><?php echo sprintf(esc_html__('Model: %s', 'wpvdb'), $embedded_model); ?></small>
                </div>
                <?php endif; ?>
                
                <?php if ($embedded_date) : ?>
                <div class="wpvdb-embedding-date">
                    <small><?php echo sprintf(esc_html__('Generated: %s', 'wpvdb'), date_i18n(get_option('date_format'), strtotime($embedded_date))); ?></small>
                </div>
                <?php endif; ?>
                
                <button id="wpvdb-reembed-post" class="button" data-post-id="<?php echo esc_attr($post->ID); ?>">
                    <?php esc_html_e('Re-generate Embeddings', 'wpvdb'); ?>
                </button>
                
                <div id="wpvdb-reembed-status" style="display:none; margin-top: 10px;"></div>
            <?php else : ?>
                <div class="wpvdb-status-row">
                    <span class="wpvdb-status-label"><?php esc_html_e('Embedding', 'wpvdb'); ?></span>
                    <span class="wpvdb-status-value">
                        <span class="wpvdb-status-dot not-embedded"></span>
                        <span class="wpvdb-status-text"><?php esc_html_e('Not embedded', 'wpvdb'); ?></span>
                    </span>
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
     * Render the embedding status column content
     * 
     * @param string $column_name
     * @param int $post_id
     */
    public static function render_embedding_column($column_name, $post_id) {
        if ($column_name !== 'wpvdb_embedded') {
            return;
        }
        
        // Check if post has meta indicating embeddings
        $is_embedded_meta = get_post_meta($post_id, '_wpvdb_embedded', true);
        $chunks_count = get_post_meta($post_id, '_wpvdb_chunks_count', true);
        
        // Verify actual embeddings exist in database
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpvdb_embeddings';
        $actual_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE doc_id = %d",
            $post_id
        ));
        
        // Only consider truly embedded if both meta and actual database records exist
        $is_embedded = $is_embedded_meta && $actual_count > 0;
        
        // If meta says it's embedded but no actual embeddings exist, fix the meta
        if ($is_embedded_meta && $actual_count == 0) {
            delete_post_meta($post_id, '_wpvdb_embedded');
            delete_post_meta($post_id, '_wpvdb_chunks_count');
            delete_post_meta($post_id, '_wpvdb_embedded_date');
            delete_post_meta($post_id, '_wpvdb_embedded_model');
        }
        
        if ($is_embedded) {
            echo '<div class="wpvdb-status-container" title="' . esc_attr(sprintf(__('Embedded (%d chunks)', 'wpvdb'), $actual_count)) . '">' .
                '<span class="wpvdb-status-dot embedded"></span>' .
                '<span class="wpvdb-status-count">(' . esc_html($actual_count) . ')</span>' .
                '</div>';
        } else {
            echo '<div class="wpvdb-status-container" title="' . esc_attr(__('Not embedded', 'wpvdb')) . '">' .
                '<span class="wpvdb-status-dot not-embedded"></span>' .
                '</div>';
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
        
        // Get settings securely
        $settings = get_option('wpvdb_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }
        
        // Ensure we have provider and model data
        $provider = isset($settings['active_provider']) && !empty($settings['active_provider']) ? 
                    $settings['active_provider'] : 'openai';
        
        $model = '';
        if ($provider === 'openai') {
            $model = isset($settings['active_model']) && !empty($settings['active_model']) ? 
                    $settings['active_model'] : 'text-embedding-3-small';
        } else if ($provider === 'automattic') {
            $model = isset($settings['active_model']) && !empty($settings['active_model']) ? 
                    $settings['active_model'] : 'a8cai-embeddings-small-1';
        }
        
        // Queue for re-embedding
        $queue = new WPVDB_Queue();
        $queue->push_to_queue([
            'post_id' => $post_id,
            'model' => $model,
            'provider' => $provider,
        ]);
        $queue->save()->dispatch();
        
        wp_send_json_success([
            'message' => __('Post queued for embedding generation', 'wpvdb'),
        ]);
    }
    
    /**
     * AJAX handler for testing embedding generation
     */
    public static function ajax_test_embedding() {
        check_ajax_referer('wpvdb-admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'wpvdb')]);
        }
        
        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';
        $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : '';
        $text = isset($_POST['text']) ? sanitize_textarea_field($_POST['text']) : '';
        
        if (empty($text)) {
            wp_send_json_error(['message' => __('Text is required for generating embeddings.', 'wpvdb')]);
        }
        
        if (!in_array($provider, ['openai', 'automattic'])) {
            wp_send_json_error(['message' => __('Invalid provider.', 'wpvdb')]);
        }
        
        // Get the appropriate API key and base URL
        $settings = get_option('wpvdb_settings', []);
        $api_key = '';
        $api_base = '';
        
        if ($provider === 'openai') {
            $api_key = $settings['openai']['api_key'] ?? '';
            $api_base = 'https://api.openai.com/v1/';
            
            // Validate model
            if (!in_array($model, ['text-embedding-3-small', 'text-embedding-3-large', 'text-embedding-ada-002'])) {
                wp_send_json_error(['message' => __('Invalid OpenAI model.', 'wpvdb')]);
            }
        } else if ($provider === 'automattic') {
            $api_key = $settings['automattic']['api_key'] ?? '';
            $api_base = 'https://api.automattic.com/v1/embedding';
            
            // Validate model
            if (!in_array($model, ['automattic-embeddings-001'])) {
                wp_send_json_error(['message' => __('Invalid Automattic model.', 'wpvdb')]);
            }
        }
        
        if (empty($api_key)) {
            wp_send_json_error(['message' => sprintf(
                __('API key for %s is not configured. Please configure it in the settings.', 'wpvdb'),
                $provider === 'openai' ? 'OpenAI' : 'Automattic AI'
            )]);
        }
        
        // Time the embedding generation
        $start_time = microtime(true);
        
        // Generate embedding
        $embedding = Core::get_embedding($text, $model, $api_base, $api_key);
        
        $end_time = microtime(true);
        $time_taken = round($end_time - $start_time, 2);
        
        if (is_wp_error($embedding)) {
            wp_send_json_error(['message' => $embedding->get_error_message()]);
        }
        
        // Get sample of embedding values (first 5)
        $sample = array_slice($embedding, 0, 5);
        $sample_json = json_encode($sample, JSON_PRETTY_PRINT);
        
        wp_send_json_success([
            'provider' => $provider === 'openai' ? 'OpenAI' : 'Automattic AI',
            'model' => $model,
            'dimensions' => count($embedding),
            'sample' => $sample_json,
            'time' => $time_taken
        ]);
    }
    
    /**
     * AJAX handler to get the full content of an embedding by ID
     */
    public static function ajax_get_embedding_content() {
        // Check nonce
        if (!check_ajax_referer('wpvdb_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'wpvdb')]);
        }
        
        // Check if user has permission
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action', 'wpvdb')]);
        }
        
        // Get the embedding ID
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) {
            wp_send_json_error(['message' => __('Invalid embedding ID', 'wpvdb')]);
        }
        
        // Get the embedding from the database
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpvdb_embeddings';
        
        $embedding = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id)
        );
        
        if (!$embedding) {
            wp_send_json_error(['message' => __('Embedding not found', 'wpvdb')]);
        }
        
        // Return the content
        $content = isset($embedding->chunk_content) ? $embedding->chunk_content : $embedding->preview;
        
        wp_send_json_success([
            'id' => $id,
            'content' => $content,
            'doc_id' => $embedding->doc_id,
            'chunk_id' => $embedding->chunk_id
        ]);
    }
    
    /**
     * Handle admin actions for our tools.
     */
    public static function handle_admin_actions() {
        // Check if we're on our admin page
        if (!isset($_GET['page']) || $_GET['page'] !== 'wpvdb-status') {
            return;
        }
        
        // Check for our action
        if (!isset($_POST['wpvdb_action'])) {
            return;
        }
        
        $action = sanitize_text_field($_POST['wpvdb_action']);
        
        // Run diagnostics action
        if ($action === 'run_diagnostics') {
            if (!isset($_POST['wpvdb_diagnostics_nonce']) || !wp_verify_nonce($_POST['wpvdb_diagnostics_nonce'], 'wpvdb_diagnostics_action')) {
                wp_die('Security check failed. Please try again.');
            }
            
            // Log that diagnostics were run
            error_log('[WPVDB ADMIN] Running database diagnostics from admin UI');
            
            // Redirect back to the page with a parameter to show diagnostics
            wp_redirect(add_query_arg('diagnostics', 'run', admin_url('admin.php?page=wpvdb-status')));
            exit;
        }
        
        // Force recreate tables action
        if ($action === 'recreate_tables') {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wpvdb_recreate_tables')) {
                wp_die(__('Security check failed', 'wpvdb'));
            }
            
            // Start output buffering to catch any unwanted output
            ob_start();
            
            // Call the forcible table recreation method
            $success = Activation::recreate_tables_force();
            
            // Store the status message in a transient
            set_transient('wpvdb_table_recreate_status', $success ? 'success' : 'error', 60);
            
            // Clear any output that might have been generated
            ob_end_clean();
            
            // Redirect to the same page without the action parameters
            wp_safe_redirect(remove_query_arg(['action', '_wpnonce']));
            exit;
        }
        
        // Handle clear_embeddings action
        if ($action === 'clear_embeddings') {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wpvdb_clear_embeddings')) {
                wp_die(__('Security check failed', 'wpvdb'));
            }
            
            // Start output buffering
            ob_start();
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'wpvdb_embeddings';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
            
            if ($table_exists) {
                $wpdb->query("TRUNCATE TABLE {$table_name}");
            }
            
            // Store the status message in a transient
            set_transient('wpvdb_embeddings_cleared', 1, 60);
            
            // Clear any output that might have been generated
            ob_end_clean();
            
            // Redirect to the same page without the action parameters
            wp_safe_redirect(remove_query_arg(['action', '_wpnonce']));
            exit;
        }
    }
    
    /**
     * Display admin notices for action results
     */
    public static function admin_notices() {
        // Check for table recreation status
        $recreate_status = get_transient('wpvdb_table_recreate_status');
        if ($recreate_status) {
            delete_transient('wpvdb_table_recreate_status');
            
            if ($recreate_status === 'success') {
                echo '<div class="notice notice-success is-dismissible"><p>';
                _e('Database tables recreated successfully.', 'wpvdb');
                echo '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>';
                _e('Failed to recreate database tables. Check your database permissions and MySQL version.', 'wpvdb');
                echo '</p></div>';
            }
        }
        
        // Check for embeddings cleared status
        if (get_transient('wpvdb_embeddings_cleared')) {
            delete_transient('wpvdb_embeddings_cleared');
            
            echo '<div class="notice notice-success is-dismissible"><p>';
            _e('All embeddings have been deleted.', 'wpvdb');
            echo '</p></div>';
        }
        
        // Show notice after bulk embed action
        if (isset($_GET['wpvdb_bulk_embed']) && isset($_GET['processed_count'])) {
            $count = intval($_GET['processed_count']);
            echo '<div class="notice notice-success is-dismissible"><p>';
            printf(
                _n(
                    '%d post has been queued for embedding generation.',
                    '%d posts have been queued for embedding generation.',
                    $count,
                    'wpvdb'
                ),
                $count
            );
            echo '</p></div>';
        }
    }
    
    /**
     * Register post meta for the block editor
     */
    public static function register_post_meta() {
        // Define the post types that support embeddings
        $post_types = Settings::get_auto_embed_post_types();
        if (empty($post_types)) {
            $post_types = ['post', 'page'];
        }
        
        // Register meta fields for each supported post type
        foreach ($post_types as $post_type) {
            register_post_meta($post_type, '_wpvdb_embedded', [
                'show_in_rest' => true,
                'single' => true,
                'type' => 'boolean',
                'auth_callback' => function() {
                    return current_user_can('edit_posts');
                }
            ]);
            
            register_post_meta($post_type, '_wpvdb_chunks_count', [
                'show_in_rest' => true,
                'single' => true,
                'type' => 'integer',
                'auth_callback' => function() {
                    return current_user_can('edit_posts');
                }
            ]);
            
            register_post_meta($post_type, '_wpvdb_embedded_date', [
                'show_in_rest' => true,
                'single' => true,
                'type' => 'string',
                'auth_callback' => function() {
                    return current_user_can('edit_posts');
                }
            ]);
            
            register_post_meta($post_type, '_wpvdb_embedded_model', [
                'show_in_rest' => true,
                'single' => true,
                'type' => 'string',
                'auth_callback' => function() {
                    return current_user_can('edit_posts');
                }
            ]);
        }
    }
    
    /**
     * Enqueue assets for the block editor
     */
    public static function enqueue_editor_assets() {
        // Enqueue the editor plugin script
        wp_enqueue_script(
            'wpvdb-editor-row',
            WPVDB_PLUGIN_URL . 'assets/js/editor.js',
            ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n'],
            WPVDB_VERSION,
            true
        );
        
        // Enqueue styles for the editor plugin
        wp_enqueue_style(
            'wpvdb-editor-styles',
            WPVDB_PLUGIN_URL . 'assets/css/editor.css',
            [],
            WPVDB_VERSION
        );
    }
    
    /**
     * Register bulk actions for embedding posts in supported post types
     */
    public static function register_bulk_embed_actions() {
        $post_types = Settings::get_auto_embed_post_types();
        
        foreach ($post_types as $post_type) {
            add_filter("bulk_actions-edit-{$post_type}", [__CLASS__, 'add_bulk_embed_action']);
            add_filter("handle_bulk_actions-edit-{$post_type}", [__CLASS__, 'handle_bulk_embed_action'], 10, 3);
        }
    }
    
    /**
     * Add bulk embed action to post list tables
     * 
     * @param array $bulk_actions
     * @return array
     */
    public static function add_bulk_embed_action($bulk_actions) {
        $bulk_actions['wpvdb_bulk_embed'] = __('Generate Embeddings', 'wpvdb');
        return $bulk_actions;
    }
    
    /**
     * Handle bulk embed action
     * 
     * @param string $redirect_to URL to redirect to after the action
     * @param string $action The action being taken
     * @param array $post_ids Array of post IDs
     * @return string Modified redirect URL
     */
    public static function handle_bulk_embed_action($redirect_to, $action, $post_ids) {
        if ($action !== 'wpvdb_bulk_embed') {
            return $redirect_to;
        }
        
        if (!current_user_can('manage_options')) {
            return $redirect_to;
        }
        
        if (empty($post_ids)) {
            return $redirect_to;
        }
        
        // Get settings
        $settings = get_option('wpvdb_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }
        
        // Use active provider and model
        $provider = !empty($settings['active_provider']) ? $settings['active_provider'] : 'openai';
        
        $model = '';
        if ($provider === 'openai') {
            $model = !empty($settings['active_model']) ? 
                     $settings['active_model'] : 
                     (!empty($settings['openai']['default_model']) ? 
                      $settings['openai']['default_model'] : 
                      'text-embedding-3-small');
        } else if ($provider === 'automattic') {
            $model = !empty($settings['active_model']) ? 
                     $settings['active_model'] : 
                     (!empty($settings['automattic']['default_model']) ? 
                      $settings['automattic']['default_model'] : 
                      'a8cai-embeddings-small-1');
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
        
        // Add the processed count to the redirect URL
        $redirect_to = add_query_arg(
            [
                'wpvdb_bulk_embed' => '1',
                'processed_count' => count($post_ids)
            ],
            $redirect_to
        );
        
        return $redirect_to;
    }
} 