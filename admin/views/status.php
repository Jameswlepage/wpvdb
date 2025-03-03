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
            <a href="#" id="wpvdb-cancel-provider-change" class="button">
                <?php esc_html_e('Cancel Change', 'wpvdb'); ?>
            </a>
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
    
    // Generate the section navigation
    echo '<div class="wpvdb-section-nav">';
    $i = 0;
    foreach ($sections as $section_id => $section_label) {
        if ($i > 0) {
            echo '<span class="divider">|</span>';
        }
        
        $url = admin_url(sprintf(
            'admin.php?page=wpvdb-status&section=%s',
            esc_attr($section_id)
        ));
        
        $class = ($section === $section_id) ? 'current' : '';
        printf(
            '<a href="%s" class="%s">%s</a>',
            esc_url($url),
            esc_attr($class),
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
                        <th><?php esc_html_e('MySQL Version', 'wpvdb'); ?></th>
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
                <a href="#" id="wpvdb-cancel-provider-change-tool" class="button">
                    <?php esc_html_e('Cancel Change', 'wpvdb'); ?>
                </a>
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
                    <p><?php esc_html_e('Re-create the database tables needed for vector embeddings.', 'wpvdb'); ?></p>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=wpvdb-status&action=recreate_tables'), 'wpvdb_recreate_tables')); ?>" class="button">
                        <?php esc_html_e('Re-Create Tables', 'wpvdb'); ?>
                    </a>
                </div>
                
                <div class="wpvdb-tool-card danger">
                    <h3><?php esc_html_e('Clear All Embeddings', 'wpvdb'); ?></h3>
                    <p><?php esc_html_e('Delete all embeddings from the database. This action cannot be undone!', 'wpvdb'); ?></p>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=wpvdb-status&action=clear_embeddings'), 'wpvdb_clear_embeddings')); ?>" class="button button-primary" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete all embeddings? This action cannot be undone!', 'wpvdb'); ?>');">
                        <?php esc_html_e('Clear All', 'wpvdb'); ?>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Re-indexing Modal -->
    <div id="wpvdb-reindex-modal" class="wpvdb-modal" style="display:none;">
        <div class="wpvdb-modal-content">
            <span class="wpvdb-modal-close">&times;</span>
            <h2><?php esc_html_e('Re-indexing Content', 'wpvdb'); ?></h2>
            <div class="wpvdb-reindex-progress">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 0%"></div>
                </div>
                <div class="progress-status">
                    <span class="processed">0</span> / <span class="total">0</span> <?php esc_html_e('items processed', 'wpvdb'); ?>
                </div>
                <p class="progress-message"><?php esc_html_e('Preparing to re-index content...', 'wpvdb'); ?></p>
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
</style>

<script>
jQuery(document).ready(function($) {
    // Handle provider change confirmation
    $('#wpvdb-apply-provider-change').on('click', function(e) {
        e.preventDefault();
        
        if (confirm('<?php esc_attr_e('This will delete all existing embeddings and activate the new provider. Are you sure you want to continue?', 'wpvdb'); ?>')) {
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'wpvdb_confirm_provider_change',
                    nonce: wpvdb.nonce,
                    cancel: false
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data.message || 'An error occurred');
                    }
                },
                error: function() {
                    alert('<?php esc_attr_e('An unexpected error occurred', 'wpvdb'); ?>');
                }
            });
        }
    });
    
    // Handle provider change cancellation
    $('#wpvdb-cancel-provider-change').on('click', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'wpvdb_confirm_provider_change',
                nonce: wpvdb.nonce,
                cancel: true
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                }
            }
        });
    });
    
    // Re-index content with pending provider/model
    $('#wpvdb-reindex-content').on('click', function() {
        var provider = $(this).data('provider');
        var model = $(this).data('model');
        
        // Show modal
        $('#wpvdb-reindex-modal').show();
        
        // Get all posts to re-index
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'wpvdb_get_posts_for_indexing',
                nonce: wpvdb.nonce
            },
            success: function(response) {
                if (response.success && response.data.posts) {
                    var posts = response.data.posts;
                    $('.total').text(posts.length);
                    
                    // Process in batches
                    processBatch(posts, 0, 5, provider, model);
                } else {
                    $('.progress-message').text('<?php esc_attr_e('No content found to index', 'wpvdb'); ?>');
                }
            }
        });
    });
    
    // Process posts in batches
    function processBatch(allPosts, startIndex, batchSize, provider, model) {
        if (startIndex >= allPosts.length) {
            // All done
            $('.progress-fill').css('width', '100%');
            $('.progress-message').text('<?php esc_attr_e('Re-indexing complete!', 'wpvdb'); ?>');
            
            // If we were re-indexing due to a provider change, apply the change
            <?php if ($has_pending_change): ?>
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'wpvdb_confirm_provider_change',
                    nonce: wpvdb.nonce,
                    cancel: false
                },
                success: function(response) {
                    if (response.success) {
                        setTimeout(function() {
                            alert(response.data.message);
                            location.reload();
                        }, 1000);
                    }
                }
            });
            <?php else: ?>
            setTimeout(function() {
                location.reload();
            }, 2000);
            <?php endif; ?>
            
            return;
        }
        
        // Get current batch
        var endIndex = Math.min(startIndex + batchSize, allPosts.length);
        var currentBatch = allPosts.slice(startIndex, endIndex);
        var postIds = currentBatch.map(function(post) { return post.id; });
        
        // Update UI
        var percentComplete = Math.round((endIndex / allPosts.length) * 100);
        $('.progress-fill').css('width', percentComplete + '%');
        $('.processed').text(endIndex);
        $('.progress-message').text('<?php esc_attr_e('Processing posts...', 'wpvdb'); ?>');
        
        // Process batch
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'wpvdb_bulk_embed',
                nonce: wpvdb.nonce,
                post_ids: postIds,
                provider: provider,
                model: model
            },
            success: function() {
                // Process next batch
                processBatch(allPosts, endIndex, batchSize, provider, model);
            },
            error: function() {
                $('.progress-message').text('<?php esc_attr_e('An error occurred. Retrying...', 'wpvdb'); ?>');
                setTimeout(function() {
                    processBatch(allPosts, startIndex, batchSize, provider, model);
                }, 3000);
            }
        });
    }
    
    // Close modal
    $('.wpvdb-modal-close').on('click', function() {
        $(this).closest('.wpvdb-modal').hide();
    });
    
    // Standard refresh content
    $('#wpvdb-refresh-content').on('click', function() {
        var activeProvider = '<?php echo esc_js($embedding_info['active_provider'] ?? ''); ?>';
        var activeModel = '<?php echo esc_js($embedding_info['active_model'] ?? ''); ?>';
        
        if (activeProvider && activeModel) {
            // Show modal
            $('#wpvdb-reindex-modal').show();
            
            // Get all posts to re-index
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'wpvdb_get_posts_for_indexing',
                    nonce: wpvdb.nonce
                },
                success: function(response) {
                    if (response.success && response.data.posts) {
                        var posts = response.data.posts;
                        $('.total').text(posts.length);
                        
                        // Process in batches
                        processBatch(posts, 0, 5, activeProvider, activeModel);
                    } else {
                        $('.progress-message').text('<?php esc_attr_e('No content found to index', 'wpvdb'); ?>');
                    }
                }
            });
        }
    });
});
</script> 