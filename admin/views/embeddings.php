<div class="wrap wpvdb-embeddings">
    <h1><?php esc_html_e('Vector Database Embeddings', 'wpvdb'); ?></h1>
    
    <div class="wpvdb-toolbar">
        <div class="wpvdb-search">
            <form method="get">
                <input type="hidden" name="page" value="wpvdb-embeddings">
                <input type="search" 
                       name="s" 
                       value="<?php echo isset($_GET['s']) ? esc_attr($_GET['s']) : ''; ?>" 
                       placeholder="<?php esc_attr_e('Search embeddings...', 'wpvdb'); ?>">
                <button type="submit" class="button"><?php esc_html_e('Search', 'wpvdb'); ?></button>
            </form>
        </div>
        
        <div class="wpvdb-actions">
            <button id="wpvdb-bulk-embed" class="button button-primary">
                <?php esc_html_e('Bulk Generate Embeddings', 'wpvdb'); ?>
            </button>
        </div>
    </div>
    
    <?php if (empty($embeddings)) : ?>
        <div class="wpvdb-no-data">
            <div class="notice notice-info inline">
                <p><?php esc_html_e('No embeddings found. Use the "Bulk Generate Embeddings" button to create embeddings for your content.', 'wpvdb'); ?></p>
            </div>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="column-id"><?php esc_html_e('ID', 'wpvdb'); ?></th>
                    <th class="column-document"><?php esc_html_e('Document', 'wpvdb'); ?></th>
                    <th class="column-chunk"><?php esc_html_e('Chunk', 'wpvdb'); ?></th>
                    <th class="column-preview"><?php esc_html_e('Preview', 'wpvdb'); ?></th>
                    <th class="column-summary"><?php esc_html_e('Summary', 'wpvdb'); ?></th>
                    <th class="column-actions"><?php esc_html_e('Actions', 'wpvdb'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($embeddings as $embedding) : 
                    $post_title = get_the_title($embedding->doc_id);
                    $post_edit_link = get_edit_post_link($embedding->doc_id);
                ?>
                    <tr>
                        <td class="column-id"><?php echo esc_html($embedding->id); ?></td>
                        <td class="column-document">
                            <?php if ($post_edit_link) : ?>
                                <a href="<?php echo esc_url($post_edit_link); ?>" target="_blank">
                                    <?php echo esc_html($post_title ?: __('(No title)', 'wpvdb')); ?> 
                                    <span class="dashicons dashicons-external"></span>
                                </a>
                            <?php else : ?>
                                <?php echo esc_html($post_title ?: __('(No title)', 'wpvdb')); ?>
                            <?php endif; ?>
                            <div class="row-actions">
                                <span class="id"><?php printf(__('Post ID: %d', 'wpvdb'), $embedding->doc_id); ?></span>
                            </div>
                        </td>
                        <td class="column-chunk"><?php echo esc_html($embedding->chunk_id); ?></td>
                        <td class="column-preview">
                            <div class="wpvdb-preview">
                                <?php echo esc_html($embedding->preview); ?>...
                                <button class="wpvdb-view-full button-link" 
                                       data-id="<?php echo esc_attr($embedding->id); ?>">
                                    <?php esc_html_e('View Full', 'wpvdb'); ?>
                                </button>
                            </div>
                        </td>
                        <td class="column-summary"><?php echo esc_html($embedding->summary); ?></td>
                        <td class="column-actions">
                            <a href="#" class="wpvdb-delete-embedding" 
                               data-id="<?php echo esc_attr($embedding->id); ?>">
                                <?php esc_html_e('Delete', 'wpvdb'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if ($total_pages > 1) : ?>
            <div class="wpvdb-pagination tablenav">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo; Previous'),
                        'next_text' => __('Next &raquo;'),
                        'total' => $total_pages,
                        'current' => $page,
                        'type' => 'list',
                    ]);
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <div id="wpvdb-full-content-modal" class="wpvdb-modal" style="display:none;">
        <div class="wpvdb-modal-content">
            <span class="wpvdb-modal-close">&times;</span>
            <h2><?php esc_html_e('Full Content', 'wpvdb'); ?></h2>
            <div class="wpvdb-full-content"></div>
        </div>
    </div>
    
    <div id="wpvdb-bulk-embed-modal" class="wpvdb-modal" style="display:none;">
        <div class="wpvdb-modal-content">
            <span class="wpvdb-modal-close">&times;</span>
            <h2><?php esc_html_e('Bulk Embed Content', 'wpvdb'); ?></h2>
            
            <form id="wpvdb-bulk-embed-form">
                <div class="wpvdb-form-group">
                    <label for="wpvdb-provider"><?php esc_html_e('AI Provider', 'wpvdb'); ?></label>
                    <select id="wpvdb-provider" name="provider">
                        <?php 
                        $settings = get_option('wpvdb_settings');
                        $current_provider = $settings['provider'] ?? 'openai';
                        $providers = [
                            'openai' => __('OpenAI', 'wpvdb'),
                            'automattic' => __('Automattic AI', 'wpvdb'),
                        ];
                        
                        foreach ($providers as $id => $name) {
                            printf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr($id),
                                selected($current_provider, $id, false),
                                esc_html($name)
                            );
                        }
                        ?>
                    </select>
                </div>

                <div class="wpvdb-form-group" id="openai-models" <?php echo $current_provider !== 'openai' ? 'style="display:none;"' : ''; ?>>
                    <label for="wpvdb-openai-model"><?php esc_html_e('OpenAI Model', 'wpvdb'); ?></label>
                    <select id="wpvdb-openai-model" name="model">
                        <?php 
                        $default_model = $settings['openai']['default_model'] ?? 'text-embedding-3-small';
                        $models = [
                            'text-embedding-3-small' => 'text-embedding-3-small (Recommended)',
                            'text-embedding-3-large' => 'text-embedding-3-large (Higher Accuracy)',
                            'text-embedding-ada-002' => 'text-embedding-ada-002 (Legacy)'
                        ];
                        
                        foreach ($models as $model_id => $model_name) {
                            printf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr($model_id),
                                selected($default_model, $model_id, false),
                                esc_html($model_name)
                            );
                        }
                        ?>
                    </select>
                </div>

                <div class="wpvdb-form-group" id="automattic-models" <?php echo $current_provider !== 'automattic' ? 'style="display:none;"' : ''; ?>>
                    <label for="wpvdb-automattic-model"><?php esc_html_e('Automattic Model', 'wpvdb'); ?></label>
                    <select id="wpvdb-automattic-model" name="model">
                        <?php 
                        $default_model = $settings['automattic']['default_model'] ?? 'automattic-embeddings-001';
                        $models = [
                            'automattic-embeddings-001' => 'automattic-embeddings-001'
                        ];
                        
                        foreach ($models as $model_id => $model_name) {
                            printf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr($model_id),
                                selected($default_model, $model_id, false),
                                esc_html($model_name)
                            );
                        }
                        ?>
                    </select>
                </div>
                
                <div class="wpvdb-form-group">
                    <label for="wpvdb-post-type"><?php esc_html_e('Post Type', 'wpvdb'); ?></label>
                    <select id="wpvdb-post-type" name="post_type">
                        <?php 
                        $post_types = get_post_types(['public' => true], 'objects');
                        foreach ($post_types as $pt) {
                            echo '<option value="' . esc_attr($pt->name) . '">' . esc_html($pt->label) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <div class="wpvdb-form-group">
                    <label for="wpvdb-limit"><?php esc_html_e('Limit', 'wpvdb'); ?></label>
                    <input type="number" id="wpvdb-limit" name="limit" min="1" max="100" value="10">
                    <p class="description">
                        <?php esc_html_e('Maximum number of posts to process at once', 'wpvdb'); ?>
                    </p>
                </div>
                
                <div class="wpvdb-form-actions">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Start Processing', 'wpvdb'); ?></button>
                    <button type="button" class="button wpvdb-modal-cancel"><?php esc_html_e('Cancel', 'wpvdb'); ?></button>
                </div>
            </form>
            
            <div id="wpvdb-bulk-embed-results" style="display:none;">
                <div class="wpvdb-progress">
                    <div class="wpvdb-progress-bar" style="width: 0%;"></div>
                </div>
                <p class="wpvdb-status-message"></p>
            </div>
        </div>
    </div>
</div>

<style>
/* WooCommerce-like styling */
.wpvdb-toolbar {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
    padding: 12px;
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.wpvdb-search input[type="search"] {
    margin-right: 5px;
    min-width: 250px;
}

.wpvdb-no-data {
    margin: 40px 0;
    text-align: center;
}

.wpvdb-preview {
    max-width: 300px;
    word-break: break-word;
}

/* Column widths */
.column-id {
    width: 70px;
}
.column-document {
    width: 20%;
}
.column-chunk {
    width: 70px;
}
.column-actions {
    width: 100px;
}

/* Modal styling */
.wpvdb-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    z-index: 9999;
    overflow-y: auto;
    padding: 50px 0;
}

.wpvdb-modal-content {
    position: relative;
    max-width: 600px;
    margin: 0 auto;
    background: #fff;
    padding: 30px;
    border-radius: 3px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
}

.wpvdb-modal-close {
    position: absolute;
    top: 10px;
    right: 15px;
    font-size: 24px;
    cursor: pointer;
}

.wpvdb-form-group {
    margin-bottom: 20px;
}

.wpvdb-form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.wpvdb-form-group select,
.wpvdb-form-group input {
    width: 100%;
}

.wpvdb-form-actions {
    margin-top: 20px;
    text-align: right;
}

.wpvdb-form-actions .button {
    margin-left: 10px;
}

.wpvdb-progress {
    height: 20px;
    background: #f0f0f1;
    margin: 20px 0;
    border-radius: 3px;
}

.wpvdb-progress-bar {
    height: 100%;
    background: #2271b1;
    border-radius: 3px;
    transition: width 0.3s ease;
}

.wpvdb-full-content {
    max-height: 400px;
    overflow-y: auto;
    padding: 15px;
    background: #f0f0f1;
    border-radius: 3px;
    margin-top: 20px;
    white-space: pre-wrap;
}

/* Description text */
.description {
    color: #646970;
    font-size: 13px;
    font-style: italic;
    margin: 5px 0 0;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle model dropdowns based on provider selection
    $('#wpvdb-provider').on('change', function() {
        var provider = $(this).val();
        if (provider === 'openai') {
            $('#openai-models').show();
            $('#automattic-models').hide();
        } else if (provider === 'automattic') {
            $('#openai-models').hide();
            $('#automattic-models').show();
        }
    });
    
    // Bulk Generate Embeddings button click handler
    $('#wpvdb-bulk-embed').on('click', function() {
        $('#wpvdb-bulk-embed-modal').show();
    });
    
    // Modal close button handler
    $('.wpvdb-modal-close, .wpvdb-modal-cancel').on('click', function() {
        $('.wpvdb-modal').hide();
    });
    
    // Form submission handler
    $('#wpvdb-bulk-embed-form').on('submit', function(e) {
        e.preventDefault();
        
        // Hide the form and show the results section
        $(this).hide();
        $('#wpvdb-bulk-embed-results').show();
        
        var postType = $('#wpvdb-post-type').val();
        var limit = $('#wpvdb-limit').val();
        var provider = $('#wpvdb-provider').val();
        var model = provider === 'openai' ? $('#wpvdb-openai-model').val() : $('#wpvdb-automattic-model').val();
        
        // Update the status message
        $('.wpvdb-status-message').text('Starting bulk embedding process...');
        $('.wpvdb-progress-bar').css('width', '25%');
        
        // First get the post IDs to process
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpvdb_get_posts_for_indexing',
                nonce: '<?php echo wp_create_nonce('wpvdb-admin'); ?>',
                post_type: postType,
                limit: limit
            },
            success: function(response) {
                if (response.success && response.data.posts && response.data.posts.length > 0) {
                    // Extract post IDs from the posts array
                    var postIds = response.data.posts.map(function(post) {
                        return post.id;
                    });
                    
                    // We have post IDs, now start the embedding process
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'wpvdb_bulk_embed',
                            nonce: '<?php echo wp_create_nonce('wpvdb-admin'); ?>',
                            post_ids: postIds,
                            provider: provider,
                            model: model
                        },
                        success: function(embedResponse) {
                            if (embedResponse.success) {
                                // Update progress bar to 100%
                                $('.wpvdb-progress-bar').css('width', '100%');
                                $('.wpvdb-status-message').html(embedResponse.data.message);
                                
                                // Refresh the page after 2 seconds to show the new embeddings
                                setTimeout(function() {
                                    window.location.reload();
                                }, 2000);
                            } else {
                                $('.wpvdb-progress-bar').css('width', '0%');
                                $('.wpvdb-status-message').html('Error: ' + (embedResponse.data.message || 'Unknown error'));
                            }
                        },
                        error: function() {
                            $('.wpvdb-progress-bar').css('width', '0%');
                            $('.wpvdb-status-message').html('An unexpected error occurred during embedding process. Please try again.');
                        },
                        beforeSend: function() {
                            // Update progress bar to 50% when starting the embedding
                            $('.wpvdb-progress-bar').css('width', '50%');
                            $('.wpvdb-status-message').text('Processing ' + postIds.length + ' posts...');
                        }
                    });
                } else {
                    $('.wpvdb-status-message').html('Error: No posts found for the selected criteria.');
                    
                    // Show the form again after 3 seconds
                    setTimeout(function() {
                        $('#wpvdb-bulk-embed-results').hide();
                        $('#wpvdb-bulk-embed-form').show();
                    }, 3000);
                }
            },
            error: function() {
                $('.wpvdb-status-message').html('An unexpected error occurred while getting posts. Please try again.');
                
                // Show the form again after 3 seconds
                setTimeout(function() {
                    $('#wpvdb-bulk-embed-results').hide();
                    $('#wpvdb-bulk-embed-form').show();
                }, 3000);
            }
        });
    });
});
</script> 