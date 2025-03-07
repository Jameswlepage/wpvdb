<div class="wpvdb-status">
    <?php if ($has_pending_change): ?>
    <div class="notice notice-warning inline">
        <p>
            <strong><?php esc_html_e('Provider Change Pending', 'wpvdb'); ?></strong>
        </p>
        <p>
            <?php 
            $active_provider_name = $embedding_info['active_provider'] === 'openai' ? 'OpenAI' : 'Automattic AI';
            $active_model = $embedding_info['active_model'];
            
            $pending_provider_name = $embedding_info['pending_provider'] === 'openai' ? 'OpenAI' : 'Automattic AI';
            $pending_model = $embedding_info['pending_model'];
            
            printf(
                esc_html__('You\'ve requested to change from %1$s (%2$s) to %3$s (%4$s). This requires re-indexing all content.', 'wpvdb'),
                '<strong>' . esc_html($active_provider_name) . '</strong>',
                '<code>' . esc_html($active_model) . '</code>',
                '<strong>' . esc_html($pending_provider_name) . '</strong>',
                '<code>' . esc_html($pending_model) . '</code>'
            ); 
            ?>
        </p>
        <div class="pending-actions">
            <a href="#" id="wpvdb-apply-provider-change" class="button button-primary">
                <?php esc_html_e('Apply Change & Clear Embeddings', 'wpvdb'); ?>
            </a>
            <button type="button" id="wpvdb-cancel-provider-change" class="button" onclick="cancelProviderChange(event)">
                <?php esc_html_e('Cancel Change', 'wpvdb'); ?>
            </button>
        </div>
    </div>
    <?php endif; ?>
    
    <?php
    // Define available sections
    $sections = [
        'system' => __('System Information', 'wpvdb'),
        'database' => __('Database', 'wpvdb'),
        'tools' => __('Tools', 'wpvdb'),
    ];
    
    // Default to first section if none specified
    if (empty($section)) {
        $section = 'system';
    }
    
    // Generate the section navigation as simple text links separated by pipes
    echo '<div class="wpvdb-section-nav" style="margin: 20px 0; padding: 10px 0; font-size: 14px;">';
    $i = 0;
    foreach ($sections as $section_id => $section_label) {
        if ($i > 0) {
            echo ' | ';
        }
        
        $url = admin_url(sprintf(
            'admin.php?page=wpvdb-status&section=%s',
            esc_attr($section_id)
        ));
        
        $class = ($section === $section_id) ? 'wpvdb-tab-current' : '';
        printf(
            '<a href="%s" class="%s" style="%s">%s</a>',
            esc_url($url),
            esc_attr($class),
            ($section === $section_id) ? 'font-weight: bold; text-decoration: none; color: #000;' : 'text-decoration: none;',
            esc_html($section_label)
        );
        
        $i++;
    }
    echo '</div>';
    ?>
    
    <div class="wpvdb-tab-content">
        <?php if ($section === 'system'): ?>
            <!-- System Information Section -->
            <h2><?php esc_html_e('System Information', 'wpvdb'); ?></h2>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th><?php esc_html_e('PHP Version', 'wpvdb'); ?></th>
                        <td><?php echo esc_html($system_info['php_version']); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('WordPress Version', 'wpvdb'); ?></th>
                        <td><?php echo esc_html($system_info['wp_version']); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('WP Memory Limit', 'wpvdb'); ?></th>
                        <td><?php echo esc_html($system_info['wp_memory_limit']); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Max Execution Time', 'wpvdb'); ?></th>
                        <td><?php echo esc_html($system_info['max_execution_time']); ?> <?php esc_html_e('seconds', 'wpvdb'); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('POST Max Size', 'wpvdb'); ?></th>
                        <td><?php echo esc_html($system_info['post_max_size']); ?></td>
                    </tr>
                    <tr>
                        <th><?php 
                            $db_type = \WPVDB\Database::get_db_type();
                            echo esc_html(ucfirst($db_type) . ' ' . __('Version', 'wpvdb')); 
                        ?></th>
                        <td><?php echo isset($system_info['mysql_version']) ? esc_html($system_info['mysql_version']) : esc_html__('Not available', 'wpvdb'); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('cURL Version', 'wpvdb'); ?></th>
                        <td><?php echo !empty($system_info['curl_version']) ? esc_html($system_info['curl_version']) : __('Not available', 'wpvdb'); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Vector DB Support', 'wpvdb'); ?></th>
                        <td>
                            <?php if (isset($system_info['vector_db_support']) && $system_info['vector_db_support']): ?>
                                <mark class="yes">
                                    <span class="dashicons dashicons-yes"></span> <?php esc_html_e('Supported', 'wpvdb'); ?>
                                </mark>
                            <?php else: ?>
                                <mark class="error">
                                    <span class="dashicons dashicons-no"></span> <?php esc_html_e('Not supported', 'wpvdb'); ?>
                                </mark>
                                <p class="description">
                                    <?php esc_html_e('Your database does not support vector operations. The plugin will fall back to using the PHP adapter, which may be slower.', 'wpvdb'); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <h2><?php esc_html_e('AI Provider Status', 'wpvdb'); ?></h2>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th><?php esc_html_e('OpenAI API Key', 'wpvdb'); ?></th>
                        <td>
                            <?php if ($system_info['openai_api_key_set']) : ?>
                                <mark class="yes"><span class="dashicons dashicons-yes"></span> <?php esc_html_e('API key is set', 'wpvdb'); ?></mark>
                            <?php else : ?>
                                <mark class="error"><span class="dashicons dashicons-warning"></span> <?php esc_html_e('API key is not set', 'wpvdb'); ?></mark>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=wpvdb-settings')); ?>" class="button button-small">
                                    <?php esc_html_e('Configure', 'wpvdb'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Automattic AI API Key', 'wpvdb'); ?></th>
                        <td>
                            <?php if ($system_info['automattic_api_key_set']) : ?>
                                <mark class="yes"><span class="dashicons dashicons-yes"></span> <?php esc_html_e('API key is set', 'wpvdb'); ?></mark>
                            <?php else : ?>
                                <mark class="error"><span class="dashicons dashicons-warning"></span> <?php esc_html_e('API key is not set', 'wpvdb'); ?></mark>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=wpvdb-settings')); ?>" class="button button-small">
                                    <?php esc_html_e('Configure', 'wpvdb'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Active Provider', 'wpvdb'); ?></th>
                        <td>
                            <?php if (!empty($embedding_info['active_provider']) && !empty($embedding_info['active_model'])) : ?>
                                <?php 
                                $provider_name = $embedding_info['active_provider'] === 'openai' ? 'OpenAI' : 'Automattic AI';
                                echo sprintf(
                                    '<strong>%s</strong> / <code>%s</code>',
                                    esc_html($provider_name),
                                    esc_html($embedding_info['active_model'])
                                ); 
                                ?>
                            <?php else : ?>
                                <em><?php esc_html_e('Not configured', 'wpvdb'); ?></em>
                            <?php endif; ?>
                            
                            <?php if (!empty($embedding_info['pending_provider']) && !empty($embedding_info['pending_model'])) : ?>
                                <div class="pending-provider">
                                    <?php 
                                    $pending_provider = $embedding_info['pending_provider'] === 'openai' ? 'OpenAI' : 'Automattic AI';
                                    echo sprintf(
                                        esc_html__('Pending change to: %s / %s', 'wpvdb'),
                                        '<strong>' . esc_html($pending_provider) . '</strong>',
                                        '<code>' . esc_html($embedding_info['pending_model']) . '</code>'
                                    ); 
                                    ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        <?php elseif ($section === 'database'): ?>
            <!-- Database Section -->
            <h2><?php esc_html_e('Database Information', 'wpvdb'); ?></h2>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th><?php esc_html_e('Embedding Table Version', 'wpvdb'); ?></th>
                        <td><?php echo isset($db_info['table_version']) ? esc_html($db_info['table_version']) : esc_html__('Not available', 'wpvdb'); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Total Embeddings', 'wpvdb'); ?></th>
                        <td><?php echo isset($db_info['total_embeddings']) ? esc_html(number_format_i18n($db_info['total_embeddings'])) : '0'; ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Total Documents', 'wpvdb'); ?></th>
                        <td><?php echo isset($db_info['total_documents']) ? esc_html(number_format_i18n($db_info['total_documents'])) : '0'; ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Storage Used', 'wpvdb'); ?></th>
                        <td><?php echo isset($db_info['storage_used']) ? esc_html($db_info['storage_used']) : esc_html__('Not available', 'wpvdb'); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Current Provider', 'wpvdb'); ?></th>
                        <td>
                            <?php 
                            if (!empty($embedding_info['active_provider'])) {
                                $provider_name = $embedding_info['active_provider'] === 'openai' ? 'OpenAI' : 'Automattic AI';
                                $model = $embedding_info['active_model'];
                                
                                printf(
                                    '%s (<code>%s</code>)',
                                    esc_html($provider_name),
                                    esc_html($model)
                                );
                                
                                if (!empty($embedding_info['pending_provider'])) {
                                    $pending_provider = $embedding_info['pending_provider'] === 'openai' ? 'OpenAI' : 'Automattic AI';
                                    $pending_model = $embedding_info['pending_model'];
                                    
                                    echo '<div class="pending-provider">';
                                    printf(
                                        esc_html__('Pending change to %s (%s)', 'wpvdb'),
                                        esc_html($pending_provider),
                                        esc_html($pending_model)
                                    );
                                    echo '</div>';
                                }
                            } else {
                                echo '<mark class="error">' . esc_html__('Not configured', 'wpvdb') . '</mark>';
                            }
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <?php if (isset($db_info['table_exists']) && $db_info['table_exists']) : ?>
            <h2><?php esc_html_e('Database Statistics', 'wpvdb'); ?></h2>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th><?php esc_html_e('Average Embedding Size', 'wpvdb'); ?></th>
                        <td><?php echo isset($db_stats['avg_embedding_size']) ? esc_html($db_stats['avg_embedding_size']) : esc_html__('Not available', 'wpvdb'); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Largest Embedding', 'wpvdb'); ?></th>
                        <td><?php echo isset($db_stats['largest_embedding']) ? esc_html($db_stats['largest_embedding']) : esc_html__('Not available', 'wpvdb'); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Average Chunk Content Size', 'wpvdb'); ?></th>
                        <td><?php echo isset($db_stats['avg_chunk_content_size']) ? esc_html($db_stats['avg_chunk_content_size']) : esc_html__('Not available', 'wpvdb'); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <h2><?php esc_html_e('Table Structure', 'wpvdb'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Field', 'wpvdb'); ?></th>
                        <th><?php esc_html_e('Type', 'wpvdb'); ?></th>
                        <th><?php esc_html_e('Null', 'wpvdb'); ?></th>
                        <th><?php esc_html_e('Key', 'wpvdb'); ?></th>
                        <th><?php esc_html_e('Default', 'wpvdb'); ?></th>
                        <th><?php esc_html_e('Extra', 'wpvdb'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($table_structure) && is_array($table_structure)): ?>
                    <?php foreach ($table_structure as $column) : ?>
                    <tr>
                        <td><?php echo isset($column->Field) ? esc_html($column->Field) : ''; ?></td>
                        <td><?php echo isset($column->Type) ? esc_html($column->Type) : ''; ?></td>
                        <td><?php echo isset($column->Null) ? esc_html($column->Null) : ''; ?></td>
                        <td><?php echo isset($column->Key) ? esc_html($column->Key) : ''; ?></td>
                        <td><?php echo isset($column->Default) ? esc_html($column->Default) : '<em>NULL</em>'; ?></td>
                        <td><?php echo isset($column->Extra) ? esc_html($column->Extra) : ''; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="6"><?php esc_html_e('Table structure information not available', 'wpvdb'); ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php endif; ?>
        <?php elseif ($section === 'tools'): ?>
            <!-- Tools Section -->
            <h2><?php esc_html_e('Maintenance Tools', 'wpvdb'); ?></h2>
            
            <?php if ($has_pending_change): ?>
            <div class="wpvdb-tool-card highlight">
                <h3><?php esc_html_e('Apply Provider Change', 'wpvdb'); ?></h3>
                <p><?php esc_html_e('You have a pending change to your embedding provider or model. Applying this change will clear all existing embeddings.', 'wpvdb'); ?></p>
                <a href="#" id="wpvdb-apply-provider-change-tool" class="button button-primary">
                    <?php esc_html_e('Apply Change', 'wpvdb'); ?>
                </a>
                <button type="button" id="wpvdb-cancel-provider-change-tool" class="button" onclick="cancelProviderChange(event)">
                    <?php esc_html_e('Cancel Change', 'wpvdb'); ?>
                </button>
            </div>
            <?php endif; ?>
            
            <div class="wpvdb-tools-grid">
                <div class="wpvdb-tool-card">
                    <h3><?php esc_html_e('Bulk Generate Embeddings', 'wpvdb'); ?></h3>
                    <p><?php esc_html_e('Generate embeddings for all published content.', 'wpvdb'); ?></p>
                    <button type="button" id="wpvdb-bulk-embed-button" class="button button-primary">
                        <?php esc_html_e('Generate Embeddings', 'wpvdb'); ?>
                    </button>
                </div>
                
                <div class="wpvdb-tool-card">
                    <h3><?php esc_html_e('Re-Create Tables', 'wpvdb'); ?></h3>
                    <p>
                        <?php 
                        $has_vector = \WPVDB\Database::has_native_vector_support();
                        if ($has_vector) {
                            esc_html_e('Re-create the database tables with native vector support.', 'wpvdb');
                            echo ' <span class="dashicons dashicons-yes" style="color:green;"></span>';
                        } else {
                            esc_html_e('Re-create the database tables without vector support (fallback tables).', 'wpvdb');
                            echo ' <span class="dashicons dashicons-warning" style="color:orange;"></span>';
                        }
                        ?>
                    </p>
                    <form method="post" action="">
                        <?php wp_nonce_field('wpvdb_recreate_tables'); ?>
                        <input type="hidden" name="wpvdb_action" value="recreate_tables">
                        <button type="submit" class="button button-secondary">
                            <?php esc_html_e('Force recreate tables', 'wpvdb'); ?>
                        </button>
                    </form>
                </div>
                
                <div class="wpvdb-tool-card">
                    <h3><?php esc_html_e('Clear All Embeddings', 'wpvdb'); ?></h3>
                    <p><?php esc_html_e('Delete all saved embeddings from the database. This cannot be undone!', 'wpvdb'); ?></p>
                    <form method="post" action="">
                        <?php wp_nonce_field('wpvdb_clear_embeddings'); ?>
                        <input type="hidden" name="wpvdb_action" value="clear_embeddings">
                        <button type="submit" class="button button-secondary" style="color:red;" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete all saved embeddings? This cannot be undone!', 'wpvdb'); ?>')">
                            <?php esc_html_e('Clear All Embeddings', 'wpvdb'); ?>
                        </button>
                    </form>
                </div>
                
                <div class="wpvdb-tool-card">
                    <h3><?php esc_html_e('Test Embedding Generation', 'wpvdb'); ?></h3>
                    <p><?php esc_html_e('Test embedding generation with your configured provider.', 'wpvdb'); ?></p>
                    <button type="button" id="wpvdb-test-embedding-button" class="button button-primary">
                        <?php esc_html_e('Test Embedding', 'wpvdb'); ?>
                    </button>
                </div>
                
                <div class="wpvdb-tool-card">
                    <h3><?php esc_html_e('Database Diagnostics', 'wpvdb'); ?></h3>
                    <p><?php esc_html_e('Run diagnostics on the database to check for vector support and troubleshoot issues.', 'wpvdb'); ?></p>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('wpvdb_diagnostics_action', 'wpvdb_diagnostics_nonce'); ?>
                        <input type="hidden" name="wpvdb_action" value="run_diagnostics">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Run Diagnostics', 'wpvdb'); ?></button>
                    </form>
                    
                    <?php 
                    // Display diagnostic results if available
                    if (isset($_GET['diagnostics']) && $_GET['diagnostics'] === 'run') {
                        $diagnostics = \WPVDB\Database::run_diagnostics(); 
                        ?>
                        <div class="wpvdb-diagnostics-results" style="margin-top: 20px; padding: 15px; background: #f8f8f8; border-left: 4px solid <?php echo $diagnostics['success'] ? '#46b450' : '#dc3232'; ?>;">
                            <h4><?php esc_html_e('Diagnostic Results', 'wpvdb'); ?></h4>
                            
                            <?php if ($diagnostics['success']): ?>
                                <p style="color: #46b450;"><strong><?php esc_html_e('✓ All tests passed successfully!', 'wpvdb'); ?></strong></p>
                            <?php else: ?>
                                <p style="color: #dc3232;"><strong><?php esc_html_e('✗ Some tests failed. See details below.', 'wpvdb'); ?></strong></p>
                            <?php endif; ?>
                            
                            <h5><?php esc_html_e('Messages:', 'wpvdb'); ?></h5>
                            <ul style="background: #fff; padding: 10px; border: 1px solid #ddd;">
                                <?php foreach ($diagnostics['messages'] as $message): ?>
                                    <li><?php echo esc_html($message); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            
                            <?php if (!empty($diagnostics['errors'])): ?>
                                <h5><?php esc_html_e('Errors:', 'wpvdb'); ?></h5>
                                <ul style="background: #fef7f7; padding: 10px; border: 1px solid #dc3232;">
                                    <?php foreach ($diagnostics['errors'] as $error): ?>
                                        <li><?php echo esc_html($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            
                            <p><em><?php esc_html_e('Check the PHP error log for more detailed debugging information.', 'wpvdb'); ?></em></p>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Test Embedding Modal -->
    <div id="wpvdb-test-embedding-modal" class="wpvdb-modal" style="display:none;">
        <div class="wpvdb-modal-content">
            <span class="wpvdb-modal-close">&times;</span>
            <h2><?php esc_html_e('Test Embedding Generation', 'wpvdb'); ?></h2>
            
            <form id="wpvdb-test-embedding-form">
                <div class="wpvdb-form-group">
                    <label for="wpvdb-test-provider"><?php esc_html_e('AI Provider', 'wpvdb'); ?></label>
                    <select id="wpvdb-test-provider" name="provider">
                        <?php 
                        $settings = get_option('wpvdb_settings');
                        $current_provider = $settings['provider'] ?? 'openai';
                        $providers = \WPVDB\Providers::get_available_providers();
                        
                        foreach ($providers as $provider_id => $provider) {
                            printf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr($provider_id),
                                selected($current_provider, $provider_id, false),
                                esc_html($provider['label'])
                            );
                        }
                        ?>
                    </select>
                </div>

                <div class="wpvdb-form-group" id="wpvdb-provider-models">
                    <label for="wpvdb-test-model"><?php esc_html_e('Model', 'wpvdb'); ?></label>
                    <select id="wpvdb-test-model" name="model">
                        <?php 
                        // Get models for current provider
                        $active_provider = isset($settings['active_provider']) ? $settings['active_provider'] : $current_provider;
                        $active_model = isset($settings['active_model']) ? $settings['active_model'] : '';
                        
                        // Load models for the active provider
                        $provider_models = \WPVDB\Models::get_provider_models($active_provider);
                        
                        foreach ($provider_models as $model_id => $model) {
                            printf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr($model_id),
                                selected($active_model, $model_id, false),
                                esc_html($model['label'])
                            );
                        }
                        ?>
                    </select>
                </div>
                
                <script type="text/javascript">
                // Store all models data for dynamic switching
                var wpvdbModels = <?php echo json_encode(\WPVDB\Models::get_available_models()); ?>;
                
                jQuery(document).ready(function($) {
                    // Update models when provider changes
                    $('#wpvdb-test-provider').on('change', function() {
                        var providerId = $(this).val();
                        var providerModels = wpvdbModels[providerId] || {};
                        
                        // Clear current options
                        $('#wpvdb-test-model').empty();
                        
                        // Add new options
                        $.each(providerModels, function(modelId, modelData) {
                            $('#wpvdb-test-model').append(
                                $('<option>', {
                                    value: modelId,
                                    text: modelData.label
                                })
                            );
                        });
                    });
                });
                </script>
                
                <div class="wpvdb-form-group">
                    <label for="wpvdb-test-text"><?php esc_html_e('Text to Embed', 'wpvdb'); ?></label>
                    <textarea id="wpvdb-test-text" name="text" rows="5" placeholder="<?php esc_attr_e('Enter text to generate embedding for...', 'wpvdb'); ?>"></textarea>
                </div>
                
                <div class="wpvdb-form-actions">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Generate Embedding', 'wpvdb'); ?></button>
                    <button type="button" class="button wpvdb-modal-cancel"><?php esc_html_e('Cancel', 'wpvdb'); ?></button>
                </div>
            </form>
            
            <div id="wpvdb-test-embedding-results" style="display:none; margin-top: 20px;">
                <h3><?php esc_html_e('Results', 'wpvdb'); ?></h3>
                <div class="wpvdb-results-content">
                    <div class="wpvdb-status-message"></div>
                    <div class="wpvdb-embedding-info"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Re-indexing Modal -->
    <div id="wpvdb-reindex-modal" class="wpvdb-modal" style="display:none;">
        <div class="wpvdb-modal-content">
            <span class="wpvdb-modal-close">&times;</span>
            <h2><?php esc_html_e('Re-indexing Content', 'wpvdb'); ?></h2>
            
            <div id="wpvdb-reindex-progress">
                <div class="wpvdb-progress">
                    <div id="wpvdb-reindex-progress-bar" class="wpvdb-progress-bar" style="width: 0%;"></div>
                </div>
                <p id="wpvdb-reindex-message" class="wpvdb-status-message">
                    <?php esc_html_e('Preparing to re-index content...', 'wpvdb'); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<style>
/* WooCommerce-like admin styling */
.wpvdb-status-tabs {
    margin-top: 20px;
}

.wpvdb-tabs {
    display: flex;
    margin: 0;
    padding: 0;
    border-bottom: 1px solid #ccc;
    background: #f7f7f7;
}

.wpvdb-tabs li {
    margin: 0;
    padding: 0;
    list-style: none;
}

.wpvdb-tabs li a {
    display: block;
    padding: 10px 15px;
    text-decoration: none;
    color: #0073aa;
    font-weight: 500;
    border-bottom: 3px solid transparent;
}

.wpvdb-tabs li.active a {
    background: #fff;
    border-bottom-color: #0073aa;
    color: #000;
}

.wpvdb-tab-content {
    background: #fff;
    border: 1px solid #ccc;
    border-top: none;
    padding: 20px;
}

.wpvdb-tab-pane {
    display: none;
}

.wpvdb-tab-pane.active {
    display: block;
}

.wpvdb-tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    grid-gap: 20px;
    margin-top: 20px;
}

.wpvdb-tool-card {
    background: #f8f9fa;
    border: 1px solid #e2e4e7;
    padding: 20px;
    border-radius: 3px;
}

.wpvdb-tool-card h3 {
    margin-top: 0;
    padding-top: 0;
}

.wpvdb-tool-card.danger {
    background: #fff8f7;
    border-color: #d63638;
}

.wpvdb-tool-card.highlight {
    background: #f0f6fc;
    border-color: #0073aa;
}

mark.yes {
    background-color: #e5f9e5;
    color: #0a5f0a;
    padding: 2px 6px;
    border-radius: 3px;
}

mark.error {
    background-color: #ffe9e9;
    color: #a00;
    padding: 2px 6px;
    border-radius: 3px;
}

.notice {
    padding: 10px;
    margin: 10px 0;
    background: #fff;
    border-left: 4px solid #00a32a;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.notice-warning {
    border-left-color: #dba617;
}

.dashicons {
    vertical-align: middle;
}

.pending-provider {
    margin-top: 8px;
    padding: 6px;
    background: #fffbea;
    border-left: 4px solid #f0b849;
    font-size: 13px;
}

.pending-actions {
    margin-top: 15px;
}

/* Re-indexing progress */
.wpvdb-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    z-index: 9999;
    display: none;
}

.wpvdb-modal-content {
    position: relative;
    margin: 100px auto;
    max-width: 500px;
    background: #fff;
    padding: 20px;
    border-radius: 4px;
}

.wpvdb-modal-close {
    position: absolute;
    top: 10px;
    right: 15px;
    font-size: 24px;
    cursor: pointer;
}

.progress-bar {
    height: 20px;
    background: #f0f0f1;
    border-radius: 4px;
    margin: 20px 0;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: #2271b1;
    transition: width 0.3s ease;
}

.progress-status {
    text-align: center;
    margin-bottom: 10px;
    font-weight: bold;
}

.progress-message {
    text-align: center;
    font-style: italic;
    color: #50575e;
}

/* Embedding test styling */
.wpvdb-form-group {
    margin-bottom: 15px;
}

.wpvdb-form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.wpvdb-form-group select,
.wpvdb-form-group textarea {
    width: 100%;
}

.wpvdb-form-actions {
    margin-top: 20px;
}

.wpvdb-results-content {
    background: #f9f9f9;
    padding: 15px;
    border: 1px solid #e5e5e5;
    border-radius: 3px;
}

.embedding-details {
    margin-top: 10px;
}

.embedding-sample {
    background: #f0f0f1;
    padding: 10px;
    overflow: auto;
    max-height: 150px;
    border-radius: 3px;
}

.success-message {
    color: #008a20;
    font-weight: bold;
}

.error-message {
    color: #d63638;
    font-weight: bold;
}

.spinner.is-active {
    visibility: visible;
    margin-right: 5px;
}
</style>

</div>

<!-- Re-index Modal -->
<div id="wpvdb-reindex-modal" class="wpvdb-modal" style="display:none;">
    <div class="wpvdb-modal-content">
        <span class="wpvdb-modal-close">&times;</span>
        <h2><?php esc_html_e('Re-indexing Content', 'wpvdb'); ?></h2>
        
        <div id="wpvdb-reindex-progress">
            <div class="wpvdb-progress">
                <div id="wpvdb-reindex-progress-bar" class="wpvdb-progress-bar" style="width: 0%;"></div>
            </div>
            <p id="wpvdb-reindex-message" class="wpvdb-status-message">
                <?php esc_html_e('Preparing to re-index content...', 'wpvdb'); ?>
            </p>
        </div>
    </div>
</div>

<script type="text/javascript">
function cancelProviderChange(e) {
    e.preventDefault();
    console.log('Cancel button clicked via inline handler');
    
    if (confirm('This will cancel the pending provider change. Are you sure?')) {
        console.log('User confirmed cancel via inline handler');
        
        jQuery.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'wpvdb_confirm_provider_change',
                nonce: '<?php echo wp_create_nonce('wpvdb-admin'); ?>',
                cancel: 'true'
            },
            beforeSend: function() {
                console.log('Sending AJAX request to cancel provider change via inline handler');
                jQuery('#wpvdb-cancel-provider-change, #wpvdb-cancel-provider-change-tool').prop('disabled', true).text('Processing...');
            },
            success: function(response) {
                console.log('AJAX response received:', response);
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                    jQuery('#wpvdb-cancel-provider-change, #wpvdb-cancel-provider-change-tool').prop('disabled', false).text('Cancel Change');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                alert('An error occurred while cancelling the provider change.');
                jQuery('#wpvdb-cancel-provider-change, #wpvdb-cancel-provider-change-tool').prop('disabled', false).text('Cancel Change');
            }
        });
    }
}
</script>

</div> <!-- End of wrap --> 