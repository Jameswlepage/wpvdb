<?php
/**
 * Status page for WPVDB
 *
 * @package WPVDB
 * @var \WPVDB\Plugin $plugin The plugin instance
 * @var \WPVDB\Admin $admin The admin instance
 */

defined('ABSPATH') || exit;

// Get the database instance
$database = $wpvdb_plugin->get_database();

// Get current settings
$settings = get_option('wpvdb_settings', []);
if (!is_array($settings)) {
    $settings = [];
}

// Get provider change status
$has_pending_change = !empty($settings['pending_provider']) && !empty($settings['pending_model']);
$active_provider = isset($settings['active_provider']) ? $settings['active_provider'] : '';
$active_model = isset($settings['active_model']) ? $settings['active_model'] : '';
$pending_provider = isset($settings['pending_provider']) ? $settings['pending_provider'] : '';
$pending_model = isset($settings['pending_model']) ? $settings['pending_model'] : '';

// Get system information 
$system_info = [];
$system_info['php_version'] = phpversion();
$system_info['wp_version'] = get_bloginfo('version');
$system_info['wp_memory_limit'] = WP_MEMORY_LIMIT;
$system_info['wp_debug_mode'] = defined('WP_DEBUG') && WP_DEBUG ? 'Yes' : 'No';

// Database info
global $wpdb;
$system_info['mysql_version'] = $wpdb->get_var('SELECT VERSION()');

// Plugin info
$plugins = get_plugins();
$active_plugins = get_option('active_plugins', []);
$system_info['plugins'] = [];
foreach ($plugins as $plugin_path => $plugin_data) {
    $system_info['plugins'][] = [
        'name' => $plugin_data['Name'],
        'version' => $plugin_data['Version'],
        'active' => in_array($plugin_path, $active_plugins),
    ];
}

// WPVDB specific info
$system_info['db_type'] = $database->get_db_type();
$system_info['vector_support'] = $database->has_native_vector_support() ? 'Yes' : 'No';
$system_info['fallbacks_enabled'] = $database->are_fallbacks_enabled() ? 'Yes' : 'No';

// Get embedding tables info
$embedding_table = $wpdb->prefix . 'wpvdb_embeddings';
$embedding_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$embedding_table'") === $embedding_table;
$system_info['embedding_table_exists'] = $embedding_table_exists ? 'Yes' : 'No';

if ($embedding_table_exists) {
    $system_info['embedding_count'] = $wpdb->get_var("SELECT COUNT(*) FROM $embedding_table");
} else {
    $system_info['embedding_count'] = '0';
}

// Define available sections
$sections = [
    'info' => __('System Information', 'wpvdb'),
    'tools' => __('Tools', 'wpvdb'),
];

// Get the current section from URL or default to 'info'
$current_section = isset($_GET['section']) ? sanitize_key($_GET['section']) : 'info';

// Ensure we have a valid section
if (!array_key_exists($current_section, $sections)) {
    $current_section = 'info';
}

?>
<div class="wrap wpvdb-admin">
    <h1><?php _e('Status', 'wpvdb'); ?></h1>
    
    <?php if ($has_pending_change): ?>
    <div class="notice notice-warning inline">
        <p>
            <strong><?php esc_html_e('Provider Change Pending', 'wpvdb'); ?></strong>
        </p>
        <p>
            <?php esc_html_e('You have a pending change to your embedding provider or model. This change requires re-indexing all content.', 'wpvdb'); ?>
        </p>
        <p>
            <button id="wpvdb-apply-provider-change-notice" class="button button-primary">
                <?php _e('Apply Change', 'wpvdb'); ?>
            </button>
            <button id="wpvdb-cancel-provider-change-notice" class="button">
                <?php _e('Cancel Change', 'wpvdb'); ?>
            </button>
        </p>
    </div>
    <?php endif; ?>
    
    <!-- Section Navigation -->
    <div class="wpvdb-section-nav">
        <?php
        $i = 0;
        foreach ($sections as $section_id => $section_label) {
            if ($i > 0) {
                echo '<span class="divider">|</span>';
            }
            
            $url = add_query_arg([
                'page' => 'wpvdb-status',
                'section' => $section_id
            ], admin_url('admin.php'));
            
            $class = ($current_section === $section_id) ? 'current' : '';
            printf(
                '<a href="%s" class="%s">%s</a>',
                esc_url($url),
                esc_attr($class),
                esc_html($section_label)
            );
            
            $i++;
        }
        ?>
    </div>
    
    <!-- System Information Section -->
    <div class="wpvdb-status-section" <?php echo $current_section !== 'info' ? 'style="display: none;"' : ''; ?>>
        <table class="widefat" cellspacing="0">
            <thead>
                <tr>
                    <th colspan="2"><?php _e('WordPress & Server Environment', 'wpvdb'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th><?php _e('WordPress Version', 'wpvdb'); ?></th>
                    <td><?php echo esc_html($system_info['wp_version']); ?></td>
                </tr>
                <tr>
                    <th><?php _e('PHP Version', 'wpvdb'); ?></th>
                    <td><?php echo esc_html($system_info['php_version']); ?></td>
                </tr>
                <tr>
                    <th><?php 
                        echo esc_html(ucfirst($system_info['db_type']) . ' ' . __('Version', 'wpvdb')); 
                    ?></th>
                    <td><?php echo isset($system_info['mysql_version']) ? esc_html($system_info['mysql_version']) : esc_html__('Not available', 'wpvdb'); ?></td>
                </tr>
                <tr>
                    <th><?php _e('WP Memory Limit', 'wpvdb'); ?></th>
                    <td><?php echo esc_html($system_info['wp_memory_limit']); ?></td>
                </tr>
                <tr>
                    <th><?php _e('WP Debug Mode', 'wpvdb'); ?></th>
                    <td><?php echo esc_html($system_info['wp_debug_mode']); ?></td>
                </tr>
            </tbody>
        </table>
        
        <table class="widefat" cellspacing="0" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th colspan="2"><?php _e('WPVDB Status', 'wpvdb'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th><?php _e('Plugin Version', 'wpvdb'); ?></th>
                    <td><?php echo esc_html(WPVDB_VERSION); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Database Type', 'wpvdb'); ?></th>
                    <td><?php echo esc_html(ucfirst($system_info['db_type'])); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Vector Support', 'wpvdb'); ?></th>
                    <td>
                        <?php if ($system_info['vector_support'] === 'Yes'): ?>
                            <span class="dashicons dashicons-yes" style="color:green;"></span> <?php _e('Available', 'wpvdb'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-no" style="color:red;"></span> <?php _e('Not Available', 'wpvdb'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Fallbacks Enabled', 'wpvdb'); ?></th>
                    <td>
                        <?php if ($system_info['fallbacks_enabled'] === 'Yes'): ?>
                            <span class="dashicons dashicons-yes" style="color:orange;"></span> <?php _e('Yes (Performance Impact)', 'wpvdb'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-no"></span> <?php _e('No', 'wpvdb'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Embeddings Table', 'wpvdb'); ?></th>
                    <td>
                        <?php if ($system_info['embedding_table_exists'] === 'Yes'): ?>
                            <span class="dashicons dashicons-yes" style="color:green;"></span> <?php 
                            printf(
                                _n('Exists (%s record)', 'Exists (%s records)', intval($system_info['embedding_count']), 'wpvdb'),
                                number_format_i18n(intval($system_info['embedding_count']))
                            ); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-no" style="color:red;"></span> <?php _e('Not Created', 'wpvdb'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <?php if ($active_provider || $has_pending_change): ?>
        <table class="widefat" cellspacing="0" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th colspan="2"><?php _e('Provider Configuration', 'wpvdb'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($active_provider): ?>
                <tr>
                    <th><?php _e('Active Provider', 'wpvdb'); ?></th>
                    <td><?php echo esc_html(ucfirst($active_provider)); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Active Model', 'wpvdb'); ?></th>
                    <td><?php echo esc_html($active_model); ?></td>
                </tr>
                <?php endif; ?>
                
                <?php if ($has_pending_change): ?>
                <tr class="wpvdb-pending-change-row">
                    <th><?php _e('Pending Provider Change', 'wpvdb'); ?></th>
                    <td>
                        <span class="dashicons dashicons-warning" style="color:orange;"></span>
                        <?php echo esc_html(sprintf(
                            __('Change from %s to %s is pending', 'wpvdb'),
                            ucfirst($active_provider),
                            ucfirst($pending_provider)
                        )); ?>
                    </td>
                </tr>
                <tr class="wpvdb-pending-change-row">
                    <th><?php _e('Pending Model Change', 'wpvdb'); ?></th>
                    <td>
                        <span class="dashicons dashicons-warning" style="color:orange;"></span>
                        <?php echo esc_html(sprintf(
                            __('Change from %s to %s is pending', 'wpvdb'),
                            $active_model,
                            $pending_model
                        )); ?>
                    </td>
                </tr>
                <tr class="wpvdb-pending-change-row">
                    <th><?php _e('Actions', 'wpvdb'); ?></th>
                    <td>
                        <div class="wpvdb-action-buttons">
                            <button id="wpvdb-apply-provider-change" class="button button-primary">
                                <?php _e('Apply Change', 'wpvdb'); ?>
                            </button>
                            <button id="wpvdb-cancel-provider-change" class="button">
                                <?php _e('Cancel Change', 'wpvdb'); ?>
                            </button>
                        </div>
                        <p class="description">
                            <?php _e('Applying the change will delete all existing embeddings and require re-indexing content.', 'wpvdb'); ?>
                        </p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <div class="wpvdb-card" style="margin-top: 20px;">
            <h3><?php _e('Debug Information', 'wpvdb'); ?></h3>
            <p><?php _e('View and export detailed debug information about your WPVDB configuration.', 'wpvdb'); ?></p>
            <p>
                <button id="wpvdb-toggle-debug-info" class="button">
                    <?php _e('Show Debug Info', 'wpvdb'); ?>
                </button>
            </p>
            <div id="wpvdb-debug-info" style="display: none;">
                <h4><?php _e('Provider Configuration', 'wpvdb'); ?></h4>
                <pre class="wpvdb-debug-output"><?php 
                    $debug_settings = $settings;
                    
                    // Mask API keys for security
                    if (isset($debug_settings['openai']['api_key']) && !empty($debug_settings['openai']['api_key'])) {
                        $debug_settings['openai']['api_key'] = '********' . substr($debug_settings['openai']['api_key'], -4);
                    }
                    if (isset($debug_settings['automattic']['api_key']) && !empty($debug_settings['automattic']['api_key'])) {
                        $debug_settings['automattic']['api_key'] = '********' . substr($debug_settings['automattic']['api_key'], -4);
                    }
                    
                    echo esc_html(print_r($debug_settings, true));
                ?></pre>
                
                <h4><?php _e('Database Information', 'wpvdb'); ?></h4>
                <pre class="wpvdb-debug-output"><?php 
                    $db_info = [
                        'db_type' => $database->get_db_type(),
                        'db_version' => $wpdb->db_version(),
                        'table_prefix' => $wpdb->prefix,
                        'has_native_vector_support' => $database->has_native_vector_support(),
                        'fallbacks_enabled' => $database->are_fallbacks_enabled(),
                        'embedding_table_exists' => $embedding_table_exists,
                        'embedding_count' => $system_info['embedding_count'],
                    ];
                    echo esc_html(print_r($db_info, true));
                ?></pre>
                
                <p>
                    <button id="wpvdb-copy-debug-info" class="button" data-clipboard-target="#wpvdb-debug-info">
                        <?php _e('Copy to Clipboard', 'wpvdb'); ?>
                    </button>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Tools Section -->
    <div class="wpvdb-status-section" <?php echo $current_section !== 'tools' ? 'style="display: none;"' : ''; ?>>
        <div class="wpvdb-card">
            <h3><?php _e('Database Tables', 'wpvdb'); ?></h3>
            <p><?php _e('If you are experiencing issues with embeddings, you can recreate the database tables.', 'wpvdb'); ?></p>
            <p>
                <?php 
                $has_vector = $database->has_native_vector_support();
                if ($has_vector) {
                    esc_html_e('Re-create the database tables with native vector support.', 'wpvdb');
                    echo ' <span class="dashicons dashicons-yes" style="color:green;"></span>';
                } else {
                    esc_html_e('Re-create the database tables with fallback support.', 'wpvdb'); 
                    echo ' <span class="dashicons dashicons-warning" style="color:orange;"></span>';
                }
                ?>
            </p>
            <p>
                <a href="<?php echo wp_nonce_url(add_query_arg(['action' => 'wpvdb_recreate_tables'], admin_url('admin.php?page=wpvdb-status')), 'wpvdb_recreate_tables'); ?>" class="button" onclick="return confirm('<?php esc_attr_e('This will delete and recreate all Vector Database tables. Your embeddings will be lost and need to be regenerated. Are you sure?', 'wpvdb'); ?>');">
                    <?php _e('Recreate Database Tables', 'wpvdb'); ?>
                </a>
            </p>
        </div>
        
        <?php if ($has_pending_change): ?>
        <div class="wpvdb-card wpvdb-pending-change-card">
            <h3><?php _e('Pending Provider Change', 'wpvdb'); ?></h3>
            <p><?php _e('There is a pending change to your embedding provider configuration.', 'wpvdb'); ?></p>
            <div class="wpvdb-provider-change-info">
                <p><strong><?php _e('From:', 'wpvdb'); ?></strong> <?php echo esc_html(ucfirst($active_provider) . ' (' . $active_model . ')'); ?></p>
                <p><strong><?php _e('To:', 'wpvdb'); ?></strong> <?php echo esc_html(ucfirst($pending_provider) . ' (' . $pending_model . ')'); ?></p>
            </div>
            <div class="wpvdb-action-buttons">
                <button id="wpvdb-apply-provider-change-tool" class="button button-primary">
                    <?php _e('Apply Change', 'wpvdb'); ?>
                </button>
                <button id="wpvdb-cancel-provider-change-tool" class="button">
                    <?php _e('Cancel Change', 'wpvdb'); ?>
                </button>
            </div>
            <p class="description">
                <?php _e('Applying the change will delete all existing embeddings.', 'wpvdb'); ?>
            </p>
        </div>
        <?php endif; ?>
        
        <div class="wpvdb-card">
            <h3><?php _e('Run Diagnostics', 'wpvdb'); ?></h3>
            <p><?php _e('Run database diagnostics to check vector capabilities.', 'wpvdb'); ?></p>
            <p>
                <a href="<?php echo esc_url(add_query_arg(['diagnostics' => 'run', 'section' => 'tools'], admin_url('admin.php?page=wpvdb-status'))); ?>" class="button">
                    <?php _e('Run Diagnostics', 'wpvdb'); ?>
                </a>
            </p>
            
            <?php
            // Display diagnostic results if available
            if (isset($_GET['diagnostics']) && $_GET['diagnostics'] === 'run') {
                $diagnostics = $database->run_diagnostics(); 
                ?>
                <div class="wpvdb-diagnostics-results <?php echo isset($diagnostics['error']) ? 'has-error' : ''; ?>">
                    <h4><?php esc_html_e('Diagnostic Results', 'wpvdb'); ?></h4>
                    
                    <ul>
                        <li><strong><?php esc_html_e('Database Type:', 'wpvdb'); ?></strong> <?php echo esc_html(ucfirst($diagnostics['db_type'])); ?></li>
                        <li><strong><?php esc_html_e('Database Version:', 'wpvdb'); ?></strong> <?php echo esc_html($diagnostics['db_version']); ?></li>
                        <li><strong><?php esc_html_e('Vector Support:', 'wpvdb'); ?></strong> 
                            <?php if ($diagnostics['has_vector_support']): ?>
                                <span style="color:green;">✓</span>
                            <?php else: ?>
                                <span style="color:red;">✗</span>
                            <?php endif; ?>
                        </li>
                        <li><strong><?php esc_html_e('Fallbacks Enabled:', 'wpvdb'); ?></strong> 
                            <?php if ($diagnostics['fallbacks_enabled']): ?>
                                <span style="color:orange;">✓</span>
                            <?php else: ?>
                                <span style="color:gray;">✗</span>
                            <?php endif; ?>
                        </li>
                        
                        <?php if ($diagnostics['has_vector_support'] && isset($diagnostics['create_table'])): ?>
                            <li><strong><?php esc_html_e('Create Vector Table:', 'wpvdb'); ?></strong> 
                                <?php if ($diagnostics['create_table']): ?>
                                    <span style="color:green;">✓</span>
                                <?php else: ?>
                                    <span style="color:red;">✗</span>
                                <?php endif; ?>
                            </li>
                        <?php endif; ?>
                        
                        <?php if (isset($diagnostics['insert_data'])): ?>
                            <li><strong><?php esc_html_e('Insert Vector Data:', 'wpvdb'); ?></strong> 
                                <?php if ($diagnostics['insert_data']): ?>
                                    <span style="color:green;">✓</span>
                                <?php else: ?>
                                    <span style="color:red;">✗</span>
                                <?php endif; ?>
                            </li>
                        <?php endif; ?>
                        
                        <?php if (isset($diagnostics['cosine_distance'])): ?>
                            <li><strong><?php esc_html_e('Cosine Distance:', 'wpvdb'); ?></strong> 
                                <?php if ($diagnostics['cosine_distance']): ?>
                                    <span style="color:green;">✓</span>
                                <?php else: ?>
                                    <span style="color:red;">✗</span>
                                <?php endif; ?>
                            </li>
                        <?php endif; ?>
                        
                        <?php if (isset($diagnostics['vector_index'])): ?>
                            <li><strong><?php esc_html_e('Vector Index:', 'wpvdb'); ?></strong> 
                                <?php if ($diagnostics['vector_index'] === true): ?>
                                    <span style="color:green;">✓</span>
                                <?php elseif (is_string($diagnostics['vector_index'])): ?>
                                    <?php echo esc_html($diagnostics['vector_index']); ?>
                                <?php else: ?>
                                    <span style="color:red;">✗</span>
                                <?php endif; ?>
                            </li>
                        <?php endif; ?>
                        
                        <?php if (isset($diagnostics['error'])): ?>
                            <li><strong><?php esc_html_e('Error:', 'wpvdb'); ?></strong> <span style="color:red;"><?php echo esc_html($diagnostics['error']); ?></span></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php
            }
            ?>
        </div>
        
        <div class="wpvdb-card">
            <h3><?php _e('Test Embedding', 'wpvdb'); ?></h3>
            <p><?php _e('Test the embedding functionality with your current configuration.', 'wpvdb'); ?></p>
            <p>
                <button id="wpvdb-test-embedding-button" class="button">
                    <?php _e('Test Embedding', 'wpvdb'); ?>
                </button>
            </p>
        </div>
    </div>
</div>

<!-- Test Embedding Modal -->
<div id="wpvdb-test-embedding-modal" class="wpvdb-modal">
    <div class="wpvdb-modal-content">
        <span class="wpvdb-modal-close">&times;</span>
        <h2><?php esc_html_e('Test Embedding Generation', 'wpvdb'); ?></h2>
        
        <form id="wpvdb-test-embedding-form">
            <div class="wpvdb-form-group">
                <label for="wpvdb-test-provider"><?php esc_html_e('Provider', 'wpvdb'); ?></label>
                <select id="wpvdb-test-provider" name="provider">
                    <option value="openai" <?php selected($active_provider, 'openai'); ?>><?php esc_html_e('OpenAI', 'wpvdb'); ?></option>
                    <option value="automattic" <?php selected($active_provider, 'automattic'); ?>><?php esc_html_e('Automattic AI', 'wpvdb'); ?></option>
                </select>
            </div>
            
            <div class="wpvdb-form-group">
                <label for="wpvdb-test-model"><?php esc_html_e('Model', 'wpvdb'); ?></label>
                <select id="wpvdb-test-model" name="model">
                    <!-- Models will be populated via JavaScript -->
                </select>
            </div>
            
            <div class="wpvdb-form-group">
                <label for="wpvdb-test-text"><?php esc_html_e('Text to Embed', 'wpvdb'); ?></label>
                <textarea id="wpvdb-test-text" name="text" rows="5" style="width: 100%;" placeholder="<?php esc_attr_e('Enter some text to generate an embedding...', 'wpvdb'); ?>"></textarea>
            </div>
            
            <div class="wpvdb-form-actions">
                <button type="submit" class="button button-primary"><?php esc_html_e('Generate Embedding', 'wpvdb'); ?></button>
                <button type="button" class="button wpvdb-modal-cancel"><?php esc_html_e('Cancel', 'wpvdb'); ?></button>
            </div>
        </form>
        
        <div id="wpvdb-test-embedding-results" style="display: none; margin-top: 20px;">
            <div class="wpvdb-status-message"></div>
            <div class="wpvdb-embedding-info"></div>
        </div>
    </div>
</div> 